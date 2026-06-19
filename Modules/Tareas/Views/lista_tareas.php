<?php
// lista_tareas
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
$action = $_GET['action'] ?? 'index_Lista';

include __DIR__ . '/../../../public/incluido/_validar_tareas.php';

if (!in_array($rol, ['investigador', 'profesor', 'supervisor'], true)) {
    header('Location: /index.php');
    exit;
}

$id_tarea = $_GET['id_tarea'] ?? null;

//Validación de argumentos en url
$id_validar = $id_tarea;
include __DIR__ . '/../../../public/incluido/_validar_id.php';

$id_proyectos = $_GET['id_proyectos'] ?? null;

//Validación de argumentos en url
$id_validar = $id_proyectos;
include __DIR__ . '/../../../public/incluido/_validar_id.php';

require_once __DIR__ . '/../Controller/tareas_controller.php';
$tareaControlador = new TareaControlador();

if (!method_exists($tareaControlador, $action)) {
    die("Error: La acción '$action' no existe en el controlador.");
}

if ($_SERVER['REQUEST_METHOD'] == 'GET' && $action == 'actualizarestado') {
    $tareaControlador->actualizarestado($id_tarea, $rol, $_GET['tipo'], $id_proyectos);
}

$tarea = $tareaControlador->$action($id_tarea, $rol);
if (!is_array($tarea)) die("Error: La acción '$action' no devolvió un array válido.");

ob_start();

// Totales rápidos
$total     = count($tarea);
$aprobados = count(array_filter($tarea, fn($t) => $t['estados_tarea'] === 'Aprobado'));
$pendientes = count(array_filter($tarea, fn($t) => in_array($t['estados_tarea'], ['Pendiente', 'Corregir'])));
$revision  = count(array_filter($tarea, fn($t) => $t['estados_tarea'] === 'Revisar'));
?>

<div class="container-fluid py-4 ancho_container">

    <!-- Cabecera -->
    <div class="row mb-3 align-items-center">
        <?php
        $titulo      = 'Lista de Tareas';
        $descripcion = 'Estudiantes participantes y estado de sus tareas';
        include __DIR__ . '/../../../public/incluido/_encabezado.php';
        ?>
        <div class="col-md-6 text-md-end">
            <a href="/Modules/Tareas/Views/index.php?id_proyectos=<?= htmlspecialchars($id_proyectos) ?>" class="btn btn-secondary btn-sm px-4"><i class="bi bi-arrow-left"></i> Regresar</a>
        </div>
    </div>


    <!-- Resumen rápido -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm text-center py-3">
                <div class="fs-3 fw-bold text-primary"><?= $total ?></div>
                <div class="text-muted small">Total</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm text-center py-3">
                <div class="fs-3 fw-bold text-warning"><?= $revision ?></div>
                <div class="text-muted small">Para revisar</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm text-center py-3">
                <div class="fs-3 fw-bold text-danger"><?= $pendientes ?></div>
                <div class="text-muted small">Pendientes/Corregir</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm text-center py-3">
                <div class="fs-3 fw-bold text-success"><?= $aprobados ?></div>
                <div class="text-muted small">Aprobados</div>
            </div>
        </div>
    </div>

    <?php if (empty($tarea)): ?>
        <div class="alert alert-info text-center">No hay estudiantes asignados a esta actividad.</div>
    <?php else: ?>

        <!-- Tabla (desktop) -->
        <div class="card shadow-sm d-none d-md-block">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th class="px-3 py-3 text-muted small fw-semibold text-uppercase">#</th>
                                <th class="px-3 py-3 text-muted small fw-semibold text-uppercase">Estudiante</th>
                                <th class="px-3 py-3 text-muted small fw-semibold text-uppercase">Estado</th>
                                <th class="px-3 py-3 text-muted small fw-semibold text-uppercase">Enviado</th>
                                <th class="px-3 py-3 text-muted small fw-semibold text-uppercase">Revisado</th>
                                <th class="px-3 py-3 text-muted small fw-semibold text-uppercase">Aprobado</th>
                                <th class="px-3 py-3 text-muted small fw-semibold text-uppercase">Entrega</th>
                                <th class="px-3 py-3 text-muted small fw-semibold text-uppercase">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tarea as $tar): ?>
                                <tr>
                                    <td class="px-3 text-muted small"><?= $tar['id_asignacion'] ?></td>
                                    <td class="px-3 fw-semibold"><?= htmlspecialchars($tar['estudiante']) ?></td>
                                    <td class="px-3">
                                        <span class="badge rounded-pill text-bg-<?= $tareaControlador->EstiloEstadoLista($tar['estados_tarea']) ?>">
                                            <?= htmlspecialchars($tar['estados_tarea'] ?? '-') ?>
                                        </span>
                                    </td>
                                    <td class="px-3 small text-muted">
                                        <?= !empty($tar['fecha_revision']) ? date('d/m/Y', strtotime($tar['fecha_revision'])) : '—' ?>
                                    </td>
                                    <td class="px-3 small text-muted">
                                        <?= !empty($tar['fecha_correccion']) ? date('d/m/Y', strtotime($tar['fecha_correccion'])) : '—' ?>
                                    </td>
                                    <td class="px-3 small text-muted">
                                        <?= !empty($tar['fecha_aprobacion']) ? date('d/m/Y', strtotime($tar['fecha_aprobacion'])) : '—' ?>
                                    </td>
                                    <td class="px-3 small">
                                        <?php if (!empty($tar['archivo_nombre'])): ?>
                                            <a href="/Modules/Tareas/Views/descargar.php?id_asignacion=<?= $tar['id_asignacion'] ?>" class="text-primary small">
                                                <i class="bi bi-paperclip"></i>
                                                <?= htmlspecialchars($tar['archivo_nombre']) ?>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-3">
                                        <?= $tareaControlador->botonesAccionLista($tar['id_asignacion'], $rol, $tar['estados_tarea'], $tar['tipo'] ?? null, $id_proyectos, $tar['id_tarea']) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Tarjetas (móvil) -->
        <div class="d-md-none">
            <?php foreach ($tarea as $tar): ?>
                <div class="mcard">

                    <div class="mcard__head">
                        <div class="mcard__row-top">
                            <h3 class="mcard__title"><?= htmlspecialchars($tar['estudiante']) ?></h3>
                            <span class="mcard__badge badge rounded-pill text-bg-<?= $tareaControlador->EstiloEstadoLista($tar['estados_tarea']) ?>">
                                Estado: <?= htmlspecialchars($tar['estados_tarea'] ?? '-') ?>
                            </span>
                        </div>
                        <span class="mcard__id">#<?= $tar['id_asignacion'] ?></span>
                    </div>

                    <div class="mcard__section">
                        <div class="mcard__grid">
                            <div>
                                <span class="mcard__label">Enviado</span>
                                <span class="mcard__value"><?= !empty($tar['fecha_revision']) ? date('d/m/Y', strtotime($tar['fecha_revision'])) : '—' ?></span>
                            </div>
                            <div>
                                <span class="mcard__label">Revisado</span>
                                <span class="mcard__value"><?= !empty($tar['fecha_correccion']) ? date('d/m/Y', strtotime($tar['fecha_correccion'])) : '—' ?></span>
                            </div>
                            <div>
                                <span class="mcard__label">Aprobado</span>
                                <span class="mcard__value"><?= !empty($tar['fecha_aprobacion']) ? date('d/m/Y', strtotime($tar['fecha_aprobacion'])) : '—' ?></span>
                            </div>
                            <div>
                                <span class="mcard__label">Entrega</span>
                                <span class="mcard__value"><?= !empty($tar['archivo_nombre']) ? '<span class="text-primary">Con archivo</span>' : '—' ?></span>
                            </div>
                        </div>
                    </div>

                    <div class="mcard__actions">
                        <?= $tareaControlador->botonesAccionLista($tar['id_asignacion'], $rol, $tar['estados_tarea'], $tar['tipo'] ?? null, $id_proyectos, $tar['id_tarea']) ?>
                    </div>

                </div>
            <?php endforeach; ?>

            <?php if (empty($tarea)): ?>
                <div class="mcard-empty">No hay entregas registradas.</div>
            <?php endif; ?>
        </div>

    <?php endif; ?>
</div>

<?php
$contenido = ob_get_clean();
$titulo    = "Lista de Entregas";
$bodyClass = "proyectos-page";
include __DIR__ . '/../../../layout.php';
?>