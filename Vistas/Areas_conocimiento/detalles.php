<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

if (!isset($_SESSION['id_usuario'])) {
    header("Location: /ITSFCP-PROYECTOS/index.php");
    exit;
}

include "../../Controladores/areaconocimientoControlador.php";
$areaControlador = new AreaConocimientoControlador();

$rol = strtolower($_SESSION['rol'] ?? '');
$id_usuario = intval($_SESSION['id_usuario']);

require_once '../../../publico/incluido/_validar_get.php';


//Solo supervisor
if ($rol !== 'supervisor') {
    header("Location: /ITSFCP-PROYECTOS/Vistas/Principal/index.php");
    exit;
}

$id_area = isset($_GET['id_area']) ? intval($_GET['id_area']) : 0;

$id_validar = $id_area;
require_once '../../../publico/incluido/_validar_id.php';

$datos = $areaControlador->indexDetalles($rol, $id_area);

$registro = $datos;
require_once '../../../publico/incluido/_validar_datos.php';


$area = $datos['area'];
$subarea = $datos['subareas'];

ob_start();
?>

<div class="container-fluid py-4 ancho_container">

    <!-- ENCABEZADO -->

    <div class="row mb-4 align-items-center">
        <?php
        $titulo      = 'Detalle de Área de Conocimiento';
        $descripcion = 'Información del área seleccionada';
        include __DIR__ . '../../../publico/incluido/_encabezado.php';
        ?>
        <div class="col-md-6 text-md-end">
            <a href="index.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Regresar
            </a>
        </div>
    </div>

    <!-- INFORMACIÓN TEMATICA -->

    <div class="card shadow-sm mb-4">
        <div class="card-header">
            <h5 class="mb-0">Información del Área de conocimientos</h5>
        </div>
        <div class="card-body">
            <div class="row mb-3">
                <div class="col-md-3 fw-bold">
                    Estado
                </div>
                <div class="col-md-9">
                    <span class="badge rounded-pill text-bg-<?= $area['estado'] == "Activo" ? 'success' : 'danger' ?>">
                        <?= htmlspecialchars($area['estado']) ?>
                    </span>
                </div>
            </div>
            <div class="row mb-3">
                <div class="col-md-3 fw-bold">
                    Nombre
                </div>
                <div class="col-md-9">
                    <?= htmlspecialchars($area['nombre']) ?>
                </div>
            </div>
            <div class="row">
                <div class="col-md-3 fw-bold">
                    Descripción
                </div>
                <div class="col-md-9">
                    <?= nl2br(htmlspecialchars($area['descripcion'])) ?>
                </div>
            </div>
        </div>
    </div>
    <!-- SUBAREAS -->
    <div class="card shadow-sm">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Subareas</h5>
            <span class="badge bg-primary">
                <?= count($subarea) ?> / 10
            </span>
        </div>
        <div class="card-body p-0">
            <?php if (!empty($subarea)): ?>
                <ul class="list-group list-group-flush">
                    <?php foreach ($subarea as $suba): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <?= htmlspecialchars($suba['nombre']) ?>
                            <span class="badge rounded-pill text-bg-<?= $suba['estado'] == "1" ? 'success' : 'danger' ?>">
                                <?= $suba['estado'] == "1" ? 'Activo' : 'Desactivado' ?>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <div class="p-3 text-center text-muted">
                    No hay subareas registradas.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php

$contenido = ob_get_clean();
$titulo = "Detalles área de conocimiento";
$bodyClass = "proyectos-page";

include __DIR__ . '/../../layout.php';
?>