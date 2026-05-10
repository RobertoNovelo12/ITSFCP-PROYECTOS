<?php
// Vistas/cartas_terminacion/detalles.php
// Detalle completo de una solicitud de carta de terminación (solo supervisor)

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

if (!isset($_SESSION['id_usuario'])) {
    header("Location: /ITSFCP-PROYECTOS/index.php");
    exit;
}

$rol        = strtolower($_SESSION['rol'] ?? '');
$id_usuario = intval($_SESSION['id_usuario']);

if ($rol !== 'supervisor') {
    header("Location: ../proyectos/index.php");
    exit;
}

$id_cierre_est = intval($_GET['id'] ?? 0);
if (!$id_cierre_est) {
    header("Location: index.php");
    exit;
}

require_once '../../Controladores/solicitudes_carta_terminacionControlador.php';
$ctrl   = new solicitudes_carta_terminacionControlador();
$carta  = $ctrl->detalleCarta($id_cierre_est);

if (!$carta) {
    header("Location: index.php?error=Solicitud+no+encontrada");
    exit;
}

$historial = $ctrl->historialProceso($carta['id_proyectos'], $carta['id_usuarios']);

ob_start();
include __DIR__ . '/../../mensaje.php';

// Mensaje de error si viene de una acción fallida
$error_msg = '';
if (!empty($_GET['error'])) {
    $error_msg = htmlspecialchars($_GET['error']);
}
?>

<div class="container-fluid py-4" style="max-width:95%;">

    <!-- CABECERA -->
    <div class="row mb-3 align-items-center">
        <div class="col-8">
            <h3 class="mb-0 fw-bold">Detalle — Carta de Terminación</h3>
        </div>
        <div class="col-4 text-end">
            <a href="index.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Regresar
            </a>
        </div>
    </div>

    <?php if ($error_msg): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="bi bi-exclamation-triangle-fill"></i> <?= $error_msg ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- RESUMEN SUPERIOR — 3 tarjetas -->
    <div class="row mb-4 g-3">

        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small mb-1">Estado de la carta</div>
                    <span class="badge text-bg-<?= $ctrl->estiloCarta($carta['estado_carta']) ?> fs-6">
                        <?= $ctrl->etiquetaCarta($carta['estado_carta']) ?>
                    </span>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small mb-1">Estado del proceso</div>
                    <span class="badge text-bg-<?= $ctrl->estiloEstadoProceso($carta['estado_proceso']) ?> fs-6">
                        <?= $ctrl->etiquetaEstadoProceso($carta['estado_proceso']) ?>
                    </span>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small mb-1">Periodo</div>
                    <strong><?= htmlspecialchars($carta['periodo']) ?></strong>
                    <span class="badge text-bg-<?= ($carta['estado_periodo'] === 'Activo') ? 'success' : 'secondary' ?> ms-2">
                        <?= htmlspecialchars($carta['estado_periodo']) ?>
                    </span>
                </div>
            </div>
        </div>

    </div>

    <!-- INFORMACIÓN DEL PROYECTO -->
    <div class="card mb-4 shadow-sm">
        <div class="card-header fw-semibold bg-light">
            <i class="bi bi-folder2-open me-2"></i>Información del proyecto
        </div>
        <div class="card-body">
            <h5 class="fw-bold"><?= htmlspecialchars($carta['titulo_proyecto']) ?></h5>
            <p class="text-muted"><?= nl2br(htmlspecialchars($carta['descripcion'])) ?></p>
            <hr>
            <div class="row">
                <div class="col-md-6">
                    <dl class="mb-0">
                        <dt>Investigador responsable</dt>
                        <dd><?= htmlspecialchars($carta['investigador']) ?></dd>
                        <dt>Fecha inicio del proyecto</dt>
                        <dd><?= htmlspecialchars($carta['fecha_inicio']) ?></dd>
                        <dt>Fecha fin del proyecto</dt>
                        <dd><?= htmlspecialchars($carta['fecha_fin']) ?></dd>
                    </dl>
                </div>
                <div class="col-md-6">
                    <dl class="mb-0">
                        <dt>Fecha de integración del estudiante</dt>
                        <dd><?= htmlspecialchars($carta['fecha_asignacion']) ?></dd>
                        <dt>Fecha de solicitud de carta</dt>
                        <dd><?= htmlspecialchars($carta['fecha_solicitud']) ?></dd>
                        <?php if ($carta['fecha_respuesta']): ?>
                            <dt>Fecha de respuesta</dt>
                            <dd><?= htmlspecialchars($carta['fecha_respuesta']) ?></dd>
                        <?php endif; ?>
                    </dl>
                </div>
            </div>
        </div>
    </div>

    <!-- INFORMACIÓN DEL ESTUDIANTE -->
    <div class="card mb-4 shadow-sm">
        <div class="card-header fw-semibold bg-light">
            <i class="bi bi-person-fill me-2"></i>Estudiante solicitante
        </div>
        <div class="card-body">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h5 class="fw-bold mb-1"><?= htmlspecialchars($carta['estudiante']) ?></h5>
                    <span class="badge text-bg-<?= ($carta['estado_integrante'] === 'activo') ? 'success' : (($carta['estado_integrante'] === 'concluido') ? 'info' : 'secondary') ?>">
                        <?= ucfirst(htmlspecialchars($carta['estado_integrante'])) ?>
                    </span>
                </div>
                <?php if ($carta['fecha_terminacion']): ?>
                    <div class="col-md-4 text-md-end mt-2 mt-md-0">
                        <div class="text-muted small">Fecha de terminación registrada</div>
                        <strong><?= htmlspecialchars($carta['fecha_terminacion']) ?></strong>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- DOCUMENTO SUBIDO POR EL ESTUDIANTE -->
    <div class="card mb-4 shadow-sm">
        <div class="card-header fw-semibold bg-light">
            <i class="bi bi-file-earmark-text-fill me-2"></i>Carta de terminación subida
        </div>
        <div class="card-body">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <p class="mb-1">
                        <strong>Nombre:</strong>
                        <?= htmlspecialchars($carta['nombre_documento']) ?>
                    </p>
                    <p class="mb-1">
                        <strong>Extensión:</strong>
                        <span class="badge bg-secondary"><?= strtoupper(htmlspecialchars($carta['extension_documento'])) ?></span>
                    </p>
                    <p class="mb-0">
                        <strong>Tamaño:</strong>
                        <?= $ctrl->formatoTamano($carta['tamano_bytes']) ?>
                        &nbsp;&mdash;&nbsp;
                        <strong>Subido el:</strong>
                        <?= htmlspecialchars($carta['fecha_subida']) ?>
                    </p>
                </div>
                <div class="col-md-4 text-md-end mt-3 mt-md-0">
                    <a href="<?= htmlspecialchars($carta['ruta_documento']) ?>"
                       target="_blank"
                       class="btn btn-outline-primary">
                        <i class="bi bi-download"></i> Descargar / Ver carta
                    </a>
                </div>
            </div>

            <?php if ($carta['estado_carta'] === 'rechazado' && $carta['comentarios']): ?>
                <hr>
                <div class="border-start border-danger border-3 ps-3 mt-2">
                    <div class="fw-semibold text-danger"><i class="bi bi-x-octagon-fill"></i> Motivo de rechazo</div>
                    <p class="mb-0 mt-1"><?= nl2br(htmlspecialchars($carta['comentarios'])) ?></p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- HISTORIAL DEL PROCESO DEL ESTUDIANTE -->
    <?php if (!empty($historial)): ?>
        <div class="card mb-4 shadow-sm">
            <div class="card-header fw-semibold bg-light">
                <i class="bi bi-clock-history me-2"></i>Historial del proceso
            </div>
            <div class="card-body">
                <div class="position-relative">
                    <!-- Línea vertical de la timeline -->
                    <div style="position:absolute;left:20px;top:0;bottom:0;width:2px;background:var(--bs-border-color);z-index:0;"></div>

                    <?php foreach ($historial as $item): ?>
                        <div class="d-flex mb-3 position-relative" style="padding-left:52px;">
                            <!-- Ícono circular -->
                            <div class="position-absolute"
                                 style="left:8px;top:2px;width:24px;height:24px;border-radius:50%;z-index:1;"
                                 class="bg-<?= htmlspecialchars($item['badge_color']) ?>">
                                <span class="badge text-bg-<?= htmlspecialchars($item['badge_color']) ?>"
                                      style="width:24px;height:24px;border-radius:50%;display:flex;align-items:center;justify-content:center;padding:0;">
                                    &bull;
                                </span>
                            </div>
                            <!-- Contenido -->
                            <div class="card w-100 border-0 bg-light">
                                <div class="card-body py-2 px-3">
                                    <div class="d-flex justify-content-between align-items-start flex-wrap">
                                        <span class="fw-semibold">
                                            <span class="badge text-bg-<?= htmlspecialchars($item['badge_color']) ?> me-2">
                                                <?= htmlspecialchars($item['evento']) ?>
                                            </span>
                                        </span>
                                        <small class="text-muted">
                                            <?= htmlspecialchars(date('d/m/Y H:i', strtotime($item['fecha']))) ?>
                                        </small>
                                    </div>
                                    <?php if (!empty($item['motivo'])): ?>
                                        <p class="mb-0 mt-1 small text-muted">
                                            <?= nl2br(htmlspecialchars($item['motivo'])) ?>
                                        </p>
                                    <?php endif; ?>
                                    <?php if (!empty($item['realizado_por'])): ?>
                                        <small class="text-muted">
                                            <i class="bi bi-person-fill"></i>
                                            <?= htmlspecialchars($item['realizado_por']) ?>
                                        </small>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- ACCIONES DEL SUPERVISOR -->
    <?php if ($carta['estado_carta'] === 'pendiente'): ?>
        <div class="card mb-4 shadow-sm border-primary">
            <div class="card-header fw-semibold bg-primary text-white">
                <i class="bi bi-shield-check me-2"></i>Acciones sobre la solicitud
            </div>
            <div class="card-body">
                <p class="mb-3">
                    El estudiante <strong><?= htmlspecialchars($carta['nombre_estudiante']) ?></strong>
                    ha subido su carta de terminación firmada. Revise el documento y decida:
                </p>
                <div class="d-flex gap-3 flex-wrap">

                    <!-- APROBAR -->
                    <a href="index.php?action=aprobar&id=<?= $carta['id_cierre_est'] ?>"
                       class="btn btn-success btn-lg"
                       onclick="return confirm('¿Confirma que desea APROBAR la carta de terminación de <?= htmlspecialchars($carta['nombre_estudiante']) ?>? Esta acción concluirá su participación en el proyecto.')">
                        <i class="bi bi-check-circle-fill me-1"></i> Aprobar carta
                    </a>

                    <!-- RECHAZAR -->
                    <a href="motivo_rechazo.php?id=<?= $carta['id_cierre_est'] ?>"
                       class="btn btn-danger btn-lg">
                        <i class="bi bi-x-circle-fill me-1"></i> Rechazar carta
                    </a>

                </div>
            </div>
        </div>

    <?php elseif ($carta['estado_carta'] === 'aprobado'): ?>
        <div class="alert alert-success">
            <i class="bi bi-check-circle-fill me-2"></i>
            Esta carta fue <strong>aprobada</strong> el <?= htmlspecialchars($carta['fecha_respuesta']) ?>.
            El estudiante ha concluido oficialmente su participación en el proyecto.
        </div>

    <?php elseif ($carta['estado_carta'] === 'rechazado'): ?>
        <div class="alert alert-danger">
            <i class="bi bi-x-octagon-fill me-2"></i>
            Esta carta fue <strong>rechazada</strong> el <?= htmlspecialchars($carta['fecha_respuesta']) ?>.
            El estudiante fue notificado para reenviar el documento corregido.
        </div>
    <?php endif; ?>

</div>

<?php
$contenido = ob_get_clean();
$titulo    = "Detalle — Carta de Terminación";
$bodyClass = "cartas-terminacion-page";
include __DIR__ . '/../../layout.php';
?>