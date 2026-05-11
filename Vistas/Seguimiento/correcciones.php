<?php
/**
 * correcciones.php
 * Vista para el estudiante cuando su solicitud está en estado 'correcciones'.
 * Muestra el hilo de comentarios y le permite responder / adjuntar archivo.
 */
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();

if (!isset($_SESSION['id_usuario'])) {
    header('Location: /ITSFCP-PROYECTOS/index.php');
    exit;
}

$rol        = strtolower($_SESSION['rol'] ?? '');
$id_usuario = intval($_SESSION['id_usuario']);

// Esta vista solo la usa el estudiante
if ($rol !== 'estudiante') {
    header('Location: /ITSFCP-PROYECTOS/index.php');
    exit;
}

$id_solicitud = intval($_GET['id'] ?? 0);
if (!$id_solicitud) {
    header('Location: index.php');
    exit;
}

require_once '../../Controladores/solicitudesControlador.php';
$ctrl   = new solicitudesControlador();

// Verificar que esta solicitud pertenece al estudiante
require_once '../../publico/config/conexion.php';
require_once '../../Modelos/solicitudes.php';
$modelo = new Solicitud($conn);

$detalle     = $modelo->obtenerDetalle($id_solicitud);
$comentarios = $modelo->obtenerComentarios($id_solicitud);

// Verificar que la solicitud es del estudiante
if (!$detalle || (int)$detalle['id_estudiante'] !== $id_usuario) {
    header('Location: index.php');
    exit;
}

$estados_activos = ['correcciones']; // Solo puede responder si está en correcciones
$puede_responder = in_array($detalle['estado'], $estados_activos, true);

ob_start();
?>


<div class="container-fluid py-4" style="max-width:95%;">
<div class="hilo-wrap">

    <!-- Regresar -->
    <div class="d-flex align-items-center mb-4 gap-3">
        <a href="index.php" class="btn btn-secondary">
            <i class="bi bi-arrow-left"></i> Regresar
        </a>
        <h4 class="mb-0">Solicitud #<?= $id_solicitud ?></h4>
        <span class="estado-pill est-<?= $detalle['estado'] ?>">
            <?= match($detalle['estado']) {
                'pendiente'    => 'Pendiente',
                'en_revision'  => 'En revisión',
                'correcciones' => 'Correcciones solicitadas',
                'aceptado'     => 'Aceptada',
                'rechazado'    => 'Rechazada',
                default        => $detalle['estado'],
            } ?>
        </span>
    </div>

    <!-- Info del proyecto -->
    <div class="card border-0 bg-light mb-4 p-3">
        <div class="row g-2">
            <div class="col-md-8">
                <div class="fw-semibold"><?= htmlspecialchars($detalle['proyecto_titulo']) ?></div>
                <div class="text-muted small">Solicitud enviada el <?= $detalle['fecha_envio'] ?></div>
            </div>
            <div class="col-md-4 text-md-end">
                <?php if ($detalle['carta_nombre']): ?>
                    <a href="/ITSFCP-PROYECTOS/<?= htmlspecialchars($detalle['carta_ruta']) ?>"
                       target="_blank" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-paperclip me-1"></i>Ver carta adjunta
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Hilo de comentarios -->
    <div class="mb-4" id="hiloComentarios">
        <?php if (empty($comentarios)): ?>
            <p class="text-muted text-center py-3">Sin comentarios aún.</p>
        <?php else: ?>
            <?php foreach ($comentarios as $c): ?>
                <div class="msg-burbuja <?= $c['tipo'] === 'investigador' ? 'msg-inv' : 'msg-est' ?>">
                    <div><?= nl2br(htmlspecialchars($c['comentario'])) ?></div>
                    <?php if ($c['archivo_nombre']): ?>
                        <div class="mt-1">
                            <a href="/ITSFCP-PROYECTOS/<?= htmlspecialchars($c['archivo_ruta']) ?>"
                               target="_blank" class="small text-primary">
                                <i class="bi bi-paperclip me-1"></i><?= htmlspecialchars($c['archivo_nombre']) ?>
                            </a>
                        </div>
                    <?php endif; ?>
                    <div class="msg-meta">
                        <strong><?= htmlspecialchars($c['autor_nombre']) ?></strong>
                        · <?= $c['tipo'] === 'investigador' ? 'Investigador' : 'Tú' ?>
                        · <?= $c['fecha'] ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Formulario de respuesta (solo si puede responder) -->
    <?php if ($puede_responder): ?>
        <div class="card border shadow-sm">
            <div class="card-header bg-white">
                <h6 class="mb-0"><i class="bi bi-reply-fill me-2 text-success"></i>Enviar correcciones al investigador</h6>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label fw-medium">Explicación de correcciones <span class="text-danger">*</span></label>
                    <textarea id="txtComentario" class="form-control" rows="4"
                              placeholder="Describe qué corregiste o ajustaste en tu solicitud…"></textarea>
                    <div class="form-text">El investigador recibirá este mensaje y revisará tu solicitud nuevamente.</div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-medium">
                        Adjuntar documento actualizado
                        <span class="text-muted small">(opcional — PDF, DOCX, imagen)</span>
                    </label>
                    <input type="file" id="fileArchivo" class="form-control" accept=".pdf,.docx,.png,.jpg">
                </div>
                <div id="mensajeEnvio" class="alert d-none mb-3"></div>
                <div class="d-flex justify-content-end gap-2">
                    <a href="index.php" class="btn btn-outline-secondary btn-sm">Cancelar</a>
                    <button type="button" class="btn btn-success btn-sm" id="btnEnviarCorrecciones">
                        <i class="bi bi-send-fill me-1"></i>Enviar correcciones
                        <span class="spinner-border spinner-border-sm d-none ms-1" id="spinnerEnvio"></span>
                    </button>
                </div>
            </div>
        </div>

        <script>
        document.getElementById('btnEnviarCorrecciones').addEventListener('click', function () {
            const comentario = document.getElementById('txtComentario').value.trim();
            const archivo    = document.getElementById('fileArchivo').files[0] || null;
            const mensajeEl  = document.getElementById('mensajeEnvio');
            const spinner    = document.getElementById('spinnerEnvio');

            if (!comentario) {
                mensajeEl.textContent = 'Por favor escribe una explicación de las correcciones.';
                mensajeEl.className   = 'alert alert-warning mb-3';
                return;
            }

            this.disabled = true;
            spinner.classList.remove('d-none');
            mensajeEl.className = 'alert d-none mb-3';

            const fd = new FormData();
            fd.append('id_solicitud', <?= $id_solicitud ?>);
            fd.append('comentario',   comentario);
            if (archivo) fd.append('archivo', archivo);

            fetch('/ITSFCP-PROYECTOS/Ajax/solicitudesAjax.php?action=enviarCorrecciones', {
                method: 'POST', body: fd
            })
            .then(r => r.json())
            .then(data => {
                if (data.ok) {
                    mensajeEl.textContent = '✓ Correcciones enviadas. El investigador será notificado.';
                    mensajeEl.className   = 'alert alert-success mb-3';
                    document.getElementById('txtComentario').value = '';
                    document.getElementById('fileArchivo').value   = '';
                    // Recargar hilo después de 1.5s
                    setTimeout(() => location.reload(), 1500);
                } else {
                    mensajeEl.textContent = data.msg || 'Error al enviar.';
                    mensajeEl.className   = 'alert alert-danger mb-3';
                    this.disabled = false;
                }
            })
            .catch(e => {
                mensajeEl.textContent = 'Error de conexión: ' + e.message;
                mensajeEl.className   = 'alert alert-danger mb-3';
                this.disabled = false;
            })
            .finally(() => spinner.classList.add('d-none'));
        });
        </script>

    <?php elseif ($detalle['estado'] === 'aceptado'): ?>
        <div class="alert alert-success text-center">
            <i class="bi bi-check-circle-fill me-2"></i>
            Tu solicitud fue <strong>aceptada</strong>. Ya formas parte del proyecto.
            <br>
            <a href="../Seguimiento/seguimiento.php" class="btn btn-sm btn-success mt-2">
                Ver mi seguimiento de documentación →
            </a>
        </div>
    <?php elseif ($detalle['estado'] === 'rechazado'): ?>
        <div class="alert alert-danger text-center">
            <i class="bi bi-ban me-2"></i>
            Tu solicitud fue <strong>rechazada definitivamente</strong>.
            Puedes postularte a otro proyecto disponible.
        </div>
    <?php else: ?>
        <div class="alert alert-info text-center">
            <i class="bi bi-clock me-2"></i>
            Tu solicitud está siendo revisada. Recibirás una respuesta próximamente.
        </div>
    <?php endif; ?>

</div>
</div>

<?php
$contenido = ob_get_clean();
$titulo    = 'Correcciones de solicitud';
$bodyClass = 'proyectos-page';
include __DIR__ . '/../../layout.php';
?>
