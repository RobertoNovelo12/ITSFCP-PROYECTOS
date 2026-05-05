<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();

if (!isset($_SESSION['id_usuario'])) {
    header("Location: /ITSFCP-PROYECTOS/index.php");
    exit;
}

$rol        = strtolower($_SESSION['rol'] ?? '');
$id_usuario = intval($_SESSION['id_usuario']);

if (!in_array($rol, ['investigador', 'profesor'], true)) {
    header("Location: /ITSFCP-PROYECTOS/index.php");
    exit;
}

require_once '../../Controladores/solicitudesControlador.php';

$ctrl         = new solicitudesControlador();
$id_solicitud = intval($_GET['id'] ?? 0);

if (!$id_solicitud) {
    header("Location: tabla.php");
    exit;
}

$data        = $ctrl->detallePagina($id_solicitud, $id_usuario, $rol);
$sol         = $data['solicitud'];
$comentarios = $data['comentarios'];

$id_proyecto   = intval($sol['id_proyectos']);
$id_estudiante = intval($sol['id_estudiante']);

// Datos de seguimiento (etapas + documentos)
$seg           = $ctrl->getDatosSeguimientoEstudiante($id_proyecto, $id_estudiante, $id_usuario);
$e1_estado     = $seg['e1_estado'];
$e2_estado     = $seg['e2_estado'];
$e3_estado     = $seg['e3_estado'];
$fase2_ok      = $seg['fase2_ok'];
$id_seg_cierre = $seg['id_seguimiento_cierre'];
$documentos    = $seg['documentos'];

function badgeSeg(string $estado): string {
    $map = [
        'pendiente'    => ['bg-secondary',           'Pendiente'],
        'proceso'      => ['bg-primary',              'En revisión'],
        'completado'   => ['bg-success',              'Aprobado'],
        'rechazado'    => ['bg-danger',               'Rechazado'],
        'correcciones' => ['bg-warning text-dark',    'Correcciones'],
        'aceptado'     => ['bg-success',              'Aceptado'],
        'en_revision'  => ['bg-info text-dark',       'En revisión'],
    ];
    [$cls, $txt] = $map[$estado] ?? ['bg-secondary', $estado];
    return "<span class='badge {$cls}'>{$txt}</span>";
}

ob_start();
?>

<div class="container-fluid py-4">

    <!-- ENCABEZADO -->
    <div class="row mb-4 align-items-center">
        <div class="col">
            <h3 class="fw-bold mb-0">Detalle de solicitud #<?= $id_solicitud ?></h3>
        </div>
        <div class="col-auto">
            <a href="tabla.php" class="btn btn-secondary btn-sm">
                <i class="bi bi-arrow-left"></i> Regresar
            </a>
        </div>
    </div>

    <!-- ══ PANEL DE 3 ETAPAS ══════════════════════════════════════ -->
    <div class="card shadow-sm mb-4 border-primary">
        <div class="card-header text-white">
            <h5 class="mb-0"><i class="bi bi-diagram-3 me-2"></i>Seguimiento del estudiante</h5>
        </div>
        <div class="card-body p-0">
            <div class="row g-0">

                <!-- ETAPA 1 -->
                <div class="col-md-4 border-end p-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <strong class="small">1. Solicitud de integración</strong>
                        <?= badgeSeg($e1_estado) ?>
                    </div>
                    <p class="text-muted small mb-2">Carta Compromiso firmada y aceptación del investigador.</p>

                    <?php if (in_array($e1_estado, ['pendiente','en_revision','correcciones','proceso'])): ?>
                        <div class="d-flex gap-1 flex-wrap mt-2">
                            <button class="btn btn-success btn-sm"
                                    onclick="confirmarAceptar(<?= $id_solicitud ?>)">
                                <i class="bi bi-check-lg"></i> Aceptar
                            </button>
                            <button class="btn btn-warning btn-sm"
                                    onclick="abrirModalAccion(<?= $id_solicitud ?>,'correcciones')">
                                <i class="bi bi-pencil"></i> Correcciones
                            </button>
                            <button class="btn btn-danger btn-sm"
                                    onclick="abrirModalAccion(<?= $id_solicitud ?>,'rechazar')">
                                <i class="bi bi-x-lg"></i> Rechazar
                            </button>
                        </div>
                    <?php elseif ($e1_estado === 'aceptado'): ?>
                        <div class="alert alert-success py-1 px-2 mt-2 small mb-0">
                            <i class="bi bi-check-circle-fill"></i> Integración aceptada.
                        </div>
                    <?php elseif ($e1_estado === 'rechazado'): ?>
                        <div class="alert alert-danger py-1 px-2 mt-2 small mb-0">
                            <i class="bi bi-ban"></i> Solicitud rechazada.
                        </div>
                    <?php endif; ?>
                </div>

                <!-- ETAPA 2 -->
                <div class="col-md-4 border-end p-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <strong class="small">2. Desarrollo del documento</strong>
                        <?= badgeSeg($e2_estado) ?>
                    </div>
                    <p class="text-muted small mb-1">
                        Secciones completadas y aprobadas automáticamente por el sistema.
                    </p>
                    <div class="text-muted small mb-2">
                        <?= $seg['actividades_aprobadas'] ?> / <?= $seg['actividades_total'] ?> actividades aprobadas
                    </div>
                    <?php if ($fase2_ok): ?>
                        <div class="alert alert-success py-1 px-2 small mb-0">
                            <i class="bi bi-check-circle-fill"></i> Todas las secciones aprobadas.
                            La carta de terminación está disponible.
                        </div>
                    <?php else: ?>
                        <div class="prog-track" style="height:6px;background:#e9ecef;border-radius:4px;overflow:hidden">
                            <div style="height:100%;background:#198754;border-radius:4px;width:<?=
                                $seg['actividades_total'] > 0
                                ? round(($seg['actividades_aprobadas'] / $seg['actividades_total']) * 100)
                                : 0 ?>%"></div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- ETAPA 3 -->
                <div class="col-md-4 p-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <strong class="small">3. Cierre del proyecto</strong>
                        <?= badgeSeg($e3_estado) ?>
                    </div>
                    <p class="text-muted small mb-2">Reporte Final aprobado y Carta de Terminación.</p>

                    <?php if ($fase2_ok && $id_seg_cierre && in_array($e3_estado, ['pendiente','proceso','correcciones'])): ?>
                        <div class="d-flex gap-1 flex-wrap mt-2">
                            <button class="btn btn-success btn-sm"
                                    onclick="responderCierre(<?= $id_seg_cierre ?>,'completado')">
                                <i class="bi bi-check-lg"></i> Aprobar
                            </button>
                            <button class="btn btn-warning btn-sm"
                                    onclick="responderCierre(<?= $id_seg_cierre ?>,'correcciones')">
                                <i class="bi bi-pencil"></i> Correcciones
                            </button>
                            <button class="btn btn-danger btn-sm"
                                    onclick="responderCierre(<?= $id_seg_cierre ?>,'rechazado')">
                                <i class="bi bi-x-lg"></i> Rechazar
                            </button>
                        </div>
                    <?php elseif (!$fase2_ok): ?>
                        <div class="text-muted small">
                            <i class="bi bi-lock"></i> Disponible al completar el desarrollo.
                        </div>
                    <?php elseif ($e3_estado === 'completado'): ?>
                        <div class="alert alert-success py-1 px-2 mt-2 small mb-0">
                            <i class="bi bi-check-circle-fill"></i> Cierre aprobado. Proyecto finalizado.
                        </div>
                    <?php endif; ?>
                </div>

            </div>
        </div>
    </div>

    <!-- ══ DOCUMENTOS DEL ESTUDIANTE ══════════════════════════════ -->
    <?php if (!empty($documentos)): ?>
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-light">
            <h5 class="mb-0"><i class="bi bi-paperclip me-2"></i>Documentos subidos por el estudiante</h5>
        </div>
        <div class="card-body">
            <div class="d-flex flex-wrap gap-2">
                <?php foreach ($documentos as $doc): ?>
                    <a href="/ITSFCP-PROYECTOS/<?= htmlspecialchars($doc['ruta']) ?>"
                       target="_blank"
                       class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-file-earmark-text me-1"></i>
                        <?= htmlspecialchars($doc['nombre']) ?>
                        <?php if (!empty($doc['tipo_nombre'])): ?>
                            <span class="text-muted small">(<?= htmlspecialchars($doc['tipo_nombre']) ?>)</span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ══ CARTA COMPROMISO (adjunta a la solicitud) ══════════════ -->
    <?php if (!empty($sol['carta_ruta'])): ?>
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-light">
            <h5 class="mb-0"><i class="bi bi-file-earmark-check me-2"></i>Carta compromiso</h5>
        </div>
        <div class="card-body">
            <a href="/ITSFCP-PROYECTOS/<?= htmlspecialchars($sol['carta_ruta']) ?>"
               target="_blank" class="btn btn-outline-primary">
                <i class="bi bi-download me-1"></i>
                <?= htmlspecialchars($sol['carta_nombre'] ?? 'Descargar carta') ?>
                <?php if (!empty($sol['carta_extension'])): ?>
                    <span class="text-muted small">(.<?= htmlspecialchars($sol['carta_extension']) ?>)</span>
                <?php endif; ?>
            </a>
        </div>
    </div>
    <?php endif; ?>

    <!-- ══ DATOS DEL ESTUDIANTE ════════════════════════════════════ -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-light"><h5 class="mb-0">Información del estudiante</h5></div>
        <div class="card-body">
            <div class="row">
                <?php
                $campos = [
                    'Nombre'   => $sol['estudiante_nombre'],
                    'Matrícula'=> $sol['matricula']          ?? '—',
                    'Correo'   => $sol['correo_institucional'],
                    'Carrera'  => $sol['carrera'],
                    'Semestre' => isset($sol['semestre']) ? $sol['semestre'] . '°' : '—',
                    'Promedio' => $sol['promedio']           ?? '—',
                ];
                foreach ($campos as $lbl => $val): ?>
                    <div class="col-md-6">
                        <dl class="mb-2">
                            <dt class="small text-muted"><?= $lbl ?></dt>
                            <dd><?= htmlspecialchars((string)$val) ?></dd>
                        </dl>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- ══ PROYECTO ════════════════════════════════════════════════ -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-light"><h5 class="mb-0">Proyecto solicitado</h5></div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <dl class="mb-2">
                        <dt class="small text-muted">Título</dt>
                        <dd><?= htmlspecialchars($sol['proyecto_titulo']) ?></dd>
                    </dl>
                </div>
                <div class="col-md-6">
                    <dl class="mb-2">
                        <dt class="small text-muted">Estado de la solicitud</dt>
                        <dd><?= $ctrl->badgeEstado($sol['estado']) ?></dd>
                    </dl>
                </div>
                <div class="col-md-6">
                    <dl class="mb-2">
                        <dt class="small text-muted">Fecha de solicitud</dt>
                        <dd><?= $sol['fecha_envio'] ?></dd>
                    </dl>
                </div>
                <div class="col-md-6">
                    <dl class="mb-2">
                        <dt class="small text-muted">Modalidad</dt>
                        <dd><?= htmlspecialchars($sol['modalidad'] ?? '—') ?></dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>

    <!-- ══ MOTIVACIÓN Y EXPERIENCIA ═══════════════════════════════ -->
    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-light"><h5 class="mb-0">Motivación</h5></div>
                <div class="card-body">
                    <?= nl2br(htmlspecialchars($sol['motivacion'] ?? 'Sin información')) ?>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-light"><h5 class="mb-0">Experiencia</h5></div>
                <div class="card-body">
                    <?= nl2br(htmlspecialchars($sol['experiencia'] ?? 'Sin información')) ?>
                </div>
            </div>
        </div>
    </div>

    <!-- ══ HISTORIAL DE COMENTARIOS ═══════════════════════════════ -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-light"><h5 class="mb-0">Historial de comentarios</h5></div>
        <div class="card-body">
            <?php if (!empty($comentarios)): ?>
                <?php foreach ($comentarios as $c): ?>
                    <div class="border rounded p-3 mb-2 <?= $c['tipo'] === 'investigador' ? 'bg-light' : 'bg-white' ?>">
                        <p class="mb-1"><?= nl2br(htmlspecialchars($c['comentario'])) ?></p>
                        <?php if (!empty($c['archivo_nombre'])): ?>
                            <a href="/ITSFCP-PROYECTOS/<?= htmlspecialchars($c['archivo_ruta']) ?>"
                               target="_blank" class="small text-primary">
                                <i class="bi bi-paperclip"></i> <?= htmlspecialchars($c['archivo_nombre']) ?>
                            </a>
                        <?php endif; ?>
                        <div class="text-muted small mt-1">
                            <span class="badge <?= $c['tipo'] === 'investigador' ? 'bg-primary' : 'bg-secondary' ?>">
                                <?= $c['tipo'] === 'investigador' ? 'Investigador' : 'Estudiante' ?>
                            </span>
                            <?= htmlspecialchars($c['fecha']) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="text-muted">Sin comentarios aún.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- ══ ACCIONES RÁPIDAS (si la solicitud sigue activa) ════════ -->
    <?php if (in_array($sol['estado'], ['pendiente','en_revision','correcciones'])): ?>
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-light"><h5 class="mb-0">Acciones sobre la solicitud</h5></div>
        <div class="card-body d-flex gap-2 flex-wrap">
            <button class="btn btn-success"
                    onclick="confirmarAceptar(<?= $id_solicitud ?>)">
                <i class="bi bi-check-circle-fill"></i> Aceptar
            </button>
            <button class="btn btn-warning"
                    onclick="abrirModalAccion(<?= $id_solicitud ?>,'correcciones')">
                <i class="bi bi-pencil-fill"></i> Pedir correcciones
            </button>
            <button class="btn btn-danger"
                    onclick="abrirModalAccion(<?= $id_solicitud ?>,'rechazar')">
                <i class="bi bi-ban"></i> Rechazar
            </button>
        </div>
    </div>
    <?php endif; ?>

</div>

<!-- ══ MODAL ACCIÓN ═══════════════════════════════════════════════ -->
<div class="modal fade" id="modalAccion" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" id="modalAccionHeader">
                <h5 class="modal-title" id="modalAccionTitulo">Acción</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="accionIdSolicitud">
                <input type="hidden" id="accionTipo">
                <div class="mb-3">
                    <label class="form-label fw-medium" id="labelComentario">
                        Comentario <span class="text-danger">*</span>
                    </label>
                    <textarea id="accionComentario" class="form-control" rows="4"
                              placeholder="Escribe tu comentario para el estudiante…"></textarea>
                </div>
                <div class="mb-2">
                    <label class="form-label fw-medium">
                        Archivo adjunto <span class="text-muted small">(opcional)</span>
                    </label>
                    <input type="file" id="accionArchivo" class="form-control" accept=".pdf,.docx,.png,.jpg">
                </div>
                <div id="accionMensaje" class="alert d-none mt-2"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary btn-sm" id="btnConfirmarAccion">
                    <span id="btnAccionTexto">Confirmar</span>
                    <span class="spinner-border spinner-border-sm d-none ms-1" id="spinnerAccion"></span>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ══ MODAL CIERRE ═══════════════════════════════════════════════ -->
<div class="modal fade" id="modalCierre" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" id="modalCierreHeader">
                <h5 class="modal-title" id="modalCierreTitulo">Responder cierre</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="cierreIdSeg">
                <input type="hidden" id="cierreEstado">
                <div class="mb-3">
                    <label class="form-label fw-medium">
                        Comentario
                        <span class="text-muted small">(obligatorio al rechazar o pedir correcciones)</span>
                    </label>
                    <textarea id="cierreComentario" class="form-control" rows="4"
                              placeholder="Escribe un comentario…"></textarea>
                </div>
                <div id="cierreMensaje" class="alert d-none mt-2"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary btn-sm" id="btnConfirmarCierre">Confirmar</button>
            </div>
        </div>
    </div>
</div>

<script>
'use strict';

const AJAX = '/ITSFCP-PROYECTOS/Ajax/solicitudesAjax.php';
const SEG_AJAX = '/ITSFCP-PROYECTOS/Ajax/seguimientoAjax.php';

// ── Etapa 1: solicitud ────────────────────────────────────────────
function confirmarAceptar(id) {
    if (!confirm('¿Confirmar aceptación? El estudiante quedará integrado al proyecto.')) return;

    const fd = new FormData();
    fd.append('id_solicitud', id);

    fetch(`${AJAX}?action=aceptar`, { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.ok) { alert(data.msg); location.reload(); }
            else mostrarMsg('accionMensaje', data.msg || 'Error.', 'danger');
        })
        .catch(e => alert('Error de conexión: ' + e.message));
}

function abrirModalAccion(id, tipo) {
    document.getElementById('accionIdSolicitud').value = id;
    document.getElementById('accionTipo').value        = tipo;
    document.getElementById('accionComentario').value  = '';
    document.getElementById('accionArchivo').value     = '';
    document.getElementById('accionMensaje').className = 'alert d-none mt-2';

    const esCor = tipo === 'correcciones';
    document.getElementById('modalAccionTitulo').textContent =
        esCor ? 'Solicitar correcciones' : 'Rechazar solicitud';
    document.getElementById('modalAccionHeader').className =
        'modal-header ' + (esCor ? 'bg-warning' : 'bg-danger text-white');
    document.getElementById('labelComentario').textContent =
        esCor ? 'Indica qué debe corregir el estudiante *' : 'Motivo de rechazo *';
    document.getElementById('btnAccionTexto').textContent =
        esCor ? 'Enviar correcciones' : 'Rechazar definitivamente';

    new bootstrap.Modal(document.getElementById('modalAccion')).show();
}

document.getElementById('btnConfirmarAccion').onclick = function () {
    const id         = document.getElementById('accionIdSolicitud').value;
    const tipo       = document.getElementById('accionTipo').value;
    const comentario = document.getElementById('accionComentario').value.trim();
    const archivo    = document.getElementById('accionArchivo').files[0] || null;
    const spinner    = document.getElementById('spinnerAccion');
    const btnTexto   = document.getElementById('btnAccionTexto');

    if (!comentario) { mostrarMsg('accionMensaje', 'El comentario es obligatorio.', 'danger'); return; }

    this.disabled = true;
    spinner.classList.remove('d-none');
    btnTexto.classList.add('d-none');

    const fd = new FormData();
    fd.append('id_solicitud', id);
    fd.append('comentario',   comentario);
    if (archivo) fd.append('archivo', archivo);

    const action = tipo === 'correcciones' ? 'correcciones' : 'rechazar';

    fetch(`${AJAX}?action=${action}`, { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.ok) {
                bootstrap.Modal.getInstance(document.getElementById('modalAccion'))?.hide();
                setTimeout(() => location.reload(), 300);
            } else {
                mostrarMsg('accionMensaje', data.msg || 'Error.', 'danger');
                this.disabled = false;
            }
        })
        .catch(e => { mostrarMsg('accionMensaje', 'Error: ' + e.message, 'danger'); this.disabled = false; })
        .finally(() => { spinner.classList.add('d-none'); btnTexto.classList.remove('d-none'); });
};

// ── Etapa 3: cierre ───────────────────────────────────────────────
function responderCierre(idSeg, estado) {
    document.getElementById('cierreIdSeg').value      = idSeg;
    document.getElementById('cierreEstado').value     = estado;
    document.getElementById('cierreComentario').value = '';
    document.getElementById('cierreMensaje').className = 'alert d-none mt-2';

    const cfg = {
        completado  : { titulo: 'Aprobar cierre del proyecto',   cls: 'bg-success text-white' },
        correcciones: { titulo: 'Pedir correcciones al cierre',  cls: 'bg-warning' },
        rechazado   : { titulo: 'Rechazar cierre del proyecto',  cls: 'bg-danger text-white' },
    }[estado] || { titulo: 'Responder cierre', cls: '' };

    document.getElementById('modalCierreTitulo').textContent = cfg.titulo;
    document.getElementById('modalCierreHeader').className   = 'modal-header ' + cfg.cls;

    new bootstrap.Modal(document.getElementById('modalCierre')).show();
}

document.getElementById('btnConfirmarCierre').onclick = function () {
    const idSeg     = document.getElementById('cierreIdSeg').value;
    const estado    = document.getElementById('cierreEstado').value;
    const comentario= document.getElementById('cierreComentario').value.trim();

    if (['correcciones','rechazado'].includes(estado) && !comentario) {
        mostrarMsg('cierreMensaje', 'El comentario es obligatorio.', 'danger');
        return;
    }

    const fd = new FormData();
    fd.append('id_seguimiento', idSeg);
    fd.append('estado',         estado);
    fd.append('comentario',     comentario);

    fetch(`${SEG_AJAX}?action=actualizarEstado`, { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.ok) {
                bootstrap.Modal.getInstance(document.getElementById('modalCierre'))?.hide();
                setTimeout(() => location.reload(), 300);
            } else {
                mostrarMsg('cierreMensaje', data.msg || 'Error.', 'danger');
            }
        })
        .catch(e => mostrarMsg('cierreMensaje', 'Error de conexión: ' + e.message, 'danger'));
};

function mostrarMsg(elId, msg, tipo) {
    const el = document.getElementById(elId);
    if (!el) return;
    el.textContent = msg;
    el.className   = `alert alert-${tipo} mt-2`;
}
</script>

<?php
$contenido = ob_get_clean();
$titulo    = 'Detalle de solicitud';
$bodyClass = 'proyectos-page';
include __DIR__ . '/../../layout.php';
?>
