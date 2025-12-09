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

require_once '../../Controladores/proyectoControlador.php';
$proyectoControlador = new ProyectoControlador();

//Datos necesarios
$tematica = $proyectoControlador->tematica();
$periodo = $proyectoControlador->obtenerperiodo();
$proyecto = $proyectoControlador->datosproyecto($id_proyecto); // Para rellenar

if ($action == 'editarProyecto') {
    $proyectoControlador->editarProyecto($_POST, $id, $rol);
}
// ======================
// GENERAR CONTENIDO
// ======================
ob_start();
include __DIR__ . '/../../mensaje.php';
?>
<div class="container-fluid py-4">
    <div class="row mb-3 align-items-center">
        <div class="row mb-1">
            <div class="col-6">
                <h3>Editar Proyecto</h3>
            </div>
            <div class="col-12 col-md-6 text-md-end text-center mb-2 mb-md-0">
                <a href="tabla.php" class="btn btn-danger w-100 w-md-auto">Regresar</a>
            </div>

            <?php foreach ($proyecto as $p): ?>
                <form method="POST" id="formProyecto" action="/ITSFCP-PROYECTOS/Vistas/Proyectos/editar.php">
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
                                <label class="form-label">Temática</label>
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

                        <div class="col-md">
                            <div class="mb-3">
                                <label class="form-label">Subtemática</label>
                                <select class="form-select" name="Subtematica" id="select2">
                                    <option selected><?php echo $p['subtematica']; ?></option>
                                </select>
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
                                <?php foreach ($periodo as $pe): ?>
                                    <input type="text" class="form-control" disabled
                                        value="<?php echo $pe['periodo'] . ' - ' . $pe['estado']; ?>">
                                <?php endforeach ?>
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
            <?php endforeach; ?>
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
$titulo = "Editar proyecto";
$bodyClass = "proyectos-page";

include __DIR__ . '/../../layout.php';
?>
<script>
    document.getElementById("select1").addEventListener("change", function() {
        const id = this.value;
        fetch("/ITSFCP-PROYECTOS/Ajax/subtematicas.php?tematica=" + id)
            .then(r => r.json())
            .then(data => {
                let select2 = document.getElementById("select2");
                select2.innerHTML = "";
                data.forEach(item => {
                    let opt = document.createElement("option");
                    opt.value = item.id_subtematica;
                    opt.textContent = item.nombre_subtematica;
                    select2.appendChild(opt);
                });
            });
    });
</script>
