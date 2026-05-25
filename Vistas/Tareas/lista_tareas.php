<?php
// lista_tareas
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
$action     = $_GET['action'] ?? 'index_Lista';
$id_tarea   = $_GET['id_tarea'] ?? null;
$id_proyectos = $_GET['id_proyectos'] ?? null;

if (!in_array($rol, ['investigador', 'profesor', 'supervisor'], true)) {
    header('Location: /ITSFCP-PROYECTOS/index.php');
    exit;
}

include "../../Controladores/tareasControlador.php";
$tareaControlador = new TareaControlador();

if (!method_exists($tareaControlador, $action)) {
    die("Error: La acción '$action' no existe en el controlador.");
}

if ($_SERVER['REQUEST_METHOD'] == 'GET' && $action == 'actualizarestado') {
    $tareaControlador->actualizarestado($id_tarea, $rol, $_GET['tipo'], $id_proyectos, null, null, null);
}

$tarea      = $tareaControlador->$action($id_tarea, $rol);
if (!is_array($tarea)) die("Error: La acción '$action' no devolvió un array válido.");

ob_start();
include __DIR__ . '/../../mensaje.php';

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
        include __DIR__ . '../../../publico/incluido/_encabezado.php';
        ?>
        <div class="col-md-6 text-md-end">
            <h3 class="mb-0 fw-semibold">Entregas de Estudiantes</h3>
            <a href="index.php?id_proyectos=<?= htmlspecialchars($id_proyectos) ?>" class="btn btn-secondary btn-sm px-4"><i class="bi bi-arrow-left"></i> Regresar</a>
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
                        <thead class="table-light">
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
                                            <a href="descargar.php?id=<?= $tar['id_asignacion'] ?>" class="text-primary small">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" class="bi bi-paperclip" viewBox="0 0 16 16">
                                                    <path d="M4.5 3a2.5 2.5 0 0 1 5 0v9a1.5 1.5 0 0 1-3 0V5a.5.5 0 0 1 1 0v7a.5.5 0 0 0 1 0V3a1.5 1.5 0 1 0-3 0v9a2.5 2.5 0 0 0 5 0V5a.5.5 0 0 1 1 0v7a3.5 3.5 0 1 1-7 0z" />
                                                </svg>
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
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <h6 class="fw-semibold mb-0"><?= htmlspecialchars($tar['estudiante']) ?></h6>
                                <small class="text-muted">#<?= $tar['id_asignacion'] ?></small>
                            </div>
                            <span class="badge rounded-pill text-bg-<?= $tareaControlador->EstiloEstadoLista($tar['estados_tarea']) ?>">
                                <?= htmlspecialchars($tar['estados_tarea'] ?? '-') ?>
                            </span>
                        </div>
                        <div class="row g-1 small text-muted mb-2">
                            <div class="col-6">
                                <span class="d-block fw-semibold" style="font-size:.7rem">ENVIADO</span>
                                <?= !empty($tar['fecha_revision']) ? date('d/m/Y', strtotime($tar['fecha_revision'])) : '—' ?>
                            </div>
                            <div class="col-6">
                                <span class="d-block fw-semibold" style="font-size:.7rem">REVISADO</span>
                                <?= !empty($tar['fecha_correccion']) ? date('d/m/Y', strtotime($tar['fecha_correccion'])) : '—' ?>
                            </div>
                            <div class="col-6">
                                <span class="d-block fw-semibold" style="font-size:.7rem">APROBADO</span>
                                <?= !empty($tar['fecha_aprobacion']) ? date('d/m/Y', strtotime($tar['fecha_aprobacion'])) : '—' ?>
                            </div>
                            <div class="col-6">
                                <span class="d-block fw-semibold" style="font-size:.7rem">ENTREGA</span>
                                <?= !empty($tar['archivo_nombre']) ? '<span class="text-primary">Con archivo</span>' : '—' ?>
                            </div>
                        </div>
                        <div class="d-flex gap-2">
                            <?= $tareaControlador->botonesAccionLista($tar['id_asignacion'], $rol, $tar['estados_tarea'], $tar['tipo'] ?? null, $id_proyectos, $tar['id_tarea']) ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

    <?php endif; ?>
</div>

<?php
$contenido = ob_get_clean();
$titulo    = "Lista de Entregas";
$bodyClass = "proyectos-page";
include __DIR__ . '/../../layout.php';
?>