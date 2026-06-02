<?php
// Vistas/solicitudes/accion_solicitud.php
// Formulario dedicado para solicitar correcciones o rechazar una solicitud de integración.

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();

if (!isset($_SESSION['id_usuario'])) {
    header("Location: /index.php");
    exit;
}

$rol = strtolower($_SESSION['rol'] ?? '');
$id_usuario = intval($_SESSION['id_usuario']);

//Solo el investigador puede acceder
if (!in_array($rol, ['investigador', 'profesor'], true)) {
    header("Location: /Vistas/Principal/index.php");
    exit;
}


$id_solicitud = intval($_GET['id'] ?? 0);

$tipo = $_GET['tipo'] ?? ''; // 'correcciones' | 'rechazar'


if (!$id_solicitud || !in_array($tipo, ['correcciones', 'rechazar'], true)) {
    header("Location: index.php");
    exit;
}

require_once __DIR__ .  '/../../Controladores/solicitudesControlador.php';
$ctrl = new solicitudesControlador();

//  Procesar POST ─
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion_post = $_POST['accion'] ?? '';

    if ($accion_post === 'correcciones') {
        $ctrl->pedirCorrecciones($id_solicitud, $id_usuario, $rol);
        // pedirCorrecciones() redirige internamente
    } elseif ($accion_post === 'rechazar') {
        $ctrl->rechazar($id_solicitud, $id_usuario, $rol);
        // rechazar() redirige internamente
    }
}

//  Cargar datos para el contexto del formulario 
$data = $ctrl->detallePagina($id_solicitud, $id_usuario, $rol);

// Validación
$registro = $data;
include __DIR__ .  '../../../publico/incluido/_validar_datos.php';

$sol  = $data['solicitud'];

// Validación
$registro = $sol;
include __DIR__ .  '../../../publico/incluido/_validar_datos.php';


// Solo se puede actuar sobre solicitudes activas
if (!in_array($sol['estado'], ['pendiente', 'en_revision', 'correcciones'])) {
    header("Location: detalles_solicitud.php?id={$id_solicitud}&error=Esta+solicitud+ya+fue+procesada");
    exit;
}

$es_correcciones = ($tipo === 'correcciones');
$msg_err         = htmlspecialchars($_GET['error'] ?? '');

ob_start();
?>

<div class="container-fluid py-4 ancho_container">

    <!-- CABECERA -->
    <div class="row mb-3 align-items-center">
        <?php
        $titulo      = "<?= $es_correcciones ? 'Solicitar correcciones' : 'Rechazar solicitud de integración' ?>";
        $descripcion = 'Resolución del supervisor al investigador';
        include __DIR__ . '../../../publico/incluido/_encabezado.php';
        ?>
        <div class="col-md-6 text-md-end">
            <a href="detalles_solicitud.php?id=<?= $id_solicitud ?>" class="btn btn-secondary btn-sm">
                <i class="bi bi-arrow-left"></i> Regresar
            </a>
        </div>
    </div>

    <?php if ($msg_err): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="bi bi-exclamation-triangle-fill me-2"></i><?= $msg_err ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- CONTEXTO — datos de la solicitud -->
    <div class="card mb-4 shadow-sm border-0">
        <div class="card-body">
            <div class="row">
                <div class="col-md-8">
                    <div class="text-muted small mb-1">Proyecto</div>
                    <p class="fw-semibold mb-1"><?= htmlspecialchars($sol['proyecto_titulo']) ?></p>
                    <div class="text-muted small">
                        <i class="bi bi-person-fill"></i>
                        Estudiante: <strong><?= htmlspecialchars($sol['estudiante_nombre']) ?></strong>
                        &nbsp;&mdash;&nbsp;
                        <i class="bi bi-123"></i>
                        Matrícula: <strong><?= htmlspecialchars($sol['matricula'] ?? '—') ?></strong>
                    </div>
                </div>
                <div class="col-md-4 mt-3 mt-md-0 text-md-end">
                    <div class="text-muted small mb-1">Estado actual</div>
                    <?= $ctrl->badgeEstado($sol['estado']) ?>
                    <div class="text-muted small mt-1">
                        <i class="bi bi-calendar3"></i> <?= $sol['fecha_envio'] ?>
                    </div>
                    <?php if (!empty($sol['carta_ruta'])): ?>
                        <div class="mt-2">
                            <a href="/<?= htmlspecialchars($sol['carta_ruta']) ?>"
                                target="_blank"
                                class="btn btn-outline-primary btn-sm">
                                <i class="bi bi-eye"></i> Ver carta compromiso
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- FORMULARIO -->
    <div class="card shadow-sm">
        <div class="card-header fw-semibold <?= $es_correcciones ? 'bg-warning' : 'bg-danger text-white' ?>">
            <i class="bi <?= $es_correcciones ? 'bi-pencil-fill' : 'bi-ban' ?> me-2"></i>
            <?= $es_correcciones ? 'Indicar correcciones al estudiante' : 'Motivo del rechazo' ?>
        </div>
        <div class="card-body">

            <?php if ($es_correcciones): ?>
                <div class="alert alert-info d-flex align-items-start gap-2 mb-4">
                    <i class="bi bi-info-circle-fill fs-5 mt-1 flex-shrink-0"></i>
                    <div>
                        El estudiante recibirá este mensaje y podrá enviar una nueva versión de su solicitud.
                        Sea específico sobre qué debe corregir o mejorar.
                    </div>
                </div>
            <?php else: ?>
                <div class="alert alert-warning d-flex align-items-start gap-2 mb-4">
                    <i class="bi bi-exclamation-triangle-fill fs-5 mt-1 flex-shrink-0"></i>
                    <div>
                        <strong>Atención:</strong> Esta acción rechazará definitivamente la solicitud.
                        El estudiante será notificado con el motivo que ingrese.
                    </div>
                </div>
            <?php endif; ?>

            <form method="POST"
                action="accion_solicitud.php?id=<?= $id_solicitud ?>&tipo=<?= $tipo ?>"
                enctype="multipart/form-data"
                onsubmit="return confirm('<?= $es_correcciones
                                                ? '¿Enviar solicitud de correcciones a ' . htmlspecialchars(addslashes($sol['estudiante_nombre'])) . '?'
                                                : '¿Confirmar el rechazo de la solicitud de ' . htmlspecialchars(addslashes($sol['estudiante_nombre'])) . '? Esta acción no se puede deshacer.' ?>
                  )">

                <div class="mb-3">
                    <label for="comentario" class="form-label fw-semibold">
                        <?= $es_correcciones ? 'Indica qué debe corregir el estudiante' : 'Motivo del rechazo' ?>
                        <span class="text-danger">*</span>
                    </label>
                    <textarea class="form-control"
                        name="comentario"
                        id="comentario"
                        rows="5"
                        maxlength="1000"
                        placeholder="<?= $es_correcciones
                                            ? 'Describe qué debe corregir, mejorar o agregar el estudiante en su solicitud…'
                                            : 'Describe el motivo por el cual se rechaza esta solicitud (perfil no compatible, cupo lleno, requisitos incompletos, etc.)…' ?>"
                        required></textarea>
                    <div class="form-text text-muted">
                        Máximo 1 000 caracteres. Este mensaje será visible para el estudiante.
                    </div>
                </div>

                <div class="mb-4">
                    <label for="archivo" class="form-label fw-semibold">
                        Archivo adjunto
                        <span class="text-muted fw-normal small">(opcional — PDF, DOCX, PNG o JPG, máx. 8 MB)</span>
                    </label>
                    <input type="file"
                        class="form-control"
                        name="archivo"
                        id="archivo"
                        accept=".pdf,.docx,.png,.jpg">
                </div>

                <!-- Campos ocultos -->
                <input type="hidden" name="accion" value="<?= $tipo ?>">
                <input type="hidden" name="id_solicitud" value="<?= $id_solicitud ?>">

                <hr>

                <div class="d-flex gap-3 justify-content-end flex-wrap">
                    <a href="detalles_solicitud.php?id=<?= $id_solicitud ?>"
                        class="btn btn-secondary">
                        <i class="bi bi-arrow-left"></i> Cancelar
                    </a>
                    <button type="submit"
                        class="btn <?= $es_correcciones ? 'btn-warning' : 'btn-danger' ?>">
                        <i class="bi <?= $es_correcciones ? 'bi-send-fill' : 'bi-x-circle-fill' ?> me-1"></i>
                        <?= $es_correcciones ? 'Enviar correcciones' : 'Confirmar rechazo' ?>
                    </button>
                </div>

            </form>
        </div>
    </div>

</div>

<?php
$contenido = ob_get_clean();
$titulo    = $es_correcciones ? 'Correcciones — Solicitud #' . $id_solicitud : 'Rechazar solicitud #' . $id_solicitud;
$bodyClass = 'proyectos-page';
include __DIR__ . '/../../layout.php';
?>