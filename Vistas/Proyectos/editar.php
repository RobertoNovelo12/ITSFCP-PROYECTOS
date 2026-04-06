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

$id_proyecto = $_GET["id_proyectos"] ?? null;
$action = $_POST['action'] ?? null;
/* CONTROLADOR */
require_once '../../Controladores/proyectoControlador.php';

$proyectoControlador = new ProyectoControlador();

//Datos necesarios
$tematica = $proyectoControlador->tematica();

$subtematicasProyecto = $proyectoControlador->subtematicasProyecto($id_proyecto);

$periodo = $proyectoControlador->obtenerperiodo();

$p = $proyectoControlador->datosproyecto($id_proyecto); // Para rellenar 

if ($action == 'editarProyecto') {
    $proyectoControlador->editarProyecto($_POST, $id, $rol);
}
// GENERAR CONTENIDO
ob_start();
include __DIR__ . '/../../mensaje.php';
include __DIR__ . '/../../error.php';
?>
<div class="container-fluid py-4">
    <!-- ENCABEZADO -->
    <div class="row mb-4 align-items-center">

        <div class="col-md-6">
            <h3 class="fw-bold mb-0">Editar periodo</h3>
        </div>

        <div class="col-md-6 text-md-end">
            <a href="tabla.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Regresar
            </a>
        </div>

    </div>
    <div class="row mb-3 align-items-center">
        <div class="row mb-1">
            <form method="POST" id="formProyecto" action="">
                <input type="hidden" id="input_hidden" name="action" value="editarProyecto">

                <div class="row mb-1">
                    <h5>Información del proyecto</h5>

                    <div class="mb-3">
                        <label class="form-label">Nombre del proyecto</label>
                        <input type="text" class="form-control" name="NombreProyecto"
                            value="<?php echo $p['titulo']; ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Descripción breve</label>
                        <textarea class="form-control" name="Descripcion" rows="6" required><?php echo $p['descripcion']; ?></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Objetivos</label>
                        <textarea class="form-control" name="Objetivos" rows="6" required><?php echo $p['objetivo']; ?></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Pre-requisitos</label>
                        <textarea class="form-control" name="Pre_requisitos" rows="3" required><?php echo $p['pre_requisitos']; ?></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Requisitos</label>
                        <textarea class="form-control" name="Requisitos" rows="3" required><?php echo $p['requisitos']; ?></textarea>
                    </div>

                </div>

                <div class="row mb-1">
                    <div class="col-md">
                        <div class="mb-3">
                            <label class="form-label">Cantidad alumnos permitidos</label>
                            <input type="number" class="form-control" name="AlumnosCantidad"
                                min="0" max="3" value="<?php echo $p['cantidad_estudiante']; ?>" required>
                        </div>
                    </div>

                    <div class="col-md">
                        <div class="mb-3">
                            <label class="form-label" for="select1">Temática</label>
                            <select class="form-select" name="Tematica" id="select1">
                                <option value="">Seleccione una temática</option>
                                <?php foreach ($tematica as $tema): ?>
                                    <option value="<?php echo $tema['id_tematica']; ?>"
                                        <?php echo ($tema['nombre_tematica'] == $p['tematica']) ? 'selected' : ''; ?>>
                                        <?php echo $tema['nombre_tematica']; ?>
                                    </option>
                                <?php endforeach ?>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="row mb-1">
                    <div class="col-md">
                        <div class="mb-3">
                            <label class="form-label">Modalidad</label>
                            <select class="form-select" name="Modalidad">
                                <option value="mixto" <?php echo ($p['modalidad'] == "mixto") ? "selected" : ""; ?>>Mixta</option>
                                <option value="virtual" <?php echo ($p['modalidad'] == "virtual") ? "selected" : ""; ?>>Virtual</option>
                                <option value="fisico" <?php echo ($p['modalidad'] == "fisico") ? "selected" : ""; ?>>Físico</option>
                            </select>
                        </div>
                    </div>
                    <!-- Subtemáticas (selección múltiple) -->
                    <div class="col-md">
                        <div class="mb-3">
                            <label class="form-label" for="select2">Subtemáticas</label>
                            <select name="subtematicas[]" id="select2" class="form-select" multiple required>

                            </select>
                            <small class="text-muted">
                                Mantén presionada la tecla Ctrl (o Cmd) para seleccionar varias
                            </small>
                        </div>
                    </div>
                </div>

                <div class="row mb-1">
                    <div class="col-md">
                        <div class="mb-3">
                            <label class="form-label">Presupuesto</label>
                            <input type="number" class="form-control" name="Presupuesto"
                                value="<?php echo $p['presupuesto']; ?>" required>
                        </div>
                    </div>

                    <div class="col-md">
                        <div class="mb-3">
                            <label class="form-label">Periodo</label>
                                <input type="text" class="form-control" disabled
                                    value="<?php echo $periodo['periodo'] . ' - ' . $periodo['estado']; ?>">
                        </div>
                    </div>
                </div>

                <div class="row mb-1">
                    <div class="col-md">
                        <label class="form-label">Fecha inicio</label>
                        <input type="date" name="FechaInicio" class="form-control"
                            value="<?php echo $p['fecha_inicio']; ?>" required>
                    </div>
                    <div class="col-md">
                        <label class="form-label">Fecha final</label>
                        <input type="date" name="FechaFinal" class="form-control"
                            value="<?php echo $p['fecha_fin']; ?>" required>
                    </div>
                </div>

                <div class="row mb-1">
                    <div class="col-12 text-center">
                        <input type="hidden" name="id_proyectos" value="<?php echo $p['id_proyectos']; ?>">
                        <button type="submit" class="btn btn-primary">Guardar cambios</button>
                    </div>
                </div>

            </form>
        </div>
    </div>
</div>
<script>
    const subtematicasProyecto = <?= json_encode($subtematicasProyecto ?? []); ?>;

    document.addEventListener("DOMContentLoaded", function() {

        const selectTematica = document.getElementById("select1");
        const selectSub = document.getElementById("select2");

        //  IDs ORIGINALES del proyecto (NO se modifican)
        const subProyectoIds = Array.isArray(subtematicasProyecto) ?
            subtematicasProyecto.map(s => Number(s.id_subtematica)) : [];

        function cargarSubtematicas() {

            const idTematica = selectTematica.value;
            selectSub.innerHTML = "";

            if (!idTematica) return;

            fetch("/ITSFCP-PROYECTOS/Ajax/subtematicas.php?tematica=" + idTematica)
                .then(r => r.json())
                .then(data => {

                    data.forEach(item => {

                        const opt = document.createElement("option");
                        opt.value = item.id_subtematica;
                        opt.textContent = item.nombre_subtematica;

                        // SOLO marcar si pertenece al proyecto
                        if (subProyectoIds.includes(Number(item.id_subtematica))) {
                            opt.selected = true;
                        }

                        selectSub.appendChild(opt);
                    });
                });
        }

        // Cambio de temática
        selectTematica.addEventListener("change", cargarSubtematicas);

        // Carga inicial
        if (selectTematica.value) {
            cargarSubtematicas();
        }
    });
</script>
<?php
$contenido = ob_get_clean();
$titulo = "Editar proyecto";
$bodyClass = "proyectos-page";

include __DIR__ . '/../../layout.php';
?>