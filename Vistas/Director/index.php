<?php
// Vistas/Director/index.php

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

include "../../Controladores/directorControlador.php";

$action = $_GET['action'] ?? 'index';
$buscar = $_GET['buscar'] ?? '';
$pagina = intval($_GET['pagina'] ?? 1);

$directorControlador = new directorControlador();

//  Acción: desactivar desde tabla 
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'desactivar_director') {
    $id_director = intval($_GET['id_director'] ?? 0);
    $directorControlador->eliminar($rol, $id_director);
    // eliminar() ya redirige con msg; no llega aquí.
}

//  Acción dinámica de filtro 
if (!method_exists($directorControlador, $action)) {
    $action = 'index';
}

$resultado  = $directorControlador->$action($rol, $buscar);
$directores = $resultado['director'] ?? [];
$paginacion = $resultado['paginacion'] ?? [
    'total'         => count($directores),
    'por_pagina'    => 6,
    'pagina'        => $pagina,
    'total_paginas' => max(1, (int)ceil(count($directores) / 6)),
];

$filtros     = $directorControlador->filtros($rol);
$encabezados = $directorControlador->encabezadosPrincipal($rol);
$opciones    = $directorControlador->opciones($rol, $filtros);

//  Mapa de mensajes 
$msg    = $_GET['msg'] ?? '';
$_mapa  = [
    'exito_crear'         => ['tipo' => 'exito',  'titulo_msg' => 'Director creado',       'mensaje' => 'El director fue registrado correctamente.'],
    'exito_editar'        => ['tipo' => 'exito',  'titulo_msg' => 'Director actualizado',  'mensaje' => 'Los datos del director fueron editados correctamente.'],
    'exito_desactivar'    => ['tipo' => 'exito',  'titulo_msg' => 'Director desactivado',  'mensaje' => 'El director fue desactivado correctamente.'],
    'exito_reactivar'     => ['tipo' => 'exito',  'titulo_msg' => 'Director reactivado',   'mensaje' => 'El director fue reactivado correctamente.'],
    'error_crear'         => ['tipo' => 'error',  'titulo_msg' => 'Error al crear',        'mensaje' => 'No fue posible registrar el director. Verifica los datos e intenta de nuevo.'],
    'error_editar'        => ['tipo' => 'error',  'titulo_msg' => 'Error al editar',       'mensaje' => 'No fue posible editar el director. Verifica los datos e intenta de nuevo.'],
    'error_desactivar'    => ['tipo' => 'error',  'titulo_msg' => 'Error al desactivar',   'mensaje' => 'No fue posible desactivar el director.'],
    'error_reactivar'     => ['tipo' => 'error',  'titulo_msg' => 'Error al reactivar',    'mensaje' => 'No fue posible reactivar el director.'],
    'error_duplicado'     => ['tipo' => 'alerta', 'titulo_msg' => 'Registro duplicado',    'mensaje' => 'Ya existe un director con ese correo. Intenta con otro.'],
    'error_cargar'        => ['tipo' => 'error',  'titulo_msg' => 'Error al cargar',       'mensaje' => 'No fue posible cargar los datos. Intenta de nuevo.'],
    'sin_permiso'         => ['tipo' => 'alerta', 'titulo_msg' => 'Acceso restringido',    'mensaje' => 'No tienes permiso para ver este director.'],
    'accion_no_permitida' => ['tipo' => 'alerta', 'titulo_msg' => 'Acción no permitida',   'mensaje' => 'La acción solicitada no está disponible para tu rol.'],
];

ob_start();
?>

<!-- ALERTAS -->
<?php if (isset($_mapa[$msg])):
    extract($_mapa[$msg]);
    include __DIR__ . '../../../publico/incluido/_mensaje.php';
endif; ?>

<div class="container-fluid py-4 ancho_container">

    <!-- TÍTULO -->
    <div class="row mb-4 align-items-center">
        <?php
        $titulo      = 'Directores';
        $descripcion = 'Gestión de directores registrados';
        include __DIR__ . '../../../publico/incluido/_encabezado.php';
        ?>
        <div class="col-12 col-md-6 text-md-end">
            <?php if ($rol === 'supervisor'): ?>
                <a href="crear.php" class="btn btn-primary">
                    <i class="bi bi-plus-lg"></i> Crear Director
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
                            placeholder="Buscar por nombre, apellido o correo..."
                            value="<?= htmlspecialchars($buscar) ?>">
                        <button type="submit" class="btn btn-primary">Buscar</button>
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
                        <?php if (!empty($directores)): ?>
                            <?php foreach ($directores as $dir): ?>
                                <tr>
                                    <td><?= htmlspecialchars($dir['nombre']) ?></td>
                                    <td><?= htmlspecialchars($dir['apellido']) ?></td>
                                    <td><?= htmlspecialchars($dir['correo'] ?? '—') ?></td>
                                    <td><?= htmlspecialchars($dir['telefono'] ?? '—') ?></td>
                                    <td><?= htmlspecialchars($dir['nombre_grado']) ?></td>
                                    <td>
                                        <span class="badge rounded-pill text-bg-<?= $directorControlador->EstiloEstadoLista($dir['estados']) ?>">
                                            <?= htmlspecialchars($dir['estados']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?= $directorControlador->botonesAccionPrincipal($dir['id_director'], $rol, $dir['estados']) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7">
                                    <div class="alert alert-info mb-0">No hay directores registrados.</div>
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
        <?php foreach ($directores as $dir): ?>
            <div class="card shadow-sm mb-3">
                <div class="card-body text-center">
                    <h5 class="fw-bold">
                        <?= htmlspecialchars($dir['nombre']) ?> <?= htmlspecialchars($dir['apellido']) ?>
                    </h5>
                    <span class="badge rounded-pill text-bg-<?= $directorControlador->EstiloEstadoLista($dir['estados']) ?>">
                        <?= htmlspecialchars($dir['estados']) ?>
                    </span>
                </div>
                <ul class="list-group list-group-flush">
                    <li class="list-group-item">
                        <div class="row text-center">
                            <div class="col-12">
                                <strong>Correo</strong>
                                <p class="mb-0"><?= htmlspecialchars($dir['correo'] ?? '—') ?></p>
                            </div>
                        </div>
                        <div class="row text-center mt-2">
                            <div class="col-6">
                                <strong>Teléfono</strong>
                                <p class="mb-0"><?= htmlspecialchars($dir['telefono'] ?? '—') ?></p>
                            </div>
                            <div class="col-6">
                                <strong>Grado</strong>
                                <p class="mb-0"><?= htmlspecialchars($dir['nombre_grado']) ?></p>
                            </div>
                        </div>
                    </li>
                </ul>
                <div class="card-body">
                    <div class="d-flex justify-content-center gap-2">
                        <?= $directorControlador->botonesAccionPrincipal($dir['id_director'], $rol, $dir['estados']) ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- PAGINACIÓN -->
    <?php if ($paginacion['total_paginas'] > 1):
        $qBase  = 'action=' . urlencode($action)
            . (!empty($buscar) ? '&buscar=' . urlencode($buscar) : '');
        $entidad = 'directores';
        include __DIR__ . '../../../publico/incluido/_paginacion.php';
    endif; ?>

</div>

<?php
$contenido = ob_get_clean();
$titulo    = 'Directores';
$bodyClass = 'proyectos-page';
include __DIR__ . '/../../layout.php';
