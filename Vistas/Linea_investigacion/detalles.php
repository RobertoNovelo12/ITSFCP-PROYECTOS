<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

if (!isset($_SESSION['id_usuario'])) {
    header("Location: /ITSFCP-PROYECTOS/index.php");
    exit;
}

require_once '../../Controladores/lineainvestigacionControlador.php';

$lineaControlador = new lineaControlador();

$rol = strtolower($_SESSION['rol'] ?? '');
$id_usuario = intval($_SESSION['id_usuario']);

$id_linea = isset($_GET['id_linea']) ? intval($_GET['id_linea']) : 0;

// Obtener datos
$linea = $lineaControlador->indexDetalles($rol, $id_linea);

// Validación
if (empty($linea)) {
    die("No se encontró la línea de investigación.");
}

ob_start();
?>

<div class="container-fluid py-4">

    <!-- ENCABEZADO -->
    <div class="row mb-4 align-items-center">

        <div class="col-md-6">
            <h3 class="fw-bold mb-0">Detalles de la línea de investigación</h3>
        </div>

        <div class="col-md-6 text-md-end">
            <a href="index.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Regresar
            </a>
        </div>

    </div>

    <!-- INFORMACIÓN DE LA LINEA DE INVESTIGACION -->
    <div class="card shadow-sm mb-4">

        <div class="card-header bg-light">
            <h5 class="mb-0">Información de la línea de investigación</h5>
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