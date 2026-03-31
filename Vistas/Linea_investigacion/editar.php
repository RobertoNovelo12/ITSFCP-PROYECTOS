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
$id_linea = $_GET["id_linea"] ?? null;


/* CONTROLADOR */
require_once '../../Controladores/lineainvestigacionControlador.php';

$action = $_POST['action'] ?? null;
$lineaControlador = new lineaControlador();
$datos = $lineaControlador->indexEditar($rol, $id_linea);
$mensaje  = "";
$estadoVista = ["activo" => 0, "desactivado" => 0];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST) && $action == 'Guardar') {
    $nombre = $_POST['Nombre'];
    $descripcion = $_POST['Descripcion'];
    $estadoVista = $lineaControlador->obtenerPorIdDiferente($id_linea, $nombre);

    if ($estadoVista['activo'] == 0 && $estadoVista['desactivado'] == 0) {
        $lineaControlador->editarLinea($rol, $id_linea, $nombre, $descripcion);
    } else {
        $mensaje = "Ya hay una línea de investigación con ese nombre, busca otro";
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST) && $action == 'Reactivar') {
    $lineaControlador->reactivar($rol, $_POST['id_linea']);
} elseif ( $_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST) && $action == 'Desactivar') {
    $lineaControlador->eliminar($rol, $_POST['id_linea']);
}

ob_start();
include __DIR__ . '/../../mensaje.php';
include __DIR__ . '/../../error.php';
?>


<div class="container-fluid py-4">

    <!-- ENCABEZADO -->
    <div class="row mb-4 align-items-center">

        <div class="col-md-6">
            <h3 class="fw-bold mb-0">Editar Línea de investigación</h3>
        </div>

        <div class="col-md-6 text-md-end">
            <a href="tabla.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Regresar
            </a>
        </div>

    </div>

    <!-- DATOS LINEA DE INVESTIGACIÓN -->
    <form method="POST" action="">
        <input type="hidden" name="id_linea" value="<?= $datos['id_linea']; ?>">
        <div class="mb-3">
            <label class="form-label">Nombre</label>
            <input
                type="text"
                name="Nombre"
                class="form-control"
                value="<?= $datos['nombre']; ?>"
                required>
        </div>
        <div class="mb-3">
            <label class="form-label">Descripción</label>
            <textarea
                name="Descripcion"
                class="form-control"
                required><?= $datos['descripcion']; ?></textarea>
        </div>
        <div class="mb-3">
            <?php if (!empty($mensaje)) { ?>
                <div class="alert alert-warning" role="alert">
                    <?= $mensaje ?>
                </div>
            <?php } else { ?>
                <?php echo $lineaControlador->botonesAccionEditar($rol, $datos['estado']); ?>
            <?php } ?>
        </div>
    </form>
</div>


<?php

$contenido = ob_get_clean();
$titulo = "Editar línea de investigación";
$bodyClass = "proyectos-page";

include __DIR__ . '/../../layout.php';
?>