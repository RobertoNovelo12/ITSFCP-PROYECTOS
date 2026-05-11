<?php

/**
 * panel_supervisor.php
 * Dashboard de solo lectura del supervisor — responsivo con tarjetas móvil.
 */

ini_set('display_errors', 0);
error_reporting(E_ALL);
session_start();

if (!isset($_SESSION['id_usuario'])) {
    header("Location: /ITSFCP-PROYECTOS/index.php");
    exit;
}

$rol        = strtolower($_SESSION['rol'] ?? '');
$id  = $_SESSION['id_usuario'];

require_once __DIR__ . '/../../Controladores/SupervisorControlador.php';
$ctrl = new SupervisorControlador();
$data = $ctrl->index();

extract($data);

function barraProgreso(int $aprobadas, int $total): string
{
    if ($total <= 0) return '<span class="text-muted small">Sin tareas</span>';
    $pct   = round(($aprobadas / $total) * 100);
    $color = $pct >= 80 ? 'success' : ($pct >= 40 ? 'warning' : 'danger');
    return "<div class='progress' style='height:8px;min-width:80px'>
              <div class='progress-bar bg-{$color}' style='width:{$pct}%'></div>
            </div>
            <span class='small text-muted'>{$aprobadas}/{$total} ({$pct}%)</span>";
}

ob_start();
?>


<div class="container-fluid py-4" style="max-width:95%;">

    <!-- ENCABEZADO -->
    <div class="row align-items-center mb-3">
        <div class="col">
            <h4 class="mb-0 fw-bold">
                <i class="bi bi-speedometer2 me-2 text-primary"></i>Panel Supervisor
            </h4>
            <span class="text-muted small">Vista general del sistema — solo lectura</span>
        </div>
        <div class="col-auto">
            <span class="badge bg-light text-dark border">
                <i class="bi bi-eye me-1"></i>Modo visualización
            </span>
        </div>
    </div>

    <!-- FILTRO GLOBAL: PERIODO -->
    <div class="filter-bar mb-3">
        <form method="GET" class="row g-2 align-items-end">
            <input type="hidden" name="tab" value="<?= htmlspecialchars($filtros['tab']) ?>">
            <div class="col-auto">
                <label class="form-label mb-1 small fw-semibold">Periodo</label>
                <select name="periodo" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">Todos los periodos</option>
                    <?php foreach ($periodos as $per): ?>
                        <option value="<?= $per['id_periodos'] ?>"
                            <?= $filtros['periodo'] == $per['id_periodos'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($per['periodo']) ?>
                            <?= ($per['estado'] == "Activo") ? ' (Activo)' : '(Terminado)' ?> </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if ($filtros['periodo']): ?>
                <div class="col-auto">
                    <a href="?tab=<?= $filtros['tab'] ?>" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-x-circle me-1"></i>Limpiar
                    </a>
                </div>
            <?php endif; ?>
        </form>
    </div>

    <!-- TARJETAS RESUMEN GLOBAL -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card stat-card azul h-100 shadow-sm">
                <div class="card-body">
                    <div class="num text-primary"><?= $resumen['proyectos']['total_proyectos'] ?? 0 ?></div>
                    <div class="lbl">Proyectos totales</div>
                    <div class="mini-stat mt-2">
                        <span class="text-success"><?= $resumen['proyectos']['activos'] ?? 0 ?> activos</span>&nbsp;
                        <span class="text-warning"><?= $resumen['proyectos']['por_aprobar'] ?? 0 ?> por aprobar</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card stat-card verde h-100 shadow-sm">
                <div class="card-body">
                    <div class="num text-success"><?= $resumen['estudiantes']['total_estudiantes'] ?? 0 ?></div>
                    <div class="lbl">Estudiantes en proyectos</div>
                    <div class="mini-stat mt-2">
                        <span class="text-success"><?= $resumen['estudiantes']['activos'] ?? 0 ?> activos</span>&nbsp;
                        <span class="text-secondary"><?= $resumen['estudiantes']['concluidos'] ?? 0 ?> concluidos</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card stat-card naranja h-100 shadow-sm">
                <div class="card-body">
                    <div class="num text-warning"><?= $resumen['solicitudes']['total_solicitudes'] ?? 0 ?></div>
                    <div class="lbl">Solicitudes de ingreso</div>
                    <div class="mini-stat mt-2">
                        <span class="text-secondary"><?= $resumen['solicitudes']['pendientes'] ?? 0 ?> pendientes</span>&nbsp;
                        <span class="text-success"><?= $resumen['solicitudes']['aceptadas'] ?? 0 ?> aceptadas</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card stat-card rojo h-100 shadow-sm">
                <div class="card-body">
                    <div class="num text-danger"><?= $resumen['tareas']['total_tareas'] ?? 0 ?></div>
                    <div class="lbl">Tareas del sistema</div>
                    <div class="mini-stat mt-2">
                        <span class="text-success"><?= $resumen['tareas']['aprobadas'] ?? 0 ?> aprobadas</span>&nbsp;
                        <span class="text-danger"><?= $resumen['tareas']['vencidas'] ?? 0 ?> vencidas</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- MINI TARJETAS SOLICITUDES POR ESTADO -->
    <div class="row g-2 mb-4">
        <?php
        $total_sol = max(1, array_sum(array_column($tabs_cards ?? [], 'val')));
        $tabs_cards = [
            ['label' => 'Pendientes',   'val' => $resumen['solicitudes']['pendientes']   ?? 0, 'color' => 'secondary'],
            ['label' => 'En revisión',  'val' => $resumen['solicitudes']['en_revision']  ?? 0, 'color' => 'info'],
            ['label' => 'Correcciones', 'val' => $resumen['solicitudes']['correcciones'] ?? 0, 'color' => 'warning'],
            ['label' => 'Aceptadas',    'val' => $resumen['solicitudes']['aceptadas']    ?? 0, 'color' => 'success'],
            ['label' => 'Rechazadas',   'val' => $resumen['solicitudes']['rechazadas']   ?? 0, 'color' => 'danger'],
        ];
        $total_sol = max(1, array_sum(array_column($tabs_cards, 'val')));
        foreach ($tabs_cards as $tc):
            $pct = round(($tc['val'] / $total_sol) * 100);
        ?>
            <div class="col">
                <div class="card shadow-sm h-100">
                    <div class="card-body text-center px-1 py-2">
                        <div class="fw-bold text-<?= $tc['color'] ?> fs-5"><?= $tc['val'] ?></div>
                        <div class="small text-muted"><?= $tc['label'] ?></div>
                        <div class="progress mt-2" style="height:4px;">
                            <div class="progress-bar bg-<?= $tc['color'] ?>" style="width:<?= $pct ?>%"></div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- TABS DE NAVEGACIÓN -->
    <div class="mb-3 d-flex flex-wrap gap-2">
        <?php
        $tabs_nav = [
            'resumen'    => ['icon' => 'bi-grid-1x2',       'label' => 'Resumen'],
            'proyectos'  => ['icon' => 'bi-folder2-open',   'label' => 'Proyectos'],
            'solicitudes' => ['icon' => 'bi-envelope-paper', 'label' => 'Solicitudes'],
            'etapas'     => ['icon' => 'bi-layers',         'label' => 'Etapas & Tareas'],
            'usuarios'   => ['icon' => 'bi-people',         'label' => 'Estudiantes'],
        ];
        foreach ($tabs_nav as $key => $tab): ?>
            <a href="?<?= http_build_query(array_merge($_GET, ['tab' => $key])) ?>"
                class="btn btn-tab <?= $filtros['tab'] == $key ? 'active' : 'btn-outline-secondary' ?>">
                <i class="<?= $tab['icon'] ?> me-1"></i><?= $tab['label'] ?>
            </a>
        <?php endforeach; ?>
    </div>

    <!-- 
         TAB: RESUMEN
     -->
    <?php if ($filtros['tab'] === 'resumen'): ?>
        <div class="row g-4">
            <div class="col-md-6">
                <div class="card shadow-sm">
                    <div class="card-header bg-white">
                        <span class="section-title mb-0">Estado de proyectos</span>
                    </div>
                    <div class="card-body">
                        <?php
                        $estados_proy = [
                            'activos'    => ['Activo', 'success'],
                            'por_aprobar' => ['Por aprobar', 'warning'],
                            'por_cerrar' => ['Por cerrar', 'info'],
                            'rechazados' => ['Rechazado', 'danger'],
                            'vencidos'   => ['Vencido', 'secondary'],
                            'cerrados'   => ['Cerrado', 'dark'],
                        ];
                        $total_p = max(1, $resumen['proyectos']['total_proyectos']);
                        foreach ($estados_proy as $key => [$lbl, $color]):
                            $val = $resumen['proyectos'][$key] ?? 0;
                            $pct = round(($val / $total_p) * 100);
                        ?>
                            <div class="mb-2">
                                <div class="d-flex justify-content-between mb-1">
                                    <span class="small"><?= $lbl ?></span>
                                    <span class="small fw-bold"><?= $val ?> <span class="text-muted">(<?= $pct ?>%)</span></span>
                                </div>
                                <div class="progress etapa-barra">
                                    <div class="progress-bar bg-<?= $color ?>" style="width:<?= $pct ?>%"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card shadow-sm">
                    <div class="card-header bg-white">
                        <span class="section-title mb-0">Investigadores activos</span>
                    </div>
                    <div class="card-body p-3" style="max-height:300px;overflow-y:auto;">
                        <?php foreach ($resumen_inv as $inv): ?>
                            <?php
                            $palabras = explode(' ', trim($inv['nombre']));
                            $iniciales = strtoupper(
                                substr($palabras[0] ?? '', 0, 1) .
                                    substr($palabras[1] ?? '', 0, 1)
                            );
                            ?>
                            <div class="inv-card">
                                <div class="inv-avatar"><?= $iniciales ?></div>
                                <div class="inv-info">
                                    <div class="inv-name"><?= htmlspecialchars($inv['nombre']) ?></div>
                                    <div class="inv-email"><?= htmlspecialchars($inv['correo_institucional']) ?></div>
                                </div>
                                <div class="inv-meta">
                                    <span class="inv-badge"><?= $inv['total_proyectos'] ?> proy.</span>
                                    <div class="inv-active"><?= $inv['activos'] ?> activos</div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <?php if (empty($resumen_inv)): ?>
                            <p class="text-muted text-center small mt-3">Sin investigadores registrados.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-12">
                <div class="card shadow-sm d-none d-md-block">
                    <div class="card-header bg-white">
                        <span class="section-title mb-0">Avance global por sección del documento</span>
                    </div>
                    <div class="card-body p-0">
                        <?php if (!empty($etapas_data['secciones'])): ?>
                            <!-- TABLA DESKTOP -->
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Sección</th>
                                            <th class="text-center">Total</th>
                                            <th class="text-center text-success">Aprobadas</th>
                                            <th class="text-center text-info">En revisión</th>
                                            <th class="text-center text-warning">Correcciones</th>
                                            <th class="text-center text-danger">Vencidas</th>
                                            <th style="min-width:120px">Avance</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($etapas_data['secciones'] as $sec):
                                            $tot = max(1, $sec['total_instancias']);
                                            $pct = round(($sec['aprobadas'] / $tot) * 100);
                                            $col = $pct >= 80 ? 'success' : ($pct >= 40 ? 'warning' : 'danger');
                                        ?>
                                            <tr>
                                                <td><?= htmlspecialchars($sec['seccion']) ?></td>
                                                <td class="text-center"><?= $sec['total_instancias'] ?></td>
                                                <td class="text-center text-success fw-bold"><?= $sec['aprobadas'] ?></td>
                                                <td class="text-center text-info"><?= $sec['en_revision'] ?></td>
                                                <td class="text-center text-warning"><?= $sec['correcciones'] ?></td>
                                                <td class="text-center text-danger"><?= $sec['vencidas'] ?></td>
                                                <td>
                                                    <div class="progress etapa-barra">
                                                        <div class="progress-bar bg-<?= $col ?>" style="width:<?= $pct ?>%"></div>
                                                    </div>
                                                    <span class="small text-muted"><?= $pct ?>%</span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <!-- TARJETAS MÓVIL secciones -->
                            <div class="d-block d-md-none p-2">
                                <?php foreach ($etapas_data['secciones'] as $sec):
                                    $tot = max(1, $sec['total_instancias']);
                                    $pct = round(($sec['aprobadas'] / $tot) * 100);
                                    $col = $pct >= 80 ? 'success' : ($pct >= 40 ? 'warning' : 'danger');
                                ?>
                                    <div class="card shadow-sm mb-3">
                                        <div class="card-body text-center">
                                            <h6 class="fw-bold mb-1"><?= htmlspecialchars($sec['seccion']) ?></h6>
                                            <div class="progress etapa-barra mb-1">
                                                <div class="progress-bar bg-<?= $col ?>" style="width:<?= $pct ?>%"></div>
                                            </div>
                                            <span class="small text-muted"><?= $pct ?>%</span>
                                        </div>
                                        <ul class="list-group list-group-flush">
                                            <li class="list-group-item">
                                                <div class="row text-center">
                                                    <div class="col-4"><strong>Total</strong>
                                                        <p class="mb-0"><?= $sec['total_instancias'] ?></p>
                                                    </div>
                                                    <div class="col-4"><strong class="text-success">Aprob.</strong>
                                                        <p class="mb-0 fw-bold text-success"><?= $sec['aprobadas'] ?></p>
                                                    </div>
                                                    <div class="col-4"><strong class="text-danger">Venc.</strong>
                                                        <p class="mb-0 text-danger"><?= $sec['vencidas'] ?></p>
                                                    </div>
                                                </div>
                                            </li>
                                            <li class="list-group-item">
                                                <div class="row text-center">
                                                    <div class="col-4"><strong class="text-info">Revisión</strong>
                                                        <p class="mb-0"><?= $sec['en_revision'] ?></p>
                                                    </div>
                                                    <div class="col-4"><strong class="text-warning">Correc.</strong>
                                                        <p class="mb-0"><?= $sec['correcciones'] ?></p>
                                                    </div>
                                                    <div class="col-4"><strong class="text-secondary">Pend.</strong>
                                                        <p class="mb-0"><?= $sec['pendientes'] ?></p>
                                                    </div>
                                                </div>
                                            </li>
                                        </ul>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-muted text-center py-3">Sin datos de tareas para este periodo.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- 
         TAB: PROYECTOS
     -->
    <?php elseif ($filtros['tab'] === 'proyectos'): ?>

        <div class="filter-bar">
            <form method="GET" class="row g-2 align-items-end">
                <input type="hidden" name="tab" value="proyectos">
                <input type="hidden" name="periodo" value="<?= htmlspecialchars($filtros['periodo']) ?>">
                <div class="col-12 col-md-3">
                    <input type="text" name="buscar_proy" value="<?= htmlspecialchars($filtros['buscar_proy']) ?>"
                        class="form-control form-control-sm" placeholder="Buscar título o investigador...">
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
                        <option value="virtual" <?= $filtros['modalidad'] === 'virtual'  ? 'selected' : '' ?>>Virtual</option>
                        <option value="fisico" <?= $filtros['modalidad'] === 'fisico'   ? 'selected' : '' ?>>Físico</option>
                        <option value="mixto" <?= $filtros['modalidad'] === 'mixto'    ? 'selected' : '' ?>>Mixto</option>
                    </select>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary btn-sm">Filtrar</button>
                    <a href="?tab=proyectos&periodo=<?= $filtros['periodo'] ?>" class="btn btn-outline-secondary btn-sm">Limpiar</a>
                </div>
            </form>
        </div>

        <!-- TABLA DESKTOP -->
        <div class="card shadow-sm d-none d-md-block">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
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
                                <tr <?= $p['tareas_vencidas'] > 0 ? "class='table-danger bg-opacity-10'" : '' ?>>
                                    <td>
                                        <div class="fw-semibold" style="max-width:200px">
                                            <?= htmlspecialchars(mb_substr($p['titulo'], 0, 60)) ?><?= strlen($p['titulo']) > 60 ? '…' : '' ?>
                                        </div>
                                    </td>
                                    <td><?= htmlspecialchars($p['investigador_nombre']) ?></td>
                                    <td class="text-center"><?= $ctrl->badgeEstadoProyecto($p['estado']) ?></td>
                                    <td class="text-center"><span class="badge bg-light text-dark text-capitalize"><?= $p['modalidad'] ?></span></td>
                                    <td class="text-center">
                                        <span class="fw-bold"><?= $p['alumnos_activos'] ?></span>
                                        <span class="text-muted small"> / <?= $p['cantidad_estudiante'] ?></span>
                                    </td>
                                    <td class="text-center">
                                        <?= $p['sol_pendientes'] > 0 ? "<span class='badge bg-warning text-dark'>{$p['sol_pendientes']}</span>" : '<span class="text-muted">—</span>' ?>
                                    </td>
                                    <td class="text-center">
                                        <?= $p['tareas_vencidas'] > 0 ? "<span class='badge bg-danger'>{$p['tareas_vencidas']}</span>" : '<span class="text-success"><i class="bi bi-check-circle"></i></span>' ?>
                                    </td>
                                    <td class="text-muted small">
                                        <?= date('d/m/Y', strtotime($p['fecha_inicio'])) ?><br>
                                        <?= date('d/m/Y', strtotime($p['fecha_fin'])) ?>
                                    </td>
                                    <td>
                                        <a href="detalle_proyecto.php?id=<?= $p['id_proyectos'] ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($proyectos)): ?>
                                <tr>
                                    <td colspan="9" class="text-center text-muted py-4">No se encontraron proyectos.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- TARJETAS MÓVIL PROYECTOS -->
        <div class="d-block d-md-none">
            <?php foreach ($proyectos as $p): ?>
                <div class="card shadow-sm mb-3 <?= $p['tareas_vencidas'] > 0 ? 'border-danger' : '' ?>">
                    <div class="card-body text-center">
                        <h5 class="fw-bold">
                            <?= htmlspecialchars(mb_substr($p['titulo'], 0, 55)) ?><?= strlen($p['titulo']) > 55 ? '…' : '' ?>
                        </h5>
                        <h5 class="fw-bold">
                            <?= $ctrl->badgeEstadoProyecto($p['estado']) ?>
                        </h5>
                    </div>
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item">
                            <strong>Investigador:</strong> <?= htmlspecialchars($p['investigador_nombre']) ?>
                        </li>
                        <li class="list-group-item">
                            <div class="row text-center">
                                <div class="col-4">
                                    <strong>Modalidad</strong>
                                    <p class="mb-0 text-capitalize"><?= $p['modalidad'] ?></p>
                                </div>
                                <div class="col-4">
                                    <strong>Alumnos</strong>
                                    <p class="mb-0"><?= $p['alumnos_activos'] ?>/<?= $p['cantidad_estudiante'] ?></p>
                                </div>
                                <div class="col-4">
                                    <strong>Sol. pend.</strong>
                                    <p class="mb-0">
                                        <?= $p['sol_pendientes'] > 0 ? "<span class='badge bg-warning text-dark'>{$p['sol_pendientes']}</span>" : '—' ?>
                                    </p>
                                </div>
                            </div>
                        </li>
                        <li class="list-group-item">
                            <div class="row text-center">
                                <div class="col-6">
                                    <strong>Fecha inicio</strong>
                                    <p class="mb-0"><?= date('d/m/Y', strtotime($p['fecha_inicio'])) ?></p>
                                </div>
                                <div class="col-6">
                                    <strong>Fecha fin</strong>
                                    <p class="mb-0"><?= date('d/m/Y', strtotime($p['fecha_fin'])) ?></p>
                                </div>
                            </div>
                        </li>
                        <?php if ($p['tareas_vencidas'] > 0): ?>
                            <li class="list-group-item text-center">
                                <span class="badge bg-danger"><?= $p['tareas_vencidas'] ?> tareas vencidas</span>
                            </li>
                        <?php endif; ?>
                    </ul>
                    <div class="card-body d-flex justify-content-center">
                        <a href="detalle_proyecto.php?id=<?= $p['id_proyectos'] ?>" class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-eye me-1"></i>Ver detalle
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php if (empty($proyectos)): ?>
                <div class="alert alert-info text-center">No se encontraron proyectos.</div>
            <?php endif; ?>
        </div>
        <?= $ctrl->htmlPaginacion($pag_proy, 'pag_proy', 'proyectos', $filtros) ?>

        <!-- 
         TAB: SOLICITUDES
     -->
    <?php elseif ($filtros['tab'] === 'solicitudes'): ?>

        <div class="filter-bar">
            <form method="GET" class="row g-2 align-items-end">
                <input type="hidden" name="tab" value="solicitudes">
                <input type="hidden" name="periodo" value="<?= htmlspecialchars($filtros['periodo']) ?>">
                <div class="col-12 col-md-3">
                    <input type="text" name="buscar_sol" value="<?= htmlspecialchars($filtros['buscar_sol']) ?>"
                        class="form-control form-control-sm" placeholder="Nombre, matrícula o proyecto...">
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
                    <input type="date" name="fecha_desde" value="<?= $filtros['fecha_desde'] ?>"
                        class="form-control form-control-sm">
                </div>
                <div class="col-6 col-md-2">
                    <input type="date" name="fecha_hasta" value="<?= $filtros['fecha_hasta'] ?>"
                        class="form-control form-control-sm">
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary btn-sm">Filtrar</button>
                    <a href="?tab=solicitudes&periodo=<?= $filtros['periodo'] ?>" class="btn btn-outline-secondary btn-sm">Limpiar</a>
                </div>
            </form>
        </div>

        <!-- TABLA DESKTOP SOLICITUDES -->
        <div class="card shadow-sm d-none d-md-block">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
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
                                    <td><?= htmlspecialchars(mb_substr($s['carrera'], 0, 30)) ?></td>
                                    <td style="max-width:180px">
                                        <?= htmlspecialchars(mb_substr($s['proyecto_titulo'], 0, 50)) ?><?= strlen($s['proyecto_titulo']) > 50 ? '…' : '' ?>
                                    </td>
                                    <td><?= htmlspecialchars($s['investigador_nombre']) ?></td>
                                    <td class="text-center"><?= $ctrl->badgeEstadoSolicitud($s['estado']) ?></td>
                                    <td class="text-center"><?= $s['semestre'] ?? '—' ?>°</td>
                                    <td class="text-center"><?= $s['promedio'] ? number_format($s['promedio'], 1) : '—' ?></td>
                                    <td class="text-center text-muted small"><?= date('d/m/Y', strtotime($s['fecha_envio'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($solicitudes)): ?>
                                <tr>
                                    <td colspan="9" class="text-center text-muted py-4">No se encontraron solicitudes.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- TARJETAS MÓVIL SOLICITUDES -->
        <div class="d-block d-md-none">
            <?php foreach ($solicitudes as $s): ?>
                <div class="card shadow-sm mb-3">
                    <div class="card-body text-center">
                        <h5 class="fw-bold"><?= htmlspecialchars($s['estudiante_nombre']) ?></h5>
                        <h5 class="fw-bold"><?= $ctrl->badgeEstadoSolicitud($s['estado']) ?></h5>
                    </div>
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item">
                            <strong>Matrícula:</strong> <?= htmlspecialchars($s['matricula']) ?><br>
                            <strong>Carrera:</strong> <?= htmlspecialchars(mb_substr($s['carrera'], 0, 35)) ?>
                        </li>
                        <li class="list-group-item">
                            <strong>Proyecto:</strong><br>
                            <span class="small"><?= htmlspecialchars(mb_substr($s['proyecto_titulo'], 0, 55)) ?><?= strlen($s['proyecto_titulo']) > 55 ? '…' : '' ?></span>
                        </li>
                        <li class="list-group-item">
                            <strong>Investigador:</strong> <?= htmlspecialchars($s['investigador_nombre']) ?>
                        </li>
                        <li class="list-group-item">
                            <div class="row text-center">
                                <div class="col-4">
                                    <strong>Semestre</strong>
                                    <p class="mb-0"><?= $s['semestre'] ?? '—' ?>°</p>
                                </div>
                                <div class="col-4">
                                    <strong>Promedio</strong>
                                    <p class="mb-0"><?= $s['promedio'] ? number_format($s['promedio'], 1) : '—' ?></p>
                                </div>
                                <div class="col-4">
                                    <strong>Fecha</strong>
                                    <p class="mb-0"><?= date('d/m/Y', strtotime($s['fecha_envio'])) ?></p>
                                </div>
                            </div>
                        </li>
                    </ul>
                </div>
            <?php endforeach; ?>
            <?php if (empty($solicitudes)): ?>
                <div class="alert alert-info text-center">No se encontraron solicitudes.</div>
            <?php endif; ?>
        </div>
        <?= $ctrl->htmlPaginacion($pag_sol, 'pag_sol', 'solicitudes', $filtros) ?>

        <!-- 
         TAB: ETAPAS & TAREAS
     -->
    <?php elseif ($filtros['tab'] === 'etapas'): ?>

        <!-- Cards etapas (ya responsivas) -->
        <div class="row g-3 mb-4">
            <?php foreach ($etapas_data['etapas'] as $etapa):
                $tot_et = max(1, $etapa['total']);
                $pct_et = round(($etapa['completados'] / $tot_et) * 100);
                $col_et = $pct_et >= 80 ? 'success' : ($pct_et >= 40 ? 'warning' : 'danger');
            ?>
                <div class="col-12 col-md-4">
                    <div class="card shadow-sm h-100">
                        <div class="card-header bg-white">
                            <span class="fw-semibold"><?= htmlspecialchars($etapa['etapa']) ?></span>
                            <span class="badge bg-<?= $etapa['categoria'] === 'final' ? 'dark' : 'secondary' ?> float-end">
                                <?= ucfirst($etapa['categoria']) ?>
                            </span>
                        </div>
                        <div class="card-body">
                            <div class="d-flex justify-content-between mb-1">
                                <small>Avance global</small><small class="fw-bold"><?= $pct_et ?>%</small>
                            </div>
                            <div class="progress mb-3 etapa-barra">
                                <div class="progress-bar bg-<?= $col_et ?>" style="width:<?= $pct_et ?>%"></div>
                            </div>
                            <div class="row text-center g-1">
                                <div class="col-3">
                                    <div class="small text-muted">Total</div>
                                    <div class="fw-bold"><?= $etapa['total'] ?></div>
                                </div>
                                <div class="col-3">
                                    <div class="small text-secondary">Pend.</div>
                                    <div class="fw-bold text-secondary"><?= $etapa['pendientes'] ?></div>
                                </div>
                                <div class="col-3">
                                    <div class="small text-primary">Proceso</div>
                                    <div class="fw-bold text-primary"><?= $etapa['en_proceso'] ?></div>
                                </div>
                                <div class="col-3">
                                    <div class="small text-success">Compl.</div>
                                    <div class="fw-bold text-success"><?= $etapa['completados'] ?></div>
                                </div>
                            </div>
                            <?php if ($etapa['rechazados'] > 0): ?>
                                <div class="mt-2 text-center">
                                    <span class="badge bg-danger"><?= $etapa['rechazados'] ?> rechazados</span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php if (empty($etapas_data['etapas'])): ?>
                <div class="col-12">
                    <p class="text-muted text-center">Sin etapas configuradas.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Detalle tareas por sección -->
        <div class="card shadow-sm">
            <div class="card-header bg-white">
                <span class="section-title mb-0">Detalle de avance por sección del documento</span>
            </div>
            <div class="card-body p-0">
                <!-- TABLA DESKTOP -->
                <div class="table-responsive d-none d-md-block">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Sección</th>
                                <th class="text-center">Instancias</th>
                                <th class="text-center text-success">Aprobadas</th>
                                <th class="text-center text-info">En revisión</th>
                                <th class="text-center text-warning">Correcciones</th>
                                <th class="text-center text-secondary">Pendientes</th>
                                <th class="text-center text-danger">Vencidas</th>
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
                                    <td class="text-center text-success fw-bold"><?= $sec['aprobadas'] ?></td>
                                    <td class="text-center text-info"><?= $sec['en_revision'] ?></td>
                                    <td class="text-center text-warning"><?= $sec['correcciones'] ?></td>
                                    <td class="text-center text-secondary"><?= $sec['pendientes'] ?></td>
                                    <td class="text-center text-danger"><?= $sec['vencidas'] ?></td>
                                    <td>
                                        <div class="progress etapa-barra">
                                            <div class="progress-bar bg-<?= $col_s ?>" style="width:<?= $pct_s ?>%"></div>
                                        </div>
                                        <span class="small text-muted"><?= $pct_s ?>%</span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($etapas_data['secciones'])): ?>
                                <tr>
                                    <td colspan="9" class="text-center text-muted py-3">Sin datos de tareas.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <!-- TARJETAS MÓVIL TAREAS POR SECCIÓN -->
                <div class="d-block d-md-none p-2">
                    <?php foreach ($etapas_data['secciones'] as $i => $sec):
                        $tot_s = max(1, $sec['total_instancias']);
                        $pct_s = round(($sec['aprobadas'] / $tot_s) * 100);
                        $col_s = $pct_s >= 80 ? 'success' : ($pct_s >= 40 ? 'warning' : 'danger');
                    ?>
                        <div class="card shadow-sm mb-3">
                            <div class="card-body text-center">
                                <h6 class="fw-bold mb-1"><?= $i + 1 ?>. <?= htmlspecialchars($sec['seccion']) ?></h6>
                                <div class="progress etapa-barra mb-1">
                                    <div class="progress-bar bg-<?= $col_s ?>" style="width:<?= $pct_s ?>%"></div>
                                </div>
                                <span class="small text-muted"><?= $pct_s ?>%</span>
                            </div>
                            <ul class="list-group list-group-flush">
                                <li class="list-group-item">
                                    <div class="row text-center">
                                        <div class="col-4"><strong>Total</strong>
                                            <p class="mb-0"><?= $sec['total_instancias'] ?></p>
                                        </div>
                                        <div class="col-4"><strong class="text-success">Aprob.</strong>
                                            <p class="mb-0 fw-bold text-success"><?= $sec['aprobadas'] ?></p>
                                        </div>
                                        <div class="col-4"><strong class="text-danger">Venc.</strong>
                                            <p class="mb-0 text-danger"><?= $sec['vencidas'] ?></p>
                                        </div>
                                    </div>
                                </li>
                                <li class="list-group-item">
                                    <div class="row text-center">
                                        <div class="col-4"><strong class="text-info">Revisión</strong>
                                            <p class="mb-0"><?= $sec['en_revision'] ?></p>
                                        </div>
                                        <div class="col-4"><strong class="text-warning">Correc.</strong>
                                            <p class="mb-0"><?= $sec['correcciones'] ?></p>
                                        </div>
                                        <div class="col-4"><strong class="text-secondary">Pend.</strong>
                                            <p class="mb-0"><?= $sec['pendientes'] ?></p>
                                        </div>
                                    </div>
                                </li>
                            </ul>
                        </div>
                    <?php endforeach; ?>
                    <?php if (empty($etapas_data['secciones'])): ?>
                        <p class="text-muted text-center py-3">Sin datos de tareas.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- 
         TAB: ESTUDIANTES
     -->
    <?php elseif ($filtros['tab'] === 'usuarios'): ?>

        <div class="filter-bar">
            <form method="GET" class="row g-2 align-items-end">
                <input type="hidden" name="tab" value="usuarios">
                <input type="hidden" name="periodo" value="<?= htmlspecialchars($filtros['periodo']) ?>">
                <div class="col-12 col-md-3">
                    <input type="text" name="buscar_usr" value="<?= htmlspecialchars($filtros['buscar_usr']) ?>"
                        class="form-control form-control-sm" placeholder="Nombre, matrícula o correo...">
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
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary btn-sm">Filtrar</button>
                    <a href="?tab=usuarios&periodo=<?= $filtros['periodo'] ?>" class="btn btn-outline-secondary btn-sm">Limpiar</a>
                </div>
            </form>
        </div>

        <!-- TABLA DESKTOP ESTUDIANTES -->
        <div class="card shadow-sm d-none d-md-block">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Estudiante</th>
                                <th>Matrícula</th>
                                <th>Carrera</th>
                                <th class="text-center">Estado</th>
                                <th class="text-center">Estado proceso</th>
                                <th class="text-center">Proyectos</th>
                                <th style="min-width:140px">Avance tareas</th>
                                <th class="text-center">Registro</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($estudiantes as $est): ?>
                                <tr>
                                    <td>
                                        <div class="fw-semibold"><?= htmlspecialchars($est['nombre_completo']) ?></div>
                                        <div class="text-muted small"><?= htmlspecialchars($est['correo_institucional']) ?></div>
                                    </td>
                                    <td><?= htmlspecialchars($est['matricula']) ?></td>
                                    <td><?= htmlspecialchars(mb_substr($est['carrera'], 0, 30)) ?></td>
                                    <td class="text-center"><?= $ctrl->badgeEstadoUsuario($est['estado_usuario']) ?></td>
                                    <td class="text-center">
                                        <span class="badge bg-primary"><?= $est['proyectos_activos'] ?> activo<?= $est['proyectos_activos'] != 1 ? 's' : '' ?></span>
                                        <br><span class="text-muted small"><?= $est['proyectos_total'] ?> total</span>
                                    </td>
                                    <td><?= barraProgreso((int)$est['tareas_aprobadas'], (int)$est['tareas_total']) ?></td>
                                    <td class="text-center text-muted small"><?= date('d/m/Y', strtotime($est['fecha_registro'])) ?></td>
                                    <td>
                                        <a href="detalle_estudiante.php?id=<?= $est['id_usuarios'] ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($estudiantes)): ?>
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-4">No se encontraron estudiantes.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- TARJETAS MÓVIL ESTUDIANTES -->
        <div class="d-block d-md-none">
            <?php foreach ($estudiantes as $est): ?>
                <div class="card shadow-sm mb-3">
                    <div class="card-body text-center">
                        <h5 class="fw-bold"><?= htmlspecialchars($est['nombre_completo']) ?></h5>
                        <h5 class="fw-bold"><?= $ctrl->badgeEstadoUsuario($est['estado_usuario']) ?></h5>
                    </div>
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item">
                            <div class="row text-center">
                                <div class="col-6">
                                    <strong>Matrícula</strong>
                                    <p class="mb-0"><?= htmlspecialchars($est['matricula']) ?></p>
                                </div>
                                <div class="col-6">
                                    <strong>Carrera</strong>
                                    <p class="mb-0 small"><?= htmlspecialchars(mb_substr($est['carrera'], 0, 25)) ?></p>
                                </div>
                            </div>
                        </li>
                        <li class="list-group-item">
                            <div class="row text-center">
                                <div class="col-6">
                                    <strong>Proyectos</strong>
                                    <p class="mb-0">
                                        <span class="badge bg-primary"><?= $est['proyectos_activos'] ?> activo<?= $est['proyectos_activos'] != 1 ? 's' : '' ?></span>
                                        <br><small class="text-muted"><?= $est['proyectos_total'] ?> total</small>
                                    </p>
                                </div>
                                <div class="col-6">
                                    <strong>Registro</strong>
                                    <p class="mb-0 small"><?= date('d/m/Y', strtotime($est['fecha_registro'])) ?></p>
                                </div>
                            </div>
                        </li>
                        <li class="list-group-item text-center">
                            <strong class="small d-block mb-1">Avance de tareas</strong>
                            <?= barraProgreso((int)$est['tareas_aprobadas'], (int)$est['tareas_total']) ?>
                        </li>
                    </ul>
                    <div class="card-body d-flex justify-content-center">
                        <a href="detalle_estudiante.php?id=<?= $est['id_usuarios'] ?>" class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-eye me-1"></i>Ver detalle
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php if (empty($estudiantes)): ?>
                <div class="alert alert-info text-center">No se encontraron estudiantes.</div>
            <?php endif; ?>
        </div>
        <?= $ctrl->htmlPaginacion($pag_usr, 'pag_usr', 'usuarios', $filtros) ?>

    <?php endif; ?>

</div>

<?php
$contenido  = ob_get_clean();
$titulo     = "Panel Supervisor";
$bodyClass  = "supervisor-page";
include __DIR__ . '/../../layout.php';
?>