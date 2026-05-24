<?php
// Vistas/Mis_solicitudes/index.php
// Vista exclusiva para el rol estudiante.
// Muestra todas sus solicitudes con filtros, resumen, tabla paginada y cards en móvil.

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
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

require_once __DIR__ . '/../../Controladores/misSolicitudesControlador.php';
$ctrl = new MisSolicitudesControlador();

// ── Acciones POST ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'cancelar') {
        $id_sol    = (int)($_POST['id_solicitud'] ?? 0);
        $resultado = $ctrl->cancelar($id_sol, $id_usuario);
        $param     = $resultado['ok'] ? 'cancelado' : 'error';
        $det       = !$resultado['ok'] ? ('&detalle=' . urlencode($resultado['mensaje'])) : '';
        header("Location: index.php?msg={$param}{$det}");
        exit;
    }
}

// ── Datos para la vista ────────────────────────────────────────────────────────
$data        = $ctrl->index($id_usuario);
$solicitudes = $data['solicitudes'];
$resumen     = $data['resumen'];
$periodos    = $data['periodos'];
$filtros     = $data['filtros'];
$paginacion  = $data['paginacion'];
$mensaje     = $ctrl->leerMensaje();

$titulo    = 'Mis Solicitudes';
$bodyClass = 'proyectos-page';

ob_start();
include __DIR__ . '/../../mensaje.php';
?>

<!-- ═
     ESTILOS LOCALES — Mis Solicitudes (Estudiante)
 -->
<style>
    /* ── Layout ──────────────────────────────────────────────────── */
    .ms-page {
        padding: 1.75rem 1.5rem;
        max-width: 1400px;
        margin: 0 auto;
    }



    /* colores por tipo — ya no se usan, Bootstrap los cubre */



    /* ── Tabla ─────────────────────────────────────────────────── */
    .ms-tabla-wrap {
        background: #fff;
        border: 1px solid var(--borde-menu);
        border-radius: var(--card-radius);
        overflow: hidden;
        box-shadow: var(--shadow-sm);
    }

    .ms-tabla {
        width: 100%;
        border-collapse: collapse;
        font-size: .855rem;
    }

    .ms-tabla thead th {
        background: #f8f9fb;
        color: var(--color-texto-secundario);
        font-size: .72rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .4px;
        padding: .75rem 1rem;
        border-bottom: 1px solid var(--borde-menu);
        white-space: nowrap;
    }

    .ms-tabla tbody tr {
        border-bottom: 1px solid #f1f3f7;
        transition: background .12s;
    }

    .ms-tabla tbody tr:last-child {
        border-bottom: none;
    }

    .ms-tabla tbody tr:hover {
        background: #f9fbff;
    }

    .ms-tabla td {
        padding: .7rem 1rem;
        vertical-align: middle;
    }

    .ms-tabla .td-proyecto {
        max-width: 220px;
    }

    .ms-tabla .td-proyecto strong {
        display: block;
        color: var(--color-texto-principal);
        font-size: .875rem;
    }

    .ms-tabla .td-proyecto span {
        color: var(--color-texto-secundario);
        font-size: .78rem;
    }

    /* ── Badges ─────────────────────────────────────────────────── */
    .badge {
        display: inline-flex;
        align-items: center;
        gap: .28rem;
        padding: .28rem .65rem;
        border-radius: 20px;
        font-size: .72rem;
        font-weight: 600;
        letter-spacing: .2px;
        border: 1px solid transparent;
        white-space: nowrap;
    }

    .badge-pendiente {
        background: var(--badge-cierre-bg);
        color: var(--badge-cierre-color);
        border-color: var(--badge-cierre-border);
    }

    .badge-en_revision {
        background: var(--badge-poraprobar-bg);
        color: var(--badge-poraprobar-color);
        border-color: var(--badge-poraprobar-border);
    }

    .badge-correcciones {
        background: var(--badge-porcerrar-bg);
        color: var(--badge-porcerrar-color);
        border-color: var(--badge-porcerrar-border);
    }

    .badge-aceptado {
        background: var(--badge-activo-bg);
        color: var(--badge-activo-color);
        border-color: var(--badge-activo-border);
    }

    .badge-rechazado {
        background: rgba(196, 18, 48, .09);
        color: var(--color-rojo-institucional);
        border-color: var(--color-rojo-institucional);
    }

    .badge-vencido {
        background: var(--badge-vencido-bg);
        color: var(--badge-vencido-color);
        border-color: var(--badge-vencido-border);
    }

    .badge-cancelado {
        background: #f1f3f7;
        color: #8a96a3;
        border-color: #cbd0d8;
    }

    /* ── Botones de acción ──────────────────────────────────────── */
    .ms-btn-accion {
        display: inline-flex;
        align-items: center;
        gap: .3rem;
        padding: .32rem .6rem;
        border-radius: 6px;
        font-size: .78rem;
        font-weight: 600;
        border: 1px solid transparent;
        cursor: pointer;
        text-decoration: none;
        transition: background .14s, transform .1s;
        white-space: nowrap;
    }

    .ms-btn-accion:hover {
        transform: translateY(-1px);
    }

    .ms-btn-ver {
        background: var(--badge-poraprobar-bg);
        color: var(--hover-boton);
        border-color: var(--hover-boton);
    }

    .ms-btn-ver:hover {
        background: var(--hover-boton);
        color: #fff;
    }

    .ms-btn-resp {
        background: var(--badge-porcerrar-bg);
        color: #a87a10;
        border-color: #d4a017;
    }

    .ms-btn-resp:hover {
        background: #d4a017;
        color: #fff;
    }

    .ms-btn-cancel {
        background: rgba(196, 18, 48, .07);
        color: var(--color-rojo-institucional);
        border-color: var(--color-rojo-institucional);
    }

    .ms-btn-cancel:hover {
        background: var(--color-rojo-institucional);
        color: #fff;
    }

    /* ── Estado vacío ───────────────────────────────────────────── */
    .ms-empty {
        text-align: center;
        padding: 4rem 2rem;
        color: var(--color-texto-secundario);
    }

    .ms-empty i {
        font-size: 3rem;
        opacity: .25;
        display: block;
        margin-bottom: 1rem;
    }

    .ms-empty p {
        font-size: .9rem;
        margin-bottom: 1.25rem;
    }

    /* ── Cards móvil ────────────────────────────────────────────── */
    .ms-card-movil {
        background: #fff;
        border: 1px solid var(--borde-menu);
        border-radius: var(--card-radius);
        box-shadow: var(--shadow-sm);
        margin-bottom: .85rem;
        overflow: hidden;
    }

    .ms-card-movil-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        padding: .85rem 1rem .6rem;
        border-bottom: 1px solid #f1f3f7;
        gap: .5rem;
    }

    .ms-card-movil-titulo {
        font-weight: 600;
        font-size: .875rem;
        color: var(--color-texto-principal);
        flex: 1;
    }

    .ms-card-movil-body {
        padding: .7rem 1rem;
    }

    .ms-card-movil-fila {
        display: flex;
        justify-content: space-between;
        font-size: .81rem;
        margin-bottom: .35rem;
    }

    .ms-card-movil-fila dt {
        color: var(--color-texto-secundario);
        font-weight: 600;
    }

    .ms-card-movil-fila dd {
        color: var(--color-texto-principal);
        margin: 0;
    }

    .ms-card-movil-acciones {
        display: flex;
        gap: .5rem;
        flex-wrap: wrap;
        padding: .6rem 1rem .85rem;
    }

    /* ── Paginación ─────────────────────────────────────────────── */
    .ms-paginacion {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: .35rem;
        margin-top: 1.25rem;
        flex-wrap: wrap;
    }

    .ms-page-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 32px;
        height: 32px;
        padding: 0 .55rem;
        border-radius: 7px;
        font-size: .82rem;
        font-weight: 600;
        border: 1px solid var(--borde-menu);
        color: var(--color-texto-secundario);
        text-decoration: none;
        background: #fff;
        transition: all .14s;
    }

    .ms-page-btn:hover {
        background: var(--badge-poraprobar-bg);
        color: var(--hover-boton);
        border-color: var(--hover-boton);
    }

    .ms-page-btn.activo {
        background: var(--color-primario);
        color: #fff;
        border-color: var(--color-primario);
    }

    .ms-page-btn.disabled {
        opacity: .4;
        pointer-events: none;
    }

    /* ── Modal de cancelar ──────────────────────────────────────── */
    .ms-modal-overlay {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, .45);
        backdrop-filter: blur(3px);
        z-index: 9999;
        align-items: center;
        justify-content: center;
    }

    .ms-modal-overlay.abierto {
        display: flex;
    }

    .ms-modal {
        background: #fff;
        border-radius: 14px;
        padding: 2rem;
        max-width: 420px;
        width: 90%;
        text-align: center;
        box-shadow: 0 20px 60px rgba(0, 0, 0, .18);
        animation: modalIn .2s ease;
    }

    @keyframes modalIn {
        from {
            opacity: 0;
            transform: scale(.92) translateY(8px);
        }

        to {
            opacity: 1;
            transform: scale(1) translateY(0);
        }
    }

    .ms-modal i {
        font-size: 2.5rem;
        color: var(--color-rojo-institucional);
    }

    .ms-modal h4 {
        font-size: 1.1rem;
        font-weight: 700;
        color: var(--color-primario);
        margin: .85rem 0 .5rem;
    }

    .ms-modal p {
        font-size: .875rem;
        color: var(--color-texto-secundario);
        margin-bottom: 1.5rem;
    }

    .ms-modal-btns {
        display: flex;
        gap: .75rem;
        justify-content: center;
    }

    .ms-btn-confirmar {
        background: var(--color-rojo-institucional);
        color: #fff;
        border: none;
        border-radius: 7px;
        padding: .5rem 1.25rem;
        font-size: .875rem;
        font-weight: 600;
        cursor: pointer;
        transition: background .15s;
    }

    .ms-btn-confirmar:hover {
        background: var(--color-rojo-institucional-dk);
    }

    .ms-btn-cerrar {
        background: var(--color-boton-secundario);
        color: var(--color-texto-principal);
        border: 1px solid var(--borde-menu);
        border-radius: 7px;
        padding: .5rem 1.25rem;
        font-size: .875rem;
        font-weight: 600;
        cursor: pointer;
        transition: background .15s;
    }

    .ms-btn-cerrar:hover {
        background: #d1d5db;
    }

    /* ── Nota corrección en tabla ───────────────────────────────── */
    .ms-nota-corr {
        display: inline-flex;
        align-items: center;
        gap: .3rem;
        font-size: .75rem;
        color: #a87a10;
        background: var(--badge-porcerrar-bg);
        border-radius: 4px;
        padding: .15rem .45rem;
        margin-top: .25rem;
    }

    /* ── Responsive tabla ───────────────────────────────────────── */
    @media (max-width: 767px) {
        .ms-tabla-wrap {
            display: none;
        }
    }

    @media (min-width: 768px) {
        .ms-cards-movil-wrap {
            display: none;
        }
    }
</style>

<!-- ═
     CONTENIDO
 -->
<div class="ms-page">

    <!-- ENCABEZADO + FILTRO PERIODO -->
    <div class="row mb-3 align-items-center">
        <div class="col-md-6">
            <h2 class="mb-0 fw-bold">Mis Solicitudes</h2>
            <p class="text-muted small mb-0">Historial y seguimiento de tus solicitudes de integración a proyectos</p>
        </div>
        <div class="col-md-6 text-md-end mt-2 mt-md-0">
            <!-- Filtro independiente de periodo -->
            <form class="d-inline-flex align-items-center gap-2" method="GET" id="formPeriodo">
                <input type="hidden" name="estado" value="<?= htmlspecialchars($filtros['estado'] ?? '') ?>">
                <input type="hidden" name="buscar" value="<?= htmlspecialchars($filtros['buscar'] ?? '') ?>">
                <label class="mb-0 text-nowrap fw-semibold">Periodo:</label>
                <select name="periodo" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">Todos</option>
                    <?php foreach ($periodos as $p): ?>
                        <option value="<?= $p['id_periodos'] ?>"
                            <?= ($filtros['periodo'] ?? '') == $p['id_periodos'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($p['periodo']) ?>
                            <?= !empty($p['estado']) ? ' (Activo)' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>
    </div>

    <!-- FLASH -->
    <?php if ($mensaje): ?>
        <?php
        $tipo_flash = $mensaje['tipo'] ?: 'info';
        $clases_alerta = ['exito' => 'alert-success', 'peligro' => 'alert-danger', 'info' => 'alert-info'];
        $iconos = ['exito' => 'bi-check-circle-fill', 'peligro' => 'bi-x-circle-fill', 'info' => 'bi-info-circle-fill'];
        $clase_alerta = $clases_alerta[$tipo_flash] ?? 'alert-secondary';
        $icono = $iconos[$tipo_flash] ?? 'bi-bell-fill';
        ?>
        <div class="alert <?= $clase_alerta ?> alert-dismissible fade show">
            <i class="bi <?= $icono ?> me-2"></i><?= htmlspecialchars($mensaje['texto']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- TARJETAS RESUMEN -->
    <div class="row mb-4 g-3">
        <?php
        $cards_resumen = [
            ['clase' => 'c-total', 'borde' => 'border-primary',   'color' => 'text-primary',   'num' => $resumen['total']        ?? 0, 'lbl' => 'Total'],
            ['clase' => 'c-pend',  'borde' => 'border-secondary', 'color' => 'text-secondary',  'num' => $resumen['pendientes']   ?? 0, 'lbl' => 'Pendientes'],
            ['clase' => 'c-rev',   'borde' => 'border-info',      'color' => 'text-info',       'num' => $resumen['en_revision']  ?? 0, 'lbl' => 'En revisión'],
            ['clase' => 'c-corr',  'borde' => 'border-warning',   'color' => 'text-warning',    'num' => $resumen['correcciones'] ?? 0, 'lbl' => 'Correcciones'],
            ['clase' => 'c-acep',  'borde' => 'border-success',   'color' => 'text-success',    'num' => $resumen['aceptadas']    ?? 0, 'lbl' => 'Aceptadas'],
            ['clase' => 'c-rech',  'borde' => 'border-danger',    'color' => 'text-danger',     'num' => $resumen['rechazadas']   ?? 0, 'lbl' => 'Rechazadas'],
        ];
        foreach ($cards_resumen as $c): ?>
            <div class="col-6 col-md-4 col-xl-2">
                <div class="card text-center <?= $c['borde'] ?> shadow-sm h-100">
                    <div class="card-body">
                        <div class="display-6 fw-bold <?= $c['color'] ?>"><?= $c['num'] ?></div>
                        <div class="text-muted small"><?= $c['lbl'] ?></div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- FILTROS SECUNDARIOS -->
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body py-2">
            <form method="GET" class="row g-2 align-items-end">
                <!-- Mantener periodo activo -->
                <input type="hidden" name="periodo" value="<?= htmlspecialchars($filtros['periodo'] ?? '') ?>">

                <!-- Buscar -->
                <div class="col-12 col-md-5">
                    <label class="form-label mb-1 small fw-semibold">Buscar</label>
                    <input type="text" name="buscar" class="form-control form-control-sm"
                        placeholder="Proyecto o investigador…"
                        value="<?= htmlspecialchars($filtros['buscar'] ?? '') ?>">
                </div>

                <!-- Estado -->
                <div class="col-6 col-md-3">
                    <label class="form-label mb-1 small fw-semibold">Estado</label>
                    <select name="estado" class="form-select form-select-sm">
                        <option value="">Todos</option>
                        <?php foreach (
                            [
                                'pendiente'    => 'Pendiente',
                                'en_revision'  => 'En revisión',
                                'correcciones' => 'Correcciones',
                                'aceptado'     => 'Aceptado',
                                'rechazado'    => 'Rechazado',
                                'cancelado'    => 'Cancelado',
                            ] as $val => $lbl
                        ): ?>
                            <option value="<?= $val ?>" <?= ($filtros['estado'] ?? '') === $val ? 'selected' : '' ?>>
                                <?= $lbl ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Botones -->
                <div class="col-auto d-flex gap-1 align-items-end">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="bi bi-funnel-fill me-1"></i>Filtrar
                    </button>
                    <a href="index.php<?= !empty($filtros['periodo']) ? '?periodo=' . urlencode($filtros['periodo']) : '' ?>"
                        class="btn btn-secondary btn-sm">
                        <i class="bi bi-arrow-counterclockwise me-1"></i>Limpiar
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!--  TABLA ESCRITORIO  -->
    <?php if (!empty($solicitudes)): ?>

        <div class="ms-tabla-wrap">
            <div style="overflow-x:auto;">
                <table class="ms-tabla">
                    <thead>
                        <tr>
                            <?php foreach ($ctrl->encabezados() as $h): ?>
                                <th><?= $h ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($solicitudes as $i => $sol): ?>
                            <?php
                            $offset = ($paginacion['pagina'] - 1) * $paginacion['por_pagina'];
                            ?>
                            <tr>
                                <td class="text-muted" style="font-size:.78rem;">
                                    <?= $offset + $i + 1 ?>
                                </td>
                                <td class="td-proyecto">
                                    <strong><?= htmlspecialchars(mb_strimwidth($sol['proyecto_titulo'], 0, 45, '…')) ?></strong>
                                    <span><?= ucfirst(htmlspecialchars($sol['modalidad'] ?? '')) ?></span>
                                    <?php if ($sol['estado'] === 'correcciones' && $sol['ultimo_comentario_inv']): ?>
                                        <div class="ms-nota-corr">
                                            <i class="bi bi-exclamation-triangle-fill"></i>
                                            <?= htmlspecialchars(mb_strimwidth($sol['ultimo_comentario_inv'], 0, 40, '…')) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td style="font-size:.845rem;"><?= htmlspecialchars($sol['investigador']) ?></td>
                                <td style="font-size:.82rem; white-space:nowrap;"><?= htmlspecialchars($sol['periodo']) ?></td>
                                <td style="text-align:center;"><?= $sol['semestre'] ? $sol['semestre'] . '°' : '—' ?></td>
                                <td style="text-align:center;"><?= $sol['promedio'] ?? '—' ?></td>
                                <td style="font-size:.82rem; white-space:nowrap;"><?= $sol['fecha_envio'] ?></td>
                                <td><?= $ctrl->badgeEstado($sol['estado']) ?></td>
                                <td>
                                    <div style="display:flex;gap:.35rem;flex-wrap:wrap;">
                                        <?= $ctrl->botonesAccion($sol['id_solicitud_proyecto'], $sol['estado']) ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!--  CARDS MÓVIL ═ -->
        <div class="ms-cards-movil-wrap">
            <?php foreach ($solicitudes as $sol): ?>
                <div class="ms-card-movil">
                    <div class="ms-card-movil-header">
                        <div class="ms-card-movil-titulo">
                            <?= htmlspecialchars(mb_strimwidth($sol['proyecto_titulo'], 0, 50, '…')) ?>
                        </div>
                        <?= $ctrl->badgeEstado($sol['estado']) ?>
                    </div>
                    <div class="ms-card-movil-body">
                        <dl style="margin:0;">
                            <div class="ms-card-movil-fila">
                                <dt>Investigador</dt>
                                <dd><?= htmlspecialchars($sol['investigador']) ?></dd>
                            </div>
                            <div class="ms-card-movil-fila">
                                <dt>Periodo</dt>
                                <dd><?= htmlspecialchars($sol['periodo']) ?></dd>
                            </div>
                            <div class="ms-card-movil-fila">
                                <dt>Fecha</dt>
                                <dd><?= $sol['fecha_envio'] ?></dd>
                            </div>
                            <?php if ($sol['promedio']): ?>
                                <div class="ms-card-movil-fila">
                                    <dt>Promedio</dt>
                                    <dd><?= $sol['promedio'] ?></dd>
                                </div>
                            <?php endif; ?>
                        </dl>
                        <?php if ($sol['estado'] === 'correcciones' && $sol['ultimo_comentario_inv']): ?>
                            <div class="ms-nota-corr mt-2" style="display:flex;">
                                <i class="bi bi-exclamation-triangle-fill me-1"></i>
                                <?= htmlspecialchars(mb_strimwidth($sol['ultimo_comentario_inv'], 0, 60, '…')) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="ms-card-movil-acciones">
                        <?= $ctrl->botonesAccion($sol['id_solicitud_proyecto'], $sol['estado']) ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!--  PAGINACIÓN  -->
        <?php if ($paginacion['total_paginas'] > 1):
            $qBase = http_build_query(array_filter([
                'periodo' => $filtros['periodo'] ?? '',
                'buscar'  => $filtros['buscar']  ?? '',
                'estado'  => $filtros['estado']  ?? '',
            ]));
            $entidad = 'resultados';
            include __DIR__ . '../../../publico/incluido/_paginacion.php';
        endif; ?>

    <?php else: ?>
        <!-- ESTADO VACÍO -->
        <div class="ms-tabla-wrap">
            <div class="ms-empty">
                <i class="bi bi-inbox"></i>
                <p>
                    <?php if (!empty($filtros['buscar']) || !empty($filtros['estado'])): ?>
                        No hay solicitudes que coincidan con los filtros aplicados.
                    <?php else: ?>
                        Aún no has enviado ninguna solicitud de integración.
                    <?php endif; ?>
                </p>
                <a href="/ITSFCP-PROYECTOS/Vistas/menu/principal.php"
                    class="ms-btn-filtrar" style="display:inline-flex;">
                    <i class="bi bi-search"></i> Explorar proyectos
                </a>
            </div>
        </div>
    <?php endif; ?>

</div>

<!--  MODAL CANCELAR ═ -->
<div class="ms-modal-overlay" id="modalCancelar">
    <div class="ms-modal">
        <i class="bi bi-exclamation-triangle-fill"></i>
        <h4>Cancelar solicitud</h4>
        <p id="modalCancelarTexto">¿Estás seguro de que deseas cancelar esta solicitud?</p>
        <form method="POST" action="index.php">
            <input type="hidden" name="accion" value="cancelar">
            <input type="hidden" name="id_solicitud" id="modalCancelarId">
            <div class="ms-modal-btns">
                <button type="submit" class="ms-btn-confirmar">
                    <i class="bi bi-check-lg me-1"></i> Sí, cancelar
                </button>
                <button type="button" class="ms-btn-cerrar" onclick="cerrarModal()">
                    Cerrar
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function abrirModalCancelar(id, titulo) {
        document.getElementById('modalCancelarId').value = id;
        document.getElementById('modalCancelarTexto').textContent =
            '¿Cancelar la solicitud para "' + titulo + '"? Esta acción no se puede deshacer.';
        document.getElementById('modalCancelar').classList.add('abierto');
    }

    function cerrarModal() {
        document.getElementById('modalCancelar').classList.remove('abierto');
    }
    document.getElementById('modalCancelar').addEventListener('click', function(e) {
        if (e.target === this) cerrarModal();
    });
</script>

<?php
$contenido = ob_get_clean();
$titulo    = "Mis solicitudes";
include __DIR__ . '/../../layout.php';
?>