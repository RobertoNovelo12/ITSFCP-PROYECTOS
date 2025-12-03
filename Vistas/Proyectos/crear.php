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
                                <option value=''>Seleccione una temática</option>
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
                    <div class="col-md">
                        <div class="mb-3">
                            <label for="select2" class="form-label">Subtemática</label>
                            <select class="form-select" name="Subtematica" id="select2" aria-label="Default select example" disabled required>

                            </select>
                        </div>
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
                            <?php foreach ($periodo as $pe): ?>
                                <input type="text" disabled class="form-control" aria-describedby="Periodo" value="<?php echo ($pe['periodo'] . " - " . $pe['estado']) ?>">
                            <?php endforeach ?>
                        </div>
                    </div>
                </div>
                <div class="row mb-1">
                    <div class="col-md">
                        <div class="mb-3">
                            <label for="InputFormLimpiar8" class="form-label">Fecha inicio</label>
                            <?php foreach ($periodo as $pe): ?>
                                <input type="date" class="form-control" name="FechaInicio" id="InputFormLimpiar8" aria-describedby="FechaInicio" min="<?php echo $pe['FechaInicio'] ?>" max="<?php echo $pe['FechaFinal'] ?>" required>
                            <?php endforeach ?>
                        </div>
                    </div>
                    <div class="col-md">
                        <div class="mb-3">
                            <label for="InputFormLimpiar9" class="form-label">Fecha final</label>
                            <?php foreach ($periodo as $pe): ?>
                                <input type="date" class="form-control" name="FechaFinal" id="InputFormLimpiar9" aria-describedby="FechaFinal" min="<?php echo $pe['FechaInicio'] ?>" max="<?php echo $pe['FechaFinal'] ?>" required>
                            <?php endforeach ?>
                        </div>
                    </div>
                </div>
                <div class="row mb-1">
                    <div class="col-12 text-center">
                        <button type="submit" class="btn btn-primary">Enviar solicitud de proyecto</button>
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
                <h1 class="modal-title fs-5" id="staticBackdropLabel">Proyecto creado y enviado la solicitud correctamente </h1>
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
<?php if (isset($_GET['msg']) && $_GET['msg'] == 'creado'): ?>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            new bootstrap.Modal(document.getElementById('mensaje')).show();
        });
    </script>
<?php endif; ?>

<script>
    document.getElementById("select1").addEventListener("change", function() {
        const id = this.value;
        console.log("ID seleccionado:", id);
        console.log("URL FETCH:", "/ITSFCP-PROYECTOS/Ajax/subtematicas.php?tematica=" + id);

        fetch("/ITSFCP-PROYECTOS/Ajax/subtematicas.php?tematica=" + id)

            .then(response => response.json())
            .then(data => {
                console.log("Subtemas:", data);

                let select2 = document.getElementById("select2");
                select2.innerHTML = "";

                data.forEach(item => {
                    let option = document.createElement("option");
                    option.value = item.id_subtematica;
                    option.textContent = item.nombre_subtematica;
                    select2.appendChild(option);
                });
            })
            .catch(error => console.error("Error en fetch:", error));
    });
</script>
