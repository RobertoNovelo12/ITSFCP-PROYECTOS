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

/* CONTROLADOR */
require_once '../../Controladores/periodoControlador.php';

$action = $_POST['action'] ?? null;
$periodoControlador = new periodoControlador();
$estadoVista = $periodoControlador->obtenerEstadoVista();
$datos = $estadoVista['datos'];


if ($action === 'registrarPeriodo') {
    $periodoControlador->registrarPeriodo($rol);
}

if ($action === 'reactivarPeriodo') {
    $periodoControlador->reactivar($datos['nombre']);
}

ob_start();
include __DIR__ . '/../../mensaje.php';
include __DIR__ . '/../../error.php';
?>


<div class="container-fluid py-4">

    <!-- ENCABEZADO -->
    <div class="row mb-3">

        <div class="col-6">
            <h3>Crear Periodo</h3>
        </div>

        <div class="col-6 text-end">
            <a href="index.php" class="btn btn-danger">Regresar</a>
        </div>

    </div>

    <!-- DATOS PERIODO -->
    <div class="card mb-4 shadow-sm">
        <div class="card-header bg-light">
            <h5 class="mb-0">Información del periodo</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <dl>
                        <dt>Nombre</dt>
                        <dd>
                            <?= htmlspecialchars($datos['nombre']); ?>
                        </dd>
                    </dl>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6">
                    <dl>
                        <dt>Fecha inicial</dt>
                        <dd><?= htmlspecialchars($datos['inicio']); ?></dd>
                    </dl>
                </div>
                <div class="col-md-6">
                    <dl>
                        <dt>Fecha final</dt>
                        <dd><?= htmlspecialchars($datos['fin']); ?></dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>
    <?php if ($estadoVista['accion'] === 'bloqueado') { ?>

        <div class="alert alert-warning" role="alert">
            <?= $estadoVista['mensaje']; ?>
        </div>

    <?php } else { ?>

        <form method="POST">

            <?php if ($estadoVista['accion'] === 'reactivar') { ?>
                <input type="hidden" name="action" value="reactivarPeriodo">
                <button type="submit" class="btn btn-guardar">
                    Reactivar Periodo
                </button>
            <?php } else { ?>
                <input type="hidden" name="action" value="registrarPeriodo">
                <button type="submit" class="btn btn-guardar">
                    Crear Periodo
                </button>
            <?php } ?>

        </form>

    <?php } ?>
</div>


<?php

$contenido = ob_get_clean();
$titulo = "Crear periodo";
$bodyClass = "proyectos-page";

include __DIR__ . '/../../layout.php';
?>