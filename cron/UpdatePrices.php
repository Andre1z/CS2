<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

require_once __DIR__ . '/../vendor/autoload.php';

use CS2\Pricing\PriceUpdater;

try {
    $updater =
        new PriceUpdater();

    $result =
        $updater->update();

    echo PHP_EOL;

    echo 'Precios actualizados correctamente.'
        . PHP_EOL;

    echo 'Versión: '
        . $result['version']
        . PHP_EOL;

    echo 'Proveedores: '
        . implode(
            ', ',
            $result['providers']
        )
        . PHP_EOL;

    echo 'Objetos: '
        . $result['items']
        . PHP_EOL;

} catch (
    Throwable $e
) {
    fwrite(
        STDERR,
        'ERROR: '
        . $e->getMessage()
        . PHP_EOL
    );

    exit(1);
}