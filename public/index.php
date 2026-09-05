<?php

require_once '../config/config.php';

?>

<!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>CS2 Inventory</title>

    <link rel="stylesheet" href="css/style.css">

</head>

<body>

    <div class="container">

        <h1>CS2 Inventory</h1>

        <p>
            Consulta el valor de tu inventario de Counter-Strike 2.
        </p>

        <?php if (isset($_SESSION['steamid'])): ?>

            <p>
                Has iniciado sesión correctamente.
            </p>

            <p>
                SteamID:
                <strong>
                    <?= htmlspecialchars($_SESSION['steamid']) ?>
                </strong>
            </p>

            <a href="logout.php">
                Cerrar sesión
            </a>

        <?php else: ?>

            <a href="login.php" class="steam-button">

                🔵 Iniciar sesión con Steam

            </a>

        <?php endif; ?>

    </div>

</body>

</html>