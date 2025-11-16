<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tareas</title>
    <link rel="stylesheet" href="../../publico/css/styles.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
</head>

<body>
<?php
session_start();

// ¿El usuario está logeado?
$usuario_logeado = isset($_SESSION['id_usuario']);
$nombre = $usuario_logeado ? $_SESSION['nombre'] : null;

// Primera letra del nombre
$inicial = $usuario_logeado ? strtoupper(substr($nombre, 0, 1)) : "";
?>

<div class="header">
    <div class="logo">ITSFCP/PROYECTOS</div>

    <div class="header-right">

        <input type="text" class="search-box" placeholder="Buscar">

        <?php if (!$usuario_logeado): ?>
            <!-- Usuario NO logeado -->
            <a class="login-btn" href="/ITSFCP-PROYECTOS/login.php">Iniciar sesión</a>

        <?php else: ?>

            <!-- ICONO DE NOTIFICACIONES -->
            <div class="notif-icon">
                <img src="/ITSFCP-PROYECTOS/publico/icons/notificacion.svg">
            </div>

            <!-- CONTENEDOR DE PERFIL -->
            <div class="profile-wrapper" id="userProfileBtn">

                <?php if (!empty($_SESSION['foto_url'])): ?>
                    <img class="avatar-img" src="/ITSFCP-PROYECTOS/img/avatars/<?php echo $_SESSION['foto_url']; ?>">
                <?php else: ?>
                    <div class="avatar-initial">
                        <?= $inicial ?>
                    </div>
                <?php endif; ?>

                <img class="avatar-arrow" src="/ITSFCP-PROYECTOS/publico/icons/caretaa.svg">
            </div>

            <!-- MENÚ DESPLEGABLE -->
            <div class="profile-dropdown" id="profileDropdown">
                <a href="/ITSFCP-PROYECTOS/logout.php" class="logout-btn">Cerrar sesión</a>
            </div>



        <?php endif; ?>
        <script src="/ITSFCP-PROYECTOS/publico/js/javascript.js"></script>


    </div>
</div>
