<?php

declare(strict_types=1);

namespace CS2\Pricing;

class PriceUpdater
{
    /**
     * URL base de los feeds de precios de CSGO Trader.
     */
    private const BASE_URL =
        'https://prices.csgotrader.app/latest/';

    /**
     * Feed oficial de tipos de cambio.
     *
     * CSGO Trader utiliza este archivo para convertir
     * sus precios a la moneda seleccionada.
     */
    private const EXCHANGE_RATES_URL =
        self::BASE_URL . 'exchange_rates.json';

    /**
     * Moneda base de los feeds de precios.
     *
     * La extensión de CSGO Trader utiliza USD como
     * referencia y después aplica el exchange rate.
     */
    private const BASE_CURRENCY = 'USD';

    /**
     * Moneda que utilizaremos en nuestra aplicación.
     */
    private const TARGET_CURRENCY = 'EUR';

    /**
     * Proveedores que utilizaremos para calcular
     * nuestro precio estimado.
     *
     * Todos ellos forman parte del sistema de
     * pricing utilizado por CSGO Trader.
     */
    private const PROVIDERS = [
        'csgotrader',
        'csmoney',
        'cstrade',
        'lisskins',
        'lootfarm',
        'csgotm',
        'csgoempire',
        'swapgg',
        'skinport',
        'buff163',
        'csfloat'
    ];

    /**
     * Para proveedores que tienen varios modos,
     * elegimos el modo más apropiado para nuestro
     * objetivo.
     *
     * En general preferimos "starting_at" cuando
     * representa el precio de entrada al mercado.
     */
    private const PROVIDER_MODES = [
        'csgotrader' => 'csgotrader',

        'csmoney' => 'csmoney',

        'cstrade' => 'cstrade',

        'lisskins' => 'lisskins',

        'lootfarm' => 'lootfarm',

        'csgotm' => 'csgotm',

        'csgoempire' => 'csgoempire',

        'swapgg' => 'swapgg',

        'skinport' => 'starting_at',

        'buff163' => 'starting_at',

        'csfloat' => 'starting_at'
    ];

    /**
     * Número mínimo de precios necesarios para
     * aplicar el filtro de outliers.
     */
    private const MIN_VALUES_FOR_OUTLIER_FILTER = 5;

    /**
     * Timeout de las peticiones.
     */
    private const REQUEST_TIMEOUT = 30;

    private const CONNECT_TIMEOUT = 10;

    private PriceCache $cache;

    public function __construct()
    {
        $this->cache =
            new PriceCache();
    }

    /**
     * Actualiza todos los proveedores y reconstruye
     * el precio medio de cada objeto.
     *
     * @return array{
     *     version: string,
     *     updated_at: int,
     *     currency: string,
     *     providers: array<int, string>,
     *     failed_providers: array<int, string>,
     *     items: int
     * }
     */
    public function update(): array
    {
        /*
         * 1. Obtener tipos de cambio.
         */
        $exchangeRates =
            $this->downloadExchangeRates();

        $eurRate =
            $this->getTargetExchangeRate(
                $exchangeRates
            );

        /*
         * 2. Descargar y normalizar proveedores.
         */
        $allProviderPrices = [];

        $successfulProviders = [];

        $failedProviders = [];

        foreach (
            self::PROVIDERS as $provider
        ) {
            try {
                $rawPrices =
                    $this->downloadProvider(
                        $provider
                    );

                $normalizedPrices =
                    $this->normalizeProvider(
                        $provider,
                        $rawPrices
                    );

                if (
                    empty($normalizedPrices)
                ) {
                    throw new \RuntimeException(
                        'El proveedor no contiene precios válidos.'
                    );
                }

                /*
                 * Convertimos los precios de USD a EUR.
                 */
                $eurPrices =
                    $this->convertPricesToEur(
                        $normalizedPrices,
                        $eurRate
                    );

                /*
                 * Guardamos el proveedor ya normalizado
                 * y convertido a EUR.
                 */
                $this->cache
                    ->saveProviderPrices(
                        $provider,
                        $eurPrices
                    );

                $allProviderPrices[$provider] =
                    $eurPrices;

                $successfulProviders[] =
                    $provider;

            } catch (
                \Throwable $e
            ) {
                $failedProviders[] =
                    $provider;

                error_log(
                    sprintf(
                        '[PriceUpdater] Error en %s: %s',
                        $provider,
                        $e->getMessage()
                    )
                );

                /*
                 * MUY IMPORTANTE:
                 *
                 * Si un proveedor falla no borramos
                 * su caché anterior.
                 *
                 * Podemos utilizarla como fallback.
                 */
                $cached =
                    $this->cache
                        ->getProviderPrices(
                            $provider
                        );

                if (
                    !empty($cached)
                ) {
                    $allProviderPrices[$provider] =
                        $cached;

                    $successfulProviders[] =
                        $provider;
                }
            }
        }

        /*
         * No continuamos si no tenemos ningún proveedor.
         */
        if (
            empty($allProviderPrices)
        ) {
            throw new \RuntimeException(
                'No se pudo obtener ningún proveedor de precios.'
            );
        }

        /*
         * 3. Calcular precio medio.
         */
        $calculatedPrices =
            $this->calculateAverages(
                $allProviderPrices
            );

        /*
         * 4. Guardar precios calculados.
         */
        $this->cache
            ->saveCalculatedPrices(
                $calculatedPrices
            );

        /*
         * 5. Generar versión.
         *
         * Si los precios no han cambiado,
         * la versión tampoco cambiará.
         */
        $version =
            $this->generateVersion(
                $calculatedPrices
            );

        $updatedAt =
            time();

        /*
         * 6. Guardar metadata.
         */
        $this->cache
            ->saveMetadata([
                'version' =>
                    $version,

                'updated_at' =>
                    $updatedAt,

                'currency' =>
                    self::TARGET_CURRENCY,

                'base_currency' =>
                    self::BASE_CURRENCY,

                'exchange_rate' =>
                    $eurRate,

                'providers' =>
                    $successfulProviders,

                'failed_providers' =>
                    array_values(
                        array_unique(
                            $failedProviders
                        )
                    ),

                'items' =>
                    count(
                        $calculatedPrices
                    )
            ]);

        return [
            'version' =>
                $version,

            'updated_at' =>
                $updatedAt,

            'currency' =>
                self::TARGET_CURRENCY,

            'providers' =>
                $successfulProviders,

            'failed_providers' =>
                array_values(
                    array_unique(
                        $failedProviders
                    )
                ),

            'items' =>
                count(
                    $calculatedPrices
                )
        ];
    }

    /**
     * Descarga los tipos de cambio de CSGO Trader.
     *
     * La respuesta esperada es algo equivalente a:
     *
     * {
     *     "USD": 1,
     *     "EUR": 0.85,
     *     "GBP": 0.74,
     *     ...
     * }
     */
    private function downloadExchangeRates(): array
    {
        $data =
            $this->downloadJson(
                self::EXCHANGE_RATES_URL
            );

        if (
            empty($data)
        ) {
            throw new \RuntimeException(
                'CSGO Trader no ha devuelto tipos de cambio.'
            );
        }

        return $data;
    }

    /**
     * Obtiene el tipo USD -> EUR.
     */
    private function getTargetExchangeRate(
        array $exchangeRates
    ): float {
        if (
            !isset(
                $exchangeRates[
                    self::TARGET_CURRENCY
                ]
            )
        ) {
            throw new \RuntimeException(
                'No existe un tipo de cambio para '
                . self::TARGET_CURRENCY
            );
        }

        $rate =
            $exchangeRates[
                self::TARGET_CURRENCY
            ];

        if (
            !is_numeric($rate)
        ) {
            throw new \RuntimeException(
                'El tipo de cambio EUR no es válido.'
            );
        }

        $rate =
            (float) $rate;

        if (
            $rate <= 0
        ) {
            throw new \RuntimeException(
                'El tipo de cambio EUR debe ser mayor que cero.'
            );
        }

        return $rate;
    }

    /**
     * Descarga un proveedor concreto.
     */
    private function downloadProvider(
        string $provider
    ): array {
        $url =
            self::BASE_URL
            . $provider
            . '.json';

        return $this->downloadJson(
            $url
        );
    }

    /**
     * Descarga y decodifica JSON.
     */
    private function downloadJson(
        string $url
    ): array {
        $ch =
            curl_init(
                $url
            );

        if (
            $ch === false
        ) {
            throw new \RuntimeException(
                'No se pudo inicializar CURL.'
            );
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER =>
                true,

            CURLOPT_FOLLOWLOCATION =>
                true,

            CURLOPT_TIMEOUT =>
                self::REQUEST_TIMEOUT,

            CURLOPT_CONNECTTIMEOUT =>
                self::CONNECT_TIMEOUT,

            CURLOPT_SSL_VERIFYPEER =>
                true,

            CURLOPT_SSL_VERIFYHOST =>
                2,

            CURLOPT_ENCODING =>
                '',

            CURLOPT_HTTPHEADER => [
                'Accept: application/json'
            ],

            CURLOPT_USERAGENT =>
                'CS2Inventory/1.0'
        ]);

        $response =
            curl_exec($ch);

        $httpCode =
            (int) curl_getinfo(
                $ch,
                CURLINFO_HTTP_CODE
            );

        $contentType =
            curl_getinfo(
                $ch,
                CURLINFO_CONTENT_TYPE
            );

        $curlError =
            curl_error($ch);

        curl_close($ch);

        if (
            $response === false
        ) {
            throw new \RuntimeException(
                'CURL error: '
                . $curlError
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
                'Respuesta JSON inválida.'
                . (
                    $contentType
                        ? ' Content-Type: '
                            . $contentType
                        : ''
                )
            );
        }

        return $data;
    }

    /**
     * Normaliza el formato de los diferentes
     * proveedores a:
     *
     * [
     *     "AK-47 | Redline (Field-Tested)" => 50.25
     * ]
     */
    private function normalizeProvider(
        string $provider,
        array $data
    ): array {
        $result = [];

        foreach (
            $data as $marketHashName => $value
        ) {
            $price =
                $this->extractPrice(
                    $provider,
                    $value
                );

            if (
                $price === null
            ) {
                continue;
            }

            if (
                $price < 0
            ) {
                continue;
            }

            /*
             * Evitamos precios absurdamente pequeños
             * que normalmente indican datos corruptos.
             */
            if (
                $price === 0
            ) {
                continue;
            }

            $result[
                (string) $marketHashName
            ] =
                $price;
        }

        return $result;
    }

    /**
     * Extrae el precio dependiendo del formato
     * del proveedor.
     */
    private function extractPrice(
        string $provider,
        mixed $value
    ): ?float {
        /*
         * Proveedores con:
         *
         * {
         *     "Item": {
         *         "price": 123
         *     }
         * }
         */
        if (
            in_array(
                $provider,
                [
                    'csgotrader',
                    'csmoney',
                    'cstrade',
                    'lisskins'
                ],
                true
            )
        ) {
            if (
                !is_array($value)
            ) {
                return null;
            }

            if (
                !isset(
                    $value['price']
                )
            ) {
                return null;
            }

            if (
                !is_numeric(
                    $value['price']
                )
            ) {
                return null;
            }

            return
                (float)
                $value['price'];
        }

        /*
         * Proveedores cuyo formato es:
         *
         * {
         *     "Item": 123
         * }
         */
        if (
            in_array(
                $provider,
                [
                    'lootfarm',
                    'csgotm',
                    'csgoempire',
                    'swapgg'
                ],
                true
            )
        ) {
            if (
                !is_numeric($value)
            ) {
                return null;
            }

            return
                (float)
                $value;
        }

        /*
         * Skinport / BUFF / CSFloat:
         *
         * {
         *     "Item": {
         *         "starting_at": {
         *             "price": 123
         *         }
         *     }
         * }
         *
         * o variantes similares dependiendo
         * del proveedor.
         */
        if (
            in_array(
                $provider,
                [
                    'skinport',
                    'buff163',
                    'csfloat'
                ],
                true
            )
        ) {
            return $this->extractModePrice(
                $value,
                self::PROVIDER_MODES[
                    $provider
                ]
                ?? 'starting_at'
            );
        }

        return null;
    }

    /**
     * Extrae un precio de un modo concreto.
     *
     * Soporta varios formatos para evitar que
     * un pequeño cambio en el JSON rompa todo
     * el actualizador.
     */
    private function extractModePrice(
        mixed $value,
        string $mode
    ): ?float {
        if (
            !is_array($value)
        ) {
            return null;
        }

        /*
         * Formato:
         *
         * {
         *     "starting_at": {
         *         "price": 123
         *     }
         * }
         */
        if (
            isset(
                $value[$mode]
            )
            &&
            is_array(
                $value[$mode]
            )
            &&
            isset(
                $value[$mode]['price']
            )
            &&
            is_numeric(
                $value[$mode]['price']
            )
        ) {
            return
                (float)
                $value[$mode]['price'];
        }

        /*
         * Formato:
         *
         * {
         *     "starting_at": 123
         * }
         */
        if (
            isset(
                $value[$mode]
            )
            &&
            is_numeric(
                $value[$mode]
            )
        ) {
            return
                (float)
                $value[$mode];
        }

        /*
         * Algunas estructuras pueden exponer
         * directamente "price".
         */
        if (
            isset(
                $value['price']
            )
            &&
            is_numeric(
                $value['price']
            )
        ) {
            return
                (float)
                $value['price'];
        }

        return null;
    }

    /**
     * Convierte todos los precios de USD a EUR.
     */
    private function convertPricesToEur(
        array $prices,
        float $eurRate
    ): array {
        $result = [];

        foreach (
            $prices as $marketHashName => $price
        ) {
            if (
                !is_numeric($price)
            ) {
                continue;
            }

            $price =
                (float) $price;

            $result[
                $marketHashName
            ] =
                round(
                    $price * $eurRate,
                    4
                );
        }

        return $result;
    }

    /**
     * Calcula el precio estimado de cada objeto.
     */
    private function calculateAverages(
        array $providerPrices
    ): array {
        /*
         * Transformamos:
         *
         * proveedor -> item -> precio
         *
         * en:
         *
         * item -> proveedor -> precio
         */
        $items = [];

        foreach (
            $providerPrices as $provider => $prices
        ) {
            foreach (
                $prices as $marketHashName => $price
            ) {
                if (
                    !isset(
                        $items[$marketHashName]
                    )
                ) {
                    $items[$marketHashName] = [];
                }

                $items[
                    $marketHashName
                ][$provider] =
                    (float) $price;
            }
        }

        $result = [];

        foreach (
            $items as $marketHashName => $marketPrices
        ) {
            if (
                empty($marketPrices)
            ) {
                continue;
            }

            $values =
                array_values(
                    $marketPrices
                );

            /*
             * Eliminamos valores extremos.
             */
            $filteredValues =
                $this->removeOutliers(
                    $values
                );

            if (
                empty($filteredValues)
            ) {
                continue;
            }

            $average =
                array_sum(
                    $filteredValues
                )
                /
                count(
                    $filteredValues
                );

            /*
             * Ordenamos para obtener min/max
             * de los precios reales disponibles.
             */
            sort($values);

            $result[
                $marketHashName
            ] = [
                'average' =>
                    round(
                        $average,
                        2
                    ),

                'min' =>
                    round(
                        min($values),
                        2
                    ),

                'max' =>
                    round(
                        max($values),
                        2
                    ),

                /*
                 * Número de mercados utilizados
                 * para calcular la media.
                 */
                'count' =>
                    count(
                        $filteredValues
                    ),

                /*
                 * Número total de mercados
                 * disponibles para este objeto.
                 */
                'total_sources' =>
                    count(
                        $values
                    ),

                /*
                 * Precios individuales por mercado.
                 */
                'prices' =>
                    $marketPrices
            ];
        }

        return $result;
    }

    /**
     * Elimina valores extremos.
     *
     * Con menos de 5 proveedores:
     *
     *     utilizamos todos.
     *
     * Con 5 o más:
     *
     *     eliminamos el más bajo
     *     y el más alto.
     *
     * Ejemplo:
     *
     * 90
     * 95
     * 98
     * 100
     * 105
     * 1500
     *
     * queda:
     *
     * 95
     * 98
     * 100
     * 105
     */
    private function removeOutliers(
        array $values
    ): array {
        $values =
            array_values(
                array_filter(
                    $values,
                    static function (
                        mixed $value
                    ): bool {
                        return
                            is_numeric($value)
                            &&
                            (float) $value > 0;
                    }
                )
            );

        $count =
            count($values);

        if (
            $count <
            self::MIN_VALUES_FOR_OUTLIER_FILTER
        ) {
            return $values;
        }

        sort($values);

        /*
         * Eliminamos el menor y mayor precio.
         */
        array_shift($values);

        array_pop($values);

        return $values;
    }

    /**
     * Genera una versión basada únicamente
     * en los precios calculados.
     *
     * Si los precios no cambian:
     *
     *     versión = igual
     *
     * Si cambia cualquier precio:
     *
     *     versión = diferente
     */
    private function generateVersion(
        array $prices
    ): string {
        $json =
            json_encode(
                $prices,
                JSON_UNESCAPED_UNICODE
                |
                JSON_UNESCAPED_SLASHES
                |
                JSON_PRESERVE_ZERO_FRACTION
            );

        if (
            $json === false
        ) {
            throw new \RuntimeException(
                'No se pudo generar la versión de precios.'
            );
        }

        return hash(
            'sha256',
            $json
        );
    }
}