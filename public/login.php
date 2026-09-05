<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

require_once __DIR__ . '/../vendor/autoload.php';

use CS2\Steam\SteamAuth;

$auth = new SteamAuth(
    APP_URL . '/steam_callback.php'
);

header(
    'Location: ' . $auth->getLoginUrl()
);

exit;