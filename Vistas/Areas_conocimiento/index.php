<?php
// Areas_conocimiento/index.php

session_start();

if (!isset($_SESSION['id_usuario'])) {
    header("Location: /ITSFCP-PROYECTOS/index.php");
    exit;
}

$rol        = strtolower($_SESSION['rol'] ?? '');
$id_usuario = (int)$_SESSION['id_usuario'];

if ($rol !== 'supervisor') {
    header("Location: /ITSFCP-PROYECTOS/Vistas/Principal/index.php");
    exit;
}

require_once '../../Controladores/AreaConocimientoControlador.php';

$areaControlador = new AreaConocimientoControlador();

//  Acción GET: desactivar área 
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'desactivar_area') {
    $id_area = (int)($_GET['id_area'] ?? 0);
    $areaControlador->desactivarArea($rol, $id_area);
    // desactivarArea() siempre redirige → el código.
}

//  Parámetros de listado 
$action = $_GET['action'] ?? 'Total';
$buscar = $_GET['buscar'] ?? '';
$pagina = (int)($_GET['pagina'] ?? 1);

// Acciones válidas de filtro de tabla
$accionesFiltro = ['Total', 'Activo', 'Desactivado'];
if (!in_array($action, $accionesFiltro, true)) {
    $action = 'Total';
}

$resultado = $areaControlador->$action($rol, $buscar);

$area       = $resultado['area']       ?? [];
$paginacion = $resultado['paginacion'] ?? [
    'total'         => count($area),
    'por_pagina'    => 6,
    'pagina'        => $pagina,
    'total_paginas' => max(1, (int)ceil(count($area) / 6)),
];

$filtros     = $areaControlador->filtros($rol);
$encabezados = $areaControlador->encabezadosPrincipal($rol);
$opciones    = $areaControlador->opciones($rol, $filtros);

//  Mapa de mensajes ─
$msg   = $_GET['msg'] ?? '';
$_mapa = [
    'exito_crear'        => ['tipo' => 'exito',  'titulo_msg' => 'Área creada',           'mensaje' => 'El área de conocimiento fue creada correctamente.'],
    'exito_editar'       => ['tipo' => 'exito',  'titulo_msg' => 'Área actualizada',       'mensaje' => 'El área de conocimiento fue editada correctamente.'],
    'exito_desactivar'   => ['tipo' => 'exito',  'titulo_msg' => 'Área desactivada',       'mensaje' => 'El área de conocimiento fue desactivada correctamente.'],
    'error_crear'        => ['tipo' => 'error',  'titulo_msg' => 'Error al crear',         'mensaje' => 'No fue posible crear el área. Verifica los datos e intenta de nuevo.'],
    'error_editar'       => ['tipo' => 'error',  'titulo_msg' => 'Error al editar',        'mensaje' => 'No fue posible editar el área. Verifica los datos e intenta de nuevo.'],
    'error_desactivar'   => ['tipo' => 'error',  'titulo_msg' => 'Error al desactivar',    'mensaje' => 'No fue posible desactivar el área.'],
    'error_duplicado'    => ['tipo' => 'error',  'titulo_msg' => 'Registro duplicado',     'mensaje' => 'Ya existe un área o subárea con ese nombre.'],
    'accion_no_permitida' => ['tipo' => 'alerta', 'titulo_msg' => 'Acción no permitida',    'mensaje' => 'La acción solicitada no está disponible para tu rol.'],
];

ob_start();
?>

<?php if (isset($_mapa[$msg])): extract($_mapa[$msg]);
    include __DIR__ . '../../../publico/incluido/_mensaje.php';
endif; ?>

<div class="container-fluid py-4 ancho_container">

    <!-- TÍTULO -->
    <div class="row mb-4 align-items-center">
        <?php
        $titulo      = 'Áreas de Conocimiento';
        $descripcion = 'Gestión de áreas de conocimiento';
        include __DIR__ . '../../../publico/incluido/_encabezado.php';
        ?>
        <div class="col-6 col-md-6 text-md-end">
            <?php if ($rol === 'supervisor'): ?>
                <a href="crear.php" class="btn btn-primary">
                    <i class="bi bi-plus-lg"></i> Crear área de conocimiento
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- FILTROS Y BÚSQUEDA -->
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body py-2">
            <div class="row g-2 align-items-end">
                <div class="col-md-4 mb-1">
                    <label class="form-label mb-1 small fw-semibold">Estado</label>
                    <select class="form-select"
                        onchange="location.href='index.php?action=' + this.value;">
                        <?php foreach ($opciones as $key => $label): ?>
                            <option value="<?= htmlspecialchars($key) ?>"
                                <?= ($action === $key) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-8 mb-1">
                    <label class="form-label mb-1 small fw-semibold">Buscar</label>
                    <form class="d-flex gap-2" method="GET" action="index.php">
                        <input type="hidden" name="action" value="<?= htmlspecialchars($action) ?>">
                        <input type="text"
                            name="buscar"
                            class="form-control"
                            placeholder="Buscar área..."
                            value="<?= htmlspecialchars($buscar) ?>">
                        <button type="submit" class="btn btn-primary">Buscar</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- TABLA ESCRITORIO -->
    <div class="row">
        <div class="col-12">
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
                                <?php if (!empty($area)): ?>
                                    <?php foreach ($area as $ar): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($ar['nombre']) ?></td>
                                            <td title="<?= htmlspecialchars($ar['descripcion']) ?>">
                                                <?= strlen($ar['descripcion']) > 60
                                                    ? htmlspecialchars(substr($ar['descripcion'], 0, 60)) . '...'
                                                    : htmlspecialchars($ar['descripcion']); ?>
                                            </td>
                                            <td><?= (int)$ar['total'] ?></td>
                                            <td>
                                                <span class="badge rounded-pill text-bg-<?= $areaControlador->EstiloEstadoLista($ar['estado']) ?>">
                                                    <?= htmlspecialchars($ar['estado']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?= date('d/m/Y', strtotime($ar['creacion'])) ?><br>
                                                <?= date('H:i',   strtotime($ar['creacion'])) ?>
                                            </td>
                                            <td>
                                                <?php if (!empty($ar['modificacion'])): ?>
                                                    <?= date('d/m/Y', strtotime($ar['modificacion'])) ?><br>
                                                    <?= date('H:i',   strtotime($ar['modificacion'])) ?>
                                                <?php else: ?>
                                                    No modificado
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?= $areaControlador->botonesAccionPrincipal(
                                                    (int)$ar['id_area'],
                                                    $rol,
                                                    $ar['estado']
                                                ) ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" class="text-center text-muted py-3">
                                            No se encontraron áreas de conocimiento.
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
                <?php foreach ($area as $ar): ?>
                    <div class="card shadow-sm mb-3">
                        <div class="card-body text-center">
                            <h5 class="fw-bold"><?= htmlspecialchars($ar['nombre']) ?></h5>
                        </div>
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item">
                                <strong>Descripción</strong>
                                <p class="mb-0" title="<?= htmlspecialchars($ar['descripcion']) ?>">
                                    <?= strlen($ar['descripcion']) > 60
                                        ? htmlspecialchars(substr($ar['descripcion'], 0, 60)) . '...'
                                        : htmlspecialchars($ar['descripcion']); ?>
                                </p>
                            </li>
                            <li class="list-group-item">
                                <div class="row text-center">
                                    <div class="col-6">
                                        <strong>Creación</strong>
                                        <p class="mb-0">
                                            <?= date('d/m/Y', strtotime($ar['creacion'])) ?><br>
                                            <?= date('H:i',   strtotime($ar['creacion'])) ?>
                                        </p>
                                    </div>
                                    <div class="col-6">
                                        <strong>Modificación</strong>
                                        <p class="mb-0">
                                            <?php if (!empty($ar['modificacion'])): ?>
                                                <?= date('d/m/Y', strtotime($ar['modificacion'])) ?><br>
                                                <?= date('H:i',   strtotime($ar['modificacion'])) ?>
                                            <?php else: ?>
                                                No modificado
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                </div>
                            </li>
                            <li class="list-group-item">
                                <div class="row text-center">
                                    <div class="col-6">
                                        <strong>Subáreas</strong>
                                        <p class="mb-0"><?= (int)$ar['total'] ?></p>
                                    </div>
                                    <div class="col-6">
                                        <strong>Estado</strong><br>
                                        <span class="badge rounded-pill text-bg-<?= $areaControlador->EstiloEstadoLista($ar['estado']) ?>">
                                            <?= htmlspecialchars($ar['estado']) ?>
                                        </span>
                                    </div>
                                </div>
                            </li>
                        </ul>
                        <div class="card-body">
                            <div class="d-flex justify-content-center gap-2">
                                <?= $areaControlador->botonesAccionPrincipal(
                                    (int)$ar['id_area'],
                                    $rol,
                                    $ar['estado']
                                ) ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- PAGINACIÓN -->
            <?php if ($paginacion['total_paginas'] > 1):
                $qBase  = 'action=' . urlencode($action)
                    . (!empty($buscar) ? '&buscar=' . urlencode($buscar) : '');
                $entidad = 'entradas';
                include __DIR__ . '../../../publico/incluido/_paginacion.php';
            endif; ?>

        </div>
    </div>
</div>

<?php
$contenido = ob_get_clean();
$titulo    = 'Área y subárea de conocimientos';
$bodyClass = 'proyectos-page';
include __DIR__ . '/../../layout.php';
?>