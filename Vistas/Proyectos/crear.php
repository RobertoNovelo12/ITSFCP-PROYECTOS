<?php
if (!isset($_SESSION)) session_start();

require_once '../../Controladores/proyectoControlador.php';

$rol = $_SESSION['rol'];
$id = $_SESSION['id_usuario'];
$action = $_POST['action'] ?? null;

$proyectoControlador = new ProyectoControlador();
$tematica  = $proyectoControlador->tematica();
$periodo   = $proyectoControlador->obtenerperiodo();

if ($action == 'registrarProyecto') {
    $proyectoControlador->registrarProyecto($_POST, $id, $rol);
}

$titulo = "Crear Proyecto";
$necesitaQuill = false;
$bodyClass = "";


$contenido = '
<div class="container-main">
    <div class="main-content-index">

        <div class="row mb-1">
            <div class="col-6"><h3>Crear Proyecto</h3></div>
            <div class="col-6 text-end">
                <a href="tabla.php" class="btn btn-danger">Regresar</a>
            </div>
        </div>

        <form method="POST" id="formProyecto" action="/ITSFCP-PROYECTOS/Vistas/Proyectos/crear.php">
            <input type="hidden" name="action" value="registrarProyecto">

            <div class="row mb-1">
                <h5>Información de proyectos</h5>

                <div class="mb-3">
                    <label class="form-label">Nombre del proyecto</label>
                    <input type="text" class="form-control" name="NombreProyecto" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Descripción breve</label>
                    <textarea class="form-control" name="Descripcion" rows="3" required></textarea>
                </div>

                <div class="mb-3">
                    <label class="form-label">Objetivos</label>
                    <textarea class="form-control" name="Objetivos" rows="3" required></textarea>
                </div>

                <div class="mb-3">
                    <label class="form-label">Pre-requisitos</label>
                    <textarea class="form-control" name="Pre_requisitos" rows="3" required></textarea>
                </div>

                <div class="mb-3">
                    <label class="form-label">Requisitos</label>
                    <textarea class="form-control" name="Requisitos" rows="3" required></textarea>
                </div>
            </div>

            <div class="row mb-1">
                <div class="col-md">
                    <label class="form-label">Cantidad alumnos permitidos</label>
                    <input type="number" class="form-control" name="AlumnosCantidad" min="1" max="3" required>
                </div>

                <div class="col-md">
                    <label class="form-label">Temática</label>
                    <select class="form-select" name="Tematica" id="select1">
                        <option value="">Seleccione una temática</option>';

foreach ($tematica as $tema) {
    $contenido .= '<option value="'.$tema['id_tematica'].'">'.$tema['nombre_tematica'].'</option>';
}

$contenido .= '
                    </select>
                </div>
            </div>

            <div class="row mb-1">
                <div class="col-md">
                    <label class="form-label">Modalidad</label>
                    <select class="form-select" name="Modalidad">
                        <option value="mixto">Mixta</option>
                        <option value="virtual">Virtual</option>
                        <option value="fisico">Físico</option>
                    </select>
                </div>

                <div class="col-md">
                    <label class="form-label">Subtemática</label>
                    <select class="form-select" name="Subtematica" id="select2"></select>
                </div>
            </div>

            <div class="row mb-1">
                <div class="col-md">
                    <label class="form-label">Presupuesto</label>
                    <input type="number" class="form-control" name="Presupuesto" min="0" required>
                </div>

                <div class="col-md">
                    <label class="form-label">Periodo</label>';

foreach ($periodo as $pe) {
    $contenido .= '<input type="text" disabled class="form-control" value="'.$pe['periodo'].' - '.$pe['estado'].'">';
}

$contenido .= '
                </div>
            </div>

            <div class="row mb-1">
                <div class="col-md">
                    <label class="form-label">Fecha inicio</label>';

foreach ($periodo as $pe) {
    $contenido .= '
                    <input type="date" class="form-control" 
                        name="FechaInicio"
                        min="'.$pe['FechaInicio'].'" 
                        max="'.$pe['FechaFinal'].'" 
                        required>';
}

$contenido .= '
                </div>

                <div class="col-md">
                    <label class="form-label">Fecha final</label>';

foreach ($periodo as $pe) {
    $contenido .= '
                    <input type="date" class="form-control" 
                        name="FechaFinal"
                        min="'.$pe['FechaInicio'].'" 
                        max="'.$pe['FechaFinal'].'" 
                        required>';
}

$contenido .= '
                </div>
            </div>

            <div class="text-center">
                <button class="btn btn-primary">Enviar proyecto</button>
            </div>

        </form>

    </div>
</div>

<script>
document.getElementById("select1").addEventListener("change", function() {
    const id = this.value;

    fetch("/ITSFCP-PROYECTOS/Ajax/subtematicas.php?tematica=" + id)
    .then(r => r.json())
    .then(data => {
        const select2 = document.getElementById("select2");
        select2.innerHTML = "";

        data.forEach(item => {
            const opt = document.createElement("option");
            opt.value = item.id_subtematica;
            opt.textContent = item.nombre_subtematica;
            select2.appendChild(opt);
        });
    });
});
</script>
';

include __DIR__ . "/../../layout.php";
