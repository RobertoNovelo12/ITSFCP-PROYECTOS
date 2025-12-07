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

$id_tarea = $_GET["id_tarea"] ?? null;
$action = $_POST['action'] ?? null;

require_once '../../Controladores/tareasControlador.php';
$tareaControlador = new TareaControlador();

//Datos necesarios
$tarea = $tareaControlador->mostrarEditarTarea($id_tarea, $rol); // Para rellenar

if ($action == 'editarTarea') {
    $tareaControlador->editarTarea($_POST, $id, $rol);
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
                <h3>Editar Tarea</h3>
            </div>
            <div class="col-6 text-end">
                <a href="tabla.php" class="btn btn-danger">Regresar</a>
            </div>

            <form action="editar.php" method="POST" enctype="multipart/form-data">
                <div class="row mb-1">
                    <input type="hidden" name="action" value="editarTarea">
                    <input type="hidden" name="id_tareas" value="<?= $tarea['id_tarea'] ?>">

                    <h3>Datos de la tarea</h3>
                    <div class="mb-3">
                        <label>Descripción:</label>
                        <textarea name="descripcion" class="form-control"><?= htmlspecialchars($tarea['descripcion']) ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label>Instrucciones:</label>
                        <textarea name="Instrucciones" class="form-control"><?= htmlspecialchars($tarea['instrucciones']) ?></textarea>
                    </div>
                    <div class="mb-3">
                        <h3>Seguimiento</h3>
                        <input type="hidden" name="id_avances" class="form-control" value="<?= $tarea['id_avances'] ?>">

                            </div>
                    </div>
                    <div class="row mb-1">
                        <div class="col-md">
                            <div class="mb-3">
                                <label>Fecha entrega:</label>
                                <input type="date" name="fecha_entrega" class="form-control"
                                    value="<?= $tarea['fecha_entrega'] ?>">
                            </div>
                        </div>
                        <div class="col-md">
                            <div class="mb-3">
                                <label>Archivo actual:</label>
                                <?php if (!empty($tarea) && !empty($tarea['archivo_nombre'])): ?>
                                    <a href="descargar_guia.php?id=<?= $tarea['id_tarea'] ?>">
                                        Descargar archivo (<?= $tarea['archivo_nombre'] ?>)
                                    </a>
                                <?php else: ?>
                                    <p>No hay archivo cargado.</p>
                                <?php endif; ?>

                                <label>Subir archivo nuevo:</label>
                                <input type="file" name="archivo">
                            </div>
                        </div>
                    </div>
                    <div class="row mb-1">
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary">Guardar cambios</button>
                        </div>
                    </div>
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
