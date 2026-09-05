<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

require_once __DIR__ . '/../vendor/autoload.php';

use CS2\Steam\SteamAuth;

$auth = new SteamAuth(
    APP_URL . '/steam_callback.php'
);

/*
 * Comprobar que Steam ha devuelto
 * una respuesta OpenID.
 */
if (empty($_GET['openid_mode'])) {

    die(
        'Steam no ha devuelto una respuesta válida.'
    );
}

/*
 * Verificar la respuesta con Steam.
 */
if (!$auth->validateResponse($_GET)) {

    http_response_code(401);

    die(
        'No se pudo verificar la autenticación con Steam.'
    );
}

/*
 * Obtener SteamID64.
 */
$steamId = $auth->getSteamId($_GET);

if ($steamId === null) {

    http_response_code(400);

    die(
        'No se pudo obtener el SteamID.'
    );
}

/*
 * Regenerar la sesión.
 */
session_regenerate_id(true);

/*
 * Guardar SteamID.
 */
$_SESSION['steamid'] = $steamId;

/*
 * Ir al inventario.
 */
header(
    'Location: ' . APP_URL . '/inventory.php'
);

exit;