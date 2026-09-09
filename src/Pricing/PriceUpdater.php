<?php

declare(strict_types=1);

namespace CS2\Pricing;

class PriceUpdater
{
    private const BASE_URL =
        'https://prices.csgotrader.app/latest/';

    /*
     * CSGO Trader utiliza este feed para sus tipos de cambio.
     * El precio de los proveedores sigue viniendo exclusivamente
     * de prices.csgotrader.app.
     */
    private const EXCHANGE_RATES_URL =
        'https://prices.csgotrader.app/latest/exchange_rates.json';

    private const SOURCE_CURRENCY = 'USD';

    private const TARGET_CURRENCY = 'EUR';

    /*
     * Proveedores de precios.
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
        $this->cache =
            new PriceCache();
    }

    /**
     * Actualiza todos los precios.
     */
    public function update(): array
    {
        /*
         * Obtenemos el cambio USD -> EUR.
         */
        $exchangeRates =
            $this->getExchangeRates();

        $usdToEur =
            $this->getUsdToEurRate(
                $exchangeRates
            );

        /*
         * Aquí almacenaremos todos los precios
         * encontrados por market_hash_name.
         *
         * Ejemplo:
         *
         * [
         *     "AK-47 | Redline" => [
         *         [
         *             "provider" => "skinport",
         *             "price" => 95.20
         *         ],
         *         [
         *             "provider" => "csgotrader",
         *             "price" => 102.50
         *         ]
         *     ]
         * ]
         */
        $allPrices = [];

        $updatedProviders = [];

        foreach (
            self::PROVIDERS as $provider
        ) {
            try {
                $data =
                    $this->downloadJson(
                        self::BASE_URL
                        . $provider
                        . '.json'
                    );

                /*
                 * Guardamos la respuesta original.
                 */
                $this->cache
                    ->saveProviderPrices(
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
                    $providerPrices
                    as $marketHashName => $price
                ) {
                    if ($price <= 0) {
                        continue;
                    }

                    $allPrices[
                        $marketHashName
                    ][] = [
                        'provider' =>
                            $provider,

                        'price' =>
                            $price
                    ];
                }

                $updatedProviders[] =
                    $provider;

            } catch (\Throwable $e) {

                /*
                 * Si un proveedor falla, utilizamos
                 * su última caché disponible.
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
                    try {
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
                                'provider' =>
                                    $provider,

                                'price' =>
                                    $price
                            ];
                        }

                        $updatedProviders[] =
                            $provider . ' (cache)';

                    } catch (\Throwable $cacheError) {
                        /*
                         * Si también falla la caché,
                         * simplemente ignoramos ese proveedor.
                         */
                    }
                }
            }
        }

        /*
         * Calculamos el precio final de cada objeto.
         */
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
         * Orden estable del JSON.
         */
        ksort($calculated);

        /*
         * Guardamos los precios calculados.
         */
        $this->cache
            ->saveCalculatedPrices(
                $calculated
            );

        /*
         * Generamos una versión a partir del contenido
         * real de los precios.
         */
        $encodedPrices =
            json_encode(
                $calculated,
                JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
            );

        if ($encodedPrices === false) {
            throw new \RuntimeException(
                'No se pudo generar la versión de precios.'
            );
        }

        $version =
            hash(
                'sha256',
                $encodedPrices
            );

        /*
         * Guardamos metadata.
         */
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
                    'remove-2-cheapest-plus-outliers-v3'
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
     * Descarga un JSON.
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
     * Obtiene el JSON de tipos de cambio.
     */
    private function getExchangeRates(): array
    {
        return $this->downloadJson(
            self::EXCHANGE_RATES_URL
        );
    }

    /**
     * Obtiene USD -> EUR.
     *
     * CSGO Trader utiliza directamente:
     *
     * exchangeRatesJSON[currency]
     *
     * según su propia extensión.
     *
     * Intentamos soportar varias estructuras por seguridad.
     */
    private function getUsdToEurRate(
        array $data
    ): float {
        /*
         * Formato:
         *
         * {
         *     "EUR": 0.85
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
         *
         * {
         *     "rates": {
         *         "EUR": 0.85
         *     }
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
         *
         * {
         *     "USD": {
         *         "EUR": 0.85
         *     }
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
         * Formato inverso:
         *
         * {
         *     "EUR": {
         *         "USD": 1.17
         *     }
         * }
         */
        if (
            isset($data['EUR']['USD'])
            && is_numeric(
                $data['EUR']['USD']
            )
            && (float) $data['EUR']['USD'] > 0
        ) {
            return
                1 /
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
             * Los precios de los feeds se convierten
             * a EUR.
             */
            $price *=
                $usdToEur;

            if (
                !is_finite($price)
                || $price <= 0
            ) {
                continue;
            }

            /*
             * Protección contra datos corruptos.
             */
            if ($price > 1000000) {
                continue;
            }

            $result[
                $marketHashName
            ] =
                round(
                    $price,
                    4
                );
        }

        return $result;
    }

    /**
     * Extrae un precio de una entrada de proveedor.
     */
    private function extractPriceFromEntry(
        string $provider,
        mixed $entry
    ): ?float {
        /*
         * Algunos proveedores devuelven directamente:
         *
         * "AK-47 | Example": 100
         */
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
         * Proveedores:
         *
         * csmoney
         * csgotrader
         * cstrade
         * lisskins
         *
         * utilizan:
         *
         * {
         *     "price": 123
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
         * steam / skinport
         *
         * Buscamos los modos disponibles.
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
         * Algunos proveedores utilizan:
         *
         * {
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
     * Extrae un número de diferentes estructuras.
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
     * Calcula el precio razonable de una skin.
     *
     * REGLAS:
     *
     * 1. Ordenar todos los precios.
     *
     * 2. Si hay 5 o más precios:
     *    eliminar los 2 más baratos.
     *
     * 3. Detectar precios absurdamente altos.
     *
     * 4. Seleccionar como máximo 4 mercados.
     *
     * 5. Los 4 mercados seleccionados son los que
     *    están más cerca del centro del mercado.
     *
     * 6. Calcular la MEDIA de esos mercados.
     */
    private function calculateReasonablePrice(
        array $entries
    ): ?array {
        $cleanEntries = [];

        foreach (
            $entries as $entry
        ) {
            if (
                !isset($entry['price'])
                || !is_numeric($entry['price'])
            ) {
                continue;
            }

            $price =
                (float) $entry['price'];

            if (
                $price <= 0
                || !is_finite($price)
            ) {
                continue;
            }

            $cleanEntries[] = [
                'provider' =>
                    $entry['provider']
                    ?? 'unknown',

                'price' =>
                    $price
            ];
        }

        if (empty($cleanEntries)) {
            return null;
        }

        /*
         * Ordenar de menor a mayor.
         */
        usort(
            $cleanEntries,
            static function (
                array $a,
                array $b
            ): int {
                return
                    $a['price']
                    <=> $b['price'];
            }
        );

        /*
         * --------------------------------------------------
         * ELIMINAR LOS DOS MÁS BARATOS
         * --------------------------------------------------
         *
         * Solamente lo hacemos cuando tenemos al menos
         * 5 fuentes.
         *
         * Con 7:
         *
         * 90 95 98 100 105 110 1500
         *
         * eliminamos:
         *
         * 90
         * 95
         */
        if (count($cleanEntries) >= 5) {
            array_shift(
                $cleanEntries
            );

            array_shift(
                $cleanEntries
            );
        }

        if (empty($cleanEntries)) {
            return null;
        }

        /*
         * --------------------------------------------------
         * ELIMINAR PRECIOS ABSURDAMENTE ALTOS
         * --------------------------------------------------
         *
         * Calculamos la mediana de lo que queda.
         *
         * Ejemplo:
         *
         * 98 100 105 110 1500
         *
         * mediana = 105
         *
         * límite = 262.50
         *
         * 1500 queda eliminado.
         */
        $pricesForMedian =
            array_map(
                static function (
                    array $entry
                ): float {
                    return
                        (float)
                        $entry['price'];
                },
                $cleanEntries
            );

        $median =
            $this->median(
                $pricesForMedian
            );

        /*
         * Permitimos hasta 2.5 veces la mediana.
         *
         * Esto es suficientemente flexible para que
         * una diferencia normal entre mercados no sea
         * eliminada, pero evita precios absurdos.
         */
        $upperLimit =
            $median * 2.5;

        $reasonableEntries = [];

        foreach (
            $cleanEntries as $entry
        ) {
            if (
                $entry['price']
                > $upperLimit
            ) {
                continue;
            }

            $reasonableEntries[] =
                $entry;
        }

        /*
         * Por seguridad, si el filtro eliminara
         * absolutamente todos los precios, utilizamos
         * los que teníamos antes del filtro.
         */
        if (
            empty($reasonableEntries)
        ) {
            $reasonableEntries =
                $cleanEntries;
        }

        /*
         * --------------------------------------------------
         * ELEGIR LOS 4 MERCADOS
         * --------------------------------------------------
         *
         * Si hay más de 4 precios razonables,
         * elegimos los 4 que estén más cerca de
         * la mediana.
         *
         * Esto evita simplemente coger los 4 más baratos.
         */
        if (
            count($reasonableEntries) > 4
        ) {
            $median =
                $this->median(
                    array_map(
                        static function (
                            array $entry
                        ): float {
                            return
                                (float)
                                $entry['price'];
                        },
                        $reasonableEntries
                    )
                );

            usort(
                $reasonableEntries,
                static function (
                    array $a,
                    array $b
                ) use ($median): int {

                    $distanceA =
                        abs(
                            $a['price']
                            - $median
                        );

                    $distanceB =
                        abs(
                            $b['price']
                            - $median
                        );

                    return
                        $distanceA
                        <=> $distanceB;
                }
            );

            /*
             * Máximo 4 mercados.
             */
            $reasonableEntries =
                array_slice(
                    $reasonableEntries,
                    0,
                    4
                );

            /*
             * Ordenamos de nuevo.
             */
            usort(
                $reasonableEntries,
                static function (
                    array $a,
                    array $b
                ): int {
                    return
                        $a['price']
                        <=> $b['price'];
                }
            );
        }

        /*
         * --------------------------------------------------
         * PRECIO FINAL
         * --------------------------------------------------
         */
        $selectedPrices =
            array_map(
                static function (
                    array $entry
                ): float {
                    return
                        (float)
                        $entry['price'];
                },
                $reasonableEntries
            );

        if (empty($selectedPrices)) {
            return null;
        }

        /*
         * Media de los mercados seleccionados.
         */
        $average =
            array_sum(
                $selectedPrices
            )
            / count($selectedPrices);

        /*
         * Rango real utilizado.
         */
        $selectedMin =
            min(
                $selectedPrices
            );

        $selectedMax =
            max(
                $selectedPrices
            );

        /*
         * Proveedores utilizados.
         */
        $providers = [];

        foreach (
            $reasonableEntries as $entry
        ) {
            if (
                isset($entry['provider'])
            ) {
                $providers[] =
                    $entry['provider'];
            }
        }

        $providers =
            array_values(
                array_unique(
                    $providers
                )
            );

        return [
            /*
             * Mantenemos "average" porque PriceService
             * utiliza este campo.
             */
            'average' =>
                round(
                    $average,
                    2
                ),

            'median' =>
                round(
                    $this->median(
                        $selectedPrices
                    ),
                    2
                ),

            'min' =>
                round(
                    $selectedMin,
                    2
                ),

            'max' =>
                round(
                    $selectedMax,
                    2
                ),

            /*
             * Mercados que realmente participan
             * en el precio.
             *
             * Máximo: 4.
             */
            'count' =>
                count(
                    $selectedPrices
                ),

            /*
             * Todos los proveedores que encontramos
             * antes de aplicar los filtros.
             */
            'total_sources' =>
                count($entries),

            'providers' =>
                $providers,

            /*
             * Precios que realmente se han utilizado.
             */
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
                    $selectedPrices
                )
        ];
    }

    /**
     * Calcula la mediana.
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