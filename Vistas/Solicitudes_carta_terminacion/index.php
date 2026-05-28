<?php
// Vistas/Solicitudes_carta_terminacion/index.php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

if (!isset($_SESSION['id_usuario'])) {
    header('Location: /ITSFCP-PROYECTOS/index.php');
    exit;
}

$rol        = strtolower($_SESSION['rol'] ?? '');
$id_usuario = (int)$_SESSION['id_usuario'];

if ($rol !== 'supervisor') {
    header('Location: /ITSFCP-PROYECTOS/Vistas/Principal/index.php');
    exit;
}

require_once '../../Controladores/solicitudesCartaTerminacionControlador.php';

$ctrl        = new solicitudes_carta_terminacionControlador();
$tipo_filtro = $_GET['tipo']       ?? 'Todas';
$buscar      = $_GET['buscar']     ?? '';
$pagina      = max(1, (int)($_GET['pagina']     ?? 1));
$id_periodo  = (int)($_GET['id_periodo'] ?? 0);

// ── Acción: aprobar (GET desde botón tabla) ───────────────────────────────────
if (isset($_GET['action']) && $_GET['action'] === 'aprobar' && isset($_GET['id'])) {
    $ctrl->aprobarCarta((int)$_GET['id'], $id_usuario, $rol);
    // aprobarCarta() redirige; no llega aquí
}

// ── Cargar datos ──────────────────────────────────────────────────────────────
$periodos  = $ctrl->obtenerTodosPeriodos();
$resumen   = $ctrl->resumenCartas($id_periodo);
$resultado = $ctrl->listarCartas($tipo_filtro, $buscar, $pagina, $id_periodo);

$solicitudes = $resultado['solicitudes'] ?? [];
$paginacion  = $resultado['paginacion']  ?? [
    'total' => 0,
    'por_pagina' => 6,
    'pagina' => $pagina,
    'total_paginas' => 1,
];

// ── Mensajes ──────────────────────────────────────────────────────────────────
$msg   = $_GET['msg'] ?? '';
$_mapa = [
    'exito_aprobar'      => ['tipo' => 'exito',  'titulo_msg' => 'Carta aprobada',      'mensaje' => 'La carta de terminación fue aprobada. El estudiante ha concluido su participación.'],
    'exito_rechazar'     => ['tipo' => 'exito',  'titulo_msg' => 'Carta rechazada',     'mensaje' => 'La carta de terminación fue rechazada. El estudiante fue notificado para reenviarla.'],
    'error_aprobar'      => ['tipo' => 'error',  'titulo_msg' => 'Error al aprobar',    'mensaje' => 'No fue posible aprobar la carta de terminación. Intenta de nuevo.'],
    'error_rechazar'     => ['tipo' => 'error',  'titulo_msg' => 'Error al rechazar',   'mensaje' => 'No fue posible rechazar la carta de terminación. Intenta de nuevo.'],
    'accion_no_permitida' => ['tipo' => 'alerta', 'titulo_msg' => 'Acción no permitida', 'mensaje' => 'La acción solicitada no está disponible para tu rol.'],
];

ob_start();
?>

<div class="container-fluid py-4 ancho_container">

    <!-- CABECERA + FILTRO DE PERIODO -->
    <div class="row mb-3 align-items-center">
        <?php
        $titulo      = 'Solicitudes de Carta de Terminación';
        $descripcion = 'Solicitudes de cierre individual de estudiantes';
        include __DIR__ . '../../../publico/incluido/_encabezado.php';
        ?>
        <div class="col-md-6 text-md-end">
            <form class="d-inline-flex align-items-center gap-2" method="GET">
                <input type="hidden" name="tipo" value="<?= htmlspecialchars($tipo_filtro) ?>">
                <input type="hidden" name="buscar" value="<?= htmlspecialchars($buscar) ?>">
                <label class="mb-0 text-nowrap fw-semibold">Periodo:</label>
                <select name="id_periodo" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="0" <?= $id_periodo === 0 ? 'selected' : '' ?>>Todos</option>
                    <?php foreach ($periodos as $p): ?>
                        <option value="<?= $p['id_periodos'] ?>"
                            <?= ($id_periodo === (int)$p['id_periodos']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($p['periodo']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>
    </div>

    <!-- ALERTAS -->
    <?php
    if (isset($_mapa[$msg])) {
        extract($_mapa[$msg]);
        include __DIR__ . '../../../publico/incluido/_mensaje.php';
    }
    ?>

    <!-- TARJETAS RESUMEN -->
    <div class="row mb-4 g-3">
        <div class="col-6 col-md-3">
            <div class="card text-center border-primary shadow-sm h-100">
                <div class="card-body">
                    <div class="display-6 fw-bold text-primary"><?= $resumen['total'] ?? 0 ?></div>
                    <div class="text-muted small">Total solicitudes</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card text-center border-warning shadow-sm h-100">
                <div class="card-body">
                    <div class="display-6 fw-bold text-warning"><?= $resumen['pendientes'] ?? 0 ?></div>
                    <div class="text-muted small">Pendientes de revisión</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card text-center border-success shadow-sm h-100">
                <div class="card-body">
                    <div class="display-6 fw-bold text-success"><?= $resumen['aprobadas'] ?? 0 ?></div>
                    <div class="text-muted small">Aprobadas</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card text-center border-danger shadow-sm h-100">
                <div class="card-body">
                    <div class="display-6 fw-bold text-danger"><?= $resumen['rechazadas'] ?? 0 ?></div>
                    <div class="text-muted small">Rechazadas</div>
                </div>
            </div>
        </div>
    </div>

    <!-- FILTROS DE TIPO + BÚSQUEDA -->
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body py-2">
            <div class="row g-2 align-items-end">

                <div class="col-md-4 mb-1">
                    <label class="form-label mb-1 small fw-semibold">Estado</label>
                    <select class="form-select"
                        onchange="location.href='?tipo='+this.value+'&buscar=<?= urlencode($buscar) ?>&id_periodo=<?= $id_periodo ?>'">
                        <?php foreach (['Todas' => 'Todas', 'Pendientes' => 'Pendientes', 'Aprobadas' => 'Aprobadas', 'Rechazadas' => 'Rechazadas'] as $key => $label): ?>
                            <option value="<?= htmlspecialchars($key) ?>"
                                <?= ($tipo_filtro === $key) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-8">
                    <label class="form-label mb-1 small fw-semibold">Buscar</label>
                    <form class="d-flex gap-2" method="GET">
                        <input type="hidden" name="tipo" value="<?= htmlspecialchars($tipo_filtro) ?>">
                        <input type="hidden" name="id_periodo" value="<?= $id_periodo ?>">
                        <input type="text" name="buscar" class="form-control"
                            placeholder="Buscar por título de proyecto..."
                            value="<?= htmlspecialchars($buscar) ?>">
                        <button type="submit" class="btn btn-primary">Buscar</button>
                    </form>
                </div>

            </div>
        </div>
    </div>

    <!-- TABLA / CARDS -->
    <?php if (!empty($solicitudes)): ?>

        <!-- ESCRITORIO -->
        <div class="card shadow-sm d-none d-md-block">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Proyecto</th>
                                <th>Estudiante</th>
                                <th>Investigador</th>
                                <th>Periodo</th>
                                <th>Fecha solicitud</th>
                                <th>Estado proceso</th>
                                <th>Estado carta</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($solicitudes as $sol): ?>
                                <tr>
                                    <td class="text-start" title="<?= htmlspecialchars($sol['titulo_proyecto']) ?>">
                                        <?= strlen($sol['titulo_proyecto']) > 45
                                            ? substr(htmlspecialchars($sol['titulo_proyecto']), 0, 45) . '…'
                                            : htmlspecialchars($sol['titulo_proyecto']) ?>
                                    </td>
                                    <td><?= htmlspecialchars($sol['estudiante']) ?></td>
                                    <td><?= htmlspecialchars($sol['investigador']) ?></td>
                                    <td><?= htmlspecialchars($sol['periodo']) ?></td>
                                    <td><?= htmlspecialchars($sol['fecha_solicitud']) ?></td>
                                    <td>
                                        <span class="badge text-bg-<?= $ctrl->estiloEstadoProceso($sol['estado_proceso']) ?>">
                                            <?= $ctrl->etiquetaEstadoProceso($sol['estado_proceso']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge text-bg-<?= $ctrl->estiloCarta($sol['estado_carta']) ?>">
                                            <?= $ctrl->etiquetaCarta($sol['estado_carta']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="d-flex gap-1 justify-content-center flex-wrap">
                                            <a href="detalles.php?id=<?= $sol['id_cierre_est'] ?>"
                                                class="btn btn-primary btn-sm"
                                                data-bs-toggle="tooltip" data-bs-title="Ver detalles">
                                                <i class="bi bi-eye-fill"></i>
                                            </a>
                                            <?php if ($sol['estado_carta'] === 'pendiente'): ?>
                                                <a href="index.php?action=aprobar&id=<?= $sol['id_cierre_est'] ?>"
                                                    class="btn btn-success btn-sm"
                                                    data-bs-toggle="tooltip" data-bs-title="Aprobar carta"
                                                    onclick="return confirm('¿Confirma que desea APROBAR la carta de terminación de <?= htmlspecialchars($sol['estudiante']) ?>?')">
                                                    <i class="bi bi-check-circle-fill"></i>
                                                </a>
                                                <a href="motivo_rechazo.php?id=<?= $sol['id_cierre_est'] ?>"
                                                    class="btn btn-danger btn-sm"
                                                    data-bs-toggle="tooltip" data-bs-title="Rechazar carta">
                                                    <i class="bi bi-x-circle-fill"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- MÓVIL -->
        <div class="d-block d-md-none mt-3">
            <?php foreach ($solicitudes as $sol): ?>
                <div class="card mb-3 shadow-sm">
                    <div class="card-body">
                        <h6 class="fw-bold mb-1">
                            <?= strlen($sol['titulo_proyecto']) > 60
                                ? substr(htmlspecialchars($sol['titulo_proyecto']), 0, 60) . '…'
                                : htmlspecialchars($sol['titulo_proyecto']) ?>
                        </h6>
                        <p class="mb-1 small"><strong>Estudiante:</strong> <?= htmlspecialchars($sol['estudiante']) ?></p>
                        <p class="mb-1 small"><strong>Investigador:</strong> <?= htmlspecialchars($sol['investigador']) ?></p>
                        <p class="mb-1 small"><strong>Periodo:</strong> <?= htmlspecialchars($sol['periodo']) ?></p>
                        <p class="mb-1 small"><strong>Solicitud:</strong> <?= htmlspecialchars($sol['fecha_solicitud']) ?></p>
                        <p class="mb-2 small">
                            <strong>Estado proceso:</strong>
                            <span class="badge text-bg-<?= $ctrl->estiloEstadoProceso($sol['estado_proceso']) ?>">
                                <?= $ctrl->etiquetaEstadoProceso($sol['estado_proceso']) ?>
                            </span>
                            &nbsp;
                            <strong>Carta:</strong>
                            <span class="badge text-bg-<?= $ctrl->estiloCarta($sol['estado_carta']) ?>">
                                <?= $ctrl->etiquetaCarta($sol['estado_carta']) ?>
                            </span>
                        </p>
                        <div class="d-flex gap-2 flex-wrap">
                            <a href="detalles.php?id=<?= $sol['id_cierre_est'] ?>" class="btn btn-primary btn-sm">
                                <i class="bi bi-eye-fill"></i> Detalles
                            </a>
                            <?php if ($sol['estado_carta'] === 'pendiente'): ?>
                                <a href="index.php?action=aprobar&id=<?= $sol['id_cierre_est'] ?>"
                                    class="btn btn-success btn-sm"
                                    onclick="return confirm('¿Aprobar la carta de <?= htmlspecialchars($sol['estudiante']) ?>?')">
                                    <i class="bi bi-check-circle-fill"></i> Aprobar
                                </a>
                                <a href="motivo_rechazo.php?id=<?= $sol['id_cierre_est'] ?>" class="btn btn-danger btn-sm">
                                    <i class="bi bi-x-circle-fill"></i> Rechazar
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- PAGINACIÓN -->
        <?php if ($paginacion['total_paginas'] > 1):
            $qBase = 'tipo='       . urlencode($tipo_filtro)
                . '&id_periodo=' . urlencode($id_periodo)
                . (!empty($buscar) ? '&buscar=' . urlencode($buscar) : '');
            $entidad = 'solicitudes';
            include __DIR__ . '../../../publico/incluido/_paginacion.php';
        endif; ?>

    <?php else: ?>
        <div class="alert alert-info text-center">
            <i class="bi bi-inbox"></i>
            No hay solicitudes de carta de terminación para mostrar.
        </div>
    <?php endif; ?>

</div>

<?php
$contenido = ob_get_clean();
$titulo    = 'Cartas de Terminación';
$bodyClass = 'cartas-terminacion-page';
include __DIR__ . '/../../layout.php';
?>