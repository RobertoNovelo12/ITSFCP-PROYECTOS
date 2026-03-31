<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

if (!isset($_SESSION['id_usuario'])) {
    header("Location: /ITSFCP-PROYECTOS/index.php");
    exit;
}

$rol = strtolower($_SESSION['rol'] ?? '');
$id_usuario = intval($_SESSION['id_usuario']);

require_once '../../Controladores/gradoacademicoControlador.php';

$action = $_POST['action'] ?? null;
$gradoacademicoControlador = new gradoacademicoControlador();
$estadoVista = ["activo" => 0, "desactivado" => 0];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST) && $action === 'Registrar') {
    $nombre = $_POST['Nombre'];
    $estadoVista = $gradoacademicoControlador->verificarGradoAcademico($nombre);

    if ($estadoVista['activo'] == 0 && $estadoVista['desactivado'] == 0) {
        $gradoacademicoControlador->registrarGradoAcademico($rol, $nombre);
    } else {
        $mensaje = "Ya hay un Grado Académico con ese nombre, intente con otro";
    }
}

ob_start();
include __DIR__ . '/../../mensaje.php';
include __DIR__ . '/../../error.php';
?>

<div class="container-fluid py-4">
    <!-- ENCABEZADO -->
    <div class="row mb-3">
        <div class="col-6">
            <h3>Crear Grado Académico</h3>
        </div>
        <div class="col-6 text-end">
            <a href="tabla.php" class="btn btn-danger">Regresar</a>
        </div>
    </div>
    <!-- DATOS GRADO ACADÉMICO -->
    <form method="POST" action="">
        <input type="hidden" name="action" value="registrar">
        <div class="mb-3">
            <label class="form-label">Grado Académico</label>
            <input
                type="text"
                name="Nombre"
                class="form-control"
                required>
        </div>
        <?php if (!empty($mensaje)) { ?>
            <div class="alert alert-warning" role="alert">
                <?= $mensaje ?>
            </div>
            <button type="submit" name="action" value="Registrar" class="btn btn-guardar">Guardar cambios</button>
        <?php } else { ?>
            <button type="submit" name="action" value="Registrar" class="btn btn-guardar">Guardar cambios</button>
        <?php } ?>
    </form>
</div>

<?php

$contenido = ob_get_clean();
$titulo = "Crear Grado Académico";
$bodyClass = "proyectos-page";

include __DIR__ . '/../../layout.php';
?>
