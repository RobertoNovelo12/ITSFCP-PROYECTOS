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
        <a href="login.php" class="toggle-btn">Ya tengo una cuenta</a>
    </div>

    <div class="container-register container-register-extended">
        <div class="content">
            <div class="title-log-reg">Crear cuenta</div>

            <form class="form" action="#" method="POST">
                <!-- nombre completo -->
                <div class="input-group">
                    <input type="text" id="nombre" class="input-field" placeholder=" " required>
                    <label for="nombre" class="floating-label">Nombre(s)</label>
                </div>

                <div class="name-group">
                    <div class="input-group">
                        <input type="text" id="apellido-paterno" class="input-field" placeholder=" " required>
                        <label for="apellido-paterno" class="floating-label">Apellido paterno</label>
                    </div>
                    <div class="input-group">
                        <input type="text" id="apellido-materno" class="input-field" placeholder=" " required>
                        <label for="apellido-materno" class="floating-label">Apellido materno</label>
                    </div>
                </div>

                <!-- CURP -->
                <div class="input-group">
                    <input type="text" id="curp" class="input-field" placeholder=" " maxlength="18" required>
                    <label for="curp" class="floating-label">CURP</label>
                </div>

                <!-- correo -->
                <div class="input-group">
                    <input type="email" id="email-register" class="input-field" placeholder=" " required>
                    <label for="email-register" class="floating-label">Correo electrónico</label>
                </div>
                
                <!-- fecha de nacimiento -->
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

                <!-- Ge´nero y Estado -->
                <div class="dual-select-group">
                    <div class="select-wrapper">
                        <label class="select-label">Género</label>
                        <select id="genero" class="role-select" required>
                            <option value="">Seleccionar</option>
                            <option value="masculino">Masculino</option>
                            <option value="femenino">Femenino</option>
                            <option value="otro">Otro</option>
                        </select>
                    </div>
                    <div class="select-wrapper">
                        <label class="select-label">Estado</label>
                        <select id="estado" class="role-select" required>
                            <option value="">Seleccionar</option>
                            <option value="aguascalientes">Aguascalientes</option>
                            <option value="baja-california">Baja California</option>
                            <option value="baja-california-sur">Baja California Sur</option>
                            <option value="campeche">Campeche</option>
                            <option value="chiapas">Chiapas</option>
                            <option value="chihuahua">Chihuahua</option>
                            <option value="coahuila">Coahuila</option>
                            <option value="colima">Colima</option>
                            <option value="cdmx">Ciudad de México</option>
                            <option value="durango">Durango</option>
                            <option value="guanajuato">Guanajuato</option>
                            <option value="guerrero">Guerrero</option>
                            <option value="hidalgo">Hidalgo</option>
                            <option value="jalisco">Jalisco</option>
                            <option value="mexico">Estado de México</option>
                            <option value="michoacan">Michoacán</option>
                            <option value="morelos">Morelos</option>
                            <option value="nayarit">Nayarit</option>
                            <option value="nuevo-leon">Nuevo León</option>
                            <option value="oaxaca">Oaxaca</option>
                            <option value="puebla">Puebla</option>
                            <option value="queretaro">Querétaro</option>
                            <option value="quintana-roo">Quintana Roo</option>
                            <option value="san-luis-potosi">San Luis Potosí</option>
                            <option value="sinaloa">Sinaloa</option>
                            <option value="sonora">Sonora</option>
                            <option value="tabasco">Tabasco</option>
                            <option value="tamaulipas">Tamaulipas</option>
                            <option value="tlaxcala">Tlaxcala</option>
                            <option value="veracruz">Veracruz</option>
                            <option value="yucatan">Yucatán</option>
                            <option value="zacatecas">Zacatecas</option>
                        </select>
                    </div>
                </div>

                <!-- Contraseñas -->
                <div class="input-group">
                    <input type="password" id="password" name="contraseña" class="input-field" placeholder=" " required>
                    <label for="password" class="floating-label">Contraseña</label>
                    <img src="./publico/icons/solar_eye-closed-broken.webp" alt="Mostrar contraseña" id="togglePassword" class="toggle-password">
                    </div>

                    <div class="input-group">
                        <input type="password" id="confirmar" name="confirmar" class="input-field" placeholder=" " required>
                        <label for="confirmar" class="floating-label">Confirmar contraseña</label>
                        <img src="./publico/icons/solar_eye-closed-broken.webp" alt="Mostrar contraseña" id="toggleConfirm" class="toggle-password">
                    </div>


                <div class="terms">
                    Al crear una cuenta, aceptas los <a href="#">Términos del servicio</a> y <a href="#">Política de privacidad</a>
                </div>
                <button type="submit" class="submit-btn">Crear cuenta</button>
            </form>
        </div>
    </div>
     <script src="./publico/js/javascript.js"></script>
</body>
</html>