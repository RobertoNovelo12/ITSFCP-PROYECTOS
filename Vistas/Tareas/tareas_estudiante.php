<?php
// tareas_estudiante
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

if (!isset($_SESSION['id_usuario'])) {
    header("Location: /ITSFCP-PROYECTOS/index.php");
    exit;
}

$rol        = $_SESSION['rol'];
$id_usuario = $_SESSION['id_usuario'];

require_once "../../Controladores/tareasControlador.php";
$tareaControlador = new TareaControlador();

$tareas = $tareaControlador->listarTareasEstudiante($id_usuario);

// Separar tareas activas de archivadas
$tareasActivas   = array_filter($tareas, fn($t) => !in_array($t['id_estadoT'], [4]));

$tareasInactivas = array_filter($tareas, fn($t) => $t['id_estadoT'] == 4);

ob_start();
?>

<div class="container-fluid py-4" style="max-width:720px;">

    <!-- Cabecera -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
        <h4 class="mb-0 fw-semibold">Mis Actividades</h4>
        <div class="col-6 col-md-6 text-md-end mb-2 mb-md-0 text-end">
        </div>
        <a href="../../Vistas/Proyectos/tabla.php" class="btn btn-secondary btn-sm px-3"><i class="bi bi-arrow-left"></i> Regresar
        </a>
    </div>

    <?php if (empty($tareasActivas) && empty($tareasInactivas)): ?>
        <div class="alert alert-info text-center">No tienes actividades asignadas por el momento.</div>
    <?php endif; ?>

    <!-- Tareas activas -->
    <?php if (!empty($tareasActivas)): ?>
        <?php foreach ($tareasActivas as $tarea): ?>

            <div class="task-card bg-white mb-3 p-3">
                <div class="d-flex justify-content-between align-items-start mb-1 gap-2">
                    <div>
                        <span class="task-tipo"><?= htmlspecialchars($tarea['tipo']) ?></span>
                        <?php if (!empty($tarea['fecha_modificacion'])): ?>
                            <span class="task-editada-badge ms-2">
                                <svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" fill="currentColor" class="bi bi-pencil-fill" viewBox="0 0 16 16">
                                    <path d="M12.854.146a.5.5 0 0 0-.707 0L10.5 1.793 14.207 5.5l1.647-1.646a.5.5 0 0 0 0-.708zm.646 6.061L9.793 2.5 3.293 9H3.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.207zm-7.468 7.468A.5.5 0 0 1 6 13.5V13h-.5a.5.5 0 0 1-.5-.5V12h-.5a.5.5 0 0 1-.5-.5V11h-.5a.5.5 0 0 1-.5-.5V10h-.5a.5.5 0 0 1-.175-.032l-.179.178a.5.5 0 0 0-.11.168l-2 5a.5.5 0 0 0 .65.65l5-2a.5.5 0 0 0 .168-.11z" />
                                </svg>
                                Actualizada <?= date('d/m/Y', strtotime($tarea['fecha_modificacion'])) ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    <span class="badge rounded-pill task-badge-estado text-bg-<?= $tareaControlador->estiloEstado($tarea['id_estadoT']) ?>">
                        <?= htmlspecialchars($tarea['estado_texto']) ?>
                    </span>
                </div>

                <!-- Instrucciones (preview) -->
                <?php if (!empty($tarea['instrucciones'])): ?>
                    <p class="task-instrucciones mt-2 mb-2"><?= htmlspecialchars($tarea['instrucciones']) ?></p>
                <?php endif; ?>

                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-2">
                    <div class="small text-muted">
                        <?php if (!empty($tarea['fecha_entrega'])): ?>
                            <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="currentColor" class="bi bi-calendar3 me-1" viewBox="0 0 16 16">
                                <path d="M14 0H2a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V2a2 2 0 0 0-2-2M1 3.857C1 3.384 1.448 3 2 3h12c.552 0 1 .384 1 .857v10.286c0 .473-.448.857-1 .857H2c-.552 0-1-.384-1-.857z" />
                                <path d="M6.5 7a1 1 0 1 0 0-2 1 1 0 0 0 0 2m3 0a1 1 0 1 0 0-2 1 1 0 0 0 0 2m3 0a1 1 0 1 0 0-2 1 1 0 0 0 0 2m-9 3a1 1 0 1 0 0-2 1 1 0 0 0 0 2m3 0a1 1 0 1 0 0-2 1 1 0 0 0 0 2m3 0a1 1 0 1 0 0-2 1 1 0 0 0 0 2m3 0a1 1 0 1 0 0-2 1 1 0 0 0 0 2m-9 3a1 1 0 1 0 0-2 1 1 0 0 0 0 2m3 0a1 1 0 1 0 0-2 1 1 0 0 0 0 2m3 0a1 1 0 1 0 0-2 1 1 0 0 0 0 2" />
                            </svg>
                            Entrega: <strong><?= date('d/m/Y', strtotime($tarea['fecha_entrega'])) ?></strong>
                        <?php else: ?>
                            <span>Sin fecha de entrega</span>
                        <?php endif; ?>
                    </div>
                    <a href="tarea.php?id_asignacion=<?= $tarea['id_asignacion'] ?>&id_tarea=<?= $tarea['id_tarea'] ?>&id_proyectos=<?= $tarea['id_proyectos'] ?>" class="btn btn-primary btn-sm px-3">
                        Ver / Entregar
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <!-- Tareas sin activar (colapsadas) -->
    <?php if (!empty($tareasInactivas)): ?>
        <details class="mt-2">
            <summary class="text-muted small mb-2" style="cursor:pointer;">
                Actividades aún no disponibles (<?= count($tareasInactivas) ?>)
            </summary>
            <?php foreach ($tareasInactivas as $tarea): ?>
                <div class="task-card bg-light mb-2 p-3 opacity-75">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="task-tipo"><?= htmlspecialchars($tarea['tipo']) ?></span>
                        <span class="badge text-bg-dark rounded-pill task-badge-estado">Sin activar</span>
                    </div>
                    <p class="small text-muted mt-1 mb-0">Esta actividad aún no ha sido habilitada por el investigador.</p>
                </div>
            <?php endforeach; ?>
        </details>
    <?php endif; ?>

</div>

<?php
$contenido = ob_get_clean();
$titulo    = "Mis Actividades";
$bodyClass = "proyectos-page";
include __DIR__ . '/../../layout.php';
?>