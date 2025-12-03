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

require_once '../../Controladores/tareasControlador.php';
$tareaControlador = new TareaControlador();

$tarea = $tareaControlador->mostrarEditarTarea($id_tarea, $rol);
// ======================
// GENERAR CONTENIDO
// ======================
ob_start();
?>
<div class="container-fluid py-4">

    <div class="row mb-3">
        <div class="col-6">
            <h3>Editar Tarea</h3>
        </div>
        <div class="col-6 text-end">
            <a href="tabla.php" class="btn btn-danger">Regresar</a>
        </div>
    </div>

    <!-- DATOS DE LA TAREA -->
    <div class="card p-3 mb-3">

        <h4>Datos de la tarea</h4>

        <div class="mb-3">
            <label>Descripción:</label>
            <textarea class="form-control" disabled><?= htmlspecialchars($tarea['tarea']['descripcion']) ?></textarea>
        </div>

        <div class="mb-3">
            <label>Instrucciones:</label>
            <textarea class="form-control" disabled><?= htmlspecialchars($tarea['tarea']['instrucciones']) ?></textarea>
        </div>

    </div>

    <!-- SEGUIMIENTO -->
    <div class="card p-3">
        <h4>Seguimiento</h4>

        <div class="row mb-3">
            <div class="col-md">
                <label>Fecha entrega:</label>
                <input type="date" class="form-control"
                       value="<?= $tarea['seguimiento']['fecha_entrega'] ?>" disabled>
            </div>

            <div class="col-md">
                <label>Archivo actual:</label><br>

                <?php if ($tarea['seguimiento']['archivo_nombre']): ?>
                    <a href="descargar.php?id=<?= $tarea['tarea']['id_avances'] ?>">
                        Descargar archivo (<?= $tarea['seguimiento']['archivo_nombre'] ?>)
                    </a>
                <?php else: ?>
                    <p>No hay archivo cargado.</p>
                <?php endif; ?>

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
