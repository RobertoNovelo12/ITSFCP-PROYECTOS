<?php
/**
 * Plantillas_documentos/historial.php
 * Línea de tiempo de eventos de un tipo de documento — solo supervisor.
 */

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

if (!isset($_SESSION['id_usuario'])) {
    header("Location: /ITSFCP-PROYECTOS/index.php");
    exit;
}

$rol               = strtolower($_SESSION['rol'] ?? '');
$id_tipo_documento = (int) ($_GET['id_tipo_documento'] ?? 0);

if ($rol !== 'supervisor') {
    header("Location: /ITSFCP-PROYECTOS/Vistas/Principal/index.php");
    exit;
}

if ($id_tipo_documento <= 0) {
    header("Location: index.php?error=id_invalido");
    exit;
}

require_once '../../Controladores/plantilladocumentoControlador.php';
$ctrl = new plantilladocumentoControlador();

$resultado         = $ctrl->info_linea_tiempo($id_tipo_documento);
$historialAgrupado = $resultado['datos'];
$paginacion        = $resultado['paginacion'];

// Obtener última fecha real del historial (igual que historial_estudiante)
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

    <?php include __DIR__ . '/../../mensaje.php'; ?>

    <!-- CABECERA -->
    <div class="row mb-3">
        <div class="col-md-6">
            <h3 class="fw-bold">Historial de plantilla de documento</h3>
        </div>
        <div class="col-6 col-md-6 text-md-end mb-2 mb-md-0 text-end">
            <a href="index.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Regresar
            </a>
        </div>
    </div>

    <!-- RESUMEN (mismo diseño que historial_estudiante) -->
    <div class="card shadow-sm p-3 mb-4">
        <h5 class="mb-3"><b>Resumen</b></h5>
        <div class="row">
            <div class="col-md-4">
                <strong>Tipo de documento ID:</strong><br>
                <?= $id_tipo_documento ?>
            </div>
            <div class="col-md-4">
                <strong>Total de eventos:</strong><br>
                <?= $paginacion['total'] ?? 0 ?>
            </div>
            <div class="col-md-4">
                <strong>Última actualización:</strong><br>
                <?= $ultima_fecha
                    ? date('d/m/Y H:i', strtotime($ultima_fecha))
                    : 'Sin historial' ?>
            </div>
        </div>
    </div>

    <!-- HISTORIAL -->
    <?php if (empty($historialAgrupado)): ?>

        <div class="alert alert-info text-center shadow-sm">
            <i class="bi bi-info-circle"></i><br><br>
            No hay historial registrado para este tipo de documento.
        </div>

    <?php else: ?>

        <ul class="timeline_historial list-unstyled">

            <?php foreach ($historialAgrupado as $version => $items): ?>

                <li class="mb-4">

                    <!-- VERSIÓN como título de grupo -->
                    <div class="fw-bold text-primary mb-2">
                        <?= htmlspecialchars($version) ?>
                    </div>

                    <?php foreach ($items as $item): ?>

                        <div class="card shadow-sm mb-2">
                            <div class="card-body p-2">

                                <!-- BADGE de evento -->
                                <span class="badge bg-<?= $ctrl->EstiloTimeLine($item['tipo_evento']) ?>">
                                    <?= ucfirst(strtolower(htmlspecialchars($item['tipo_evento']))) ?>
                                </span>

                                <!-- HORA -->
                                <small class="text-muted ms-2">
                                    <?= date('H:i', strtotime($item['fecha'])) ?>
                                </small>

                                <!-- FECHA COMPLETA -->
                                <small class="text-muted ms-1">
                                    · <?= date('d/m/Y', strtotime($item['fecha'])) ?>
                                </small>

                                <!-- DESCRIPCIÓN -->
                                <p class="mb-1 mt-2">
                                    <?= htmlspecialchars($item['descripcion'] ?? 'Sin descripción') ?>
                                </p>

                                <!-- USUARIO -->
                                <small class="text-secondary">
                                    <?= htmlspecialchars($item['usuario'] ?? 'Sistema') ?>
                                </small>

                                <!-- ARCHIVO (si tiene documento adjunto) -->
                                <?php if (!empty($item['nombre_archivo'])): ?>
                                    <small class="descargar ms-2">
                                        <a href="descargar_plantilla.php?id_plantilla=<?= (int) $item['id_plantilla'] ?>"
                                           data-bs-toggle="tooltip"
                                           data-bs-title="Descargar <?= htmlspecialchars($item['nombre_archivo']) ?>">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16"
                                                fill="red" class="bi bi-file-earmark-word-fill" viewBox="0 0 16 16">
                                                <path d="M9.293 0H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h8a2 2 0 0 0
                                                         2-2V4.707A1 1 0 0 0 13.707 4L10 .293A1 1 0 0 0 9.293
                                                         0M9.5 3.5v-2l3 3h-2a1 1 0 0 1-1-1M5.485 6.879l1.036
                                                         4.144.997-3.655a.5.5 0 0 1 .964 0l.997 3.655 1.036-4.144a.5.5
                                                         0 0 1 .97.242l-1.5 6a.5.5 0 0 1-.967.01L8
                                                         9.402l-1.018 3.73a.5.5 0 0 1-.967-.01l-1.5-6a.5.5 0 1 1 .97-.242z"/>
                                            </svg>
                                        </a>
                                    </small>
                                <?php endif; ?>

                            </div>
                        </div>

                    <?php endforeach; ?>

                </li>

            <?php endforeach; ?>

        </ul>

        <!-- PAGINACIÓN -->
        <?php
        $qBase   = 'id_tipo_documento=' . $id_tipo_documento;
        $entidad = 'eventos';
        include __DIR__ . '../../../publico/incluido/_paginacion.php';
        ?>

    <?php endif; ?>

</div>

<?php
$contenido = ob_get_clean();
$titulo    = 'Historial de plantilla de documento';
$bodyClass = 'proyectos-page';
include __DIR__ . '/../../layout.php';
?>