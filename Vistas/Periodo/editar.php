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
    <div class="row mb-4 align-items-center">

        <div class="col-md-6">
            <h3 class="fw-bold mb-0">Editar periodo</h3>
        </div>

        <div class="col-md-6 text-md-end">
            <a href="index.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Regresar
            </a>
        </div>

    </div>

    <!-- DATOS PERIODO -->
    <div class="card mb-4 shadow-sm">
        <div class="card-header">
            <h5 class="mb-0">Información del periodo</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <dl>
                        <dt>Nombre</dt>
                        <dd>
                            <?= $datos['nombre']; ?>
                        </dd>
                    </dl>
                </div>
                <div class="col-md-6">
                    <dl>
                        <dt>Estado</dt>
                        <dd>
                            <p class="badge rounded-pill text-bg-<?php echo $periodoControlador->EstiloEstadoLista($datos['estado']); ?>"><?= $datos['estado']; ?></p>
                        </dd>
                    </dl>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6">
                    <dl>
                        <dt>Fecha inicial</dt>
                        <dd><?= $datos['inicio']; ?></dd>
                    </dl>
                </div>
                <div class="col-md-6">
                    <dl>
                        <dt>Fecha final</dt>
                        <dd><?= $datos['fin']; ?></dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>
    <form action="" method="POST">
        <input type="hidden" name="action" value="eliminar">

        <button type="submit" class="btn btn-guardar">
            Desactivar Periodo
        </button>
    </form>
</div>


<?php

$contenido = ob_get_clean();
$titulo = "Editar periodo";
$bodyClass = "proyectos-page";

include __DIR__ . '/../../layout.php';
?>