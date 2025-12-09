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
$id_proyectos = $_GET["id_proyectos"] ?? $_POST["id_proyectos"] ?? null;
$action = $_POST['action'] ?? $_GET['action'] ?? null;


require_once '../../Controladores/tareasControlador.php';
$tareaControlador = new TareaControlador();

//Datos necesarios
$tarea = $tareaControlador->mostrarEditarTarea($id_tarea, $rol); // Para rellenar

if ($action == 'editarTarea') {
    $tareaControlador->editarTarea($_POST, $rol, $id_proyectos);
}
if ($action == 'actualizarestado' && isset($_GET['id_tarea'])) {
    $tareaControlador->actualizarestado($_GET['id_tarea'], $rol, $_GET['tipo'], $id_proyectos);
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
                <a href="tabla.php?id_proyectos=<?= $id_proyectos; ?>" class="btn btn-danger">Regresar</a>
            </div>

            <form action="editar.php?id_proyectos=<?php $id_proyectos ?? $tarea['id_tarea']; ?>" method="POST" enctype="multipart/form-data">
                <div class="row mb-1">
                    <input type="hidden" name="action" value="editarTarea">
                    <input type="hidden" name="id_tarea" value="<?= $tarea['id_tarea']; ?>">
                    <input type="hidden" name="id_proyectos" value="<?= $id_proyectos; ?>">
                    <div class="mb-3">
                        <label>Descripción:</label>
                        <textarea name="descripcion" class="form-control"><?= htmlspecialchars($tarea['descripcion']) ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label>Instrucciones:</label>
                        <textarea name="instrucciones" class="form-control"><?= htmlspecialchars($tarea['instrucciones']) ?></textarea>
                    </div>

                    <div class="row mb-1">
                        <div class="col-md">
                            <div class="mb-3">
                                <label>Fecha entrega:</label>
                                <input type="date" name="fecha_entrega" class="form-control"
                                    value="<?= $tarea['fecha_entrega']; ?>">
                            </div>
                        </div>
                        <div class="col-md">
                            <div class="mb-3">
                                <label>Archivo actual:</label>
                                <?php if (!empty($tarea) && !empty($tarea['archivo_nombre'])): ?>
                                    <a href="descargar_guia.php?id=<?= $tarea['id_tarea'] ?>">
                                        Descargar archivo (<?= $tarea['archivo_nombre']; ?>)
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
                            <div class="col-12">
                                <?php echo $tareaControlador->botonesAccionTarea($tarea['id_tarea'], $rol, $tarea['estado'], null, $id_proyectos); ?>
                            </div>
                        </div>
                    </div>
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
