<?php
// Vistas/solicitudes/accion_cierre.php
// Formulario dedicado para aprobar, pedir correcciones o rechazar el cierre de proyecto (etapa 3).
// Sigue el mismo patrón que accion_solicitud.php.

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

//Solo el investigador puede acceder
if (!in_array($rol, ['investigador', 'profesor'], true)) {
    header("Location: /ITSFCP-PROYECTOS/Vistas/Principal/index.php");
    exit;
}

$id_seg   = intval($_GET['id_seg']  ?? 0);

//Validación de argumentos en url
$id_validar = $id_seg;
require_once '../../../publico/incluido/_validar_id.php';


$id_sol   = intval($_GET['id_sol']  ?? 0);

//Validación de argumentos en url
$id_validar = $id_sol;
require_once '../../../publico/incluido/_validar_id.php';

$estado   = $_GET['estado']         ?? ''; // 'completado' | 'correcciones' | 'rechazado'

//Validación de argumentos en url
$id_validar = $estado;
require_once '../../../publico/incluido/_validar_id.php';


if (!$id_seg || !$id_sol || !in_array($estado, ['completado', 'correcciones', 'rechazado'], true)) {
    header("Location: index.php");
    exit;
}

require_once '../../Controladores/solicitudesControlador.php';
$ctrl = new solicitudesControlador();

//  Procesar POST index.php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ctrl->responderCierre($id_seg, $id_sol, $id_usuario, $rol);
    // responderCierre() redirige internamente
}

//  Datos de contexto index.php
$data = $ctrl->detallePagina($id_sol, $id_usuario, $rol);

// Validación
$registro = $data;
require_once '../../../publico/incluido/_validar_datos.php';

$sol  = $data['solicitud'];

// Validación
$registro = $sol;
require_once '../../../publico/incluido/_validar_datos.php';


$msg_err = htmlspecialchars($_GET['error'] ?? '');

$cfg = match ($estado) {
    'completado'   => [
        'titulo'      => 'Aprobar cierre del proyecto',
        'card_header' => 'bg-success text-white',
        'icono'       => 'bi-check-circle-fill',
        'btn_clase'   => 'btn-success',
        'btn_texto'   => 'Confirmar aprobación',
        'alerta'      => '',
        'placeholder' => 'Comentario de aprobación (opcional)…',
        'requerido'   => false,
    ],
    'correcciones' => [
        'titulo'      => 'Pedir correcciones al cierre',
        'card_header' => 'bg-warning',
        'icono'       => 'bi-pencil-fill',
        'btn_clase'   => 'btn-warning',
        'btn_texto'   => 'Enviar correcciones',
        'alerta'      => 'El estudiante recibirá este mensaje y deberá corregir los documentos de cierre.',
        'placeholder' => 'Indica qué debe corregir el estudiante en los documentos de cierre…',
        'requerido'   => true,
    ],
    'rechazado'    => [
        'titulo'      => 'Rechazar cierre del proyecto',
        'card_header' => 'bg-danger text-white',
        'icono'       => 'bi-ban',
        'btn_clase'   => 'btn-danger',
        'btn_texto'   => 'Confirmar rechazo',
        'alerta'      => '<strong>Atención:</strong> El cierre será rechazado. El estudiante recibirá la notificación con el motivo.',
        'placeholder' => 'Describe el motivo del rechazo del cierre…',
        'requerido'   => true,
    ],
};

ob_start();
include __DIR__ . '/../../mensaje.php';
?>

<div class="container-fluid py-4 ancho_container">

    <!-- CABECERA -->
    <div class="row mb-3 align-items-center">
        <?php
        $titulo      = 'Acción de Cierre <?= $cfg["titulo"] ?>';
        $descripcion = 'Resolución del supervisor al investigador';
        include __DIR__ . '../../../publico/incluido/_encabezado.php';
        ?>
        <div class="col-md-6 text-md-end">
            <a href="detalles_solicitud.php?id=<?= $id_sol ?>" class="btn btn-secondary btn-sm">
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

    <!-- CONTEXTO -->
    <div class="card mb-4 shadow-sm border-0">
        <div class="card-body">
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
    </div>

    <!-- FORMULARIO -->
    <div class="card shadow-sm">
        <div class="card-header fw-semibold <?= $cfg['card_header'] ?>">
            <i class="bi <?= $cfg['icono'] ?> me-2"></i><?= $cfg['titulo'] ?>
        </div>
        <div class="card-body">

            <?php if ($cfg['alerta']): ?>
                <div class="alert alert-<?= $estado === 'correcciones' ? 'info' : 'warning' ?> d-flex align-items-start gap-2 mb-4">
                    <i class="bi bi-<?= $estado === 'correcciones' ? 'info-circle-fill' : 'exclamation-triangle-fill' ?> fs-5 mt-1 flex-shrink-0"></i>
                    <div><?= $cfg['alerta'] ?></div>
                </div>
            <?php endif; ?>

            <form method="POST"
                action="accion_cierre.php?id_seg=<?= $id_seg ?>&id_sol=<?= $id_sol ?>&estado=<?= $estado ?>">

                <div class="mb-4">
                    <label for="comentario" class="form-label fw-semibold">
                        Comentario
                        <?php if ($cfg['requerido']): ?>
                            <span class="text-danger">*</span>
                        <?php else: ?>
                            <span class="text-muted fw-normal small">(opcional)</span>
                        <?php endif; ?>
                    </label>
                    <textarea class="form-control"
                        name="comentario"
                        id="comentario"
                        rows="5"
                        maxlength="1000"
                        placeholder="<?= $cfg['placeholder'] ?>"
                        <?= $cfg['requerido'] ? 'required' : '' ?>></textarea>
                    <div class="form-text text-muted">Máximo 1 000 caracteres.</div>
                </div>

                <!-- Campos ocultos -->
                <input type="hidden" name="id_seguimiento" value="<?= $id_seg ?>">
                <input type="hidden" name="id_solicitud" value="<?= $id_sol ?>">
                <input type="hidden" name="estado" value="<?= $estado ?>">

                <hr>

                <div class="d-flex gap-3 justify-content-end flex-wrap">
                    <a href="detalles_solicitud.php?id=<?= $id_sol ?>" class="btn btn-secondary">
                        <i class="bi bi-arrow-left"></i> Cancelar
                    </a>
                    <button type="submit" class="btn <?= $cfg['btn_clase'] ?>">
                        <i class="bi <?= $cfg['icono'] ?> me-1"></i><?= $cfg['btn_texto'] ?>
                    </button>
                </div>

            </form>
        </div>
    </div>

</div>

<?php
$contenido = ob_get_clean();
$titulo    = $cfg['titulo'];
$bodyClass = 'proyectos-page';
include __DIR__ . '/../../layout.php';
?>