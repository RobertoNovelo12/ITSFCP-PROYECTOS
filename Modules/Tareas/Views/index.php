<?php
// Vistas/Tareas/index.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

if (!isset($_SESSION['id_usuario'])) {
    header("Location: /index.php");
    exit;
}

$rol        = strtolower($_SESSION['rol'] ?? '');
$id_usuario = intval($_SESSION['id_usuario']);
$action     = $_GET['action'] ?? 'index_Principal';

include __DIR__ . '/../../../public/incluido/_validar_tareas.php';

if (!in_array($rol, ['investigador', 'profesor', 'supervisor'], true)) {
    header('Location: /index.php');
    exit;
}

$id_proyecto = $_GET['id_proyectos'] ?? null;

//Validación de argumentos en url
$id_validar = $id_proyecto;
include __DIR__ . '/../../../public/incluido/_validar_id.php';

require_once __DIR__ . "/../Controller/tareas_controller.php";
$tareaControlador = new TareaControlador();

if (!method_exists($tareaControlador, $action)) {
    die("Error: La acción '$action' no existe en el controlador.");
}

if ($_SERVER['REQUEST_METHOD'] == 'GET' && $action == 'actualizarestado') {
    $tareaControlador->actualizarestado($id_proyecto, $rol, $_GET['tipo'], $id_proyecto);
}

$tarea = $tareaControlador->$action($id_proyecto, $id_usuario, $rol);
if (!is_array($tarea)) {
    header("Location: /Modules/Proyectos/Views/index.php?msg=sin_argumentos_url");
    exit;
}
$encabezados = $tareaControlador->encabezadosPrincipal($rol);
include __DIR__ . '/../../../public/incluido/_iconos.php';
ob_start();
?>

<div class="container-fluid py-4 ancho_container">

    <!-- Cabecera -->
    <div class="row mb-4 align-items-center">

        <?php
        $titulo      = 'Tareas';
        $descripcion = 'Tareas activas y pendientes por activar';
        include __DIR__ . '/../../../public/incluido/_encabezado.php';
        ?>

        <div class="col-6 col-md-6 text-md-end">
            <a href="/Modules/Proyectos/Views/index.php" class="btn btn-secondary btn-sm px-4">
                <i class="<?= $iconos['tabla']['regresar'] ?>"></i> Regresar</a>
        </div>
    </div>

    <!-- Tabla (desktop) -->
    <?php if (!empty($tarea)) { ?>
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
                            <?php if ($rol == "estudiante"): ?>
                                <?php foreach ($tarea as $tar): ?>
                                    <tr>
                                        <td class="px-3 fw-semibold"><?= htmlspecialchars($tar['tipo']) ?></td>
                                        <td class="px-3">
                                            <span class="badge rounded-pill text-bg-<?= $tareaControlador->EstiloEstadoLista($tar['estados_tarea'] ?? $tar['estado_entrega'] ?? '') ?>">
                                                <?= htmlspecialchars($tar['estados_tarea'] ?? $tar['estado_entrega'] ?? '-') ?>
                                            </span>
                                        </td>
                                        <td class="px-3">
                                            <?php if (!empty($tar['archivo_nombre'])): ?>
                                                <a href="/Modules/Tareas/Views/descargar_guia.php?id=<?= $tar['id_tarea'] ?>" class="text-danger" title="Descargar guía">
                                                    <i class="<?= $iconos['tabla']['guia_pdf'] ?>"></i>
                                                </a>
                                            <?php else: ?>
                                                <span class="text-muted small">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-3 small text-muted"><?= $tar['fecha_entrega'] ?: "Sin fecha" ?></td>
                                        <td class="px-3">
                                            <?= $tareaControlador->botonesAccionPrincipal($tar['id_tarea'], $rol, $tar['estado_entrega'] ?? $tar['estados_tarea'] ?? '', $id_proyecto) ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>

                            <?php elseif (in_array($rol, ["investigador", "supervisor"])): ?>
                                <?php foreach ($tarea as $tar): ?>
                                    <tr>
                                        <!-- Actividad -->
                                        <td class="px-3">
                                            <span class="fw-semibold"><?= htmlspecialchars($tar['tipo']) ?></span>
                                            <?php if (!empty($tar['fecha_modificacion'])): ?>
                                                <br><small class="text-warning">
                                                    <i class="<?= $iconos['tabla']['editada'] ?>"></i>
                                                    Editada <?= date('d/m/Y', strtotime($tar['fecha_modificacion'])) ?>
                                                </small>
                                            <?php endif; ?>
                                        </td>

                                        <!-- Entregas -->
                                        <td class="px-3 text-center">
                                            <span class="fw-semibold"><?= (int)$tar['total_aprobados'] ?></span>
                                            <span class="text-muted">/<?= (int)$tar['total_asignados'] ?></span>
                                            <span class="text-muted small ms-1">aprobados</span>

                                            <?php if ((int)$tar['total_requieren_revision'] > 0): ?>
                                                <br>
                                                <span class="badge text-bg-warning mt-1" title="Entregas pendientes de revisión">
                                                    <i class="<?= $iconos['detalles']['espera'] ?>"></i>
                                                    <?= (int)$tar['total_requieren_revision'] ?> por revisar
                                                </span>
                                            <?php endif; ?>
                                        </td>

                                        <!-- Estado plantilla -->
                                        <td class="px-3">
                                            <span class="badge rounded-pill text-bg-<?= $tareaControlador->EstiloEstadoLista($tar['estado_plantilla']) ?>">
                                                <?= htmlspecialchars($tar['estado_plantilla'] ?? '-') ?>
                                            </span>
                                        </td>

                                        <!-- Guía -->
                                        <td class="px-3">
                                            <?php if (!empty($tar['archivo_nombre'])): ?>
                                                <a href="/Modules/Tareas/Views/descargar_guia.php?id=<?= $tar['id_tarea'] ?>" class="text-danger" title="Descargar guía">
                                                    <i class="<?= $iconos['tabla']['guia_pdf'] ?>"></i>
                                                </a>
                                            <?php else: ?>
                                                <span class="text-muted small">—</span>
                                            <?php endif; ?>
                                        </td>

                                        <!-- Fecha entrega -->
                                        <td class="px-3 small text-muted"><?= $tar['fecha_entrega'] ?: "Sin fecha" ?></td>

                                        <!-- Acciones -->
                                        <td class="px-3">
                                            <?= $tareaControlador->botonesAccionPrincipal($tar['id_tarea'], $rol, $tar['estado_plantilla'], $id_proyecto) ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>


        <!-- Tarjetas (móvil) -->
        <div class="d-md-none">
            <?php if (empty($tarea)): ?>
                <div class="alert alert-info text-center mt-3">No hay actividades registradas.</div>
            <?php endif; ?>
            <?php foreach ($tarea as $tar): ?>
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <h6 class="fw-semibold mb-0"><?= htmlspecialchars($tar['tipo']) ?></h6>
                                <?php if (!empty($tar['fecha_modificacion'])): ?>
                                    <small class="text-warning">Editada <?= date('d/m/Y', strtotime($tar['fecha_modificacion'])) ?></small>
                                <?php endif; ?>
                            </div>
                            <?php
                            $estadoCard = $tar['estado_plantilla'] ?? $tar['estados_tarea'] ?? $tar['estado_entrega'] ?? '';
                            ?>
                            <span class="badge rounded-pill text-bg-<?= $tareaControlador->EstiloEstadoLista($estadoCard) ?>">
                                <?= htmlspecialchars($estadoCard ?: '-') ?>
                            </span>
                        </div>

                        <div class="row g-2 small text-muted mb-2">
                            <?php if (in_array($rol, ['investigador', 'supervisor'])): ?>
                                <div class="col-6">
                                    <span class="d-block text-uppercase fw-semibold" style="font-size:.7rem">Aprobados</span>
                                    <i class="<?= $iconos['detalles']['exito_todos'] ?> text-success me-1"></i>
                                    <?= (int)$tar['total_aprobados'] ?>/<?= (int)$tar['total_asignados'] ?>
                                    <?php if ((int)$tar['total_requieren_revision'] > 0): ?>
                                        <span class="badge text-bg-warning ms-1" title="Por revisar">
                                            <i class="<?= $iconos['detalles']['espera'] ?>"></i>
                                            <?= (int)$tar['total_requieren_revision'] ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            <div class="col-6">
                                <span class="d-block text-uppercase fw-semibold" style="font-size:.7rem">Fecha entrega</span>
                                <?= $tar['fecha_entrega'] ?: "Sin fecha" ?>
                            </div>
                            <div class="col-6">
                                <span class="d-block text-uppercase fw-semibold" style="font-size:.7rem">Guía</span>
                                <?php if (!empty($tar['archivo_nombre'])): ?>
                                    <a href="/Modules/Tareas/Views/descargar_guia.php?id=<?= $tar['id_tarea'] ?>" class="text-danger">
                                        <i class="<?= $iconos['tabla']['guia_pdf'] ?>"></i> PDF
                                    </a>
                                <?php else: ?>
                                    <span>—</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="d-flex gap-2">
                            <?= $tareaControlador->botonesAccionPrincipal($tar['id_tarea'], $rol, $estadoCard, $id_proyecto) ?>
                        </div>

                    </div>
                </div>

            <?php endforeach; ?>
        </div>
    <?php } else { ?>
        <div class="alert alert-info text-center">
            No hay tareas para mostrar
        </div>
    <?php } ?>
</div>

<?php
$contenido = ob_get_clean();
$titulo    = "Seguimiento de Actividades";
$bodyClass = "proyectos-page";
include __DIR__ . '/../../../layout.php';
?>