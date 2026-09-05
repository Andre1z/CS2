<?php

require_once '../config/config.php';

$params = [

    'openid.ns' => 'http://specs.openid.net/auth/2.0',

    'openid.mode' => 'checkid_setup',

    'openid.return_to' => BASE_URL . '/steam_callback.php',

    'openid.realm' => BASE_URL,

    'openid.identity' => 'http://specs.openid.net/auth/2.0/identifier_select',

    'openid.claimed_id' => 'http://specs.openid.net/auth/2.0/identifier_select'

];

$url = STEAM_OPENID_URL . '?' . http_build_query($params);

header('Location: ' . $url);

exit;