<?php

declare(strict_types=1);

namespace CS2\Pricing;

class PriceUpdater
{
    private const BASE_URL =
        'https://prices.csgotrader.app/latest/';

    /**
     * Proveedores publicados por el sistema
     * de precios de CSGO Trader.
     *
     * Empezamos con los proveedores cuyo formato
     * de precio podemos normalizar directamente.
     */
    private const PROVIDERS = [
        'csgotrader',
        'csmoney',
        'cstrade',
        'lisskins',
        'lootfarm',
        'csgotm',
        'csgoempire',
        'swapgg'
    ];

    private PriceCache $cache;

    public function __construct()
    {
        $this->cache =
            new PriceCache();
    }

    public function update(): array
    {
        $allProviderPrices = [];

        foreach (
            self::PROVIDERS as $provider
        ) {
            try {
                $raw =
                    $this->downloadProvider(
                        $provider
                    );

                $prices =
                    $this->normalizeProvider(
                        $provider,
                        $raw
                    );

                if (
                    empty($prices)
                ) {
                    continue;
                }

                $this->cache
                    ->saveProviderPrices(
                        $provider,
                        $prices
                    );

                $allProviderPrices[$provider] =
                    $prices;

            } catch (
                \Throwable $e
            ) {
                error_log(
                    sprintf(
                        'Error actualizando %s: %s',
                        $provider,
                        $e->getMessage()
                    )
                );
            }
        }

        if (
            empty($allProviderPrices)
        ) {
            throw new \RuntimeException(
                'No se pudo actualizar ningún proveedor de precios.'
            );
        }

        $calculated =
            $this->calculateAverages(
                $allProviderPrices
            );

        $this->cache
            ->saveCalculatedPrices(
                $calculated
            );

        $version =
            $this->generateVersion(
                $calculated
            );

        $this->cache
            ->saveMetadata([
                'version' =>
                    $version,

                'updated_at' =>
                    time(),

                'providers' =>
                    array_keys(
                        $allProviderPrices
                    )
            ]);

        return [
            'version' =>
                $version,

            'updated_at' =>
                time(),

            'providers' =>
                array_keys(
                    $allProviderPrices
                ),

            'items' =>
                count($calculated)
        ];
    }

    private function downloadProvider(
        string $provider
    ): array {
        $url =
            self::BASE_URL
            . $provider
            . '.json';

        $ch =
            curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER =>
                true,

            CURLOPT_FOLLOWLOCATION =>
                true,

            CURLOPT_TIMEOUT =>
                30,

            CURLOPT_CONNECTTIMEOUT =>
                10,

            CURLOPT_SSL_VERIFYPEER =>
                true,

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
            curl_getinfo(
                $ch,
                CURLINFO_HTTP_CODE
            );

        $error =
            curl_error($ch);

        curl_close($ch);

        if ($response === false) {
            throw new \RuntimeException(
                'CURL: ' . $error
            );
        }

        if ($httpCode !== 200) {
            throw new \RuntimeException(
                'HTTP ' . $httpCode
            );
        }

        $data =
            json_decode(
                $response,
                true
            );

        if (!is_array($data)) {
            throw new \RuntimeException(
                'Respuesta JSON inválida.'
            );
        }

        return $data;
    }

    private function normalizeProvider(
        string $provider,
        array $data
    ): array {
        $result = [];

        foreach (
            $data as $marketHashName => $value
        ) {
            $price = null;

            /*
             * Proveedores cuyo JSON contiene:
             *
             * {
             *   "item": {
             *      "price": 123
             *   }
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
                    is_array($value)
                    &&
                    isset($value['price'])
                    &&
                    is_numeric($value['price'])
                ) {
                    $price =
                        (float) $value['price'];
                }
            }

            /*
             * Proveedores cuyo JSON contiene:
             *
             * {
             *   "item": 123
             * }
             */
            elseif (
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
                    is_numeric($value)
                ) {
                    $price =
                        (float) $value;
                }
            }

            if (
                $price === null
                ||
                $price < 0
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

    private function calculateAverages(
        array $providerPrices
    ): array {
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

                $items[$marketHashName][$provider] =
                    (float) $price;
            }
        }

        $result = [];

        foreach (
            $items as $marketHashName => $marketPrices
        ) {
            $values =
                array_values(
                    $marketPrices
                );

            if (
                empty($values)
            ) {
                continue;
            }

            sort($values);

            $filtered =
                $this->removeOutliers(
                    $values
                );

            $average =
                array_sum($filtered)
                / count($filtered);

            $result[$marketHashName] = [
                'average' =>
                    round($average, 2),

                'min' =>
                    round(min($values), 2),

                'max' =>
                    round(max($values), 2),

                'count' =>
                    count($filtered),

                'total_sources' =>
                    count($values),

                'prices' =>
                    $marketPrices
            ];
        }

        return $result;
    }

    private function removeOutliers(
        array $values
    ): array {
        $count =
            count($values);

        /*
         * Con pocos mercados no eliminamos
         * datos para no distorsionar el resultado.
         */
        if ($count < 5) {
            return $values;
        }

        /*
         * Eliminamos el precio más bajo
         * y el más alto.
         *
         * Ejemplo:
         *
         * 90, 95, 98, 100, 105, 1500
         *
         * pasa a:
         *
         * 95, 98, 100, 105
         */
        array_shift($values);
        array_pop($values);

        return $values;
    }

    private function generateVersion(
        array $prices
    ): string {
        return hash(
            'sha256',
            json_encode(
                $prices,
                JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
            )
        );
    }
}