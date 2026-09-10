<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

$isLoggedIn =
    isset($_SESSION['steam_id'])
    && !empty($_SESSION['steam_id']);
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
        content="Consulta y gestiona tu inventario de Counter-Strike 2 de forma sencilla."
    >

    <title>CS2 Inventory</title>

    <link
        rel="stylesheet"
        href="css/style.css"
    >
</head>

<body>

    <!-- =====================================================
         HEADER
         ===================================================== -->

    <header class="site-header">

        <div class="container header-container">

            <a
                href="index.php"
                class="logo"
            >
                CS2 Inventory
            </a>

            <nav class="main-nav">

                <ul>

                    <li>
                        <a href="#funciones">
                            ¿Qué puedes hacer?
                        </a>
                    </li>

                    <li>
                        <?php if ($isLoggedIn): ?>

                            <a
                                href="inventory.php"
                                class="nav-steam-button"
                            >
                                Ir a mi inventario
                            </a>

                        <?php else: ?>

                            <a
                                href="login.php"
                                class="nav-steam-button"
                            >
                                Iniciar sesión con Steam
                            </a>

                        <?php endif; ?>
                    </li>

                </ul>

            </nav>

        </div>

    </header>


    <main>

        <!-- =================================================
             HERO
             ================================================= -->

        <section class="hero">

            <div class="container hero-container">

                <div class="hero-content">

                    <span class="hero-badge">
                        COUNTER-STRIKE 2
                    </span>

                    <h1>
                        Consulta y gestiona
                        <span>tu inventario de CS2</span>
                    </h1>

                    <p class="hero-description">
                        Consulta tus objetos de Counter-Strike 2,
                        descubre el valor estimado de tus skins
                        y prepáralas para ponerlas a la venta
                        fácilmente.
                    </p>

                    <div class="hero-actions">

                        <?php if ($isLoggedIn): ?>

                            <a
                                href="inventory.php"
                                class="button button-primary"
                            >
                                Ir a mi inventario
                            </a>

                        <?php else: ?>

                            <a
                                href="login.php"
                                class="button button-primary"
                            >
                                Iniciar sesión con Steam
                            </a>

                        <?php endif; ?>

                        <a
                            href="#funciones"
                            class="button button-secondary"
                        >
                            Descubrir la plataforma
                        </a>

                    </div>

                </div>

            </div>

        </section>


        <!-- =================================================
             FUNCIONES
             ================================================= -->

        <section
            id="funciones"
            class="features-section"
        >

            <div class="container">

                <div class="section-heading">

                    <span class="section-label">
                        FUNCIONES
                    </span>

                    <h2>
                        ¿Qué puedes hacer aquí?
                    </h2>

                    <p>
                        Una forma sencilla de consultar tus objetos
                        de CS2 y prepararlos para venderlos.
                    </p>

                </div>


                <div class="features-grid">

                    <!-- =====================================
                         INVENTARIO
                         ===================================== -->

                    <article class="feature-card">

                        <div class="feature-icon">
                            🎒
                        </div>

                        <div class="feature-content">

                            <h3>
                                Ver tu inventario
                            </h3>

                            <p>
                                Consulta todos tus objetos de
                                Counter-Strike 2 en un solo lugar.
                                Puedes conocer información de tus
                                skins como su nombre, rareza,
                                desgaste y otros detalles.
                            </p>

                            <?php if ($isLoggedIn): ?>

                                <a
                                    href="inventory.php"
                                    class="feature-link"
                                >
                                    Ver mi inventario
                                    <span>→</span>
                                </a>

                            <?php else: ?>

                                <a
                                    href="login.php"
                                    class="feature-link"
                                >
                                    Acceder con Steam
                                    <span>→</span>
                                </a>

                            <?php endif; ?>

                        </div>

                    </article>


                    <!-- =====================================
                         VENTA
                         ===================================== -->

                    <article class="feature-card">

                        <div class="feature-icon">
                            💰
                        </div>

                        <div class="feature-content">

                            <h3>
                                Poner objetos en venta
                            </h3>

                            <p>
                                Selecciona los objetos que quieras
                                vender y publica un precio para que
                                otros usuarios puedan consultar tus
                                artículos disponibles.
                            </p>

                            <?php if ($isLoggedIn): ?>

                                <a
                                    href="inventory.php"
                                    class="feature-link"
                                >
                                    Gestionar mis objetos
                                    <span>→</span>
                                </a>

                            <?php else: ?>

                                <a
                                    href="login.php"
                                    class="feature-link"
                                >
                                    Empezar ahora
                                    <span>→</span>
                                </a>

                            <?php endif; ?>

                        </div>

                    </article>

                </div>

            </div>

        </section>


        <!-- =================================================
             CTA FINAL
             ================================================= -->

        <section class="cta-section">

            <div class="container">

                <div class="cta-card">

                    <div class="cta-content">

                        <span class="section-label">
                            EMPIEZA AHORA
                        </span>

                        <h2>
                            Ten tus skins bajo control.
                        </h2>

                        <p>
                            Conecta tu cuenta de Steam y consulta
                            tu inventario de CS2 de forma rápida
                            y sencilla.
                        </p>

                    </div>

                    <div class="cta-action">

                        <?php if ($isLoggedIn): ?>

                            <a
                                href="inventory.php"
                                class="button button-primary"
                            >
                                Ir a mi inventario
                            </a>

                        <?php else: ?>

                            <a
                                href="login.php"
                                class="button button-primary"
                            >
                                Iniciar sesión con Steam
                            </a>

                        <?php endif; ?>

                    </div>

                </div>

            </div>

        </section>

    </main>


    <!-- =====================================================
         FOOTER
         ===================================================== -->

    <footer class="site-footer">
        <div class="container footer-container">

            <div class="footer-brand">
                <p class="footer-copyright">
                    &copy;
                    <?= date('Y') ?>
                    CS2 Inventory
                </p>

                <p class="footer-disclaimer">
                    CS2 Inventory es una plataforma independiente
                    y no está afiliada, asociada, autorizada,
                    respaldada ni patrocinada por Valve Corporation.
                    Counter-Strike 2 y Steam son marcas comerciales
                    de Valve Corporation.
                </p>
            </div>

            <div class="footer-links">
                <a href="legal.php">
                    Aviso legal
                </a>
            </div>
        </div>
    </footer>

</body>

</html>