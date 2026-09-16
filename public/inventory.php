<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

require_once __DIR__ . '/../vendor/autoload.php';

use CS2\Steam\SteamInventory;
use CS2\Pricing\PriceService;


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
    header(
        'Location: index.php'
    );

    exit;
}


/*
 * ---------------------------------------------------------
 * VARIABLES
 * ---------------------------------------------------------
 */

$error = null;

$items = [];

$total = 0.0;

$priceVersion = null;

$priceUpdatedAt = null;


/*
 * ---------------------------------------------------------
 * CARGAR INVENTARIO
 * ---------------------------------------------------------
 */

try {

    /*
     * Steam es la única fuente del inventario.
     */
    $inventoryService =
        new SteamInventory();

    $items =
        $inventoryService->getInventory(
            (string)
            $steamId
        );


    /*
     * Precios desde la caché local.
     */
    $priceService =
        new PriceService();

    $items =
        $priceService->addPrices(
            $items
        );

    $total =
        $priceService->calculateTotal(
            $items
        );

    $priceVersion =
        $priceService->getVersion();

    $priceUpdatedAt =
        $priceService->getUpdatedAt();

} catch (
    Throwable $e
) {

    $error =
        $e->getMessage();
}


/*
 * ---------------------------------------------------------
 * AGRUPAR OBJETOS
 * ---------------------------------------------------------
 *
 * Las cajas y objetos stackable se agrupan por
 * market_hash_name.
 *
 * Las armas/skins con float NO se agrupan.
 */

$groups = [];

foreach (
    $items as $index => $item
) {
    $stackable =
        !empty(
            $item['stackable']
        );

    $marketHashName =
        $item['market_hash_name']
        ?? null;

    if (
        $stackable
        &&
        $marketHashName
    ) {

        $groupKey =
            'stack_'
            . sha1(
                (string)
                $marketHashName
            );

    } else {

        $groupKey =
            'item_'
            . (
                $item['assetid']
                ?? $index
            );
    }

    if (
        !isset(
            $groups[$groupKey]
        )
    ) {
        $groups[$groupKey] = [
            'key' =>
                $groupKey,

            'stackable' =>
                $stackable,

            'items' => [],
        ];
    }

    $groups[$groupKey]['items'][] =
        $item;
}


/*
 * ---------------------------------------------------------
 * ORDEN INICIAL
 * ---------------------------------------------------------
 *
 * Precio mayor a menor.
 */

usort(
    $groups,
    static function (
        array $a,
        array $b
    ): int {

        $priceA =
            (float)
            (
                $a['items'][0]['price']
                ?? 0
            );

        $priceB =
            (float)
            (
                $b['items'][0]['price']
                ?? 0
            );

        return $priceB <=> $priceA;
    }
);

?>
<!DOCTYPE html>

<html
    lang="es"
>

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

<body
    class="inventory-body"
>

<header
    class="inventory-header"
>

    <a
        href="index.php"
        class="inventory-logo"
    >
        CS2 INVENTORY
    </a>

    <div
        class="inventory-header-right"
    >

        <span
            class="steam-user"
        >
            Steam
        </span>

        <a
            href="logout.php"
            class="logout-link"
        >
            Cerrar sesión
        </a>

    </div>

</header>


<main
    class="inventory-shell"
>

    <?php if ($error): ?>

        <section
            class="inventory-error"
        >

            <strong>
                No se pudo cargar el inventario
            </strong>

            <p>

                <?= htmlspecialchars(
                    $error,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>

            </p>

        </section>

    <?php else: ?>


        <section
            class="inventory-main"
        >

            <!-- =========================================
                 TOOLBAR
                 ========================================= -->

            <div
                class="inventory-toolbar"
            >

                <button
                    type="button"
                    id="refresh-inventory"
                    class="toolbar-button"
                    title="Actualizar"
                >
                    ↻
                </button>


                <button
                    type="button"
                    id="select-all"
                    class="select-all-button"
                >

                    Seleccionar todo

                    <span
                        id="select-all-count"
                    >
                        0
                    </span>

                </button>


                <select
                    id="inventory-sort"
                    class="sort-select"
                >

                    <option
                        value="price-desc"
                    >
                        Precio: Mayor a menor
                    </option>

                    <option
                        value="price-asc"
                    >
                        Precio: Menor a mayor
                    </option>

                    <option
                        value="name-asc"
                    >
                        Nombre: A-Z
                    </option>

                    <option
                        value="name-desc"
                    >
                        Nombre: Z-A
                    </option>

                </select>


                <div
                    class="inventory-search"
                >

                    <span>
                        ⌕
                    </span>

                    <input
                        type="search"
                        id="inventory-search"
                        placeholder="Buscar skins..."
                        autocomplete="off"
                    >

                </div>

            </div>


            <!-- =========================================
                 INFORMACIÓN
                 ========================================= -->

            <div
                class="inventory-info"
            >

                <div>

                    <strong>
                        Mi inventario
                    </strong>

                    <span>
                        <?= count($items) ?> objetos
                    </span>

                </div>


                <div>

                    <span>
                        Valor total
                    </span>

                    <strong>

                        <?= number_format(
                            $total,
                            0,
                            ',',
                            '.'
                        ) ?>

                        €

                    </strong>

                </div>

            </div>


            <?php if ($priceUpdatedAt): ?>

                <div
                    class="price-update-info"
                >

                    Precios actualizados:

                    <?= date(
                        'd/m/Y H:i',
                        $priceUpdatedAt
                    ) ?>

                </div>

            <?php endif; ?>


            <!-- =========================================
                 GRID
                 ========================================= -->

            <div
                class="inventory-grid"
                id="inventory-grid"
            >

                <?php foreach (
                    $groups as $group
                ): ?>

                    <?php

                    $groupItems =
                        $group['items'];

                    $isStack =
                        $group['stackable']
                        &&
                        count(
                            $groupItems
                        ) > 1;

                    $representative =
                        $groupItems[0];

                    $unitPrice =
                        (float)
                        (
                            $representative['price']
                            ?? 0
                        );

                    $groupName =
                        $representative['name']
                        ?? 'Objeto';

                    $searchName =
                        strtolower(
                            (
                                $representative['name']
                                ?? ''
                            )
                            . ' '
                            .
                            (
                                $representative['market_hash_name']
                                ?? ''
                            )
                            . ' '
                            .
                            (
                                $representative['type']
                                ?? ''
                            )
                        );

                    ?>


                    <article
                        class="inventory-card <?= $isStack ? 'is-stack' : '' ?>"
                        data-group-key="<?= htmlspecialchars(
                            $group['key'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                        data-price="<?= $unitPrice ?>"
                        data-name="<?= htmlspecialchars(
                            $groupName,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                        data-search="<?= htmlspecialchars(
                            $searchName,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                    >

                        <?php if ($isStack): ?>

                            <!-- =================================
                                 STACK
                                 ================================= -->

                            <button
                                type="button"
                                class="stack-toggle"
                                title="Mostrar objetos"
                                aria-label="Mostrar objetos"
                            >
                                <span>
                                    ↕
                                </span>
                            </button>


                            <span
                                class="stack-count"
                            >
                                ×<?= count(
                                    $groupItems
                                ) ?>
                            </span>


                            <div
                                class="inventory-card-image"
                            >

                                <?php if (
                                    !empty(
                                        $representative['icon_url']
                                    )
                                ): ?>

                                    <img
                                        src="https://community.cloudflare.steamstatic.com/economy/image/<?= htmlspecialchars(
                                            $representative['icon_url'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>/256fx256f"
                                        alt=""
                                        loading="lazy"
                                    >

                                <?php endif; ?>

                            </div>


                            <div
                                class="inventory-card-content"
                            >

                                <span
                                    class="inventory-card-type"
                                >
                                    <?= htmlspecialchars(
                                        $representative['type']
                                        ?? 'Objeto',
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </span>


                                <h3
                                    class="inventory-card-name"
                                >

                                    <?= htmlspecialchars(
                                        $groupName,
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>

                                </h3>


                                <div
                                    class="inventory-card-price"
                                >

                                    <?= number_format(
                                        $unitPrice,
                                        2,
                                        ',',
                                        '.'
                                    ) ?>

                                    €

                                    <small>
                                        / unidad
                                    </small>

                                </div>


                                <div
                                    class="inventory-float no-float"
                                    aria-hidden="true"
                                >
                                    &nbsp;
                                </div>


                                <label
                                    class="card-checkbox stack-master-checkbox"
                                >

                                    <input
                                        type="checkbox"
                                        class="stack-master"
                                        data-group-key="<?= htmlspecialchars(
                                            $group['key'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>"
                                    >

                                    <span></span>

                                </label>

                            </div>


                            <!-- =================================
                                 OBJETOS DEL STACK
                                 ================================= -->

                            <div
                                class="stack-items"
                            >

                                <?php foreach (
                                    $groupItems as $stackItem
                                ): ?>

                                    <label
                                        class="stack-item-row"
                                    >

                                        <span
                                            class="stack-item-name"
                                        >

                                            <?= htmlspecialchars(
                                                $stackItem['name']
                                                ?? 'Objeto',
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>

                                        </span>


                                        <strong>

                                            <?= number_format(
                                                (float)
                                                (
                                                    $stackItem['price']
                                                    ?? $unitPrice
                                                ),
                                                2,
                                                ',',
                                                '.'
                                            ) ?>

                                            €

                                        </strong>


                                        <input
                                            type="checkbox"
                                            class="item-checkbox"
                                            data-asset-id="<?= htmlspecialchars(
                                                (string)
                                                (
                                                    $stackItem['assetid']
                                                    ?? ''
                                                ),
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>"
                                            data-stackable="1"
                                            data-group-key="<?= htmlspecialchars(
                                                $group['key'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>"
                                            data-price="<?= (float) (
                                                $stackItem['price']
                                                ?? $unitPrice
                                            ) ?>"
                                            data-name="<?= htmlspecialchars(
                                                $stackItem['name']
                                                ?? 'Objeto',
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>"
                                            data-market-hash-name="<?= htmlspecialchars(
                                                $stackItem['market_hash_name']
                                                ?? '',
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>"
                                        >

                                    </label>

                                <?php endforeach; ?>

                            </div>


                        <?php else: ?>

                            <!-- =================================
                                 OBJETO NORMAL
                                 ================================= -->

                            <?php

                            $item =
                                $representative;

                            $itemPrice =
                                (float)
                                (
                                    $item['price']
                                    ?? 0
                                );

                            ?>


                            <div
                                class="inventory-card-image"
                            >

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
                                        ) ?>/256fx256f"
                                        alt=""
                                        loading="lazy"
                                    >

                                <?php endif; ?>

                            </div>


                            <div
                                class="inventory-card-content"
                            >

                                <span
                                    class="inventory-card-type"
                                >

                                    <?= htmlspecialchars(
                                        $item['type']
                                        ?? $item['weapon']
                                        ?? 'Objeto',
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>

                                </span>


                                <h3
                                    class="inventory-card-name"
                                    style="<?=
                                        !empty(
                                            $item['name_color']
                                        )
                                            ? 'color: '
                                                . htmlspecialchars(
                                                    $item['name_color'],
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                )
                                                . ';'
                                            : ''
                                    ?>"
                                >

                                    <?= htmlspecialchars(
                                        $item['name']
                                        ?? 'Objeto desconocido',
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>

                                </h3>


                                <div
                                    class="inventory-card-price"
                                >

                                    <strong>

                                        <?= number_format(
                                            $itemPrice,
                                            2,
                                            ',',
                                            '.'
                                        ) ?>

                                        €

                                    </strong>

                                    <?php if (
                                        !empty(
                                            $item['price_count']
                                        )
                                    ): ?>

                                        <small>

                                            <?= (int)
                                                $item['price_count'] ?>

                                            mercados

                                        </small>

                                    <?php endif; ?>

                                </div>


                                <!--
                                    Esta zona siempre existe.
                                    Si todavía no tenemos float,
                                    JS mantiene el espacio.
                                -->

                                <div
                                    class="inventory-float <?= !empty(
                                        $item['has_float']
                                    ) ? 'float-pending' : 'no-float' ?>"
                                    <?php if (
                                        !empty(
                                            $item['inspect_url']
                                        )
                                    ): ?>

                                        data-inspect="<?= htmlspecialchars(
                                            $item['inspect_url'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>"

                                    <?php endif; ?>
                                >

                                    <?php if (
                                        !empty(
                                            $item['has_float']
                                        )
                                    ): ?>

                                        <span
                                            class="float-value"
                                        >
                                            Float cargando...
                                        </span>

                                        <div
                                            class="float-bar"
                                        ></div>

                                    <?php else: ?>

                                        <span>
                                            &nbsp;
                                        </span>

                                    <?php endif; ?>

                                </div>


                                <label
                                    class="card-checkbox"
                                >

                                    <input
                                        type="checkbox"
                                        class="item-checkbox"
                                        data-asset-id="<?= htmlspecialchars(
                                            (string)
                                            (
                                                $item['assetid']
                                                ?? ''
                                            ),
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>"
                                        data-stackable="0"
                                        data-price="<?= $itemPrice ?>"
                                        data-name="<?= htmlspecialchars(
                                            $item['name']
                                            ?? 'Objeto',
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>"
                                        data-market-hash-name="<?= htmlspecialchars(
                                            $item['market_hash_name']
                                            ?? '',
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>"
                                    >

                                    <span></span>

                                </label>

                            </div>

                        <?php endif; ?>

                    </article>

                <?php endforeach; ?>

            </div>

        </section>


        <!-- =============================================
             PANEL DERECHO
             ============================================= -->

        <aside
            class="selected-panel"
        >

            <div
                class="selected-panel-header"
            >

                <div
                    class="selected-title-row"
                >

                    <h2>
                        Tus artículos
                    </h2>

                    <span
                        id="selected-count"
                        class="selected-count"
                    >
                        0
                    </span>

                </div>


                <!--
                    Solo aparece cuando hay selección
                    de objetos agrupables.
                -->

                <button
                    type="button"
                    id="exit-group-selection"
                    class="exit-group-selection"
                    hidden
                >
                    ← Salir de selección
                </button>

            </div>


            <div
                id="selected-items"
                class="selected-items"
            >

                <div
                    class="selected-empty"
                >
                    Selecciona objetos para añadirlos aquí.
                </div>

            </div>


            <div
                class="selected-panel-footer"
            >

                <div
                    class="selected-total"
                >

                    <span>
                        Total
                    </span>

                    <strong
                        id="selected-total"
                    >
                        0,00 €
                    </strong>

                </div>


                <button
                    type="button"
                    id="sell-selected"
                    class="sell-button"
                    disabled
                >
                    Vender
                </button>

            </div>

        </aside>

    <?php endif; ?>

</main>


<div
    id="inventory-toast"
    class="inventory-toast"
></div>


<script
    src="js/app.js"
></script>

</body>

</html>