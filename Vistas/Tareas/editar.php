<?php
// editar
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

if (!isset($_SESSION['id_usuario'])) {
    header("Location: /ITSFCP-PROYECTOS/index.php");
    exit;
}

$rol = $_SESSION['rol'];
$id  = $_SESSION['id_usuario'];

// Solo investigador/profesor puede editar
if (!in_array($rol, ['investigador', 'profesor'])) {
    header("Location: /ITSFCP-PROYECTOS/index.php");
    exit;
}

$id_tarea    = $_GET["id_tarea"] ?? null;
$id_proyectos = $_GET["id_proyectos"] ?? $_POST["id_proyectos"] ?? null;
$action      = $_POST['action'] ?? $_GET['action'] ?? null;

require_once '../../Controladores/tareasControlador.php';
$tareaControlador = new TareaControlador();

if ($action == 'editarTarea') {
    $_POST['id_usuario'] = $id;
    $tareaControlador->editarTarea($_POST, $rol, $id_proyectos);
}
if ($action == 'actualizarestado' && isset($_GET['id_tarea'])) {
    $tareaControlador->actualizarestado($_GET['id_tarea'], $rol, $_GET['tipo'], $id_proyectos);
}

$tarea = $tareaControlador->mostrarEditarTarea($id_tarea, $rol);

// Historial de ediciones
$ediciones = $tareaControlador->obtenerEdicionesRecientes($id_tarea, 8);

$campoNombres = [
    'descripcion'  => 'Descripción',
    'instrucciones'=> 'Instrucciones',
    'fecha_entrega'=> 'Fecha de entrega',
    'archivo_guia' => 'Archivo de guía',
];

ob_start();
include __DIR__ . '/../../mensaje.php';
?>
<div class="container-fluid py-4" style="max-width:800px;">

    <!-- Cabecera -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
        <div>
            <h4 class="mb-0 fw-semibold"><?= htmlspecialchars($tarea['titulo_tarea'] ?? 'Editar Actividad') ?></h4>
            <small class="text-muted">Modificar instrucciones, descripción y archivo de guía</small>
        </div>
        <div class="d-flex align-items-center gap-2">
            <span class="badge rounded-pill text-bg-<?= $tareaControlador->EstiloEstadoLista($tarea['estado'] ?? '') ?>">
                <?= htmlspecialchars($tarea['estado'] ?? '') ?>
            </span>
            <a href="tabla.php?id_proyectos=<?= htmlspecialchars($id_proyectos) ?>" class="btn btn-secondary btn-sm px-3"><i class="bi bi-arrow-left"></i>  Regresar</a>
        </div>
    </div>

    <!-- Alerta si fue editada antes -->
    <?php if (!empty($tarea['fecha_modificacion'])): ?>
        <div class="alert alert-info d-flex gap-2 align-items-center py-2 mb-3">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-clock-history flex-shrink-0" viewBox="0 0 16 16">
                <path d="M8.515 1.019A7 7 0 0 0 8 1V0a8 8 0 0 1 .589.022zm2.004.45a7 7 0 0 0-.985-.299l.219-.976q.576.129 1.126.342zm1.37.71a7 7 0 0 0-.439-.27l.493-.87a8 8 0 0 1 .979.654l-.615.789a7 7 0 0 0-.418-.302zm1.834 1.79a7 7 0 0 0-.653-.796l.724-.69q.406.429.747.91zm.744 1.352a7 7 0 0 0-.214-.468l.893-.45a8 8 0 0 1 .45 1.088l-.95.313a7 7 0 0 0-.179-.483m.53 2.507a7 7 0 0 0-.1-1.025l.985-.17q.1.58.116 1.17zm-.131 1.538q.05-.254.081-.51l.99.128a8 8 0 0 1-.198 1.JEDIs l-.95-.313q.08-.252.077-.505M8 1a7 7 0 1 0 4.95 11.95l.707.707A8.001 8.001 0 1 1 8 0z"/>
                <path d="M7.5 3a.5.5 0 0 1 .5.5v5.21l3.248 1.856a.5.5 0 0 1-.496.868l-3.5-2A.5.5 0 0 1 7 9V3.5a.5.5 0 0 1 .5-.5"/>
            </svg>
            <small>Última edición: <strong><?= date('d/m/Y H:i', strtotime($tarea['fecha_modificacion'])) ?></strong></small>
        </div>
    <?php endif; ?>

    <!-- Formulario de edición -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form action="editar.php?id_proyectos=<?= htmlspecialchars($id_proyectos ?? '') ?>" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action"       value="editarTarea">
                <input type="hidden" name="id_tarea"     value="<?= $tarea['id_tarea'] ?>">
                <input type="hidden" name="id_proyectos" value="<?= htmlspecialchars($id_proyectos ?? '') ?>">
                <input type="hidden" name="id_usuario"   value="<?= $id ?>">

                <div class="mb-3">
                    <label class="form-label tarea-seccion-label">Descripción</label>
                    <textarea name="descripcion" class="form-control" rows="5"><?= htmlspecialchars($tarea['descripcion'] ?? '') ?></textarea>
                </div>

                <div class="mb-3">
                    <label class="form-label tarea-seccion-label">Instrucciones</label>
                    <textarea name="instrucciones" class="form-control" rows="6"><?= htmlspecialchars($tarea['instrucciones'] ?? '') ?></textarea>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label tarea-seccion-label">Fecha de entrega</label>
                        <input type="date" name="fecha_entrega" class="form-control"
                               value="<?= htmlspecialchars($tarea['fecha_entrega'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label tarea-seccion-label">Archivo de guía actual</label>
                        <?php if (!empty($tarea['archivo_nombre'])): ?>
                            <div class="mb-1">
                                <a href="descargar_guia.php?id=<?= $tarea['id_tarea'] ?>" class="small text-danger d-flex align-items-center gap-1">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" class="bi bi-file-earmark-pdf-fill" viewBox="0 0 16 16">
                                        <path d="M5.523 12.424q.21-.124.459-.238a8 8 0 0 1-.45.606c-.28.337-.498.516-.635.572l-.035.012a.3.3 0 0 1-.026-.044c-.056-.11-.054-.216.04-.36.106-.165.319-.354.647-.548m2.455-1.647q-.178.037-.356.078a21 21 0 0 0 .5-1.05 12 12 0 0 0 .51.858q-.326.048-.654.114m2.525.939a4 4 0 0 1-.435-.41q.344.007.612.054c.317.057.466.147.518.209a.1.1 0 0 1 .026.064.44.44 0 0 1-.06.2.3.3 0 0 1-.094.124.1.1 0 0 1-.069.015c-.09-.003-.258-.066-.498-.256M8.278 6.97c-.04.244-.108.524-.2.829a5 5 0 0 1-.089-.346c-.076-.353-.087-.63-.046-.822.038-.177.11-.248.196-.283a.5.5 0 0 1 .145-.04c.013.03.028.092.032.198q.008.183-.038.465z"/>
                                        <path fill-rule="evenodd" d="M4 0h5.293A1 1 0 0 1 10 .293L13.707 4a1 1 0 0 1 .293.707V14a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V2a2 2 0 0 1 2-2m5.5 1.5v2a1 1 0 0 0 1 1h2zM4.165 13.668c.09.18.23.343.438.419.207.075.412.04.58-.03.318-.13.635-.436.926-.786.333-.401.683-.927 1.021-1.51a11.7 11.7 0 0 1 1.997-.406c.3.383.61.713.91.95.28.22.603.403.934.417a.86.86 0 0 0 .51-.138c.155-.101.27-.247.354-.416.09-.181.145-.37.138-.563a.84.84 0 0 0-.2-.518c-.226-.27-.596-.4-.96-.465a5.8 5.8 0 0 0-1.335-.05 11 11 0 0 1-.98-1.686c.25-.66.437-1.284.52-1.794.036-.218.055-.426.048-.614a1.24 1.24 0 0 0-.127-.538.7.7 0 0 0-.477-.365c-.202-.043-.41 0-.601.077-.377.15-.576.47-.651.823-.073.34-.04.736.046 1.136.088.406.238.848.43 1.295a20 20 0 0 1-1.062 2.227 7.7 7.7 0 0 0-1.482.645c-.37.22-.699.48-.897.787-.21.326-.275.714-.08 1.103"/>
                                    </svg>
                                    <?= htmlspecialchars($tarea['archivo_nombre']) ?>
                                </a>
                            </div>
                        <?php else: ?>
                            <p class="small text-muted mb-1">Sin archivo cargado.</p>
                        <?php endif; ?>
                        <label class="form-label tarea-seccion-label mt-1">Subir nuevo archivo</label>
                        <input type="file" name="archivo" class="form-control form-control-sm">
                        <small class="text-muted">Si subes un nuevo archivo reemplazará el anterior.</small>
                    </div>
                </div>

                <!-- Botones de acción de estado -->
                <div class="pt-3 d-flex flex-wrap align-items-center">
                    <button type="submit" class="btn btn-primary btn-sm px-4">Guardar cambios</button>
                    <?= $tareaControlador->botonesAccionTarea($tarea['id_tarea'], $rol, $tarea['estado'], $id_proyectos) ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Historial de ediciones -->
    <?php if (!empty($ediciones)): ?>
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <p class="tarea-seccion-label mb-3">Historial de cambios en esta actividad</p>
                <?php foreach ($ediciones as $ed): ?>
                    <div class="historial-edicion-item">
                        <div class="d-flex flex-wrap justify-content-between gap-1 mb-1">
                            <span class="small fw-semibold text-dark">
                                <?= $campoNombres[$ed['campo_modificado']] ?? htmlspecialchars($ed['campo_modificado']) ?>
                            </span>
                            <small class="text-muted"><?= date('d/m/Y H:i', strtotime($ed['fecha'])) ?> · <?= htmlspecialchars($ed['editor'] ?? '') ?></small>
                        </div>
                        <?php if ($ed['campo_modificado'] === 'fecha_entrega'): ?>
                            <small class="text-muted">
                                <?= htmlspecialchars($ed['valor_anterior'] ?? '—') ?>
                                <span class="mx-1">→</span>
                                <strong><?= htmlspecialchars($ed['valor_nuevo'] ?? '—') ?></strong>
                            </small>
                        <?php elseif ($ed['campo_modificado'] === 'archivo_guia'): ?>
                            <small class="text-muted">Se actualizó el archivo de guía.</small>
                        <?php else: ?>
                            <small class="text-muted">Contenido actualizado.</small>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

</div>

<?php
$contenido = ob_get_clean();
$titulo    = "Editar Actividad";
$bodyClass = "proyectos-page";
include __DIR__ . '/../../layout.php';
?>
