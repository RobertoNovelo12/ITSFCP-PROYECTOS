<?php
// Vistas/cartas_terminacion/motivo_rechazo.php
// Formulario para que el supervisor ingrese el motivo de rechazo de la carta de terminación

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

if (!isset($_SESSION['id_usuario'])) {
    header("Location: /index.php");
    exit;
}

$rol        = strtolower($_SESSION['rol'] ?? '');
$id_usuario = intval($_SESSION['id_usuario']);

// Solo supervisor
if ($rol !== 'supervisor') {
    header("Location: /Modules/Principal/Views/index.php");
    exit;
}


$id_cierre_est = intval($_GET['id'] ?? 0);

require_once __DIR__ . '/../Controller/solicitudes_carta_terminacion_controller.php';
$ctrl  = new solicitudes_carta_terminacionControlador();

// Procesar POST — rechazo enviado
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'rechazarCarta') {
    $ctrl->rechazarCarta($_POST, $id_usuario, $rol);
    // rechazarCarta() ya redirige; no sigue ejecutando
}

// Cargar datos mínimos para mostrar contexto en el formulario
$carta = $ctrl->detalleCarta($id_cierre_est);


// Solo se puede rechazar si está pendiente
if ($carta['estado_carta'] !== 'finalizacion_pendiente') {
    header("Location: detalles.php?id=" . $id_cierre_est . "&error=Esta+solicitud+ya+fue+procesada");
    exit;
}

// Mensaje de error de reenvío
$error_msg = '';
if (!empty($_GET['error'])) {
    $error_msg = htmlspecialchars($_GET['error']);
}

ob_start();
?>

<div class="container-fluid py-4 ancho_container">

    <!-- CABECERA -->
    <div class="row mb-3 align-items-center">
<?php
$titulo      = 'Rechazo de Carta de Terminación';
$descripcion = 'Etapa 3 · Carta de terminación — motivo de rechazo al estudiante';
include __DIR__ . '/../../../public/incluido/_encabezado.php';
?>
        <div class="col-4 text-end">
            <a href="detalles.php?id=<?= $id_cierre_est ?>" class="btn btn-secondary">
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

    <!-- CONTEXTO — info de la solicitud -->
    <div class="card mb-4 shadow-sm border-0">
        <div class="card-body">
            <div class="row">
                <div class="col-md-8">
                    <div class="text-muted small mb-1">Proyecto</div>
                    <p class="fw-semibold mb-1"><?= htmlspecialchars($carta['titulo_proyecto']) ?></p>
                    <div class="text-muted small">
                        <i class="bi bi-person-fill"></i>
                        Estudiante: <strong><?= htmlspecialchars($carta['estudiante']) ?></strong>
                        &nbsp;&mdash;&nbsp;
                        <i class="bi bi-calendar3"></i>
                        Periodo: <strong><?= htmlspecialchars($carta['periodo']) ?></strong>
                    </div>
                </div>
                <div class="col-md-4 mt-3 mt-md-0 text-md-end">
                    <div class="text-muted small mb-1">Documento subido</div>
                    <span class="badge bg-secondary me-1">
                        <?= strtoupper(htmlspecialchars($carta['extension_documento'])) ?>
                    </span>
                    <span class="small"><?= htmlspecialchars($carta['nombre_documento']) ?></span>
                    <div class="mt-1">
                        <a href="<?= htmlspecialchars($carta['ruta_documento']) ?>"
                           target="_blank"
                           class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-eye"></i> Ver carta
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- FORMULARIO DE RECHAZO -->
    <div class="card shadow-sm">
        <div class="card-header fw-semibold bg-danger text-white">
            <i class="bi bi-x-octagon-fill me-2"></i>Motivo del rechazo
        </div>
        <div class="card-body">

            <div class="alert alert-warning d-flex align-items-start gap-2 mb-4">
                <i class="bi bi-exclamation-triangle-fill fs-5 mt-1 flex-shrink-0"></i>
                <div>
                    <strong>Atención:</strong> Al rechazar esta carta, el estudiante será notificado
                    y deberá subir nuevamente el documento corregido. Asegúrese de describir
                    claramente el motivo para que pueda corregirlo.
                </div>
            </div>

            <form method="POST" action="motivo_rechazo.php?id=<?= $id_cierre_est ?>">

                <div class="mb-3">
                    <label class="form-label fw-semibold">Tipo de acción</label>
                    <input type="text"
                           class="form-control"
                           value="Rechazo de carta de terminación"
                           disabled>
                </div>

                <div class="mb-3">
                    <label for="comentario" class="form-label fw-semibold">
                        Motivo del rechazo <span class="text-danger">*</span>
                    </label>
                    <textarea class="form-control"
                              name="comentario"
                              id="comentario"
                              rows="4"
                              maxlength="1000"
                              placeholder="Describa por qué se rechaza la carta (firma faltante, documento ilegible, datos incorrectos, etc.)..."
                              required></textarea>
                    <div class="form-text text-muted">Máximo 1000 caracteres. Este mensaje será visible para el estudiante.</div>
                </div>

                <!-- Campos ocultos -->
                <input type="hidden" name="action"        value="rechazarCarta">
                <input type="hidden" name="id_cierre_est" value="<?= $id_cierre_est ?>">

                <hr>

                <div class="d-flex gap-3 justify-content-end flex-wrap">
                    <a href="detalles.php?id=<?= $id_cierre_est ?>" class="btn btn-secondary">
                        <i class="bi bi-arrow-left"></i> Cancelar
                    </a>
                    <button type="submit"
                            class="btn btn-danger"
                            onclick="return confirm('¿Confirma que desea RECHAZAR la carta de <?= htmlspecialchars($carta['nombre_estudiante']) ?>? El estudiante recibirá una notificación.')">
                        <i class="bi bi-x-circle-fill me-1"></i> Confirmar rechazo
                    </button>
                </div>

            </form>

        </div>
    </div>

</div>

<?php
$contenido = ob_get_clean();
$titulo    = "Rechazar Carta de Terminación";
$bodyClass = "cartas-terminacion-page";
include __DIR__ . '/../../../layout.php';
?>