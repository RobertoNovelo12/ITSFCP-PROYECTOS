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

if ($rol !== 'supervisor') {
    header("Location: /ITSFCP-PROYECTOS/Vistas/Principal/index.php");
    exit;
}

/* CONTROLADOR */
require_once __DIR__ . '/../../Controladores/tematicaControlador.php';

$action = $_POST['action'] ?? null;

if ($action === 'registrarArea_conocimineto') {

    $tematicaControlador = new tematicaControlador();

    $subtematicas = $_POST['subtematicas'] ?? [];

    $tematicaControlador->registrarTematica(
        $rol
    );
}

ob_start();

?>


<div class="container-fluid py-4 ancho_container">

    <!-- ENCABEZADO -->
    <div class="row mb-4 align-items-center">

        <?php
        $titulo      = 'Nueva Temática';
        $descripcion = 'Registro de una nueva temática';
        include __DIR__ . '../../../publico/incluido/_encabezado.php';
        ?>

        <div class="col-md-6 text-md-end">
            <a href="index.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Regresar
            </a>
        </div>

    </div>

    <!-- DATOS TEMÁTICA -->
    <h5>Información de la temática</h5>

    <form method="POST" action="" id="formCrearTematica">

        <input type="hidden" name="action" value="registrarTematica">

        <div class="mb-3">

            <label class="form-label">Nombre</label>

            <input
                type="text"
                id="NombreTematica"
                name="NombreTematica"
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

        <!-- SUBTEMÁTICAS -->
        <h5>Subtemáticas (<span id="contadorSubtematicas">0 / 10</span>)</h5>

        <hr>

        <!-- DESKTOP -->

        <div id="listaSubtematicas"></div>


        <!-- BOTÓN AGREGAR -->
        <div class="mt-3">

            <button
                type="button"
                class="btn btn-agregar-sub w-100"
                onclick="agregarSubtematica()">
                Agregar subtemática
            </button>

        </div>

        <hr>

        <button
            type="submit"
            class="btn btn-guardar-tematica">

            Crear temática

        </button>

</div>

</form>

<script src="../../publico/js/subtematicas.js"></script>

<?php

$contenido = ob_get_clean();
$titulo = "Crear temática";
$bodyClass = "proyectos-page";

include __DIR__ . '/../../layout.php';
?>