<?php
// Periodo/index.php

session_start();

if (!isset($_SESSION['id_usuario'])) {
    header("Location: /index.php");
    exit;
}

$rol = strtolower($_SESSION['rol'] ?? '');
$id_usuario = (int)$_SESSION['id_usuario'];

if ($rol !== 'supervisor') {
    header("Location: /Vistas/Principal/index.php");
    exit;
}

require_once __DIR__ .  '/../../Controladores/periodoControlador.php';

$periodoControlador = new periodoControlador();

//  Acción GET: desactivar periodo ─
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'desactivar_periodo') {
    $id_periodos = (int)($_GET['id_periodos'] ?? 0);
    $periodoControlador->desactivarPeriodo($rol, $id_periodos);
    // Siempre redirige → el código no continúa.
}

//  Parámetros de listado 
$action = $_GET['action'] ?? 'Total';
$buscar = $_GET['buscar'] ?? '';
$pagina = (int)($_GET['pagina'] ?? 1);

$accionesFiltro = ['Total', 'Activo', 'Terminado', 'Desactivado'];
if (!in_array($action, $accionesFiltro, true)) {
    $action = 'Total';
}

$resultado  = $periodoControlador->$action($rol, $buscar);
$periodos   = $resultado['periodo']    ?? [];
$paginacion = $resultado['paginacion'] ?? [
    'total'         => count($periodos),
    'por_pagina'    => 6,
    'pagina'        => $pagina,
    'total_paginas' => max(1, (int)ceil(count($periodos) / 6)),
];

$encabezados = $periodoControlador->encabezadosPrincipal($rol);
$opciones    = $periodoControlador->opciones();

//  Mapa de mensajes ─
$msg   = $_GET['msg'] ?? '';
$_mapa = [
    'exito_crear'         => ['tipo' => 'exito',  'titulo_msg' => 'Periodo creado',         'mensaje' => 'El periodo fue creado correctamente.'],
    'exito_editar'        => ['tipo' => 'exito',  'titulo_msg' => 'Periodo actualizado',     'mensaje' => 'Las fechas del periodo fueron actualizadas correctamente.'],
    'exito_desactivar'    => ['tipo' => 'exito',  'titulo_msg' => 'Periodo desactivado',     'mensaje' => 'El periodo fue desactivado correctamente.'],
    'exito_reactivar'     => ['tipo' => 'exito',  'titulo_msg' => 'Periodo reactivado',      'mensaje' => 'El periodo fue reactivado correctamente.'],
    'error_crear'         => ['tipo' => 'error',  'titulo_msg' => 'Error al crear',          'mensaje' => 'No fue posible crear el periodo. Verifica los datos e intenta de nuevo.'],
    'error_editar'        => ['tipo' => 'error',  'titulo_msg' => 'Error al editar',         'mensaje' => 'No fue posible actualizar las fechas del periodo.'],
    'error_desactivar'    => ['tipo' => 'error',  'titulo_msg' => 'Error al desactivar',     'mensaje' => 'No fue posible desactivar el periodo.'],
    'error_reactivar'     => ['tipo' => 'error',  'titulo_msg' => 'Error al reactivar',      'mensaje' => 'No fue posible reactivar el periodo.'],
    'error_duplicado'     => ['tipo' => 'error',  'titulo_msg' => 'Registro duplicado',      'mensaje' => 'Ya existe un periodo con esas fechas o nombre.'],
    'error_cargar'        => ['tipo' => 'error',  'titulo_msg' => 'Error al cargar',        'mensaje' => 'No fue posible cargar los datos. Intenta de nuevo.'],
    'error_sin_registro'  => ['tipo' => 'error',  'titulo_msg' => 'Error al no tener registro',        'mensaje' => 'No fue posible cargar los datos. Intenta de nuevo.'],
    'sin_argumentos_url' => ['tipo' => 'alerta', 'titulo_msg' => 'No se han proporcionado parámetros en la URL.',   'mensaje' => 'La acción solicitada no está disponible por falta de parámetros en la URL.'],
    'accion_no_permitida' => ['tipo' => 'alerta', 'titulo_msg' => 'Acción no permitida',     'mensaje' => 'La acción solicitada no está disponible para tu rol.'],
];

ob_start();
?>

<div class="container-fluid py-4 ancho_container">

    <!-- TÍTULO -->
    <div class="row mb-4 align-items-center">
        <?php
        $titulo      = 'Periodos';
        $descripcion = 'Gestión de periodos académicos';
        include __DIR__ . '../../../publico/incluido/_encabezado.php';
        ?>
        <div class="col-12 col-md-6 text-md-end">
            <?php if ($rol === 'supervisor'): ?>
                <a href="crear.php" class="btn btn-primary">
                    <i class="bi bi-plus-lg"></i> Crear Periodo
                </a>
            <?php endif; ?>
        </div>
    </div>

    <?php if (isset($_mapa[$msg])): extract($_mapa[$msg]);
        include __DIR__ . '../../../publico/incluido/_mensaje.php';
    endif; ?>

    <!-- FILTROS Y BÚSQUEDA -->
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body py-2">
            <!-- TOTAL REGISTROS -->
            <?php
            include __DIR__ . '../../../publico/incluido/_total_registros.php';
            ?>
            <div class="row g-2 align-items-end">
                <!-- FILTRO -->
                <div class="col-md-4 mb-1">
                    <label class="form-label mb-1 small fw-semibold">
                        Estado
                    </label>
                    <select class="form-select"
                        onchange="location.href='?action=' + this.value + '&buscar=<?= urlencode($buscar) ?>'">
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
                    <label class="form-label mb-1 small fw-semibold">
                        Buscar
                    </label>
                    <form class="d-flex gap-2" method="GET">

                        <input type="hidden"
                            name="action"
                            value="<?= htmlspecialchars($action) ?>">
                        <input type="text"
                            name="buscar"
                            class="form-control"
                            placeholder="Por nombre..."
                            value="<?= htmlspecialchars($buscar) ?>">

                        <button type="submit" class="btn btn-primary">
                            Buscar
                        </button>
                        <?php if (!empty($buscar)): ?>
                            <a href="?action=<?= urlencode($action) ?>"
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

    <!-- AVISO para desactivados -->
    <?php if ($action === 'Desactivado'): ?>
        <div class="alert alert-secondary d-flex align-items-center gap-2 mb-4" role="alert">
            <i class="bi bi-info-circle-fill flex-shrink-0"></i>
            <div>
                Los periodos <strong>desactivados administrativamente</strong> se muestran aquí.
                Solo pueden reactivarse aquellos cuyo semestre aún no ha terminado.
            </div>
        </div>
    <?php endif; ?>

    <!-- TABLA ESCRITORIO -->
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
                        <?php if (!empty($periodos)): ?>
                            <?php foreach ($periodos as $per): ?>
                                <tr class="<?= ($per['estados'] === 'Desactivado') ? 'table-secondary' : '' ?>">
                                    <td><?= htmlspecialchars($per['periodo']) ?></td>
                                    <td><?= date('d/m/Y', strtotime($per['inicio'])) ?></td>
                                    <td><?= date('d/m/Y', strtotime($per['final'])) ?></td>
                                    <td><?= date('d/m/Y', strtotime($per['crear'])) ?></td>
                                    <td><?= date('H:i',   strtotime($per['crear'])) ?></td>
                                    <td>
                                        <span class="badge rounded-pill text-bg-<?= $periodoControlador->EstiloEstadoLista($per['estados']) ?>">
                                            <?= htmlspecialchars($per['estados']) ?>
                                        </span>
                                        <?php if ($per['estados'] === 'Desactivado' && !$per['puede_reactivar']): ?>
                                            <br><small class="text-muted">Semestre pasado</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?= $periodoControlador->botonesAccionPrincipal(
                                            (int)$per['id_periodos'],
                                            $rol,
                                            $per['estados'],
                                            (int)($per['puede_reactivar'] ?? 0)
                                        ) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted py-3">
                                    No hay periodos en esta categoría.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- TARJETAS MÓVIL -->
    <div class="d-block d-md-none">
        <?php if (!empty($periodos)): ?>
            <?php foreach ($periodos as $per): ?>
                <div class="card shadow-sm mb-3 <?= ($per['estados'] === 'Desactivado') ? 'border-secondary' : '' ?>">
                    <div class="card-body text-center">
                        <h5 class="fw-bold"><?= htmlspecialchars($per['periodo']) ?></h5>
                        <span class="badge rounded-pill text-bg-<?= $periodoControlador->EstiloEstadoLista($per['estados']) ?>">
                            <?= htmlspecialchars($per['estados']) ?>
                        </span>
                        <?php if ($per['estados'] === 'Desactivado' && !$per['puede_reactivar']): ?>
                            <br><small class="text-muted">Semestre pasado — no reactivable</small>
                        <?php endif; ?>
                    </div>
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item">
                            <div class="row text-center">
                                <div class="col-6">
                                    <strong>Fecha inicio</strong>
                                    <p class="mb-0"><?= date('d/m/Y', strtotime($per['inicio'])) ?></p>
                                </div>
                                <div class="col-6">
                                    <strong>Fecha final</strong>
                                    <p class="mb-0"><?= date('d/m/Y', strtotime($per['final'])) ?></p>
                                </div>
                            </div>
                            <div class="row text-center mt-2">
                                <div class="col-6">
                                    <strong>Fecha Creación</strong>
                                    <p class="mb-0"><?= date('d/m/Y', strtotime($per['crear'])) ?></p>
                                </div>
                                <div class="col-6">
                                    <strong>Hora Creación</strong>
                                    <p class="mb-0"><?= date('H:i', strtotime($per['crear'])) ?></p>
                                </div>
                            </div>
                        </li>
                    </ul>
                    <div class="card-body">
                        <div class="d-flex justify-content-center gap-2">
                            <?= $periodoControlador->botonesAccionPrincipal(
                                (int)$per['id_periodos'],
                                $rol,
                                $per['estados'],
                                (int)($per['puede_reactivar'] ?? 0)
                            ) ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="alert alert-info">No hay periodos en esta categoría.</div>
        <?php endif; ?>
    </div>

    <!-- PAGINACIÓN -->
    <?php if ($paginacion['total_paginas'] > 1):
        $qBase  = 'action=' . urlencode($action)
            . (!empty($buscar) ? '&buscar=' . urlencode($buscar) : '');
        $entidad = 'periodos';
        include __DIR__ . '../../../publico/incluido/_paginacion.php';
    endif; ?>

</div>

<?php
$contenido = ob_get_clean();
$titulo    = 'Períodos';
$bodyClass = 'proyectos-page';
include __DIR__ . '/../../layout.php';
?>