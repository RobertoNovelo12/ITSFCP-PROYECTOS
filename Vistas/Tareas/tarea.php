<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

if (!isset($_SESSION['id_usuario'])) {
    header("Location: /ITSFCP-PROYECTOS/index.php");
    exit;
}
$rol = $_SESSION['rol'];
$id = $_SESSION['id_usuario'];

$id_asignacion = $_POST["id_asignacion"]
    ?? $_GET["id_asignacion"]
    ?? null;
$id_tarea = $_POST["id_tarea"] ?? $_GET["id_tarea"] ?? null;

if ($id_asignacion == null) {
    die("ERROR: No se recibió id_asignacion");
}
$estado = $_POST["estado"] ?? $_GET["estado"] ?? null;
$id_proyecto = $_GET["id_proyectos"] ?? null;
$action = $_POST['action'] ?? $_GET['action'] ?? null;
$tipo = $_GET['tipo'] ?? null;
require_once '../../Controladores/tareasControlador.php';
$tareaControlador = new TareaControlador();

// DATOS
$datos = $tareaControlador->mostrarTarea($id_asignacion, $rol);

if ($action == 'editarTareaEstudiante') {
    $tareaControlador->editarTareaEstudiante($_POST, $rol);
}
if ($action == 'editarTareaRevisar') {
    $tareaControlador->editarTareaRevisar($_POST, $rol);
}
if ($action == 'actualizarestado') {
    $tareaControlador->actualizarestado($_GET['id_tarea'], $rol, $_GET['tipo'], $id_proyecto, $id_asignacion);
}

// ======================
// GENERAR VISTA
// ======================
ob_start();
?>
<div class="container-fluid py-4">
    <div class="row mb-3 align-items-center">

        <div class="row mb-1">
            <div class="col-6">
                <h3>Revisar Tarea</h3>
            </div>
            <div class="col-6 text-end">
                <?php if ($rol == "investigador"){ ?>
                    <a href="lista_tareas.php?id_tarea=<?= $id_tarea; ?>&id_proyectos=<?= $id_proyecto; ?>" class="btn btn-danger">Regresar</a>
                <?php }elseif ($rol == "estudiante"){ ?>
                    <a href="tareas_estudiante.php?id_tarea=<?= $id_tarea; ?>&id_proyectos=<?= $id_proyecto; ?>" class="btn btn-danger">Regresar</a>
                <?php } ?>
            </div>
        </div>

        <div class="row mb-1">
            <div class="mb-3">
                <h5>Descripción</h5>
                <span><?= $datos['descripcion'] ?? "" ?></span>
            </div>
        </div>

        <div class="row mb-1">
            <div class="mb-3">
                <h5>Instrucciones</h5>
                <span><?= $datos['instrucciones'] ?? "" ?></span>
            </div>
        </div>

        <form action="tarea.php" method="POST" enctype="multipart/form-data">
            <div class="row mb-1">

                <?php if ($rol == "estudiante"): ?>
                    <input type="hidden" name="action" value="editarTareaEstudiante">
                <?php endif; ?>

                <?php if ($rol == "investigador"): ?>
                    <input type="hidden" name="action" value="editarTareaRevisar">
                <?php endif; ?>

                <input type="hidden" name="id_tarea" value="<?= $datos['id_tarea']; ?>">
                <input type="hidden" name="id_asignacion" value="<?= $datos['id_asignacion']; ?>">

                <?php echo $tareaControlador->tareas($datos['tipo_tarea'], $rol, $datos) ?? ""; ?>
            </div>

            <div class="row mb-1">
                <div class="col-12">
                    <?php echo $tareaControlador->botonesAccionTarea($datos['id_tarea'], $rol, $estado, $datos['id_asignacion'], $id_proyecto);?>
                    <?php if ($rol != "supervisor"): ?>
                        <button type="submit" class="btn btn-primary">Guardar cambios</button>
                    <?php endif; ?>

                </div>
            </div>
        </form>

    </div>
</div>

<?php
$contenido = ob_get_clean();
$titulo = "Editar tarea";
$bodyClass = "proyectos-page";

include __DIR__ . '/../../layout.php';
?>
