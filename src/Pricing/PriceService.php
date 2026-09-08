<?php

declare(strict_types=1);

namespace CS2\Pricing;

class PriceService
{
    /**
     * Endpoint utilizado por la propia extensión
     * oficial de CSGO Trader.
     */
    private const PRICES_URL =
        'https://prices.csgotrader.app/latest/csgotrader.json';

    /**
     * Cache local de precios.
     */
    private const CACHE_FILE =
        __DIR__ . '/../../storage/csgotrader_prices.json';

    /**
     * 15 minutos.
     */
    private const CACHE_TTL = 900;

    private ?array $prices = null;

    /**
     * Devuelve el precio de un objeto.
     */
    public function getPrice(
        ?string $marketHashName
    ): ?float {
        if (
            !$marketHashName ||
            trim($marketHashName) === ''
        ) {
            return null;
        }

        $prices = $this->getPrices();

        if (
            !isset($prices[$marketHashName])
        ) {
            return null;
        }

        $item =
            $prices[$marketHashName];

        if (
            !is_array($item) ||
            !isset($item['price'])
        ) {
            return null;
        }

        return $this->normalizePrice(
            $item['price']
        );
    }

    /**
     * Añade el precio a cada objeto del inventario.
     */
    public function addPrices(
        array $items
    ): array {
        $prices =
            $this->getPrices();

        foreach ($items as &$item) {
            $marketHashName =
                $item['market_hash_name']
                ?? null;

            $item['price'] =
                $this->getPriceFromPrices(
                    $marketHashName,
                    $prices
                );
        }

        unset($item);

        return $items;
    }

    /**
     * Calcula el valor total.
     */
    public function calculateTotal(
        array $items
    ): float {
        $total = 0.0;

        foreach ($items as $item) {
            if (
                isset($item['price']) &&
                $item['price'] !== null
            ) {
                $total +=
                    (float) $item['price'];
            }
        }

        return $total;
    }

    /**
     * Busca un precio dentro del listado descargado.
     */
    private function getPriceFromPrices(
        ?string $marketHashName,
        array $prices
    ): ?float {
        if (
            !$marketHashName ||
            !isset($prices[$marketHashName])
        ) {
            return null;
        }

        $item =
            $prices[$marketHashName];

        if (
            !is_array($item) ||
            !array_key_exists(
                'price',
                $item
            )
        ) {
            return null;
        }

        return $this->normalizePrice(
            $item['price']
        );
    }

    /**
     * Carga los precios desde cache o CSGO Trader.
     */
    private function getPrices(): array
    {
        if ($this->prices !== null) {
            return $this->prices;
        }

        $cacheDirectory =
            dirname(self::CACHE_FILE);

        if (
            !is_dir($cacheDirectory)
        ) {
            if (
                !mkdir(
                    $cacheDirectory,
                    0775,
                    true
                ) &&
                !is_dir($cacheDirectory)
            ) {
                throw new \RuntimeException(
                    'No se pudo crear el directorio '
                    . 'de cache de precios.'
                );
            }
        }

        /**
         * Intentamos utilizar la cache.
         */
        if (
            file_exists(
                self::CACHE_FILE
            )
        ) {
            $age =
                time()
                - filemtime(
                    self::CACHE_FILE
                );

            if (
                $age < self::CACHE_TTL
            ) {
                $cached =
                    file_get_contents(
                        self::CACHE_FILE
                    );

                if (
                    $cached !== false
                ) {
                    $data =
                        json_decode(
                            $cached,
                            true
                        );

                    if (
                        is_array($data)
                    ) {
                        $this->prices =
                            $data;

                        return $this->prices;
                    }
                }
            }
        }

        /**
         * La cache no existe o ha caducado.
         * Descargamos los precios actuales.
         */
        $data =
            $this->downloadPrices();

        file_put_contents(
            self::CACHE_FILE,
            json_encode(
                $data,
                JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
            ),
            LOCK_EX
        );

        $this->prices =
            $data;

        return $this->prices;
    }

    /**
     * Descarga el listado de precios de CSGO Trader.
     */
    private function downloadPrices(): array
    {
        $ch =
            curl_init(
                self::PRICES_URL
            );

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

            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Accept-Encoding: gzip'
            ],

            CURLOPT_ENCODING =>
                '',

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
                'No se pudo conectar con '
                . 'CSGO Trader: '
                . $curlError
            );
        }

        if (
            $httpCode !== 200
        ) {
            throw new \RuntimeException(
                'CSGO Trader ha devuelto HTTP '
                . $httpCode
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
                'CSGO Trader ha devuelto un JSON inválido.'
                . ' HTTP: '
                . $httpCode
                . (
                    $contentType
                        ? ' | Content-Type: '
                            . $contentType
                        : ''
                )
            );
        }

        return $data;
    }

    /**
     * Normaliza el precio.
     */
    private function normalizePrice(
        mixed $price
    ): ?float {
        if (
            $price === null ||
            $price === ''
        ) {
            return null;
        }

        if (
            !is_numeric($price)
        ) {
            return null;
        }

        $price =
            (float) $price;

        if (
            $price < 0
        ) {
            return null;
        }

        return $price;
    }
}