<?php

declare(strict_types=1);

namespace CS2\Pricing;

class PriceService
{
    /**
     * Devuelve el precio de un objeto.
     *
     * De momento devuelve null porque
     * todavía no hemos conectado el proveedor
     * de precios.
     */
    public function getPrice(
        ?string $marketHashName
    ): ?float {

        if (!$marketHashName) {
            return null;
        }

        /*
         * TODO:
         *
         * Aquí conectaremos la API
         * de precios que decidamos utilizar.
         */

        return null;
    }

    /**
     * Añade los precios al inventario.
     */
    public function addPrices(
        array $items
    ): array {

        foreach ($items as &$item) {

            $item['price'] = $this->getPrice(
                $item['market_hash_name']
            );
        }

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

            if ($item['price'] !== null) {

                $total += (float) $item['price'];
            }
        }

        return $total;
    }
}