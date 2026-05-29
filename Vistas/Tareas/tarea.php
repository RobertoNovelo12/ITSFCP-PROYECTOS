<?php
//
//  tarea.php
//

session_start();

if (!isset($_SESSION['id_usuario'])) {
    header("Location: /ITSFCP-PROYECTOS/index.php");
    exit;
}

$rol = strtolower($_SESSION['rol'] ?? '');
$id  = (int)$_SESSION['id_usuario'];

// include __DIR__ .  ' Validar GET no vacío (reutilizable _validar_tareas.php) include __DIR__ .  '
include __DIR__ .  '../../../publico/incluido/_validar_tareas.php';

// Solo investigador y estudiante pueden acceder
if (!in_array($rol, ['investigador', 'estudiante', 'supervisor'], true)) {
    header("Location: /ITSFCP-PROYECTOS/Vistas/Principal/index.php");
    exit;
}

// include __DIR__ .  ' Validar IDs de URL include __DIR__ .  '─
$id_proyecto   = (int)($_GET['id_proyectos']  ?? 0);
$id_validar    = $id_proyecto;
include __DIR__ .  '../../../publico/incluido/_validar_id.php';

$id_asignacion = (int)($_GET['id_asignacion'] ?? 0);
$id_validar    = $id_asignacion;
include __DIR__ .  '../../../publico/incluido/_validar_id.php';

$id_tarea      = (int)($_GET['id_tarea']      ?? 0);
$id_validar    = $id_tarea;
include __DIR__ .  '../../../publico/incluido/_validar_id.php';

require_once __DIR__ .  '/../../Controladores/tareasControlador.php';
$tareaControlador = new TareaControlador();

// Límites de caracteres para el contenido del estudiante
const MAX_CONTENIDO   = 8000;
const MAX_COMENTARIOS = 1500;

// include __DIR__ .  '
//  MANEJO DEL POST
//  Acciones:
//    1. guardar_borrador      → guarda contenido/archivo, estado = 8
//    2. editarTareaEstudiante → guarda contenido/archivo y cambia estado (Revisar)
//    3. editarTareaRevisar    → investigador cambia estado (Aprobar / Corregir)
// include __DIR__ .  '
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['action'])) {

    $action     = $_POST['action'];
    $id_datos   = (int)($_POST['id_tarea']      ?? 0);
    $id_asig    = (int)($_POST['id_asignacion'] ?? 0);
    $idproy     = (int)($_POST['id_proyectos']  ?? 0);
    $tipo       = trim($_POST['tipo']           ?? '');
    $comentario = $_POST['comentarios']         ?? '';
    $contenido  = $_POST['contenido']           ?? '';

    // Validación de límites en servidor
    $lenContenido   = mb_strlen(strip_tags($contenido),  'UTF-8');
    $lenComentarios = mb_strlen(strip_tags($comentario), 'UTF-8');

    if (in_array($action, ['guardar_borrador', 'editarTareaEstudiante'], true)) {
        if ($lenContenido > MAX_CONTENIDO || $lenComentarios > MAX_COMENTARIOS) {
            $excedido = $lenContenido > MAX_CONTENIDO ? 'limite_contenido' : 'limite_comentarios';
            header("Location: tarea.php?id_tarea={$id_datos}&id_proyectos={$idproy}&id_asignacion={$id_asig}&msg={$excedido}");
            exit;
        }
    }

    // 1 — Guardar borrador (estudiante)
    if ($action === 'guardar_borrador') {
        $tareaControlador->guardar_borrador(
            $id_datos,
            $idproy,
            $id_asig,
            $id,
            $contenido,
            $comentario
        );
        // guardar_borrador() redirige internamente
    }

    // 2 — Enviar tarea / cambiar estado (estudiante)
    if ($action === 'editarTareaEstudiante') {
        if (empty($tipo)) {
            header("Location: tarea.php?id_tarea={$id_datos}&id_proyectos={$idproy}&id_asignacion={$id_asig}&msg=error_tipo_invalido");
            exit;
        }
        $tareaControlador->editar($_POST, $rol, $idproy, $id_asig, $id);
        // editar() redirige internamente
    }

    // 3 — Revisar / Aprobar / Corregir (investigador / profesor)
    if ($action === 'editarTareaRevisar') {
        if (empty($tipo)) {
            header("Location: tarea.php?id_tarea={$id_datos}&id_proyectos={$idproy}&id_asignacion={$id_asig}&msg=error_tipo_invalido");
            exit;
        }
        $tareaControlador->editar($_POST, $rol, $idproy, $id_asig, $id);
        // editar() redirige internamente
    }
}

// include __DIR__ .  ' Datos para la vista include __DIR__ .  '
$datos = $tareaControlador->mostrarTarea($id_asignacion, $rol, $id, $id_proyecto);

// Validar que se encontró el registro (reutilizable _validar_datos.php)
$registro = $datos;
include __DIR__ .  '../../../publico/incluido/_validar_datos.php';

$resultado         = $tareaControlador->info_linea_tiempo($id_asignacion);
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

// ¿El estudiante tiene borrador activo?
$esBorrador = (($datos['id_estadoT'] ?? 0) == 8);

// Longitudes actuales (texto plano, sin etiquetas HTML)
$lenContenidoActual   = mb_strlen(strip_tags($datos['contenido']   ?? ''), 'UTF-8');
$lenComentariosActual = mb_strlen(strip_tags($datos['comentarios'] ?? ''), 'UTF-8');

// include __DIR__ .  ' Mapa de mensajes (patrón $_mapa estándar) 
$msg   = $_GET['msg'] ?? '';
$_mapa = [
    // Éxitos
    'exito_estado'            => ['tipo' => 'exito',  'titulo_msg' => 'Estado actualizado',     'mensaje' => 'El estado de la tarea fue actualizado correctamente.'],
    'exito_borrador'          => ['tipo' => 'exito',  'titulo_msg' => 'Borrador guardado',       'mensaje' => 'El borrador fue guardado correctamente.'],
    // Errores de acción
    'error_estado'            => ['tipo' => 'error',  'titulo_msg' => 'Error al actualizar',     'mensaje' => 'No fue posible actualizar el estado. Intenta de nuevo.'],
    'error_borrador'          => ['tipo' => 'error',  'titulo_msg' => 'Error al guardar',        'mensaje' => 'No fue posible guardar el borrador. Intenta de nuevo.'],
    'error_tipo_invalido'     => ['tipo' => 'error',  'titulo_msg' => 'Acción no válida',        'mensaje' => 'No se pudo procesar la acción: falta el tipo de estado. Intenta de nuevo.'],
    // Límites de caracteres
    'limite_contenido'        => ['tipo' => 'error',  'titulo_msg' => 'Límite excedido',         'mensaje' => 'El contenido supera el límite de ' . number_format(MAX_CONTENIDO, 0, '.', ',') . ' caracteres permitidos. Reduce el texto antes de guardar.'],
    'limite_comentarios'      => ['tipo' => 'error',  'titulo_msg' => 'Límite excedido',         'mensaje' => 'Los comentarios superan el límite de ' . number_format(MAX_COMENTARIOS, 0, '.', ',') . ' caracteres permitidos. Reduce el texto antes de guardar.'],
    // Acceso / datos
    'accion_no_permitida'     => ['tipo' => 'alerta', 'titulo_msg' => 'Acción no permitida',     'mensaje' => 'La acción solicitada no está disponible para tu rol.'],
    'error_cargar'            => ['tipo' => 'error',  'titulo_msg' => 'Error al cargar',         'mensaje' => 'No se encontró la tarea solicitada.'],
    'error_sin_registro'      => ['tipo' => 'error',  'titulo_msg' => 'Sin datos',               'mensaje' => 'No se encontró información para esta tarea.'],
    'sin_argumentos_url'      => ['tipo' => 'error',  'titulo_msg' => 'Parámetros faltantes',    'mensaje' => 'Faltan parámetros en la URL. Accede a la tarea desde el listado.'],
    'periodo_vencido'         => ['tipo' => 'alerta', 'titulo_msg' => 'Periodo vencido',         'mensaje' => 'El periodo asociado a esta tarea ya venció.'],
];

ob_start();
?>

<div class="container-fluid py-4 ancho_container">

    <!-- Cabecera -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <div>
            <h4 class="mb-0 fw-semibold">
                <?= htmlspecialchars($datos['tipo_tarea'] ?? 'Tarea') ?>
            </h4>
            <small class="text-muted">ID asignación: <?= $id_asignacion ?></small>
        </div>
        <div class="d-flex align-items-center gap-2">
            <?php
            $est = $estadoLabel[$datos['id_estadoT'] ?? 1]
                ?? ['texto' => htmlspecialchars($datos['estado'] ?? ''), 'clase' => 'secondary'];
            ?>
            <span class="badge text-bg-<?= $est['clase'] ?> rounded-pill px-3 py-2">
                <?= htmlspecialchars($est['texto']) ?>
            </span>

            <?php if ($esBorrador && $rol === 'estudiante'): ?>
                <span class="badge bg-warning text-dark rounded-pill px-3 py-2">
                    <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="currentColor"
                        class="bi bi-pencil me-1" viewBox="0 0 16 16">
                        <path d="M12.146.146a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1 0 .708l-10 10a.5.5 0 0
                                 1-.168.11l-5 2a.5.5 0 0 1-.65-.65l2-5a.5.5 0 0 1 .11-.168zM11.207
                                 2.5 13.5 4.793 14.793 3.5 12.5 1.207zm1.586 3L10.5 3.207 4 9.707V10h.5a.5.5
                                 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1
                                 .5.5v.207zm-9.761 5.175-.106.106-1.528 3.821 3.821-1.528.106-.106A.5.5 0
                                 0 1 5 12.5V12h-.5a.5.5 0 0 1-.5-.5V11h-.5a.5.5 0 0 1-.5-.5V10h-.5a.5.5
                                 0 0 1-.468-.325z" />
                    </svg>Borrador sin enviar
                </span>
            <?php endif; ?>

            <?php if (in_array($rol, ['investigador', 'supervisor'], true)): ?>
                <a href="lista_tareas.php?id_tarea=<?= $id_tarea ?>&id_proyectos=<?= $id_proyecto ?>"
                    class="btn btn-secondary btn-sm px-3">← Regresar</a>
            <?php elseif (in_array($rol, ['estudiante', 'alumno'], true)): ?>
                <a href="tareas_estudiante.php?id_asignacion=<?= $id_asignacion ?>&id_tarea=<?= $id_tarea ?>&id_proyectos=<?= $id_proyecto ?>"
                    class="btn btn-secondary btn-sm px-3">
                    <i class="bi bi-arrow-left"></i> Regresar
                </a>
            <?php endif; ?>
        </div>
    </div>

        <!-- include __DIR__ .  ' Mensajes de feedback (patrón $_mapa) include __DIR__ .  ' -->
    <?php if (isset($_mapa[$msg])): extract($_mapa[$msg]); include __DIR__ . '/../../../publico/incluido/_mensaje.php'; endif; ?>

    <!-- Alerta: tarea modificada por investigador -->
    <?php if (!empty($datos['fecha_modificacion'])): ?>
        <div class="alert alert-warning d-flex align-items-center gap-2 py-2 mb-3" role="alert">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor"
                class="bi bi-pencil-fill flex-shrink-0" viewBox="0 0 16 16">
                <path d="M12.854.146a.5.5 0 0 0-.707 0L10.5 1.793 14.207 5.5l1.647-1.646a.5.5 0 0
                         0 0-.708zm.646 6.061L9.793 2.5 3.293 9H3.5a.5.5 0 0 1 .5.5v.5h.5a.5.5
                         0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.207zm-7.468
                         7.468A.5.5 0 0 1 6 13.5V13h-.5a.5.5 0 0 1-.5-.5V12h-.5a.5.5 0 0
                         1-.5-.5V11h-.5a.5.5 0 0 1-.5-.5V10h-.5a.5.5 0 0 1-.175-.032l-.179.178a.5.5
                         0 0 0-.11.168l-2 5a.5.5 0 0 0 .65.65l5-2a.5.5 0 0 0 .168-.11z" />
            </svg>
            <span class="small">El investigador actualizó esta tarea el
                <strong><?= date('d/m/Y', strtotime($datos['fecha_modificacion'])) ?></strong>.
                Revisa las instrucciones.
            </span>
        </div>
    <?php endif; ?>

    <!-- Alerta: borrador visible para el investigador -->
    <?php if ($esBorrador && $rol === 'investigador'): ?>
        <div class="alert alert-info d-flex align-items-center gap-2 py-2 mb-3" role="alert">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor"
                class="bi bi-clock-history flex-shrink-0" viewBox="0 0 16 16">
                <path d="M8.515 1.019A7 7 0 0 0 8 1V0a8 8 0 0 1 .589.022zm2.004.45a7 7 0 0
                         0-.985-.299l.219-.976q.576.129 1.126.342zm1.37.71a7 7 0 0 0-.439-.27l.493-.87a8
                         8 0 0 1 .979.654l-.615.789a7 7 0 0 0-.418-.302zm1.834 1.79a7 7 0 0
                         0-.653-.796l.724-.69q.406.429.747.91zm.744 1.352a7 7 0 0 0-.214-.468l.893-.45a8
                         8 0 0 1 .45 1.088l-.95.313a7 7 0 0 0-.179-.483m.53 2.507a7 7 0 0
                         0-.1-1.025l.985-.17q.1.58.116 1.17zm-.131 1.538q.05-.254.081-.51l.99.128a8 8
                         0 0 1-.198 1.006l-.95-.313q.08-.252.077-.505M8 1a7 7 0 1 0 4.95
                         11.95l.707.707A8.001 8.001 0 1 1 8 0z" />
                <path d="M7.5 3a.5.5 0 0 1 .5.5v5.21l3.248 1.856a.5.5 0 0 1-.496.868l-3.5-2A.5.5
                         0 0 1 7 9V3.5a.5.5 0 0 1 .5-.5" />
            </svg>
            <span class="small">El estudiante tiene un <strong>borrador guardado</strong> que aún no ha
                sido enviado para revisión.</span>
        </div>
    <?php endif; ?>

    <!-- Información de la tarea -->
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header"><i class="bi bi-info-circle me-2"></i> <b>Información de la tarea</b></div>
        <div class="card-body">
            <p class="tarea-seccion-label mb-1">Descripción</p>
            <p class="mb-3"><?= ($datos['descripcion'] ?? '') ?></p>
            <hr>
            <p class="tarea-seccion-label mb-1">Instrucciones</p>
            <p class="mb-0"><?= ($datos['instrucciones'] ?? '') ?></p>
        </div>
    </div>

    <!-- Archivo guía -->
    <?php if (!empty($datos['guia_nombre'])): ?>
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header"><b>Archivo guía</b></div>
            <div class="card-body py-2 d-flex align-items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="#dc3545"
                    class="bi bi-file-earmark-pdf-fill flex-shrink-0" viewBox="0 0 16 16">
                    <path d="M5.523 12.424q.21-.124.459-.238a8 8 0 0 1-.45.606c-.28.337-.498.516-.635.572l-.035.012a.3.3
                             0 0 1-.026-.044c-.056-.11-.054-.216.04-.36.106-.165.319-.354.647-.548m2.455-1.647q-.178.037-.356.078a21
                             21 0 0 0 .5-1.05 12 12 0 0 0 .51.858q-.326.048-.654.114m2.525.939a4 4 0 0
                             1-.435-.41q.344.007.612.054c.317.057.466.147.518.209a.1.1 0 0 1 .026.064.44.44
                             0 0 1-.06.2.3.3 0 0 1-.094.124.1.1 0 0 1-.069.015c-.09-.003-.258-.066-.498-.256M8.278
                             6.97c-.04.244-.108.524-.2.829a5 5 0 0 1-.089-.346c-.076-.353-.087-.63-.046-.822.038-.177.11-.248.196-.283a.5.5
                             0 0 1 .145-.04c.013.03.028.092.032.198q.008.183-.038.465z" />
                    <path fill-rule="evenodd" d="M4 0h5.293A1 1 0 0 1 10 .293L13.707 4a1 1 0 0 1 .293.707V14a2 2 0 0
                             1-2 2H4a2 2 0 0 1-2-2V2a2 2 0 0 1 2-2m5.5 1.5v2a1 1 0 0 0 1 1h2z" />
                </svg>
                <div class="flex-grow-1">
                    <span class="tarea-seccion-label d-block mb-0">Archivo de guía</span>
                    <a href="descargar_guia.php?id=<?= $id_tarea ?>" class="small">
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
                        <svg xmlns="http://www.w3.org/2000/svg" width="11" height="11" fill="currentColor"
                            class="bi bi-floppy me-1" viewBox="0 0 16 16">
                            <path d="M11 2H9v3h2z" />
                            <path d="M1.5 0h11.586a1.5 1.5 0 0 1 1.06.44l1.415 1.414A1.5 1.5 0 0 1 16
                                     2.914V14.5a1.5 1.5 0 0 1-1.5 1.5h-13A1.5 1.5 0 0 1 0
                                     14.5v-13A1.5 1.5 0 0 1 1.5 0M1 1.5v13a.5.5 0 0 0 .5.5H2v-4.5A1.5
                                     1.5 0 0 1 3.5 9h9a1.5 1.5 0 0 1 1.5 1.5V15h.5a.5.5 0 0 0
                                     .5-.5V2.914a.5.5 0 0 0-.146-.353l-1.415-1.415A.5.5 0 0 0 13.086
                                     1H13v4.5A1.5 1.5 0 0 1 11.5 7h-7A1.5 1.5 0 0 1 3 5.5V1H1.5a.5.5
                                     0 0 0-.5.5m3 4a.5.5 0 0 0 .5.5h7a.5.5 0 0 0 .5-.5V1H4zm6 6.5v3h-4v-3h4a.5.5
                                     0 0 1 0 0" />
                        </svg>Borrador guardado — no enviado aún
                    </span>
                <?php endif; ?>
            </div>

            <div class="card-body">

                <?php
                // NOTA DE LÍMITES (solo estudiante en estados editables)
                if ($rol === 'estudiante' && in_array($datos['id_estadoT'] ?? 0, [1, 8, 3], true)):
                ?>
                    <div class="nota-explicacion mb-3">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none"
                            viewBox="0 0 20 20" class="flex-shrink-0">
                            <circle cx="10" cy="10" r="8.5" stroke="currentColor" stroke-width="1.4" />
                            <path d="M10 9v5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
                            <circle cx="10" cy="6.5" r="0.8" fill="currentColor" />
                        </svg>
                        <span>
                            El <strong>contenido de tu entrega</strong> tiene un máximo de
                            <strong><?= number_format(MAX_CONTENIDO, 0, '.', ',') ?> caracteres</strong>
                            (texto plano, sin contar etiquetas HTML).
                            Los <strong>comentarios</strong> tienen un máximo de
                            <strong><?= number_format(MAX_COMENTARIOS, 0, '.', ',') ?> caracteres</strong>.
                            Los contadores se actualizan en tiempo real mientras escribes.
                        </span>
                    </div>
                <?php endif; ?>

                <form id="form-principal"
                    action="tarea.php"
                    method="POST"
                    enctype="multipart/form-data">

                    <input type="hidden" name="action"
                        value="<?= $rol === 'estudiante' ? 'editarTareaEstudiante' : 'editarTareaRevisar' ?>">
                    <input type="hidden" name="id_tarea"       value="<?= (int)$datos['id_tarea'] ?>">
                    <input type="hidden" name="id_proyectos"   value="<?= (int)$datos['id_proyectos'] ?>">
                    <input type="hidden" name="id_asignacion"  value="<?= (int)$datos['id_asignacion'] ?>">
                    <input type="hidden" name="tipo" id="campo-tipo" value="">

                    <?= $tareaControlador->tareas($datos['tipo_tarea'], $rol, $datos) ?? '' ?>

                    <?php if ($rol === 'estudiante' && in_array($datos['id_estadoT'] ?? 0, [1, 8, 3], true)): ?>
                        <!-- Contador: contenido principal -->
                        <div class="char-counter mt-1 mb-3" id="counter-contenido">
                            <span id="counter-contenido-val"><?= $lenContenidoActual ?></span>
                            / <?= number_format(MAX_CONTENIDO, 0, '.', ',') ?> caracteres
                        </div>
                        <!-- Contador: comentarios -->
                        <div class="char-counter mt-1 mb-2" id="counter-comentarios">
                            <span id="counter-comentarios-val"><?= $lenComentariosActual ?></span>
                            / <?= number_format(MAX_COMENTARIOS, 0, '.', ',') ?> caracteres (comentarios)
                        </div>
                    <?php endif; ?>

                    <div class="d-flex flex-wrap gap-2 mt-3">
                        <?= $tareaControlador->botonesAccionTarea(
                            $datos['id_tarea'],
                            $rol,
                            $datos['estado'],
                            $datos['id_asignacion']
                        ) ?>
                    </div>
                </form>

                <?php if ($rol === 'estudiante' && in_array($datos['id_estadoT'] ?? 0, [1, 8, 3], true)): ?>
                    <form id="form-borrador"
                        action="tarea.php"
                        method="POST"
                        enctype="multipart/form-data"
                        class="d-none">

                        <input type="hidden" name="action"       value="guardar_borrador">
                        <input type="hidden" name="id_tarea"     value="<?= (int)$datos['id_tarea'] ?>">
                        <input type="hidden" name="id_proyectos" value="<?= (int)$datos['id_proyectos'] ?>">
                        <input type="hidden" name="id_asignacion" value="<?= (int)$datos['id_asignacion'] ?>">
                        <!-- Rellenados por JS justo antes del submit -->
                        <input type="hidden" name="contenido"    id="borrador-contenido">
                        <input type="hidden" name="comentarios"  id="borrador-comentarios">
                    </form>
                <?php endif; ?>

            </div><!-- /.card-body -->
        </div><!-- /.card -->

    <?php else: ?>
        <!-- Supervisor: solo lectura -->
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body">
                <p class="tarea-seccion-label mb-1">Contenido del estudiante</p>
                <div class="mb-0">
                    <?= !empty($datos['contenido'])
                        ? $datos['contenido']
                        : 'Sin contenido entregado.' ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Línea de tiempo (historial de estados) -->
    <div class="card border-0 shadow-sm">
        <div class="card-header"><b>Historial de estados</b></div>
        <div class="card-body">
            <?php if (empty($historialAgrupado)): ?>
                <p class="text-muted small text-center py-3 mb-0">Sin historial de estados aún.</p>
            <?php else: ?>
                <div class="timeline-wrap">
                    <?php foreach ($historialAgrupado as $fecha => $items): ?>
                        <div class="mb-3">
                            <p class="text-muted small fw-semibold mb-2"><?= htmlspecialchars($fecha) ?></p>
                            <?php foreach ($items as $item):
                                $estadoItem = $estadoLabel[$item['id_estadoT']]
                                    ?? ['texto' => $item['estado'], 'clase' => 'secondary'];
                            ?>
                                <div class="timeline-item">
                                    <div class="timeline-dot bg-<?= $estadoItem['clase'] ?>"></div>
                                    <div class="ms-2">
                                        <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                                            <span class="badge text-bg-<?= $estadoItem['clase'] ?> rounded-pill">
                                                <?= htmlspecialchars($estadoItem['texto']) ?>
                                            </span>
                                            <small class="text-muted">
                                                <?= date('H:i', strtotime($item['fecha'])) ?>
                                            </small>
                                            <small class="text-muted">
                                                — <?= $item['esEstudiante'] ? 'Estudiante' : 'Investigador' ?>
                                                <?= !empty($item['usuario'])
                                                    ? '(' . htmlspecialchars($item['usuario']) . ')'
                                                    : '' ?>
                                            </small>
                                        </div>
                                        <?php if (!empty($item['comentario'])): ?>
                                            <p class="small mb-0 text-muted">
                                                <?= htmlspecialchars($item['comentario']) ?>
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Paginación -->
                <?php if ($paginacion['total_paginas'] > 1):
                    $qBase = http_build_query([
                        'id_asignacion' => $id_asignacion,
                        'id_tarea'      => $id_tarea,
                        'id_proyectos'  => $id_proyecto,
                    ]);
                    include __DIR__ . '/../../publico/incluido/_paginacion.php';
                endif; ?>
            <?php endif; ?>
        </div>
    </div>

</div><!-- /.container-fluid -->

<!-- TinyMCE -->
<script src="https://cdn.tiny.cloud/1/0ro7u4jwmnmqkovrjmi7cc1w5kk7tzragurlph7foryy7xbv/tinymce/6/tinymce.min.js"></script>

<style>
    .nota-explicacion {
        display: flex;
        align-items: flex-start;
        gap: .5rem;
        background: #f0f6ff;
        border-left: 3px solid #3b82f6;
        border-radius: .375rem;
        padding: .6rem .85rem;
        font-size: .825rem;
        color: #374151;
        line-height: 1.5;
    }
    .nota-explicacion svg { color: #3b82f6; margin-top: .1rem; }

    .char-counter {
        font-size: .78rem;
        color: #6b7280;
        text-align: right;
        transition: color .2s;
    }
    .char-counter.near-limit { color: #d97706; font-weight: 600; }
    .char-counter.over-limit { color: #dc2626; font-weight: 700; }
</style>

<script>
    (function() {
        'use strict';

        const esSoloLectura  = <?= in_array($rol, ['investigador', 'supervisor'], true) ? 'true' : 'false' ?>;
        const esEstudianteEdit = <?= ($rol === 'estudiante' && in_array($datos['id_estadoT'] ?? 0, [1, 8, 3], true)) ? 'true' : 'false' ?>;

        const LIMITE_CONTENIDO   = <?= MAX_CONTENIDO ?>;
        const LIMITE_COMENTARIOS = <?= MAX_COMENTARIOS ?>;

        function obtenerTextoPlano(editorId) {
            const inst = tinymce.get(editorId);
            if (inst) return inst.getContent({ format: 'text' });
            const el = document.getElementById(editorId);
            return el ? el.value : '';
        }

        function actualizarContador(contadorId, valorId, len, max) {
            const counter = document.getElementById(contadorId);
            const valEl   = document.getElementById(valorId);
            if (!counter || !valEl) return;
            valEl.textContent = len.toLocaleString('es-MX');
            counter.classList.remove('near-limit', 'over-limit');
            if (len > max)           counter.classList.add('over-limit');
            else if (len >= max * .9) counter.classList.add('near-limit');
        }

        function refrescarContadores() {
            if (!esEstudianteEdit) return;
            const formPpal = document.getElementById('form-principal');
            if (!formPpal) return;

            const editorContenido = formPpal.querySelector('.editor');
            if (editorContenido) {
                const len = [...obtenerTextoPlano(editorContenido.id)].length;
                actualizarContador('counter-contenido', 'counter-contenido-val', len, LIMITE_CONTENIDO);
            }

            const taComentarios = formPpal.querySelector('[name="comentarios"]');
            if (taComentarios) {
                const instCom = tinymce.get(taComentarios.id);
                const texto   = instCom ? instCom.getContent({ format: 'text' }) : taComentarios.value;
                actualizarContador('counter-comentarios', 'counter-comentarios-val', [...texto].length, LIMITE_COMENTARIOS);
            }
        }

        function validarLimites() {
            if (!esEstudianteEdit) return true;
            const formPpal = document.getElementById('form-principal');
            if (!formPpal) return true;

            const editorContenido = formPpal.querySelector('.editor');
            if (editorContenido) {
                const len = [...obtenerTextoPlano(editorContenido.id)].length;
                if (len > LIMITE_CONTENIDO) {
                    const exceso = len - LIMITE_CONTENIDO;
                    alert(`"Contenido de tu entrega" supera el límite en ${exceso.toLocaleString('es-MX')} carácter${exceso !== 1 ? 'es' : ''}.\nPor favor reduce el texto antes de enviar.`);
                    tinymce.get(editorContenido.id)?.focus();
                    return false;
                }
            }

            const taComentarios = formPpal.querySelector('[name="comentarios"]');
            if (taComentarios) {
                const instCom = tinymce.get(taComentarios.id);
                const texto   = instCom ? instCom.getContent({ format: 'text' }) : taComentarios.value;
                const len     = [...texto].length;
                if (len > LIMITE_COMENTARIOS) {
                    const exceso = len - LIMITE_COMENTARIOS;
                    alert(`"Comentarios" supera el límite en ${exceso.toLocaleString('es-MX')} carácter${exceso !== 1 ? 'es' : ''}.\nPor favor reduce el texto antes de enviar.`);
                    if (instCom) instCom.focus();
                    return false;
                }
            }

            return true;
        }

        document.addEventListener('DOMContentLoaded', function() {

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
                setup(editor) {
                    editor.on('init',                     () => refrescarContadores());
                    editor.on('input keyup change SetContent', () => refrescarContadores());
                },
            });

            // include __DIR__ .  ' Form principal include __DIR__ .  '
            const formPrincipal = document.getElementById('form-principal');
            const campoTipo     = document.getElementById('campo-tipo');

            if (formPrincipal && campoTipo) {
                formPrincipal.querySelectorAll('button[type="submit"][name="tipo"]').forEach(btn => {
                    btn.addEventListener('click', function() { campoTipo.value = this.value; });
                });

                formPrincipal.addEventListener('submit', function(e) {
                    if (!campoTipo.value) {
                        const primerBoton = formPrincipal.querySelector('button[type="submit"][name="tipo"]');
                        if (primerBoton) campoTipo.value = primerBoton.value;
                    }
                    if (!validarLimites()) { e.preventDefault(); return; }

                    const editorEl = formPrincipal.querySelector('.editor');
                    if (editorEl) {
                        const inst = tinymce.get(editorEl.id);
                        if (inst) inst.save();
                    }
                });
            }

            // include __DIR__ .  ' Form borrador include __DIR__ .  '
            const formBorrador = document.getElementById('form-borrador');
            if (formBorrador) {
                formBorrador.addEventListener('submit', function(e) {
                    if (!validarLimites()) { e.preventDefault(); return; }

                    const editorEl = formPrincipal
                        ? formPrincipal.querySelector('.editor')
                        : document.querySelector('.editor');

                    if (editorEl) {
                        const inst = tinymce.get(editorEl.id);
                        if (inst) document.getElementById('borrador-contenido').value = inst.getContent();
                    }

                    if (!document.getElementById('borrador-contenido').value) {
                        const ta = formPrincipal
                            ? formPrincipal.querySelector('textarea[name="contenido"]')
                            : document.querySelector('textarea[name="contenido"]');
                        if (ta) document.getElementById('borrador-contenido').value = ta.value;
                    }

                    const com = formPrincipal
                        ? formPrincipal.querySelector('[name="comentarios"]')
                        : document.querySelector('[name="comentarios"]');
                    if (com) {
                        const instCom = tinymce.get(com.id);
                        document.getElementById('borrador-comentarios').value = instCom
                            ? instCom.getContent()
                            : com.value;
                    }
                });
            }

        }); // DOMContentLoaded

    })();
</script>

<?php
$page_content = ob_get_clean();
$titulo       = 'Revisar Tarea';
$contenido    = $page_content;
include __DIR__ . '/../../layout.php';
?>