<?php
/*Proyectos/historial_estudiante.php - Página secundaria de historial del estudiante */

ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();

if (!isset($_SESSION['id_usuario'])) {
    header("Location: /ITSFCP-PROYECTOS/index.php");
    exit;
}

$rol        = strtolower($_SESSION['rol'] ?? '');

$id_proyecto = $_GET['id_proyecto'] ?? null;
$id_usuario = $_GET['id_usuario'] ?? null;

if (!$id_proyecto || !$id_usuario) {
    die("ERROR: Datos incompletos");
}

require_once '../../Controladores/proyectoControlador.php';

$controlador = new ProyectoControlador();

$resultado = $controlador->historial_estudiante_proyecto($id_proyecto, $id_usuario);

$historialAgrupado = $resultado['datos'] ?? [];
$paginacion = $resultado['paginacion'] ?? [];

/**
 * Obtener última fecha real del historial
 */
$ultima_fecha = null;

if (!empty($historialAgrupado)) {
    foreach ($historialAgrupado as $items) {
        foreach ($items as $item) {
            if (!$ultima_fecha || strtotime($item['fecha']) > strtotime($ultima_fecha)) {
                $ultima_fecha = $item['fecha'];
            }
        }
    }
}

ob_start();
?>

<div class="container-fluid py-4" style="max-width:95%;">

    <!-- HEADER -->
    <div class="row mb-3">
        <div class="col-md-6">
            <h3>Historial del estudiante</h3>
        </div>
        <div class="col-6 col-md-6 text-md-end mb-2 mb-md-0 text-end">
            <a href="detalles.php?id_proyectos=<?= $id_proyecto ?>" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Regresar
            </a>
        </div>
    </div>

    <!-- RESUMEN -->
    <div class="card shadow-sm p-3 mb-4">
        <h5 class="mb-3">Resumen</h5>

        <div class="row">
            <div class="col-md-4">
                <strong>Proyecto ID:</strong><br>
                <?= $id_proyecto ?>
            </div>

            <div class="col-md-4">
                <strong>Estudiante ID:</strong><br>
                <?= $id_usuario ?>
            </div>

            <div class="col-md-4">
                <strong>Última actualización:</strong><br>
                <?= $ultima_fecha ? date("d/m/Y H:i", strtotime($ultima_fecha)) : 'Sin historial' ?>
            </div>
        </div>
    </div>

    <!-- HISTORIAL -->
    <?php if (empty($historialAgrupado)): ?>

        <div class="alert alert-info text-center shadow-sm">
            <i class="bi bi-info-circle"></i><br><br>
            Este estudiante no tiene historial registrado en este proyecto.
        </div>

    <?php else: ?>

        <ul class="timeline_historial_estudiante list-unstyled">

            <?php foreach ($historialAgrupado as $fecha => $items): ?>

                <li class="mb-4">

                    <!-- FECHA -->
                    <div class="fw-bold text-primary mb-2">
                        <?= $fecha ?>
                    </div>

                    <?php foreach ($items as $item): ?>

                        <div class="card shadow-sm mb-2">
                            <div class="card-body p-2">

                                <!-- BADGE -->
                                <span class="badge bg-<?= $item['tipo_evento'] == 'baja' ? 'danger' : 'success' ?>">
                                    <?= ucfirst($item['tipo_evento']) ?>
                                </span>

                                <!-- HORA -->
                                <small class="text-muted ms-2">
                                    <?= date("H:i", strtotime($item['fecha'])) ?>
                                </small>

                                <!-- DESCRIPCIÓN -->
                                <p class="mb-1 mt-2">
                                    <?= $item['descripcion'] ?? 'Sin descripción' ?>
                                </p>

                                <!-- USUARIO -->
                                <small class="text-secondary">
                                    <?= $item['usuario'] ?? 'Sistema' ?>
                                </small>

                            </div>
                        </div>

                    <?php endforeach; ?>

                </li>

            <?php endforeach; ?>

        </ul>

    <?php endif; ?>

    <!-- PAGINACIÓN -->
    <?php if (!empty($paginacion) && $paginacion['total_paginas'] > 1): ?>
        <nav>
            <ul class="pagination justify-content-center">

                <?php for ($i = 1; $i <= $paginacion['total_paginas']; $i++): ?>
                    <li class="page-item <?= ($i == $paginacion['pagina']) ? 'active' : '' ?>">
                        <a class="page-link"
                            href="?id_proyecto=<?= $id_proyecto ?>&id_usuario=<?= $id_usuario ?>&pagina=<?= $i ?>">
                            <?= $i ?>
                        </a>
                    </li>
                <?php endfor; ?>

            </ul>
        </nav>
    <?php endif; ?>

</div>

<?php
$contenido = ob_get_clean();
$titulo = "Historial del estudiante";
include __DIR__ . '/../../layout.php';
?>