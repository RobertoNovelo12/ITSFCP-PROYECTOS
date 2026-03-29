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
$action = $_POST['action'] ?? null;

//Se llama al controlador

require_once '..\..\Controladores\proyectoControlador.php';

$proyectoControlador = new ProyectoControlador();


$tematica = $proyectoControlador->tematica();
$periodo = $proyectoControlador->obtenerperiodo();
if ($action == 'registrarProyecto') {
    $proyectoControlador->registrarProyecto($_POST, $id, $rol);
}
ob_start();
include __DIR__ . '/../../mensaje.php';
?>
<div class="container-fluid py-4">
    <div class="row mb-3 align-items-center">
        <div class="row mb-1">
            <div class="col-6">
                <h3>Crear Proyecto</h3>
            </div>
            <div class="col-6 col-md-6 text-md-end mb-2 mb-md-0 text-end">
                <a href="tabla.php" class="btn btn-danger w-100 w-md-auto">Regresar</a>
            </div>
            <form method="POST" id="formProyecto" action="/ITSFCP-PROYECTOS/Vistas/Proyectos/crear.php">
                <input type="hidden" id="input_hidden" name="action" value="registrarProyecto">
                <div class="row mb-1">
                    <h5>Información de proyectos</h5>
                    <div class="mb-3">
                        <label for="exampleFormControlInput1" class="form-label">Nombre del proyecto</label>
                        <input type="text" class="form-control" name="NombreProyecto" id="InputFormLimpiar1" required>
                    </div>
                    <div class="mb-3">
                        <label for="exampleFormControlTextarea1" class="form-label">Descripcion breve</label>
                        <textarea class="form-control" name="Descripcion" id="InputFormLimpiar2" rows="3" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="exampleFormControlTextarea1" class="form-label">Objetivos</label>
                        <textarea class="form-control" name="Objetivos" id="InputFormLimpiar3" rows="3" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="exampleFormControlTextarea1" class="form-label">Pre-requisitos</label>
                        <textarea class="form-control" name="Pre_requisitos" id="InputFormLimpiar4" rows="3" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="exampleFormControlTextarea1" class="form-label">Requisitos</label>
                        <textarea class="form-control" name="Requisitos" id="InputFormLimpiar5" rows="3" required></textarea>
                    </div>
                </div>
                <div class="row mb-1">
                    <div class="col-md">
                        <div class="mb-3">
                            <label for="InputFormLimpiar6" class="form-label">Cantidad alumnos permitidos</label>
                            <input type="number" class="form-control" name="AlumnosCantidad" id="InputFormLimpiar6" aria-describedby="Cantidad alumnos" min="0" max="3" required>
                        </div>
                    </div>
                    <div class="col-md">
                        <div class="mb-3">
                            <label for="select1" class="form-label">Temática</label>
                            <select class="form-select" name="Tematica" id="select1" aria-label="Default select example">
                                <?php foreach ($tematica as $tema): ?>
                                    <option value="<?php echo $tema['id_tematica'] ?>"><?php echo $tema['nombre_tematica'] ?></option>
                                <?php endforeach ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="row mb-1">
                    <div class="col-md">
                        <div class="mb-3">
                            <label for="floatingSelectGrid" class="form-label">Modalidad</label>
                            <select class="form-select" id="floatingSelectGrid" name="Modalidad" aria-label="Default select example">
                                <option value="mixto">Mixta</option>
                                <option value="virtual">Virtual</option>
                                <option value="fisico">Físico</option>
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
                    <div class="row mb-1">
                        <div class="col-md">
                            <div class="mb-3">
                                <label for="InputFormLimpiar7" class="form-label">Presupuesto</label>
                                <input type="number" class="form-control" name="Presupuesto" id="InputFormLimpiar7" aria-describedby="Presupuesto" min="0" required>
                            </div>
                        </div>
                        <div class="col-md">
                            <div class="mb-3">
                                <label for="InputFormLimpiar7" class="form-label">Periodo</label>
                                <input type="text" disabled class="form-control" aria-describedby="Periodo" value="<?php echo ($periodo['periodo'] . " - " . $periodo['estado']) ?>">
                            </div>
                        </div>
                    </div>
                    <div class="row mb-1">
                        <div class="col-md">
                            <div class="mb-3">
                                <label for="InputFormLimpiar8" class="form-label">Fecha inicio</label>
                                <input type="date" class="form-control" name="FechaInicio" id="InputFormLimpiar8" aria-describedby="FechaInicio" min="<?php echo $periodo['FechaInicio'] ?>" max="<?php echo $periodo['FechaFinal'] ?>" required>
                            </div>
                        </div>
                        <div class="col-md">
                            <div class="mb-3">
                                <label for="InputFormLimpiar9" class="form-label">Fecha final</label>
                                <input type="date" class="form-control" name="FechaFinal" id="InputFormLimpiar9" aria-describedby="FechaFinal" min="<?php echo $periodo['FechaInicio'] ?>" max="<?= date('Y-m-d', strtotime('+1 year')) ?>" required>
                            </div>
                        </div>
                        <div class="alert alert-warning" role="alert">
                            Los proyectos pueden durar un máximo de 1 año
                        </div>
                    </div>
                    <div class="row mb-1">
                        <div class="col-12 text-center">
                            <?php if ($periodo['estado'] == "Activo") { ?>
                                <button type="submit" class="btn btn-guardar">Enviar solicitud de proyecto</button>
                            <?php } else {
                            ?>
                                <div class="alert alert-danger" role="alert">
                                    No hay periodo activo para crear un proyecto
                                </div>

                            <?php } ?>
                        </div>
                    </div>
            </form>
        </div>
    </div>
</div>
<?php
$contenido = ob_get_clean();
$titulo = "Crear Proyecto";
$bodyClass = "proyectos-page";

include __DIR__ . '/../../layout.php';
?>

<script>
    document.addEventListener("DOMContentLoaded", function() {

        const selectTematica = document.getElementById("select1");
        const selectSub = document.getElementById("select2");

        function cargarSubtematicas() {

            const idTematica = selectTematica.value;
            if (!idTematica) return;

            fetch("/ITSFCP-PROYECTOS/Ajax/subtematicas.php?tematica=" + idTematica)
                .then(r => r.json())
                .then(data => {

                    selectSub.innerHTML = "";

                    data.forEach(item => {

                        const opt = document.createElement("option");
                        opt.value = item.id_subtematica;
                        opt.textContent = item.nombre_subtematica;

                        selectSub.appendChild(opt);
                    });
                });
        }

        // Evento al cambiar temática
        selectTematica.addEventListener("change", cargarSubtematicas);

        // cargar automáticamente al abrir
        if (selectTematica.value) {
            cargarSubtematicas();
        }
    });
</script>