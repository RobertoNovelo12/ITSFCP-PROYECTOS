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
$periodos    = $resultado['periodos']    ?? [];

ob_start();
include __DIR__ . '/../../mensaje.php';
?>
<div class="container-fluid py-4">

    <div class="row mb-3 align-items-center">
        <div class="col">
            <h2 class="mb-0 fw-semibold">Solicitudes de integración</h2>
            <p class="text-muted small mb-0">Gestiona las solicitudes de estudiantes para tus proyectos.</p>
        </div>
    </div>

    <!-- FILTRO GLOBAL POR PERIODO -->
    <div class="row mb-4">
        <div class="col-12 col-md-auto">
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <label class="fw-semibold small mb-0 text-nowrap">Periodo:</label>
                <form method="GET" action="" id="formPeriodo" class="d-flex align-items-center gap-2 flex-wrap">
                    <!-- Conservar otros filtros al cambiar periodo -->
                    <?php foreach (['estado', 'buscar', 'proyecto', 'fecha_desde', 'fecha_hasta'] as $f): ?>
                        <?php if (!empty($filtros[$f])): ?>
                            <input type="hidden" name="<?= $f ?>" value="<?= htmlspecialchars($filtros[$f]) ?>">
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <select name="periodo" class="form-select form-select-sm" style="min-width:180px"
                        onchange="document.getElementById('formPeriodo').submit()">
                        <option value="">Todos los periodos</option>
                        <?php foreach ($periodos as $p): ?>
                            <option value="<?= htmlspecialchars($p['id_periodos']) ?>"
                                <?= ($filtros['periodo'] ?? '') == $p['id_periodos'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($p['periodo']) ?>
                                <?php if (!empty($p['estado']) && $p['estado']): ?>
                                    <span> (Activo)</span>
                                <?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (!empty($filtros['periodo'])): ?>
                        <a href="tabla.php" class="btn btn-outline-secondary btn-sm text-nowrap">
                            Limpiar
                        </a>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>

    <!-- RESUMEN -->
    <div class="row g-3 mb-4">
        <?php
        $cards = [
            ['num' => $resumen['total']       ?? 0, 'lbl' => 'Total',        'color' => 'primary'],
            ['num' => $resumen['pendientes']   ?? 0, 'lbl' => 'Pendientes',   'color' => 'secondary'],
            ['num' => $resumen['en_revision']  ?? 0, 'lbl' => 'En revisión',  'color' => 'info'],
            ['num' => $resumen['correcciones'] ?? 0, 'lbl' => 'Correcciones', 'color' => 'warning'],
            ['num' => $resumen['aceptadas']    ?? 0, 'lbl' => 'Aceptadas',    'color' => 'success'],
            ['num' => $resumen['rechazadas']   ?? 0, 'lbl' => 'Rechazadas',   'color' => 'danger'],
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

    <!-- FILTROS -->
    <form method="GET" action="" class="row g-2 mb-3 align-items-end">
        <!-- Conservar filtro de periodo en los filtros secundarios -->
        <?php if (!empty($filtros['periodo'])): ?>
            <input type="hidden" name="periodo" value="<?= htmlspecialchars($filtros['periodo']) ?>">
        <?php endif; ?>
        <div class="col-12 col-sm-6 col-md-4 col-lg-3">
            <label class="form-label small fw-medium mb-1">Buscar</label>
            <input type="text" name="buscar" class="form-control form-control-sm"
                placeholder="Nombre, matrícula, proyecto…"
                value="<?= htmlspecialchars($filtros['buscar'] ?? '') ?>">
        </div>
        <div class="col-6 col-sm-4 col-md-2">
            <label class="form-label small fw-medium mb-1">Estado</label>
            <select name="estado" class="form-select form-select-sm">
                <option value="">Todos</option>
                <?php foreach (
                    [
                        'pendiente' => 'Pendiente',
                        'en_revision' => 'En revisión',
                        'correcciones' => 'Correcciones',
                        'aceptado' => 'Aceptado',
                        'rechazado' => 'Rechazado'
                    ] as $val => $lbl
                ): ?>
                    <option value="<?= $val ?>" <?= ($filtros['estado'] ?? '') === $val ? 'selected' : '' ?>>
                        <?= $lbl ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-sm-4 col-md-3">
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
        <div class="col-6 col-sm-3 col-md-2">
            <label class="form-label small fw-medium mb-1">Desde</label>
            <input type="date" name="fecha_desde" class="form-control form-control-sm"
                value="<?= htmlspecialchars($filtros['fecha_desde'] ?? '') ?>">
        </div>
        <div class="col-6 col-sm-3 col-md-2">
            <label class="form-label small fw-medium mb-1">Hasta</label>
            <input type="date" name="fecha_hasta" class="form-control form-control-sm"
                value="<?= htmlspecialchars($filtros['fecha_hasta'] ?? '') ?>">
        </div>
        <div class="col-auto d-flex gap-2">
            <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-search"></i> Filtrar</button>
            <a href="tabla.php<?= !empty($filtros['periodo']) ? '?periodo=' . urlencode($filtros['periodo']) : '' ?>"
                class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-x-circle"></i> Limpiar
            </a>
        </div>
    </form>

    <!-- TABLA -->
    <?php if (!empty($solicitudes)): ?>
        <div class="card shadow-sm d-none d-md-block">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr><?php foreach ($ctrl->encabezados() as $h): ?><th class="small fw-semibold"><?= $h ?></th><?php endforeach; ?></tr>
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
                                        <?= $ctrl->botonesAccion($sol['id_solicitud_proyecto'], $sol['estado'], $sol['id_proyectos']) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
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
                        'periodo'     => $filtros['periodo'],
                        'buscar'      => $filtros['buscar'],
                        'estado'      => $filtros['estado'],
                        'proyecto'    => $filtros['proyecto'],
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
            </nav>
        <?php endif; ?>

    <?php else: ?>
        <div class="alert alert-info text-center">
            <i class="bi bi-inbox me-2"></i>No hay solicitudes que coincidan con los filtros.
        </div>
    <?php endif; ?>

</div>

<!-- ══ MODAL DETALLE ══════════════════════════════════════════════ -->
<div class="modal fade" id="modalDetalle" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="bi bi-file-person me-2"></i>Detalle de solicitud</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">

                <!-- Spinner -->
                <div id="detalleCargando" class="text-center py-4">
                    <div class="spinner-border text-primary"></div>
                    <p class="text-muted small mt-2">Cargando…</p>
                </div>

                <!-- Contenido -->
                <div id="detalleContenido" style="display:none">

                    <!-- Info estudiante + proyecto -->
                    <div class="row g-3 mb-3" id="detalleInfo"></div>

                    <!-- 3 Etapas de seguimiento -->
                    <div class="card border-primary mb-3">
                        <div class="card-header bg-primary text-white py-2">
                            <strong><i class="bi bi-diagram-3 me-1"></i>Seguimiento del estudiante</strong>
                        </div>
                        <div class="card-body p-0">
                            <div class="row g-0" id="detalleEtapas"></div>
                        </div>
                    </div>

                    <!-- Comentarios -->
                    <div class="fw-semibold small mb-2">
                        <i class="bi bi-chat-left-text me-1"></i>Historial de comentarios
                    </div>
                    <div id="panelComentarios" class="mb-2"></div>

                </div>
            </div>

            <div class="modal-footer" id="detalleFooter" style="display:none">
                <button class="btn btn-success btn-sm" id="btnAceptarDetalle">
                    <i class="bi bi-check-circle-fill me-1"></i>Aceptar
                </button>
                <button class="btn btn-warning btn-sm" id="btnCorreccionesDetalle">
                    <i class="bi bi-pencil-fill me-1"></i>Correcciones
                </button>
                <button class="btn btn-danger btn-sm" id="btnRechazarDetalle">
                    <i class="bi bi-ban me-1"></i>Rechazar
                </button>
                <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- ══ MODAL ACCIÓN (correcciones / rechazar) ════════════════════ -->
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
                        Archivo adjunto <span class="text-muted small">(opcional, PDF/DOCX/imagen)</span>
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

<script>
    'use strict';

    const AJAX = '/ITSFCP-PROYECTOS/Ajax/solicitudesAjax.php';
    let _idSolicitudActual = null;


    function renderEtapasSeguimiento(seg, sol, id_solicitud) {
        const el = document.getElementById('detalleEtapas');
        const e1 = seg.e1_estado || sol.estado || 'pendiente';
        const e2 = seg.e2_estado || 'pendiente';
        const e3 = seg.e3_estado || 'pendiente';
        const fase2ok = seg.fase2_ok || false;

        const colorMap = {
            pendiente: 'secondary',
            proceso: 'primary',
            completado: 'success',
            rechazado: 'danger',
            correcciones: 'warning',
            en_revision: 'info',
            aceptado: 'success'
        };
        const labelMap = {
            pendiente: 'Pendiente',
            proceso: 'En revisión',
            completado: 'Aprobado',
            rechazado: 'Rechazado',
            correcciones: 'Correcciones',
            en_revision: 'En revisión',
            aceptado: 'Aceptado'
        };

        const badge = (e) => `<span class="badge bg-${colorMap[e]||'secondary'} ${e==='correcciones'?'text-dark':''}">${labelMap[e]||e}</span>`;

        const activos1 = ['pendiente', 'en_revision', 'correcciones', 'proceso'];

        el.innerHTML = `
        <!-- Etapa 1 -->
        <div class="col-md-4 border-end p-3">
            <div class="d-flex justify-content-between align-items-center mb-1">
                <strong class="small">1. Solicitud de integración</strong>
                ${badge(e1)}
            </div>
            <p class="text-muted small mb-2">Carta Compromiso firmada y aceptación del investigador.</p>
            ${activos1.includes(e1) ? `
                <div class="d-flex gap-1 flex-wrap mt-2">
                    <button class="btn btn-success btn-sm" onclick="confirmarAceptar(${id_solicitud})">
                        <i class="bi bi-check-lg"></i> Aceptar
                    </button>
                    <button class="btn btn-warning btn-sm" onclick="abrirModalAccion(${id_solicitud},'correcciones')">
                        <i class="bi bi-pencil"></i> Correcciones
                    </button>
                    <button class="btn btn-danger btn-sm" onclick="abrirModalAccion(${id_solicitud},'rechazar')">
                        <i class="bi bi-x-lg"></i> Rechazar
                    </button>
                </div>` : ''}
        </div>
        <!-- Etapa 2 -->
        <div class="col-md-4 border-end p-3">
            <div class="d-flex justify-content-between align-items-center mb-1">
                <strong class="small">2. Desarrollo del documento</strong>
                ${badge(e2)}
            </div>
            <p class="text-muted small mb-1">Completado automáticamente al terminar las actividades.</p>
            <div class="small text-muted">${seg.actividades_aprobadas || 0} / ${seg.actividades_total || 0} actividades aprobadas</div>
            ${fase2ok ? '<div class="alert alert-success py-1 px-2 mt-1 small mb-0"><i class="bi bi-check-circle-fill"></i> Todas aprobadas.</div>' : ''}
        </div>
        <!-- Etapa 3 -->
        <div class="col-md-4 p-3">
            <div class="d-flex justify-content-between align-items-center mb-1">
                <strong class="small">3. Cierre del proyecto</strong>
                ${badge(e3)}
            </div>
            <p class="text-muted small mb-1">Reporte Final aprobado y Carta de Terminación.</p>
            ${!fase2ok ? '<div class="text-muted small"><i class="bi bi-lock"></i> Disponible al completar el desarrollo.</div>' : ''}
            ${e3 === 'completado' ? `<a href="detalles_solicitud.php?id=${id_solicitud}" class="btn btn-sm btn-outline-success mt-1">
                <i class="bi bi-download"></i> Ver detalle completo
            </a>` : ''}
        </div>
    `;
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
            ${c.archivo_nombre ? `<a href="/ITSFCP-PROYECTOS/${c.archivo_ruta}" target="_blank" class="small text-primary">
                <i class="bi bi-paperclip"></i> ${esc(c.archivo_nombre)}
            </a>` : ''}
            <div class="msg-meta"><strong>${esc(c.autor_nombre)}</strong> · ${c.tipo === 'investigador' ? 'Investigador' : 'Estudiante'} · ${c.fecha}</div>
        </div>
    `).join('');
        panel.scrollTop = panel.scrollHeight;
    }

    // ── Botones footer modal ──────────────────────────────────────────
    document.getElementById('btnAceptarDetalle').onclick = () => confirmarAceptar(_idSolicitudActual);
    document.getElementById('btnCorreccionesDetalle').onclick = () => abrirModalAccion(_idSolicitudActual, 'correcciones');
    document.getElementById('btnRechazarDetalle').onclick = () => abrirModalAccion(_idSolicitudActual, 'rechazar');

    // ── Confirmar aceptar ─────────────────────────────────────────────
    function confirmarAceptar(id) {
        if (!confirm('¿Confirmar aceptación? El estudiante quedará integrado al proyecto.')) return;
        enviarAccion('aceptar', id, '', null);
    }

    // ── Modal de correcciones / rechazo ──────────────────────────────
    function abrirModalAccion(id, tipo) {
        document.getElementById('accionIdSolicitud').value = id;
        document.getElementById('accionTipo').value = tipo;
        document.getElementById('accionComentario').value = '';
        document.getElementById('accionArchivo').value = '';
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

        bootstrap.Modal.getOrCreateInstance(document.getElementById('modalAccion')).show();
    }

    document.getElementById('btnConfirmarAccion').onclick = function() {
        const id = document.getElementById('accionIdSolicitud').value;
        const tipo = document.getElementById('accionTipo').value;
        const comentario = document.getElementById('accionComentario').value.trim();
        const archivo = document.getElementById('accionArchivo').files[0] || null;

        if (!comentario) {
            mostrarMensajeAccion('El comentario es obligatorio.', 'danger');
            return;
        }
        enviarAccion(tipo, id, comentario, archivo);
    };

    function enviarAccion(action, id, comentario, archivo) {
        const spinner = document.getElementById('spinnerAccion');
        const btnTexto = document.getElementById('btnAccionTexto');
        spinner?.classList.remove('d-none');
        btnTexto?.classList.add('d-none');

        const fd = new FormData();
        fd.append('id_solicitud', id);
        fd.append('comentario', comentario);
        if (archivo) fd.append('archivo', archivo);

        fetch(`${AJAX}?action=${action}`, {
                method: 'POST',
                body: fd
            })
            .then(r => r.json())
            .then(data => {
                if (data.ok) {
                    ['modalAccion', 'modalDetalle'].forEach(mid => {
                        bootstrap.Modal.getInstance(document.getElementById(mid))?.hide();
                    });
                    setTimeout(() => location.reload(), 300);
                } else {
                    mostrarMensajeAccion(data.msg || 'Error al procesar.', 'danger');
                }
            })
            .catch(e => mostrarMensajeAccion('Error de conexión: ' + e.message, 'danger'))
            .finally(() => {
                spinner?.classList.add('d-none');
                btnTexto?.classList.remove('d-none');
            });
    }

    function mostrarMensajeAccion(msg, tipo) {
        const el = document.getElementById('accionMensaje');
        el.textContent = msg;
        el.className = `alert alert-${tipo} mt-2`;
    }

    function esc(str) {
        if (!str) return '';
        return str.toString()
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    function badgeEstado(estado) {
        const map = {
            pendiente: 'secondary',
            en_revision: 'info',
            correcciones: 'warning',
            aceptado: 'success',
            rechazado: 'danger'
        };
        const lbl = {
            pendiente: 'Pendiente',
            en_revision: 'En revisión',
            correcciones: 'Correcciones',
            aceptado: 'Aceptado',
            rechazado: 'Rechazado'
        };
        const c = map[estado] || 'secondary';
        const td = (estado === 'en_revision' || estado === 'correcciones') ? 'text-dark' : '';
        return `<span class="badge bg-${c} ${td}">${lbl[estado]||estado}</span>`;
    }
</script>

<?php
$contenido = ob_get_clean();
$titulo    = 'Solicitudes de integración';
$bodyClass = 'proyectos-page';
include __DIR__ . '/../../layout.php';
?>