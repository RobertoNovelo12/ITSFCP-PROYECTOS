<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

if (!isset($_SESSION['id_usuario'])) {
    header("Location: /index.php");
    exit;
}


$rol = strtolower($_SESSION['rol'] ?? '');
$id_usuario = intval($_SESSION['id_usuario']);

include __DIR__ .  '../../../publico/incluido/_validar_get.php';

$id_linea = intval($_GET['id_linea']) ?? 0;

$id_validar = $id_linea;
include __DIR__ .  '../../../publico/incluido/_validar_id.php';

//Solo supervisor
if ($rol !== 'supervisor') {
    header("Location: /Vistas/Principal/index.php");
    exit;
}

require_once __DIR__ .  '/../../Controladores/lineaInvestigacionControlador.php';

$lineaControlador = new lineaInvestigacioncontrolador();
// Obtener datos
$linea = $lineaControlador->indexDetalles($rol, $id_linea);

// Validación
$registro = $linea;
include __DIR__ .  '../../../publico/incluido/_validar_datos.php';


ob_start();
?>

<div class="container-fluid py-4 ancho_container">

    <!-- ENCABEZADO -->
    <div class="row mb-4 align-items-center">

        <?php
        $titulo      = 'Detalle de Línea de Investigación';
        $descripcion = 'Información de la línea seleccionada';
        include __DIR__ . '../../../publico/incluido/_encabezado.php';
        ?>

        <div class="col-md-6 text-md-end">
            <a href="index.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Regresar
            </a>
        </div>

    </div>

    <!-- INFORMACIÓN DE LA LINEA DE INVESTIGACION -->
    <div class="card shadow-sm mb-4">

        <div class="card-header">
            <h5 class="mb-0"><i class="bi-info-circle me-2"></i>Información de la línea de investigación</h5>
        </div>

        <div class="card-body">
            <div class="row">
                <div class="col-md-12">
                    <dl>
                        <dt>Nombre</dt>
                        <dd>
                            <?= htmlspecialchars($linea['nombre']) ?>
                        </dd>
                    </dl>
                </div>
            </div>
            <div class="row">
                <div class="col-md-12">
                    <dl>
                        <dt>Descripción</dt>
                        <dd>
                            <?= htmlspecialchars($linea['descripcion']) ?>
                        </dd>
                    </dl>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6">
                    <dl>
                        <dt>Fecha creación</dt>
                        <?= date("d/m/Y", strtotime($linea['fecha_creacion'])), ' ' . date("H:i", strtotime($linea['fecha_creacion'])) ?>
                    </dl>
                </div>
                <div class="col-md-6">
                    <dl>
                        <dt>Fecha modificación</dt>
                        <?php
                        if (!empty($linea['fecha_modificacion']) && $linea['fecha_modificacion'] != "0000-00-00 00:00:00") {
                            echo date("d/m/Y H:i", strtotime($linea['fecha_modificacion']));
                        } else {
                            echo "No hay modificación";
                        }
                        ?>
                    </dl>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6">
                    <dl>
                        <dt>Estado</dt>
                        <dd>
                            <span class="badge rounded-pill text-bg-<?= $lineaControlador->EstiloEstadoLista($linea['estado']); ?>">
                                <?= htmlspecialchars($linea['estado']) ?>
                            </span>
                        </dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>
</div>

<?php

$contenido = ob_get_clean();
$titulo = "Detalles línea de investigación";
$bodyClass = "proyectos-page";

include __DIR__ . '/../../layout.php';
?>