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
//Solo supervisor
if ($rol ?? '' !== 'supervisor') {
    header("Location: /ITSFCP-PROYECTOS/Vistas/Principal/index.php");
    exit;
}


/* CONTROLADOR */
require_once '../../Controladores/lineainvestigacionControlador.php';

$action = $_POST['action'] ?? null;
$lineaControlador = new lineaControlador();
$estadoVista = ["activo" => 0, "desactivado" => 0];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST) && $action === 'Registrar') {
    $nombre = $_POST['Nombre'];
    $descripcion = $_POST['Descripcion'];
    $estadoVista = $lineaControlador->verificarLinea($nombre);

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST) && $estadoVista['activo'] == 0 && $estadoVista['desactivado'] == 0) {
        $lineaControlador->registrarLinea($rol, $nombre, $descripcion);
    } else {
        $mensaje = "Ya hay una línea de investigación con ese nombre, intente con otro";
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
            <h3>Crear Línea de investigación</h3>
        </div>
        <div class="col-6 text-end">
            <a href="index.php" class="btn btn-danger">Regresar</a>
        </div>
    </div>
    <!-- DATOS LINEA DE INVESTIGACION -->
    <form method="POST" action="">
        <input type="hidden" name="action" value="registrar">
        <div class="mb-3">
            <label class="form-label">Nombre</label>
            <input
                type="text"
                name="Nombre"
                class="form-control"
                required>
        </div>
        <div class="mb-3">
            <label class="form-label">Descripción</label>
            <textarea
                name="Descripcion"
                class="form-control"
                required></textarea>
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
$titulo = "Crear Línea de investigación";
$bodyClass = "proyectos-page";

include __DIR__ . '/../../layout.php';
?>