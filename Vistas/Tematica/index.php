<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

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

$action = $_GET['action'] ?? 'index';
$buscar = $_GET['buscar'] ?? '';
$pagina = intval($_GET['pagina'] ?? 1);

require_once __DIR__ . "/../../Controladores/tematicaControlador.php";

$tematicaControlador = new tematicaControlador();

//  ACCIÓN: DESACTIVAR 
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'desactivar_tematica') {
    $id_tematica = intval($_GET['id_tematica'] ?? 0);
    $tematicaControlador->desactivarTematica($id_tematica, $rol);
    // desactivarTematica ya redirige internamente
}

//  ACCIÓN: CARGAR TABLA 
if (!method_exists($tematicaControlador, $action)) {
    die("Error: La acción '$action' no existe en el controlador.");
}

$resultado = $tematicaControlador->$action($rol, $buscar);

if (is_string($resultado)) {
    $resultado = json_decode($resultado, true);
}

$tematica   = $resultado['tematica']   ?? [];
$paginacion = $resultado['paginacion'] ?? [
    'total'         => count($tematica),
    'por_pagina'    => 6,
    'pagina'        => $pagina,
    'total_paginas' => max(1, ceil(count($tematica) / 6)),
];

$encabezados = $tematicaControlador->encabezadosPrincipal($rol);
$opciones    = $tematicaControlador->opciones();

//  MENSAJES 
$msg = $_GET['msg'] ?? '';

$_mapa = [
    'exito_crear'         => ['tipo' => 'exito',  'titulo_msg' => 'Temática creada',         'mensaje' => 'La temática fue creada correctamente.'],
    'exito_editar'        => ['tipo' => 'exito',  'titulo_msg' => 'Temática actualizada',    'mensaje' => 'La temática fue editada correctamente.'],
    'exito_estado'        => ['tipo' => 'exito',  'titulo_msg' => 'Estado actualizado',      'mensaje' => 'El estado de la temática fue actualizado correctamente.'],
    'error_crear'         => ['tipo' => 'error',  'titulo_msg' => 'Error al crear',          'mensaje' => 'No fue posible crear la temática. Verifica los datos e intenta de nuevo.'],
    'error_cargar'        => ['tipo' => 'error',  'titulo_msg' => 'Error al cargar',         'mensaje' => 'No fue posible cargar los datos. Intenta de nuevo.'],
    'error_editar'        => ['tipo' => 'error',  'titulo_msg' => 'Error al editar',         'mensaje' => 'No fue posible editar la temática. Verifica los datos e intenta de nuevo.'],
    'error_estado'        => ['tipo' => 'error',  'titulo_msg' => 'Error de estado',         'mensaje' => 'No fue posible actualizar el estado de la temática.'],
    'sin_permiso'         => ['tipo' => 'alerta', 'titulo_msg' => 'Acceso restringido',      'mensaje' => 'No tienes permiso para ver esta temática.'],
    'accion_no_permitida' => ['tipo' => 'alerta', 'titulo_msg' => 'Acción no permitida',    'mensaje' => 'La acción solicitada no está disponible para tu rol.'],
    'error_sin_registro'  => ['tipo' => 'error',  'titulo_msg' => 'Sin registro',            'mensaje' => 'No fue posible cargar los datos. Intenta de nuevo.'],
    'sin_argumentos_url'  => ['tipo' => 'alerta', 'titulo_msg' => 'Parámetros faltantes',   'mensaje' => 'La acción solicitada no está disponible por falta de parámetros en la URL.'],
];

ob_start();
?>

<div class="container-fluid py-4 ancho_container">

    <!-- ENCABEZADO -->
    <div class="row mb-4 align-items-center">

        <?php
        $titulo      = 'Temáticas';
        $descripcion = 'Gestión de temáticas de investigación';
        include __DIR__ . '../../../publico/incluido/_encabezado.php';
        ?>

        <div class="col-6 col-md-6 text-md-end">
            <?php if ($rol === 'supervisor'): ?>
                <a href="crear.php" class="btn btn-primary">
                    <i class="bi bi-plus-lg"></i> Crear temática
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- ALERTAS -->
    <?php
    if (isset($_mapa[$msg])) {
        extract($_mapa[$msg]);
        include __DIR__ . '../../../publico/incluido/_mensaje.php';
    }
    ?>

    <!-- FILTROS Y BÚSQUEDA -->
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body py-2">

            <!-- TOTAL REGISTROS -->
            <?php include __DIR__ . '../../../publico/incluido/_total_registros.php'; ?>

            <div class="row g-2 align-items-end">

                <!-- FILTRO -->
                <div class="col-md-4 mb-1">
                    <label class="form-label mb-1 small fw-semibold">Estado</label>
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
                    <label class="form-label mb-1 small fw-semibold">Buscar</label>
                    <form class="d-flex gap-2" method="GET">
                        <input type="hidden" name="action" value="<?= htmlspecialchars($action) ?>">
                        <input type="text"
                            name="buscar"
                            class="form-control"
                            placeholder="Buscar temática..."
                            value="<?= htmlspecialchars($buscar) ?>">
                        <button type="submit" class="btn btn-primary">Buscar</button>
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
                            <?php foreach ($encabezados as $encabezado): ?>
                                <th><?= $encabezado ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($tematica)): ?>
                            <?php foreach ($tematica as $tem): ?>
                                <tr>
                                    <td><?= htmlspecialchars($tem['tematica']) ?></td>
                                    <td title="<?= htmlspecialchars($tem['descripcion']) ?>">
                                        <?= strlen($tem['descripcion']) > 60
                                            ? htmlspecialchars(substr($tem['descripcion'], 0, 60)) . '...'
                                            : htmlspecialchars($tem['descripcion']); ?>
                                    </td>
                                    <td><?= (int)$tem['total'] ?></td>
                                    <td>
                                        <span class="badge rounded-pill text-bg-<?= $tematicaControlador->EstiloEstadoLista($tem['estado']) ?>">
                                            <?= htmlspecialchars($tem['estado']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?= date("d/m/Y", strtotime($tem['creacion'])) ?><br>
                                        <?= date("H:i",   strtotime($tem['creacion'])) ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($tem['modificacion'])): ?>
                                            <?= date("d/m/Y", strtotime($tem['modificacion'])) ?><br>
                                            <?= date("H:i",   strtotime($tem['modificacion'])) ?>
                                        <?php else: ?>
                                            No modificado
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?= $tematicaControlador->botonesAccionPrincipal(
                                            $tem['id_tematica'],
                                            $rol,
                                            $tem['estado']
                                        ) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted py-3">
                                    No hay temáticas registradas.
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
        <?php if (!empty($tematica)): ?>
            <?php foreach ($tematica as $tem): ?>
                <div class="card shadow-sm mb-3">
                    <div class="card-body text-center">
                        <h5 class="fw-bold"><?= htmlspecialchars($tem['tematica']) ?></h5>
                    </div>
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item">
                            <strong>Descripción</strong>
                            <p class="mb-0" title="<?= htmlspecialchars($tem['descripcion']) ?>">
                                <?= strlen($tem['descripcion']) > 60
                                    ? htmlspecialchars(substr($tem['descripcion'], 0, 60)) . '...'
                                    : htmlspecialchars($tem['descripcion']); ?>
                            </p>
                        </li>
                        <li class="list-group-item">
                            <div class="row text-center">
                                <div class="col-6">
                                    <strong>Creación</strong>
                                    <p class="mb-0">
                                        <?= date("d/m/Y", strtotime($tem['creacion'])) ?><br>
                                        <?= date("H:i",   strtotime($tem['creacion'])) ?>
                                    </p>
                                </div>
                                <div class="col-6">
                                    <strong>Modificación</strong><br>
                                    <?php if (!empty($tem['modificacion'])): ?>
                                        <?= date("d/m/Y", strtotime($tem['modificacion'])) ?><br>
                                        <?= date("H:i",   strtotime($tem['modificacion'])) ?>
                                    <?php else: ?>
                                        No modificado
                                    <?php endif; ?>
                                </div>
                            </div>
                        </li>
                        <li class="list-group-item">
                            <div class="row text-center">
                                <div class="col-6">
                                    <strong>Subtemáticas</strong>
                                    <p class="mb-0"><?= (int)$tem['total'] ?></p>
                                </div>
                                <div class="col-6">
                                    <strong>Estado</strong><br>
                                    <span class="badge rounded-pill text-bg-<?= $tematicaControlador->EstiloEstadoLista($tem['estado']) ?>">
                                        <?= htmlspecialchars($tem['estado']) ?>
                                    </span>
                                </div>
                            </div>
                        </li>
                    </ul>
                    <div class="card-body">
                        <div class="d-flex justify-content-center gap-2">
                            <?= $tematicaControlador->botonesAccionPrincipal(
                                $tem['id_tematica'],
                                $rol,
                                $tem['estado']
                            ) ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="text-center text-muted py-3">No hay temáticas registradas.</div>
        <?php endif; ?>
    </div>

    <!-- PAGINACIÓN -->
    <?php if ($paginacion['total_paginas'] > 1):
        $qBase  = 'action=' . urlencode($action)
                . (!empty($buscar) ? '&buscar=' . urlencode($buscar) : '');
        $entidad = 'temáticas';
        include __DIR__ . '../../../publico/incluido/_paginacion.php';
    endif; ?>

</div>

<?php
$contenido = ob_get_clean();
$titulo    = "Temática y subtemática";
$bodyClass = "proyectos-page";

include __DIR__ . '/../../layout.php';
?>
