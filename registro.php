<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear Cuenta</title>
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
            <div class="title-log-reg">Crear cuenta</div>

            <form class="form" action="#" method="POST">
                <div class="input-group">
                    <input type="text" id="curp" class="input-field" placeholder=" " required>
                    <label for="curp" class="floating-label">CURP</label>
                </div>

                <div class="input-group">
                    <input type="email" id="email-register" class="input-field" placeholder=" " required>
                    <label for="email-register" class="floating-label">Correo electrónico</label>
                </div>
                
                <div class="date-group">
                    <label class="date-label">Fecha de nacimiento</label>
                    <div class="date-inputs">
                        <div class="input-group">
                            <input type="number" id="day" class="input-field date-field" placeholder=" " min="1" max="31" required>
                            <label for="day" class="floating-label">Día</label>
                        </div>
                        <div class="input-group">
                            <input type="number" id="month" class="input-field date-field" placeholder=" " min="1" max="12" required>
                            <label for="month" class="floating-label">Mes</label>
                        </div>
                        <div class="input-group">
                            <input type="number" id="year" class="input-field date-field" placeholder=" " min="1900" max="2024" required>
                            <label for="year" class="floating-label">Año</label>
                        </div>
                    </div>
                </div>

                <div class="input-group">
                    <input type="password" id="password1" class="input-field" placeholder=" " required>
                    <label for="password1" class="floating-label">Contraseña</label>
                </div>

                <div class="input-group">
                    <input type="password" id="password2" class="input-field" placeholder=" " required>
                    <label for="password2" class="floating-label">Confirmar contraseña</label>
                </div>

                <div class="terms">
                    Al crear una cuenta, aceptas los <a href="#">Términos del servicio</a> y <a href="#"></ahref>Política de privacidad</a>
                </div>
                <button type="submit" class="submit-btn">Crear cuenta</button>
            </form>
        </div>
    </div>
</body>
</html>