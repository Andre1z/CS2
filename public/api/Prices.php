<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';

require_once __DIR__ . '/../../vendor/autoload.php';

use CS2\Pricing\PriceService;

header(
    'Content-Type: application/json; charset=utf-8'
);

try {
    $input =
        json_decode(
            file_get_contents('php://input'),
            true
        );

    if (
        !is_array($input)
    ) {
        throw new RuntimeException(
            'Petición inválida.'
        );
    }

    $names =
        $input['items']
        ?? [];

    if (
        !is_array($names)
    ) {
        throw new RuntimeException(
            'La lista de objetos no es válida.'
        );
    }

    /*
     * Evitamos peticiones gigantes.
     */
    $names =
        array_slice(
            $names,
            0,
            5000
        );

    $service =
        new PriceService();

    $result = [];

    foreach (
        $names as $name
    ) {
        if (
            !is_string($name)
            ||
            trim($name) === ''
        ) {
            continue;
        }

        $details =
            $service->getPriceDetails(
                $name
            );

        if (
            $details === null
        ) {
            continue;
        }

        $result[$name] =
            $details;
    }

    echo json_encode([
        'success' =>
            true,

        'version' =>
            $service->getVersion(),

        'updated_at' =>
            $service->getUpdatedAt(),

        'prices' =>
            $result
    ], JSON_UNESCAPED_UNICODE);

} catch (
    Throwable $e
) {
    http_response_code(500);

    echo json_encode([
        'success' =>
            false,

        'error' =>
            $e->getMessage()
    ]);
}