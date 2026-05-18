<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

if (!isset($_SESSION['id_usuario'])) {
    header("Location: /ITSFCP-PROYECTOS/index.php");
    exit;
}

require_once '../../Controladores/tematicaControlador.php';

$tematicaControlador = new TematicaControlador();

$rol = strtolower($_SESSION['rol'] ?? '');
$id_usuario = intval($_SESSION['id_usuario']);

$id_tematica = isset($_GET['id_tematica']) ? intval($_GET['id_tematica']) : 0;

$datos = $tematicaControlador->indexDetalles($rol, $id_tematica);

$tematica = $datos['tematica'][0];
$subtematicas = $datos['subtematicas'];

ob_start();
?>

<div class="container-fluid py-4">

    <!-- ENCABEZADO -->

    <div class="row mb-4 align-items-center">

        <div class="col-md-6">
            <h3 class="fw-bold mb-0">Detalles de la temática</h3>
        </div>

        <div class="col-md-6 text-md-end">
            <a href="index.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Regresar
            </a>
        </div>

    </div>


    <!-- INFORMACIÓN TEMATICA -->

    <div class="card shadow-sm mb-4">

        <div class="card-header">
            <h5 class="mb-0">Información de la temática</h5>
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

            <h5 class="mb-0">Subtemáticas</h5>

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

                                <?=  $sub['estado'] == "1" ? 'Activo' : 'Desactivado' ?>

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

include __DIR__ . '/../../layout.php';
?>