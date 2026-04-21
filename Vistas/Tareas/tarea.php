<?php
// tarea
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();

if (!isset($_SESSION['id_usuario'])) {
    header("Location: /ITSFCP-PROYECTOS/index.php");
    exit;
}

$rol           = $_SESSION['rol'];
$id            = $_SESSION['id_usuario'];
$id_proyecto   = $_GET["id_proyectos"] ?? null;
$id_asignacion = $_GET["id_asignacion"] ?? null;
$id_tarea      = $_GET["id_tarea"] ?? null;

if ($id_asignacion == null) die("ERROR: No se recibió id_asignacion");

require_once '../../Controladores/tareasControlador.php';
$tareaControlador = new TareaControlador();

$datos    = $tareaControlador->mostrarTarea($id_asignacion, $rol);
$resultado = $tareaControlador->info_linea_tiempo($id_asignacion);

$historialAgrupado = $resultado['datos'];
$paginacion        = $resultado['paginacion'];

// Etiquetas de estado
$estadoLabel = [
    1 => ['texto' => 'Pendiente',   'clase' => 'primary'],
    2 => ['texto' => 'Enviado',     'clase' => 'warning'],
    3 => ['texto' => 'Corregir',    'clase' => 'danger'],
    4 => ['texto' => 'Sin activar', 'clase' => 'dark'],
    5 => ['texto' => 'Aprobado',    'clase' => 'success'],
    6 => ['texto' => 'Vencido',     'clase' => 'secondary'],
    7 => ['texto' => 'Entregado',   'clase' => 'info'],
];

// Acción POST (enviada desde el formulario)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['action'])) {
    $action = $_POST['action'];
    $id_datos = intval($_POST['id_tarea'] ?? 0);
    $id_asig  = intval($_POST['id_asignacion'] ?? 0);
    $idproy   = intval($_POST['id_proyectos'] ?? 0);
    $tipo     = $_POST['tipo'] ?? null;
    $comentario = $_POST['comentarios'] ?? '';

    if ($action === 'editarTareaEstudiante' && $tipo) {
        $tareaControlador->editar($_POST, $rol, $idproy, $id_asig, $id);
    } elseif ($action === 'editarTareaRevisar' && $tipo) {
        $tareaControlador->editar($_POST, $rol, $idproy, $id_asig, $id);
    }
}

ob_start();
?>

<div class="container-fluid py-4" style="max-width:95%;">

    <!-- Cabecera -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <div>
            <h4 class="mb-0 fw-semibold"><?= htmlspecialchars($datos['tipo_tarea'] ?? 'Tarea') ?></h4>
            <small class="text-muted">ID asignación: <?= $id_asignacion ?></small>
        </div>
        <div class="d-flex align-items-center gap-2">
            <?php
            $est = $estadoLabel[$datos['id_estadoT'] ?? 1] ?? ['texto' => $datos['estado'], 'clase' => 'secondary'];
            ?>
            <span class="badge text-bg-<?= $est['clase'] ?> rounded-pill px-3 py-2"><?= $est['texto'] ?></span>
            <?php if ($rol == "investigador" || $rol == "supervisor") { ?>
                <a href="lista_tareas.php?id_tarea=<?= $id_tarea ?>&id_proyectos=<?= $id_proyecto ?>" class="btn btn-secondary btn-sm px-3">← Regresar</a>
            <?php } elseif ($rol == "estudiante" || $rol == "alumno") { ?>
                <a href="tareas_estudiante.php?id_asignacion=<?= $id_asignacion ?>&id_tarea=<?= $id_tarea ?>&id_proyectos=<?= $id_proyecto ?>" class="btn btn-secondary btn-sm px-3">
                    <i class="bi bi-arrow-left"></i> Regresar
                </a> <?php } ?>

        </div>
    </div>

    <!-- Aviso de tarea editada -->
    <?php if (!empty($datos['fecha_modificacion'])): ?>
        <div class="alert alert-warning d-flex align-items-center gap-2 py-2 mb-3" role="alert">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-pencil-fill flex-shrink-0" viewBox="0 0 16 16">
                <path d="M12.854.146a.5.5 0 0 0-.707 0L10.5 1.793 14.207 5.5l1.647-1.646a.5.5 0 0 0 0-.708zm.646 6.061L9.793 2.5 3.293 9H3.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.207zm-7.468 7.468A.5.5 0 0 1 6 13.5V13h-.5a.5.5 0 0 1-.5-.5V12h-.5a.5.5 0 0 1-.5-.5V11h-.5a.5.5 0 0 1-.5-.5V10h-.5a.5.5 0 0 1-.175-.032l-.179.178a.5.5 0 0 0-.11.168l-2 5a.5.5 0 0 0 .65.65l5-2a.5.5 0 0 0 .168-.11z" />
            </svg>
            <span class="small">El investigador actualizó esta tarea el <strong><?= date('d/m/Y', strtotime($datos['fecha_modificacion'])) ?></strong>. Revisa las instrucciones.</span>
        </div>
    <?php endif; ?>

    <!-- Instrucciones -->
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header">
            <b>Información de la tarea</b>
        </div>
        <div class="card-body">
            <p class="tarea-seccion-label mb-1">Descripción</p>
            <p class="mb-3"><?= nl2br(htmlspecialchars($datos['descripcion'] ?? '')) ?></p>
            <hr>
            <p class="tarea-seccion-label mb-1">Instrucciones</p>
            <p class="mb-0"><?= nl2br(htmlspecialchars($datos['instrucciones'] ?? '')) ?></p>
        </div>
    </div>

    <!-- Archivo guía -->
    <?php if (!empty($datos['guia_nombre'])): ?>
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header">
                <b>Archivo guía</b>
            </div>
            <div class="card-body py-2 d-flex align-items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="#dc3545" class="bi bi-file-earmark-pdf-fill flex-shrink-0" viewBox="0 0 16 16">
                    <path d="M5.523 12.424q.21-.124.459-.238a8 8 0 0 1-.45.606c-.28.337-.498.516-.635.572l-.035.012a.3.3 0 0 1-.026-.044c-.056-.11-.054-.216.04-.36.106-.165.319-.354.647-.548m2.455-1.647q-.178.037-.356.078a21 21 0 0 0 .5-1.05 12 12 0 0 0 .51.858q-.326.048-.654.114m2.525.939a4 4 0 0 1-.435-.41q.344.007.612.054c.317.057.466.147.518.209a.1.1 0 0 1 .026.064.44.44 0 0 1-.06.2.3.3 0 0 1-.094.124.1.1 0 0 1-.069.015c-.09-.003-.258-.066-.498-.256M8.278 6.97c-.04.244-.108.524-.2.829a5 5 0 0 1-.089-.346c-.076-.353-.087-.63-.046-.822.038-.177.11-.248.196-.283a.5.5 0 0 1 .145-.04c.013.03.028.092.032.198q.008.183-.038.465z" />
                    <path fill-rule="evenodd" d="M4 0h5.293A1 1 0 0 1 10 .293L13.707 4a1 1 0 0 1 .293.707V14a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V2a2 2 0 0 1 2-2m5.5 1.5v2a1 1 0 0 0 1 1h2zM4.165 13.668c.09.18.23.343.438.419.207.075.412.04.58-.03.318-.13.635-.436.926-.786.333-.401.683-.927 1.021-1.51a11.7 11.7 0 0 1 1.997-.406c.3.383.61.713.91.95.28.22.603.403.934.417a.86.86 0 0 0 .51-.138c.155-.101.27-.247.354-.416.09-.181.145-.37.138-.563a.84.84 0 0 0-.2-.518c-.226-.27-.596-.4-.96-.465a5.8 5.8 0 0 0-1.335-.05 11 11 0 0 1-.98-1.686c.25-.66.437-1.284.52-1.794.036-.218.055-.426.048-.614a1.24 1.24 0 0 0-.127-.538.7.7 0 0 0-.477-.365c-.202-.043-.41 0-.601.077-.377.15-.576.47-.651.823-.073.34-.04.736.046 1.136.088.406.238.848.43 1.295a20 20 0 0 1-1.062 2.227 7.7 7.7 0 0 0-1.482.645c-.37.22-.699.48-.897.787-.21.326-.275.714-.08 1.103" />
                </svg>
                <div class="flex-grow-1">
                    <span class="tarea-seccion-label d-block mb-0">Archivo de guía</span>
                    <a href="descargar_guia.php?id=<?= $datos['id_tarea'] ?>" class="small">
                        <?= htmlspecialchars($datos['guia_nombre']) ?>
                    </a>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Formulario (estudiante / investigador) -->
    <?php if ($rol !== 'supervisor'): ?>
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header">
                <b>Formulario</b>
            </div>
            <div class="card-body">
                <p class="tarea-seccion-label mb-3">
                    <?= $rol === 'estudiante' ? 'Tu entrega' : 'Revisión del investigador' ?>
                </p>
                <form action="tarea.php" method="POST" enctype="multipart/form-data">
                    <?php if ($rol === 'estudiante'): ?>
                        <input type="hidden" name="action" value="editarTareaEstudiante">
                    <?php else: ?>
                        <input type="hidden" name="action" value="editarTareaRevisar">
                    <?php endif; ?>
                    <input type="hidden" name="id_tarea" value="<?= $datos['id_tarea'] ?>">
                    <input type="hidden" name="id_proyectos" value="<?= $datos['id_proyectos'] ?>">
                    <input type="hidden" name="id_asignacion" value="<?= $datos['id_asignacion'] ?>">
                    <input type="hidden" name="id_usuario" value="<?= $id ?>">

                    <?= $tareaControlador->tareas($datos['tipo_tarea'], $rol, $datos) ?? "" ?>

                    <div class="d-flex flex-wrap gap-2 mt-3">
                        <?= $tareaControlador->botonesAccionTarea($datos['id_tarea'], $rol, $datos['estado'], $datos['id_asignacion']) ?>
                    </div>
                </form>
            </div>
        </div>
    <?php else: ?>
        <!-- Supervisor: solo lectura del contenido -->
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body">
                <p class="tarea-seccion-label mb-1">Contenido del estudiante</p>
                <p class="mb-0"><?= nl2br(htmlspecialchars($datos['contenido'] ?? 'Sin contenido entregado.')) ?></p>
            </div>
        </div>
    <?php endif; ?>

    <!-- Línea de tiempo (solo cambios de estado) -->
    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <p class="tarea-seccion-label mb-3">Historial de estados</p>

            <?php if (empty($historialAgrupado)): ?>
                <p class="text-muted small text-center py-3 mb-0">Sin historial de estados aún.</p>
            <?php else: ?>
                <div class="timeline-wrap">
                    <?php foreach ($historialAgrupado as $fecha => $items): ?>
                        <div class="mb-3">
                            <p class="text-muted small fw-semibold mb-2"><?= $fecha ?></p>
                            <?php foreach ($items as $item):
                                $estadoItem = $estadoLabel[$item['id_estadoT']] ?? ['texto' => $item['estado'], 'clase' => 'secondary'];
                            ?>
                                <div class="timeline-item">
                                    <div class="timeline-dot bg-<?= $estadoItem['clase'] ?>"></div>
                                    <div class="ms-2">
                                        <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                                            <span class="badge text-bg-<?= $estadoItem['clase'] ?> rounded-pill"><?= htmlspecialchars($estadoItem['texto']) ?></span>
                                            <small class="text-muted"><?= date("H:i", strtotime($item['fecha'])) ?></small>
                                            <small class="text-muted">
                                                — <?= $item['esEstudiante'] ? 'Estudiante' : 'Investigador' ?>
                                                <?= !empty($item['usuario']) ? '(' . htmlspecialchars($item['usuario']) . ')' : '' ?>
                                            </small>
                                        </div>
                                        <?php if (!empty($item['comentario'])): ?>
                                            <p class="small mb-0 text-muted"><?= htmlspecialchars($item['comentario']) ?></p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Paginación -->
                <?php if ($paginacion['total_paginas'] > 1): ?>
                    <nav class="mt-3">
                        <ul class="pagination pagination-sm justify-content-center mb-0">
                            <?php for ($i = 1; $i <= $paginacion['total_paginas']; $i++): ?>
                                <li class="page-item <?= ($i == $paginacion['pagina']) ? 'active' : '' ?>">
                                    <a class="page-link" href="?id_asignacion=<?= $id_asignacion ?>&pagina=<?= $i ?>&id_tarea=<?= $id_tarea ?>&id_proyectos=<?= $id_proyecto ?>">
                                        <?= $i ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                        </ul>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

</div>

<?php
$contenido = ob_get_clean();
$titulo    = "Revisar Tarea";
include __DIR__ . '/../../layout.php';
?>