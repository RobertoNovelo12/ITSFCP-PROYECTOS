<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

if (!isset($_SESSION['id_usuario'])) {
    header("Location: /ITSFCP-PROYECTOS/index.php");
    exit;
}

$rol = strtolower($_SESSION['rol'] ?? '');
$id_usuario = intval($_SESSION['id_usuario']);

// Validación - Existencia de GET
include __DIR__ .  '../../../publico/incluido/_validar_get.php';

//Solo supervisor
if ($rol !== 'supervisor') {
    header("Location: /ITSFCP-PROYECTOS/Vistas/Principal/index.php");
    exit;
}

$id_grado = isset($_GET['id_grado']) ? intval($_GET['id_grado']) : 0;

$id_validar = $id_grado;
include __DIR__ .  '../../../publico/incluido/_validar_id.php';

require_once __DIR__ .  '/../../Controladores/gradoacademicoControlador.php';

$gradoacademicoControlador = new gradoacademicoControlador();
// Validación - Existencia de datos
$registro = $gradoacademicoControlador->indexDetalles($rol, $id_grado);

include __DIR__ .  '../../../publico/incluido/_validar_datos.php';

ob_start();
?>

<div class="container-fluid py-4 ancho_container">

    <!-- ENCABEZADO -->
    <div class="row mb-4 align-items-center">
        <?php
        $titulo      = 'Detalle de Grado Académico';
        $descripcion = 'Información del grado seleccionado';
        include __DIR__ . '../../../publico/incluido/_encabezado.php';
        ?>
        <div class="col-md-6 text-md-end">
            <a href="index.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Regresar
            </a>
        </div>
    </div>

    <!-- INFORMACIÓN DE GRADO ACADÉMICO -->
    <div class="card shadow-sm mb-4">
        <div class="card-header">
            <h5 class="mb-0"><i class="bi-info-circle me-2"></i>Información de Grado Académico</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-12">
                    <dl>
                        <dt>Grado Académico</dt>
                        <dd>
                            <?= htmlspecialchars($registro['nombre']) ?>
                        </dd>
                    </dl>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6">
                    <dl>
                        <dt>Fecha creación</dt>
                        <?= date("d/m/Y", strtotime($registro['fecha_creacion'])), ' ' . date("H:i", strtotime($registro['fecha_creacion'])) ?>
                    </dl>
                </div>
                <div class="col-md-6">
                    <dl>
                        <dt>Fecha modificación</dt>
                        <?php
                        if (!empty($registro['fecha_modificacion']) && $registro['fecha_modificacion'] != "0000-00-00 00:00:00") {
                            echo date("d/m/Y H:i", strtotime($registro['fecha_modificacion']));
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
                            <span class="badge rounded-pill text-bg-<?= $gradoacademicoControlador->EstiloEstadoLista($registro['estado']); ?>">
                                <?= htmlspecialchars($registro['estado']) ?>
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
$titulo = "Detalles Grado Académico";
$bodyClass = "proyectos-page";

include __DIR__ . '/../../layout.php';
?>