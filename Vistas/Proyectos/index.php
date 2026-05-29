<?php
/*Proyectos/index.php - Página principal */
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

if (!isset($_SESSION['id_usuario'])) {
    header("Location: /ITSFCP-PROYECTOS/index.php");
    exit;
}

$rol = strtolower($_SESSION['rol'] ?? '');
$id_usuario = intval($_SESSION['id_usuario']);

//Todos los roles pueden acceder
if (!in_array($rol, ['investigador', 'profesor', 'estudiante', 'supervisor'], true)) {
    header("Location: /ITSFCP-PROYECTOS/Vistas/Principal/index.php");
    exit;
}

$action = $_GET['action'] ?? 'index';
$buscar = $_GET['buscar'] ?? '';
$pagina = intval($_GET['pagina'] ?? 1);

require_once "../../Controladores/proyectoControlador.php";
$proyectoControlador = new ProyectoControlador();
$periodo = $proyectoControlador->periodoactual();

// Solo acciones de proyectos (no solicitudes)
$accionesPermitidas = ['index', 'Total', 'Activos', 'Cierre', 'PorAprobar', 'Rechazados', 'PorCerrar', 'Vencido', 'Cierrerechazado'];
if (!in_array($action, $accionesPermitidas)) {
    header("Location: index.php?msg=accion_no_permitida");
}

if (!method_exists($proyectoControlador, $action)) {
    die("Error: La acción '$action' no existe en el controlador.");
}

$resultado = $proyectoControlador->$action($id_usuario, $rol, $buscar);

if (is_string($resultado)) {
    $resultado = json_decode($resultado, true);
}

if (!is_array($resultado)) {
    die("Error: La acción '$action' no devolvió un array válido.");
}

$proyectos = $resultado['proyectos'] ?? [];

$paginacion = $resultado['paginacion'] ?? [
    'total'        => count($proyectos),
    'por_pagina'   => 6,
    'pagina'       => $pagina,
    'total_paginas' => max(1, ceil(count($proyectos) / 6))
];

$encabezados = $proyectoControlador->encabezadosProyectos($rol);
$opciones   = $proyectoControlador->opcionesProyectos($rol);

//PARA ACTIVAR O DESACTIVAR EL CREAR Y EDITAR PROYECTO
// ¿Puede el investigador crear proyecto hoy?
$hoy = date('Y-m-d');
$puedeCrear_Editar = ($hoy >= $periodo['fecha_inicio_proyectos']
    && $hoy <= $periodo['fecha_fin_proyectos']);

//  Mensaje de éxito/error ─
$msg = $_GET['msg'] ?? '';

$_mapa = [
    'exito_crear'        => ['tipo' => 'exito',  'titulo_msg' => 'Proyecto creado',        'mensaje' => 'El proyecto fue creado correctamente.'],
    'exito_editar'       => ['tipo' => 'exito',  'titulo_msg' => 'Proyecto actualizado',   'mensaje' => 'El proyecto fue editado correctamente.'],
    'exito_estado'       => ['tipo' => 'exito',  'titulo_msg' => 'Estado actualizado',     'mensaje' => 'El estado del proyecto fue actualizado correctamente.'],
    'exito_operacion'    => ['tipo' => 'exito',  'titulo_msg' => 'Operación completada',   'mensaje' => 'La operación sobre el estudiante fue realizada correctamente.'],
    'error_crear'        => ['tipo' => 'error',  'titulo_msg' => 'Error al crear',         'mensaje' => 'No fue posible crear el proyecto. Verifica los datos e intenta de nuevo.'],
    'error_cargar'        => ['tipo' => 'error',  'titulo_msg' => 'Error al cargar',        'mensaje' => 'No fue posible cargar los datos. Intenta de nuevo.'],
    'error_editar'       => ['tipo' => 'error',  'titulo_msg' => 'Error al editar',        'mensaje' => 'No fue posible editar el proyecto. Verifica los datos e intenta de nuevo.'],
    'error_estado'       => ['tipo' => 'error',  'titulo_msg' => 'Error de estado',        'mensaje' => 'No fue posible actualizar el estado del proyecto.'],
    'error_operacion'    => ['tipo' => 'error',  'titulo_msg' => 'Error en la operación',  'mensaje' => 'No fue posible completar la operación sobre el estudiante.'],
    'sin_permiso'        => ['tipo' => 'alerta', 'titulo_msg' => 'Acceso restringido',     'mensaje' => 'No tienes permiso para ver la información del proyecto.'],
    'sin_permiso_tarea'   => ['tipo' => 'alerta', 'titulo_msg' => 'Acceso restringido',     'mensaje' => 'No tienes permiso para ver las tareas del proyecto.'],
    'accion_no_permitida' => ['tipo' => 'alerta', 'titulo_msg' => 'Acción no permitida',   'mensaje' => 'La acción solicitada no está disponible para tu rol.'],
    'periodo_vencido' => ['tipo' => 'alerta', 'titulo_msg' => 'Acción no permitida',   'mensaje' => 'La acción solicitada no está disponible al no estar en el periodo indicado para hacer correcciones o modificaciones al proyecto.'],
    'error_sin_registro'  => ['tipo' => 'error',  'titulo_msg' => 'Error al no tener registro',        'mensaje' => 'No fue posible cargar los datos. Intenta de nuevo.'],
    'sin_argumentos_url' => ['tipo' => 'alerta', 'titulo_msg' => 'No se han proporcionado parámetros en la URL.',   'mensaje' => 'La acción solicitada no está disponible por falta de parámetros en la URL.'],
];

ob_start();
?>

<div class="container-fluid py-4 ancho_container">

    <!-- HEADER -->
    <div class="row mb-3 align-items-center">
        <?php
        if ($rol === 'supervisor') {
            $titulo = 'Proyectos Aprobados';
        } elseif ($rol === 'estudiante') {
            $titulo = 'Proyectos';
        } else {
            $titulo = 'Proyectos';
        }
        $descripcion = 'Listado de proyectos de investigación';
        include __DIR__ . '../../../publico/incluido/_encabezado.php';
        ?>
        <div class="col-md-6 text-md-end">
            <?php if ($rol === 'investigador' || $rol === 'profesor'):  ?>
                <?php if ($puedeCrear_Editar): ?>
                    <a href="crear.php" class="btn btn-primary">Crear proyecto</a>
                <?php else: ?>
                    <button class="btn btn-secondary" disabled title="Fuera del periodo de registro">
                        Crear proyecto
                    </button>
                <?php endif; ?>
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

    <!-- FILTROS -->
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body py-2">
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

    <!-- TABLA -->
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
                                        <?php foreach ($encabezados as $encabezado): ?>
                                            <th><?= htmlspecialchars($encabezado) ?></th>
                                        <?php endforeach; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($proyectos as $proyecto): ?>
                                        <tr>

                                            <th><?= $proyecto['id_proyectos'] ?></th>

                                            <td title="<?= htmlspecialchars($proyecto['titulo']) ?>">
                                                <?= strlen($proyecto['titulo']) > 60
                                                    ? substr($proyecto['titulo'], 0, 60) . '...'
                                                    : htmlspecialchars($proyecto['titulo']); ?>
                                            </td>

                                            <td><?= $proyecto['fecha_inicio'] ?></td>
                                            <td><?= $proyecto['fecha_fin'] ?></td>

                                            <!-- ESTADO PROYECTO -->
                                            <td>
                                                <span class="badge text-bg-<?= $proyectoControlador->EstiloEstado($proyecto['estado_proyecto']) ?>">
                                                    <?= htmlspecialchars($proyecto['estado_proyecto']) ?>
                                                </span>
                                            </td>

                                            <!-- ESTADO ESTUDIANTE: solo para rol estudiante -->
                                            <?php if ($rol === 'estudiante'): ?>
                                                <td>
                                                    <?php
                                                    $estadoEst = $proyecto['estado_estudiante'] ?? 'sin_asignar';
                                                    $clase = ($estadoEst == 'baja') ? 'danger'
                                                        : (($estadoEst == 'concluido') ? 'success' : 'primary');
                                                    ?>
                                                    <span class="badge text-bg-<?= $clase ?>">
                                                        <?= strtoupper($estadoEst) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <a href="../Seguimiento/index.php?id_proyectos=<?= $proyecto['id_proyectos'] ?>"
                                                        class="btn btn-sm btn-primary">
                                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16"
                                                            fill="currentColor" class="bi bi-folder2-open" viewBox="0 0 16 16">
                                                            <path d="M1 3.5A1.5 1.5 0 0 1 2.5 2h2.764c.958 0 1.76.56 2.311 1.184C7.985 3.648 8.48 4 9 4h4.5A1.5 1.5 0 0 1 15 5.5v.64c.57.265.94.876.856 1.546l-.64 5.124A2.5 2.5 0 0 1 12.733 15H3.266a2.5 2.5 0 0 1-2.481-2.19l-.64-5.124A1.5 1.5 0 0 1 1 6.14zM2 6h12v-.5a.5.5 0 0 0-.5-.5H9c-.964 0-1.71-.629-2.174-1.154C6.374 3.334 5.82 3 5.264 3H2.5a.5.5 0 0 0-.5.5zm-.367 1a.5.5 0 0 0-.496.562l.64 5.124A1.5 1.5 0 0 0 3.266 14h9.468a1.5 1.5 0 0 0 1.489-1.314l.64-5.124A.5.5 0 0 0 14.367 7z" />
                                                        </svg>
                                                    </a>
                                                </td>
                                            <?php endif; ?>

                                            <td><?= $proyecto['periodo'] ?></td>
                                            <td><?= $proyecto['total'] ?></td>

                                            <td>
                                                <?= $proyectoControlador->botonesAccion(
                                                    $proyecto['id_proyectos'],
                                                    $rol,
                                                    $proyecto['estado_proyecto'],
                                                    $id_usuario,
                                                    $proyecto['puede_cerrar'] ?? 0,
                                                    $proyecto['estado_estudiante'] ?? null,
                                                    $puedeCrear_Editar
                                                ); ?>
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
                        <div class="card mb-3 shadow-sm">
                            <div class="card-body">

                                <h5>ID: <?= $proyecto['id_proyectos'] ?></h5>
                                <p><strong>Título:</strong> <?= htmlspecialchars($proyecto['titulo']) ?></p>
                                <p><strong>Fecha inicio:</strong> <?= $proyecto['fecha_inicio'] ?></p>
                                <p><strong>Fecha final:</strong> <?= $proyecto['fecha_fin'] ?></p>
                                <p>
                                    <strong>Estado proyecto:</strong>
                                    <span class="badge text-bg-<?= $proyectoControlador->EstiloEstado($proyecto['estado_proyecto']) ?>">
                                        <?= htmlspecialchars($proyecto['estado_proyecto']) ?>
                                    </span>
                                </p>

                                <?php if ($rol === 'estudiante'): ?>
                                    <p>
                                        <strong>Estado estudiante:</strong>
                                        <?php
                                        $estadoEst = $proyecto['estado_estudiante'] ?? 'activo';
                                        $clase = ($estadoEst == 'baja') ? 'danger'
                                            : (($estadoEst == 'concluido') ? 'success' : 'primary');
                                        ?>
                                        <span class="badge text-bg-<?= $clase ?>">
                                            <?= strtoupper($estadoEst) ?>
                                        </span>
                                    </p>
                                <?php endif; ?>

                                <p><strong>Periodo:</strong> <?= $proyecto['periodo'] ?></p>
                                <p><strong>Pendientes:</strong> <?= $proyecto['total'] ?></p>

                                <div class="d-flex gap-2 flex-wrap">
                                    <?= $proyectoControlador->botonesAccion(
                                        $proyecto['id_proyectos'],
                                        $rol,
                                        $proyecto['estado_proyecto'],
                                        null,
                                        $proyecto['puede_cerrar'] ?? 0,
                                        $proyecto['estado_estudiante'] ?? null,
                                        $puedeCrear_Editar
                                    ); ?>
                                </div>

                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- PAGINACIÓN -->
                <?php if ($paginacion['total_paginas'] > 1):
                    $qBase = 'action=' . urlencode($action)
                        . (!empty($buscar) ? '&buscar=' . urlencode($buscar) : '');
                    $entidad = 'proyectos';
                    include __DIR__ . '../../../publico/incluido/_paginacion.php'; ?>

                <?php endif; ?>

            <?php else: ?>
                <div class="alert alert-info text-center">
                    No hay proyectos para mostrar
                </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<?php
$contenido = ob_get_clean();
$titulo    = "Proyectos";
$bodyClass = "proyectos-page";
include __DIR__ . '/../../layout.php';
?>