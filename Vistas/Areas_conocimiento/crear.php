<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

/* VALIDACIÓN DE SESIÓN */
if (!isset($_SESSION['id_usuario'])) {
    header("Location: /ITSFCP-PROYECTOS/index.php");
    exit;
}

$rol = strtolower($_SESSION['rol'] ?? '');
$id_usuario = intval($_SESSION['id_usuario']);

//Solo supervisor
if ($rol !== 'supervisor') {
    header("Location: /ITSFCP-PROYECTOS/Vistas/Principal/index.php");
    exit;
}

/* CONTROLADOR */
require_once '../../Controladores/areaconocimientoControlador.php';

$action = $_POST['action'] ?? null;

if ($action === 'registrarArea') {

    $areaControlador = new AreaConocimientoControlador();

    $subareas = $_POST['subarea'] ?? [];

    $areaControlador->registrarArea(
        $_POST,
        $rol,
        $subareas
    );
}

ob_start();
include __DIR__ . '/../../mensaje.php';
include __DIR__ . '/../../error.php';
?>
<div class="container-fluid py-4 ancho_container">

    <!-- ENCABEZADO -->
    <div class="row mb-4 align-items-center">

        <?php
        $titulo      = 'Nueva Área de Conocimiento';
        $descripcion = 'Registro de una nueva área';
        include __DIR__ . '../../../publico/incluido/_encabezado.php';
        ?>

        <div class="col-md-6 text-md-end">
            <a href="index.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Regresar
            </a>
        </div>

    </div>
    <form method="POST" action="" id="formCrearArea">

        <input type="hidden" name="action" value="registrarArea">


        <!-- DATOS ÁREA -->
        <h5>Información del área</h5>

        <div class="mb-3">
            <label class="form-label">Nombre</label>

            <input
                type="text"
                id="NombreArea"
                name="NombreArea"
                class="form-control"
                required>
        </div>

        <div class="mb-3">
            <label class="form-label">Descripción</label>

            <textarea
                name="Descripcion"
                class="form-control"
                required></textarea>
        </div>

        <!-- SUBÁREAS -->
        <h5>Subáreas (<span id="contadorSubarea">0 / 5</span>)</h5>

        <hr>

        <div id="listaSubarea"></div>

        <!-- BOTÓN AGREGAR -->
        <div class="mt-3">
            <button
                type="button"
                class="btn btn-agregar-sub w-100"
                onclick="agregarSubarea()">
                Agregar subárea
            </button>
        </div>

        <hr>

        <button
            type="submit"
            class="btn btn-guardar-area">
            Crear área
        </button>


    </form>
</div>
<script src="../../publico/js/subareas.js"></script>

<?php

$contenido = ob_get_clean();
$titulo = "Crear Área de conocimiento";
$bodyClass = "proyectos-page";

include __DIR__ . '/../../layout.php';
?>