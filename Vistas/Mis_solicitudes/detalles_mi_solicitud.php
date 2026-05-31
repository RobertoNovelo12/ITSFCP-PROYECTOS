<?php
// Vistas/Mis_solicitudes/detalles_mi_solicitud.php
// Detalle de una solicitud individual del estudiante.
// Incluye datos de la solicitud, carta compromiso, historial de comentarios
// y formulario de respuesta si el estado es "correcciones".

ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();

if (!isset($_SESSION['id_usuario'])) {
    header("Location: /ITSFCP-PROYECTOS/index.php");
    exit;
}

$rol        = strtolower($_SESSION['rol'] ?? '');
$id_usuario = (int)$_SESSION['id_usuario'];

if ($rol !== 'estudiante') {
    header("Location: /ITSFCP-PROYECTOS/Vistas/Principal/index.php");
    exit;
}

$id_solicitud = (int)($_GET['id'] ?? 0);

require_once __DIR__ .  '/../../Controladores/misSolicitudesControlador.php';
$ctrl = new MisSolicitudesControlador();

//  POST: responder correcciones 
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'responder') {
    $resultado = $ctrl->procesarRespuesta($_POST, $_FILES, $id_usuario);
    if ($resultado['ok']) {
        header("Location: detalles_mi_solicitud.php?id={$id_solicitud}&msg=enviado");
    } else {
        header("Location: detalles_mi_solicitud.php?id={$id_solicitud}&msg=error&detalle=" . urlencode($resultado['mensaje']));
    }
    exit;
}

//  Datos ─
$data      = $ctrl->detallePagina($id_solicitud, $id_usuario);
$sol       = $data['solicitud'];
$hilo      = $data['hilo'];


$es_correcciones = ($sol['estado'] === 'correcciones');
$ya_respondio    = (int)($sol['ya_respondio'] ?? 0) > 0;

// Flash
$msg_ok  = $_GET['msg'] ?? '';
$detalle = htmlspecialchars($_GET['detalle'] ?? '');

function formatBytes(int $bytes): string {
    if ($bytes >= 1048576) return round($bytes / 1048576, 1) . ' MB';
    if ($bytes >= 1024)    return round($bytes / 1024, 1)    . ' KB';
    return $bytes . ' B';
}

$titulo    = 'Detalle de solicitud #' . $id_solicitud;
$bodyClass = 'proyectos-page';

ob_start();
include __DIR__ . '/../../mensaje.php';
?>

<!-- 
     CONTENIDO
═ -->
<div class="container-fluid py-4"  style="max-width:95%;">

<div class="dms-page">

    <!-- NAVEGACIÓN -->
        <div class="row mb-4 align-items-center">
        <div class="col">
            <h3 class="fw-bold mb-0">Detalle de solicitud #<?= $id_solicitud ?></h3>
        </div>
        <div class="col-auto">
            <a href="index.php" class="btn btn-secondary btn-sm">
                <i class="bi bi-arrow-left"></i> Regresar
            </a>
        </div>
    </div>

    <!-- ENCABEZADO -->
    <div class="dms-header">
        <div>
            <h2><?= htmlspecialchars($sol['proyecto_titulo']) ?></h2>
            <p>Solicitud #<?= $id_solicitud ?> &mdash; Enviada el <?= $sol['fecha_envio'] ?></p>
        </div>
        <div style="display:flex;align-items:center;gap:.75rem;flex-wrap:wrap;">
            <?= $ctrl->badgeEstado($sol['estado']) ?>
            <?php if ($sol['estado'] === 'correcciones' && !$ya_respondio): ?>
                <a href="#form-responder" class="badge badge-correcciones" style="cursor:pointer;text-decoration:none;">
                    <i class="bi bi-reply-fill"></i> Responder correcciones
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- FLASH -->
    <?php if ($msg_ok === 'enviado'): ?>
        <div class="dms-flash exito">
            <i class="bi bi-check-circle-fill"></i>
            <span>Tu respuesta fue enviada. El investigador la revisará.</span>
        </div>
    <?php elseif ($msg_ok === 'error'): ?>
        <div class="dms-flash peligro">
            <i class="bi bi-x-circle-fill"></i>
            <span><?= $detalle ?: 'Ocurrió un error. Intenta de nuevo.' ?></span>
        </div>
    <?php endif; ?>

    <!-- NOTA ESTADO ESPECIAL -->
    <?php if ($sol['estado'] === 'aceptado'): ?>
        <div class="dms-nota exito">
            <i class="bi bi-check-circle-fill"></i>
            <div>
                <strong>¡Integración aceptada!</strong>
                Fuiste aceptado en este proyecto. Revisa tu panel de actividades para continuar.
            </div>
        </div>
    <?php elseif ($sol['estado'] === 'rechazado' && !empty($sol['motivo_rechazo'])): ?>
        <div class="dms-nota peligro">
            <i class="bi bi-x-circle-fill"></i>
            <div>
                <strong>Motivo de rechazo</strong>
                <?= nl2br(htmlspecialchars($sol['motivo_rechazo'])) ?>
            </div>
        </div>
    <?php elseif ($sol['estado'] === 'correcciones' && $ya_respondio): ?>
        <div class="dms-nota info">
            <i class="bi bi-clock-history"></i>
            <div>Ya enviaste tu respuesta a las correcciones. Esperando revisión del investigador.</div>
        </div>
    <?php endif; ?>

    <!--  TARJETA: DATOS DEL PROYECTO  -->
    <div class="dms-card">
        <div class="dms-card-header">
            <i class="bi bi-folder2-open"></i> Datos de la solicitud
        </div>
        <div class="dms-card-body">
            <div class="dms-datos-grid">
                <dl class="dms-campo">
                    <dt>Proyecto</dt>
                    <dd><?= htmlspecialchars($sol['proyecto_titulo']) ?></dd>
                </dl>
                <dl class="dms-campo">
                    <dt>Modalidad</dt>
                    <dd><?= ucfirst(htmlspecialchars($sol['modalidad'] ?? '—')) ?></dd>
                </dl>
                <dl class="dms-campo">
                    <dt>Investigador</dt>
                    <dd><?= htmlspecialchars($sol['investigador']) ?></dd>
                </dl>
                <dl class="dms-campo">
                    <dt>Correo investigador</dt>
                    <dd><?= htmlspecialchars($sol['email_investigador'] ?? '—') ?></dd>
                </dl>
                <dl class="dms-campo">
                    <dt>Periodo</dt>
                    <dd><?= htmlspecialchars($sol['periodo']) ?></dd>
                </dl>
                <dl class="dms-campo">
                    <dt>Fecha de envío</dt>
                    <dd><?= $sol['fecha_envio'] ?></dd>
                </dl>
                <?php if ($sol['fecha_respuesta']): ?>
                <dl class="dms-campo">
                    <dt>Fecha de respuesta</dt>
                    <dd><?= $sol['fecha_respuesta'] ?></dd>
                </dl>
                <?php endif; ?>
                <dl class="dms-campo">
                    <dt>Estado</dt>
                    <dd><?= $ctrl->badgeEstado($sol['estado']) ?></dd>
                </dl>
                <dl class="dms-campo">
                    <dt>Semestre</dt>
                    <dd><?= $sol['semestre'] ? $sol['semestre'] . '°' : '—' ?></dd>
                </dl>
                <dl class="dms-campo">
                    <dt>Promedio</dt>
                    <dd><?= $sol['promedio'] ?? '—' ?></dd>
                </dl>
            </div>
        </div>
    </div>

    <!--  CARTA COMPROMISO  -->
    <?php if (!empty($sol['carta_ruta'])): ?>
        <div class="dms-card">
            <div class="dms-card-header">
                <i class="bi bi-file-earmark-check"></i> Carta compromiso
            </div>
            <div class="dms-card-body">
                <a href="/ITSFCP-PROYECTOS/<?= htmlspecialchars($sol['carta_ruta']) ?>"
                   target="_blank" class="dms-carta-pill">
                    <i class="bi bi-file-earmark-pdf-fill"></i>
                    <?= htmlspecialchars($sol['carta_nombre'] ?? 'Descargar carta') ?>
                    <?php if (!empty($sol['carta_extension'])): ?>
                        <span style="opacity:.7;">.<?= htmlspecialchars($sol['carta_extension']) ?></span>
                    <?php endif; ?>
                    <i class="bi bi-box-arrow-up-right" style="font-size:.75rem;"></i>
                </a>
            </div>
        </div>
    <?php endif; ?>

    <!--  MOTIVACIÓN Y EXPERIENCIA  -->
    <div class="dms-card">
        <div class="dms-card-header">
            <i class="bi bi-person-lines-fill"></i> Información enviada
        </div>
        <div class="dms-card-body">
            <div class="dms-datos-grid">
                <div>
                    <div class="dms-label" style="margin-bottom:.5rem;">Motivación</div>
                    <div class="dms-texto-campo">
                        <?= nl2br(htmlspecialchars($sol['motivacion'] ?? 'Sin información')) ?>
                    </div>
                </div>
                <div>
                    <div class="dms-label" style="margin-bottom:.5rem;">Experiencia</div>
                    <div class="dms-texto-campo">
                        <?= nl2br(htmlspecialchars($sol['experiencia'] ?? 'Sin información')) ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!--  HILO DE COMENTARIOS  -->
    <div class="dms-card">
        <div class="dms-card-header">
            <i class="bi bi-chat-dots-fill"></i> Historial de comunicación
            <?php if (!empty($hilo)): ?>
                <span style="margin-left:auto;font-size:.75rem;font-weight:500;opacity:.85;">
                    <?= count($hilo) ?> mensaje<?= count($hilo) !== 1 ? 's' : '' ?>
                </span>
            <?php endif; ?>
        </div>
        <div class="dms-card-body">
            <?php if (!empty($hilo)): ?>
                <div class="dms-hilo">
                    <?php foreach ($hilo as $msg): ?>
                        <div class="dms-burbuja <?= $msg['tipo'] === 'investigador' ? 'inv' : 'est' ?>">
                            <div class="dms-burbuja-meta">
                                <span class="dms-burbuja-autor">
                                    <?= $msg['tipo'] === 'investigador'
                                        ? '<i class="bi bi-person-badge"></i> Investigador'
                                        : '<i class="bi bi-person-fill"></i> Tú' ?>
                                </span>
                                <span class="dms-burbuja-fecha">
                                    <i class="bi bi-clock" style="font-size:.7rem;"></i>
                                    <?= htmlspecialchars($msg['fecha']) ?>
                                </span>
                            </div>
                            <p><?= nl2br(htmlspecialchars($msg['comentario'])) ?></p>
                            <?php if (!empty($msg['doc_nombre'])): ?>
                                <a href="/ITSFCP-PROYECTOS/<?= htmlspecialchars($msg['doc_ruta']) ?>"
                                   target="_blank" class="dms-adjunto-pill">
                                    <i class="bi bi-paperclip"></i>
                                    <?= htmlspecialchars($msg['doc_nombre']) ?>
                                    <?php if (!empty($msg['doc_extension'])): ?>
                                        <span style="opacity:.65;">
                                            .<?= strtoupper(htmlspecialchars($msg['doc_extension'])) ?>
                                            <?php if (!empty($msg['doc_tamano'])): ?>
                                                · <?= formatBytes((int)$msg['doc_tamano']) ?>
                                            <?php endif; ?>
                                        </span>
                                    <?php endif; ?>
                                    <i class="bi bi-box-arrow-up-right" style="font-size:.68rem;"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="dms-hilo-vacio">
                    <i class="bi bi-chat-square-dots"></i>
                    <p>Sin comentarios aún. Los mensajes del investigador aparecerán aquí.</p>
                </div>
            <?php endif; ?>
        </div>

        <!--  FORMULARIO RESPUESTA (solo si estado = correcciones y no respondió)  -->
        <?php if ($es_correcciones && !$ya_respondio): ?>
            <div id="form-responder"
                 style="border-top: 2px solid #d4a017; background: var(--badge-porcerrar-bg);">
                <div style="padding: .7rem 1.1rem; display:flex; align-items:center; gap:.5rem;
                            font-size:.82rem; font-weight:700; color: #a87a10; text-transform:uppercase; letter-spacing:.4px;">
                    <i class="bi bi-reply-fill"></i> Responder correcciones
                </div>

                <div class="dms-nota advertencia" style="margin: 0 1rem .75rem; font-size:.835rem;">
                    <i class="bi bi-info-circle-fill"></i>
                    <div>
                        Al enviar tu respuesta, la solicitud volverá a estado <strong>En revisión</strong>
                        para que el investigador pueda evaluarla nuevamente.
                    </div>
                </div>

                <form method="POST"
                      action="detalles_mi_solicitud.php?id=<?= $id_solicitud ?>"
                      enctype="multipart/form-data"
                      class="dms-form-resp">
                    <input type="hidden" name="accion"       value="responder">
                    <input type="hidden" name="id_solicitud" value="<?= $id_solicitud ?>">

                    <div style="margin-bottom:.9rem;">
                        <label class="dms-label" for="comentario">
                            Tu respuesta <span style="color:var(--color-rojo-institucional);">*</span>
                        </label>
                        <textarea class="dms-textarea" id="comentario" name="comentario"
                                  rows="5" maxlength="1000"
                                  placeholder="Describe los cambios realizados o responde al comentario del investigador…"
                                  required></textarea>
                        <div class="dms-hint">Máximo 1,000 caracteres.</div>
                    </div>

                    <div style="margin-bottom:1.1rem;">
                        <label class="dms-label" for="adjunto">
                            Documento adjunto
                            <span style="font-weight:400;color:var(--color-texto-secundario);">(opcional — PDF, DOCX, imagen — máx. 5 MB)</span>
                        </label>
                        <input type="file" class="dms-file-input" id="adjunto" name="adjunto"
                               accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                    </div>

                    <div style="display:flex;gap:.65rem;justify-content:flex-end;flex-wrap:wrap;">
                        <a href="index.php" class="dms-btn-volver">
                            <i class="bi bi-x"></i> Cancelar
                        </a>
                        <button type="submit" class="dms-btn-enviar">
                            <i class="bi bi-send-fill"></i> Enviar respuesta
                        </button>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>

</div>
</div>
<?php
$contenido = ob_get_clean();
$titulo    = "Detalles de la solicitud";
include __DIR__ . '/../../layout.php';
?>