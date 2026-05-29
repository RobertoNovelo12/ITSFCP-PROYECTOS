<?php
// Areas_conocimiento/editar.php

session_start();

if (!isset($_SESSION['id_usuario'])) {
    header("Location: /ITSFCP-PROYECTOS/index.php");
    exit;
}

$rol        = strtolower($_SESSION['rol'] ?? '');
$id_usuario = (int)$_SESSION['id_usuario'];

include __DIR__ .  '../../../publico/incluido/_validar_get.php';

if ($rol !== 'supervisor') {
    header("Location: /ITSFCP-PROYECTOS/Vistas/Principal/index.php");
    exit;
}

$id_area    = (int)($_GET['id_area'] ?? 0);

$id_validar = $id_area;
include __DIR__ .  '../../../publico/incluido/_validar_id.php';



require_once __DIR__ .  '/../../Controladores/AreaConocimientoControlador.php';

$areaControlador = new AreaConocimientoControlador();

//  Acción POST: guardar cambios ─
// editarArea() valida método, rol y redirige internamente con ?msg=.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'Modificar') {
    $areaControlador->editarArea($rol, $_POST);
    // Siempre redirige → el código no continúa.
}

//  Cargar datos actuales del área ─
$datos   = $areaControlador->indexEditar($rol, $id_area);
$registro = $datos;
require_once __DIR__ .  '../../../publico/incluido/_validar_datos.php';


$area    = $datos['area']    ?? [];
$subareas = $datos['subareas'] ?? [];

// Si no se encontró el área redirigir con error
if (empty($area)) {
    header("Location: index.php?msg=error_cargar");
    exit;
}

//  Mapa de mensajes ─
$msg   = $_GET['msg'] ?? '';
$_mapa = [
    'exito_editar'        => ['tipo' => 'exito',  'titulo_msg' => 'Área actualizada',      'mensaje' => 'El área de conocimiento fue editada correctamente.'],
    'error_editar'        => ['tipo' => 'error',  'titulo_msg' => 'Error al editar',        'mensaje' => 'No fue posible editar el área. Verifica los datos e intenta de nuevo.'],
    'error_duplicado'     => ['tipo' => 'error',  'titulo_msg' => 'Registro duplicado',     'mensaje' => 'Ya existe una subárea con ese nombre en esta área.'],
    'error_cargar'        => ['tipo' => 'error',  'titulo_msg' => 'Error al cargar',        'mensaje' => 'No se encontró el área solicitada.'],
    'accion_no_permitida' => ['tipo' => 'alerta', 'titulo_msg' => 'Acción no permitida',    'mensaje' => 'La acción solicitada no está disponible para tu rol.'],
];

ob_start();
?>

<?php if (isset($_mapa[$msg])): extract($_mapa[$msg]); require_once __DIR__ . '/../../../publico/incluido/_mensaje.php'; endif; ?>

<div class="container-fluid py-4 ancho_container">

    <!-- TÍTULO -->
    <div class="row mb-4 align-items-center">
        <?php
        $titulo      = 'Editar Área de Conocimiento';
        $descripcion = 'Modificar datos del área';
        include __DIR__ . '../../../publico/incluido/_encabezado.php';
        ?>
        <div class="col-md-6 text-md-end">
            <a href="index.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Regresar
            </a>
        </div>
    </div>

    <form method="POST" action="" id="formCrearArea">

        <input type="hidden" name="action"  value="Modificar">
        <input type="hidden" name="id_area" value="<?= (int)$area['id_area'] ?>">

        <!-- DATOS DEL ÁREA -->
        <h5>Información de Área de conocimiento</h5>

        <div class="mb-3">
            <label class="form-label">Estado</label>
            <select name="Estado" class="form-select">
                <option value="0" <?= ($area['estado'] === 'Desactivado') ? 'selected' : '' ?>>
                    Desactivado
                </option>
                <option value="1" <?= ($area['estado'] === 'Activo') ? 'selected' : '' ?>>
                    Activo
                </option>
            </select>
        </div>

        <div class="mb-3">
            <label class="form-label">Nombre</label>
            <input
                type="text"
                id="NombreArea"
                name="NombreArea"
                class="form-control"
                value="<?= htmlspecialchars($area['nombre']) ?>"
                required>
        </div>

        <div class="mb-3">
            <label class="form-label">Descripción</label>
            <textarea
                name="Descripcion"
                class="form-control"
                required><?= htmlspecialchars($area['descripcion']) ?></textarea>
        </div>

        <!-- SUBÁREAS -->
        <h5>Subáreas (<span id="contadorSubarea">0 / 10</span>)</h5>
        <hr>

        <div id="listaSubarea">
            <?php foreach ($subareas as $i => $sub): ?>
                <div class="subarea row mb-3 align-items-center g-2">

                    <input type="hidden"
                           name="subarea[<?= $i ?>][id_subarea]"
                           value="<?= (int)$sub['id_subarea'] ?>">

                    <div class="col-12 col-md-8">
                        <input
                            class="form-control subarea-input"
                            name="subarea[<?= $i ?>][nombre]"
                            value="<?= htmlspecialchars($sub['nombre']) ?>"
                            required>
                    </div>

                    <div class="col-12 col-md-4">
                        <button
                            type="button"
                            class="btn btn-eliminar-sub w-100"
                            onclick="eliminarSub(this)">
                            Eliminar
                        </button>
                    </div>

                </div>
            <?php endforeach; ?>
        </div>

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

        <button type="submit" class="btn btn-guardar-area">
            Guardar cambios
        </button>

    </form>
</div>

<script src="../../publico/js/subareas.js"></script>

<?php
$contenido = ob_get_clean();
$titulo    = 'Editar Área de conocimiento';
$bodyClass = 'proyectos-page';
include __DIR__ . '/../../layout.php';
?>