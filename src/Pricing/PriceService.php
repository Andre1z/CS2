<?php

declare(strict_types=1);

namespace CS2\Pricing;

class PriceService
{
    /**
     * Fuente de precios de CSGO Trader.
     *
     * CSGO Trader publica dumps de precios que
     * podemos utilizar para valorar los objetos.
     */
    private const PRICES_URL =
        'https://prices.csgotrader.app/latest/prices_v6.json';

    /**
     * Archivo de caché local.
     */
    private const CACHE_FILE =
        __DIR__ . '/../../storage/prices_v6.json';

    /**
     * Tiempo durante el que mantenemos el caché.
     *
     * 15 minutos.
     */
    private const CACHE_TTL = 900;

    /**
     * Precios cargados en memoria.
     */
    private ?array $prices = null;

    /**
     * Devuelve el precio de un objeto.
     */
    public function getPrice(
        ?string $marketHashName
    ): ?float {
        if (!$marketHashName) {
            return null;
        }

        $prices = $this->getPrices();

        if (
            !isset(
                $prices[$marketHashName]
            )
        ) {
            return null;
        }

        $item = $prices[$marketHashName];

        /*
         * Intentamos primero el precio propio
         * de CSGO Trader.
         */
        if (
            isset($item['csgotrader']['price'])
        ) {
            return $this->normalizePrice(
                $item['csgotrader']['price']
            );
        }

        /*
         * Algunas versiones/formas del fichero
         * pueden utilizar otros campos.
         */
        if (
            isset($item['csgotrader']['suggested_price'])
        ) {
            return $this->normalizePrice(
                $item['csgotrader']['suggested_price']
            );
        }

        return null;
    }

    /**
     * Añade precios a todos los objetos.
     */
    public function addPrices(
        array $items
    ): array {
        /*
         * Cargamos los precios una sola vez.
         */
        $this->getPrices();

        foreach ($items as &$item) {
            $item['price'] =
                $this->getPrice(
                    $item['market_hash_name']
                );
        }

        unset($item);

        return $items;
    }

    /**
     * Calcula el valor total del inventario.
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
     * Obtiene todos los precios.
     *
     * Primero intenta utilizar el caché.
     * Si está caducado, descarga una versión nueva.
     */
    private function getPrices(): array
    {
        if ($this->prices !== null) {
            return $this->prices;
        }

        /*
         * Crear directorio storage si no existe.
         */
        $cacheDirectory =
            dirname(self::CACHE_FILE);

        if (
            !is_dir($cacheDirectory)
        ) {
            mkdir(
                $cacheDirectory,
                0775,
                true
            );
        }

        /*
         * Utilizar caché si todavía es válido.
         */
        if (
            file_exists(self::CACHE_FILE)
            &&
            (
                time()
                - filemtime(self::CACHE_FILE)
            ) < self::CACHE_TTL
        ) {
            $cached =
                file_get_contents(
                    self::CACHE_FILE
                );

            if ($cached !== false) {
                $data =
                    json_decode(
                        $cached,
                        true
                    );

                if (is_array($data)) {
                    $this->prices = $data;

                    return $this->prices;
                }
            }
        }

        /*
         * Descargar precios actualizados.
         */
        $data =
            $this->downloadPrices();

        /*
         * Guardar caché.
         */
        file_put_contents(
            self::CACHE_FILE,
            json_encode(
                $data,
                JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
            ),
            LOCK_EX
        );

        $this->prices = $data;

        return $this->prices;
    }

    /**
     * Descarga el fichero de precios.
     */
    private function downloadPrices(): array
    {
        $ch = curl_init(
            self::PRICES_URL
        );

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,

            CURLOPT_FOLLOWLOCATION => true,

            CURLOPT_TIMEOUT => 30,

            CURLOPT_CONNECTTIMEOUT => 10,

            CURLOPT_SSL_VERIFYPEER => true,

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

        $curlError =
            curl_error($ch);

        curl_close($ch);

        if ($response === false) {
            throw new \RuntimeException(
                'No se pudo descargar el listado '
                . 'de precios de CSGO Trader: '
                . $curlError
            );
        }

        if ($httpCode !== 200) {
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

        if (!is_array($data)) {
            throw new \RuntimeException(
                'CSGO Trader ha devuelto un JSON inválido.'
            );
        }

        return $data;
    }

    /**
     * Normaliza un precio.
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

        if ($price < 0) {
            return null;
        }

        return $price;
    }
}