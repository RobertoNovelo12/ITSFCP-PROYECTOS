<?php
// tarea
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

error_reporting(E_ALL);

session_start();

if (!isset($_SESSION['id_usuario'])) {
    header("Location: /ITSFCP-PROYECTOS/index.php");
    exit;
}

$rol           = $_SESSION['rol'];
$id            = $_SESSION['id_usuario'];
$id_proyecto   = $_GET["id_proyectos"]  ?? null;
$id_asignacion = $_GET["id_asignacion"] ?? null;
$id_tarea      = $_GET["id_tarea"]      ?? null;

require_once '../../Controladores/tareasControlador.php';
$tareaControlador = new TareaControlador();

// 
//  MANEJO DEL POST
//  Se distinguen tres acciones:
//    1. guardar_borrador  → solo guarda contenido/archivo, estado = 8
//    2. editarTareaEstudiante → guarda contenido/archivo y cambia estado (Revisar)
//    3. editarTareaRevisar    → investigador cambia estado (Aprobar / Corregir)
// 
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['action'])) {
    $action        = $_POST['action'];
    $id_datos      = intval($_POST['id_tarea']      ?? 0);
    $id_asig       = intval($_POST['id_asignacion'] ?? 0);
    $idproy        = intval($_POST['id_proyectos']  ?? 0);
    $tipo          = $_POST['tipo']       ?? null;
    $comentario    = $_POST['comentarios'] ?? '';
    $contenido     = $_POST['contenido']   ?? '';

    // 1  Guardar borrador (estudiante)
    if ($action === 'guardar_borrador') {
        $tareaControlador->guardar_borrador(
            $id_datos,
            $idproy,
            $id_asig,
            intval($id),
            $contenido,
            $comentario
        );
        // guardar_borrador hace header() + exit() internamente
    }

    // 2  Enviar / cambiar estado (estudiante)
    if ($action === 'editarTareaEstudiante' && $tipo) {
        $tareaControlador->editar($_POST, $rol, $idproy, $id_asig, $id);
    }

    // 3  Revisar / Aprobar / Corregir (investigador)
    if ($action === 'editarTareaRevisar' && $tipo) {
        $tareaControlador->editar($_POST, $rol, $idproy, $id_asig, $id);
    }
}

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
    8 => ['texto' => 'Borrador',    'clase' => 'secondary'],
];

// Detectar si el estudiante tiene un borrador activo
$esBorrador = (($datos['id_estadoT'] ?? 0) == 8);

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

            <?php if ($esBorrador && $rol === 'estudiante'): ?>
                <span class="badge bg-warning text-dark rounded-pill px-3 py-2">
                    <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="currentColor" class="bi bi-pencil me-1" viewBox="0 0 16 16">
                        <path d="M12.146.146a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1 0 .708l-10 10a.5.5 0 0 1-.168.11l-5 2a.5.5 0 0 1-.65-.65l2-5a.5.5 0 0 1 .11-.168zM11.207 2.5 13.5 4.793 14.793 3.5 12.5 1.207zm1.586 3L10.5 3.207 4 9.707V10h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.5h.293zm-9.761 5.175-.106.106-1.528 3.821 3.821-1.528.106-.106A.5.5 0 0 1 5 12.5V12h-.5a.5.5 0 0 1-.5-.5V11h-.5a.5.5 0 0 1-.468-.325z"/>
                    </svg>Borrador sin enviar
                </span>
            <?php endif; ?>

            <?php if ($rol == "investigador" || $rol == "supervisor"): ?>
                <a href="lista_tareas.php?id_tarea=<?= $id_tarea ?>&id_proyectos=<?= $id_proyecto ?>"
                   class="btn btn-secondary btn-sm px-3">← Regresar</a>
            <?php elseif ($rol == "estudiante" || $rol == "alumno"): ?>
                <a href="tareas_estudiante.php?id_asignacion=<?= $id_asignacion ?>&id_tarea=<?= $id_tarea ?>&id_proyectos=<?= $id_proyecto ?>"
                   class="btn btn-secondary btn-sm px-3">
                    <i class="bi bi-arrow-left"></i> Regresar
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Alerta de tarea modificada por el investigador -->
    <?php if (!empty($datos['fecha_modificacion'])): ?>
        <div class="alert alert-warning d-flex align-items-center gap-2 py-2 mb-3" role="alert">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-pencil-fill flex-shrink-0" viewBox="0 0 16 16">
                <path d="M12.854.146a.5.5 0 0 0-.707 0L10.5 1.793 14.207 5.5l1.647-1.646a.5.5 0 0 0 0-.708zm.646 6.061L9.793 2.5 3.293 9H3.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.207zm-7.468 7.468A.5.5 0 0 1 6 13.5V13h-.5a.5.5 0 0 1-.5-.5V12h-.5a.5.5 0 0 1-.5-.5V11h-.5a.5.5 0 0 1-.5-.5V10h-.5a.5.5 0 0 1-.175-.032l-.179.178a.5.5 0 0 0-.11.168l-2 5a.5.5 0 0 0 .65.65l5-2a.5.5 0 0 0 .168-.11z"/>
            </svg>
            <span class="small">El investigador actualizó esta tarea el
                <strong><?= date('d/m/Y', strtotime($datos['fecha_modificacion'])) ?></strong>.
                Revisa las instrucciones.
            </span>
        </div>
    <?php endif; ?>

    <!-- Alerta de borrador (visible para el investigador cuando el estudiante tiene borrador) -->
    <?php if ($esBorrador && $rol === 'investigador'): ?>
        <div class="alert alert-info d-flex align-items-center gap-2 py-2 mb-3" role="alert">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-clock-history flex-shrink-0" viewBox="0 0 16 16">
                <path d="M8.515 1.019A7 7 0 0 0 8 1V0a8 8 0 0 1 .589.022zm2.004.45a7 7 0 0 0-.985-.299l.219-.976q.576.129 1.126.342zm1.37.71a7 7 0 0 0-.439-.27l.493-.87a8 8 0 0 1 .979.654l-.615.789a7 7 0 0 0-.418-.302zm1.834 1.79a7 7 0 0 0-.653-.796l.724-.69q.406.429.747.91zm.744 1.352a7 7 0 0 0-.214-.468l.893-.45a8 8 0 0 1 .45 1.088l-.95.313a7 7 0 0 0-.179-.483m.53 2.507a7 7 0 0 0-.1-1.025l.985-.17q.1.58.116 1.17zm-.131 1.538q.05-.254.081-.51l.99.128a8 8 0 0 1-.198 1.006l-.95-.313q.08-.252.077-.505M8 1a7 7 0 1 0 4.95 11.95l.707.707A8.001 8.001 0 1 1 8 0z"/>
                <path d="M7.5 3a.5.5 0 0 1 .5.5v5.21l3.248 1.856a.5.5 0 0 1-.496.868l-3.5-2A.5.5 0 0 1 7 9V3.5a.5.5 0 0 1 .5-.5"/>
            </svg>
            <span class="small">El estudiante tiene un <strong>borrador guardado</strong> que aún no ha sido enviado para revisión.</span>
        </div>
    <?php endif; ?>

    <!-- Información de la tarea -->
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header">
            <b>Información de la tarea</b>
        </div>
        <div class="card-body">
            <p class="tarea-seccion-label mb-1">Descripción</p>
            <p class="mb-3"><?= $datos['descripcion'] ?></p>
            <hr>
            <p class="tarea-seccion-label mb-1">Instrucciones</p>
            <p class="mb-0"><?= $datos['instrucciones'] ?></p>
        </div>
    </div>

    <!-- Archivo guía -->
    <?php if (!empty($datos['guia_nombre'])): ?>
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header"><b>Archivo guía</b></div>
            <div class="card-body py-2 d-flex align-items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="#dc3545" class="bi bi-file-earmark-pdf-fill flex-shrink-0" viewBox="0 0 16 16">
                    <path d="M5.523 12.424q.21-.124.459-.238a8 8 0 0 1-.45.606c-.28.337-.498.516-.635.572l-.035.012a.3.3 0 0 1-.026-.044c-.056-.11-.054-.216.04-.36.106-.165.319-.354.647-.548m2.455-1.647q-.178.037-.356.078a21 21 0 0 0 .5-1.05 12 12 0 0 0 .51.858q-.326.048-.654.114m2.525.939a4 4 0 0 1-.435-.41q.344.007.612.054c.317.057.466.147.518.209a.1.1 0 0 1 .026.064.44.44 0 0 1-.06.2.3.3 0 0 1-.094.124.1.1 0 0 1-.069.015c-.09-.003-.258-.066-.498-.256M8.278 6.97c-.04.244-.108.524-.2.829a5 5 0 0 1-.089-.346c-.076-.353-.087-.63-.046-.822.038-.177.11-.248.196-.283a.5.5 0 0 1 .145-.04c.013.03.028.092.032.198q.008.183-.038.465z"/>
                    <path fill-rule="evenodd" d="M4 0h5.293A1 1 0 0 1 10 .293L13.707 4a1 1 0 0 1 .293.707V14a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V2a2 2 0 0 1 2-2m5.5 1.5v2a1 1 0 0 0 1 1h2zM4.165 13.668c.09.18.23.343.438.419.207.075.412.04.58-.03.318-.13.635-.436.926-.786.333-.401.683-.927 1.021-1.51a11.7 11.7 0 0 1 1.997-.406c.3.383.61.713.91.95.28.22.603.403.934.417a.86.86 0 0 0 .51-.138c.155-.101.27-.247.354-.416.09-.181.145-.37.138-.563a.84.84 0 0 0-.2-.518c-.226-.27-.596-.4-.96-.465a5.8 5.8 0 0 0-1.335-.05 11 11 0 0 1-.98-1.686c.25-.66.437-1.284.52-1.794.036-.218.055-.426.048-.614a1.24 1.24 0 0 0-.127-.538.7.7 0 0 0-.477-.365c-.202-.043-.41 0-.601.077-.377.15-.576.47-.651.823-.073.34-.04.736.046 1.136.088.406.238.848.43 1.295a20 20 0 0 1-1.062 2.227 7.7 7.7 0 0 0-1.482.645c-.37.22-.699.48-.897.787-.21.326-.275.714-.08 1.103"/>
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
            <div class="card-header d-flex justify-content-between align-items-center">
                <b><?= $rol === 'estudiante' ? 'Tu entrega' : 'Revisión del investigador' ?></b>
                <?php if ($esBorrador && $rol === 'estudiante'): ?>
                    <span class="badge bg-warning text-dark small">
                        <svg xmlns="http://www.w3.org/2000/svg" width="11" height="11" fill="currentColor" class="bi bi-floppy me-1" viewBox="0 0 16 16">
                            <path d="M11 2H9v3h2z"/>
                            <path d="M1.5 0h11.586a1.5 1.5 0 0 1 1.06.44l1.415 1.414A1.5 1.5 0 0 1 16 2.914V14.5a1.5 1.5 0 0 1-1.5 1.5h-13A1.5 1.5 0 0 1 0 14.5v-13A1.5 1.5 0 0 1 1.5 0M1 1.5v13a.5.5 0 0 0 .5.5H2v-4.5A1.5 1.5 0 0 1 3.5 9h9a1.5 1.5 0 0 1 1.5 1.5V15h.5a.5.5 0 0 0 .5-.5V2.914a.5.5 0 0 0-.146-.353l-1.415-1.415A.5.5 0 0 0 13.086 1H13v4.5A1.5 1.5 0 0 1 11.5 7h-7A1.5 1.5 0 0 1 3 5.5V1H1.5a.5.5 0 0 0-.5.5m3 4a.5.5 0 0 0 .5.5h7a.5.5 0 0 0 .5-.5V1H4zm6 6.5v3h-4v-3h4a.5.5 0 0 1 0 0"/>
                        </svg>Borrador guardado — no enviado aún
                    </span>
                <?php endif; ?>
            </div>
            <div class="card-body">

                <?php
                //  FORM PRINCIPAL (enviar / revisar estado) 
                //    El botón "Guardar borrador" usa un segundo form (form-borrador)
                //    con action=guardar_borrador para no mezclar acciones.
                ?>

                <!-- Formulario de envío / revisión -->
                <form id="form-principal" action="" method="POST" enctype="multipart/form-data">
                    <?php if ($rol === 'estudiante'): ?>
                        <input type="hidden" name="action" value="editarTareaEstudiante">
                    <?php else: ?>
                        <input type="hidden" name="action" value="editarTareaRevisar">
                    <?php endif; ?>
                    <input type="hidden" name="id_tarea"      value="<?= $datos['id_tarea'] ?>">
                    <input type="hidden" name="id_proyectos"  value="<?= $datos['id_proyectos'] ?>">
                    <input type="hidden" name="id_asignacion" value="<?= $datos['id_asignacion'] ?>">
                    <input type="hidden" name="id_usuario"    value="<?= $id ?>">

                    <?= $tareaControlador->tareas($datos['tipo_tarea'], $rol, $datos) ?? "" ?>

                    <div class="d-flex flex-wrap gap-2 mt-3">
                        <?= $tareaControlador->botonesAccionTarea($datos['id_tarea'], $rol, $datos['estado'], $datos['id_asignacion']) ?>
                    </div>
                </form>

                <?php if ($rol === 'estudiante' && in_array($datos['id_estadoT'] ?? 0, [1, 8, 3])): ?>
                    <?php
                    // ── FORM BORRADOR (form separado para no mezclar con el submit principal) ──
                    // Comparte los mismos campos de datos pero action = guardar_borrador.
                    // Los valores del textarea/archivo los copia JS antes de enviar.
                    ?>
                    <form id="form-borrador" action="" method="POST" enctype="multipart/form-data" class="d-none">
                        <input type="hidden" name="action"        value="guardar_borrador">
                        <input type="hidden" name="id_tarea"      value="<?= $datos['id_tarea'] ?>">
                        <input type="hidden" name="id_proyectos"  value="<?= $datos['id_proyectos'] ?>">
                        <input type="hidden" name="id_asignacion" value="<?= $datos['id_asignacion'] ?>">
                        <input type="hidden" name="id_usuario"    value="<?= $id ?>">
                        <input type="hidden" name="contenido"     id="borrador-contenido"    value="">
                        <input type="hidden" name="comentarios"   id="borrador-comentarios"  value="">
                    </form>
                <?php endif; ?>

            </div>
        </div>
    <?php else: ?>
        <!-- Supervisor: solo lectura del contenido -->
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body">
                <p class="tarea-seccion-label mb-1">Contenido del estudiante</p>
                <div class="mb-0"><?= $datos['contenido'] ?? 'Sin contenido entregado.' ?></div>
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
                                            <span class="badge text-bg-<?= $estadoItem['clase'] ?> rounded-pill">
                                                <?= htmlspecialchars($estadoItem['texto']) ?>
                                            </span>
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
                                    <a class="page-link"
                                       href="?id_asignacion=<?= $id_asignacion ?>&pagina=<?= $i ?>&id_tarea=<?= $id_tarea ?>&id_proyectos=<?= $id_proyecto ?>">
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

<!-- TinyMCE -->
<script src="https://cdn.tiny.cloud/1/0ro7u4jwmnmqkovrjmi7cc1w5kk7tzragurlph7foryy7xbv/tinymce/6/tinymce.min.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function () {
    const esSoloLectura = <?= ($rol === 'investigador' || $rol === 'supervisor') ? 'true' : 'false' ?>;

    tinymce.init({
        selector: '.editor',
        readonly: esSoloLectura,
        height: 350,
        menubar: !esSoloLectura,
        plugins: 'lists link table code wordcount charmap insertdatetime',
        toolbar: esSoloLectura ? false : `
            undo redo |
            bold italic underline |
            alignleft aligncenter alignright |
            bullist numlist |
            link table |
            charmap |
            insertdatetime |
            code
        `,
        toolbar_mode: 'sliding',
        branding: false,
        statusbar: true,
    });

    // ── Botón Guardar borrador ────────────────────────────────────────────────
    // El botón usa form="form-borrador". Antes de que ese form se envíe,
    // copiamos el contenido del editor TinyMCE y los comentarios al form oculto.
    const formBorrador = document.getElementById('form-borrador');
    if (formBorrador) {
        formBorrador.addEventListener('submit', function (e) {
            // Volcar contenido de TinyMCE al campo oculto del form borrador
            const editorInstance = tinymce.get(document.querySelector('.editor')?.id);
            if (editorInstance) {
                document.getElementById('borrador-contenido').value = editorInstance.getContent();
            } else {
                // Fallback: campo textarea sin TinyMCE
                const textarea = document.querySelector('textarea[name="contenido"]');
                if (textarea) {
                    document.getElementById('borrador-contenido').value = textarea.value;
                }
            }
            // Copiar comentarios
            const comentariosField = document.querySelector('textarea[name="comentarios"]');
            if (comentariosField) {
                document.getElementById('borrador-comentarios').value = comentariosField.value;
            }
        });
    }
});
</script>

<?php
$contenido_layout = ob_get_clean();
$titulo           = "Revisar Tarea";
// Renombrar para no colisionar con $contenido de la tarea
$contenido = $contenido_layout;
include __DIR__ . '/../../layout.php';
?>