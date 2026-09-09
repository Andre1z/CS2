<?php

declare(strict_types=1);

namespace CS2\Pricing;

class PriceService
{
    private PriceCache $cache;

    private ?array $prices = null;

    public function __construct()
    {
        $this->cache =
            new PriceCache();
    }

    /**
     * Devuelve el precio calculado de una skin.
     */
    public function getPrice(
        ?string $marketHashName
    ): ?float {
        if (
            !$marketHashName
        ) {
            return null;
        }

        $prices =
            $this->getPrices();

        if (
            !isset(
                $prices[$marketHashName]
            )
        ) {
            return null;
        }

        if (
            !isset(
                $prices[
                    $marketHashName
                ]['average']
            )
        ) {
            return null;
        }

        return
            (float)
            $prices[
                $marketHashName
            ]['average'];
    }

    /**
     * Devuelve todos los detalles del precio.
     */
    public function getPriceDetails(
        ?string $marketHashName
    ): ?array {
        if (
            !$marketHashName
        ) {
            return null;
        }

        $prices =
            $this->getPrices();

        return
            $prices[
                $marketHashName
            ]
            ?? null;
    }

    /**
     * Añade la información de precios a los objetos
     * del inventario.
     */
    public function addPrices(
        array $items
    ): array {
        $prices =
            $this->getPrices();

        foreach (
            $items as &$item
        ) {
            $name =
                $item['market_hash_name']
                ?? null;

            $details =
                $name !== null
                    ? (
                        $prices[
                            $name
                        ]
                        ?? null
                    )
                    : null;

            /*
             * Precio final de la skin.
             */
            $item['price'] =
                $details !== null
                    && isset(
                        $details['average']
                    )
                        ? (float)
                            $details['average']
                        : null;

            /*
             * Precio mínimo de los mercados
             * que realmente participan.
             */
            $item['price_min'] =
                $details['min']
                ?? null;

            /*
             * Precio máximo de los mercados
             * que realmente participan.
             */
            $item['price_max'] =
                $details['max']
                ?? null;

            /*
             * Número de mercados utilizados
             * para calcular el precio.
             *
             * Máximo: 4.
             */
            $item['price_count'] =
                $details['count']
                ?? 0;

            /*
             * Número total de fuentes encontradas
             * antes de aplicar los filtros.
             */
            $item['price_total_sources'] =
                $details['total_sources']
                ?? 0;

            /*
             * Precios individuales utilizados.
             */
            $item['price_markets'] =
                $details['prices']
                ?? [];

            /*
             * Proveedores utilizados.
             */
            $item['price_providers'] =
                $details['providers']
                ?? [];
        }

        unset($item);

        return $items;
    }

    /**
     * Calcula el valor total del inventario.
     *
     * IMPORTANTE:
     *
     * El total final SIEMPRE es un número entero.
     *
     * Ejemplos:
     *
     * 100.49 € -> 100 €
     * 100.50 € -> 101 €
     * 100.51 € -> 101 €
     *
     * Utilizamos PHP_ROUND_HALF_UP para que exactamente
     * .50 se redondee hacia arriba.
     */
    public function calculateTotal(
        array $items
    ): float {
        $total = 0.0;

        foreach (
            $items as $item
        ) {
            if (
                isset($item['price'])
                && $item['price'] !== null
                && is_numeric($item['price'])
            ) {
                $total +=
                    (float)
                    $item['price'];
            }
        }

        /*
         * Redondeo a euros enteros.
         *
         * < 0.50 -> abajo
         * >= 0.50 -> arriba
         */
        return (float) round(
            $total,
            0,
            PHP_ROUND_HALF_UP
        );
    }

    /**
     * Devuelve la versión actual de precios.
     */
    public function getVersion(): ?string
    {
        $metadata =
            $this->cache
                ->getMetadata();

        return
            $metadata['version']
            ?? null;
    }

    /**
     * Devuelve cuándo se actualizaron
     * los precios por última vez.
     */
    public function getUpdatedAt(): ?int
    {
        $metadata =
            $this->cache
                ->getMetadata();

        return
            isset(
                $metadata['updated_at']
            )
                ? (int)
                    $metadata['updated_at']
                : null;
    }

    /**
     * Carga los precios calculados desde la caché.
     */
    private function getPrices(): array
    {
        if (
            $this->prices !== null
        ) {
            return $this->prices;
        }

        $this->prices =
            $this->cache
                ->getCalculatedPrices();

        return $this->prices;
    }
}