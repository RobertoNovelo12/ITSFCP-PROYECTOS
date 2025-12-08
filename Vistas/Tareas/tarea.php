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

$id_asignacion = $_GET["id_asignacion"] ?? null;
$tipo = $_GET["tipo"] ?? null;
$id_proyecto = $_GET["id_proyectos"] ?? null;
$action = $_POST['action'] ?? null;

require_once '../../Controladores/tareasControlador.php';
$tareaControlador = new TareaControlador();

//Datos necesarios
$datos = $tareaControlador->mostrarTarea($id_asignacion, $rol); // Para rellenar
if ($action == 'editarTarea') {
    $tareaControlador->editarTarea($_POST, $id, $rol);
}
if ($action == 'editarTareaRevisar') {
    $tareaControlador->editarTareaRevisar($_POST, $id, $rol);
}
// ======================
// GENERAR CONTENIDO
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
                <a href="lista_tareas.php?id_tarea=<?= $datos['id_tarea']; ?>&id_proyectos=<?= $id_proyecto; ?>" class="btn btn-danger">Regresar</a>
            </div>
            <div class="row mb-1">
                <div class="mb-3">
                    <h5>Descripción</h5>
                    <span><?= $datos['descripcion'] ?></span>
                </div>
            </div>
            <div class="row mb-1">
                <div class="mb-3">
                    <h5>Instrucciones</h5>
                    <span><?= $datos['instrucciones'] ?></span>
                </div>
            </div>

            <form action="tarea.php" method="POST" enctype="multipart/form-data">
                <div class="row mb-1">
                    <?php if ($rol == "estudiante"): ?>
                        <input type="hidden" name="action" value="editarTarea">
                    <?php endif; ?>
                    <?php if ($rol == "investigador"): ?>
                        <input type="hidden" name="action" value="editarTareaRevisar">
                    <?php endif; ?>
                    <input type="hidden" name="id_tarea[]" value="<?= $datos['id_tarea'] ?>">

                    <?= $tareaControlador->tareas($tipo, $rol, $datos) ?>
                </div>

                <div class="row mb-1">
                    <?php if ($rol == "investigador"): ?>
                        <a href="editar.php?id_tarea=<?php $id_tarea; ?>&action=editarTareaRevisar&tipo=Corregir" type="button" class="btn btn-info" data-bs-toggle="tooltip" data-bs-placement="top"
                                data-bs-custom-class="custom-tooltip" data-bs-title="Solicitar corregir">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-arrow-counterclockwise" viewBox="0 0 16 16">
                                    <path fill-rule="evenodd" d="M8 3a5 5 0 1 1-4.546 2.914.5.5 0 0 0-.908-.417A6 6 0 1 0 8 2z" />
                                    <path d="M8 4.466V.534a.25.25 0 0 0-.41-.192L5.23 2.308a.25.25 0 0 0 0 .384l2.36 1.966A.25.25 0 0 0 8 4.466" />
                                    </svg></a>
                    <?php endif; ?>
                    <?php if ($rol != "supervisor"): ?>
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary">Guardar cambios</button>
                        </div>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$contenido = ob_get_clean();
$titulo = "Editar tarea";
$bodyClass = "proyectos-page";

include __DIR__ . '/../../layout.php';
?>
