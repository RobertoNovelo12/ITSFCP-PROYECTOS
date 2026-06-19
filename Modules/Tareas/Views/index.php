<?php
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
                <div class="mcard-empty">No hay actividades registradas.</div>
            <?php endif; ?>
            <?php foreach ($tarea as $tar): ?>
                <?php $estadoCard = $tar['estado_plantilla'] ?? $tar['estados_tarea'] ?? $tar['estado_entrega'] ?? ''; ?>
                <div class="mcard">

                    <div class="mcard__head">
                        <div class="mcard__row-top">
                            <h3 class="mcard__title"><?= htmlspecialchars($tar['tipo']) ?></h3>
                            <span class="mcard__badge badge rounded-pill text-bg-<?= $tareaControlador->EstiloEstadoLista($estadoCard) ?>">
                                Estado: <?= htmlspecialchars($estadoCard ?: '-') ?>
                            </span>
                        </div>
                        <?php if (!empty($tar['fecha_modificacion'])): ?>
                            <div class="mcard__subtitle text-warning">
                                Editada el <?= date('d/m/Y', strtotime($tar['fecha_modificacion'])) ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="mcard__section">
                        <div class="mcard__grid">
                            <?php if (in_array($rol, ['investigador', 'supervisor'])): ?>
                                <div>
                                    <span class="mcard__label">Aprobados</span>
                                    <span class="mcard__value">
                                        <i class="<?= $iconos['detalles']['exito_todos'] ?> text-success me-1"></i>
                                        <?= (int)$tar['total_aprobados'] ?>/<?= (int)$tar['total_asignados'] ?>
                                        <?php if ((int)$tar['total_requieren_revision'] > 0): ?>
                                            <span class="badge text-bg-warning ms-1" title="Por revisar">
                                                <i class="<?= $iconos['detalles']['espera'] ?>"></i>
                                                <?= (int)$tar['total_requieren_revision'] ?>
                                            </span>
                                        <?php endif; ?>
                                    </span>
                                </div>
                            <?php endif; ?>
                            <div>
                                <span class="mcard__label">Fecha entrega</span>
                                <span class="mcard__value"><?= $tar['fecha_entrega'] ?: "Sin fecha" ?></span>
                            </div>
                            <div>
                                <span class="mcard__label">Guía</span>
                                <span class="mcard__value">
                                    <?php if (!empty($tar['archivo_nombre'])): ?>
                                        <a href="/Modules/Tareas/Views/descargar_guia.php?id=<?= $tar['id_tarea'] ?>" class="text-danger">
                                            <i class="<?= $iconos['tabla']['guia_pdf'] ?>"></i> PDF
                                        </a>
                                    <?php else: ?>
                                        <span class="mcard__value--muted">—</span>
                                    <?php endif; ?>
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="mcard__actions">
                        <?= $tareaControlador->botonesAccionPrincipal($tar['id_tarea'], $rol, $estadoCard, $id_proyecto) ?>
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