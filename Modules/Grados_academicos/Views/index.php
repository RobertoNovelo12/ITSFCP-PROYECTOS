<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

if (!isset($_SESSION['id_usuario'])) {
    header("Location: /index.php");
    exit;
}

$rol = strtolower($_SESSION['rol'] ?? '');
$id_usuario = intval($_SESSION['id_usuario']);

if ($rol !== 'supervisor') {
    header("Location: /Modules/Principal/Views/index.php");
    exit;
}


$action = $_GET['action'] ?? 'index';
$buscar = $_GET['buscar'] ?? '';
$pagina = intval($_GET['pagina'] ?? 1);

require_once __DIR__ . "/../Controller/grado_academico_controller.php";

$gradoacademicoControlador = new gradoacademicoControlador();

//  Acción: desactivar desde tabla 
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'desactivar_grados_academicos') {
    $id_grado = intval($_GET['id_grado'] ?? 0);
    $gradoacademicoControlador->eliminar($rol, $id_grado);
    // eliminar() ya redirige con msg; no llega aquí.
}

//  Acción dinámica de filtro 
if (!method_exists($gradoacademicoControlador, $action)) {
    $action = 'index';
}

$resultado  = $gradoacademicoControlador->$action($rol, $buscar);
$registros  = $resultado['grados_academicos'] ?? [];
$paginacion = $resultado['paginacion'] ?? [
    'total'         => count($registros),
    'por_pagina'    => 6,
    'pagina'        => $pagina,
    'total_paginas' => max(1, (int)ceil(count($registros) / 6)),
];

$encabezados = $gradoacademicoControlador->encabezadosPrincipal($rol);
$opciones    = $gradoacademicoControlador->opciones();

//  Mapa de mensajes 
$msg   = $_GET['msg'] ?? '';
$_mapa = [
    'exito_crear'         => ['tipo' => 'exito',  'titulo_msg' => 'Grado creado',          'mensaje' => 'El grado académico fue registrado correctamente.'],
    'exito_editar'        => ['tipo' => 'exito',  'titulo_msg' => 'Grado actualizado',      'mensaje' => 'El grado académico fue editado correctamente.'],
    'exito_desactivar'    => ['tipo' => 'exito',  'titulo_msg' => 'Grado desactivado',      'mensaje' => 'El grado académico fue desactivado correctamente.'],
    'exito_reactivar'     => ['tipo' => 'exito',  'titulo_msg' => 'Grado reactivado',       'mensaje' => 'El grado académico fue reactivado correctamente.'],
    'error_crear'         => ['tipo' => 'error',  'titulo_msg' => 'Error al crear',         'mensaje' => 'No fue posible registrar el grado académico. Verifica los datos e intenta de nuevo.'],
    'error_editar'        => ['tipo' => 'error',  'titulo_msg' => 'Error al editar',        'mensaje' => 'No fue posible editar el grado académico. Verifica los datos e intenta de nuevo.'],
    'error_desactivar'    => ['tipo' => 'error',  'titulo_msg' => 'Error al desactivar',    'mensaje' => 'No fue posible desactivar el grado académico.'],
    'error_reactivar'     => ['tipo' => 'error',  'titulo_msg' => 'Error al reactivar',     'mensaje' => 'No fue posible reactivar el grado académico.'],
    'error_duplicado'     => ['tipo' => 'alerta', 'titulo_msg' => 'Registro duplicado',     'mensaje' => 'Ya existe un grado académico con ese nombre. Intenta con otro.'],
    'error_cargar'        => ['tipo' => 'error',  'titulo_msg' => 'Error al cargar',        'mensaje' => 'No fue posible cargar los datos. Intenta de nuevo.'],
    'error_sin_registro'  => ['tipo' => 'error',  'titulo_msg' => 'Error al no tener registro',        'mensaje' => 'No fue posible cargar los datos. Intenta de nuevo.'],
    'sin_permiso'         => ['tipo' => 'alerta', 'titulo_msg' => 'Acceso restringido',     'mensaje' => 'No tienes permiso para ver este registro.'],
    'accion_no_permitida' => ['tipo' => 'alerta', 'titulo_msg' => 'Acción no permitida',    'mensaje' => 'La acción solicitada no está disponible para tu rol.'],
    'sin_argumentos_url' => ['tipo' => 'alerta', 'titulo_msg' => 'No se han proporcionado parámetros en la URL.',   'mensaje' => 'La acción solicitada no está disponible por falta de parámetros en la URL.'],
];

ob_start();
?>



<div class="container-fluid py-4 ancho_container">

    <!-- TÍTULO -->
    <div class="row mb-4 align-items-center">
        <?php
        $titulo      = 'Grados Académicos';
        $descripcion = 'Gestión de grados académicos';
        include __DIR__ . '/../../../public/incluido/_encabezado.php';
        ?>
        <div class="col-6 col-md-6 text-md-end">
            <?php if ($rol === 'supervisor'): ?>
                <a href="crear.php" class="btn btn-primary">
                    <i class="bi bi-plus-lg"></i> Crear Grado Académico
                </a>
            <?php endif; ?>
        </div>
    </div>
    <!-- ALERTAS -->
    <?php if (isset($_mapa[$msg])):
        extract($_mapa[$msg]);
        include __DIR__ . '/../../../public/incluido/_mensaje.php';
    endif; ?>
    <!-- FILTROS Y BÚSQUEDA -->
    <div class="card border-0 shadow-sm mb-3">

        <div class="card-body py-2">

            <!-- TOTAL REGISTROS -->
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
                        <?php if (!empty($registros)): ?>
                            <?php foreach ($registros as $reg): ?>
                                <tr>
                                    <td><?= htmlspecialchars($reg['nombre']) ?></td>
                                    <td><?= date("d/m/Y", strtotime($reg['crear'])) ?></td>
                                    <td><?= date("H:i",   strtotime($reg['crear'])) ?></td>
                                    <td>
                                        <span class="badge rounded-pill text-bg-<?= $gradoacademicoControlador->EstiloEstadoLista($reg['estados']) ?>">
                                            <?= htmlspecialchars($reg['estados']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?= $gradoacademicoControlador->botonesAccionPrincipal($reg['id_grado'], $rol, $reg['estados']) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5">
                                    <div class="alert alert-info mb-0">No hay grados académicos registrados.</div>
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
        <?php if (!empty($registros)): ?>
            <?php foreach ($registros as $reg): ?>
                <div class="mcard">

                    <div class="mcard__head">
                        <div class="mcard__row-top">
                            <h3 class="mcard__title"><?= htmlspecialchars($reg['nombre']) ?></h3>
                            <span class="mcard__badge badge rounded-pill text-bg-<?= $gradoacademicoControlador->EstiloEstadoLista($reg['estados']) ?>">
                                Estado: <?= htmlspecialchars($reg['estados']) ?>
                            </span>
                        </div>
                    </div>

                    <div class="mcard__section">
                        <div class="mcard__grid">
                            <div>
                                <span class="mcard__label">Fecha creación</span>
                                <span class="mcard__value"><?= date("d/m/Y", strtotime($reg['crear'])) ?></span>
                            </div>
                            <div>
                                <span class="mcard__label">Hora creación</span>
                                <span class="mcard__value"><?= date("H:i", strtotime($reg['crear'])) ?></span>
                            </div>
                        </div>
                    </div>

                    <div class="mcard__actions">
                        <?= $gradoacademicoControlador->botonesAccionPrincipal($reg['id_grado'], $rol, $reg['estados']) ?>
                    </div>

                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="mcard-empty">No hay grados académicos registrados.</div>
        <?php endif; ?>
    </div>

    <!-- PAGINACIÓN -->
    <?php
    $qBase   = 'action=' . urlencode($action)
        . (!empty($buscar) ? '&buscar=' . urlencode($buscar) : '');
    $entidad = 'grados';
    include __DIR__ . '/../../../public/incluido/_paginacion.php';
    ?>

</div>

<?php
$contenido = ob_get_clean();
$titulo    = 'Grado Académico';
$bodyClass = 'proyectos-page';
include __DIR__ . '/../../../layout.php';
