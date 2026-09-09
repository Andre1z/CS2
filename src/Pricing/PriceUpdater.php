<?php

declare(strict_types=1);

namespace CS2\Pricing;

class PriceUpdater
{
    /**
     * Fuente de precios de CSGO Trader.
     *
     * Los precios de las skins siguen viniendo
     * exclusivamente de aquí.
     */
    private const BASE_URL =
        'https://prices.csgotrader.app/latest/';

    /**
     * Fuente externa utilizada únicamente para
     * convertir USD -> EUR.
     *
     * NO se utiliza para obtener precios de skins.
     */
    private const EXCHANGE_RATES_URL =
        'https://api.frankfurter.app/latest?from=USD&to=EUR';

    private const SOURCE_CURRENCY = 'USD';

    private const TARGET_CURRENCY = 'EUR';

    /**
     * Proveedores de precios disponibles.
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
         * Aquí agruparemos todos los precios
         * encontrados por market_hash_name.
         */
        $allPrices = [];

        $updatedProviders = [];

        /*
         * Descargamos los precios de cada proveedor.
         */
        foreach (
            self::PROVIDERS as $provider
        ) {
            try {
                $url =
                    self::BASE_URL
                    . $provider
                    . '.json';

                $data =
                    $this->downloadJson(
                        $url
                    );

                /*
                 * Guardamos la respuesta original
                 * del proveedor.
                 */
                $this->cache
                    ->saveProviderPrices(
                        $provider,
                        $data
                    );

                /*
                 * Extraemos los precios.
                 */
                $providerPrices =
                    $this->extractProviderPrices(
                        $provider,
                        $data,
                        $usdToEur
                    );

                /*
                 * Añadimos cada precio al conjunto
                 * general.
                 */
                foreach (
                    $providerPrices
                    as $marketHashName => $price
                ) {
                    if (
                        $price <= 0
                    ) {
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
                 * Si falla un proveedor, intentamos
                 * utilizar su última caché.
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
                            if (
                                $price <= 0
                            ) {
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
                            $provider
                            . ' (cache)';

                    } catch (
                        \Throwable $cacheError
                    ) {
                        /*
                         * Si tampoco podemos utilizar
                         * la caché, ignoramos el proveedor.
                         */
                    }
                }
            }
        }

        /*
         * Calculamos el precio razonable de cada skin.
         */
        $calculated = [];

        foreach (
            $allPrices as $marketHashName => $entries
        ) {
            $result =
                $this->calculateReasonablePrice(
                    $entries
                );

            if (
                $result === null
            ) {
                continue;
            }

            $calculated[
                $marketHashName
            ] = $result;
        }

        /*
         * Ordenamos por nombre para que el JSON
         * sea estable.
         */
        ksort(
            $calculated
        );

        /*
         * Guardamos los precios calculados.
         */
        $this->cache
            ->saveCalculatedPrices(
                $calculated
            );

        /*
         * Generamos una versión basada en los
         * precios reales.
         */
        $encodedPrices =
            json_encode(
                $calculated,
                JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
            );

        if (
            $encodedPrices === false
        ) {
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
                count(
                    $calculated
                )
        ];
    }

    /**
     * Descarga un JSON.
     */
    private function downloadJson(
        string $url
    ): array {
        $ch =
            curl_init(
                $url
            );

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
            curl_exec(
                $ch
            );

        $httpCode =
            curl_getinfo(
                $ch,
                CURLINFO_HTTP_CODE
            );

        $error =
            curl_error(
                $ch
            );

        curl_close(
            $ch
        );

        if (
            $response === false
        ) {
            throw new \RuntimeException(
                'Error CURL: '
                . $error
            );
        }

        if (
            $httpCode !== 200
        ) {
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

        if (
            !is_array($data)
        ) {
            throw new \RuntimeException(
                'JSON inválido recibido desde '
                . $url
            );
        }

        return $data;
    }

    /**
     * Obtiene los tipos de cambio.
     *
     * Esta petición solamente se utiliza para
     * convertir los precios de USD a EUR.
     */
    private function getExchangeRates(): array
    {
        return $this->downloadJson(
            self::EXCHANGE_RATES_URL
        );
    }

    /**
     * Obtiene el cambio USD -> EUR.
     *
     * Frankfurter devuelve:
     *
     * {
     *     "amount": 1,
     *     "base": "USD",
     *     "date": "...",
     *     "rates": {
     *         "EUR": 0.85
     *     }
     * }
     */
    private function getUsdToEurRate(
        array $data
    ): float {
        if (
            !isset(
                $data['rates']['EUR']
            )
        ) {
            throw new \RuntimeException(
                'No se ha encontrado el cambio USD -> EUR.'
            );
        }

        if (
            !is_numeric(
                $data['rates']['EUR']
            )
        ) {
            throw new \RuntimeException(
                'El cambio USD -> EUR no es numérico.'
            );
        }

        $rate =
            (float)
            $data['rates']['EUR'];

        if (
            $rate <= 0
            || !is_finite($rate)
        ) {
            throw new \RuntimeException(
                'El cambio USD -> EUR no es válido.'
            );
        }

        return $rate;
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
                !is_string(
                    $marketHashName
                )
                || $marketHashName === ''
            ) {
                continue;
            }

            $price =
                $this->extractPriceFromEntry(
                    $provider,
                    $entry
                );

            if (
                $price === null
            ) {
                continue;
            }

            /*
             * Convertimos USD -> EUR.
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
            if (
                $price > 1000000
            ) {
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
     * Extrae el precio de una entrada.
     */
    private function extractPriceFromEntry(
        string $provider,
        mixed $entry
    ): ?float {
        /*
         * Algunos proveedores pueden devolver
         * directamente un número.
         */
        if (
            is_numeric($entry)
        ) {
            $value =
                (float)
                $entry;

            return
                $value > 0
                    ? $value
                    : null;
        }

        if (
            !is_array($entry)
        ) {
            return null;
        }

        /*
         * Proveedores que utilizan:
         *
         * {
         *     "price": 123
         * }
         */
        if (
            isset(
                $entry['price']
            )
            && is_numeric(
                $entry['price']
            )
        ) {
            $value =
                (float)
                $entry['price'];

            if (
                $value > 0
            ) {
                return $value;
            }
        }

        /*
         * Posibles campos utilizados por
         * steam / skinport.
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
         * Algunas estructuras contienen el precio
         * dentro de otro array.
         */
        foreach (
            $entry as $value
        ) {
            if (
                !is_array($value)
            ) {
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
     * Extrae un valor numérico de diferentes
     * estructuras de datos.
     */
    private function extractNumericValue(
        mixed $value
    ): ?float {
        if (
            is_numeric($value)
        ) {
            $number =
                (float)
                $value;

            return
                $number > 0
                    ? $number
                    : null;
        }

        if (
            !is_array($value)
        ) {
            return null;
        }

        if (
            isset(
                $value['price']
            )
            && is_numeric(
                $value['price']
            )
        ) {
            $number =
                (float)
                $value['price'];

            return
                $number > 0
                    ? $number
                    : null;
        }

        if (
            isset(
                $value['value']
            )
            && is_numeric(
                $value['value']
            )
        ) {
            $number =
                (float)
                $value['value'];

            return
                $number > 0
                    ? $number
                    : null;
        }

        return null;
    }

    /**
     * Calcula el precio razonable.
     *
     * REGLAS:
     *
     * 1. Recoger todos los precios disponibles.
     *
     * 2. Ordenarlos de menor a mayor.
     *
     * 3. Eliminar los DOS más baratos cuando
     *    existen al menos 5 fuentes.
     *
     * 4. Eliminar precios absurdamente altos.
     *
     * 5. Si quedan más de 4 precios razonables,
     *    elegir los 4 más cercanos a la mediana.
     *
     * 6. Calcular la media de los precios seleccionados.
     *
     * EJEMPLO:
     *
     * 90
     * 95
     * 98
     * 100
     * 105
     * 110
     * 1500
     *
     * Eliminamos:
     *
     * 90
     * 95
     * 1500
     *
     * Resultado:
     *
     * 98
     * 100
     * 105
     * 110
     *
     * Media:
     *
     * 103.25 €
     */
    private function calculateReasonablePrice(
        array $entries
    ): ?array {
        $cleanEntries = [];

        foreach (
            $entries as $entry
        ) {
            if (
                !isset(
                    $entry['price']
                )
                || !is_numeric(
                    $entry['price']
                )
            ) {
                continue;
            }

            $price =
                (float)
                $entry['price'];

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

        if (
            empty($cleanEntries)
        ) {
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
         * ELIMINAR LOS DOS PRECIOS MÁS BARATOS
         * --------------------------------------------------
         */
        if (
            count($cleanEntries) >= 5
        ) {
            array_shift(
                $cleanEntries
            );

            array_shift(
                $cleanEntries
            );
        }

        if (
            empty($cleanEntries)
        ) {
            return null;
        }

        /*
         * --------------------------------------------------
         * ELIMINAR PRECIOS ABSURDAMENTE ALTOS
         * --------------------------------------------------
         *
         * Calculamos la mediana.
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
         * Un precio que supere 2.5 veces la mediana
         * se considera un valor absurdo.
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
         * Protección.
         */
        if (
            empty($reasonableEntries)
        ) {
            $reasonableEntries =
                $cleanEntries;
        }

        /*
         * --------------------------------------------------
         * SELECCIONAR LOS 4 MERCADOS
         * --------------------------------------------------
         *
         * Si tenemos más de 4 mercados razonables,
         * seleccionamos los 4 más cercanos a la mediana.
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
             * Ordenamos nuevamente.
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
         * CALCULAR PRECIO FINAL
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

        if (
            empty($selectedPrices)
        ) {
            return null;
        }

        /*
         * Media de los mercados seleccionados.
         */
        $average =
            array_sum(
                $selectedPrices
            )
            /
            count(
                $selectedPrices
            );

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
                isset(
                    $entry['provider']
                )
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

        /*
         * Resultado final.
         */
        return [
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
             * Número de mercados realmente utilizados.
             *
             * Máximo: 4.
             */
            'count' =>
                count(
                    $selectedPrices
                ),

            /*
             * Número total de precios encontrados
             * antes de aplicar los filtros.
             */
            'total_sources' =>
                count(
                    $entries
                ),

            'providers' =>
                $providers,

            /*
             * Precios que realmente participan
             * en la media.
             */
            'prices' =>
                array_map(
                    static function (
                        float $value
                    ): float {
                        return
                            round(
                                $value,
                                2
                            );
                    },
                    $selectedPrices
                )
        ];
    }

    /**
     * Calcula la mediana de un array.
     */
    private function median(
        array $values
    ): float {
        if (
            empty($values)
        ) {
            return 0.0;
        }

        sort(
            $values,
            SORT_NUMERIC
        );

        $count =
            count(
                $values
            );

        $middle =
            intdiv(
                $count,
                2
            );

        /*
         * Número par de valores.
         */
        if (
            $count % 2 === 0
        ) {
            return (
                $values[
                    $middle - 1
                ]
                +
                $values[
                    $middle
                ]
            ) / 2;
        }

        /*
         * Número impar.
         */
        return
            $values[
                $middle
            ];
    }
}