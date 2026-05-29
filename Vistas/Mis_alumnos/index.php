<?php

/**
 * mis_alumnos/index.php
 * Vista principal del módulo "Mis Alumnos" para el investigador.
 * Solo lectura — sin acciones de baja/reactivación.
 * Ruta sugerida: /ITSFCP-PROYECTOS/Vistas/mis_alumnos/index.php
 */

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

if (!isset($_SESSION['id_usuario'])) {
    header('Location: /ITSFCP-PROYECTOS/index.php');
    exit;
}

$rol        = strtolower($_SESSION['rol'] ?? '');
$id_usuario = (int)$_SESSION['id_usuario'];

if (!in_array($rol, ['investigador', 'profesor'], true)) {
    header('Location: /ITSFCP-PROYECTOS/Vistas/Principal/index.php');
    exit;
}

require_once __DIR__ .  '/../../Controladores/misAlumnosControlador.php';

$ctrl = new misalumnosControlador();
$data = $ctrl->index();
extract($data); // $filtros, $periodos, $proyectos, $carreras, $resumen, $alumnos, $paginacion

//  Mensajes ─
$msg   = $_GET['msg'] ?? '';
$_mapa = [
    'exito_operacion'    => ['tipo' => 'exito',  'titulo_msg' => 'Operación completada',  'mensaje' => 'La operación sobre el estudiante fue realizada correctamente.'],
    'error_operacion'    => ['tipo' => 'error',  'titulo_msg' => 'Error en la operación', 'mensaje' => 'No fue posible completar la operación sobre el estudiante.'],
    'accion_no_permitida' => ['tipo' => 'alerta', 'titulo_msg' => 'Acción no permitida',   'mensaje' => 'La acción solicitada no está disponible para tu rol.'],
];

$opEstado = [
    ''          => 'Todos los estados',
    'activo'    => 'Activo',
    'concluido' => 'Concluido',
    'baja'      => 'Baja',
];

$opEstadoProceso = [
    ''                    => 'Todos los procesos',
    'en_proceso'          => 'En proceso',
    'carta_subida'        => 'Carta subida',
    'en_correccion'       => 'En corrección',
    'liberado_supervisor' => 'Liberado por supervisor',
];

ob_start();
?>

<div class="container-fluid py-4 ancho_container">

    <!-- CABECERA + FILTRO DE PERIODO -->
    <div class="row mb-3 align-items-center">
        <?php
        $titulo      = 'Mis Alumnos';
        $descripcion = 'Estudiantes asignados a tus proyectos';
        include __DIR__ . '../../../publico/incluido/_encabezado.php';
        ?>
        <div class="col-md-6 text-md-end">
            <form class="d-inline-flex align-items-center gap-2" method="GET">
                <input type="hidden" name="id_proyecto" value="<?= $filtros['id_proyecto'] ?>">
                <input type="hidden" name="estado" value="<?= htmlspecialchars($filtros['estado']) ?>">
                <input type="hidden" name="estado_proceso" value="<?= htmlspecialchars($filtros['estado_proceso']) ?>">
                <input type="hidden" name="carrera" value="<?= $filtros['carrera'] ?>">
                <input type="hidden" name="buscar" value="<?= htmlspecialchars($filtros['buscar']) ?>">
                <label class="mb-0 text-nowrap fw-semibold">Periodo:</label>
                <select name="periodo" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="0" <?= $filtros['periodo'] === 0 ? 'selected' : '' ?>>Todos</option>
                    <?php foreach ($periodos as $per): ?>
                        <option value="<?= $per['id_periodos'] ?>"
                            <?= $filtros['periodo'] === (int)$per['id_periodos'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($per['periodo']) ?>
                            <?= $per['estado_periodo'] === 'Activo' ? ' (Activo)' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>
    </div>
    <!-- ALERTAS -->
    <?php
    if (isset($_mapa[$msg])) {
        extract($_mapa[$msg]);
        include __DIR__ . '../../../publico/incluido/_mensaje.php';
    }
    ?>

    <!-- TARJETAS RESUMEN -->
    <div class="row mb-4 g-3">
        <div class="col-6 col-md-3">
            <div class="card text-center border-primary shadow-sm h-100">
                <div class="card-body">
                    <div class="display-6 fw-bold text-primary"><?= (int)$resumen['alumnos_unicos'] ?></div>
                    <div class="text-muted small">Alumnos únicos</div>
                    <div class="text-muted small"><?= (int)$resumen['total_participaciones'] ?> participaciones</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card text-center border-success shadow-sm h-100">
                <div class="card-body">
                    <div class="display-6 fw-bold text-success"><?= (int)$resumen['activos'] ?></div>
                    <div class="text-muted small">Activos</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card text-center border-info shadow-sm h-100">
                <div class="card-body">
                    <div class="display-6 fw-bold text-info"><?= (int)$resumen['concluidos'] ?></div>
                    <div class="text-muted small">Concluidos</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card text-center border-danger shadow-sm h-100">
                <div class="card-body">
                    <div class="display-6 fw-bold text-danger"><?= (int)$resumen['bajas'] ?></div>
                    <div class="text-muted small">Bajas</div>
                </div>
            </div>
        </div>
    </div>

    <!-- FILTROS SECUNDARIOS -->
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body py-2">
            <form method="GET" class="row g-2 align-items-end">
                <input type="hidden" name="periodo" value="<?= $filtros['periodo'] ?>">

                <div class="col-12 col-md-3">
                    <label class="form-label mb-1 small fw-semibold">Proyecto</label>
                    <select name="id_proyecto" class="form-select form-select-sm">
                        <option value="0" <?= $filtros['id_proyecto'] === 0 ? 'selected' : '' ?>>Todos mis proyectos</option>
                        <?php foreach ($proyectos as $pr): ?>
                            <option value="<?= $pr['id_proyectos'] ?>"
                                <?= $filtros['id_proyecto'] === (int)$pr['id_proyectos'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars(mb_substr($pr['titulo'], 0, 55)) . (mb_strlen($pr['titulo']) > 55 ? '…' : '') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-6 col-md-2">
                    <label class="form-label mb-1 small fw-semibold">Estado</label>
                    <select name="estado" class="form-select form-select-sm">
                        <?php foreach ($opEstado as $val => $lbl): ?>
                            <option value="<?= $val ?>" <?= $filtros['estado'] === $val ? 'selected' : '' ?>>
                                <?= htmlspecialchars($lbl) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-6 col-md-2">
                    <label class="form-label mb-1 small fw-semibold">Proceso</label>
                    <select name="estado_proceso" class="form-select form-select-sm">
                        <?php foreach ($opEstadoProceso as $val => $lbl): ?>
                            <option value="<?= $val ?>" <?= $filtros['estado_proceso'] === $val ? 'selected' : '' ?>>
                                <?= htmlspecialchars($lbl) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-6 col-md-2">
                    <label class="form-label mb-1 small fw-semibold">Carrera</label>
                    <select name="carrera" class="form-select form-select-sm">
                        <option value="0" <?= $filtros['carrera'] === 0 ? 'selected' : '' ?>>Todas</option>
                        <?php foreach ($carreras as $car): ?>
                            <option value="<?= $car['id_carrera'] ?>"
                                <?= $filtros['carrera'] === (int)$car['id_carrera'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($car['nombre_carrera']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-6 col-md-2">
                    <label class="form-label mb-1 small fw-semibold">Buscar</label>
                    <input type="text" name="buscar" class="form-control form-control-sm"
                        placeholder="Nombre, matrícula..."
                        value="<?= htmlspecialchars($filtros['buscar']) ?>">
                </div>

                <div class="col-auto d-flex gap-1 align-items-end">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="bi bi-funnel-fill me-1"></i>Filtrar
                    </button>
                    <a href="?periodo=<?= $filtros['periodo'] ?>" class="btn btn-secondary btn-sm">
                        <i class="bi bi-arrow-counterclockwise me-1"></i>Limpiar
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- CONTENIDO -->
    <?php if (!empty($alumnos)): ?>

        <!-- TABLA ESCRITORIO -->
        <div class="card shadow-sm d-none d-md-block">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Estudiante</th>
                                <th>Proyecto</th>
                                <th class="text-center">Periodo</th>
                                <th class="text-center">Estado</th>
                                <th class="text-center">Proceso</th>
                                <th style="min-width:140px;">Avance tareas</th>
                                <th class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($alumnos as $a): ?>
                                <tr>
                                    <td>
                                        <div class="fw-semibold"><?= htmlspecialchars($a['nombre_completo']) ?></div>
                                        <div class="text-muted small">
                                            <?= htmlspecialchars($a['matricula']) ?>
                                            &middot;
                                            <?= htmlspecialchars(mb_substr($a['carrera'], 0, 30)) ?>
                                        </div>
                                    </td>
                                    <td style="max-width:200px;" title="<?= htmlspecialchars($a['titulo_proyecto']) ?>">
                                        <span class="small">
                                            <?= htmlspecialchars(mb_substr($a['titulo_proyecto'], 0, 55))
                                                . (mb_strlen($a['titulo_proyecto']) > 55 ? '…' : '') ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge text-dark border"><?= htmlspecialchars($a['periodo']) ?></span>
                                        <br>
                                        <span class="small text-<?= $a['estado_periodo'] === 'Activo' ? 'success' : 'secondary' ?>">
                                            <?= $a['estado_periodo'] ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <?= $ctrl->badgeEstadoParticipacion($a['estado_participacion']) ?>
                                        <?php if ($a['fecha_terminacion']): ?>
                                            <br><span class="small text-muted"><?= $a['fecha_terminacion'] ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?= $ctrl->badgeEstadoProceso($a['estado_proceso']) ?>
                                    </td>
                                    <td>
                                        <?= $ctrl->barraAvance((int)$a['tareas_aprobadas'], (int)$a['tareas_total']) ?>
                                    </td>
                                    <td class="text-center">
                                        <div class="d-flex gap-1 justify-content-center">
                                            <a href="../proyectos/detalles.php?id_proyectos=<?= $a['id_proyectos'] ?>"
                                                class="btn btn-sm btn-primary"
                                                data-bs-toggle="tooltip" data-bs-title="Ir al proyecto">
                                                <i class="bi bi-folder2-open"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- TARJETAS MÓVIL -->
        <div class="d-block d-md-none">
            <?php foreach ($alumnos as $a): ?>
                <div class="card shadow-sm mb-3 <?= $a['estado_participacion'] === 'baja' ? 'border-danger' : '' ?>">
                    <div class="card-body pb-2">
                        <h6 class="fw-bold mb-0"><?= htmlspecialchars($a['nombre_completo']) ?></h6>
                        <div class="text-muted small">
                            <?= htmlspecialchars($a['matricula']) ?>
                            &middot; <?= htmlspecialchars(mb_substr($a['carrera'], 0, 35)) ?>
                        </div>
                        <div class="mt-1 d-flex gap-1 flex-wrap">
                            <?= $ctrl->badgeEstadoParticipacion($a['estado_participacion']) ?>
                            <?= $ctrl->badgeEstadoProceso($a['estado_proceso']) ?>
                        </div>
                    </div>
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item small">
                            <strong>Proyecto:</strong><br>
                            <?= htmlspecialchars(mb_substr($a['titulo_proyecto'], 0, 65))
                                . (mb_strlen($a['titulo_proyecto']) > 65 ? '…' : '') ?>
                            <span class="badge text-dark border ms-1"><?= htmlspecialchars($a['periodo']) ?></span>
                        </li>
                        <li class="list-group-item text-center">
                            <strong class="small d-block mb-1">Avance de tareas</strong>
                            <?= $ctrl->barraAvance((int)$a['tareas_aprobadas'], (int)$a['tareas_total']) ?>
                        </li>
                        <?php if ($a['fecha_terminacion']): ?>
                            <li class="list-group-item small text-center text-muted">
                                Terminado: <?= htmlspecialchars($a['fecha_terminacion']) ?>
                            </li>
                        <?php endif; ?>
                    </ul>
                    <div class="card-body pt-2 d-flex gap-2 justify-content-center">
                        <a href="../proyectos/detalles.php?id_proyectos=<?= $a['id_proyectos'] ?>"
                            class="btn btn-primary btn-sm">
                            <i class="bi bi-folder2-open me-1"></i>Ir al proyecto
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- PAGINACIÓN -->
        <?php
        $qBase = http_build_query(array_filter([
            'periodo'        => $filtros['periodo']        ?: '',
            'id_proyecto'    => $filtros['id_proyecto']    ?: '',
            'carrera'        => $filtros['carrera']        ?: '',
            'estado'         => $filtros['estado']         ?: '',
            'estado_proceso' => $filtros['estado_proceso'] ?: '',
            'buscar'         => $filtros['buscar']         ?: '',
        ]));
        $entidad = 'participaciones';
        include __DIR__ . '../../../publico/incluido/_paginacion.php';
        ?>

    <?php else: ?>
        <div class="alert alert-info text-center mt-3">
            <i class="bi bi-inbox fs-4 d-block mb-1"></i>
            No se encontraron alumnos con los filtros seleccionados.
            <?php if ($filtros['buscar'] || $filtros['estado'] || $filtros['estado_proceso'] || $filtros['id_proyecto'] || $filtros['carrera']): ?>
                <br>
                <a href="?periodo=<?= $filtros['periodo'] ?>" class="btn btn-secondary btn-sm mt-2">
                    <i class="bi bi-arrow-counterclockwise me-1"></i>Limpiar filtros
                </a>
            <?php endif; ?>
        </div>
    <?php endif; ?>

</div>

<?php
$contenido = ob_get_clean();
$titulo    = 'Mis Alumnos';
$bodyClass = 'mis-alumnos-page';
include __DIR__ . '/../../layout.php';
?>