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
        <a href="index.php" class="home-btn">
            Regresar al inicio
        </a>
        <a href="registro.php" class="toggle-btn">Crear cuenta</a>
    </div>

    <div class="container-register">
        <div class="content">
            <div class="title-log-reg">Iniciar sesión</div>

            <form class="form" action="#" method="POST">
                <div class="input-group">
                    <input type="email" id="email" class="input-field" placeholder=" " required>
                    <label for="email" class="floating-label">Correo electrónico</label>
                </div>

                <div class="input-group">
                    <input type="password" id="password" class="input-field" placeholder=" " required>
                    <label for="password" class="floating-label">Contraseña</label>
                </div>

                <button type="submit" class="submit-btn">Iniciar sesión</button>
                <div class="link">¿Olvidaste tu contraseña?</div>
            </form>
        </div>
    </div>
</body>
</html>