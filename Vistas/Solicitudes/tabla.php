<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

if (!isset($_SESSION['id_usuario'])) {
    header('Location: /ITSFCP-PROYECTOS/index.php');
    exit;
}

$rol        = strtolower($_SESSION['rol'] ?? '');
$id_usuario = intval($_SESSION['id_usuario']);

// Solo investigadores
if (!in_array($rol, ['investigador', 'profesor'], true)) {
    header('Location: /ITSFCP-PROYECTOS/index.php');
    exit;
}

require_once '../../Controladores/solicitudesControlador.php';
$ctrl = new solicitudesControlador();

$resultado   = $ctrl->index($id_usuario, $rol);
$solicitudes = $resultado['solicitudes'] ?? [];
$resumen     = $resultado['resumen']     ?? [];
$proyectos   = $resultado['proyectos']   ?? [];
$filtros     = $resultado['filtros']     ?? [];
$paginacion  = $resultado['paginacion']  ?? [];

ob_start();
include __DIR__ . '/../../mensaje.php';
?>

<div class="container-fluid py-4">

    <!-- ── TÍTULO ── -->
    <div class="row mb-3 align-items-center">
        <div class="col">
            <h2 class="mb-0 fw-semibold">Solicitudes de integración</h2>
            <p class="text-muted small mb-0">Gestiona las solicitudes de estudiantes para tus proyectos de investigación.</p>
        </div>
    </div>

    <!-- ── RESUMEN ── -->
    <div class="row g-3 mb-4">
        <?php
        $cards = [
            ['num' => $resumen['total']       ?? 0, 'lbl' => 'Total',          'color' => 'primary'],
            ['num' => $resumen['pendientes']   ?? 0, 'lbl' => 'Pendientes',     'color' => 'secondary'],
            ['num' => $resumen['en_revision']  ?? 0, 'lbl' => 'En revisión',    'color' => 'info'],
            ['num' => $resumen['correcciones'] ?? 0, 'lbl' => 'Correcciones',   'color' => 'warning'],
            ['num' => $resumen['aceptadas']    ?? 0, 'lbl' => 'Aceptadas',      'color' => 'success'],
            ['num' => $resumen['rechazadas']   ?? 0, 'lbl' => 'Rechazadas',     'color' => 'danger'],
        ];
        foreach ($cards as $c): ?>
            <div class="col-6 col-md-4 col-xl-2">
                <div class="resumen-card border bg-white shadow-sm h-100">
                    <div class="resumen-num text-<?= $c['color'] ?>"><?= $c['num'] ?></div>
                    <div class="resumen-lbl"><?= $c['lbl'] ?></div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- ── FILTROS ── -->
    <form method="GET" action="" class="row g-2 filtros-row mb-3 align-items-end">
        <input type="hidden" name="action" value="index">

        <div class="col-12 col-sm-6 col-md-4 col-lg-3">
            <label class="form-label small fw-medium mb-1">Buscar</label>
            <input type="text" name="buscar" class="form-control form-control-sm"
                   placeholder="Nombre, matrícula, proyecto…"
                   value="<?= htmlspecialchars($filtros['buscar'] ?? '') ?>">
        </div>

        <div class="col-6 col-sm-4 col-md-3 col-lg-2">
            <label class="form-label small fw-medium mb-1">Estado</label>
            <select name="estado" class="form-select form-select-sm">
                <option value="">Todos</option>
                <?php foreach (['pendiente' => 'Pendiente', 'en_revision' => 'En revisión',
                                'correcciones' => 'Correcciones', 'aceptado' => 'Aceptado',
                                'rechazado' => 'Rechazado'] as $val => $lbl): ?>
                    <option value="<?= $val ?>" <?= ($filtros['estado'] ?? '') === $val ? 'selected' : '' ?>>
                        <?= $lbl ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-6 col-sm-4 col-md-3 col-lg-2">
            <label class="form-label small fw-medium mb-1">Proyecto</label>
            <select name="proyecto" class="form-select form-select-sm">
                <option value="">Todos</option>
                <?php foreach ($proyectos as $p): ?>
                    <option value="<?= $p['id_proyectos'] ?>"
                        <?= ($filtros['proyecto'] ?? '') == $p['id_proyectos'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars(mb_strimwidth($p['titulo'], 0, 40, '…')) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-6 col-sm-4 col-md-2 col-lg-1">
            <label class="form-label small fw-medium mb-1">Semestre</label>
            <select name="semestre" class="form-select form-select-sm">
                <option value="">Todos</option>
                <?php for ($s = 1; $s <= 10; $s++): ?>
                    <option value="<?= $s ?>" <?= ($filtros['semestre'] ?? '') == $s ? 'selected' : '' ?>>
                        <?= $s ?>°
                    </option>
                <?php endfor; ?>
            </select>
        </div>

        <div class="col-6 col-sm-4 col-md-2 col-lg-2">
            <label class="form-label small fw-medium mb-1">Desde</label>
            <input type="date" name="fecha_desde" class="form-control form-control-sm"
                   value="<?= htmlspecialchars($filtros['fecha_desde'] ?? '') ?>">
        </div>

        <div class="col-6 col-sm-4 col-md-2 col-lg-2">
            <label class="form-label small fw-medium mb-1">Hasta</label>
            <input type="date" name="fecha_hasta" class="form-control form-control-sm"
                   value="<?= htmlspecialchars($filtros['fecha_hasta'] ?? '') ?>">
        </div>

        <div class="col-auto d-flex gap-2">
            <button type="submit" class="btn btn-primary btn-sm">
                <i class="bi bi-search"></i> Filtrar
            </button>
            <a href="tabla.php" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-x-circle"></i> Limpiar
            </a>
        </div>
    </form>

    <!-- ── TABLA ── -->
    <?php if (!empty($solicitudes)): ?>

        <!-- DESKTOP -->
        <div class="table-responsive d-none d-md-block">
            <table class="table table-hover align-middle text-center">
                <thead class="table-light">
                    <tr>
                        <?php foreach ($ctrl->encabezados() as $h): ?>
                            <th class="small fw-semibold"><?= $h ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($solicitudes as $sol): ?>
                        <tr>
                            <td class="text-muted small"><?= $sol['id_solicitud_proyecto'] ?></td>
                            <td class="text-start">
                                <div class="fw-medium"><?= htmlspecialchars($sol['estudiante_nombre']) ?></div>
                                <div class="text-muted small"><?= htmlspecialchars($sol['correo_institucional'] ?? '') ?></div>
                            </td>
                            <td><?= htmlspecialchars($sol['matricula'] ?? '-') ?></td>
                            <td class="small"><?= htmlspecialchars($sol['carrera'] ?? '-') ?></td>
                            <td class="text-start small"><?= htmlspecialchars(mb_strimwidth($sol['proyecto_titulo'], 0, 45, '…')) ?></td>
                            <td><?= $sol['semestre'] ? $sol['semestre'] . '°' : '-' ?></td>
                            <td><?= $sol['promedio'] ?? '-' ?></td>
                            <td class="small"><?= $sol['fecha_envio'] ?></td>
                            <td><?= $ctrl->badgeEstado($sol['estado']) ?></td>
                            <td class="text-nowrap">
                                <?= $ctrl->botonesAccion(
                                    $sol['id_solicitud_proyecto'],
                                    $sol['estado'],
                                    $sol['id_proyectos']
                                ) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- MÓVIL -->
        <div class="d-md-none">
            <?php foreach ($solicitudes as $sol): ?>
                <div class="card mb-3 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <div class="fw-semibold"><?= htmlspecialchars($sol['estudiante_nombre']) ?></div>
                                <div class="text-muted small"><?= htmlspecialchars($sol['matricula'] ?? '') ?></div>
                            </div>
                            <?= $ctrl->badgeEstado($sol['estado']) ?>
                        </div>
                        <p class="small mb-1"><strong>Proyecto:</strong> <?= htmlspecialchars(mb_strimwidth($sol['proyecto_titulo'], 0, 50, '…')) ?></p>
                        <p class="small mb-1"><strong>Carrera:</strong> <?= htmlspecialchars($sol['carrera'] ?? '-') ?></p>
                        <p class="small mb-2"><strong>Fecha:</strong> <?= $sol['fecha_envio'] ?></p>
                        <div class="d-flex gap-2 flex-wrap">
                            <?= $ctrl->botonesAccion($sol['id_solicitud_proyecto'], $sol['estado'], $sol['id_proyectos']) ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- PAGINACIÓN -->
        <?php if ($paginacion['total_paginas'] > 1): ?>
            <nav class="mt-3">
                <ul class="pagination justify-content-center pagination-sm">
                    <?php
                    $qBase = http_build_query(array_filter([
                        'action'      => 'index',
                        'buscar'      => $filtros['buscar'],
                        'estado'      => $filtros['estado'],
                        'proyecto'    => $filtros['proyecto'],
                        'semestre'    => $filtros['semestre'],
                        'fecha_desde' => $filtros['fecha_desde'],
                        'fecha_hasta' => $filtros['fecha_hasta'],
                    ]));
                    $pag = $paginacion;
                    ?>
                    <li class="page-item <?= $pag['pagina'] <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="?<?= $qBase ?>&pagina=<?= $pag['pagina'] - 1 ?>">‹</a>
                    </li>
                    <?php for ($i = 1; $i <= $pag['total_paginas']; $i++): ?>
                        <li class="page-item <?= $i === $pag['pagina'] ? 'active' : '' ?>">
                            <a class="page-link" href="?<?= $qBase ?>&pagina=<?= $i ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?= $pag['pagina'] >= $pag['total_paginas'] ? 'disabled' : '' ?>">
                        <a class="page-link" href="?<?= $qBase ?>&pagina=<?= $pag['pagina'] + 1 ?>">›</a>
                    </li>
                </ul>
                <p class="text-center text-muted small">
                    Mostrando <?= (($pag['pagina']-1)*$pag['por_pagina'])+1 ?>–<?= min($pag['pagina']*$pag['por_pagina'], $pag['total']) ?> de <?= $pag['total'] ?> solicitudes
                </p>
            </nav>
        <?php endif; ?>

    <?php else: ?>
        <div class="alert alert-info text-center">
            <i class="bi bi-inbox me-2"></i>
            No hay solicitudes que coincidan con los filtros aplicados.
        </div>
    <?php endif; ?>

</div>

<!-- ══════════════════════════════════════════════════════
     MODAL — ACCIÓN (correcciones / rechazo)
══════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalAccion" tabindex="-1" aria-hidden="true">
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
                    <label class="form-label fw-medium" id="labelComentario">Comentario <span class="text-danger">*</span></label>
                    <textarea id="accionComentario" class="form-control" rows="4"
                              placeholder="Escribe tu comentario para el estudiante…"></textarea>
                </div>
                <div class="mb-2">
                    <label class="form-label fw-medium">Archivo adjunto <span class="text-muted small">(opcional, PDF/DOCX/imagen)</span></label>
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

<!--  JAVASCRIPT -->
<script>
'use strict';

// ── Inicializar tooltips Bootstrap ──
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-bs-toggle="tooltip"]')
        .forEach(el => new bootstrap.Tooltip(el));
});

let _idSolicitudActual = null;
let _estadoActual      = null;

// ── Ver detalle ──
function verDetalleSolicitud(id) {
    _idSolicitudActual = id;
    document.getElementById('detalleCargando').style.display  = '';
    document.getElementById('detalleContenido').style.display = 'none';
    document.getElementById('detalleFooter').style.display    = 'none';

    new bootstrap.Modal(document.getElementById('modalDetalle')).show();

    fetch('/ITSFCP-PROYECTOS/Ajax/solicitudesAjax.php?action=detalle&id=' + id)
        .then(r => r.json())
        .then(data => {
            if (data.error) { alert(data.error); return; }

            const s = data.solicitud;
            _estadoActual = s.estado;

            // Info estudiante + proyecto
            document.getElementById('detalleInfo').innerHTML = `
                <div class="col-md-6">
                    <div class="card h-100 border-0 bg-light p-3">
                        <h6 class="text-primary mb-2"><i class="bi bi-person-fill me-1"></i>Estudiante</h6>
                        <p class="mb-1"><strong>Nombre:</strong> ${esc(s.estudiante_nombre)}</p>
                        <p class="mb-1"><strong>Matrícula:</strong> ${esc(s.matricula)}</p>
                        <p class="mb-1"><strong>Correo:</strong> ${esc(s.correo_institucional)}</p>
                        <p class="mb-1"><strong>Carrera:</strong> ${esc(s.carrera)}</p>
                        <p class="mb-1"><strong>Semestre:</strong> ${s.semestre ?? '-'}°</p>
                        <p class="mb-0"><strong>Promedio:</strong> ${s.promedio ?? '-'}</p>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card h-100 border-0 bg-light p-3">
                        <h6 class="text-primary mb-2"><i class="bi bi-journal-text me-1"></i>Proyecto solicitado</h6>
                        <p class="mb-1"><strong>Título:</strong> ${esc(s.proyecto_titulo)}</p>
                        <p class="mb-1"><strong>Modalidad:</strong> ${esc(s.modalidad)}</p>
                        <p class="mb-1"><strong>Fecha solicitud:</strong> ${s.fecha_envio}</p>
                        <p class="mb-1"><strong>Estado:</strong> ${badgeEstado(s.estado)}</p>
                        <hr class="my-2">
                        <h6 class="text-primary mb-1">Motivación</h6>
                        <p class="small mb-1">${esc(s.motivacion) || 'Sin información'}</p>
                        <h6 class="text-primary mb-1">Experiencia</h6>
                        <p class="small mb-0">${esc(s.experiencia) || 'Sin información'}</p>
                    </div>
                </div>
                ${s.carta_nombre ? `
                <div class="col-12">
                    <div class="alert alert-info py-2 mb-0">
                        <i class="bi bi-paperclip me-1"></i>
                        <strong>Carta Compromiso adjunta:</strong>
                        <a href="/ITSFCP-PROYECTOS/${s.carta_ruta}" target="_blank" class="ms-2 btn btn-sm btn-outline-primary">
                            <i class="bi bi-download me-1"></i>${esc(s.carta_nombre)}
                        </a>
                    </div>
                </div>` : ''}
            `;

            // Etapas del seguimiento
            renderEtapas(data.etapas || []);

            // Comentarios
            renderComentarios(data.comentarios || []);

            // Footer con acciones
            const estados_activos = ['pendiente','en_revision','correcciones'];
            document.getElementById('detalleFooter').style.display = '';
            document.getElementById('btnAceptarDetalle').style.display     = estados_activos.includes(s.estado) ? '' : 'none';
            document.getElementById('btnCorreccionesDetalle').style.display = estados_activos.includes(s.estado) ? '' : 'none';
            document.getElementById('btnRechazarDetalle').style.display    = estados_activos.includes(s.estado) ? '' : 'none';

            document.getElementById('detalleCargando').style.display  = 'none';
            document.getElementById('detalleContenido').style.display = '';
        })
        .catch(e => alert('Error al cargar: ' + e.message));
}

function renderEtapas(etapas) {
    const el = document.getElementById('detalleEtapas');
    if (!etapas.length) {
        el.innerHTML = '<p class="text-muted small">Sin información de seguimiento aún.</p>';
        return;
    }
    const colores = { pendiente:'secondary', proceso:'info', completado:'success', rechazado:'danger' };
    el.innerHTML = etapas.map((e,i) => `
        <div class="d-flex align-items-center gap-2 mb-1">
            <span class="badge bg-${colores[e.estado]||'secondary'} rounded-pill" style="min-width:90px">${e.estado}</span>
            <span class="small">${i+1}. ${esc(e.nombre)}</span>
        </div>
    `).join('');
}

function renderComentarios(comentarios) {
    const panel = document.getElementById('panelComentarios');
    if (!comentarios.length) {
        panel.innerHTML = '<p class="text-muted small">Sin comentarios aún.</p>';
        return;
    }
    panel.innerHTML = comentarios.map(c => `
        <div class="msg-item ${c.tipo === 'investigador' ? 'msg-inv' : 'msg-est'}">
            <div>${esc(c.comentario)}</div>
            ${c.archivo_nombre ? `<a href="/ITSFCP-PROYECTOS/${c.archivo_ruta}" target="_blank" class="small"><i class="bi bi-paperclip"></i> ${esc(c.archivo_nombre)}</a>` : ''}
            <div class="msg-meta">${c.autor_nombre} · ${c.fecha}</div>
        </div>
    `).join('');
    panel.scrollTop = panel.scrollHeight;
}

// ── Botones del footer del modal detalle ──
document.getElementById('btnAceptarDetalle').onclick = () => confirmarAceptar(_idSolicitudActual);
document.getElementById('btnCorreccionesDetalle').onclick = () => abrirModalAccion(_idSolicitudActual, 'correcciones');
document.getElementById('btnRechazarDetalle').onclick = () => abrirModalAccion(_idSolicitudActual, 'rechazar');

// ── Aceptar con confirmación ──
function confirmarAceptar(id) {
    if (!confirm('¿Confirmar la aceptación de esta solicitud? El estudiante quedará integrado al proyecto.')) return;
    enviarAccion('aceptar', id, '', null);
}

// ── Modal de acción (correcciones / rechazar) ──
function abrirModalAccion(id, tipo) {
    document.getElementById('accionIdSolicitud').value = id;
    document.getElementById('accionTipo').value        = tipo;
    document.getElementById('accionComentario').value  = '';
    document.getElementById('accionArchivo').value     = '';
    document.getElementById('accionMensaje').className = 'alert d-none mt-2';

    const esCorrección = tipo === 'correcciones';
    document.getElementById('modalAccionTitulo').textContent =
        esCorrección ? 'Solicitar correcciones' : 'Rechazar solicitud';
    document.getElementById('modalAccionHeader').className =
        'modal-header ' + (esCorrección ? 'bg-warning' : 'bg-danger text-white');
    document.getElementById('labelComentario').textContent =
        esCorrección ? 'Indica al estudiante qué debe corregir *' : 'Motivo de rechazo *';
    document.getElementById('btnAccionTexto').textContent =
        esCorrección ? 'Enviar correcciones' : 'Rechazar definitivamente';

    bootstrap.Modal.getOrCreateInstance(document.getElementById('modalAccion')).show();
}

document.getElementById('btnConfirmarAccion').onclick = function () {
    const id         = document.getElementById('accionIdSolicitud').value;
    const tipo       = document.getElementById('accionTipo').value;
    const comentario = document.getElementById('accionComentario').value.trim();
    const archivo    = document.getElementById('accionArchivo').files[0] || null;

    if (!comentario) {
        mostrarMensajeAccion('El comentario es obligatorio.', 'danger');
        return;
    }
    enviarAccion(tipo, id, comentario, archivo);
};

function enviarAccion(action, id, comentario, archivo) {
    const spinner  = document.getElementById('spinnerAccion');
    const btnTexto = document.getElementById('btnAccionTexto');
    if (spinner) { spinner.classList.remove('d-none'); if (btnTexto) btnTexto.classList.add('d-none'); }

    const fd = new FormData();
    fd.append('id_solicitud', id);
    fd.append('comentario',   comentario);
    if (archivo) fd.append('archivo', archivo);

    fetch('/ITSFCP-PROYECTOS/Ajax/solicitudesAjax.php?action=' + action, {
        method: 'POST', body: fd
    })
    .then(r => r.json())
    .then(data => {
        if (data.ok) {
            // Cerrar modales y recargar
            ['modalAccion','modalDetalle'].forEach(id => {
                const m = bootstrap.Modal.getInstance(document.getElementById(id));
                if (m) m.hide();
            });
            setTimeout(() => location.reload(), 300);
        } else {
            mostrarMensajeAccion(data.msg || 'Error al procesar.', 'danger');
        }
    })
    .catch(e => mostrarMensajeAccion('Error de conexión: ' + e.message, 'danger'))
    .finally(() => {
        if (spinner) { spinner.classList.add('d-none'); if (btnTexto) btnTexto.classList.remove('d-none'); }
    });
}

function mostrarMensajeAccion(msg, tipo) {
    const el = document.getElementById('accionMensaje');
    el.textContent = msg;
    el.className   = `alert alert-${tipo} mt-2`;
}

// ── Utilidades ──
function esc(str) {
    if (!str) return '';
    return str.toString().replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

function badgeEstado(estado) {
    const map = {
        pendiente:    'secondary',
        en_revision:  'info',
        correcciones: 'warning',
        aceptado:     'success',
        rechazado:    'danger',
    };
    const lbl = {
        pendiente:'Pendiente', en_revision:'En revisión',
        correcciones:'Correcciones', aceptado:'Aceptado', rechazado:'Rechazado'
    };
    const c = map[estado] || 'secondary';
    return `<span class="badge bg-${c} ${estado==='en_revision'||estado==='correcciones'?'text-dark':''}">${lbl[estado]||estado}</span>`;
}
</script>

<?php
$contenido = ob_get_clean();
$titulo    = 'Solicitudes de integración';
$bodyClass = 'proyectos-page';
include __DIR__ . '/../../layout.php';
?>
