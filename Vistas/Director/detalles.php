<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

if (!isset($_SESSION['id_usuario'])) {
    header("Location: /ITSFCP-PROYECTOS/index.php");
    exit;
}

require_once '../../Controladores/directorControlador.php';

$directorControlador = new directorControlador();

$rol = strtolower($_SESSION['rol'] ?? '');
$id_usuario = intval($_SESSION['id_usuario']);

$id_director = isset($_GET['id_director']) ? intval($_GET['id_director']) : 0;

$director = $directorControlador->indexDetalles($rol, $id_director);

if (empty($director)) {
    die("No se encontró el director.");
}

ob_start();
?>

<div class="container-fluid py-4">

    <!-- ENCABEZADO -->
    <div class="row mb-4 align-items-center">
        <div class="col-md-6">
            <h3 class="fw-bold mb-0">Detalles del Director</h3>
        </div>
        <div class="col-md-6 text-md-end">
            <a href="tabla.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Regresar
            </a>
        </div>
    </div>

    <!-- INFORMACIÓN DEL DIRECTOR -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-light">
            <h5 class="mb-0">Información del Director</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <dl>
                        <dt>Nombre</dt>
                        <dd>
                            <?= htmlspecialchars($director['nombre']) ?>
                        </dd>
                    </dl>
                </div>
                <div class="col-md-6">
                    <dl>
                        <dt>Apellido</dt>
                        <dd>
                            <?= htmlspecialchars($director['apellido']) ?>
                        </dd>
                    </dl>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6">
                    <dl>
                        <dt>Correo</dt>
                        <dd>
                            <?= htmlspecialchars($director['correo'] ?? 'No registrado') ?>
                        </dd>
                    </dl>
                </div>
                <div class="col-md-6">
                    <dl>
                        <dt>Teléfono</dt>
                        <dd>
                            <?= htmlspecialchars($director['telefono'] ?? 'No registrado') ?>
                        </dd>
                    </dl>
                </div>
            </div>
            <div class="row">
                <div class="col-md-12">
                    <dl>
                        <dt>Grado Académico</dt>
                        <dd>
                            <?= htmlspecialchars($director['nombre_grado']) ?>
                        </dd>
                    </dl>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6">
                    <dl>
                        <dt>Fecha inicio</dt>
                        <?= date("d/m/Y", strtotime($director['inicio'])) ?>
                    </dl>
                </div>
                <div class="col-md-6">
                    <dl>
                        <dt>Fecha final</dt>
                        <?php
                        if (!empty($director['fin']) && $director['fin']) {
                            echo date("d/m/Y", strtotime($director['fin']));
                        } else {
                            echo "No hay final definido";
                        }
                        ?>
                    </dl>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6">
                    <dl>
                        <dt>Fecha creación</dt>
                        <?= date("d/m/Y", strtotime($director['fecha_creacion'])), ' ' . date("H:i", strtotime($director['fecha_creacion'])) ?>
                    </dl>
                </div>
                <div class="col-md-6">
                    <dl>
                        <dt>Fecha modificación</dt>
                        <?php
                        if (!empty($director['fecha_modificacion']) && $director['fecha_modificacion'] != "0000-00-00 00:00:00") {
                            echo date("d/m/Y H:i", strtotime($director['fecha_modificacion']));
                        } else {
                            echo "No hay modificación";
                        }
                        ?>
                    </dl>
                </div>
            </div>
            <div class="row">
                <div class="col-md-12">
                    <dl>
                        <dt>Motivo salida</dt>
                        <dd>
                            <?= htmlspecialchars($director['motivo_fin']) ? htmlspecialchars($director['motivo_fin']) : "No hay"  ?>
                        </dd>
                    </dl>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6">
                    <dl>
                        <dt>Estado</dt>
                        <dd>
                            <span class="badge rounded-pill text-bg-<?= $directorControlador->EstiloEstadoLista($director['estado']); ?>">
                                <?= htmlspecialchars($director['estado']) ?>
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
$titulo = "Detalles Director";
$bodyClass = "proyectos-page";

include __DIR__ . '/../../layout.php';
?>