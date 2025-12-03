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
                <a href="lista_tareas.php?id_tarea=<?=$datos['id_tarea'];?>&id_proyectos=<?= $id_proyecto; ?>" class="btn btn-danger">Regresar</a>
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
                    <input type="hidden" name="id_tareas[]" value="<?= $datos['id_tarea'] ?>">

                    <?= $tareaControlador->tareas($tipo, $rol, $datos) ?>
                </div>
        </div>
        <?php if($rol != "supervisor"): ?>
        <div class="row mb-1">
            <div class="col-12">
                <button type="submit" class="btn btn-primary">Guardar cambios</button>
            </div>
        </div>
        <?php endif; ?>
        </form>
    </div>
</div>
</div>
<!-- Modal -->
<div class="modal fade" id="mensaje" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="staticBackdropLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h1 class="modal-title fs-5" id="staticBackdropLabel">Operación correctamente </h1>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <img src="/ITSFCP-PROYECTOS/publico/icons/comprobar.svg" alt="">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>
<?php
$contenido = ob_get_clean();
$titulo = "Editar tarea";
$bodyClass = "proyectos-page";

include __DIR__ . '/../../layout.php';
?>
<?php if (isset($_GET['msg']) && $_GET['msg'] == 'mensaje'): ?>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            new bootstrap.Modal(document.getElementById('mensaje')).show();
        });
    </script>
<?php endif; ?>
