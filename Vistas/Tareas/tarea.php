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
$id_proyecto = $_POST["id_proyectos"]
    ?? $_GET["id_proyectos"]
    ?? null;
$id_asignacion = $_POST["id_asignacion"]
    ?? $_GET["id_asignacion"]
    ?? null;
$id_tarea = $_POST["id_tarea"] ?? $_GET["id_tarea"] ?? null;

if ($id_asignacion == null) {
    die("ERROR: No se recibió id_asignacion");
}
$action = $_POST['action'] ?? $_GET['action'] ?? null;
$tipo = $_POST["tipo"] ?? $_GET['tipo'] ?? null;
require_once '../../Controladores/tareasControlador.php';
$tareaControlador = new TareaControlador();

// DATOS
$datos = $tareaControlador->mostrarTarea($id_asignacion, $rol);
$historialAgrupado = $tareaControlador->info_linea_tiempo($id_asignacion, $id);
//PASAR AL LADO DEL CONTROLADOR
if ($action == 'editar') {
    $tareaControlador->editar($_POST, $rol, $id_proyecto, $id_asignacion, $id);
}


// GENERAR VISTA
ob_start();

?>

<div class="container-fluid py-4">
    <?php include __DIR__ . '/../../mensaje.php'; ?>
    <div class="row mb-3 align-items-center">

        <div class="row mb-1">
            <div class="col-6">
                <h3>Revisar Tarea</h3>
            </div>
            <div class="col-6 text-end">
                <?php if ($rol == "investigador" || $rol== "supervisor") { ?>
                    <a href="lista_tareas.php?id_tarea=<?= $id_tarea; ?>&id_proyectos=<?= $id_proyecto; ?>" class="btn btn-danger">Regresar</a>
                <?php } elseif ($rol == "estudiante") { ?>
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
                <input type="hidden" name="action" value="editar">
                <input type="hidden" name="id_tarea" value="<?= $datos['id_tarea']; ?>">
                <input type="hidden" name="id_proyectos" value="<?= $datos['id_proyectos']; ?>">
                <input type="hidden" name="id_asignacion" value="<?= $datos['id_asignacion']; ?>">

                <?php echo $tareaControlador->tareas($datos['tipo_tarea'], $rol, $datos) ?? ""; ?> <!--Para generar las tareas según el tipos-->
            </div>

            <div class="row mb-1">
                <div class="col-12">
                    <?php echo $tareaControlador->botonesAccionTarea($datos['id_tarea'], $rol, $datos['estado'], $datos['id_asignacion']); ?>
                </div>
            </div>
        </form>
        <div class="row mb-1">
            <div class="mb-3">
                <details>
                    <summary>Línea de tiempo de acciones</summary>

                    <ul class="timeline">

                    <?php if (empty($historialAgrupado)): ?>

        <div class="alert alert-info text-center shadow-sm">
            <i class="bi bi-info-circle"></i><br><br>
            Este estudiante no tiene historial registrado en esta tarea.
        </div>

    <?php else: ?>

                        <?php foreach ($historialAgrupado as $fecha => $items): ?>

                            <?php foreach ($items as $item): ?>

                                <li>

                                    <div class="timeline-content">

                                        <div class="timeline-header">
                                            <span class="titulo 
                                <?= ($item['esEstudiante'] == 1) ? 'estudiante' : 'investigador' ?>">

                                                <?= ($item['esEstudiante'] == 1 ? 'Estudiante' : 'Investigador') ?>
                                                - <?= $item['estado'] ?>

                                            </span>

                                            <span class="fecha">
                                                <?= $fecha ?> - <?= date("H:i", strtotime($item['fecha'])) ?>
                                            </span>
                                        </div>

                                        <p class="comentario">
                                            <?= $item['comentario'] ?>
                                        </p>

                                    </div>

                                </li>

                            <?php endforeach; ?>

                        <?php endforeach; ?>

                          <?php endif; ?>

                    </ul>
                </details>
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