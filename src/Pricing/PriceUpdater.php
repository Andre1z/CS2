<?php

declare(strict_types=1);

namespace CS2\Pricing;

class PriceUpdater
{
    private const BASE_URL =
        'https://prices.csgotrader.app/latest/';

    private const EXCHANGE_RATES_URL =
        'https://prices.csgotrader.app/latest/exchange_rates.json';

    /**
     * Los feeds de CSGO Trader están expresados principalmente
     * en USD. Nosotros convertimos todo a EUR.
     */
    private const SOURCE_CURRENCY = 'USD';

    private const TARGET_CURRENCY = 'EUR';

    /**
     * Proveedores disponibles en los feeds de CSGO Trader.
     *
     * Si alguno no está disponible, se ignora y se utiliza
     * la caché anterior si existe.
     */
    private const PROVIDERS = [
        'steam',
        'skinport',
        'csgotrader',
        'csmoney',
        'cstrade',
        'lisskins',
        'lootfarm',
        'csgotm',
        'csgoempire',
        'swapgg',
        'buff163',
        'youpin898',
        'csfloat',
        'c5game',
    ];

    private PriceCache $cache;

    public function __construct()
    {
        $this->cache = new PriceCache();
    }

    public function update(): array
    {
        $exchangeRates =
            $this->getExchangeRates();

        $usdToEur =
            $this->getUsdToEurRate(
                $exchangeRates
            );

        $allPrices = [];

        $updatedProviders = [];

        foreach (self::PROVIDERS as $provider) {
            try {
                $data =
                    $this->downloadJson(
                        self::BASE_URL
                        . $provider
                        . '.json'
                    );

                if (!is_array($data)) {
                    throw new \RuntimeException(
                        'Respuesta inválida.'
                    );
                }

                $this->cache->saveProviderPrices(
                    $provider,
                    $data
                );

                $providerPrices =
                    $this->extractProviderPrices(
                        $provider,
                        $data,
                        $usdToEur
                    );

                foreach (
                    $providerPrices as $marketHashName => $price
                ) {
                    if ($price <= 0) {
                        continue;
                    }

                    $allPrices[
                        $marketHashName
                    ][] = [
                        'provider' => $provider,
                        'price' => $price
                    ];
                }

                $updatedProviders[] =
                    $provider;

            } catch (\Throwable $e) {

                /*
                 * Si un proveedor falla, utilizamos
                 * la última versión guardada.
                 */
                $cached =
                    $this->cache
                        ->getProviderPrices(
                            $provider
                        );

                if (
                    !empty($cached)
                    && is_array($cached)
                ) {
                    $providerPrices =
                        $this->extractProviderPrices(
                            $provider,
                            $cached,
                            $usdToEur
                        );

                    foreach (
                        $providerPrices
                        as $marketHashName => $price
                    ) {
                        if ($price <= 0) {
                            continue;
                        }

                        $allPrices[
                            $marketHashName
                        ][] = [
                            'provider' => $provider,
                            'price' => $price
                        ];
                    }

                    $updatedProviders[] =
                        $provider . ' (cache)';
                }
            }
        }

        $calculated = [];

        foreach (
            $allPrices as $marketHashName => $entries
        ) {
            $result =
                $this->calculateReasonablePrice(
                    $entries
                );

            if ($result === null) {
                continue;
            }

            $calculated[
                $marketHashName
            ] = $result;
        }

        /*
         * Ordenamos para que el JSON sea estable.
         * Así la versión solamente cambia cuando
         * realmente cambian los precios.
         */
        ksort($calculated);

        $this->cache
            ->saveCalculatedPrices(
                $calculated
            );

        $version =
            hash(
                'sha256',
                json_encode(
                    $calculated,
                    JSON_UNESCAPED_UNICODE
                    | JSON_UNESCAPED_SLASHES
                )
            );

        $this->cache
            ->saveMetadata([
                'version' =>
                    $version,

                'updated_at' =>
                    time(),

                'currency' =>
                    self::TARGET_CURRENCY,

                'providers' =>
                    $updatedProviders,

                'algorithm' =>
                    'reasonable-median-v2'
            ]);

        return [
            'version' =>
                $version,

            'providers' =>
                $updatedProviders,

            'items' =>
                count($calculated)
        ];
    }

    /**
     * Descarga JSON desde CSGO Trader.
     */
    private function downloadJson(
        string $url
    ): array {
        $ch =
            curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER =>
                true,

            CURLOPT_TIMEOUT =>
                30,

            CURLOPT_CONNECTTIMEOUT =>
                10,

            CURLOPT_FOLLOWLOCATION =>
                true,

            CURLOPT_SSL_VERIFYPEER =>
                true,

            CURLOPT_HTTPHEADER => [
                'Accept: application/json'
            ],

            CURLOPT_USERAGENT =>
                'CS2Inventory/1.0'
        ]);

        $response =
            curl_exec($ch);

        $httpCode =
            curl_getinfo(
                $ch,
                CURLINFO_HTTP_CODE
            );

        $error =
            curl_error($ch);

        curl_close($ch);

        if ($response === false) {
            throw new \RuntimeException(
                'Error CURL: '
                . $error
            );
        }

        if ($httpCode !== 200) {
            throw new \RuntimeException(
                'HTTP '
                . $httpCode
                . ' al descargar '
                . $url
            );
        }

        $data =
            json_decode(
                $response,
                true
            );

        if (!is_array($data)) {
            throw new \RuntimeException(
                'JSON inválido recibido desde '
                . $url
            );
        }

        return $data;
    }

    /**
     * Obtiene los tipos de cambio.
     */
    private function getExchangeRates(): array
    {
        return $this->downloadJson(
            self::EXCHANGE_RATES_URL
        );
    }

    /**
     * Obtiene USD -> EUR intentando soportar
     * las diferentes estructuras posibles del feed.
     */
    private function getUsdToEurRate(
        array $data
    ): float {
        /*
         * Formato:
         * {
         *   "EUR": 0.85
         * }
         */
        if (
            isset($data['EUR'])
            && is_numeric($data['EUR'])
            && (float) $data['EUR'] > 0
        ) {
            return (float) $data['EUR'];
        }

        /*
         * Formato:
         * {
         *   "rates": {
         *       "EUR": 0.85
         *   }
         * }
         */
        if (
            isset($data['rates']['EUR'])
            && is_numeric(
                $data['rates']['EUR']
            )
            && (float) $data['rates']['EUR'] > 0
        ) {
            return (float)
                $data['rates']['EUR'];
        }

        /*
         * Formato:
         * {
         *   "USD": {
         *       "EUR": 0.85
         *   }
         * }
         */
        if (
            isset($data['USD']['EUR'])
            && is_numeric(
                $data['USD']['EUR']
            )
            && (float) $data['USD']['EUR'] > 0
        ) {
            return (float)
                $data['USD']['EUR'];
        }

        /*
         * Si el feed está expresado al revés:
         *
         * EUR -> USD
         */
        if (
            isset($data['EUR']['USD'])
            && is_numeric(
                $data['EUR']['USD']
            )
            && (float) $data['EUR']['USD'] > 0
        ) {
            return 1 /
                (float) $data['EUR']['USD'];
        }

        throw new \RuntimeException(
            'No se ha podido encontrar el cambio USD -> EUR.'
        );
    }

    /**
     * Extrae los precios de un proveedor.
     */
    private function extractProviderPrices(
        string $provider,
        array $data,
        float $usdToEur
    ): array {
        $result = [];

        foreach (
            $data as $marketHashName => $entry
        ) {
            if (
                !is_string($marketHashName)
                || $marketHashName === ''
            ) {
                continue;
            }

            $price =
                $this->extractPriceFromEntry(
                    $provider,
                    $entry
                );

            if ($price === null) {
                continue;
            }

            /*
             * Los feeds pueden contener precios
             * en USD. Los convertimos a EUR.
             */
            $price *= $usdToEur;

            if (
                !is_finite($price)
                || $price <= 0
            ) {
                continue;
            }

            /*
             * Evitamos errores absurdos del feed.
             */
            if ($price > 1000000) {
                continue;
            }

            $result[
                $marketHashName
            ] = round(
                $price,
                4
            );
        }

        return $result;
    }

    /**
     * Extrae un precio de las distintas estructuras
     * que utiliza CSGO Trader.
     */
    private function extractPriceFromEntry(
        string $provider,
        mixed $entry
    ): ?float {
        if (is_numeric($entry)) {
            $value =
                (float) $entry;

            return $value > 0
                ? $value
                : null;
        }

        if (!is_array($entry)) {
            return null;
        }

        /*
         * Proveedores que directamente utilizan:
         *
         * {
         *   "price": 123
         * }
         */
        if (
            isset($entry['price'])
            && is_numeric($entry['price'])
        ) {
            $value =
                (float) $entry['price'];

            if ($value > 0) {
                return $value;
            }
        }

        /*
         * steam / skinport / buff / csfloat
         *
         * {
         *   "starting_at": {
         *       "price": 123
         *   }
         * }
         */
        $preferredKeys = [
            'starting_at',
            'lowest',
            'lowest_price',
            'median',
            'average',
            'avg'
        ];

        foreach (
            $preferredKeys as $key
        ) {
            if (
                !array_key_exists(
                    $key,
                    $entry
                )
            ) {
                continue;
            }

            $value =
                $this->extractNumericValue(
                    $entry[$key]
                );

            if (
                $value !== null
                && $value > 0
            ) {
                return $value;
            }
        }

        /*
         * Algunos feeds contienen estructuras
         * como:
         *
         * "steam": {
         *     "starting_at": {
         *         "price": 123
         *     }
         * }
         */
        foreach (
            $entry as $value
        ) {
            if (!is_array($value)) {
                continue;
            }

            $price =
                $this->extractNumericValue(
                    $value
                );

            if (
                $price !== null
                && $price > 0
            ) {
                return $price;
            }
        }

        return null;
    }

    /**
     * Busca un número dentro de una estructura de precio.
     */
    private function extractNumericValue(
        mixed $value
    ): ?float {
        if (is_numeric($value)) {
            $number =
                (float) $value;

            return $number > 0
                ? $number
                : null;
        }

        if (!is_array($value)) {
            return null;
        }

        if (
            isset($value['price'])
            && is_numeric($value['price'])
        ) {
            $number =
                (float) $value['price'];

            return $number > 0
                ? $number
                : null;
        }

        if (
            isset($value['value'])
            && is_numeric($value['value'])
        ) {
            $number =
                (float) $value['value'];

            return $number > 0
                ? $number
                : null;
        }

        return null;
    }

    /**
     * Calcula el precio "razonable".
     *
     * No utilizamos una media simple porque los mercados
     * baratos pueden arrastrar demasiado el resultado.
     *
     * Algoritmo:
     *
     * 1. Ordenamos los precios.
     * 2. Calculamos la mediana.
     * 3. Eliminamos precios que estén muy alejados
     *    de esa mediana.
     * 4. Volvemos a calcular la mediana.
     *
     * Resultado:
     * un precio central del mercado.
     */
    private function calculateReasonablePrice(
        array $entries
    ): ?array {
        $values = [];

        foreach ($entries as $entry) {
            if (
                !isset($entry['price'])
                || !is_numeric($entry['price'])
            ) {
                continue;
            }

            $price =
                (float) $entry['price'];

            if (
                $price > 0
                && is_finite($price)
            ) {
                $values[] =
                    $price;
            }
        }

        if (empty($values)) {
            return null;
        }

        sort($values, SORT_NUMERIC);

        $totalSources =
            count($values);

        $rawMin =
            min($values);

        $rawMax =
            max($values);

        /*
         * Con una sola fuente no hay forma de hacer
         * una comparación entre mercados.
         */
        if ($totalSources === 1) {
            $reasonableValues =
                $values;
        } else {
            $median =
                $this->median($values);

            /*
             * Permitimos un rango amplio alrededor
             * de la mediana.
             *
             * 0.60 = no aceptar precios < 60% de la mediana.
             * 1.60 = no aceptar precios > 160% de la mediana.
             *
             * Esto elimina auténticos outliers sin
             * cargarnos diferencias normales entre mercados.
             */
            $lowerLimit =
                $median * 0.60;

            $upperLimit =
                $median * 1.60;

            $reasonableValues = [];

            foreach ($values as $value) {
                if (
                    $value >= $lowerLimit
                    && $value <= $upperLimit
                ) {
                    $reasonableValues[] =
                        $value;
                }
            }

            /*
             * Si el filtro fuera demasiado agresivo,
             * recuperamos todos los valores.
             */
            if (
                count($reasonableValues) < 2
            ) {
                $reasonableValues =
                    $values;
            }

            sort(
                $reasonableValues,
                SORT_NUMERIC
            );
        }

        /*
         * La mediana es nuestro precio final.
         *
         * Esto es intencionado:
         *
         * 50 €
         * 80 €
         * 95 €
         * 100 €
         * 500 €
         *
         * -> 95 €
         *
         * Los extremos no dominan el precio.
         */
        $reasonablePrice =
            $this->median(
                $reasonableValues
            );

        $reasonableMin =
            min($reasonableValues);

        $reasonableMax =
            max($reasonableValues);

        $providers = [];

        foreach ($entries as $entry) {
            if (
                isset($entry['provider'])
            ) {
                $providers[] =
                    $entry['provider'];
            }
        }

        $providers =
            array_values(
                array_unique($providers)
            );

        return [
            /*
             * Mantenemos "average" porque PriceService
             * y el frontend ya utilizan este campo.
             */
            'average' =>
                round(
                    $reasonablePrice,
                    2
                ),

            'median' =>
                round(
                    $reasonablePrice,
                    2
                ),

            'min' =>
                round(
                    $reasonableMin,
                    2
                ),

            'max' =>
                round(
                    $reasonableMax,
                    2
                ),

            'count' =>
                count($reasonableValues),

            'total_sources' =>
                $totalSources,

            'providers' =>
                $providers,

            'prices' =>
                array_map(
                    static function (
                        float $value
                    ): float {
                        return round(
                            $value,
                            2
                        );
                    },
                    $reasonableValues
                )
        ];
    }

    /**
     * Mediana de una lista numérica.
     */
    private function median(
        array $values
    ): float {
        if (empty($values)) {
            return 0.0;
        }

        sort(
            $values,
            SORT_NUMERIC
        );

        $count =
            count($values);

        $middle =
            intdiv(
                $count,
                2
            );

        if (
            $count % 2 === 0
        ) {
            return (
                $values[$middle - 1]
                + $values[$middle]
            ) / 2;
        }

        return
            $values[$middle];
    }
}