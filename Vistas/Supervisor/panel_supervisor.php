<?php

/**
 * panel_supervisor.php
 * Dashboard de solo lectura del supervisor — responsivo con tarjetas móvil.
 */

ini_set('display_errors', 0);
error_reporting(E_ALL);
session_start();

if (!isset($_SESSION['id_usuario'])) {
    header("Location: /index.php");
    exit;
}

$rol = strtolower($_SESSION['rol'] ?? '');
$id  = $_SESSION['id_usuario'];

if (strtolower($_SESSION['rol'] ?? '') !== 'supervisor') {
    header("Location: /Vistas/Principal/index.php");
    exit;
}

require_once __DIR__ . '/../../Controladores/SupervisorControlador.php';
$ctrl = new SupervisorControlador();
$data = $ctrl->index();

extract($data);

function barraProgreso(int $aprobadas, int $total): string
{
    if ($total <= 0) return '<span class="text-muted small">Sin tareas</span>';
    $pct   = round(($aprobadas / $total) * 100);
    $color = $pct >= 80 ? 'success' : ($pct >= 40 ? 'warning' : 'danger');
    return "<div class='progress' style='height:6px;min-width:80px;border-radius:4px'>
              <div class='progress-bar bg-{$color}' style='width:{$pct}%'></div>
            </div>
            <span class='small text-muted'>{$aprobadas}/{$total} ({$pct}%)</span>";
}

ob_start();
?>

<div class="container-fluid py-4 sup-page ancho_container">

    <!--  ENCABEZADO -->
    <div class="row mb-4 align-items-center">
        <?php
        $titulo      = 'Panel del Supervisor';
        $descripcion = 'Resumen general de proyectos, estudiantes y solicitudes';
        include __DIR__ . '../../../publico/incluido/_encabezado.php';
        ?>
        <div class="col-md-6 text-md-end">
            <span class="badge-modo"><i class="bi bi-eye me-1"></i>Modo visualización</span>
        </div>
    </div>
    <br><br>

    <!--  FILTRO GLOBAL: PERIODO -->
    <div class="filter-periodo">
        <label><i class="bi bi-calendar3 me-1"></i>Filtrar por periodo académico:</label>
        <form method="GET" class="d-flex align-items-center gap-2 flex-wrap mb-0">
            <input type="hidden" name="tab" value="<?= htmlspecialchars($filtros['tab']) ?>">
            <select name="periodo" class="form-select form-select-sm" style="min-width:200px"
                onchange="this.form.submit()">
                <option value="">Todos los periodos</option>
                <?php foreach ($periodos as $per): ?>
                    <option value="<?= $per['id_periodos'] ?>"
                        <?= $filtros['periodo'] == $per['id_periodos'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($per['periodo']) ?>
                        <?= ($per['estado'] == "Activo") ? ' ★ Activo' : '' ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if ($filtros['periodo']): ?>
                <a href="?tab=<?= $filtros['tab'] ?>" class="btn btn-secondary btn-sm">
                    <i class="bi bi-x-circle me-1"></i>Limpiar
                </a>
            <?php endif; ?>
        </form>
    </div>

    <!--  TARJETAS KPI PRINCIPALES -->
    <div class="row g-3 mb-4">

        <!-- Proyectos -->
        <div class="col-6 col-md-3">
            <div class="kpi-card azul h-100">
                <div class="kpi-icon azul"><i class="bi bi-folder2-open"></i></div>
                <div class="kpi-num"><?= $resumen['proyectos']['total_proyectos'] ?? 0 ?></div>
                <div class="fw-bold mb-2 mb-md-0">Proyectos</div>
                <div class="kpi-desc">Proyectos de investigación registrados en el sistema para el periodo seleccionado.</div>
                <div class="kpi-sub">
                    <span class="text-success"><i class="bi bi-circle-fill" style="font-size:.45rem;vertical-align:middle"></i> <?= $resumen['proyectos']['activos'] ?? 0 ?> activos</span>
                    <span class="text-warning"><i class="bi bi-circle-fill" style="font-size:.45rem;vertical-align:middle"></i> <?= $resumen['proyectos']['por_aprobar'] ?? 0 ?> por aprobar</span>
                    <span class="text-info"><i class="bi bi-circle-fill" style="font-size:.45rem;vertical-align:middle"></i> <?= $resumen['proyectos']['por_cerrar'] ?? 0 ?> por cerrar</span>
                    <span class="text-danger"><i class="bi bi-circle-fill" style="font-size:.45rem;vertical-align:middle"></i> <?= $resumen['proyectos']['rechazados'] ?? 0 ?> rechazados</span>
                    <span class="text-secondary"><i class="bi bi-circle-fill" style="font-size:.45rem;vertical-align:middle"></i> <?= $resumen['proyectos']['vencidos'] ?? 0 ?> vencidos</span>
                    <span class="text-dark"><i class="bi bi-circle-fill" style="font-size:.45rem;vertical-align:middle"></i> <?= $resumen['proyectos']['cerrados'] ?? 0 ?> cerrados</span>
                </div>
            </div>
        </div>

        <!-- Estudiantes -->
        <div class="col-6 col-md-3">
            <div class="kpi-card verde h-100">
                <div class="kpi-icon verde"><i class="bi bi-people-fill"></i></div>
                <div class="kpi-num"><?= $resumen['estudiantes']['total_estudiantes'] ?? 0 ?></div>
                <div class="fw-bold mb-2 mb-md-0">Estudiantes</div>
                <div class="kpi-desc">Estudiantes integrados a algún proyecto de investigación, incluyendo activos y con proyecto concluido.</div>
                <div class="kpi-sub">
                    <span class="text-success"><i class="bi bi-circle-fill" style="font-size:.45rem;vertical-align:middle"></i> <?= $resumen['estudiantes']['activos'] ?? 0 ?> activos</span>
                    <span class="text-primary"><i class="bi bi-circle-fill" style="font-size:.45rem;vertical-align:middle"></i> <?= $resumen['estudiantes']['concluidos'] ?? 0 ?> concluidos</span>
                    <span class="text-danger"><i class="bi bi-circle-fill" style="font-size:.45rem;vertical-align:middle"></i> <?= $resumen['estudiantes']['bajas'] ?? 0 ?> bajas</span>
                </div>
            </div>
        </div>

        <!-- Solicitudes de integración -->
        <div class="col-6 col-md-3">
            <div class="kpi-card amber h-100">
                <div class="kpi-icon amber"><i class="bi bi-envelope-paper-fill"></i></div>
                <div class="kpi-num"><?= $resumen['solicitudes']['total_solicitudes'] ?? 0 ?></div>
                <div class="fw-bold mb-2 mb-md-0">Solicitudes de integración</div>
                <div class="kpi-desc">Solicitudes enviadas por estudiantes para incorporarse a un proyecto de investigación.</div>
                <div class="kpi-sub">
                    <span class="text-secondary"><i class="bi bi-circle-fill" style="font-size:.45rem;vertical-align:middle"></i> <?= $resumen['solicitudes']['pendientes'] ?? 0 ?> pendientes</span>
                    <span class="text-info"><i class="bi bi-circle-fill" style="font-size:.45rem;vertical-align:middle"></i> <?= $resumen['solicitudes']['en_revision'] ?? 0 ?> en revisión</span>
                    <span class="text-warning"><i class="bi bi-circle-fill" style="font-size:.45rem;vertical-align:middle"></i> <?= $resumen['solicitudes']['correcciones'] ?? 0 ?> correcciones</span>
                    <span class="text-success"><i class="bi bi-circle-fill" style="font-size:.45rem;vertical-align:middle"></i> <?= $resumen['solicitudes']['aceptadas'] ?? 0 ?> aceptadas</span>
                    <span class="text-danger"><i class="bi bi-circle-fill" style="font-size:.45rem;vertical-align:middle"></i> <?= $resumen['solicitudes']['rechazadas'] ?? 0 ?> rechazadas</span>
                </div>
            </div>
        </div>

        <!-- Tareas / secciones del documento -->
        <div class="col-6 col-md-3">
            <div class="kpi-card rojo h-100">
                <div class="kpi-icon rojo"><i class="bi bi-list-task"></i></div>
                <div class="kpi-num"><?= $resumen['tareas']['total_tareas'] ?? 0 ?></div>
                <div class="fw-bold mb-2 mb-md-0">Secciones del documento</div>
                <div class="kpi-desc">Instancias de secciones del documento asignadas a cada estudiante en todos los proyectos (excluye "Sin activar").</div>
                <div class="kpi-sub">
                    <span class="text-success"><i class="bi bi-circle-fill" style="font-size:.45rem;vertical-align:middle"></i> <?= $resumen['tareas']['aprobadas'] ?? 0 ?> aprobadas</span>
                    <span class="text-primary"><i class="bi bi-circle-fill" style="font-size:.45rem;vertical-align:middle"></i> <?= $resumen['tareas']['entregadas'] ?? 0 ?> entregadas</span>
                    <span class="text-info"><i class="bi bi-circle-fill" style="font-size:.45rem;vertical-align:middle"></i> <?= $resumen['tareas']['en_revision'] ?? 0 ?> en revisión</span>
                    <span class="text-warning"><i class="bi bi-circle-fill" style="font-size:.45rem;vertical-align:middle"></i> <?= $resumen['tareas']['corregir'] ?? 0 ?> a corregir</span>
                    <span class="text-secondary"><i class="bi bi-circle-fill" style="font-size:.45rem;vertical-align:middle"></i> <?= $resumen['tareas']['pendientes'] ?? 0 ?> pendientes</span>
                    <span class="text-danger"><i class="bi bi-circle-fill" style="font-size:.45rem;vertical-align:middle"></i> <?= $resumen['tareas']['vencidas'] ?? 0 ?> vencidas</span>
                </div>
            </div>
        </div>

    </div>

    <!--  MINI TARJETAS SOLICITUDES -->
    <div class="row g-2 mb-4">
        <?php
        $tabs_cards = [
            ['label' => 'Pendientes',   'val' => $resumen['solicitudes']['pendientes']   ?? 0, 'color' => '#718096', 'text_color' => 'text-secondary'],
            ['label' => 'En revisión',  'val' => $resumen['solicitudes']['en_revision']  ?? 0, 'color' => '#0EA5E9', 'text_color' => 'text-info'],
            ['label' => 'Correcciones', 'val' => $resumen['solicitudes']['correcciones'] ?? 0, 'color' => '#D4A017', 'text_color' => 'text-warning'],
            ['label' => 'Aceptadas',    'val' => $resumen['solicitudes']['aceptadas']    ?? 0, 'color' => '#22C55E', 'text_color' => 'text-success'],
            ['label' => 'Rechazadas',   'val' => $resumen['solicitudes']['rechazadas']   ?? 0, 'color' => '#C41230', 'text_color' => 'text-danger'],
        ];
        $total_sol = max(1, array_sum(array_column($tabs_cards, 'val')));
        foreach ($tabs_cards as $tc):
            $pct = round(($tc['val'] / $total_sol) * 100);
        ?>
            <div class="col">
                <div class="mini-stat-card">
                    <div class="val <?= $tc['text_color'] ?>"><?= $tc['val'] ?></div>
                    <div class="lbl"><?= $tc['label'] ?></div>
                    <div class="barra" style="background:<?= $tc['color'] ?>;width:<?= max(4, $pct) ?>%"></div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!--  TABS DE NAVEGACIÓN -->
    <div class="nav-tabs-custom">
        <?php
        $tabs_nav = [
            'resumen'     => ['icon' => 'bi-grid-1x2-fill',  'label' => 'Resumen'],
            'proyectos'   => ['icon' => 'bi-folder2-open',   'label' => 'Proyectos'],
            'solicitudes' => ['icon' => 'bi-envelope-paper', 'label' => 'Solicitudes'],
            'etapas'      => ['icon' => 'bi-layers-fill',    'label' => 'Etapas & Secciones'],
            'usuarios'    => ['icon' => 'bi-people-fill',    'label' => 'Estudiantes'],
        ];
        foreach ($tabs_nav as $key => $tab): ?>
            <a href="?<?= http_build_query(array_merge($_GET, ['tab' => $key])) ?>"
                class="<?= $filtros['tab'] == $key ? 'active' : '' ?>">
                <i class="<?= $tab['icon'] ?>"></i><?= $tab['label'] ?>
            </a>
        <?php endforeach; ?>
    </div>

    <?php
    // Helper: $qBase compartido para los tres tabs con paginación
    $qBase_filtros = http_build_query(array_filter([
        'tab'             => $filtros['tab'],
        'periodo'         => $filtros['periodo']         ?? '',
        'investigador'    => $filtros['investigador']    ?? '',
        'modalidad'       => $filtros['modalidad']       ?? '',
        'carrera'         => $filtros['carrera']         ?? '',
        'estado_proyecto' => $filtros['estado_proyecto'] ?? '',
        'estado_sol'      => $filtros['estado_sol']      ?? '',
        'estado_usuario'  => $filtros['estado_usuario']  ?? '',
        'buscar_proy'     => $filtros['buscar_proy']     ?? '',
        'buscar_sol'      => $filtros['buscar_sol']      ?? '',
        'buscar_usr'      => $filtros['buscar_usr']      ?? '',
        'fecha_desde'     => $filtros['fecha_desde']     ?? '',
        'fecha_hasta'     => $filtros['fecha_hasta']     ?? '',
    ]));
    ?>

    <!-- 
         TAB: RESUMEN
          -->
    <?php if ($filtros['tab'] === 'resumen'): ?>
        <div class="row g-3">

            <!-- Estado de proyectos -->
            <div class="col-md-6">
                <div class="sec-card">
                    <div class="sec-card-header">
                        <div class="sec-title"><i class="bi bi-folder-fill"></i>Estado de proyectos</div>
                        <span class="sec-sub"><?= $resumen['proyectos']['total_proyectos'] ?? 0 ?> proyectos registrados</span>
                    </div>
                    <div class="p-3">
                        <?php
                        $estados_proy = [
                            'activos'     => ['Activos (en ejecución)',           'success'],
                            'por_aprobar' => ['Por aprobar (solicitud creación)', 'warning'],
                            'por_cerrar'  => ['Por cerrar (solicitud cierre)',    'info'],
                            'rechazados'  => ['Rechazados por el supervisor',     'danger'],
                            'vencidos'    => ['Vencidos (fecha fin superada)',     'secondary'],
                            'cerrados'    => ['Cerrados oficialmente',             'dark'],
                        ];
                        $total_p = max(1, $resumen['proyectos']['total_proyectos']);
                        foreach ($estados_proy as $key => [$lbl, $color]):
                            $val = $resumen['proyectos'][$key] ?? 0;
                            $pct = round(($val / $total_p) * 100);
                        ?>
                            <div class="mb-3">
                                <div class="d-flex justify-content-between mb-1">
                                    <span style="font-size:.8rem; color:var(--tecnm-text)"><?= $lbl ?></span>
                                    <span style="font-size:.8rem; font-weight:700; color:var(--tecnm-navy)"><?= $val ?> <span class="fw-normal text-muted">(<?= $pct ?>%)</span></span>
                                </div>
                                <div class="etapa-prog">
                                    <div class="etapa-prog-inner bg-<?= $color ?>" style="width:<?= $pct ?>%"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Investigadores activos -->
            <div class="col-md-6">
                <div class="sec-card">
                    <div class="sec-card-header">
                        <div class="sec-title"><i class="bi bi-person-workspace"></i>Investigadores activos</div>
                        <span class="sec-sub">Cuerpos académicos con proyectos</span>
                    </div>
                    <div style="max-height:290px; overflow-y:auto;">
                        <?php foreach ($resumen_inv as $inv):
                            $palabras  = explode(' ', trim($inv['nombre']));
                            $iniciales = strtoupper(substr($palabras[0] ?? '', 0, 1) . substr($palabras[1] ?? '', 0, 1));
                        ?>
                            <div class="inv-row">
                                <div class="inv-avatar"><?= $iniciales ?></div>
                                <div style="flex:1; min-width:0;">
                                    <div class="inv-name"><?= htmlspecialchars($inv['nombre']) ?></div>
                                    <div class="inv-email"><?= htmlspecialchars($inv['correo_institucional']) ?></div>
                                </div>
                                <div class="text-end" style="flex-shrink:0;">
                                    <span class="inv-badge"><?= $inv['total_proyectos'] ?> proy.</span>
                                    <div style="font-size:.7rem; color:var(--tecnm-muted); margin-top:.15rem;"><?= $inv['activos'] ?> activos</div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <?php if (empty($resumen_inv)): ?>
                            <p class="text-muted text-center small py-3">Sin investigadores registrados.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Resumen de tareas por estado -->
            <div class="col-12">
                <div class="sec-card">
                    <div class="sec-card-header">
                        <div class="sec-title"><i class="bi bi-bar-chart-steps"></i>Resumen global de secciones por estado</div>
                        <span class="sec-sub">Total: <?= $resumen['tareas']['total_tareas'] ?? 0 ?> instancias activas</span>
                    </div>
                    <div class="p-3">
                        <?php
                        $estados_tareas_res = [
                            'aprobadas'   => ['Aprobadas',    'success',   $resumen['tareas']['aprobadas']   ?? 0],
                            'entregadas'  => ['Entregadas',   'primary',   $resumen['tareas']['entregadas']  ?? 0],
                            'en_revision' => ['En revisión',  'info',      $resumen['tareas']['en_revision'] ?? 0],
                            'corregir'    => ['A corregir',   'warning',   $resumen['tareas']['corregir']    ?? 0],
                            'pendientes'  => ['Pendientes',   'secondary', $resumen['tareas']['pendientes']  ?? 0],
                            'vencidas'    => ['Vencidas',     'danger',    $resumen['tareas']['vencidas']    ?? 0],
                        ];
                        $total_t = max(1, $resumen['tareas']['total_tareas'] ?? 0);
                        foreach ($estados_tareas_res as [$lbl, $color, $val]):
                            $pct = round(($val / $total_t) * 100);
                        ?>
                            <div class="mb-2">
                                <div class="d-flex justify-content-between mb-1">
                                    <span style="font-size:.8rem; color:var(--tecnm-text)"><?= $lbl ?></span>
                                    <span style="font-size:.8rem; font-weight:700; color:var(--tecnm-navy)"><?= $val ?> <span class="fw-normal text-muted">(<?= $pct ?>%)</span></span>
                                </div>
                                <div class="etapa-prog">
                                    <div class="etapa-prog-inner bg-<?= $color ?>" style="width:<?= max(0, $pct) ?>%"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Tabla de secciones del documento -->
            <div class="col-12">
                <div class="sec-card">
                    <div class="sec-card-header">
                        <div class="sec-title"><i class="bi bi-table"></i>Avance global por sección del documento de investigación</div>
                        <span class="sec-sub">Una instancia = una sección asignada a un estudiante</span>
                    </div>
                    <?php if (!empty($etapas_data['secciones'])): ?>
                        <!-- DESKTOP -->
                        <div class="d-none d-md-block table-responsive">
                            <table class="table tbl-sup mb-0">
                                <thead>
                                    <tr>
                                        <th>Sección</th>
                                        <th class="text-center">Instancias</th>
                                        <th class="text-center" style="color:#16A34A">Aprobadas</th>
                                        <th class="text-center" style="color:#2563EB">Entregadas</th>
                                        <th class="text-center" style="color:#0EA5E9">En revisión</th>
                                        <th class="text-center" style="color:#D4A017">Correcciones</th>
                                        <th class="text-center" style="color:#718096">Pendientes</th>
                                        <th class="text-center" style="color:#C41230">Vencidas</th>
                                        <th style="min-width:130px">Avance</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($etapas_data['secciones'] as $sec):
                                        $tot = max(1, $sec['total_instancias']);
                                        $pct = round(($sec['aprobadas'] / $tot) * 100);
                                        $col = $pct >= 80 ? 'success' : ($pct >= 40 ? 'warning' : 'danger');
                                    ?>
                                        <tr>
                                            <td class="fw-semibold"><?= htmlspecialchars($sec['seccion']) ?></td>
                                            <td class="text-center"><?= $sec['total_instancias'] ?></td>
                                            <td class="text-center fw-bold" style="color:#16A34A"><?= $sec['aprobadas'] ?></td>
                                            <td class="text-center" style="color:#2563EB"><?= $sec['entregadas'] ?? 0 ?></td>
                                            <td class="text-center" style="color:#0EA5E9"><?= $sec['en_revision'] ?></td>
                                            <td class="text-center" style="color:#D4A017"><?= $sec['correcciones'] ?></td>
                                            <td class="text-center text-secondary"><?= $sec['pendientes'] ?></td>
                                            <td class="text-center" style="color:#C41230"><?= $sec['vencidas'] ?></td>
                                            <td>
                                                <div class="etapa-prog">
                                                    <div class="etapa-prog-inner bg-<?= $col ?>" style="width:<?= $pct ?>%"></div>
                                                </div>
                                                <span style="font-size:.71rem; color:var(--tecnm-muted)"><?= $pct ?>%</span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <!-- MÓVIL -->
                        <div class="d-block d-md-none p-2">
                            <?php foreach ($etapas_data['secciones'] as $sec):
                                $tot = max(1, $sec['total_instancias']);
                                $pct = round(($sec['aprobadas'] / $tot) * 100);
                                $col = $pct >= 80 ? 'success' : ($pct >= 40 ? 'warning' : 'danger');
                            ?>
                                <div class="card mb-2 border shadow-sm">
                                    <div class="card-body py-2 px-3">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <span style="font-size:.8rem; font-weight:600"><?= htmlspecialchars($sec['seccion']) ?></span>
                                            <span style="font-size:.75rem; color:var(--tecnm-muted)"><?= $pct ?>%</span>
                                        </div>
                                        <div class="etapa-prog mb-2">
                                            <div class="etapa-prog-inner bg-<?= $col ?>" style="width:<?= $pct ?>%"></div>
                                        </div>
                                        <div class="row text-center g-0" style="font-size:.72rem">
                                            <div class="col"><span class="text-success fw-bold"><?= $sec['aprobadas'] ?></span><br>Aprob.</div>
                                            <div class="col"><span style="color:#2563EB"><?= $sec['entregadas'] ?? 0 ?></span><br>Entregadas</div>
                                            <div class="col"><span style="color:#0EA5E9"><?= $sec['en_revision'] ?></span><br>Revisión</div>
                                            <div class="col"><span class="text-warning"><?= $sec['correcciones'] ?></span><br>Correc.</div>
                                            <div class="col"><span class="text-secondary"><?= $sec['pendientes'] ?></span><br>Pend.</div>
                                            <div class="col"><span class="text-danger"><?= $sec['vencidas'] ?></span><br>Venc.</div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-muted text-center py-3 small">Sin datos de secciones para este periodo.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- 
         TAB: PROYECTOS
          -->
    <?php elseif ($filtros['tab'] === 'proyectos'): ?>

        <div class="filter-bar">
            <form method="GET" class="row g-2 align-items-end mb-0">
                <input type="hidden" name="tab" value="proyectos">
                <input type="hidden" name="periodo" value="<?= htmlspecialchars($filtros['periodo']) ?>">
                <div class="col-12 col-md-3">
                    <input type="text" name="buscar_proy" value="<?= htmlspecialchars($filtros['buscar_proy']) ?>"
                        class="form-control form-control-sm" placeholder="Buscar título o investigador…">
                </div>
                <div class="col-6 col-md-2">
                    <select name="estado_proyecto" class="form-select form-select-sm">
                        <option value="">Todos los estados</option>
                        <?php foreach ($estados_p as $ep): ?>
                            <option value="<?= htmlspecialchars($ep['nombre']) ?>"
                                <?= $filtros['estado_proyecto'] == $ep['nombre'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($ep['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-md-3">
                    <select name="investigador" class="form-select form-select-sm">
                        <option value="">Todos los investigadores</option>
                        <?php foreach ($investigadores as $inv): ?>
                            <option value="<?= $inv['id_usuarios'] ?>"
                                <?= $filtros['investigador'] == $inv['id_usuarios'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($inv['nombre_completo']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <select name="modalidad" class="form-select form-select-sm">
                        <option value="">Modalidad</option>
                        <option value="virtual" <?= $filtros['modalidad'] === 'virtual' ? 'selected' : '' ?>>Virtual</option>
                        <option value="fisico" <?= $filtros['modalidad'] === 'fisico'  ? 'selected' : '' ?>>Físico</option>
                        <option value="mixto" <?= $filtros['modalidad'] === 'mixto'   ? 'selected' : '' ?>>Mixto</option>
                    </select>
                </div>
                <div class="col-auto d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm">Filtrar</button>
                    <a href="?tab=proyectos&periodo=<?= $filtros['periodo'] ?>" class="btn btn-secondary btn-sm">Limpiar</a>
                </div>
            </form>
        </div>

        <!-- DESKTOP -->
        <div class="sec-card d-none d-md-block">
            <div class="table-responsive">
                <table class="table tbl-sup mb-0">
                    <thead>
                        <tr>
                            <th>Proyecto</th>
                            <th>Investigador</th>
                            <th class="text-center">Estado</th>
                            <th class="text-center">Modalidad</th>
                            <th class="text-center">Alumnos</th>
                            <th class="text-center">Sol. pend.</th>
                            <th class="text-center">Tareas venc.</th>
                            <th class="text-center">Fechas</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($proyectos as $p): ?>
                            <tr <?= $p['tareas_vencidas'] > 0 ? "style='background:#FFF5F5'" : '' ?>>
                                <td>
                                    <div class="fw-semibold" style="max-width:200px; font-size:.82rem">
                                        <?= htmlspecialchars(mb_substr($p['titulo'], 0, 60)) ?><?= strlen($p['titulo']) > 60 ? '…' : '' ?>
                                    </div>
                                </td>
                                <td style="font-size:.8rem"><?= htmlspecialchars($p['investigador_nombre']) ?></td>
                                <td class="text-center"><?= $ctrl->badgeEstadoProyecto($p['estado']) ?></td>
                                <td class="text-center">
                                    <span class="badge text-dark text-capitalize border"><?= $p['modalidad'] ?></span>
                                </td>
                                <td class="text-center">
                                    <span class="fw-bold"><?= $p['alumnos_activos'] ?></span>
                                    <span class="text-muted"> / <?= $p['cantidad_estudiante'] ?></span>
                                </td>
                                <td class="text-center">
                                    <?= $p['sol_pendientes'] > 0
                                        ? "<span class='badge bg-warning text-dark'>{$p['sol_pendientes']}</span>"
                                        : '<span class="text-muted">—</span>' ?>
                                </td>
                                <td class="text-center">
                                    <?= $p['tareas_vencidas'] > 0
                                        ? "<span class='badge bg-danger'>{$p['tareas_vencidas']}</span>"
                                        : '<span class="text-success"><i class="bi bi-check-circle-fill"></i></span>' ?>
                                </td>
                                <td class="text-muted" style="font-size:.75rem; white-space:nowrap">
                                    <?= date('d/m/Y', strtotime($p['fecha_inicio'])) ?><br>
                                    <?= date('d/m/Y', strtotime($p['fecha_fin'])) ?>
                                </td>
                                <td>
                                    <a href="detalle_proyecto.php?id=<?= $p['id_proyectos'] ?>"
                                        class="btn btn-sm btn-primary" style="font-size:.75rem">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($proyectos)): ?>
                            <tr>
                                <td colspan="9" class="text-center text-muted py-4 small">No se encontraron proyectos.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- MÓVIL -->
        <div class="d-block d-md-none">
            <?php foreach ($proyectos as $p): ?>
                <div class="card shadow-sm mb-3 <?= $p['tareas_vencidas'] > 0 ? 'border-danger' : '' ?>">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-1">
                            <h6 class="fw-bold mb-0" style="font-size:.85rem">
                                <?= htmlspecialchars(mb_substr($p['titulo'], 0, 55)) ?><?= strlen($p['titulo']) > 55 ? '…' : '' ?>
                            </h6>
                            <?= $ctrl->badgeEstadoProyecto($p['estado']) ?>
                        </div>
                        <p class="small mb-1"><strong>Investigador:</strong> <?= htmlspecialchars($p['investigador_nombre']) ?></p>
                        <p class="small mb-1"><strong>Alumnos:</strong> <?= $p['alumnos_activos'] ?>/<?= $p['cantidad_estudiante'] ?> &mdash; <strong>Modalidad:</strong> <?= $p['modalidad'] ?></p>
                        <p class="small mb-1"><strong>Fechas:</strong> <?= date('d/m/Y', strtotime($p['fecha_inicio'])) ?> – <?= date('d/m/Y', strtotime($p['fecha_fin'])) ?></p>
                        <?php if ($p['tareas_vencidas'] > 0): ?>
                            <span class="badge bg-danger mb-1"><?= $p['tareas_vencidas'] ?> secciones vencidas</span>
                        <?php endif; ?>
                        <div class="mt-2">
                            <a href="detalle_proyecto.php?id=<?= $p['id_proyectos'] ?>" class="btn btn-primary btn-sm">
                                <i class="bi bi-eye me-1"></i>Ver detalle
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php if (empty($proyectos)): ?>
                <div class="alert alert-info text-center small">No se encontraron proyectos.</div>
            <?php endif; ?>
        </div>

        <?php
        // PAGINACIÓN proyectos — FIX: sin <?= y con barra en include
        $qBase        = $qBase_filtros;
        $clave_pagina = 'pag_proy';
        $entidad      = 'proyectos';
        $sm           = true;
        $paginacion   = $pag_proy;
        include __DIR__ . '../../../publico/incluido/_paginacion.php';
        ?>

        <!-- 
         TAB: SOLICITUDES
          -->
    <?php elseif ($filtros['tab'] === 'solicitudes'): ?>

        <div class="filter-bar">
            <form method="GET" class="row g-2 align-items-end mb-0">
                <input type="hidden" name="tab" value="solicitudes">
                <input type="hidden" name="periodo" value="<?= htmlspecialchars($filtros['periodo']) ?>">
                <div class="col-12 col-md-3">
                    <input type="text" name="buscar_sol" value="<?= htmlspecialchars($filtros['buscar_sol']) ?>"
                        class="form-control form-control-sm" placeholder="Nombre, matrícula o proyecto…">
                </div>
                <div class="col-6 col-md-2">
                    <select name="estado_sol" class="form-select form-select-sm">
                        <option value="">Todos los estados</option>
                        <?php foreach (['pendiente' => 'Pendiente', 'en_revision' => 'En revisión', 'correcciones' => 'Correcciones', 'aceptado' => 'Aceptado', 'rechazado' => 'Rechazado'] as $v => $l): ?>
                            <option value="<?= $v ?>" <?= $filtros['estado_sol'] === $v ? 'selected' : '' ?>><?= $l ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <select name="carrera" class="form-select form-select-sm">
                        <option value="">Todas las carreras</option>
                        <?php foreach ($carreras as $c): ?>
                            <option value="<?= $c['id_carrera'] ?>" <?= $filtros['carrera'] == $c['id_carrera'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c['nombre_carrera']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <input type="date" name="fecha_desde" value="<?= $filtros['fecha_desde'] ?>" class="form-control form-control-sm">
                </div>
                <div class="col-6 col-md-2">
                    <input type="date" name="fecha_hasta" value="<?= $filtros['fecha_hasta'] ?>" class="form-control form-control-sm">
                </div>
                <div class="col-auto d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm">Filtrar</button>
                    <a href="?tab=solicitudes&periodo=<?= $filtros['periodo'] ?>" class="btn btn-secondary btn-sm">Limpiar</a>
                </div>
            </form>
        </div>

        <!-- DESKTOP -->
        <div class="sec-card d-none d-md-block">
            <div class="table-responsive">
                <table class="table tbl-sup mb-0">
                    <thead>
                        <tr>
                            <th>Estudiante</th>
                            <th>Matrícula</th>
                            <th>Carrera</th>
                            <th>Proyecto</th>
                            <th>Investigador</th>
                            <th class="text-center">Estado</th>
                            <th class="text-center">Semestre</th>
                            <th class="text-center">Promedio</th>
                            <th class="text-center">Fecha envío</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($solicitudes as $s): ?>
                            <tr>
                                <td class="fw-semibold"><?= htmlspecialchars($s['estudiante_nombre']) ?></td>
                                <td><?= htmlspecialchars($s['matricula']) ?></td>
                                <td style="font-size:.78rem"><?= htmlspecialchars(mb_substr($s['carrera'], 0, 30)) ?></td>
                                <td style="max-width:180px; font-size:.78rem">
                                    <?= htmlspecialchars(mb_substr($s['proyecto_titulo'], 0, 50)) ?><?= strlen($s['proyecto_titulo']) > 50 ? '…' : '' ?>
                                </td>
                                <td style="font-size:.78rem"><?= htmlspecialchars($s['investigador_nombre']) ?></td>
                                <td class="text-center"><?= $ctrl->badgeEstadoSolicitud($s['estado']) ?></td>
                                <td class="text-center"><?= $s['semestre'] ?? '—' ?>°</td>
                                <td class="text-center"><?= $s['promedio'] ? number_format($s['promedio'], 1) : '—' ?></td>
                                <td class="text-center text-muted" style="font-size:.75rem; white-space:nowrap">
                                    <?= date('d/m/Y', strtotime($s['fecha_envio'])) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($solicitudes)): ?>
                            <tr>
                                <td colspan="9" class="text-center text-muted py-4 small">No se encontraron solicitudes.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- MÓVIL -->
        <div class="d-block d-md-none">
            <?php foreach ($solicitudes as $s): ?>
                <div class="card shadow-sm mb-3">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <div class="fw-semibold small"><?= htmlspecialchars($s['estudiante_nombre']) ?></div>
                                <div class="text-muted" style="font-size:.72rem"><?= htmlspecialchars($s['matricula']) ?></div>
                            </div>
                            <?= $ctrl->badgeEstadoSolicitud($s['estado']) ?>
                        </div>
                        <p class="small mb-1"><strong>Proyecto:</strong> <?= htmlspecialchars(mb_substr($s['proyecto_titulo'], 0, 50)) ?><?= strlen($s['proyecto_titulo']) > 50 ? '…' : '' ?></p>
                        <p class="small mb-1"><strong>Investigador:</strong> <?= htmlspecialchars($s['investigador_nombre']) ?></p>
                        <p class="small mb-0"><strong>Carrera:</strong> <?= htmlspecialchars(mb_substr($s['carrera'], 0, 35)) ?> &mdash; <strong>Sem. <?= $s['semestre'] ?? '—' ?>°</strong></p>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php if (empty($solicitudes)): ?>
                <div class="alert alert-info text-center small">No se encontraron solicitudes.</div>
            <?php endif; ?>
        </div>

        <?php
        // PAGINACIÓN solicitudes
        $qBase        = $qBase_filtros;
        $clave_pagina = 'pag_sol';
        $entidad      = 'solicitudes';
        $sm           = true;
        $paginacion   = $pag_sol;
        include __DIR__ . '../../../publico/incluido/_paginacion.php';
        ?>

        <!-- 
         TAB: ETAPAS & SECCIONES
          -->
    <?php elseif ($filtros['tab'] === 'etapas'): ?>

        <div class="row g-3 mb-4">
            <?php foreach ($etapas_data['etapas'] as $etapa):
                $tot_et = max(1, $etapa['total']);
                $pct_et = round(($etapa['completados'] / $tot_et) * 100);
                $col_et = $pct_et >= 80 ? 'success' : ($pct_et >= 40 ? 'warning' : 'danger');
            ?>
                <div class="col-12 col-md-4">
                    <div class="sec-card h-100">
                        <div class="sec-card-header">
                            <div class="sec-title"><?= htmlspecialchars($etapa['etapa']) ?></div>
                            <span class="badge bg-<?= $etapa['categoria'] === 'final' ? 'dark' : 'secondary' ?>">
                                <?= ucfirst($etapa['categoria']) ?>
                            </span>
                        </div>
                        <div class="p-3">
                            <div class="d-flex justify-content-between mb-1" style="font-size:.78rem">
                                <span>Avance global</span>
                                <span class="fw-bold"><?= $pct_et ?>%</span>
                            </div>
                            <div class="etapa-prog mb-3">
                                <div class="etapa-prog-inner bg-<?= $col_et ?>" style="width:<?= $pct_et ?>%"></div>
                            </div>
                            <div class="row text-center g-1" style="font-size:.75rem">
                                <div class="col-3">
                                    <div class="text-muted">Total</div>
                                    <div class="fw-bold"><?= $etapa['total'] ?></div>
                                </div>
                                <div class="col-3">
                                    <div class="text-secondary">Pend.</div>
                                    <div class="fw-bold text-secondary"><?= $etapa['pendientes'] ?></div>
                                </div>
                                <div class="col-3">
                                    <div style="color:var(--tecnm-blue)">Proceso</div>
                                    <div class="fw-bold" style="color:var(--tecnm-blue)"><?= $etapa['en_proceso'] ?></div>
                                </div>
                                <div class="col-3">
                                    <div class="text-success">Compl.</div>
                                    <div class="fw-bold text-success"><?= $etapa['completados'] ?></div>
                                </div>
                            </div>
                            <?php if ($etapa['rechazados'] > 0): ?>
                                <div class="mt-2 text-center">
                                    <span class="badge bg-danger" style="font-size:.7rem"><?= $etapa['rechazados'] ?> rechazadas</span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php if (empty($etapas_data['etapas'])): ?>
                <div class="col-12">
                    <p class="text-muted text-center small">Sin etapas configuradas.</p>
                </div>
            <?php endif; ?>
        </div>

        <div class="sec-card">
            <div class="sec-card-header">
                <div class="sec-title"><i class="bi bi-list-columns"></i>Detalle por sección del documento</div>
                <span class="sec-sub">Instancias totales en el sistema</span>
            </div>
            <div class="d-none d-md-block table-responsive">
                <table class="table tbl-sup mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Sección</th>
                            <th class="text-center">Instancias</th>
                            <th class="text-center" style="color:#16A34A">Aprobadas</th>
                            <th class="text-center" style="color:#2563EB">Entregadas</th>
                            <th class="text-center" style="color:#0EA5E9">En revisión</th>
                            <th class="text-center" style="color:#D4A017">Correcciones</th>
                            <th class="text-center" style="color:#718096">Pendientes</th>
                            <th class="text-center" style="color:#C41230">Vencidas</th>
                            <th style="min-width:130px">Avance</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($etapas_data['secciones'] as $i => $sec):
                            $tot_s = max(1, $sec['total_instancias']);
                            $pct_s = round(($sec['aprobadas'] / $tot_s) * 100);
                            $col_s = $pct_s >= 80 ? 'success' : ($pct_s >= 40 ? 'warning' : 'danger');
                        ?>
                            <tr>
                                <td class="text-muted"><?= $i + 1 ?></td>
                                <td class="fw-semibold"><?= htmlspecialchars($sec['seccion']) ?></td>
                                <td class="text-center"><?= $sec['total_instancias'] ?></td>
                                <td class="text-center fw-bold" style="color:#16A34A"><?= $sec['aprobadas'] ?></td>
                                <td class="text-center" style="color:#2563EB"><?= $sec['entregadas'] ?? 0 ?></td>
                                <td class="text-center" style="color:#0EA5E9"><?= $sec['en_revision'] ?></td>
                                <td class="text-center" style="color:#D4A017"><?= $sec['correcciones'] ?></td>
                                <td class="text-center text-secondary"><?= $sec['pendientes'] ?></td>
                                <td class="text-center" style="color:#C41230"><?= $sec['vencidas'] ?></td>
                                <td>
                                    <div class="etapa-prog">
                                        <div class="etapa-prog-inner bg-<?= $col_s ?>" style="width:<?= $pct_s ?>%"></div>
                                    </div>
                                    <span style="font-size:.71rem; color:var(--tecnm-muted)"><?= $pct_s ?>%</span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($etapas_data['secciones'])): ?>
                            <tr>
                                <td colspan="10" class="text-center text-muted py-3 small">Sin datos de secciones.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <!-- MÓVIL secciones -->
            <div class="d-block d-md-none p-2">
                <?php foreach ($etapas_data['secciones'] as $i => $sec):
                    $tot_s = max(1, $sec['total_instancias']);
                    $pct_s = round(($sec['aprobadas'] / $tot_s) * 100);
                    $col_s = $pct_s >= 80 ? 'success' : ($pct_s >= 40 ? 'warning' : 'danger');
                ?>
                    <div class="card mb-2 border shadow-sm">
                        <div class="card-body py-2 px-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span style="font-size:.8rem; font-weight:600"><?= $i + 1 ?>. <?= htmlspecialchars($sec['seccion']) ?></span>
                                <span style="font-size:.75rem; color:var(--tecnm-muted)"><?= $pct_s ?>%</span>
                            </div>
                            <div class="etapa-prog mb-2">
                                <div class="etapa-prog-inner bg-<?= $col_s ?>" style="width:<?= $pct_s ?>%"></div>
                            </div>
                            <div class="row text-center g-0" style="font-size:.72rem">
                                <div class="col"><span class="text-success fw-bold"><?= $sec['aprobadas'] ?></span><br>Aprob.</div>
                                <div class="col"><span style="color:#2563EB"><?= $sec['entregadas'] ?? 0 ?></span><br>Entregadas</div>
                                <div class="col"><span style="color:#0EA5E9"><?= $sec['en_revision'] ?></span><br>Revisión</div>
                                <div class="col"><span class="text-warning"><?= $sec['correcciones'] ?></span><br>Correc.</div>
                                <div class="col"><span class="text-secondary"><?= $sec['pendientes'] ?></span><br>Pend.</div>
                                <div class="col"><span class="text-danger"><?= $sec['vencidas'] ?></span><br>Venc.</div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- 
         TAB: ESTUDIANTES
          -->
    <?php elseif ($filtros['tab'] === 'usuarios'): ?>

        <div class="filter-bar">
            <form method="GET" class="row g-2 align-items-end mb-0">
                <input type="hidden" name="tab" value="usuarios">
                <input type="hidden" name="periodo" value="<?= htmlspecialchars($filtros['periodo']) ?>">
                <div class="col-12 col-md-3">
                    <input type="text" name="buscar_usr" value="<?= htmlspecialchars($filtros['buscar_usr']) ?>"
                        class="form-control form-control-sm" placeholder="Nombre, matrícula o correo…">
                </div>
                <div class="col-6 col-md-3">
                    <select name="carrera" class="form-select form-select-sm">
                        <option value="">Todas las carreras</option>
                        <?php foreach ($carreras as $c): ?>
                            <option value="<?= $c['id_carrera'] ?>" <?= $filtros['carrera'] == $c['id_carrera'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c['nombre_carrera']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <select name="estado_usuario" class="form-select form-select-sm">
                        <option value="">Todos los estados</option>
                        <?php foreach (['activo' => 'Activo', 'aprobado' => 'Aprobado', 'espera' => 'En espera', 'cancelado' => 'Cancelado'] as $v => $l): ?>
                            <option value="<?= $v ?>" <?= $filtros['estado_usuario'] === $v ? 'selected' : '' ?>><?= $l ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-auto d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm">Filtrar</button>
                    <a href="?tab=usuarios&periodo=<?= $filtros['periodo'] ?>" class="btn btn-secondary btn-sm">Limpiar</a>
                </div>
            </form>
        </div>

        <!-- DESKTOP -->
        <div class="sec-card d-none d-md-block">
            <div class="table-responsive">
                <table class="table tbl-sup mb-0">
                    <thead>
                        <tr>
                            <th>Estudiante</th>
                            <th>Matrícula</th>
                            <th>Carrera</th>
                            <th class="text-center">Estado</th>
                            <th class="text-center">Proyectos</th>
                            <th style="min-width:140px">Avance secciones</th>
                            <th class="text-center">Registro</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($estudiantes as $est): ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold" style="font-size:.82rem"><?= htmlspecialchars($est['nombre_completo']) ?></div>
                                    <div class="text-muted" style="font-size:.72rem"><?= htmlspecialchars($est['correo_institucional']) ?></div>
                                </td>
                                <td style="font-size:.8rem"><?= htmlspecialchars($est['matricula']) ?></td>
                                <td style="font-size:.78rem"><?= htmlspecialchars(mb_substr($est['carrera'], 0, 30)) ?></td>
                                <td class="text-center"><?= $ctrl->badgeEstadoUsuario($est['estado_usuario']) ?></td>
                                <td class="text-center">
                                    <span class="badge" style="background:var(--tecnm-navy); font-size:.7rem"><?= $est['proyectos_activos'] ?> activo<?= $est['proyectos_activos'] != 1 ? 's' : '' ?></span>
                                    <br><span class="text-muted" style="font-size:.7rem"><?= $est['proyectos_total'] ?> total</span>
                                </td>
                                <td><?= barraProgreso((int)$est['tareas_aprobadas'], (int)$est['tareas_total']) ?></td>
                                <td class="text-center text-muted" style="font-size:.75rem; white-space:nowrap">
                                    <?= date('d/m/Y', strtotime($est['fecha_registro'])) ?>
                                </td>
                                <td>
                                    <a href="detalle_estudiante.php?id=<?= $est['id_usuarios'] ?>"
                                        class="btn btn-sm btn-primary" style="font-size:.75rem">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($estudiantes)): ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4 small">No se encontraron estudiantes.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- MÓVIL -->
        <div class="d-block d-md-none">
            <?php foreach ($estudiantes as $est): ?>
                <div class="card shadow-sm mb-3">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <div class="fw-semibold small"><?= htmlspecialchars($est['nombre_completo']) ?></div>
                                <div class="text-muted" style="font-size:.72rem"><?= htmlspecialchars($est['correo_institucional']) ?></div>
                            </div>
                            <?= $ctrl->badgeEstadoUsuario($est['estado_usuario']) ?>
                        </div>
                        <p class="small mb-1"><strong>Matrícula:</strong> <?= htmlspecialchars($est['matricula']) ?></p>
                        <p class="small mb-1"><strong>Carrera:</strong> <?= htmlspecialchars(mb_substr($est['carrera'], 0, 40)) ?></p>
                        <p class="small mb-2"><strong>Proyectos:</strong> <?= $est['proyectos_activos'] ?> activos / <?= $est['proyectos_total'] ?> total</p>
                        <div class="mb-2"><?= barraProgreso((int)$est['tareas_aprobadas'], (int)$est['tareas_total']) ?></div>
                        <a href="detalle_estudiante.php?id=<?= $est['id_usuarios'] ?>" class="btn btn-primary btn-sm">
                            <i class="bi bi-eye me-1"></i>Ver detalle
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php if (empty($estudiantes)): ?>
                <div class="alert alert-info text-center small">No se encontraron estudiantes.</div>
            <?php endif; ?>
        </div>

        <?php
        // PAGINACIÓN usuarios
        $qBase        = $qBase_filtros;
        $clave_pagina = 'pag_usr';
        $entidad      = 'registros';
        $sm           = true;
        $paginacion   = $pag_usr;
        include __DIR__ . '../../../publico/incluido/_paginacion.php';
        ?>

    <?php endif; ?>

</div>

<?php
$contenido = ob_get_clean();
$titulo    = "Panel Supervisor";
$bodyClass = "supervisor-page";
include __DIR__ . '/../../layout.php';
?>