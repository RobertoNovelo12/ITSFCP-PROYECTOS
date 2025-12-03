<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

if (!isset($_SESSION['id_usuario'])) {
    header("Location: /ITSFCP-PROYECTOS/index.php");
    exit;
}

$rol = strtolower($_SESSION['rol'] ?? '');
$id_usuario = intval($_SESSION['id_usuario']);
//ACCIONES
$action = isset($_GET['action']) ? $_GET['action'] : 'index_Lista';
$id_tarea = $_GET['id_tarea'] ?? null;
$id_proyectos = $_GET['id_proyectos'] ?? null;
//LLAMADA AL CONTROLADOR
include "../../Controladores/tareasControlador.php";

$tareaControlador = new TareaControlador();
//Si no existe el controlador que mande un mensaje
if (!method_exists($tareaControlador, $action)) {
    die("Error: La acción '$action' no existe en el controlador.");
}
//Se ejecuta la acción del controlador

if ($_SERVER['REQUEST_METHOD'] == 'GET' && $action == 'actualizarestado') {
    $tareaControlador->actualizarestado($id_tarea, $rol, $_GET['tipo']);
}
//EJECUTAR ACCION
$tarea = $tareaControlador->$action($id_tarea, $rol);
if (!is_array($tarea)) {
    die("Error: La acción '$action' no devolvió un array válido.");
}
$encabezados = $tareaControlador->encabezadosLista($rol);
// ======================
// GENERAR CONTENIDO
// ======================
ob_start();
?>

<script>
    //MOSTRAR TOOLTIP, QUE ES UN TEXTO AL SOBREPONER MOUSE EN BOTÓN
    document.addEventListener('DOMContentLoaded', function() {
        const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
        const tooltipList = [...tooltipTriggerList].map(t => new bootstrap.Tooltip(t));
    });
</script>


<div class="container-fluid py-4">
    <div class="row mb-3 align-items-center">
        <div class="row  mb-3">
        <div class="col-md-6">
            <h3 class="mb-0">Lista de Tareas</h3>
        </div>
            <div class="col-12 col-md-6 text-md-end text-center mb-2 mb-md-0">
                <a href="tabla.php?id_proyectos=<?php echo $id_proyectos; ?>" class="btn btn-danger w-100 w-md-auto">Regresar</a>
            </div>
        </div>
        <div class="row mb-3">
            <div class="col-12">
                <div class="table-responsive">
                    <table class="table table-light" id="tabla_informacion">
                        <thead class="text-center">
                            <tr>
                                <?php
                                foreach ($encabezados as $encabezado) {
                                    echo "<th scope='col'>{$encabezado}</th>";
                                }
                                ?>
                            </tr>
                        </thead>
                        <tbody class="text-center">
                            <?php 
                             if ($rol == "investigador" || $rol == "supervisor") {
                                foreach ($tarea as $tar) {
                                    echo "<tr>";
                                    echo "<th scope='row'>{$tar['id_asignacion']}</th>";
                                    echo "<th scope='row'>{$tar['estudiante']}</th>";
                                    echo "<td>{$tar['estados_tarea']}</td>";
                                    echo "<td>{$tar['fecha_entrega']}</td>";
                                    echo "<td>{$tareaControlador->botonesAccionLista($tar['id_tarea'],$rol,$tar['estados_tarea'],$tar['tipo'],$id_proyectos)}</td>";
                                    echo "</tr>";
                                }
                            } ?>
                        </tbody>
                    </table>
                </div>
                <?php foreach ($tarea as $tar): ?>
                    <div class="card mb-3" id="tarjeta_móvil" style="width: 18rem;">
                        <div class="card-body">
                            <h5 class="card-title"><?php echo $tar['id_asignacion'] ?></h5>
                            <p class="card-text"><?php echo $tar['estudiante'] ?></p>
                        </div>

                        <ul class="list-group list-group-flush">
                            <li class="list-group-item">
                                <div class="row">
                                        <div class="col-12">
                                            <label>Estado</label>
                                            <p class="card-text"><?php echo ($tar['estados_tarea']) ?></p>
                                        </div>
                                </div>
                            </li>
                            <li class="list-group-item">
                                <div class="row">
                                    <div class="col-12">
                                        <label>Fecha Entrega</label>
                                        <p class="card-text"><?php echo $tar['fecha_entrega'] ?></p>
                                    </div>
                                </div>
                            </li>
                        </ul>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-12">
                                    <?php echo $tareaControlador->botonesAccionLista($tar['id_asignacion'], $rol, $tar['estados_tarea'], null); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach;  ?>

            </div>
        </div>
    </div>
</div>
<?php
$contenido = ob_get_clean();
$titulo = "Lista de Tareas";
$bodyClass = "proyectos-page";

include __DIR__ . '/../../layout.php';
?>
<?php if (isset($_GET['msg']) && $_GET['msg'] == 'mensaje'): ?>
    <script>
        abrirMensaje();
    </script>
<?php unset($_SESSION['mensaje']);
endif; ?>
