<?php
// Carreras/index.php

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

require_once __DIR__ .  '/../../Controladores/carreraControlador.php';

$carreraControlador = new carreraControlador();

//  Acción GET: desactivar carrera ─
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'desactivar_carrera') {
    $id_carrera = (int)($_GET['id_carrera'] ?? 0);
    $carreraControlador->desactivarCarrera($rol, $id_carrera);
    // desactivarCarrera() siempre redirige → el código no continúa.
}

//  Parámetros de listado 
$action = $_GET['action'] ?? 'Total';
$buscar = $_GET['buscar'] ?? '';
$pagina = (int)($_GET['pagina'] ?? 1);

$accionesFiltro = ['Total', 'Activo', 'Desactivado'];
if (!in_array($action, $accionesFiltro, true)) {
    $action = 'Total';
}

$resultado = $carreraControlador->$action($rol, $buscar);

$carreras   = $resultado['carrera']    ?? [];
$paginacion = $resultado['paginacion'] ?? [
    'total'         => count($carreras),
    'por_pagina'    => 6,
    'pagina'        => $pagina,
    'total_paginas' => max(1, (int)ceil(count($carreras) / 6)),
];

$encabezados = $carreraControlador->encabezadosPrincipal($rol);
$opciones    = $carreraControlador->opciones();

//  Mapa de mensajes ─
$msg   = $_GET['msg'] ?? '';
$_mapa = [
    'exito_crear'         => ['tipo' => 'exito',  'titulo_msg' => 'Carrera creada',          'mensaje' => 'La carrera fue creada correctamente.'],
    'exito_editar'        => ['tipo' => 'exito',  'titulo_msg' => 'Carrera actualizada',      'mensaje' => 'La carrera fue editada correctamente.'],
    'exito_desactivar'    => ['tipo' => 'exito',  'titulo_msg' => 'Carrera desactivada',      'mensaje' => 'La carrera fue desactivada correctamente.'],
    'exito_reactivar'     => ['tipo' => 'exito',  'titulo_msg' => 'Carrera reactivada',       'mensaje' => 'La carrera fue reactivada correctamente.'],
    'error_crear'         => ['tipo' => 'error',  'titulo_msg' => 'Error al crear',           'mensaje' => 'No fue posible crear la carrera. Verifica los datos e intenta de nuevo.'],
    'error_cargar'        => ['tipo' => 'error',  'titulo_msg' => 'Error al cargar',        'mensaje' => 'No fue posible cargar los datos. Intenta de nuevo.'],
    'error_editar'        => ['tipo' => 'error',  'titulo_msg' => 'Error al editar',          'mensaje' => 'No fue posible editar la carrera. Verifica los datos e intenta de nuevo.'],
    'error_desactivar'    => ['tipo' => 'error',  'titulo_msg' => 'Error al desactivar',      'mensaje' => 'No fue posible desactivar la carrera.'],
    'error_reactivar'     => ['tipo' => 'error',  'titulo_msg' => 'Error al reactivar',       'mensaje' => 'No fue posible reactivar la carrera.'],
    'error_duplicado'     => ['tipo' => 'error',  'titulo_msg' => 'Registro duplicado',       'mensaje' => 'Ya existe una carrera con ese nombre.'],
    'error_sin_registro'  => ['tipo' => 'error',  'titulo_msg' => 'Error al no tener registro',        'mensaje' => 'No fue posible cargar los datos. Intenta de nuevo.'],
    'accion_no_permitida' => ['tipo' => 'alerta', 'titulo_msg' => 'Acción no permitida',      'mensaje' => 'La acción solicitada no está disponible para tu rol.'],
    'sin_argumentos_url' => ['tipo' => 'alerta', 'titulo_msg' => 'No se han proporcionado parámetros en la URL.',   'mensaje' => 'La acción solicitada no está disponible por falta de parámetros en la URL.'],

];

ob_start();
?>

<div class="container-fluid py-4 ancho_container">

    <!-- TÍTULO -->
    <div class="row mb-4 align-items-center">
        <?php
        $titulo      = 'Carreras';
        $descripcion = 'Gestión de carreras registradas';
        include __DIR__ . '../../../publico/incluido/_encabezado.php';
        ?>
        <div class="col-6 col-md-6 text-md-end">
            <?php if ($rol === 'supervisor'): ?>
                <a href="crear.php" class="btn btn-primary">
                    <i class="bi bi-plus-lg"></i> Crear Carrera
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
            <div class="row g-2 align-items-end">
                <?php
                include __DIR__ . '../../../publico/incluido/_total_registros.php';
                ?>
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
                            placeholder="Buscar..."
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
                            <?php foreach ($encabezados as $enc): ?>
                                <th><?= htmlspecialchars($enc) ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($carreras)): ?>
                            <?php foreach ($carreras as $car): ?>
                                <tr>
                                    <td><?= htmlspecialchars($car['nombre_carrera']) ?></td>
                                    <td><?= date('d/m/Y', strtotime($car['crear'])) ?></td>
                                    <td><?= date('H:i',   strtotime($car['crear'])) ?></td>
                                    <td>
                                        <span class="badge rounded-pill text-bg-<?= $carreraControlador->EstiloEstadoLista($car['estados']) ?>">
                                            <?= htmlspecialchars($car['estados']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?= $carreraControlador->botonesAccionPrincipal(
                                            (int)$car['id_carrera'],
                                            $rol,
                                            $car['estados']
                                        ) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted py-3">
                                    No hay carreras registradas.
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
        <?php foreach ($carreras as $car): ?>
            <div class="card shadow-sm mb-3">
                <div class="card-body text-center">
                    <h5 class="fw-bold"><?= htmlspecialchars($car['nombre_carrera']) ?></h5>
                    <span class="badge rounded-pill text-bg-<?= $carreraControlador->EstiloEstadoLista($car['estados']) ?>">
                        <?= htmlspecialchars($car['estados']) ?>
                    </span>
                </div>
                <ul class="list-group list-group-flush">
                    <li class="list-group-item">
                        <div class="row text-center">
                            <div class="col-6">
                                <strong>Fecha creación</strong>
                                <p class="mb-0"><?= date('d/m/Y', strtotime($car['crear'])) ?></p>
                            </div>
                            <div class="col-6">
                                <strong>Hora creación</strong>
                                <p class="mb-0"><?= date('H:i', strtotime($car['crear'])) ?></p>
                            </div>
                        </div>
                    </li>
                </ul>
                <div class="card-body">
                    <div class="d-flex justify-content-center gap-2">
                        <?= $carreraControlador->botonesAccionPrincipal(
                            (int)$car['id_carrera'],
                            $rol,
                            $car['estados']
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

<?php
$contenido = ob_get_clean();
$titulo    = 'Carreras';
$bodyClass = 'proyectos-page';
include __DIR__ . '/../../layout.php';
?>