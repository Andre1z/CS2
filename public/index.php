<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

$isLoggedIn = isset($_SESSION['steamid']);

?>

<!DOCTYPE html>

<html lang="es">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>CS2 Inventory</title>

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

</header>

<main class="hero">

    <div class="hero-content">

        <h1>
            Descubre cuánto vale tu inventario de CS2
        </h1>

        <p>
            Conecta tu cuenta de Steam y consulta
            el valor de todos tus objetos.
        </p>

        <?php if ($isLoggedIn): ?>

            <a
                href="inventory.php"
                class="steam-button"
            >
                Ver mi inventario
            </a>

        <?php else: ?>

            <a
                href="login.php"
                class="steam-button"
            >
                🔵 Iniciar sesión con Steam
            </a>

        <?php endif; ?>

    </div>

</main>

<script src="js/app.js"></script>

</body>

</html>