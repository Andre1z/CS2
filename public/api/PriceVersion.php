<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';

require_once __DIR__ . '/../../vendor/autoload.php';

use CS2\Pricing\PriceService;

header(
    'Content-Type: application/json; charset=utf-8'
);

try {
    $service =
        new PriceService();

    echo json_encode([
        'success' =>
            true,

        'version' =>
            $service->getVersion(),

        'updated_at' =>
            $service->getUpdatedAt()
    ]);

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