<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

require_once __DIR__ . '/../vendor/autoload.php';

use CS2\Steam\SteamInventory;
use CS2\Pricing\PriceService;

if (
    !isset($_SESSION['steamid'])
) {
    header(
        'Location: index.php'
    );

    exit;
}

$steamId =
    $_SESSION['steamid'];

$error = null;

$items = [];

$total = 0.0;

try {
    /*
     * 1. INVENTARIO
     *
     * El inventario se obtiene
     * directamente desde Steam.
     */
    $inventoryService =
        new SteamInventory();

    $items =
        $inventoryService
            ->getInventory(
                $steamId
            );

    /*
     * 2. PRECIOS
     *
     * CSGO Trader solamente proporciona
     * los precios de los objetos.
     */
    $priceService =
        new PriceService();

    $items =
        $priceService
            ->addPrices(
                $items
            );

    /*
     * 3. VALOR TOTAL
     */
    $total =
        $priceService
            ->calculateTotal(
                $items
            );

} catch (
    Throwable $e
) {
    $error =
        $e->getMessage();
}

?>
<!DOCTYPE html>

<html lang="es">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Mi inventario - CS2 Inventory
    </title>

    <link
        rel="stylesheet"
        href="css/style.css"
    >

</head>

<body>

<header class="navbar">

    <div class="logo">
        CS2 INVENTORY
    </div>

    <a href="logout.php">
        Cerrar sesión
    </a>

</header>

<main class="inventory-page">

    <h1>
        Mi inventario de CS2
    </h1>

    <?php if ($error): ?>

        <div class="error">

            <?= htmlspecialchars(
                $error,
                ENT_QUOTES,
                'UTF-8'
            ) ?>

        </div>

    <?php else: ?>

        <section class="stats">

            <div class="stat-card">

                <span>
                    Valor total
                </span>

                <strong>

                    <?= number_format(
                        $total,
                        2,
                        ',',
                        '.'
                    ) ?>

                    €

                </strong>

            </div>

            <div class="stat-card">

                <span>
                    Objetos
                </span>

                <strong>
                    <?= count($items) ?>
                </strong>

            </div>

        </section>

        <section class="inventory">

            <?php if (
                empty($items)
            ): ?>

                <p>
                    No se encontraron objetos.
                </p>

            <?php else: ?>

                <?php foreach (
                    $items as $item
                ): ?>

                    <div class="item">

                        <?php if (
                            !empty(
                                $item['icon_url']
                            )
                        ): ?>

                            <img
                                src="https://community.cloudflare.steamstatic.com/economy/image/<?= htmlspecialchars(
                                    $item['icon_url'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>/128fx128f"
                                alt=""
                                loading="lazy"
                            >

                        <?php endif; ?>

                        <div class="item-info">

                            <h3>

                                <?= htmlspecialchars(
                                    $item['name']
                                    ?? 'Objeto desconocido',
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>

                            </h3>

                            <small>

                                <?= htmlspecialchars(
                                    $item['market_hash_name']
                                    ?? '',
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>

                            </small>

                        </div>

                        <div class="item-price">

                            <?php if (
                                isset(
                                    $item['price']
                                ) &&
                                $item['price'] !== null
                            ): ?>

                                <?= number_format(
                                    (float) $item['price'],
                                    2,
                                    ',',
                                    '.'
                                ) ?>

                                €

                            <?php else: ?>

                                Precio no disponible

                            <?php endif; ?>

                        </div>

                    </div>

                <?php endforeach; ?>

            <?php endif; ?>

        </section>

    <?php endif; ?>

</main>

<script src="js/app.js"></script>

</body>

</html>