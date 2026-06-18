<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión</title>
    <link rel="stylesheet" href="/public/css/styles.css">
</head>
<body class="body-register">

    <?php if (isset($_GET['solicitud_enviada'])): ?>
        <div class="modal-overlay" id="modalSolicitud">
            <div class="modal-content">
                <h2>¡Solicitud enviada!</h2>
                <p>Tu cuenta aún está en espera de aprobación.</p>
                <button class="submit-btn" onclick="cerrarModal()">Aceptar</button>
            </div>
        </div>
        <script>
            document.getElementById("modalSolicitud").style.display = "flex";
            function cerrarModal() {
                window.location.href = "/Modules/Login/Views/index.php";
            }
        </script>
    <?php endif; ?>

    <div class="header-log-reg">
        <a href="/index.php" class="home-btn">Regresar al inicio</a>
        <a href="/registro.php" class="toggle-btn">Crear cuenta</a>
    </div>

    <div class="container-register">
        <div class="content">
            <div class="title-log-reg">Iniciar sesión</div>

            <!-- El form ahora apunta al mismo login.php (que actúa como entrada) -->
            <form class="form" action="/login.php" method="POST">
                <div class="input-group">
                    <input type="email" id="email" name="correo"
                           class="input-field" placeholder=" " required>
                    <label for="email" class="floating-label">Correo electrónico</label>
                </div>

                <div class="input-group">
                    <input type="password" id="password" name="contraseña"
                           class="input-field" placeholder=" " required>
                    <label for="password" class="floating-label">Contraseña</label>
                    <span class="toggle-password-wrapper">
                        <img src="/public/icons/solar_eye-closed-broken.webp"
                             id="togglePassword" class="toggle-password">
                    </span>
                </div>

                <button type="submit" name="login" class="submit-btn">Iniciar sesión</button>

                <?php if (isset($_GET['error'])): ?>
                    <p style="color:red;"><?= htmlspecialchars($_GET['error']) ?></p>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <script src="/public/js/javascript.js"></script>
</body>
</html>