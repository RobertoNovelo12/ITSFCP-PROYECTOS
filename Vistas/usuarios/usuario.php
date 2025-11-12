<?php
session_start();

// Si no hay sesión, redirigir
if (!isset($_SESSION['id_usuario'])) {
    header("Location: ../../login.php");
    exit;
}

$rol = $_GET['rol'] ?? $_SESSION['rol'] ?? '';
$rol = strtolower(trim($rol));
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Solicitud de Alta</title>
    <link rel="stylesheet" href="../../publico/css/styles.css">
</head>

<body class="body-register">
    <div class="container-perfil">
        <h1 class="title-perfil">Solicitud de alta - <?php echo ucfirst($rol); ?></h1>

        <?php if ($rol === 'alumno'): ?>
            <form class="form-perfil" id="formSolicitud" action="../../publico/config/procesar_solicitud.php" method="POST">
                <input type="hidden" name="rol" value="alumno">
                <div class="input-group">
                    <input type="text" name="matricula" class="input-field" placeholder=" " required>
                    <label class="floating-label">Matrícula</label>
                </div>
                <div class="input-group">
                    <input type="text" name="carrera" class="input-field" placeholder=" " required>
                    <label class="floating-label">Carrera</label>
                </div>
                <div class="input-group">
                    <input type="text" name="area" class="input-field" placeholder=" " required>
                    <label class="floating-label">Área de conocimiento</label>
                </div>
                <div class="input-group">
                    <input type="text" name="subarea" class="input-field" placeholder=" " required>
                    <label class="floating-label">Subárea de conocimiento</label>
                </div>
                <button type="submit" class="submit-btn">Enviar solicitud</button>
            </form>

        <?php elseif ($rol === 'profesor'): ?>
            <form class="form-perfil" id="formSolicitud" action="../../publico/config/procesar_solicitud.php" method="POST">
                <input type="hidden" name="rol" value="profesor">
                <div class="input-group">
                    <input type="text" name="area" class="input-field" placeholder=" " required>
                    <label class="floating-label">Área de conocimiento</label>
                </div>
                <div class="input-group">
                    <input type="text" name="subarea" class="input-field" placeholder=" " required>
                    <label class="floating-label">Subárea de conocimiento</label>
                </div>
                <div class="input-group">
                    <input type="text" name="nivel_sni" class="input-field" placeholder=" " required>
                    <label class="floating-label">Nivel de SNI</label>
                </div>
                <div class="input-group">
                    <input type="text" name="grado" class="input-field" placeholder=" " required>
                    <label class="floating-label">Grado máximo de estudio</label>
                </div>
                <div class="input-group">
                    <input type="text" name="linea" class="input-field" placeholder=" " required>
                    <label class="floating-label">Línea de investigación</label>
                </div>
                <div class="input-group">
                    <input type="text" name="rfc" class="input-field" placeholder=" " required>
                    <label class="floating-label">RFC</label>
                </div>
                <button type="submit" class="submit-btn">Enviar solicitud</button>
            </form>

        <?php elseif ($rol === 'supervisor'): ?>
            <form class="form-perfil" id="formSolicitud" action="../../publico/config/procesar_solicitud.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="rol" value="supervisor">
                <div class="input-group">
                    <label class="floating-label">Descargar formato de solicitud</label><br>
                    <a href="../../publico/docs/formato_solicitud.pdf" class="submit-btn" download>Descargar Formato</a>
                </div>

                <div class="input-group">
                    <input type="text" name="departamento" class="input-field" placeholder=" " required>
                    <label class="floating-label">Nombre del departamento</label>
                </div>
                <div class="input-group">
                    <input type="text" name="cargo" class="input-field" placeholder=" " required>
                    <label class="floating-label">Cargo</label>
                </div>
                <div class="input-group">
                    <input type="text" name="rfc" class="input-field" placeholder=" " required>
                    <label class="floating-label">RFC</label>
                </div>
                <div class="input-group">
                    <label class="floating-label">Subir solicitud en PDF</label>
                    <input type="file" name="solicitud_pdf" accept="application/pdf" required>
                </div>
                <button type="submit" class="submit-btn">Enviar solicitud</button>
            </form>

        <?php else: ?>
            <p>No se reconoce el tipo de usuario. Por favor, vuelva a <a href="crear_perfil.php">crear su perfil</a>.</p>
        <?php endif; ?>
    </div>

    <!-- Modal -->
    <div id="modal-solicitud" class="modal-overlay" style="display:none;">
        <div class="modal-content">
            <h2>Solicitud enviada</h2>
            <p>Se ha enviado su solicitud. Si sus datos son correctos, se le dará acceso al sistema lo más pronto posible.</p>
            <button id="confirmar-btn" class="submit-btn">Confirmar</button>
        </div>
    </div>
    <script src="../../publico/js/javascript.js"></script>
</body>
</html>