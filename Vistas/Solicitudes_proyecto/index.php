<?php
// Solicitudes_proyecto/index.php — Tabla principal con filtros y acciones.

session_start();

if (!isset($_SESSION['id_usuario'])) {
    header("Location: /ITSFCP-PROYECTOS/index.php");
    exit;
}

$rol        = strtolower($_SESSION['rol'] ?? '');
$id_usuario = intval($_SESSION['id_usuario']);

if ($rol !== 'supervisor') {
    header("Location: /ITSFCP-PROYECTOS/Vistas/Principal/index.php");
    exit;
}

require_once "../../Controladores/solicitudes_proyectoControlador.php";
$SolicitudesProyectoControlador = new SolicitudesProyectoControlador();

// 
// ACCIONES GET — deben procesarse ANTES de cualquier output
// 
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'actualizarestado') {
    $SolicitudesProyectoControlador->actualizarestado(
        intval($_GET['id_proyectos'] ?? 0),
        $rol,
        $_GET['tipo'] ?? ''
    );
    // actualizarestado() siempre llama redirigir() → exit, nunca llega aquí.
}

// 
// CARGA DE DATOS
// 
$tipo_filtro = $_GET['tipo']      ?? 'Todas';
$buscar      = $_GET['buscar']    ?? '';
$pagina      = intval($_GET['pagina']    ?? 1);
$id_periodo  = intval($_GET['id_periodo'] ?? 0);

$periodos  = $SolicitudesProyectoControlador->obtenerTodosPeriodos();
$resumen   = $SolicitudesProyectoControlador->resumenSolicitudes($rol, $id_usuario, $id_periodo);
$resultado = $SolicitudesProyectoControlador->listarSolicitudes($rol, $id_usuario, $tipo_filtro, $buscar, $pagina, $id_periodo);

$solicitudes = $resultado['solicitudes'] ?? [];
$paginacion  = $resultado['paginacion']  ?? [
    'total'         => count($solicitudes),
    'por_pagina'    => 6,
    'pagina'        => $pagina,
    'total_paginas' => max(1, ceil(count($solicitudes) / 6)),
];

// 
// MAPA DE ALERTAS
// 
$msg   = $_GET['msg'] ?? '';
$_mapa = [
    'exito_estado'        => ['tipo' => 'exito',  'titulo_msg' => 'Estado actualizado',    'mensaje' => 'El estado de la solicitud fue actualizado correctamente.'],
    'exito_rechazo'       => ['tipo' => 'exito',  'titulo_msg' => 'Rechazo registrado',    'mensaje' => 'El rechazo fue registrado y el investigador fue notificado.'],
    'error_estado'        => ['tipo' => 'error',  'titulo_msg' => 'Error de estado',       'mensaje' => 'No fue posible actualizar el estado de la solicitud.'],
    'error_rechazo'       => ['tipo' => 'error',  'titulo_msg' => 'Error en el rechazo',   'mensaje' => 'No fue posible registrar el rechazo. Verifica los datos e intenta de nuevo.'],
    'error_sin_registro'  => ['tipo' => 'error',  'titulo_msg' => 'Error al no tener registro',        'mensaje' => 'No fue posible cargar los datos. Intenta de nuevo.'],
    'error_cargar'        => ['tipo' => 'error',  'titulo_msg' => 'Error al cargar',        'mensaje' => 'No fue posible cargar los datos. Intenta de nuevo.'],
    'sin_permiso'         => ['tipo' => 'alerta', 'titulo_msg' => 'Acceso restringido',    'mensaje' => 'No tienes permiso para realizar esta acción.'],
    'accion_no_permitida' => ['tipo' => 'alerta', 'titulo_msg' => 'Acción no permitida',   'mensaje' => 'La acción solicitada no está disponible para tu rol.'],
    'sin_argumentos_url' => ['tipo' => 'alerta', 'titulo_msg' => 'No se han proporcionado parámetros en la URL.',   'mensaje' => 'La acción solicitada no está disponible por falta de parámetros en la URL.'],
];

ob_start();
?>

<div class="container-fluid py-4 ancho_container">

    <!-- CABECERA -->
    <div class="row mb-3 align-items-center">
        <?php
        $titulo      = 'Solicitudes de Proyecto';
        $descripcion = 'Solicitudes de registro de nuevos proyectos';
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
                            <?= ($id_periodo === intval($p['id_periodos'])) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($p['periodo']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>
    </div>

    <!-- ALERTAS -->
    <?php if (isset($_mapa[$msg])) : extract($_mapa[$msg]);
        include __DIR__ . '../../../publico/incluido/_mensaje.php';
    endif; ?>

    <!-- TARJETAS RESUMEN -->
    <?php if ($rol === 'supervisor'): ?>
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
                        <div class="display-6 fw-bold text-warning"><?= $resumen['pendientes_creacion'] ?? 0 ?></div>
                        <div class="text-muted small">Pendientes creación</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card text-center border-info shadow-sm h-100">
                    <div class="card-body">
                        <div class="display-6 fw-bold text-info"><?= $resumen['pendientes_cierre'] ?? 0 ?></div>
                        <div class="text-muted small">Pendientes cierre</div>
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
        </div>
    <?php endif; ?>

    <!-- FILTROS -->
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body py-2">
            <div class="row g-2 align-items-end">
                <div class="col-md-4 mb-1">
                    <label class="form-label mb-1 small fw-semibold">Tipo</label>
                    <select class="form-select"
                        onchange="location.href='?tipo=' + this.value + '&buscar=<?= urlencode($buscar) ?>&id_periodo=<?= $id_periodo ?>'">
                        <?php foreach (['Todas' => 'Todas', 'Creacion' => 'Creación', 'Cierre' => 'Cierre', 'Pendientes' => 'Pendientes'] as $key => $label): ?>
                            <option value="<?= htmlspecialchars($key) ?>" <?= ($tipo_filtro === $key) ? 'selected' : '' ?>>
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
                        <input type="text" name="buscar" class="form-control"
                            placeholder="Buscar por título..."
                            value="<?= htmlspecialchars($buscar) ?>">
                        <button type="submit" class="btn btn-primary">Buscar</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- TABLA -->
    <div class="row">
        <div class="col-12">
            <?php if (!empty($solicitudes)): ?>

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
                                        <th>Investigador</th>
                                        <th>Periodo</th>
                                        <th>Fecha solicitud</th>
                                        <th>Estado</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($solicitudes as $sol): ?>
                                        <tr>
                                            <th><?= $sol['id_proyectos'] ?></th>
                                            <td title="<?= htmlspecialchars($sol['titulo']) ?>">
                                                <?= strlen($sol['titulo']) > 50
                                                    ? substr($sol['titulo'], 0, 50) . '...'
                                                    : htmlspecialchars($sol['titulo']) ?>
                                            </td>
                                            <td>
                                                <?php
                                                $tipoLabel = ($sol['tipo_solicitud'] === 'creacion') ? 'Creación' : 'Cierre';
                                                $tipoBadge = ($sol['tipo_solicitud'] === 'creacion') ? 'primary' : 'dark';
                                                ?>
                                                <span class="badge text-bg-<?= $tipoBadge ?>"><?= $tipoLabel ?></span>
                                            </td>
                                            <td><?= htmlspecialchars($sol['investigador']) ?></td>
                                            <td><?= htmlspecialchars($sol['periodo']) ?></td>
                                            <td><?= $sol['fecha_solicitud'] ?></td>
                                            <td>
                                                <span class="badge text-bg-<?= $SolicitudesProyectoControlador->EstiloEstado($sol['estado_proyecto']) ?>">
                                                    <?= htmlspecialchars($sol['estado_proyecto']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?= $SolicitudesProyectoControlador->botonesAccionSolicitud(
                                                    $sol['id_proyectos'],
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
                <div class="d-block d-md-none mt-3">
                    <?php foreach ($solicitudes as $sol): ?>
                        <?php
                        $tipoLabel = ($sol['tipo_solicitud'] === 'creacion') ? 'Creación' : 'Cierre';
                        $tipoBadge = ($sol['tipo_solicitud'] === 'creacion') ? 'primary' : 'dark';
                        ?>
                        <div class="card mb-3 shadow-sm">
                            <div class="card-body">
                                <h6><?= htmlspecialchars($sol['titulo']) ?></h6>
                                <p><strong>Tipo:</strong> <span class="badge text-bg-<?= $tipoBadge ?>"><?= $tipoLabel ?></span></p>
                                <p><strong>Investigador:</strong> <?= htmlspecialchars($sol['investigador']) ?></p>
                                <p><strong>Periodo:</strong> <?= htmlspecialchars($sol['periodo']) ?></p>
                                <p><strong>Fecha:</strong> <?= $sol['fecha_solicitud'] ?></p>
                                <p>
                                    <strong>Estado:</strong>
                                    <span class="badge text-bg-<?= $SolicitudesProyectoControlador->EstiloEstado($sol['estado_proyecto']) ?>">
                                        <?= htmlspecialchars($sol['estado_proyecto']) ?>
                                    </span>
                                </p>
                                <div class="d-flex gap-2 flex-wrap">
                                    <?= $SolicitudesProyectoControlador->botonesAccionSolicitud(
                                        $sol['id_proyectos'],
                                        $rol,
                                        $sol['tipo_solicitud'],
                                        $sol['estado_proyecto']
                                    ) ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if ($paginacion['total_paginas'] > 1):
                    $qBase  = 'tipo='       . urlencode($tipo_filtro)
                        . '&id_periodo=' . urlencode($id_periodo)
                        . (!empty($buscar) ? '&buscar=' . urlencode($buscar) : '');
                    $entidad = 'solicitudes';
                    include __DIR__ . '../../../publico/incluido/_paginacion.php';
                endif; ?>

            <?php else: ?>
                <div class="alert alert-info text-center">No hay solicitudes para mostrar</div>
            <?php endif; ?>
        </div>
    </div>

</div>

<?php
$contenido = ob_get_clean();
$titulo    = "Solicitudes";
$bodyClass = "solicitudes-page";
include __DIR__ . '/../../layout.php';
?>