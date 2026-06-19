<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

if (!isset($_SESSION['id_usuario'])) {
    header('Location: /index.php');
    exit;
}

$rol        = strtolower($_SESSION['rol'] ?? '');
$id_usuario = intval($_SESSION['id_usuario']);

if ($rol !== 'supervisor') {
    header("Location: /Modules/Principal/Views/index.php");
    exit;
}

$action = $_GET['action'] ?? 'index';
$buscar = $_GET['buscar'] ?? '';
$pagina = intval($_GET['pagina'] ?? 1);

require_once __DIR__ . '/../Controller/linea_investigacion_controller.php';

$ctrl = new LineaInvestigacionControlador();

//  Acción: desactivar desde enlace GET 
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'desactivar_linea') {
    $ctrl->eliminar($rol, (int)($_GET['id_linea'] ?? 0));
    // eliminar() ya redirige; no continúa.
}

//  Obtener registros por acción ─
$accionesValidas = ['index', 'Total', 'Activo', 'Desactivado'];
if (!in_array($action, $accionesValidas, true)) {
    $action = 'index';
}

$resultado  = $ctrl->$action($rol, $buscar);
$lineas     = $resultado['linea']      ?? [];
$paginacion = $resultado['paginacion'] ?? [
    'total'         => count($lineas),
    'por_pagina'    => 6,
    'pagina'        => $pagina,
    'total_paginas' => max(1, (int)ceil(count($lineas) / 6)),
];

$encabezados = $ctrl->encabezadosPrincipal($rol);
$opciones    = $ctrl->opciones();

//  Mensajes ─
$msg   = $_GET['msg'] ?? '';
$_mapa = [
    'exito_crear'         => ['tipo' => 'exito',  'titulo_msg' => 'Línea creada',              'mensaje' => 'La línea de investigación fue creada correctamente.'],
    'exito_editar'        => ['tipo' => 'exito',  'titulo_msg' => 'Línea actualizada',         'mensaje' => 'La línea de investigación fue editada correctamente.'],
    'exito_desactivar'    => ['tipo' => 'exito',  'titulo_msg' => 'Línea desactivada',         'mensaje' => 'La línea de investigación fue desactivada correctamente.'],
    'exito_reactivar'     => ['tipo' => 'exito',  'titulo_msg' => 'Línea reactivada',          'mensaje' => 'La línea de investigación fue reactivada correctamente.'],
    'error_crear'         => ['tipo' => 'error',  'titulo_msg' => 'Error al crear',            'mensaje' => 'No fue posible crear la línea de investigación. Verifica los datos e intenta de nuevo.'],
    'error_cargar'        => ['tipo' => 'error',  'titulo_msg' => 'Error al cargar',        'mensaje' => 'No fue posible cargar los datos. Intenta de nuevo.'],
    'error_editar'        => ['tipo' => 'error',  'titulo_msg' => 'Error al editar',           'mensaje' => 'No fue posible editar la línea de investigación. Verifica los datos e intenta de nuevo.'],
    'error_desactivar'    => ['tipo' => 'error',  'titulo_msg' => 'Error al desactivar',       'mensaje' => 'No fue posible desactivar la línea de investigación.'],
    'error_reactivar'     => ['tipo' => 'error',  'titulo_msg' => 'Error al reactivar',        'mensaje' => 'No fue posible reactivar la línea de investigación.'],
    'error_duplicado'     => ['tipo' => 'alerta', 'titulo_msg' => 'Registro duplicado',        'mensaje' => 'Ya existe una línea de investigación con ese nombre.'],
    'error_sin_registro'  => ['tipo' => 'error',  'titulo_msg' => 'Error al no tener registro',        'mensaje' => 'No fue posible cargar los datos. Intenta de nuevo.'],
    'sin_argumentos_url' => ['tipo' => 'alerta', 'titulo_msg' => 'No se han proporcionado parámetros en la URL.',   'mensaje' => 'La acción solicitada no está disponible por falta de parámetros en la URL.'],
    'accion_no_permitida' => ['tipo' => 'alerta', 'titulo_msg' => 'Acción no permitida',       'mensaje' => 'La acción solicitada no está disponible para tu rol.'],
];

ob_start();
?>

<div class="container-fluid py-4 ancho_container">

    <!-- TÍTULO -->
    <div class="row mb-4 align-items-center">
        <?php
        $titulo      = 'Líneas de Investigación';
        $descripcion = 'Gestión de líneas de investigación';
        include __DIR__ . '/../../../public/incluido/_encabezado.php';
        ?>
        <div class="col-6 col-md-6 text-md-end">
            <a href="crear.php" class="btn btn-primary">
                <i class="bi bi-plus-lg"></i> Crear Línea de investigación
            </a>
        </div>
    </div>
    <!-- ALERTAS -->
    <?php
    if (isset($_mapa[$msg])) {
        extract($_mapa[$msg]);
        include __DIR__ . '/../../../public/incluido/_mensaje.php';
    }
    ?>
    <!-- FILTROS Y BÚSQUEDA -->
    <div class="card border-0 shadow-sm mb-3">

        <div class="card-body py-2">
            <?php
            include __DIR__ . '/../../../public/incluido/_total_registros.php';
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
                            placeholder="Buscar..."
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

    <!-- TABLA ESCRITORIO -->
    <div class="card shadow-sm d-none d-md-block">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <?php foreach ($encabezados as $enc): ?>
                                <th><?= $enc ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($lineas)): ?>
                            <?php foreach ($lineas as $lin): ?>
                                <tr>
                                    <td><?= htmlspecialchars($lin['nombre']) ?></td>
                                    <td title="<?= htmlspecialchars($lin['descripcion']) ?>">
                                        <?= strlen($lin['descripcion']) > 60
                                            ? htmlspecialchars(substr($lin['descripcion'], 0, 60)) . '…'
                                            : htmlspecialchars($lin['descripcion']) ?>
                                    </td>
                                    <td><?= date('d/m/Y', strtotime($lin['crear'])) ?></td>
                                    <td><?= date('H:i',   strtotime($lin['crear'])) ?></td>
                                    <td>
                                        <span class="badge rounded-pill text-bg-<?= $ctrl->EstiloEstadoLista($lin['estados']) ?>">
                                            <?= htmlspecialchars($lin['estados']) ?>
                                        </span>
                                    </td>
                                    <td><?= $ctrl->botonesAccionPrincipal((int)$lin['id_linea'], $rol, $lin['estados']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6">
                                    <div class="alert alert-info mb-0">No hay líneas de investigación registradas.</div>
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
        <?php if (!empty($lineas)): ?>
            <?php foreach ($lineas as $linea_item): ?>
                <div class="mcard">

                    <div class="mcard__head">
                        <div class="mcard__row-top">
                            <h3 class="mcard__title"><?= htmlspecialchars($linea_item['nombre']) ?></h3>
                            <span class="mcard__badge badge rounded-pill text-bg-<?= $ctrl->EstiloEstadoLista($linea_item['estados']) ?>">
                                Estado: <?= htmlspecialchars($linea_item['estados']) ?>
                            </span>
                        </div>
                    </div>

                    <div class="mcard__section">
                        <span class="mcard__label">Descripción</span>
                        <p class="mcard__text-clamp mb-0" title="<?= htmlspecialchars($linea_item['descripcion']) ?>">
                            <?= strlen($linea_item['descripcion']) > 60
                                ? htmlspecialchars(substr($linea_item['descripcion'], 0, 60)) . '…'
                                : htmlspecialchars($linea_item['descripcion']) ?>
                        </p>
                    </div>

                    <div class="mcard__section">
                        <div class="mcard__grid">
                            <div>
                                <span class="mcard__label">Fecha creación</span>
                                <span class="mcard__value"><?= date('d/m/Y', strtotime($linea_item['crear'])) ?></span>
                            </div>
                            <div>
                                <span class="mcard__label">Hora creación</span>
                                <span class="mcard__value"><?= date('H:i', strtotime($linea_item['crear'])) ?></span>
                            </div>
                        </div>
                    </div>

                    <div class="mcard__actions">
                        <?= $ctrl->botonesAccionPrincipal((int)$linea_item['id_linea'], $rol, $linea_item['estados']) ?>
                    </div>

                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="mcard-empty">No hay líneas de investigación registradas.</div>
        <?php endif; ?>
    </div>

    <!-- PAGINACIÓN -->
    <?php
    $qBase  = 'action=' . urlencode($action)
        . (!empty($buscar) ? '&buscar=' . urlencode($buscar) : '');
    $entidad = 'entradas';
    include __DIR__ . '/../../../public/incluido/_paginacion.php';
    ?>

</div>

<?php
$contenido = ob_get_clean();
$titulo    = 'Líneas de investigación';
$bodyClass = 'proyectos-page';
include __DIR__ . '/../../../layout.php';
?>