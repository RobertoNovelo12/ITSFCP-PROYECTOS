<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

/* VALIDACIÓN DE SESIÓN */
if (!isset($_SESSION['id_usuario'])) {
    header("Location: /ITSFCP-PROYECTOS/index.php");
    exit;
}

$rol = strtolower($_SESSION['rol'] ?? '');
$id_usuario = intval($_SESSION['id_usuario']);
$id_carrera = $_GET["id_carrera"] ?? null;

//Solo supervisor
if ($rol ?? '' !== 'supervisor') {
    header("Location: /ITSFCP-PROYECTOS/Vistas/Principal/index.php");
    exit;
}

/* CONTROLADOR */
require_once '../../Controladores/carreraControlador.php';

$action = $_POST['action'] ?? null;
$carreraControlador = new carreraControlador();
$datos = $carreraControlador->indexEditar($rol, $id_carrera);
$mensaje  = "";
$estadoVista = ["activo" => 0, "desactivado" => 0];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST) && $action == 'Guardar') {
    $nombre_carrera = $_POST['NombreCarrera'];
    $estadoVista = $carreraControlador->obtenerPorIdDiferente($id_carrera, $nombre_carrera);

    if ($estadoVista['activo'] == 0 && $estadoVista['desactivado'] == 0) {
        $carreraControlador->editarCarrera($rol, $id_carrera, $nombre_carrera);
    } else {
        $mensaje = "Ya hay una carrera con ese nombre, busca otro";
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST) && $action == 'Reactivar') {
    $carreraControlador->reactivar($rol, $_POST['id_carrera']);
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST) && $action == 'Desactivar') {
    $carreraControlador->eliminar($rol, $_POST['id_carrera']);
}

ob_start();
include __DIR__ . '/../../mensaje.php';
include __DIR__ . '/../../error.php';
?>


<div class="container-fluid py-4">

    <!-- ENCABEZADO -->
    <div class="row mb-4 align-items-center">

        <div class="col-md-6">
            <h3 class="fw-bold mb-0">Editar Carrera</h3>
        </div>

        <div class="col-md-6 text-md-end">
            <a href="index.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Regresar
            </a>
        </div>

    </div>

    <!-- DATOS CARRERA -->
    <form method="POST" action="">
        <input type="hidden" name="id_carrera" value="<?= $datos['id_carrera']; ?>">
        <div class="mb-3">
            <label class="form-label">Nombre de la Carrera</label>
            <input
                type="text"
                name="NombreCarrera"
                class="form-control"
                value="<?= htmlspecialchars($datos['nombre_carrera']); ?>"
                required>
        </div>
        <div class="mb-3">
            <?php if (!empty($mensaje)) { ?>
                <div class="alert alert-warning" role="alert">
                    <?= $mensaje ?>
                </div>
            <?php } else { ?>
                <?php echo $carreraControlador->botonesAccionEditar($rol, $datos['estado']); ?>
            <?php } ?>
        </div>
    </form>
</div>


<?php

$contenido = ob_get_clean();
$titulo = "Editar Carrera";
$bodyClass = "proyectos-page";

include __DIR__ . '/../../layout.php';
?>
