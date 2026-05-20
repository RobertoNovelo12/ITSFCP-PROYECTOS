<?php
/*Proyectos/editar.php - Página para editar proyecto */

ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();

if (!isset($_SESSION['id_usuario'])) {
    header("Location: /ITSFCP-PROYECTOS/index.php");
    exit;
}

$rol        = strtolower($_SESSION['rol'] ?? '');
$id = $_SESSION['id_usuario'];

$id_proyecto = $_GET["id_proyectos"] ?? null;
$action = $_POST['action'] ?? null;

//Solo investigador puede acceder
if (!in_array($rol, ['investigador', 'profesor'], true)) {
    header("Location: /ITSFCP-PROYECTOS/Vistas/Principal/index.php");
    exit;
}

require_once '../../Controladores/proyectoControlador.php';

$proyectoControlador = new ProyectoControlador();

$periodoActualProyectos = $proyectoControlador->periodoactual();
$hoy = date('Y-m-d');
$puedeEditar = ($hoy >= $periodoActualProyectos['fecha_inicio_proyectos']
    && $hoy <= $periodoActualProyectos['fecha_fin_proyectos']);

if (!$puedeEditar) {
    header('Location: /ITSFCP-PROYECTOS/Vistas/Proyectos/index.php');
    exit;
}


// DATOS
$tematica = $proyectoControlador->tematica();
$subtematicasProyecto = $proyectoControlador->subtematicasProyecto($id_proyecto);
$periodo = $proyectoControlador->obtenerperiodo();
$estudiantes = $proyectoControlador->estudiantes($id_proyecto);
$p = $proyectoControlador->datosproyecto($id_proyecto);

// ACCIONES
if ($action == 'editarProyecto') {
    $proyectoControlador->editarProyecto($_POST, $id, $rol);
}

if ($action == 'baja') {
    $proyectoControlador->accionEstudiante($_POST);
}

if ($action == 'reactivar') {
    $proyectoControlador->accionEstudiante($_POST);
}

// CONTENIDO
ob_start();
include __DIR__ . '/../../mensaje.php';
include __DIR__ . '/../../error.php';
?>

<div class="container-fluid py-4" style="max-width:95%;">

    <!-- ENCABEZADO -->
    <div class="row mb-4 align-items-center">
        <div class="col-md-6">
            <h3 class="fw-bold mb-0">Editar proyecto</h3>
        </div>
        <div class="col-md-6 text-md-end">
            <a href="index.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Regresar
            </a>
        </div>
    </div>

    <!-- FORMULARIO PROYECTO -->
    <form method="POST">
        <input type="hidden" name="action" value="editarProyecto">
        <input type="hidden" name="id_proyectos" value="<?= $p['id_proyectos']; ?>">

        <h5>Información del proyecto <span class="badge text-bg-<?php echo $proyectoControlador->EstiloEstado($p['estado_proyecto']); ?>"><?= htmlspecialchars($proyecto['estado_proyecto'] ?? '-', ENT_QUOTES, 'UTF-8') ?></span></h5>

        <div class="mb-3">
            <label>Nombre del proyecto</label>
            <input type="text" class="form-control" name="NombreProyecto"
                value="<?= $p['titulo']; ?>" required>
        </div>

        <div class="mb-3">
            <label>Descripción</label>
            <textarea class="form-control" name="Descripcion" rows="4"><?= $p['descripcion']; ?></textarea>
        </div>

        <div class="mb-3">
            <label>Objetivos</label>
            <textarea class="form-control" name="Objetivos" rows="4"><?= $p['objetivo']; ?></textarea>
        </div>

        <div class="mb-3">
            <label>Pre-requisitos</label>
            <textarea class="form-control" name="Pre_requisitos"><?= $p['pre_requisitos']; ?></textarea>
        </div>

        <div class="mb-3">
            <label>Requisitos</label>
            <textarea class="form-control" name="Requisitos"><?= $p['requisitos']; ?></textarea>
        </div>

        <div class="row">
            <div class="col-md">
                <label>Cantidad alumnos</label>
                <input type="number" class="form-control" name="AlumnosCantidad"
                    value="<?= $p['cantidad_estudiante']; ?>">
            </div>

            <div class="col-md">
                <label>Temática</label>
                <select class="form-select" name="Tematica" id="select1">
                    <?php foreach ($tematica as $tema): ?>
                        <option value="<?= $tema['id_tematica']; ?>"
                            <?= ($tema['nombre_tematica'] == $p['tematica']) ? 'selected' : ''; ?>>
                            <?= $tema['nombre_tematica']; ?>
                        </option>
                    <?php endforeach ?>
                </select>
            </div>
        </div>

        <div class="row mt-2">
            <div class="col-md">
                <label>Modalidad</label>
                <select class="form-select" name="Modalidad">
                    <option value="mixto" <?= ($p['modalidad'] == "mixto") ? "selected" : "" ?>>Mixto</option>
                    <option value="virtual" <?= ($p['modalidad'] == "virtual") ? "selected" : "" ?>>Virtual</option>
                    <option value="fisico" <?= ($p['modalidad'] == "fisico") ? "selected" : "" ?>>Físico</option>
                </select>
            </div>

            <div class="col-md">
                <label>Subtemáticas</label>
                <select name="subtematicas[]" id="select2" class="form-select" multiple></select>
            </div>
        </div>

        <div class="row mt-2">
            <div class="col-md">
                <label>Presupuesto</label>
                <input type="number" class="form-control" name="Presupuesto"
                    value="<?= $p['presupuesto']; ?>">
            </div>

            <div class="col-md">
                <label>Periodo</label>
                <input type="text" class="form-control" disabled
                    value="<?= $periodo['periodo'] . ' - ' . $periodo['estado']; ?>">
            </div>
        </div>

        <div class="row mt-2">
            <div class="col-md">
                <label>Fecha inicio</label>
                <input type="date" name="FechaInicio" class="form-control"
                    value="<?= $p['fecha_inicio']; ?>">
            </div>

            <div class="col-md">
                <label>Fecha fin</label>
                <input type="date" name="FechaFinal" class="form-control"
                    value="<?= $p['fecha_fin']; ?>">
            </div>
        </div>

        <div class="text-center mt-3">
            <button class="btn btn-primary">Guardar cambios</button>
        </div>
    </form>

    <!-- FORMULARIO OCULTO PARA ACCIONES -->
    <form id="formAccion" method="POST">
        <input type="hidden" name="action" id="action">
        <input type="hidden" name="id_estudiante" id="id_estudiante">
        <input type="hidden" name="id_proyecto" value="<?= $id_proyecto ?>">
    </form>

    <!-- ESTUDIANTES -->
    <?php if (in_array($rol, ["supervisor", "profesor", "investigador"])): ?>

        <h5 class="mt-5">Estudiantes involucrados</h5>

        <div class="table-responsive">
            <table class="table table-striped text-center">

                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre</th>
                        <th>Carrera</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($estudiantes as $alumno): ?>
                        <tr>

                            <td><?= $alumno['id_usuarios'] ?></td>

                            <td>
                                <?= $alumno['nombre'] . " " . $alumno['apellido_paterno'] ?>
                            </td>

                            <td><?= $alumno['carrera'] ?></td>

                            <td>
                                <?php if ($alumno['estado'] == "activo"): ?>

                                    <span class="badge bg-success">Activo</span>

                                <?php else: ?>

                                    <span class="badge bg-danger">
                                        Baja <i class="bi bi-info-circle"
                                            data-bs-toggle="tooltip"
                                            title="<?= htmlspecialchars($alumno['motivo']) ?>">
                                        </i>
                                    </span>

                                <?php endif; ?>
                            </td>

                            <td>
                                <?= $proyectoControlador->botonesAccionEditarEstudiante(
                                    $alumno['id_usuarios'],
                                    $rol,
                                    $alumno['estado'],
                                    $id_proyecto,
                                    $p['estado_proyecto']
                                ) ?>
                                <a href="historial_estudiante.php?id_proyecto=<?= $id_proyecto ?>&id_usuario=<?= $alumno['id_usuarios'] ?>"
                                    class="btn btn-info btn-sm">
                                    Historial
                                </a>
                            </td>

                        </tr>
                    <?php endforeach; ?>
                </tbody>

            </table>
        </div>

    <?php endif; ?>

</div>

<!-- JS -->
<script>
    const subtematicasProyecto = <?= json_encode($subtematicasProyecto ?? []); ?>;

    document.addEventListener("DOMContentLoaded", function() {

        const selectTematica = document.getElementById("select1");
        const selectSub = document.getElementById("select2");

        const subIds = subtematicasProyecto.map(s => Number(s.id_subtematica));

        function cargarSub() {
            fetch("/ITSFCP-PROYECTOS/Ajax/subtematicas.php?tematica=" + selectTematica.value)
                .then(r => r.json())
                .then(data => {
                    selectSub.innerHTML = "";
                    data.forEach(item => {
                        let opt = document.createElement("option");
                        opt.value = item.id_subtematica;
                        opt.textContent = item.nombre_subtematica;

                        if (subIds.includes(Number(item.id_subtematica))) {
                            opt.selected = true;
                        }

                        selectSub.appendChild(opt);
                    });
                });
        }

        if (selectTematica.value) cargarSub();
        selectTematica.addEventListener("change", cargarSub);

    });

    // BOTONES
    document.addEventListener("click", function(e) {
        if (e.target.dataset.accion) {
            document.getElementById("action").value = e.target.dataset.accion;
            document.getElementById("id_estudiante").value = e.target.dataset.id;
            document.getElementById("formAccion").submit();
        }
    });
    document.addEventListener("DOMContentLoaded", function() {
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function(tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    });
</script>

<?php
$contenido = ob_get_clean();
$titulo = "Editar proyecto";
include __DIR__ . '/../../layout.php';
?>