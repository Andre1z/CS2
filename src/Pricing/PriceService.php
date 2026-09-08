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

        return
            isset(
                $prices[$marketHashName]['average']
            )
                ? (float)
                    $prices[
                        $marketHashName
                    ]['average']
                : null;
    }

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
                        $prices[$name]
                        ?? null
                    )
                    : null;

            $item['price'] =
                $details !== null
                    ? (
                        isset(
                            $details['average']
                        )
                            ? (float)
                                $details['average']
                            : null
                    )
                    : null;

            $item['price_min'] =
                $details['min']
                ?? null;

            $item['price_max'] =
                $details['max']
                ?? null;

            $item['price_count'] =
                $details['count']
                ?? 0;

            $item['price_total_sources'] =
                $details['total_sources']
                ?? 0;

            $item['price_markets'] =
                $details['prices']
                ?? [];

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
     * El resultado se redondea deliberadamente
     * a euros enteros.
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
            ) {
                $total +=
                    (float)
                    $item['price'];
            }
        }

        /*
         * Queremos:
         *
         * 12.37 € -> 12 €
         * 12.50 € -> 13 €
         * 12.81 € -> 13 €
         *
         * PHP_ROUND_HALF_UP evita comportamientos
         * extraños con el redondeo convencional.
         */
        return (float) round(
            $total,
            0,
            PHP_ROUND_HALF_UP
        );
    }

    public function getVersion(): ?string
    {
        $metadata =
            $this->cache
                ->getMetadata();

        return
            $metadata['version']
            ?? null;
    }

    public function getUpdatedAt(): ?int
    {
        $metadata =
            $this->cache
                ->getMetadata();

        return
            isset($metadata['updated_at'])
                ? (int)
                    $metadata['updated_at']
                : null;
    }

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