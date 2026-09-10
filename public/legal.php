<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
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
        content="Aviso legal de CS2 Inventory."
    >

    <title>Aviso legal - CS2 Inventory</title>

    <link
        rel="stylesheet"
        href="css/style.css"
    >
</head>

<body>

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
                    <a href="index.php">
                        Inicio
                    </a>
                </li>

                <li>
                    <?php if (
                        isset($_SESSION['steam_id'])
                        && !empty($_SESSION['steam_id'])
                    ): ?>

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

<section class="legal-section">

    <div class="container legal-container">

        <span class="section-label">
            INFORMACIÓN LEGAL
        </span>

        <h1>
            Aviso legal
        </h1>

        <div class="legal-content">

            <h2>1. Información general</h2>

            <p>
                Este sitio web, denominado
                <strong>CS2 Inventory</strong>,
                es una plataforma independiente destinada
                a la consulta y gestión de inventarios de
                objetos de Counter-Strike 2.
            </p>


            <h2>2. Relación con Valve</h2>

            <p>
                <strong>
                    CS2 Inventory no está afiliado, asociado,
                    autorizado, respaldado ni patrocinado por
                    Valve Corporation.
                </strong>
            </p>

            <p>
                Steam y Counter-Strike 2 son marcas comerciales
                de Valve Corporation. Este sitio web es un
                proyecto independiente y no representa a
                Valve Corporation.
            </p>


            <h2>3. Uso de Steam</h2>

            <p>
                La autenticación de usuarios mediante Steam
                se realiza utilizando los mecanismos de
                autenticación proporcionados por Steam.
                CS2 Inventory no solicita ni almacena las
                contraseñas de las cuentas de Steam.
            </p>


            <h2>4. Inventarios y precios</h2>

            <p>
                La información mostrada sobre los inventarios
                depende de la información pública disponible
                en Steam.
            </p>

            <p>
                Los precios mostrados en la plataforma tienen
                carácter meramente orientativo y pueden no
                coincidir con el precio real de venta o compra
                de un objeto en un determinado mercado.
            </p>


            <h2>5. Responsabilidad</h2>

            <p>
                CS2 Inventory no garantiza la disponibilidad,
                exactitud o actualización permanente de la
                información procedente de servicios externos.
            </p>

            <p>
                El usuario es responsable de las decisiones
                que tome basándose en la información mostrada
                en esta plataforma.
            </p>


            <h2>6. Propiedad intelectual</h2>

            <p>
                Las marcas, nombres y elementos pertenecientes
                a terceros continúan siendo propiedad de sus
                respectivos titulares.
            </p>

            <p>
                CS2 Inventory no reclama derechos de propiedad
                sobre las marcas registradas de Valve Corporation
                ni sobre otros elementos pertenecientes a sus
                respectivos propietarios.
            </p>


            <h2>7. Contacto</h2>

            <p>
                Para cualquier consulta relacionada con esta
                plataforma, puedes utilizar los medios de
                contacto indicados en el sitio web.
            </p>

        </div>

    </div>

</section>

</main>


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