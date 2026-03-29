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

/* CONTROLADOR */
require_once '../../Controladores/periodoControlador.php';

$action = $_POST['action'] ?? null;
$periodoControlador = new periodoControlador();
$datos = $periodoControlador->generarPeriodoAutomatico();

if ($action === 'registrarPeriodo') {
    $periodoControlador->registrarPeriodo($rol);
}

ob_start();
include __DIR__ . '/../../mensaje.php';
include __DIR__ . '/../../error.php';
?>


<div class="container-fluid py-4">

    <!-- ENCABEZADO -->
    <div class="row mb-3">

        <div class="col-6">
            <h3>Crear Temática</h3>
        </div>

        <div class="col-6 text-end">
            <a href="tabla.php" class="btn btn-danger">Regresar</a>
        </div>

    </div>

    <!-- DATOS PERIODO -->
    <h5>Información del periodo</h5>
    <form method="POST" action="" id="formCrearTematica">

        <input type="hidden" name="action" value="registrarPeriodo">


        <div class="mb-3">

            <label class="form-label">Nombre</label>

            <input
                type="text"
                name="Periodo"
                class="form-control"
                value="<?= $datos['nombre']; ?>"
                readonly
                required>
        </div>

        <div class="mb-3">

            <label class="form-label">Fecha inicio</label>

            <input
                type="date"
                name="fechaInicio"
                class="form-control"
                aria-describedby="FechaInicio"
                min="<?= $datos['inicio']; ?>"
                max="<?= $datos['fin']; ?>"
                value="<?= $datos['inicio']; ?>"
                readonly
                required>
        </div>

        <div class="mb-3">

            <label class="form-label">Fecha final</label>

            <input
                type="date"
                name="fechaFinal"
                class="form-control"
                aria-describedby="FechaFinal"
                min="<?= $datos['inicio']; ?>"
                max="<?= $datos['fin']; ?>"
                value="<?= $datos['fin']; ?>"
                readonly
                required>
        </div>

        <hr>

        <button
            type="submit"
            class="btn btn-guardar-tematica">

            Crear Periodo

        </button>

</div>

</form>


<?php

$contenido = ob_get_clean();
$titulo = "Crear periodo";
$bodyClass = "proyectos-page";

include __DIR__ . '/../../layout.php';
?>