<?php
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
    header("Location: /ITSFCP-PROYECTOS/index.php");
    exit;
}

require_once '../../Controladores/solicitudActualizacionControlador.php';

$controlador = new SolicitudActualizacionControlador();

// ── POST: confirmar rechazo ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $controlador->rechazar($_POST, $id_usuario);
    exit;
}

// ── GET: mostrar formulario ──────────────────────────────────────
$id_solicitud = intval($_GET['id_solicitud'] ?? 0);
if ($id_solicitud <= 0) die("ID de solicitud no válido.");

$datos     = $controlador->detalle($id_solicitud);
$solicitud = $datos['solicitud'];

if (empty($solicitud)) die("No se encontró la solicitud.");

// Solo se puede rechazar si está pendiente
if ($solicitud['estado'] !== 'pendiente') {
    header("Location: detalles.php?id_solicitud=" . $id_solicitud);
    exit;
}

$error = $_GET['error'] ?? '';

ob_start();
?>

<div class="container-fluid py-4">

    <!-- ENCABEZADO -->
    <div class="row mb-4 align-items-center">
        <div class="col-12 col-md-8">
            <h3 class="fw-bold mb-0">Rechazar solicitud académica</h3>
            <p class="text-muted mb-0 small">
                ID #<?= $id_solicitud ?> &bull;
                <?= $controlador->etiquetaTipo($solicitud['tipo']) ?>
            </p>
        </div>
        <div class="col-12 col-md-4 text-md-end mt-2 mt-md-0">
            <a href="detalles.php?id_solicitud=<?= $id_solicitud ?>" class="btn btn-secondary">
                <i class="bi bi-arrow-left me-1"></i> Regresar
            </a>
        </div>
    </div>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="bi bi-exclamation-triangle-fill me-1"></i>
            <?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row g-4">

        <!-- Columna izquierda: info de la solicitud -->
        <div class="col-12 col-lg-5">

            <!-- Estado resultante -->
            <div class="card border-0 shadow-sm mb-4 border-danger border-start border-4">
                <div class="card-body d-flex align-items-center gap-3 py-3">
                    <div class="rounded-circle d-flex align-items-center justify-content-center bg-danger bg-opacity-10"
                         style="width:52px;height:52px;flex-shrink:0;">
                        <i class="bi bi-x-circle-fill text-danger fs-3"></i>
                    </div>
                    <div>
                        <small class="text-muted d-block">Estado resultante</small>
                        <span class="badge rounded-pill bg-danger fs-6 px-3 py-2">Rechazado</span>
                        <small class="text-muted d-block mt-1">
                            El estado cambiará al confirmar el rechazo.
                        </small>
                    </div>
                </div>
            </div>

            <!-- Resumen del investigador -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-bottom d-flex align-items-center gap-2 py-3">
                    <i class="bi bi-person-badge text-primary fs-5"></i>
                    <h6 class="mb-0 fw-semibold">Información del investigador</h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <small class="text-muted d-block">Nombre completo</small>
                        <strong><?= htmlspecialchars($solicitud['investigador']) ?></strong>
                    </div>
                    <div class="mb-3">
                        <small class="text-muted d-block">Correo institucional</small>
                        <span><?= htmlspecialchars($solicitud['correo_institucional']) ?></span>
                    </div>
                    <div>
                        <small class="text-muted d-block">Fecha de solicitud</small>
                        <span><?= date("d/m/Y H:i", strtotime($solicitud['fecha_solicitud'])) ?></span>
                    </div>
                </div>
            </div>

            <!-- Cambio solicitado (resumen) -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-bottom d-flex align-items-center gap-2 py-3">
                    <i class="bi bi-arrow-left-right text-success fs-5"></i>
                    <h6 class="mb-0 fw-semibold">Cambio solicitado</h6>
                </div>
                <div class="card-body">
                    <div class="d-flex align-items-center gap-3 flex-wrap">
                        <div class="text-center">
                            <small class="text-muted d-block mb-1">Actual</small>
                            <span class="badge bg-secondary rounded-pill px-3 py-2">
                                <?= htmlspecialchars($solicitud['valor_actual_nombre'] ?? '—') ?>
                            </span>
                        </div>
                        <i class="bi bi-arrow-right fs-4 text-muted"></i>
                        <div class="text-center">
                            <small class="text-muted d-block mb-1">Solicitado</small>
                            <span class="badge bg-primary rounded-pill px-3 py-2">
                                <?= htmlspecialchars($solicitud['valor_nuevo_nombre'] ?? '—') ?>
                            </span>
                        </div>
                    </div>
                    <hr>
                    <?php if (!empty($solicitud['nombre_archivo'])): ?>
                        <a href="<?= htmlspecialchars($solicitud['ruta']) ?>"
                           target="_blank"
                           class="btn btn-sm btn-outline-danger w-100">
                            <i class="bi bi-file-earmark-pdf-fill me-1"></i> Ver documento adjunto
                        </a>
                    <?php else: ?>
                        <span class="text-muted small">Sin documento adjunto.</span>
                    <?php endif; ?>
                </div>
            </div>

        </div>

        <!-- Columna derecha: formulario de rechazo -->
        <div class="col-12 col-lg-7">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-bottom d-flex align-items-center gap-2 py-3">
                    <i class="bi bi-chat-left-text-fill text-danger fs-5"></i>
                    <h6 class="mb-0 fw-semibold">Motivo del rechazo</h6>
                </div>
                <div class="card-body">

                    <form method="POST" action="respuesta.php" id="form-rechazo" novalidate>
                        <input type="hidden" name="id_solicitud" value="<?= $id_solicitud ?>">

                        <div class="mb-4">
                            <label for="comentario" class="form-label fw-semibold">
                                Comentario de rechazo <span class="text-danger">*</span>
                            </label>
                            <textarea
                                id="comentario"
                                name="comentario"
                                class="form-control"
                                rows="7"
                                required
                                minlength="10"
                                placeholder="Explica el motivo del rechazo. Este mensaje se enviará al investigador por correo electrónico."></textarea>
                            <div class="invalid-feedback">El comentario es obligatorio (mínimo 10 caracteres).</div>
                            <div class="form-text">
                                <i class="bi bi-envelope me-1"></i>
                                Este comentario será enviado al investigador como notificación.
                            </div>
                        </div>

                        <!-- Contador de caracteres -->
                        <div class="text-end mb-3">
                            <small class="text-muted" id="char-count">0 caracteres</small>
                        </div>

                        <div class="d-flex justify-content-between flex-wrap gap-2">
                            <a href="detalles.php?id_solicitud=<?= $id_solicitud ?>"
                               class="btn btn-secondary">
                                <i class="bi bi-arrow-left me-1"></i> Cancelar
                            </a>
                            <button type="submit"
                                    class="btn btn-danger"
                                    onclick="return confirmarRechazo()">
                                <i class="bi bi-x-circle-fill me-1"></i> Confirmar rechazo
                            </button>
                        </div>

                    </form>
                </div>
            </div>
        </div>

    </div>

</div>

<script>
// Contador de caracteres
const textarea = document.getElementById('comentario');
const counter  = document.getElementById('char-count');
textarea.addEventListener('input', () => {
    counter.textContent = textarea.value.length + ' caracteres';
});

function confirmarRechazo() {
    const form = document.getElementById('form-rechazo');
    if (!form.checkValidity()) {
        form.classList.add('was-validated');
        return false;
    }
    const comentario = textarea.value.trim();
    if (comentario.length < 10) {
        alert('El comentario debe tener al menos 10 caracteres.');
        return false;
    }
    return confirm('¿Confirmar el rechazo de esta solicitud? Se enviará una notificación por correo al investigador.');
}
</script>

<?php
$contenido = ob_get_clean();
$titulo    = "Rechazar solicitud académica";
$bodyClass = "solicitudes-page";

include __DIR__ . '/../../layout.php';
?>