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
            (float)
            $prices[
                $marketHashName
            ]['average'];
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
                    ? $details['average']
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
        }

        unset($item);

        return $items;
    }

    public function calculateTotal(
        array $items
    ): float {
        $total = 0.0;

        foreach (
            $items as $item
        ) {
            if (
                isset($item['price'])
                &&
                $item['price'] !== null
            ) {
                $total +=
                    (float) $item['price'];
            }
        }

        return round(
            $total,
            2
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