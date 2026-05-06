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

$id_tarea     = $_GET["id_tarea"]    ?? null;
$id_proyectos = $_GET["id_proyectos"] ?? $_POST["id_proyectos"] ?? null;
$action       = $_POST['action']     ?? $_GET['action'] ?? null;

require_once '../../Controladores/tareasControlador.php';
$tareaControlador = new TareaControlador();

if ($action == 'editarTarea') {
    $_POST['id_usuario'] = $id;
    $tareaControlador->editarTarea($_POST, $rol, $id_proyectos);
}
if ($action == 'actualizarestado' && isset($_GET['id_tarea'])) {
    $tareaControlador->actualizarestado($_GET['id_tarea'], $rol, $_GET['tipo'], $id_proyectos);
}

$tarea    = $tareaControlador->mostrarEditarTarea($id_tarea, $rol);
$ediciones = $tareaControlador->obtenerEdicionesRecientes($id_tarea, 8);

$campoNombres = [
    'descripcion'  => 'Descripción',
    'instrucciones' => 'Instrucciones',
    'fecha_entrega' => 'Fecha de entrega',
    'archivo_guia'  => 'Archivo de guía',
];

ob_start();
include __DIR__ . '/../../mensaje.php';
?>

<div class="container-fluid py-4" style="max-width:95%;">

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
            <a href="tabla.php?id_proyectos=<?= htmlspecialchars($id_proyectos) ?>"
               class="btn btn-secondary btn-sm px-3">
                <i class="bi bi-arrow-left"></i> Regresar
            </a>
        </div>
    </div>

    <!-- Alerta de última edición -->
    <?php if (!empty($tarea['fecha_modificacion'])): ?>
        <div class="alert alert-info d-flex gap-2 align-items-center py-2 mb-3">
            <small>Última edición: <strong><?= date('d/m/Y H:i', strtotime($tarea['fecha_modificacion'])) ?></strong></small>
        </div>
    <?php endif; ?>

    <!-- Formulario de edición -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header"><b>Información de la tarea</b></div>
        <div class="card-body">
            <form action="editar.php?id_proyectos=<?= htmlspecialchars($id_proyectos ?? '') ?>"
                  method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action"       value="editarTarea">
                <input type="hidden" name="id_tarea"     value="<?= $tarea['id_tarea'] ?>">
                <input type="hidden" name="id_proyectos" value="<?= htmlspecialchars($id_proyectos ?? '') ?>">
                <input type="hidden" name="id_usuario"   value="<?= $id ?>">

                <div class="mb-3">
                    <label class="form-label tarea-seccion-label">Descripción</label>
                    <textarea class="form-control editor" name="descripcion" rows="3"><?= htmlspecialchars($tarea['descripcion'] ?? '') ?></textarea>
                </div>

                <div class="mb-3">
                    <label class="form-label tarea-seccion-label">Instrucciones</label>
                    <textarea class="form-control editor" name="instrucciones" rows="3"><?= htmlspecialchars($tarea['instrucciones'] ?? '') ?></textarea>
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
                                <a href="descargar_guia.php?id=<?= $tarea['id_tarea'] ?>"
                                   class="small text-danger d-flex align-items-center gap-1">
                                    <?= htmlspecialchars($tarea['archivo_nombre']) ?>
                                </a>
                            </div>
                            <?php if (($tarea['id_tareatipo'] ?? 0) == 12): ?>
                                <small class="text-danger">La plantilla fue proporcionada por la plataforma</small>
                            <?php endif; ?>
                        <?php else: ?>
                            <p class="small text-muted mb-1">Sin archivo cargado.</p>
                        <?php endif; ?>
                        <label class="form-label tarea-seccion-label mt-1">Subir nuevo archivo</label>
                        <input type="file" name="archivo" class="form-control form-control-sm">
                        <small class="text-muted">Si subes un nuevo archivo reemplazará el anterior.</small>
                    </div>
                </div>

                <!-- Botones -->
                <div class="pt-3 d-flex flex-wrap align-items-center gap-2">
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
                            <small class="text-muted">
                                <?= date('d/m/Y H:i', strtotime($ed['fecha'])) ?>
                                · <?= htmlspecialchars($ed['editor'] ?? '') ?>
                            </small>
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

<!-- TinyMCE -->
<script src="https://cdn.tiny.cloud/1/0ro7u4jwmnmqkovrjmi7cc1w5kk7tzragurlph7foryy7xbv/tinymce/6/tinymce.min.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function () {
    tinymce.init({
        selector: '.editor',
        height: 350,
        plugins: 'lists link table code wordcount charmap insertdatetime',
        toolbar: `
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
});
</script>

<?php
$contenido = ob_get_clean();
$titulo    = "Editar Actividad";
$bodyClass = "proyectos-page";
include __DIR__ . '/../../layout.php';
?>