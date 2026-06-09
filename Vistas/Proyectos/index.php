<?php
// Proyectos/index.php — Módulo 1: solo lectura operativa + redirección a Módulo 2

session_start();

//  Guard de sesión 
if (!isset($_SESSION['id_usuario'])) {
    header('Location: /index.php');
    exit;
}

$rol        = strtolower($_SESSION['rol'] ?? '');
$id_usuario = (int)$_SESSION['id_usuario'];

//  Guard de rol 
if (!in_array($rol, ['investigador', 'profesor', 'estudiante', 'supervisor'], true)) {
    header('Location: /Vistas/Principal/index.php');
    exit;
}

//  Parámetros de entrada 
// Se sanean en el controlador; aquí solo se capturan.
$action     = $_GET['action']    ?? 'index';
$buscar     = $_GET['buscar']    ?? '';
$pagina     = max(1, (int)($_GET['pagina']     ?? 1));
$id_periodo = ($rol === 'supervisor') ? (int)($_GET['id_periodo'] ?? 0) : 0;

require_once '../../Controladores/proyectoControlador.php';
$proyectoControlador = new ProyectoControlador();
$periodo             = $proyectoControlador->periodoactual();

// Catálogo de periodos — solo necesario para el selector del supervisor.
$periodos = ($rol === 'supervisor') ? $proyectoControlador->obtenerTodosPeriodos() : [];

//  Acción: actualizarestado (GET) 
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'actualizarestado') {
    if (in_array($rol, ['supervisor'], true)
        && isset($_GET['id_proyectos'], $_GET['tipo'])
    ) {
        $proyectoControlador->actualizarestado(
            (int)$_GET['id_proyectos'],
            $rol,
            $_GET['tipo']
        );
        // actualizarestado() siempre llama redirigir() → exit, nunca llega aquí.
    }
    // Rol no autorizado o parámetros faltantes → ignorar y seguir mostrando la tabla.
    $action = 'index';
}


$accionesBase = ['index', 'Total', 'Activos', 'Cierre', 'PorCerrar', 'Vencido', 'actualizarestado', 'Concluidos'];

// El supervisor conserva acceso de solo lectura a todos los estados operativos.
$accionesPermitidas = $accionesBase;
if ($rol === 'supervisor') {
    $accionesPermitidas[] = 'Rechazados';
    $accionesPermitidas[] = 'Cierrerechazado';
    $accionesPermitidas[] = 'PorAprobar';
}

if (!in_array($action, $accionesPermitidas, true)) {
    header('Location: index.php?msg=accion_no_permitida');
    exit;
}

if (!method_exists($proyectoControlador, $action)) {
    header('Location: index.php?msg=accion_no_permitida');
    exit;
}

//  Ejecución de la acción 
$resultado = $proyectoControlador->$action($id_usuario, $rol, $buscar, $id_periodo);

if (is_string($resultado)) {
    $resultado = json_decode($resultado, true);
}

if (!is_array($resultado)) {
    header('Location: index.php?msg=error_cargar');
    exit;
}

$proyectos  = $resultado['proyectos'] ?? [];
$paginacion = $resultado['paginacion'] ?? [
    'total'         => count($proyectos),
    'por_pagina'    => 6,
    'pagina'        => $pagina,
    'total_paginas' => max(1, (int)ceil(count($proyectos) / 6)),
];

$encabezados = $proyectoControlador->encabezadosProyectos($rol);
$opciones    = $proyectoControlador->opcionesProyectos($rol);  

//  Ventana de registro de proyectos 
$hoy               = date('Y-m-d');
$puedeCrear_Editar = (
    $periodo
    && $hoy >= $periodo['fecha_inicio_proyectos']
    && $hoy <= $periodo['fecha_fin_proyectos']
);

//  Mensajes de alerta 
$msg   = $_GET['msg'] ?? '';
$_mapa = [
    'exito_crear'         => ['tipo' => 'exito',  'titulo_msg' => 'Proyecto creado',         'mensaje' => 'El proyecto fue creado. Puedes seguir su estado en Solicitudes.'],
    'exito_editar'        => ['tipo' => 'exito',  'titulo_msg' => 'Proyecto actualizado',    'mensaje' => 'El proyecto fue editado correctamente.'],
    'exito_estado'        => ['tipo' => 'exito',  'titulo_msg' => 'Estado actualizado',      'mensaje' => 'El estado del proyecto fue actualizado correctamente.'],
    'exito_operacion'     => ['tipo' => 'exito',  'titulo_msg' => 'Operación completada',    'mensaje' => 'La operación sobre el estudiante fue realizada correctamente.'],
    'error_crear'         => ['tipo' => 'error',  'titulo_msg' => 'Error al crear',          'mensaje' => 'No fue posible crear el proyecto. Verifica los datos e intenta de nuevo.'],
    'error_cargar'        => ['tipo' => 'error',  'titulo_msg' => 'Error al cargar',         'mensaje' => 'No fue posible cargar los datos. Intenta de nuevo.'],
    'error_editar'        => ['tipo' => 'error',  'titulo_msg' => 'Error al editar',         'mensaje' => 'No fue posible editar el proyecto. Verifica los datos e intenta de nuevo.'],
    'error_estado'        => ['tipo' => 'error',  'titulo_msg' => 'Error de estado',         'mensaje' => 'No fue posible actualizar el estado del proyecto.'],
    'error_operacion'     => ['tipo' => 'error',  'titulo_msg' => 'Error en la operación',   'mensaje' => 'No fue posible completar la operación sobre el estudiante.'],
    'sin_permiso'         => ['tipo' => 'alerta', 'titulo_msg' => 'Acceso restringido',      'mensaje' => 'No tienes permiso para ver la información del proyecto.'],
    'sin_permiso_tarea'   => ['tipo' => 'alerta', 'titulo_msg' => 'Acceso restringido',      'mensaje' => 'No tienes permiso para ver las tareas del proyecto.'],
    'accion_no_permitida' => ['tipo' => 'alerta', 'titulo_msg' => 'Acción no permitida',     'mensaje' => 'La acción solicitada no está disponible para tu rol.'],
    'periodo_vencido'     => ['tipo' => 'alerta', 'titulo_msg' => 'Fuera de periodo',        'mensaje' => 'La acción no está disponible fuera del período de registro de proyectos.'],
    'error_sin_registro'  => ['tipo' => 'error',  'titulo_msg' => 'Sin registro',            'mensaje' => 'No fue posible cargar los datos. Intenta de nuevo.'],
    'sin_argumentos_url'  => ['tipo' => 'alerta', 'titulo_msg' => 'Parámetros faltantes',    'mensaje' => 'La acción solicitada no está disponible por falta de parámetros en la URL.'],
];

ob_start();
?>

<div class="container-fluid py-4 ancho_container">

    <!-- HEADER -->
    <div class="row mb-3 align-items-center">
        <?php
        $titulo      = ($rol === 'supervisor') ? 'Proyectos Aprobados' : 'Proyectos';
        $descripcion = 'Listado de proyectos de investigación';
        include __DIR__ . '../../../publico/incluido/_encabezado.php';
        ?>
        <div class="col-md-6 text-md-end d-flex justify-content-end align-items-center gap-2 flex-wrap">
            <?php if (in_array($rol, ['investigador', 'profesor'], true)): ?>
                <?php if ($puedeCrear_Editar): ?>
                    <a href="crear.php" class="btn btn-primary">Crear proyecto</a>
                <?php else: ?>
                    <button class="btn btn-secondary" disabled title="Fuera del periodo de registro">
                        Crear proyecto
                    </button>
                <?php endif; ?>
            <?php endif; ?>

            <?php if (in_array($rol, ['investigador', 'profesor'], true)): ?>
                <!-- Acceso directo al Módulo de solicitudes para ver solicitudes propias -->
                <a href="../Solicitudes_proyecto/index.php" class="btn btn-outline-secondary ms-2">
                    <i class="bi bi-inbox me-1"></i> Mis solicitudes
                </a>
            <?php endif; ?>

            <?php if ($rol === 'supervisor'): ?>
                <!-- Filtro de periodo exclusivo del supervisor -->
                <form class="d-inline-flex align-items-center gap-2" method="GET">
                    <input type="hidden" name="action" value="<?= htmlspecialchars($action) ?>">
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
            <?php endif; ?>
        </div>
    </div>

    <!-- ALERTAS -->
    <?php if (isset($_mapa[$msg])):
        extract($_mapa[$msg]);
        include __DIR__ . '../../../publico/incluido/_mensaje.php';
    endif; ?>

    <?php if (in_array($rol, ['investigador', 'profesor'], true)): ?>
        <div class="alert alert-info d-flex align-items-center gap-2 py-2 mb-3" role="alert">
            <i class="bi bi-info-circle-fill flex-shrink-0"></i>
            <span>
                Los proyectos pendientes de aprobación o rechazados se gestionan en
                <a href="../Solicitudes_proyecto/index.php" class="alert-link fw-semibold">Mis solicitudes</a>.
            </span>
        </div>
    <?php endif; ?>

    <!-- FILTROS -->
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body py-2">
            <?php include __DIR__ . '../../../publico/incluido/_total_registros.php'; ?>
            <div class="row g-2 align-items-end">
                <!-- FILTRO DE ESTADO -->
                <div class="col-md-4 mb-1">
                    <label class="form-label mb-1 small fw-semibold">Estado</label>
                    <select class="form-select"
                        onchange="location.href='?action=' + this.value + '&buscar=<?= urlencode($buscar) ?>&id_periodo=<?= $id_periodo ?>'">
                        <?php foreach ($opciones as $key => $label): ?>
                            <option value="<?= htmlspecialchars($key) ?>"
                                <?= ($action === $key) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <!-- BUSCADOR -->
                <div class="col-md-8 mb-1">
                    <label class="form-label mb-1 small fw-semibold">Buscar</label>
                    <form class="d-flex gap-2" method="GET">
                        <input type="hidden" name="action" value="<?= htmlspecialchars($action) ?>">
                        <input type="hidden" name="id_periodo" value="<?= $id_periodo ?>">
                        <input type="text"
                               name="buscar"
                               class="form-control"
                               placeholder="Por nombre..."
                               value="<?= htmlspecialchars($buscar) ?>">
                        <button type="submit" class="btn btn-primary">Buscar</button>
                        <?php if (!empty($buscar)): ?>
                            <a href="?action=<?= urlencode($action) ?>&id_periodo=<?= $id_periodo ?>"
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

    <!-- TABLA / TARJETAS -->
    <div class="row">
        <div class="col-12">

            <?php if (!empty($proyectos)): ?>

                <!-- ESCRITORIO -->
                <div class="card shadow-sm d-none d-md-block">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead>
                                    <tr>
                                        <?php foreach ($encabezados as $enc): ?>
                                            <th><?= htmlspecialchars($enc) ?></th>
                                        <?php endforeach; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($proyectos as $proyecto): ?>
                                        <tr>
                                            <th><?= (int)$proyecto['id_proyectos'] ?></th>

                                            <td title="<?= htmlspecialchars($proyecto['titulo']) ?>">
                                                <?= htmlspecialchars(
                                                    mb_strlen($proyecto['titulo']) > 60
                                                        ? mb_substr($proyecto['titulo'], 0, 60) . '…'
                                                        : $proyecto['titulo']
                                                ) ?>
                                            </td>

                                            <td><?= htmlspecialchars($proyecto['fecha_inicio']) ?></td>
                                            <td><?= htmlspecialchars($proyecto['fecha_fin']) ?></td>

                                            <td>
                                                <span class="badge text-bg-<?= $proyectoControlador->EstiloEstado($proyecto['estado_proyecto']) ?>">
                                                    <?= htmlspecialchars($proyecto['estado_proyecto']) ?>
                                                </span>
                                            </td>

                                            <?php if ($rol === 'estudiante'): ?>
                                                <td>
                                                    <?php
                                                    $estadoEst = $proyecto['estado_estudiante'] ?? 'sin_asignar';
                                                    $claseEst  = match ($estadoEst) {
                                                        'baja'     => 'danger',
                                                        'concluido'=> 'success',
                                                        default    => 'primary',
                                                    };
                                                    ?>
                                                    <span class="badge text-bg-<?= $claseEst ?>">
                                                        <?= strtoupper(htmlspecialchars($estadoEst)) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <a href="../Seguimiento/index.php?id_proyectos=<?= (int)$proyecto['id_proyectos'] ?>"
                                                       class="btn btn-sm btn-primary">
                                                        <i class="bi bi-folder2-open"></i>
                                                    </a>
                                                </td>
                                            <?php endif; ?>

                                            <td><?= htmlspecialchars($proyecto['periodo']) ?></td>
                                            <td><?= (int)($proyecto['total'] ?? 0) ?></td>

                                            <td>
                                                <?= $proyectoControlador->botonesAccion(
                                                    (int)$proyecto['id_proyectos'],
                                                    $rol,
                                                    $proyecto['estado_proyecto'],
                                                    $id_usuario,
                                                    (int)($proyecto['puede_cerrar'] ?? 0),
                                                    $proyecto['estado_estudiante'] ?? null,
                                                    $puedeCrear_Editar
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
                    <?php foreach ($proyectos as $proyecto): ?>
                        <div class="card shadow-sm mb-3">
                            <div class="card-body text-center">
                                <h5 class="fw-bold"><?= htmlspecialchars($proyecto['titulo']) ?></h5>
                                <span class="badge rounded-pill text-bg-<?= $proyectoControlador->EstiloEstado($proyecto['estado_proyecto']) ?>">
                                    <?= htmlspecialchars($proyecto['estado_proyecto']) ?>
                                </span>
                            </div>
                            <ul class="list-group list-group-flush">
                                <li class="list-group-item">
                                    <div class="row text-center">
                                        <div class="col-12">
                                            <strong>Periodo</strong>
                                            <p class="mb-0"><?= htmlspecialchars($proyecto['periodo']) ?></p>
                                        </div>
                                    </div>
                                    <div class="row text-center mt-2">
                                        <div class="col-6">
                                            <strong>Inicio</strong>
                                            <p class="mb-0"><?= htmlspecialchars($proyecto['fecha_inicio']) ?></p>
                                        </div>
                                        <div class="col-6">
                                            <strong>Fin</strong>
                                            <p class="mb-0"><?= htmlspecialchars($proyecto['fecha_fin']) ?></p>
                                        </div>
                                    </div>
                                    <div class="row text-center mt-2">
                                        <div class="col-6">
                                            <strong>Pendientes</strong>
                                            <p class="mb-0"><?= (int)($proyecto['total'] ?? 0) ?></p>
                                        </div>
                                        <?php if ($rol === 'estudiante'): ?>
                                            <div class="col-6">
                                                <?php
                                                $estadoEst = $proyecto['estado_estudiante'] ?? 'activo';
                                                $claseEst  = match ($estadoEst) {
                                                    'baja'      => 'danger',
                                                    'concluido' => 'success',
                                                    default     => 'primary',
                                                };
                                                ?>
                                                <strong>Estado</strong>
                                                <p class="mb-0">
                                                    <span class="badge rounded-pill text-bg-<?= $claseEst ?>">
                                                        <?= strtoupper(htmlspecialchars($estadoEst)) ?>
                                                    </span>
                                                </p>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </li>
                            </ul>
                            <div class="card-body">
                                <div class="d-flex justify-content-center gap-2 flex-wrap">
                                    <?= $proyectoControlador->botonesAccion(
                                        (int)$proyecto['id_proyectos'],
                                        $rol,
                                        $proyecto['estado_proyecto'],
                                        null,
                                        (int)($proyecto['puede_cerrar'] ?? 0),
                                        $proyecto['estado_estudiante'] ?? null,
                                        $puedeCrear_Editar
                                    ) ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- PAGINACIÓN -->
                <?php
                $qBase   = 'action=' . urlencode($action)
                    . '&id_periodo=' . $id_periodo
                    . (!empty($buscar) ? '&buscar=' . urlencode($buscar) : '');
                $entidad = 'proyectos';
                include __DIR__ . '../../../publico/incluido/_paginacion.php';
                ?>

            <?php else: ?>
                <div class="alert alert-info text-center">No hay proyectos para mostrar.</div>
            <?php endif; ?>

        </div>
    </div>
</div>

<?php
$contenido = ob_get_clean();
$titulo    = 'Proyectos';
$bodyClass = 'proyectos-page';
include __DIR__ . '/../../layout.php';
?>
