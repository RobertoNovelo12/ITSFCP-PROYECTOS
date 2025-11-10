<?php
session_start();

// Si ya hay sesión activa, redirige según rol
if (isset($_SESSION['rol'])) {
    $base_url = "/ITSFCP-PROYECTOS/";
    switch ($_SESSION['rol']) {
        case 'Estudiante':
            header("Location: {$base_url}Vistas/proyectos/alumno.php");
            break;
        case 'Profesor':
        case 'Investigador':
            header("Location: {$base_url}Vistas/proyectos/profesor.php");
            break;
        case 'Supervisor':
            header("Location: {$base_url}Vistas/proyectos/supervisor.php");
            break;
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión</title>
    <link rel="stylesheet" href="./publico/css/styles.css">
</head>
<body class="body-register">
    <div class="header-log-reg">
        <a href="index.php" class="home-btn">Regresar al inicio</a>
        <a href="registro.php" class="toggle-btn">Crear cuenta</a>
    </div>

    <div class="container-register">
        <div class="content">
            <div class="title-log-reg">Iniciar sesión</div>

            <form class="form" action="./publico/config/login_.php" method="POST">
                <div class="input-group">
                    <input type="email" id="email" name="correo" class="input-field" placeholder=" " required>
                    <label for="email" class="floating-label">Correo electrónico</label>
                </div>

                    <div class="input-group">
                    <input type="password" id="password" name="contraseña" class="input-field" placeholder=" " required>
                    <label for="password" class="floating-label">Contraseña</label>
                    <span class="toggle-password-wrapper">
                        <img src="./publico/icons/solar_eye-closed-broken.webp" alt="Mostrar contraseña" id="togglePassword" class="toggle-password">
                    </span>
                    </div>


                <button type="submit" name="login" class="submit-btn">Iniciar sesión</button>
                <div class="link">¿Olvidaste tu contraseña?</div>
            </form>

            <?php if (isset($_GET['error'])): ?>
                <p style="color: red;"><?= htmlspecialchars($_GET['error']) ?></p>
            <?php endif; ?>
        </div>
    </div>
</body>
<script src="./publico/js/javascript.js"></script>
</html>