<?php
// Areas_conocimiento/crear.php

session_start();

if (!isset($_SESSION['id_usuario'])) {
    header("Location: /index.php");
    exit;
}

$rol        = strtolower($_SESSION['rol'] ?? '');
$id_usuario = (int)$_SESSION['id_usuario'];

if ($rol !== 'supervisor') {
    header("Location: /Modules/Principal/Views/index.php");
    exit;
}

require_once __DIR__ . '/../Controller/area_conocimiento_controller.php';

//  Acción POST: registrar área 
// registrarArea() valida método, rol y redirige internamente con ?msg=.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'registrarArea') {
    $areaControlador = new AreaConocimientoControlador();
    $areaControlador->registrarArea($rol, $_POST);
    // Siempre redirige → el código no continúa.
}

//  Mapa de mensajes ─
$msg   = $_GET['msg'] ?? '';
$_mapa = [
    'exito_crear'         => ['tipo' => 'exito',  'titulo_msg' => 'Área creada',         'mensaje' => 'El área de conocimiento fue creada correctamente.'],
    'error_crear'         => ['tipo' => 'error',  'titulo_msg' => 'Error al crear',       'mensaje' => 'No fue posible crear el área. Verifica los datos e intenta de nuevo.'],
    'error_duplicado'     => ['tipo' => 'error',  'titulo_msg' => 'Registro duplicado',   'mensaje' => 'Ya existe un área o subárea con ese nombre.'],
    'accion_no_permitida' => ['tipo' => 'alerta', 'titulo_msg' => 'Acción no permitida',  'mensaje' => 'La acción solicitada no está disponible para tu rol.'],
];

ob_start();
?>

<?php if (isset($_mapa[$msg])): extract($_mapa[$msg]); include $_SERVER['DOCUMENT_ROOT'] . '/public/incluido/_mensaje.php'; endif; ?>

<div class="container-fluid py-4 ancho_container">

    <!-- ENCABEZADO -->
    <div class="row mb-4 align-items-center">
        <?php
        $titulo      = 'Nueva Área de Conocimiento';
        $descripcion = 'Registro de una nueva área';
        include $_SERVER['DOCUMENT_ROOT'] . '/public/incluido/_encabezado.php';
        ?>
        <div class="col-md-6 text-md-end">
            <a href="index.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Regresar
            </a>
        </div>
    </div>

    <form method="POST" action="" id="formCrearArea">

        <input type="hidden" name="action" value="registrarArea">

        <!-- DATOS DEL ÁREA -->
        <h5><i class="bi-info-circle me-2"></i>Información del área</h5>

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

        <div class="mt-3">
            <button
                type="button"
                class="btn btn-agregar-sub w-100"
                onclick="agregarSubarea()">
                Agregar subárea
            </button>
        </div>

        <hr>

        <button type="submit" class="btn btn-guardar-area"><i class="bi bi-plus-lg me-1"></i> Crear área</button>

    </form>
</div>

<script src="/public/js/subareas.js"></script>

<?php
$contenido = ob_get_clean();
$titulo    = 'Crear Área de conocimiento';
$bodyClass = 'proyectos-page';
include __DIR__ . '/../../../layout.php';
?>