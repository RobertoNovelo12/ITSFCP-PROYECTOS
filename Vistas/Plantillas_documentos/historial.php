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
                <h3>Historial de plantilla de documento</h3>
            </div>
            <div class="col-6 text-end">
                    <a href="tabla.php?id_plantilla=<?= $id_tarea; ?>" class="btn btn-danger">Regresar</a>
            </div>
        </div>

        <div class="row mb-1">
            <div class="mb-3">
                <details>
                    <summary>Línea de tiempo de acciones</summary>

                    <ul class="timeline">

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