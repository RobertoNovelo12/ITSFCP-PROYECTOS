<?php
// detalles
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

if (!isset($_SESSION['id_usuario'])) {
    header("Location: /ITSFCP-PROYECTOS/index.php");
    exit;
}

$rol = $_SESSION['rol'];
$id  = $_SESSION['id_usuario'];

// Solo investigador y supervisor pueden ver detalles de la plantilla
if (!in_array($rol, ['investigador', 'supervisor'])) {
    header("Location: /ITSFCP-PROYECTOS/index.php");
    exit;
}

$id_tarea    = $_GET["id_tarea"] ?? null;
$id_proyectos = $_GET["id_proyectos"] ?? null;

require_once '../../Controladores/tareasControlador.php';
$tareaControlador = new TareaControlador();

$tarea    = $tareaControlador->mostrarEditarTarea($id_tarea, $rol);
$ediciones = $tareaControlador->obtenerEdicionesRecientes($id_tarea, 10);

$campoNombres = [
    'descripcion'  => 'Descripción',
    'instrucciones'=> 'Instrucciones',
    'fecha_entrega'=> 'Fecha de entrega',
    'archivo_guia' => 'Archivo de guía',
];

ob_start();
?>

<style>
.tarea-seccion-label { font-size:.72rem; font-weight:600; text-transform:uppercase; letter-spacing:.05em; color:#6c757d; }
.historial-edicion-item { border-left: 3px solid #ffc107; padding-left:.75rem; margin-bottom:.75rem; }
</style>

<div class="container-fluid py-4" style="max-width:800px;">

    <!-- Cabecera -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
        <div>
            <h4 class="mb-0 fw-semibold"><?= htmlspecialchars($tarea['titulo_tarea'] ?? 'Detalles de Actividad') ?></h4>
            <small class="text-muted">Vista de solo lectura</small>
        </div>
        <div class="d-flex align-items-center gap-2">
            <span class="badge rounded-pill text-bg-<?= $tareaControlador->EstiloEstadoLista($tarea['estado'] ?? '') ?>">
                <?= htmlspecialchars($tarea['estado'] ?? '') ?>
            </span>
            <a href="tabla.php?id_proyectos=<?= htmlspecialchars($id_proyectos) ?>" class="btn btn-outline-danger btn-sm px-3">← Regresar</a>
        </div>
    </div>

    <!-- Alerta de edición reciente -->
    <?php if (!empty($tarea['fecha_modificacion'])): ?>
        <div class="alert alert-warning d-flex align-items-center gap-2 py-2 mb-3">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-pencil-fill flex-shrink-0" viewBox="0 0 16 16">
                <path d="M12.854.146a.5.5 0 0 0-.707 0L10.5 1.793 14.207 5.5l1.647-1.646a.5.5 0 0 0 0-.708zm.646 6.061L9.793 2.5 3.293 9H3.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.207zm-7.468 7.468A.5.5 0 0 1 6 13.5V13h-.5a.5.5 0 0 1-.5-.5V12h-.5a.5.5 0 0 1-.5-.5V11h-.5a.5.5 0 0 1-.5-.5V10h-.5a.5.5 0 0 1-.175-.032l-.179.178a.5.5 0 0 0-.11.168l-2 5a.5.5 0 0 0 .65.65l5-2a.5.5 0 0 0 .168-.11z"/>
            </svg>
            <small>Esta actividad fue editada el <strong><?= date('d/m/Y H:i', strtotime($tarea['fecha_modificacion'])) ?></strong>.</small>
        </div>
    <?php endif; ?>

    <!-- Datos de la tarea -->
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <div class="mb-4">
                <p class="tarea-seccion-label mb-1">Descripción</p>
                <p class="mb-0"><?= nl2br(htmlspecialchars($tarea['descripcion'] ?? 'Sin descripción.')) ?></p>
            </div>
            <div class="mb-0">
                <p class="tarea-seccion-label mb-1">Instrucciones</p>
                <p class="mb-0"><?= nl2br(htmlspecialchars($tarea['instrucciones'] ?? 'Sin instrucciones.')) ?></p>
            </div>
        </div>
    </div>

    <!-- Fecha y archivo -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <p class="tarea-seccion-label mb-1">Fecha de entrega</p>
                    <?php if (!empty($tarea['fecha_entrega'])): ?>
                        <p class="mb-0 fw-semibold"><?= date('d/m/Y', strtotime($tarea['fecha_entrega'])) ?></p>
                    <?php else: ?>
                        <p class="text-muted mb-0">Sin fecha definida.</p>
                    <?php endif; ?>
                </div>
                <div class="col-md-6">
                    <p class="tarea-seccion-label mb-1">Archivo de guía</p>
                    <?php if (!empty($tarea['archivo_nombre'])): ?>
                        <a href="descargar_guia.php?id=<?= $tarea['id_tarea'] ?>" class="d-flex align-items-center gap-2 text-danger small">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-file-earmark-pdf-fill flex-shrink-0" viewBox="0 0 16 16">
                                <path d="M5.523 12.424q.21-.124.459-.238a8 8 0 0 1-.45.606c-.28.337-.498.516-.635.572l-.035.012a.3.3 0 0 1-.026-.044c-.056-.11-.054-.216.04-.36.106-.165.319-.354.647-.548m2.455-1.647q-.178.037-.356.078a21 21 0 0 0 .5-1.05 12 12 0 0 0 .51.858q-.326.048-.654.114m2.525.939a4 4 0 0 1-.435-.41q.344.007.612.054c.317.057.466.147.518.209a.1.1 0 0 1 .026.064.44.44 0 0 1-.06.2.3.3 0 0 1-.094.124.1.1 0 0 1-.069.015c-.09-.003-.258-.066-.498-.256M8.278 6.97c-.04.244-.108.524-.2.829a5 5 0 0 1-.089-.346c-.076-.353-.087-.63-.046-.822.038-.177.11-.248.196-.283a.5.5 0 0 1 .145-.04c.013.03.028.092.032.198q.008.183-.038.465z"/>
                                <path fill-rule="evenodd" d="M4 0h5.293A1 1 0 0 1 10 .293L13.707 4a1 1 0 0 1 .293.707V14a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V2a2 2 0 0 1 2-2m5.5 1.5v2a1 1 0 0 0 1 1h2zM4.165 13.668c.09.18.23.343.438.419.207.075.412.04.58-.03.318-.13.635-.436.926-.786.333-.401.683-.927 1.021-1.51a11.7 11.7 0 0 1 1.997-.406c.3.383.61.713.91.95.28.22.603.403.934.417a.86.86 0 0 0 .51-.138c.155-.101.27-.247.354-.416.09-.181.145-.37.138-.563a.84.84 0 0 0-.2-.518c-.226-.27-.596-.4-.96-.465a5.8 5.8 0 0 0-1.335-.05 11 11 0 0 1-.98-1.686c.25-.66.437-1.284.52-1.794.036-.218.055-.426.048-.614a1.24 1.24 0 0 0-.127-.538.7.7 0 0 0-.477-.365c-.202-.043-.41 0-.601.077-.377.15-.576.47-.651.823-.073.34-.04.736.046 1.136.088.406.238.848.43 1.295a20 20 0 0 1-1.062 2.227 7.7 7.7 0 0 0-1.482.645c-.37.22-.699.48-.897.787-.21.326-.275.714-.08 1.103"/>
                            </svg>
                            <?= htmlspecialchars($tarea['archivo_nombre']) ?>
                        </a>
                    <?php else: ?>
                        <p class="text-muted small mb-0">Sin archivo adjunto.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Historial de ediciones -->
    <?php if (!empty($ediciones)): ?>
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <p class="tarea-seccion-label mb-3">Historial de cambios</p>
                <?php foreach ($ediciones as $ed): ?>
                    <div class="historial-edicion-item">
                        <div class="d-flex flex-wrap justify-content-between gap-1 mb-1">
                            <span class="small fw-semibold text-dark">
                                <?= $campoNombres[$ed['campo_modificado']] ?? htmlspecialchars($ed['campo_modificado']) ?>
                            </span>
                            <small class="text-muted">
                                <?= date('d/m/Y H:i', strtotime($ed['fecha'])) ?>
                                <?= !empty($ed['editor']) ? '· ' . htmlspecialchars($ed['editor']) : '' ?>
                            </small>
                        </div>
                        <?php if ($ed['campo_modificado'] === 'fecha_entrega'): ?>
                            <small class="text-muted">
                                <?= htmlspecialchars($ed['valor_anterior'] ?? '—') ?>
                                <span class="mx-1">→</span>
                                <strong><?= htmlspecialchars($ed['valor_nuevo'] ?? '—') ?></strong>
                            </small>
                        <?php elseif ($ed['campo_modificado'] === 'archivo_guia'): ?>
                            <small class="text-muted">Se actualizó el archivo de guía.</small>
                        <?php else: ?>
                            <small class="text-muted">Contenido de la actividad actualizado.</small>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php else: ?>
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center text-muted small py-4">Sin historial de ediciones registrado.</div>
        </div>
    <?php endif; ?>

</div>

<?php
$contenido = ob_get_clean();
$titulo    = "Detalles de Actividad";
$bodyClass = "proyectos-page";
include __DIR__ . '/../../layout.php';
?>
