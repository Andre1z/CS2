<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';

require_once __DIR__ . '/../../vendor/autoload.php';

use CS2\Steam\SkinFloatService;

header(
    'Content-Type: application/json; charset=utf-8'
);

if (
    !isset(
        $_SESSION['steam_id']
    )
    &&
    !isset(
        $_SESSION['steamid']
    )
) {
    http_response_code(401);

    echo json_encode([
        'success' => false,
        'error' =>
            'No has iniciado sesión.'
    ]);

    exit;
}

$steamId =
    $_SESSION['steam_id']
    ?? $_SESSION['steamid']
    ?? null;

if (
    !is_string($steamId)
    ||
    !ctype_digit($steamId)
) {
    http_response_code(401);

    echo json_encode([
        'success' => false,
        'error' =>
            'SteamID inválido.'
    ]);

    exit;
}

$inspectUrl =
    $_GET['inspect_url']
    ?? '';

if (
    !is_string($inspectUrl)
    ||
    trim($inspectUrl) === ''
) {
    http_response_code(400);

    echo json_encode([
        'success' => false,
        'error' =>
            'Falta el inspect link.'
    ]);

    exit;
}

try {
    $service =
        new SkinFloatService();

    $result =
        $service->getFloat(
            $inspectUrl,
            $steamId
        );

    if (
        $result === null
    ) {
        http_response_code(404);

        echo json_encode([
            'success' => false,
            'error' =>
                'No se ha podido obtener el Float.'
        ]);

        exit;
    }

    echo json_encode(
        [
            'success' =>
                true,

            'float' =>
                $result['float'],

            'paint_seed' =>
                $result['paint_seed']
                ?? null,

            'wear_name' =>
                $result['wear_name']
                ?? null,

            'weapon_type' =>
                $result['weapon_type']
                ?? null,

            'item_name' =>
                $result['item_name']
                ?? null
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

    http_response_code(502);

    echo json_encode([
        'success' => false,
        'error' =>
            'No se ha podido consultar '
            . 'el Float en este momento.'
    ]);
}