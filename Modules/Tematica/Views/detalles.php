<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

if (!isset($_SESSION['id_usuario'])) {
    header("Location: /index.php");
    exit;
}

require_once __DIR__ . '/../Controller/tematica_controller.php';

$tematicaControlador = new TematicaControlador();

$rol = strtolower($_SESSION['rol'] ?? '');
$id_usuario = intval($_SESSION['id_usuario']);

include __DIR__ .  '/../../../public/incluido/_validar_get.php';

if ($rol !== 'supervisor') {
    header("Location: /Modules/Principal/Views/index.php");
    exit;
}

$id_tematica = isset($_GET['id_tematica']) ? intval($_GET['id_tematica']) : 0;

//Validación de argumentos en url
$id_validar = $id_tematica;
include __DIR__ .  '/../../../public/incluido/_validar_id.php';

$datos = $tematicaControlador->indexDetalles($rol, $id_tematica);

$tematica = $datos['tematica'][0];
$subtematicas = $datos['subtematicas'];

ob_start();
?>

<div class="container-fluid py-4 ancho_container">

    <!-- ENCABEZADO -->

    <div class="row mb-4 align-items-center">

        <?php
        $titulo      = 'Detalle de Temática';
        $descripcion = 'Información de la temática seleccionada';
        include __DIR__ . '/../../../public/incluido/_encabezado.php';
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
            <h5 class="mb-0"><i class="bi bi-info-circle me-2"></i> Información de la temática</h5>
        </div>

        <div class="card-body">

            <div class="row mb-3">

                <div class="col-md-3 fw-bold">
                    Estado
                </div>

                <div class="col-md-9">

                    <span class="badge rounded-pill text-bg-<?= $tematica['estado'] == "Activo" ? 'success' : 'danger' ?>">

                        <?= htmlspecialchars($tematica['estado']) ?>

                    </span>

                </div>

            </div>


            <div class="row mb-3">

                <div class="col-md-3 fw-bold">
                    Nombre
                </div>

                <div class="col-md-9">

                    <?= htmlspecialchars($tematica['nombre']) ?>

                </div>

            </div>


            <div class="row">

                <div class="col-md-3 fw-bold">
                    Descripción
                </div>

                <div class="col-md-9">

                    <?= nl2br(htmlspecialchars($tematica['descripcion'])) ?>

                </div>

            </div>

        </div>

    </div>


    <!-- SUBTEMATICAS -->

    <div class="card shadow-sm">

        <div class="card-header d-flex justify-content-between align-items-center">

            <h5 class="mb-0"><i class="bi bi-file-text me-2"></i> Subtemáticas</h5>

            <span class="badge bg-primary">

                <?= count($subtematicas) ?> / 10

            </span>

        </div>

        <div class="card-body p-0">

            <?php if (!empty($subtematicas)): ?>

                <ul class="list-group list-group-flush">

                    <?php foreach ($subtematicas as $sub): ?>

                        <li class="list-group-item d-flex justify-content-between align-items-center">

                            <?= htmlspecialchars($sub['nombre']) ?>

                            <span class="badge rounded-pill text-bg-<?= $sub['estado'] == "1" ? 'success' : 'danger' ?>">

                                <?= $sub['estado'] == "1" ? 'Activo' : 'Desactivado' ?>

                            </span>

                        </li>

                    <?php endforeach; ?>

                </ul>

            <?php else: ?>

                <div class="p-3 text-center text-muted">

                    No hay subtemáticas registradas.

                </div>

            <?php endif; ?>

        </div>

    </div>

</div>

<?php

$contenido = ob_get_clean();
$titulo = "Detalles temática";
$bodyClass = "proyectos-page";

include __DIR__ . '/../../../layout.php';
?>