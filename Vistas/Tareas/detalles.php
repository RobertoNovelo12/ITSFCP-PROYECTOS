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

$rol = strtolower($_SESSION['rol'] ?? '');
$id_usuario = intval($_SESSION['id_usuario']);

// Solo investigador, supervisor y estudiante pueden acceder 
if (!in_array($rol, ['investigador', 'supervisor', 'estudiante'])) {
    header("Location: /ITSFCP-PROYECTOS/Vistas/Principal/index.php");
    exit;
}

include __DIR__ . '../../../publico/incluido/_validar_tareas.php';

$id_tarea = $_GET["id_tarea"] ?? null;

//Validación de argumentos en url
$id_validar = $id_tarea;
include __DIR__ .  '../../../publico/incluido/_validar_id.php';


$id_proyectos = $_GET["id_proyectos"] ?? null;

//Validación de argumentos en url
$id_validar = $id_proyectos;
include __DIR__ .  '../../../publico/incluido/_validar_id.php';

require_once __DIR__ . '/../../Controladores/tareasControlador.php';
$tareaControlador = new TareaControlador();

$tarea    = $tareaControlador->mostrarEditarTarea($id_tarea, $rol, $id_usuario, $id_proyectos);
$ediciones = $tareaControlador->obtenerEdicionesRecientes($id_tarea, 10);

$campoNombres = [
    'descripcion'  => 'Descripción',
    'instrucciones' => 'Instrucciones',
    'fecha_entrega' => 'Fecha de entrega',
    'archivo_guia' => 'Archivo de guía',
];

ob_start();
?>


<div class="container-fluid py-4 ancho_container">

    <!-- Cabecera -->
    <div class="row mb-4 align-items-center">

        <?php
        $titulo      = '<?= htmlspecialchars($tarea["titulo_tarea"] ?? "Detalles de Actividad") ?>';
        $descripcion = 'Vista de solo lectura';
        include __DIR__ . '../../../publico/incluido/_encabezado.php';
        ?>

        <div class="col-6 col-md-6 text-md-end">
            <?php if ($rol == "supervisor"): ?>
                <span class="badge rounded-pill text-bg-<?= $tareaControlador->EstiloEstadoLista($tarea['estado'] ?? '') ?>">
                    <?= htmlspecialchars($tarea['estado'] ?? '') ?>
                </span>
                <a href="index.php?id_proyectos=<?= htmlspecialchars($id_proyectos) ?>" class="btn btn-secondary btn-sm px-3"><i class="bi bi-arrow-left"></i> Regresar</a>
            <?php endif; ?>
        </div>
    </div>
    <!-- Alerta de edición reciente -->
    <?php if (!empty($tarea['fecha_modificacion'])): ?>
        <div class="alert alert-warning d-flex align-items-center gap-2 py-2 mb-3">
            <i class="bi bi-pencil-fill"></i>
            <small>Esta actividad fue editada el <strong><?= date('d/m/Y H:i', strtotime($tarea['fecha_modificacion'])) ?></strong>.</small>
        </div>
    <?php endif; ?>

    <!-- Datos de la tarea -->
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header">
            <i class="bi bi-info-circle me-2"></i> <b>Información de la tarea</b>
        </div>
        <div class="card-body">
            <div class="mb-4">
                <p class="tarea-seccion-label mb-1">Descripción</p>
                <p class="mb-0"><?= $tarea['descripcion'] ?></p>
            </div>
            <div class="mb-0">
                <p class="tarea-seccion-label mb-1">Instrucciones</p>
                <p class="mb-0"><?= $tarea['instrucciones'] ?></p>
            </div>
        </div>
    </div>

    <!-- Fecha y archivo -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header">
            <i class="bi bi-file-text me-2"></i> <b>Información adicional</b>
        </div>
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
                            <i class="bi bi-file-earmark-pdf-fill"></i>
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

    <div class="card border-0 shadow-sm">
        <div class="card-header">
            <i class="bi bi-clock-history me-2"></i> <b>Historial de la tarea</b>
        </div>
        <?php if (!empty($ediciones)): ?>
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