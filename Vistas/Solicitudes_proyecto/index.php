<?php
// Solicitudes_proyectos/index.php
// Accesible por supervisor, investigador y profesor.
// Cada rol tiene dashboard y acciones distintas.

session_start();

//  Guard de sesión 
if (!isset($_SESSION['id_usuario'])) {
    header('Location: /index.php');
    exit;
}

$rol        = strtolower($_SESSION['rol'] ?? '');
$id_usuario = (int)$_SESSION['id_usuario'];

//  Solo accede el supervisor y el investigador/profesor que es el solicitante.
if (!in_array($rol, ['supervisor', 'investigador', 'profesor'], true)) {
    header('Location: /Vistas/Principal/index.php');
    exit;
}

require_once __DIR__ . '/../../Controladores/solicitudes_proyectoControlador.php';
$ctrl = new SolicitudesProyectoControlador();

//  Acciones GET 
// Deben procesarse ANTES de cualquier output.
$action = $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action !== '') {

    $id_proyecto = (int)($_GET['id_proyectos'] ?? 0);

    // Supervisor: aprobar proyecto/cierre
    if ($action === 'actualizarestado' && $rol === 'supervisor') {
        $ctrl->actualizarestado($id_proyecto, $rol, $_GET['tipo'] ?? '');
        // actualizarestado() siempre llama redirigir() → exit, nunca llega aquí.

        // Investigador / Profesor: solicitar cierre desde Módulo 2
    } elseif ($action === 'solicitarCierre' && in_array($rol, ['investigador', 'profesor'], true)) {
        $ctrl->solicitarCierre($id_proyecto, $rol, $id_usuario);
        // solicitarCierre() siempre llama redirigir() → exit.

        // Investigador / Profesor: reenviar cierre rechazado
    } elseif ($action === 'reenviarCierre' && in_array($rol, ['investigador', 'profesor'], true)) {
        $ctrl->reenviarCierre($id_proyecto, $rol, $id_usuario);
        // reenviarCierre() siempre llama redirigir() → exit.

    } else {
        // Acción desconocida o no permitida para este rol.
        header('Location: index.php?msg=accion_no_permitida');
        exit;
    }
}

//  Carga de datos 
$tipo_filtro = $_GET['tipo']       ?? 'Todas';
$buscar      = $_GET['buscar']     ?? '';
$pagina      = max(1, (int)($_GET['pagina']     ?? 1));
$id_periodo  = (int)($_GET['id_periodo'] ?? 0);

// Parámetro opcional: preseleccionar un proyecto (p.ej. desde botón del Módulo 1)
$id_proyecto_sel = (int)($_GET['id_proyectos'] ?? 0);

$periodos    = $ctrl->obtenerTodosPeriodos();
$resumen     = $ctrl->resumenSolicitudes($rol, $id_usuario, $id_periodo);
$resultado   = $ctrl->listarSolicitudes($rol, $id_usuario, $tipo_filtro, $buscar, $pagina, $id_periodo);

$solicitudes = $resultado['solicitudes'] ?? [];
$paginacion  = $resultado['paginacion']  ?? [
    'total'         => count($solicitudes),
    'por_pagina'    => 6,
    'pagina'        => $pagina,
    'total_paginas' => max(1, (int)ceil(count($solicitudes) / 6)),
];

//  Mapa de alertas 
$msg   = $_GET['msg'] ?? '';
$_mapa = [
    // Supervisor
    'exito_estado'           => ['tipo' => 'exito',  'titulo_msg' => 'Estado actualizado',      'mensaje' => 'El estado de la solicitud fue actualizado correctamente.'],
    'exito_rechazo'          => ['tipo' => 'exito',  'titulo_msg' => 'Rechazo registrado',      'mensaje' => 'El rechazo fue registrado y el investigador fue notificado.'],
    'error_estado'           => ['tipo' => 'error',  'titulo_msg' => 'Error de estado',         'mensaje' => 'No fue posible actualizar el estado de la solicitud.'],
    'error_rechazo'          => ['tipo' => 'error',  'titulo_msg' => 'Error en el rechazo',     'mensaje' => 'No fue posible registrar el rechazo. Verifica los datos e intenta de nuevo.'],
    // Investigador / Profesor
    'exito_cierre_solicitado' => ['tipo' => 'exito',  'titulo_msg' => 'Cierre solicitado',       'mensaje' => 'Tu solicitud de cierre fue enviada al supervisor para revisión.'],
    'exito_reenvio'          => ['tipo' => 'exito',  'titulo_msg' => 'Solicitud reenviada',     'mensaje' => 'Tu solicitud fue reenviada al supervisor para revisión.'],
    'error_reenvio'          => ['tipo' => 'error',  'titulo_msg' => 'Error al reenviar',       'mensaje' => 'No fue posible reenviar la solicitud. Intenta de nuevo.'],
    // Comunes
    'error_sin_registro'     => ['tipo' => 'error',  'titulo_msg' => 'Sin registro',            'mensaje' => 'No fue posible cargar los datos. Intenta de nuevo.'],
    'error_cargar'           => ['tipo' => 'error',  'titulo_msg' => 'Error al cargar',         'mensaje' => 'No fue posible cargar los datos. Intenta de nuevo.'],
    'sin_permiso'            => ['tipo' => 'alerta', 'titulo_msg' => 'Acceso restringido',      'mensaje' => 'No tienes permiso para realizar esta acción.'],
    'accion_no_permitida'    => ['tipo' => 'alerta', 'titulo_msg' => 'Acción no permitida',     'mensaje' => 'La acción solicitada no está disponible para tu rol.'],
    'sin_argumentos_url'     => ['tipo' => 'alerta', 'titulo_msg' => 'Parámetros faltantes',    'mensaje' => 'La acción solicitada no está disponible por falta de parámetros en la URL.'],
];

ob_start();
?>

<div class="container-fluid py-4 ancho_container">

    <!-- CABECERA -->
    <div class="row mb-3 align-items-center">
        <?php
        $titulo      = in_array($rol, ['investigador', 'profesor'], true)
            ? 'Mis solicitudes'
            : 'Solicitudes de Proyecto';
        $descripcion = in_array($rol, ['investigador', 'profesor'], true)
            ? 'Estado de tus solicitudes de creación y cierre'
            : 'Solicitudes de registro y cierre de proyectos';
        include __DIR__ . '../../../publico/incluido/_encabezado.php';
        ?>
        <div class="col-md-6 text-md-end d-flex justify-content-end align-items-center gap-2 flex-wrap">

            <?php if (in_array($rol, ['investigador', 'profesor'], true)): ?>
                <!-- Acceso rápido al Módulo 1 -->
                <a href="../Proyectos/index.php" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-folder2 me-1"></i> Mis proyectos activos
                </a>
            <?php endif; ?>

            <!-- Filtro de periodo -->
            <form class="d-inline-flex align-items-center gap-2" method="GET">
                <input type="hidden" name="tipo" value="<?= htmlspecialchars($tipo_filtro) ?>">
                <input type="hidden" name="buscar" value="<?= htmlspecialchars($buscar) ?>">
                <label class="mb-0 text-nowrap fw-semibold small">Periodo:</label>
                <select name="id_periodo" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="0" <?= $id_periodo === 0 ? 'selected' : '' ?>>Todos</option>
                    <?php foreach ($periodos as $p): ?>
                        <option value="<?= (int)$p['id_periodos'] ?>"
                            <?= ($id_periodo === (int)$p['id_periodos']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($p['periodo']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>
    </div>

    <!-- ALERTAS -->
    <?php if (isset($_mapa[$msg])):
        extract($_mapa[$msg]);
        include __DIR__ . '../../../publico/incluido/_mensaje.php';
    endif; ?>

    <!-- 
         DASHBOARD — Tarjetas de resumen
     -->

    <?php if ($rol === 'supervisor'): ?>

        <div class="row mb-4 g-3">
            <div class="col-6 col-md-3">
                <div class="card text-center border-primary shadow-sm h-100">
                    <div class="card-body">
                        <div class="display-6 fw-bold text-primary"><?= (int)($resumen['total'] ?? 0) ?></div>
                        <div class="text-muted small">Total solicitudes</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card text-center border-warning shadow-sm h-100">
                    <div class="card-body">
                        <div class="display-6 fw-bold text-warning"><?= (int)($resumen['pendientes_creacion'] ?? 0) ?></div>
                        <div class="text-muted small">Pendientes creación</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card text-center border-info shadow-sm h-100">
                    <div class="card-body">
                        <div class="display-6 fw-bold text-info"><?= (int)($resumen['pendientes_cierre'] ?? 0) ?></div>
                        <div class="text-muted small">Pendientes cierre</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card text-center border-success shadow-sm h-100">
                    <div class="card-body">
                        <div class="display-6 fw-bold text-success"><?= (int)($resumen['aprobadas'] ?? 0) ?></div>
                        <div class="text-muted small">Aprobadas</div>
                    </div>
                </div>
            </div>
        </div>

    <?php elseif (in_array($rol, ['investigador', 'profesor'], true)): ?>

        <div class="row mb-4 g-3">
            <div class="col-6 col-md-3">
                <a href="?tipo=Creacion&id_periodo=<?= $id_periodo ?>" class="text-decoration-none">
                    <div class="card text-center border-warning shadow-sm h-100">
                        <div class="card-body">
                            <div class="display-6 fw-bold text-warning"><?= (int)($resumen['pendientes_creacion'] ?? 0) ?></div>
                            <div class="text-muted small">Pend. aprobación (creación)</div>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-6 col-md-3">
                <a href="?tipo=Cierre&id_periodo=<?= $id_periodo ?>" class="text-decoration-none">
                    <div class="card text-center border-info shadow-sm h-100">
                        <div class="card-body">
                            <div class="display-6 fw-bold text-info"><?= (int)($resumen['pendientes_cierre'] ?? 0) ?></div>
                            <div class="text-muted small">Pend. aprobación (cierre)</div>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-6 col-md-3">
                <a href="?tipo=RequierenAccion&id_periodo=<?= $id_periodo ?>" class="text-decoration-none">
                    <div class="card text-center border-danger shadow-sm h-100">
                        <div class="card-body">
                            <div class="display-6 fw-bold text-danger"><?= (int)($resumen['requieren_accion'] ?? 0) ?></div>
                            <div class="text-muted small">Requieren corrección</div>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-6 col-md-3">
                <div class="card text-center border-success shadow-sm h-100">
                    <div class="card-body">
                        <div class="display-6 fw-bold text-success"><?= (int)($resumen['aprobadas'] ?? 0) ?></div>
                        <div class="text-muted small">Aprobadas (histórico)</div>
                    </div>
                </div>
            </div>
        </div>

    <?php endif; ?>

    <!-- 
         FILTROS
     -->
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body py-2">
            <?php include __DIR__ . '../../../publico/incluido/_total_registros.php'; ?>
            <div class="row g-2 align-items-end">
                <div class="col-md-4 mb-1">
                    <label class="form-label mb-1 small fw-semibold">Tipo</label>
                    <select class="form-select"
                        onchange="location.href='?tipo=' + this.value + '&buscar=<?= urlencode($buscar) ?>&id_periodo=<?= $id_periodo ?>'">
                        <?php
                        // El investigador tiene un filtro extra "Requieren acción"
                        $filtros = ['Todas' => 'Todas', 'Creacion' => 'Creación', 'Cierre' => 'Cierre', 'Pendientes' => 'Pendientes'];
                        if (in_array($rol, ['investigador', 'profesor'], true)) {
                            $filtros['RequierenAccion'] = 'Requieren acción';
                        }
                        foreach ($filtros as $key => $label):
                        ?>
                            <option value="<?= htmlspecialchars($key) ?>"
                                <?= ($tipo_filtro === $key) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-8 mb-1">
                    <label class="form-label mb-1 small fw-semibold">Buscar</label>
                    <form class="d-flex gap-2" method="GET">
                        <input type="hidden" name="tipo" value="<?= htmlspecialchars($tipo_filtro) ?>">
                        <input type="hidden" name="id_periodo" value="<?= $id_periodo ?>">
                        <input type="text"
                            name="buscar"
                            class="form-control"
                            placeholder="Buscar por título..."
                            value="<?= htmlspecialchars($buscar) ?>">
                        <button type="submit" class="btn btn-primary">Buscar</button>
                        <?php if (!empty($buscar)): ?>
                            <a href="?tipo=<?= urlencode($tipo_filtro) ?>&id_periodo=<?= $id_periodo ?>"
                                class="btn btn-secondary"
                                title="Limpiar búsqueda">
                                <i class="bi bi-x-lg"></i>
                            </a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- 
         TABLA / TARJETAS
     -->
    <div class="row">
        <div class="col-12">
            <?php if (!empty($solicitudes)): ?>

                <!-- Aviso contextual para el investigador en sección "En revisión" -->
                <?php if (in_array($rol, ['investigador', 'profesor'], true) && in_array($tipo_filtro, ['Todas', 'Pendientes', 'Creacion', 'Cierre'], true)): ?>
                    <div class="alert alert-info d-flex align-items-center gap-2 py-2 mb-3" role="alert">
                        <i class="bi bi-hourglass-split flex-shrink-0"></i>
                        <span>Las solicitudes <strong>Por aprobar</strong> y <strong>Por cerrar</strong> están siendo revisadas por el supervisor.</span>
                    </div>
                <?php endif; ?>

                <!-- ESCRITORIO -->
                <div class="card shadow-sm d-none d-md-block">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Título</th>
                                        <th>Tipo solicitud</th>
                                        <?php if ($rol === 'supervisor'): ?>
                                            <th>Investigador</th>
                                        <?php endif; ?>
                                        <th>Periodo</th>
                                        <th>Fecha solicitud</th>
                                        <th>Estado</th>
                                        <?php if (in_array($rol, ['investigador', 'profesor'], true)): ?>
                                            <th>Comentario supervisor</th>
                                        <?php endif; ?>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($solicitudes as $sol): ?>
                                        <tr>
                                            <th><?= (int)$sol['id_proyectos'] ?></th>

                                            <td title="<?= htmlspecialchars($sol['titulo']) ?>">
                                                <?= htmlspecialchars(
                                                    mb_strlen($sol['titulo']) > 50
                                                        ? mb_substr($sol['titulo'], 0, 50) . '…'
                                                        : $sol['titulo']
                                                ) ?>
                                            </td>

                                            <td>
                                                <?php
                                                $tipoLabel = ($sol['tipo_solicitud'] === 'creacion') ? 'Creación' : 'Cierre';
                                                $tipoBadge = ($sol['tipo_solicitud'] === 'creacion') ? 'primary' : 'dark';
                                                ?>
                                                <span class="badge text-bg-<?= $tipoBadge ?>"><?= $tipoLabel ?></span>
                                            </td>

                                            <?php if ($rol === 'supervisor'): ?>
                                                <td><?= htmlspecialchars($sol['investigador']) ?></td>
                                            <?php endif; ?>

                                            <td><?= htmlspecialchars($sol['periodo']) ?></td>
                                            <td><?= htmlspecialchars($sol['fecha_solicitud']) ?></td>

                                            <td>
                                                <span class="badge text-bg-<?= $ctrl->EstiloEstado($sol['estado_proyecto']) ?>">
                                                    <?= htmlspecialchars($sol['estado_proyecto']) ?>
                                                </span>
                                            </td>

                                            <?php if (in_array($rol, ['investigador', 'profesor'], true)): ?>
                                                <td class="text-muted small" style="max-width:200px">
                                                    <?php if (!empty($sol['comentario_preview'])): ?>
                                                        <span title="<?= htmlspecialchars($sol['comentario_preview']) ?>">
                                                            <?= htmlspecialchars(
                                                                mb_strlen($sol['comentario_preview']) > 80
                                                                    ? mb_substr($sol['comentario_preview'], 0, 80) . '…'
                                                                    : $sol['comentario_preview']
                                                            ) ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="text-muted">—</span>
                                                    <?php endif; ?>
                                                </td>
                                            <?php endif; ?>

                                            <td>
                                                <?= $ctrl->botonesAccionSolicitud(
                                                    (int)$sol['id_proyectos'],
                                                    $rol,
                                                    $sol['tipo_solicitud'],
                                                    $sol['estado_proyecto']
                                                ) ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- MÓVIL -->
                <div class="d-block d-md-none mt-4">
                    <?php foreach ($solicitudes as $sol): ?>
                        <?php
                        $tipoLabel = ($sol['tipo_solicitud'] === 'creacion') ? 'Creación' : 'Cierre';
                        $tipoBadge = ($sol['tipo_solicitud'] === 'creacion') ? 'primary' : 'dark';
                        ?>

                        <div class="card shadow-sm mb-3">

                            <!-- Encabezado -->
                            <div class="card-body text-center">
                                <h5 class="fw-bold"><?= htmlspecialchars($sol['titulo']) ?></h5>

                                <span class="badge rounded-pill text-bg-<?= $ctrl->EstiloEstado($sol['estado_proyecto']) ?>">
                                    <?= htmlspecialchars($sol['estado_proyecto']) ?>
                                </span>
                            </div>

                            <!-- Información -->
                            <ul class="list-group list-group-flush">
                                <li class="list-group-item">

                                    <div class="row text-center">
                                        <div class="col-12">
                                            <strong>Tipo de solicitud</strong>
                                            <p class="mb-0">
                                                <span class="badge text-bg-<?= $tipoBadge ?>">
                                                    <?= $tipoLabel ?>
                                                </span>
                                            </p>
                                        </div>
                                    </div>

                                    <?php if ($rol === 'supervisor'): ?>
                                        <div class="row text-center mt-2">
                                            <div class="col-12">
                                                <strong>Investigador</strong>
                                                <p class="mb-0">
                                                    <?= htmlspecialchars($sol['investigador']) ?>
                                                </p>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <div class="row text-center mt-2">
                                        <div class="col-6">
                                            <strong>Periodo</strong>
                                            <p class="mb-0">
                                                <?= htmlspecialchars($sol['periodo']) ?>
                                            </p>
                                        </div>

                                        <div class="col-6">
                                            <strong>Fecha</strong>
                                            <p class="mb-0">
                                                <?= htmlspecialchars($sol['fecha_solicitud']) ?>
                                            </p>
                                        </div>
                                    </div>

                                    <?php if (
                                        in_array($rol, ['investigador', 'profesor'], true) &&
                                        !empty($sol['comentario_preview'])
                                    ): ?>
                                        <div class="row text-center mt-2">
                                            <div class="col-12">
                                                <strong>Comentario</strong>
                                                <p class="mb-0 small text-muted">
                                                    <?= htmlspecialchars(mb_substr($sol['comentario_preview'], 0, 100)) ?>
                                                    <?= mb_strlen($sol['comentario_preview']) > 100 ? '…' : '' ?>
                                                </p>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                </li>
                            </ul>

                            <!-- Botones -->
                            <div class="card-body">
                                <div class="d-flex justify-content-center gap-2 flex-wrap">
                                    <?= $ctrl->botonesAccionSolicitud(
                                        (int)$sol['id_proyectos'],
                                        $rol,
                                        $sol['tipo_solicitud'],
                                        $sol['estado_proyecto']
                                    ) ?>
                                </div>
                            </div>

                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- PAGINACIÓN -->
                <?php
                $qBase   = 'tipo='       . urlencode($tipo_filtro)
                    . '&id_periodo=' . urlencode((string)$id_periodo)
                    . (!empty($buscar) ? '&buscar=' . urlencode($buscar) : '');
                $entidad = 'solicitudes';
                include __DIR__ . '../../../publico/incluido/_paginacion.php';
                ?>

            <?php else: ?>
                <div class="alert alert-info text-center">No hay solicitudes para mostrar.</div>
            <?php endif; ?>

        </div>
    </div>

</div>

<?php
$contenido = ob_get_clean();
$titulo    = in_array($rol, ['investigador', 'profesor'], true) ? 'Mis solicitudes' : 'Solicitudes';
$bodyClass = 'solicitudes-page';
include __DIR__ . '/../../layout.php';
?>