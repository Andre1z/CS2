<?php

declare(strict_types=1);

namespace CS2\Pricing;

class PriceService
{
    /**
     * Obtiene el precio de un objeto.
     *
     * Actualmente es un placeholder.
     */
    public function getPrice(
        ?string $marketHashName
    ): ?float {
        if (!$marketHashName) {
            return null;
        }

        /*
         * TODO:
         * Aquí conectaremos la API de precios.
         */

        return null;
    }

    /**
     * Añade precios a los objetos.
     */
    public function addPrices(
        array $items
    ): array {
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
     * Calcula el valor total.
     */
    public function calculateTotal(
        array $items
    ): float {
        $total = 0.0;

        foreach ($items as $item) {
            if (
                $item['price'] !== null
            ) {
                $total +=
                    (float) $item['price'];
            }
        }

        return $total;
    }
}