<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';

require_once __DIR__ . '/../../vendor/autoload.php';

use CS2\Steam\SkinFloatService;

header(
    'Content-Type: application/json; charset=utf-8'
);

header(
    'Cache-Control: no-store, no-cache, must-revalidate'
);


/*
 * ---------------------------------------------------------
 * SESIÓN
 * ---------------------------------------------------------
 */

$steamId =
    $_SESSION['steamid']
    ?? $_SESSION['steam_id']
    ?? null;

if (
    !$steamId
) {
    http_response_code(401);

    echo json_encode(
        [
            'success' => false,
            'message' => 'Sesión no válida.',
        ],
        JSON_UNESCAPED_UNICODE
    );

    exit;
}


/*
 * ---------------------------------------------------------
 * INSPECT URL
 * ---------------------------------------------------------
 */

$inspectUrl =
    $_GET['inspect_url']
    ?? $_GET['url']
    ?? '';

$inspectUrl =
    trim(
        (string)
        $inspectUrl
    );

if (
    $inspectUrl === ''
) {
    http_response_code(400);

    echo json_encode(
        [
            'success' => false,
            'message' => 'Inspect URL no proporcionada.',
        ],
        JSON_UNESCAPED_UNICODE
    );

    exit;
}


/*
 * ---------------------------------------------------------
 * SERVICIO
 * ---------------------------------------------------------
 */

try {

    $service =
        new SkinFloatService();

    $result =
        $service->getFloat(
            $inspectUrl,
            (string)
            $steamId
        );

    /*
     * No es un error HTTP que un objeto no tenga
     * float disponible.
     */
    if (
        $result === null
    ) {
        echo json_encode(
            [
                'success' => false,
                'available' => false,
                'message' =>
                    'Float no disponible.',
            ],
            JSON_UNESCAPED_UNICODE
        );

        exit;
    }

    echo json_encode(
        [
            'success' => true,
            'available' => true,

            'float' =>
                $result['float']
                ?? null,

            'paint_seed' =>
                $result['paint_seed']
                ?? null,

            'wear' =>
                $result['wear']
                ?? null,
        ],
        JSON_UNESCAPED_UNICODE
    );

} catch (
    Throwable $e
) {

    error_log(
        'Float API error: '
        . $e->getMessage()
    );

    http_response_code(500);

    echo json_encode(
        [
            'success' => false,
            'available' => false,
            'message' =>
                'No se pudo obtener el float.',
        ],
        JSON_UNESCAPED_UNICODE
    );
}