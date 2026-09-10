<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

require_once __DIR__ . '/../vendor/autoload.php';

use CS2\Steam\SteamInventory;
use CS2\Pricing\PriceService;


/*
 * Compatibilidad temporal con ambas
 * claves de sesión.
 */
$steamId =
    $_SESSION['steam_id']
    ?? $_SESSION['steamid']
    ?? null;

if (
    !is_string($steamId)
    ||
    $steamId === ''
) {
    header(
        'Location: index.php'
    );

    exit;
}

$error = null;

$items = [];

$total = 0.0;

$priceUpdatedAt = null;

try {

    /*
     * INVENTARIO
     *
     * Siempre viene directamente
     * desde Steam.
     */
    $inventoryService =
        new SteamInventory();

    $items =
        $inventoryService
            ->getInventory(
                $steamId
            );


    /*
     * PRECIOS
     *
     * PriceService únicamente lee
     * la caché local.
     */
    $priceService =
        new PriceService();

    $items =
        $priceService
            ->addPrices(
                $items
            );

    $total =
        $priceService
            ->calculateTotal(
                $items
            );

    $priceUpdatedAt =
        $priceService
            ->getUpdatedAt();

} catch (
    Throwable $e
) {
    $error =
        $e->getMessage();
}


/*
 * =========================================================
 * AGRUPACIÓN
 * =========================================================
 */

$groups = [];

foreach (
    $items
    as $item
) {
    $marketHashName =
        $item['market_hash_name']
        ?? null;

    $assetId =
        (string) (
            $item['assetid']
            ?? ''
        );

    $stackable =
        !empty(
            $item['stackable']
        )
        &&
        $marketHashName !== null
        &&
        $marketHashName !== '';

    if (
        $stackable
    ) {
        $groupKey =
            'stack_'
            . hash(
                'sha256',
                $marketHashName
            );
    } else {
        $groupKey =
            'item_'
            . $assetId;
    }

    if (
        !isset(
            $groups[$groupKey]
        )
    ) {
        $groups[$groupKey] = [
            'key' =>
                $groupKey,

            'name' =>
                $item['name']
                ?? 'Objeto desconocido',

            'market_hash_name' =>
                $marketHashName,

            'icon_url' =>
                $item['icon_url']
                ?? null,

            'price' =>
                $item['price']
                ?? null,

            'price_min' =>
                $item['price_min']
                ?? null,

            'price_max' =>
                $item['price_max']
                ?? null,

            'price_count' =>
                $item['price_count']
                ?? 0,

            'stackable' =>
                $stackable,

            'items' =>
                []
        ];
    }

    $groups[$groupKey]['items'][] =
        $item;
}


/*
 * Orden inicial:
 * precio mayor → menor.
 */
uasort(
    $groups,
    static function (
        array $a,
        array $b
    ): int {
        return
            (
                (float) (
                    $b['price']
                    ?? 0
                )
            )
            <=>
            (
                (float) (
                    $a['price']
                    ?? 0
                )
            );
    }
);

?>

<!DOCTYPE html>

<html lang="es">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <meta
        name="description"
        content="Consulta y gestiona tu inventario de Counter-Strike 2."
    >

    <title>
        Mi inventario - CS2 Inventory
    </title>

    <link
        rel="stylesheet"
        href="css/style.css"
    >

</head>

<body class="inventory-body">


<header class="inventory-header">

    <a
        href="index.php"
        class="inventory-logo"
    >
        <span class="logo-dot"></span>

        CS2 INVENTORY
    </a>


    <div class="inventory-header-actions">

        <span class="inventory-steam-id">
            Steam
        </span>

        <a
            href="logout.php"
            class="logout-button"
        >
            Cerrar sesión
        </a>

    </div>

</header>


<main class="inventory-layout">


    <!-- =================================================
         INVENTORY AREA
         ================================================= -->

    <section class="inventory-main">


        <!-- TOOLBAR -->

        <div class="inventory-toolbar">

            <div class="toolbar-left">

                <button
                    type="button"
                    class="toolbar-icon-button"
                    id="refreshInventory"
                    title="Actualizar inventario"
                >
                    <svg
                        viewBox="0 0 24 24"
                        aria-hidden="true"
                    >
                        <path
                            d="M20 11a8 8 0 0 0-14.9-4M4 5v4h4"
                        />

                        <path
                            d="M4 13a8 8 0 0 0 14.9 4M20 19v-4h-4"
                        />
                    </svg>
                </button>


                <button
                    type="button"
                    class="select-all-button"
                    id="selectAll"
                >

                    <span class="check-icon">
                        ✓
                    </span>

                    <span>
                        Seleccionar todo
                    </span>

                    <span
                        class="inventory-count"
                        id="inventoryCount"
                    >
                        <?= count($items) ?>
                    </span>

                </button>

            </div>


            <div class="toolbar-center">

                <label
                    class="sort-control"
                    for="sortInventory"
                >

                    <svg
                        viewBox="0 0 24 24"
                        aria-hidden="true"
                    >
                        <path d="M8 5v14" />
                        <path d="m5 8 3-3 3 3" />
                        <path d="M16 19V5" />
                        <path d="m13 16 3 3 3-3" />
                    </svg>

                    <select
                        id="sortInventory"
                    >

                        <option value="price-desc">
                            Precio: Mayor a menor
                        </option>

                        <option value="price-asc">
                            Precio: Menor a mayor
                        </option>

                        <option value="name-asc">
                            Nombre: A-Z
                        </option>

                        <option value="name-desc">
                            Nombre: Z-A
                        </option>

                    </select>

                </label>

            </div>


            <div class="toolbar-search">

                <svg
                    viewBox="0 0 24 24"
                    aria-hidden="true"
                >
                    <circle
                        cx="11"
                        cy="11"
                        r="6"
                    />

                    <path
                        d="m16 16 4 4"
                    />
                </svg>

                <input
                    type="search"
                    id="searchInventory"
                    placeholder="Buscar skins..."
                    autocomplete="off"
                >

            </div>

        </div>


        <?php if ($error): ?>

            <div class="inventory-error">

                <strong>
                    No se ha podido cargar el inventario.
                </strong>

                <span>
                    <?= htmlspecialchars(
                        $error,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </span>

            </div>

        <?php else: ?>


            <div class="inventory-status">

                <div>

                    <strong>
                        Mi inventario
                    </strong>

                    <span>
                        <?= count($items) ?>
                        objetos
                    </span>

                </div>


                <?php if ($priceUpdatedAt): ?>

                    <span>

                        Precios actualizados:

                        <?= date(
                            'd/m/Y H:i',
                            $priceUpdatedAt
                        ) ?>

                    </span>

                <?php endif; ?>

            </div>


            <section
                class="inventory-grid"
                id="inventoryGrid"
            >

                <?php if (empty($groups)): ?>

                    <div class="empty-inventory">

                        <div class="empty-inventory-icon">
                            📦
                        </div>

                        <h2>
                            Tu inventario está vacío
                        </h2>

                        <p>
                            No se han encontrado objetos
                            de Counter-Strike 2.
                        </p>

                    </div>

                <?php else: ?>


                    <?php foreach (
                        $groups
                        as $group
                    ): ?>

                        <?php

                        $groupItems =
                            $group['items'];

                        $groupCount =
                            count(
                                $groupItems
                            );

                        $representative =
                            $groupItems[0];

                        $groupPrice =
                            $group['price'];

                        $nameColor =
                            $representative[
                                'name_color'
                            ]
                            ?? null;

                        $nameStyle =
                            $nameColor
                                ? '--item-name-color:#'
                                    . $nameColor
                                : '';

                        ?>

                        <article
                            class="
                                inventory-group
                                <?= $group['stackable']
                                    && $groupCount > 1
                                        ? 'has-stack'
                                        : '' ?>
                            "
                            data-group-key="<?= htmlspecialchars(
                                $group['key'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>"
                            data-name="<?= htmlspecialchars(
                                strtolower(
                                    $group['name']
                                ),
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>"
                            data-price="<?= htmlspecialchars(
                                (string) (
                                    $groupPrice
                                    ?? 0
                                ),
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>"
                        >


                            <!-- CARD PRINCIPAL -->

                            <div class="inventory-card">


                                <div class="card-top">

                                    <?php if (
                                        $group['stackable']
                                        &&
                                        $groupCount > 1
                                    ): ?>

                                        <span
                                            class="stack-count"
                                        >
                                            ×<?= $groupCount ?>
                                        </span>

                                    <?php endif; ?>


                                    <?php if (
                                        $group['stackable']
                                        &&
                                        $groupCount > 1
                                    ): ?>

                                        <button
                                            type="button"
                                            class="stack-toggle"
                                            title="Desagrupar objetos"
                                            aria-label="Desagrupar objetos"
                                        >

                                            <svg
                                                viewBox="0 0 24 24"
                                                aria-hidden="true"
                                            >
                                                <rect
                                                    x="4"
                                                    y="4"
                                                    width="7"
                                                    height="7"
                                                />

                                                <rect
                                                    x="13"
                                                    y="13"
                                                    width="7"
                                                    height="7"
                                                />

                                                <path
                                                    d="M13 7h4v4"
                                                />

                                                <path
                                                    d="m17 7-5 5"
                                                />
                                            </svg>

                                        </button>

                                    <?php endif; ?>

                                </div>


                                <div class="card-image">

                                    <?php if (
                                        !empty(
                                            $representative[
                                                'icon_url'
                                            ]
                                        )
                                    ): ?>

                                        <img
                                            src="https://community.cloudflare.steamstatic.com/economy/image/<?= htmlspecialchars(
                                                $representative[
                                                    'icon_url'
                                                ],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>/256fx256f"
                                            alt=""
                                            loading="lazy"
                                        >

                                    <?php else: ?>

                                        <div class="no-image">
                                            ?
                                        </div>

                                    <?php endif; ?>

                                </div>


                                <div class="card-body">


                                    <div
                                        class="item-type"
                                    >
                                        <?= htmlspecialchars(
                                            $representative[
                                                'type'
                                            ]
                                            ?? 'Objeto',
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </div>


                                    <h3
                                        class="item-name"
                                        style="<?= htmlspecialchars(
                                            $nameStyle,
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>"
                                    >
                                        <?= htmlspecialchars(
                                            $group['name'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </h3>


                                    <?php if (
                                        !empty(
                                            $representative[
                                                'wear'
                                            ]
                                        )
                                    ): ?>

                                        <span
                                            class="item-wear"
                                        >
                                            <?= htmlspecialchars(
                                                $representative[
                                                    'wear'
                                                ],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>
                                        </span>

                                    <?php endif; ?>


                                    <?php if (
                                        !empty(
                                            $representative[
                                                'has_float'
                                            ]
                                        )
                                    ): ?>

                                        <div
                                            class="item-float"
                                            data-inspect-url="<?= htmlspecialchars(
                                                $representative[
                                                    'inspect_url'
                                                ],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>"
                                        >
                                            Float
                                            <span>
                                                Cargando...
                                            </span>
                                        </div>

                                    <?php endif; ?>


                                    <?php if (
                                        $groupPrice !== null
                                    ): ?>

                                        <div class="card-price">

                                            <?= number_format(
                                                (float)
                                                $groupPrice,
                                                2,
                                                ',',
                                                '.'
                                            ) ?>

                                            €

                                        </div>

                                    <?php else: ?>

                                        <div
                                            class="card-price unavailable"
                                        >
                                            Precio no disponible
                                        </div>

                                    <?php endif; ?>


                                    <?php if (
                                        $group['price_count']
                                        > 0
                                    ): ?>

                                        <div
                                            class="card-markets"
                                        >
                                            <?= (int)
                                                $group[
                                                    'price_count'
                                                ] ?>

                                            mercados
                                        </div>

                                    <?php endif; ?>

                                </div>


                                <label
                                    class="item-checkbox-wrapper"
                                >

                                    <input
                                        type="checkbox"
                                        class="
                                            item-checkbox
                                            <?= $group[
                                                'stackable'
                                            ] && $groupCount > 1
                                                ? 'stack-master-checkbox'
                                                : ''
                                            ?>
                                        "
                                        data-group="<?= htmlspecialchars(
                                            $group['key'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>"
                                    >

                                    <span
                                        class="custom-checkbox"
                                    >
                                    </span>

                                </label>

                            </div>


                            <!-- =================================================
                                 ELEMENTOS DESAGRUPADOS
                                 ================================================= -->

                            <?php if (
                                $group['stackable']
                                &&
                                $groupCount > 1
                            ): ?>

                                <div
                                    class="stack-items"
                                >

                                    <?php foreach (
                                        $groupItems
                                        as $stackItem
                                    ): ?>

                                        <?php

                                        $stackNameColor =
                                            $stackItem[
                                                'name_color'
                                            ]
                                            ?? null;

                                        $stackNameStyle =
                                            $stackNameColor
                                                ? '--item-name-color:#'
                                                    . $stackNameColor
                                                : '';

                                        ?>

                                        <div
                                            class="stack-item"
                                            data-price="<?= htmlspecialchars(
                                                (string) (
                                                    $stackItem[
                                                        'price'
                                                    ]
                                                    ?? 0
                                                ),
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>"
                                        >

                                            <div
                                                class="stack-item-image"
                                            >

                                                <?php if (
                                                    !empty(
                                                        $stackItem[
                                                            'icon_url'
                                                        ]
                                                    )
                                                ): ?>

                                                    <img
                                                        src="https://community.cloudflare.steamstatic.com/economy/image/<?= htmlspecialchars(
                                                            $stackItem[
                                                                'icon_url'
                                                            ],
                                                            ENT_QUOTES,
                                                            'UTF-8'
                                                        ) ?>/256fx256f"
                                                        alt=""
                                                        loading="lazy"
                                                    >

                                                <?php endif; ?>

                                            </div>


                                            <div
                                                class="stack-item-info"
                                            >

                                                <strong
                                                    style="<?= htmlspecialchars(
                                                        $stackNameStyle,
                                                        ENT_QUOTES,
                                                        'UTF-8'
                                                    ) ?>"
                                                >
                                                    <?= htmlspecialchars(
                                                        $stackItem[
                                                            'name'
                                                        ],
                                                        ENT_QUOTES,
                                                        'UTF-8'
                                                    ) ?>
                                                </strong>


                                                <?php if (
                                                    !empty(
                                                        $stackItem[
                                                            'wear'
                                                        ]
                                                    )
                                                ): ?>

                                                    <span>
                                                        <?= htmlspecialchars(
                                                            $stackItem[
                                                                'wear'
                                                            ],
                                                            ENT_QUOTES,
                                                            'UTF-8'
                                                        ) ?>
                                                    </span>

                                                <?php endif; ?>


                                                <?php if (
                                                    !empty(
                                                        $stackItem[
                                                            'has_float'
                                                        ]
                                                    )
                                                ): ?>

                                                    <span
                                                        class="
                                                            item-float
                                                            stack-float
                                                        "
                                                        data-inspect-url="<?= htmlspecialchars(
                                                            $stackItem[
                                                                'inspect_url'
                                                            ],
                                                            ENT_QUOTES,
                                                            'UTF-8'
                                                        ) ?>"
                                                    >
                                                        Float
                                                        <b>
                                                            Cargando...
                                                        </b>
                                                    </span>

                                                <?php endif; ?>

                                            </div>


                                            <div
                                                class="stack-item-price"
                                            >

                                                <?php if (
                                                    $stackItem[
                                                        'price'
                                                    ] !== null
                                                ): ?>

                                                    <?= number_format(
                                                        (float)
                                                        $stackItem[
                                                            'price'
                                                        ],
                                                        2,
                                                        ',',
                                                        '.'
                                                    ) ?>

                                                    €

                                                <?php else: ?>

                                                    —

                                                <?php endif; ?>

                                            </div>


                                            <label
                                                class="
                                                    item-checkbox-wrapper
                                                    stack-child-checkbox
                                                "
                                            >

                                                <input
                                                    type="checkbox"
                                                    class="item-checkbox"
                                                    data-asset-id="<?= htmlspecialchars(
                                                        (string) (
                                                            $stackItem[
                                                                'assetid'
                                                            ]
                                                            ?? ''
                                                        ),
                                                        ENT_QUOTES,
                                                        'UTF-8'
                                                    ) ?>"
                                                    data-name="<?= htmlspecialchars(
                                                        $stackItem[
                                                            'name'
                                                        ],
                                                        ENT_QUOTES,
                                                        'UTF-8'
                                                    ) ?>"
                                                    data-price="<?= htmlspecialchars(
                                                        (string) (
                                                            $stackItem[
                                                                'price'
                                                            ]
                                                            ?? 0
                                                        ),
                                                        ENT_QUOTES,
                                                        'UTF-8'
                                                    ) ?>"
                                                    data-image="<?= htmlspecialchars(
                                                        $stackItem[
                                                            'icon_url'
                                                        ]
                                                        ?? '',
                                                        ENT_QUOTES,
                                                        'UTF-8'
                                                    ) ?>"
                                                >

                                                <span
                                                    class="custom-checkbox"
                                                >
                                                </span>

                                            </label>

                                        </div>

                                    <?php endforeach; ?>

                                </div>

                            <?php endif; ?>


                            <!-- DATOS DEL ITEM PRINCIPAL -->

                            <?php if (
                                !(
                                    $group['stackable']
                                    &&
                                    $groupCount > 1
                                )
                            ): ?>

                                <div
                                    class="single-item-data"
                                >

                                    <input
                                        type="hidden"
                                        class="single-item-name"
                                        value="<?= htmlspecialchars(
                                            $representative[
                                                'name'
                                            ],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>"
                                    >

                                    <input
                                        type="hidden"
                                        class="single-item-price"
                                        value="<?= htmlspecialchars(
                                            (string) (
                                                $representative[
                                                    'price'
                                                ]
                                                ?? 0
                                            ),
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>"
                                    >

                                    <input
                                        type="hidden"
                                        class="single-item-asset"
                                        value="<?= htmlspecialchars(
                                            (string) (
                                                $representative[
                                                    'assetid'
                                                ]
                                                ?? ''
                                            ),
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>"
                                    >

                                    <input
                                        type="hidden"
                                        class="single-item-image"
                                        value="<?= htmlspecialchars(
                                            $representative[
                                                'icon_url'
                                            ]
                                            ?? '',
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>"

                                </div>

                            <?php endif; ?>


                        </article>

                    <?php endforeach; ?>

                <?php endif; ?>

            </section>


            <div
                class="no-search-results"
                id="noSearchResults"
                hidden
            >
                <strong>
                    No se encontraron objetos.
                </strong>

                <span>
                    Prueba con otro nombre o búsqueda.
                </span>
            </div>


        <?php endif; ?>

    </section>


    <!-- =================================================
         SELL SIDEBAR
         ================================================= -->

    <aside class="sell-sidebar">


        <div class="sell-sidebar-header">

            <div>

                <span class="sell-icon">
                    🛍
                </span>

                <strong>
                    Tus artículos
                </strong>

            </div>


            <button
                type="button"
                id="clearSelection"
                class="clear-selection"
                title="Vaciar selección"
                disabled
            >
                Vaciar
            </button>

        </div>


        <div
            class="selected-items"
            id="selectedItems"
        >

            <div
                class="selected-empty"
                id="selectedEmpty"
            >

                <div>
                    🛒
                </div>

                <p>
                    Añade los artículos
                    que quieras vender
                </p>

            </div>

        </div>


        <div class="sell-sidebar-bottom">


            <div class="selected-total">

                <span>
                    TOTAL
                </span>

                <strong id="selectedTotal">
                    0,00 €
                </strong>

            </div>


            <button
                type="button"
                class="sell-button"
                id="sellButton"
                disabled
            >

                <span>
                    ⚡
                </span>

                Vender

            </button>


            <small class="sell-notice">
                La publicación de objetos se conectará
                en una fase posterior.
            </small>

        </div>

    </aside>

</main>


<script src="js/app.js"></script>

</body>

</html>