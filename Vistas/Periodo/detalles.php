<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

if (!isset($_SESSION['id_usuario'])) {
    header("Location: /ITSFCP-PROYECTOS/index.php");
    exit;
}

require_once '../../Controladores/periodoControlador.php';

$periodoControlador = new periodoControlador();

$rol = strtolower($_SESSION['rol'] ?? '');
$id_usuario = intval($_SESSION['id_usuario']);

$id_periodo = isset($_GET['id_periodos']) ? intval($_GET['id_periodos']) : 0;

// Obtener datos
$periodo = $periodoControlador->indexDetalles($rol, $id_periodo);

// Validación
if (empty($periodo)) {
    die("No se encontró el periodo.");
}

ob_start();
?>

<div class="container-fluid py-4">

    <!-- ENCABEZADO -->
    <div class="row mb-4 align-items-center">

        <div class="col-md-6">
            <h3 class="fw-bold mb-0">Detalles del periodo</h3>
        </div>

        <div class="col-md-6 text-md-end">
            <a href="tabla.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Regresar
            </a>
        </div>

    </div>

    <!-- INFORMACIÓN DEL PERIODO -->
    <div class="card shadow-sm mb-4">

        <div class="card-header bg-light">
            <h5 class="mb-0">Información del periodo</h5>
        </div>

        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <dl>
                        <dt>Nombre</dt>
                        <dd>
                            <?= htmlspecialchars($periodo['periodo']) ?>
                        </dd>
                    </dl>
                </div>
                <div class="col-md-6">
                    <dl>
                        <dt>Estado</dt>
                        <dd>
                            <span class="badge rounded-pill text-bg-<?=
                                                                    $periodoControlador->EstiloEstadoLista($periodo['estado']);
                                                                    ?>">
                                <?= htmlspecialchars($periodo['estado']) ?>
                            </span>
                        </dd>
                    </dl>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6">
                    <dl>
                        <dt>Fecha inicial</dt>
                        <?= date("d/m/Y", strtotime($periodo['fecha_inicio'])) ?>
                    </dl>
                </div>
                <div class="col-md-6">
                    <dl>
                        <dt>Fecha final</dt>
                        <?= date("d/m/Y", strtotime($periodo['fecha_final'])) ?>
                    </dl>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6">
                    <dl>
                        <dt>Fecha creación</dt>
                        <?= date("d/m/Y", strtotime($periodo['fecha_creacion'])), ' ' . date("H:i", strtotime($periodo['fecha_creacion'])) ?>
                    </dl>
                </div>
                <div class="col-md-6">
                    <dl>
                        <dt>Fecha modificación</dt>
                        <?php
                        if (!empty($periodo['fecha_modificacion']) && $periodo['fecha_modificacion'] != "0000-00-00 00:00:00") {
                            echo date("d/m/Y H:i", strtotime($periodo['fecha_modificacion']));
                        } else {
                            echo "No hay modificación";
                        }
                        ?>
                    </dl>
                </div>
            </div>
        </div>

    </div>

</div>

<?php

$contenido = ob_get_clean();
$titulo = "Detalles periodo";
$bodyClass = "proyectos-page";

include __DIR__ . '/../../layout.php';
?>