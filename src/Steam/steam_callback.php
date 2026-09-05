<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

require_once __DIR__ . '/../vendor/autoload.php';

use CS2\Steam\SteamAuth;

$auth = new SteamAuth(
    APP_URL . '/steam_callback.php'
);

if (!$auth->validateResponse($_GET)) {

    http_response_code(401);

    die('No se pudo verificar la autenticación con Steam.');
}

$steamId = $auth->getSteamId($_GET);

if ($steamId === null) {

    http_response_code(400);

    die('SteamID no válido.');
}

/*
 * Regeneramos la sesión para evitar
 * problemas de session fixation.
 */
session_regenerate_id(true);

$_SESSION['steamid'] = $steamId;

header(
    'Location: ' . APP_URL . '/inventory.php'
);

exit;