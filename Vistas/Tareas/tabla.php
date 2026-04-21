<?php
// tabla
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
$action     = $_GET['action'] ?? 'index_Principal';
$id_proyecto = $_GET['id_proyectos'] ?? null;

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
    $tareaControlador->actualizarestado($id_proyecto, $rol, $_GET['tipo'], $id_proyecto);
}

$tarea      = $tareaControlador->$action($id_proyecto, $id_usuario, $rol);
if (!is_array($tarea)) die("Error: La acción '$action' no devolvió un array válido.");

$encabezados = $tareaControlador->encabezadosPrincipal($rol);

ob_start();
include __DIR__ . '/../../mensaje.php';
?>

<div class="container-fluid py-4">

    <!-- Cabecera -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
        <h3 class="mb-0 fw-semibold">Seguimiento de Actividades</h3>
        <a href="../Proyectos/tabla.php" class="btn btn-outline-danger btn-sm px-4">← Regresar</a>
    </div>

    <!-- Tabla (desktop) -->
    <div class="card border-0 shadow-sm d-none d-md-block">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle" id="tabla_informacion">
                    <thead class="table-light">
                        <tr>
                            <?php foreach ($encabezados as $enc): ?>
                                <th class="px-3 py-3 text-muted small fw-semibold text-uppercase"><?= $enc ?></th>
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
                                            <a href="descargar_guia.php?id=<?= $tar['id_tarea'] ?>" class="text-danger" title="Descargar guía">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-file-earmark-pdf-fill" viewBox="0 0 16 16">
                                                    <path d="M5.523 12.424q.21-.124.459-.238a8 8 0 0 1-.45.606c-.28.337-.498.516-.635.572l-.035.012a.3.3 0 0 1-.026-.044c-.056-.11-.054-.216.04-.36.106-.165.319-.354.647-.548m2.455-1.647q-.178.037-.356.078a21 21 0 0 0 .5-1.05 12 12 0 0 0 .51.858q-.326.048-.654.114m2.525.939a4 4 0 0 1-.435-.41q.344.007.612.054c.317.057.466.147.518.209a.1.1 0 0 1 .026.064.44.44 0 0 1-.06.2.3.3 0 0 1-.094.124.1.1 0 0 1-.069.015c-.09-.003-.258-.066-.498-.256M8.278 6.97c-.04.244-.108.524-.2.829a5 5 0 0 1-.089-.346c-.076-.353-.087-.63-.046-.822.038-.177.11-.248.196-.283a.5.5 0 0 1 .145-.04c.013.03.028.092.032.198q.008.183-.038.465z"/>
                                                    <path fill-rule="evenodd" d="M4 0h5.293A1 1 0 0 1 10 .293L13.707 4a1 1 0 0 1 .293.707V14a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V2a2 2 0 0 1 2-2m5.5 1.5v2a1 1 0 0 0 1 1h2zM4.165 13.668c.09.18.23.343.438.419.207.075.412.04.58-.03.318-.13.635-.436.926-.786.333-.401.683-.927 1.021-1.51a11.7 11.7 0 0 1 1.997-.406c.3.383.61.713.91.95.28.22.603.403.934.417a.86.86 0 0 0 .51-.138c.155-.101.27-.247.354-.416.09-.181.145-.37.138-.563a.84.84 0 0 0-.2-.518c-.226-.27-.596-.4-.96-.465a5.8 5.8 0 0 0-1.335-.05 11 11 0 0 1-.98-1.686c.25-.66.437-1.284.52-1.794.036-.218.055-.426.048-.614a1.24 1.24 0 0 0-.127-.538.7.7 0 0 0-.477-.365c-.202-.043-.41 0-.601.077-.377.15-.576.47-.651.823-.073.34-.04.736.046 1.136.088.406.238.848.43 1.295a20 20 0 0 1-1.062 2.227 7.7 7.7 0 0 0-1.482.645c-.37.22-.699.48-.897.787-.21.326-.275.714-.08 1.103"/>
                                                </svg>
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
                                    <td class="px-3">
                                        <span class="fw-semibold"><?= htmlspecialchars($tar['tipo']) ?></span>
                                        <?php if (!empty($tar['fecha_modificacion'])): ?>
                                            <br><small class="text-warning">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="11" height="11" fill="currentColor" class="bi bi-pencil-fill" viewBox="0 0 16 16">
                                                    <path d="M12.854.146a.5.5 0 0 0-.707 0L10.5 1.793 14.207 5.5l1.647-1.646a.5.5 0 0 0 0-.708zm.646 6.061L9.793 2.5 3.293 9H3.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.207zm-7.468 7.468A.5.5 0 0 1 6 13.5V13h-.5a.5.5 0 0 1-.5-.5V12h-.5a.5.5 0 0 1-.5-.5V11h-.5a.5.5 0 0 1-.5-.5V10h-.5a.5.5 0 0 1-.175-.032l-.179.178a.5.5 0 0 0-.11.168l-2 5a.5.5 0 0 0 .65.65l5-2a.5.5 0 0 0 .168-.11z"/>
                                                </svg>
                                                Editada <?= date('d/m/Y', strtotime($tar['fecha_modificacion'])) ?>
                                            </small>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-3 text-center">
                                        <span class="fw-semibold"><?= $tar['total_entregados'] ?></span>
                                        <span class="text-muted">/<?= $tar['total_asignados'] ?></span>
                                    </td>
                                    <td class="px-3">
                                        <span class="badge rounded-pill text-bg-<?= $tareaControlador->EstiloEstadoLista($tar['estado_plantilla']) ?>">
                                            <?= htmlspecialchars($tar['estado_plantilla'] ?? '-') ?>
                                        </span>
                                    </td>
                                    <td class="px-3">
                                        <?php if (!empty($tar['archivo_nombre'])): ?>
                                            <a href="descargar_guia.php?id=<?= $tar['id_tarea'] ?>" class="text-danger" title="Descargar guía">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-file-earmark-pdf-fill" viewBox="0 0 16 16">
                                                    <path d="M5.523 12.424q.21-.124.459-.238a8 8 0 0 1-.45.606c-.28.337-.498.516-.635.572l-.035.012a.3.3 0 0 1-.026-.044c-.056-.11-.054-.216.04-.36.106-.165.319-.354.647-.548m2.455-1.647q-.178.037-.356.078a21 21 0 0 0 .5-1.05 12 12 0 0 0 .51.858q-.326.048-.654.114m2.525.939a4 4 0 0 1-.435-.41q.344.007.612.054c.317.057.466.147.518.209a.1.1 0 0 1 .026.064.44.44 0 0 1-.06.2.3.3 0 0 1-.094.124.1.1 0 0 1-.069.015c-.09-.003-.258-.066-.498-.256M8.278 6.97c-.04.244-.108.524-.2.829a5 5 0 0 1-.089-.346c-.076-.353-.087-.63-.046-.822.038-.177.11-.248.196-.283a.5.5 0 0 1 .145-.04c.013.03.028.092.032.198q.008.183-.038.465z"/>
                                                    <path fill-rule="evenodd" d="M4 0h5.293A1 1 0 0 1 10 .293L13.707 4a1 1 0 0 1 .293.707V14a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V2a2 2 0 0 1 2-2m5.5 1.5v2a1 1 0 0 0 1 1h2zM4.165 13.668c.09.18.23.343.438.419.207.075.412.04.58-.03.318-.13.635-.436.926-.786.333-.401.683-.927 1.021-1.51a11.7 11.7 0 0 1 1.997-.406c.3.383.61.713.91.95.28.22.603.403.934.417a.86.86 0 0 0 .51-.138c.155-.101.27-.247.354-.416.09-.181.145-.37.138-.563a.84.84 0 0 0-.2-.518c-.226-.27-.596-.4-.96-.465a5.8 5.8 0 0 0-1.335-.05 11 11 0 0 1-.98-1.686c.25-.66.437-1.284.52-1.794.036-.218.055-.426.048-.614a1.24 1.24 0 0 0-.127-.538.7.7 0 0 0-.477-.365c-.202-.043-.41 0-.601.077-.377.15-.576.47-.651.823-.073.34-.04.736.046 1.136.088.406.238.848.43 1.295a20 20 0 0 1-1.062 2.227 7.7 7.7 0 0 0-1.482.645c-.37.22-.699.48-.897.787-.21.326-.275.714-.08 1.103"/>
                                                </svg>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted small">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-3 small text-muted"><?= $tar['fecha_entrega'] ?: "Sin fecha" ?></td>
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
                                <span class="d-block text-uppercase fw-semibold" style="font-size:.7rem">Entregas</span>
                                <?= ($tar['total_entregados'] ?? 0) ?>/<?= ($tar['total_asignados'] ?? 0) ?>
                            </div>
                        <?php endif; ?>
                        <div class="col-6">
                            <span class="d-block text-uppercase fw-semibold" style="font-size:.7rem">Fecha entrega</span>
                            <?= $tar['fecha_entrega'] ?: "Sin fecha" ?>
                        </div>
                        <div class="col-6">
                            <span class="d-block text-uppercase fw-semibold" style="font-size:.7rem">Guía</span>
                            <?php if (!empty($tar['archivo_nombre'])): ?>
                                <a href="descargar_guia.php?id=<?= $tar['id_tarea'] ?>" class="text-danger">PDF</a>
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

</div>

<?php
$contenido = ob_get_clean();
$titulo    = "Seguimiento de Actividades";
$bodyClass = "proyectos-page";
include __DIR__ . '/../../layout.php';
?>
