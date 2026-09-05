<?php

declare(strict_types=1);

session_start();

define(
    'BASE_PATH',
    dirname(__DIR__)
);

define(
    'APP_URL',
    getenv('APP_URL') ?: 'http://localhost:8000'
);