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

//Solo supervisor accede
if ($rol !== 'supervisor') {
    header("Location: /ITSFCP-PROYECTOS/Vistas/Principal/index.php");
    exit;
}
require_once '../../Controladores/solicitudActualizacionControlador.php';

$controlador  = new SolicitudActualizacionControlador();

require_once '../../../publico/incluido/_validar_get.php';

$id_solicitud = intval($_GET['id_solicitud'] ?? 0);

//Validación de argumentos en url
$id_validar = $id_solicitud;
require_once '../../../publico/incluido/_validar_id.php';

$datos     = $controlador->detalle($id_solicitud);

// Validación
$registro = $datos;
require_once '../../../publico/incluido/_validar_datos.php';

$solicitud = $datos['solicitud'];
$historial = $datos['historial'];

// Validación
$registro = $solicitud;
require_once '../../../publico/incluido/_validar_datos.php';

ob_start();
?>

<div class="container-fluid py-4 ancho_container">

    <!-- ENCABEZADO -->
    <div class="row mb-4 align-items-center">
        <?php
        $titulo      = 'Detalle de solicitud';
        $descripcion = 'ID #<?= $id_solicitud ?> &bull;
                <?= $controlador->etiquetaTipo($solicitud["tipo"]) ?>&bull Recibida el <?= date("d/m/Y H:i", strtotime($solicitud["fecha_solicitud"])) ?>';
        include __DIR__ . '../../../publico/incluido/_encabezado.php';
        ?>
        <div class="col-6 col-md-4 text-md-end mt-2 mt-md-0">
            <a href="index.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left me-1"></i> Regresar
            </a>
            <?php if ($solicitud['estado'] === 'pendiente'): ?>
                <a href="index.php?action=aprobar&id_solicitud=<?= $id_solicitud ?>"
                    class="btn btn-success ms-2"
                    onclick="return confirm('¿Aprobar esta solicitud?')">
                    <i class="bi bi-check-circle-fill me-1"></i> Aprobar
                </a>
                <a href="respuesta.php?id_solicitud=<?= $id_solicitud ?>"
                    class="btn btn-danger ms-2">
                    <i class="bi bi-x-circle-fill me-1"></i> Rechazar
                </a>
            <?php endif; ?>
        </div>
    </div>

    <div class="row g-4">

        <!-- Columna izquierda: datos + documento -->
        <div class="col-12 col-lg-7">

            <!-- Estado badge destacado -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body d-flex align-items-center gap-3 py-3">
                    <div class="rounded-circle d-flex align-items-center justify-content-center
                                bg-<?= $controlador->estiloEstado($solicitud['estado']) ?> bg-opacity-10"
                        style="width:52px;height:52px;flex-shrink:0;">
                        <i class="bi <?= $controlador->iconoEstado($solicitud['estado']) ?>
                           text-<?= $controlador->estiloEstado($solicitud['estado']) ?> fs-3"></i>
                    </div>
                    <div>
                        <small class="text-muted d-block">Estado de la solicitud</small>
                        <span class="badge rounded-pill bg-<?= $controlador->estiloEstado($solicitud['estado']) ?> fs-6 px-3 py-2">
                            <?= ucfirst($solicitud['estado']) ?>
                        </span>
                    </div>
                    <?php if (!empty($solicitud['fecha_respuesta'])): ?>
                        <div class="ms-auto text-end">
                            <small class="text-muted d-block">Fecha de respuesta</small>
                            <small class="fw-semibold"><?= date("d/m/Y H:i", strtotime($solicitud['fecha_respuesta'])) ?></small>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Datos del investigador -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header border-bottom d-flex align-items-center gap-2 py-3">
                    <i class="bi bi-person-badge fs-5"></i>
                    <h6 class="mb-0 fw-semibold">Datos del investigador</h6>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <small class="text-muted d-block">Nombre completo</small>
                            <strong><?= htmlspecialchars($solicitud['investigador']) ?></strong>
                        </div>
                        <div class="col-12 col-md-6">
                            <small class="text-muted d-block">Correo institucional</small>
                            <span><?= htmlspecialchars($solicitud['correo_institucional']) ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Valores -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header border-bottom d-flex align-items-center gap-2 py-3">
                    <i class="bi bi-arrow-left-right fs-5"></i>
                    <h6 class="mb-0 fw-semibold">Cambio solicitado</h6>
                </div>
                <div class="card-body">
                    <div class="row g-3 align-items-center">
                        <div class="col-12 col-md-5 text-center">
                            <small class="text-muted d-block mb-1">Valor actual</small>
                            <span class="badge bg-secondary fs-6 px-3 py-2 rounded-pill">
                                <?= htmlspecialchars($solicitud['valor_actual_nombre'] ?? '—') ?>
                            </span>
                        </div>
                        <div class="col-12 col-md-2 text-center">
                            <i class="bi bi-arrow-right fs-3 text-muted d-none d-md-block mx-auto"></i>
                            <i class="bi bi-arrow-down fs-3 text-muted d-block d-md-none mx-auto"></i>
                        </div>
                        <div class="col-12 col-md-5 text-center">
                            <small class="text-muted d-block mb-1">Valor solicitado</small>
                            <span class="badge bg-primary fs-6 px-3 py-2 rounded-pill">
                                <?= htmlspecialchars($solicitud['valor_nuevo_nombre'] ?? '—') ?>
                            </span>
                        </div>
                    </div>
                    <hr>
                    <div class="text-center">
                        <small class="text-muted">Tipo de solicitud: </small>
                        <span class="badge bg-<?= $solicitud['tipo'] === 'sni' ? 'primary' : 'success' ?> rounded-pill">
                            <?= $controlador->etiquetaTipo($solicitud['tipo']) ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Documento adjunto -->
            <div class="card border-0 shadow-sm">
                <div class="card-header border-bottom d-flex align-items-center gap-2 py-3">
                    <i class="bi bi-file-earmark-pdf-fill fs-5"></i>
                    <h6 class="mb-0 fw-semibold">Documento de evidencia</h6>
                </div>
                <div class="card-body">
                    <?php if (!empty($solicitud['nombre_archivo'])): ?>
                        <div class="d-flex align-items-center gap-3">
                            <i class="bi bi-file-earmark-pdf-fill text-danger fs-1"></i>
                            <div>
                                <p class="mb-1 fw-semibold"><?= htmlspecialchars($solicitud['doc_nombre'] ?? $solicitud['nombre_archivo']) ?></p>
                                <a href="<?= htmlspecialchars($solicitud['ruta']) ?>"
                                    target="_blank"
                                    class="btn btn-outline-danger btn-sm">
                                    <i class="bi bi-eye me-1"></i> Ver PDF
                                </a>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-warning mb-0">
                            <i class="bi bi-exclamation-triangle me-1"></i> Sin documento adjunto.
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>

        <!-- Columna derecha: historial -->
        <div class="col-12 col-lg-5">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header border-bottom d-flex align-items-center gap-2 py-3">
                    <i class="bi bi-clock-history fs-5"></i>
                    <h6 class="mb-0 fw-semibold">Historial de la solicitud</h6>
                </div>
                <div class="card-body">
                    <?php if (empty($historial)): ?>
                        <div class="alert alert-info mb-0">Sin historial registrado.</div>
                    <?php else: ?>
                        <ul class="timeline list-unstyled">
                            <?php foreach ($historial as $h): ?>
                                <li class="timeline-item mb-4 ps-4 border-start border-2
                                    <?= $h['estado_nuevo'] === 'aprobado'
                                        ? 'border-success'
                                        : ($h['estado_nuevo'] === 'rechazado'
                                            ? 'border-danger'
                                            : 'border-warning') ?>">

                                    <div class="d-flex align-items-center gap-2 mb-1 flex-wrap">
                                        <span class="badge rounded-pill bg-<?= $h['estado_nuevo'] === 'aprobado'
                                                                                ? 'success' : ($h['estado_nuevo'] === 'rechazado' ? 'danger' : 'warning text-dark') ?>">
                                            <?= ucfirst($h['estado_nuevo']) ?>
                                        </span>
                                        <?php if (!empty($h['estado_anterior'])): ?>
                                            <small class="text-muted">
                                                desde <em><?= ucfirst($h['estado_anterior']) ?></em>
                                            </small>
                                        <?php endif; ?>
                                        <small class="text-muted ms-auto">
                                            <?= date("d/m/Y H:i", strtotime($h['fecha'])) ?>
                                        </small>
                                    </div>

                                    <?php if (!empty($h['comentario'])): ?>
                                        <p class="mb-1 small text-muted fst-italic">
                                            <i class="bi bi-chat-left-text me-1"></i>
                                            <?= htmlspecialchars($h['comentario']) ?>
                                        </p>
                                    <?php endif; ?>

                                    <small class="text-muted">
                                        <i class="bi bi-person me-1"></i>
                                        <?= htmlspecialchars($h['usuario_accion'] ?? 'Sistema') ?>
                                    </small>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </div>

</div>

<style>
    .timeline-item {
        position: relative;
    }

    .timeline-item::before {
        content: '';
        position: absolute;
        left: -6px;
        top: 6px;
        width: 10px;
        height: 10px;
        border-radius: 50%;
        background: #6c757d;
        border: 2px solid #fff;
        box-shadow: 0 0 0 2px #6c757d;
    }

    .timeline-item.border-success::before {
        background: #198754;
        box-shadow: 0 0 0 2px #198754;
    }

    .timeline-item.border-danger::before {
        background: #dc3545;
        box-shadow: 0 0 0 2px #dc3545;
    }

    .timeline-item.border-warning::before {
        background: #ffc107;
        box-shadow: 0 0 0 2px #ffc107;
    }
</style>

<?php
$contenido = ob_get_clean();
$titulo    = "Detalle de solicitud";
$bodyClass = "solicitudes-page";

include __DIR__ . '/../../layout.php';
?>