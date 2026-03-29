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
$id_periodos = $_GET["id_periodos"] ?? null;


/* CONTROLADOR */
require_once '../../Controladores/periodoControlador.php';

$action = $_POST['action'] ?? null;
$periodoControlador = new periodoControlador();
$datos = $periodoControlador->indexEditar($rol, $id_periodos);


if ($action == 'eliminar') {
    $periodoControlador->eliminar($id_periodos, $rol);
}

ob_start();
include __DIR__ . '/../../mensaje.php';
include __DIR__ . '/../../error.php';
?>


<div class="container-fluid py-4">

    <!-- ENCABEZADO -->
    <div class="row mb-3">

        <div class="col-6">
            <h3>Editar Periodo</h3>
        </div>

        <div class="col-6 text-end">
            <a href="tabla.php" class="btn btn-danger">Regresar</a>
        </div>

    </div>

    <!-- DATOS PERIODO -->
    <h5>Información del periodo</h5>
    <div class="mb-3">
        <label class="form-label">Nombre</label>
        <p class="form-control-plaintext"><?= $datos['nombre']; ?></p>
    </div>

    <div class="mb-3">
        <label class="form-label">Fecha inicio</label>
        <p class="form-control-plaintext"><?= $datos['inicio']; ?></p>
    </div>

    <div class="mb-3">
        <label class="form-label">Fecha final</label>
        <p class="form-control-plaintext"><?= $datos['fin']; ?></p>
    </div>

    <div class="mb-3">
        <label class="form-label">Estado</label><br>
        <p class="badge rounded-pill text-bg-<?php echo $periodoControlador->EstiloEstadoLista($datos['estado']); ?>"><?= $datos['estado']; ?></p>
    </div>
    <form action="" method="POST">
        <input type="hidden" name="action" value="eliminar">

        <button type="submit" class="btn btn-guardar">
            Eliminar Periodo
        </button>
    </form>
</div>


<?php

$contenido = ob_get_clean();
$titulo = "Editar periodo";
$bodyClass = "proyectos-page";

include __DIR__ . '/../../layout.php';
?>