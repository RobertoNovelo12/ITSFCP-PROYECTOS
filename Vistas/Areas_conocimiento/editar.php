<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

if (!isset($_SESSION['id_usuario'])) {
    header("Location: /ITSFCP-PROYECTOS/index.php");
    exit;
}

include "../../Controladores/areaconocimientoControlador.php";

$areaControlador = new AreaConocimientoControlador();

$rol = strtolower($_SESSION['rol'] ?? '');
$id_usuario = intval($_SESSION['id_usuario']);

$id_area = isset($_GET['id_area']) ? intval($_GET['id_area']) : 0;

$datos = $areaControlador->indexEditar($rol, $id_area);

$area = $datos['area'];
$subareas = $datos['subareas'];

$action = $_POST['action'] ?? null;

if ($action === 'Modificar') {

    $subareas = $_POST['subarea'] ?? [];

    $areaControlador->editarArea(
        $rol
    );
}

ob_start();
include __DIR__ . '/../../mensaje.php';
include __DIR__ . '/../../error.php';
?>

<form method="POST" action="" id="formCrearArea">

    <input type="hidden" name="action" value="Modificar">

    <input type="hidden" name="id_area" value="<?= $area['id_area'] ?>">

    <div class="container-fluid py-4">

        <!-- ENCABEZADO -->

        <div class="row mb-3">

            <div class="col-6">
                <h3>Editar Área de conocimiento</h3>
            </div>

            <div class="col-6 text-end">
                <a href="tabla.php" class="btn btn-danger">Regresar</a>
            </div>

        </div>


        <!-- DATOS TEMATICA -->

        <h5>Información de Área de conocimiento</h5>

        <div class="mb-3">

            <label class="form-label">Estado</label>

            <select name="Estado" class="form-select">

                <option value="0" <?= $area['estado'] == "Desactivado" ? 'selected' : '' ?>>
                    Desactivado
                </option>

                <option value="1" <?= $area['estado'] == "Activo" ? 'selected' : '' ?>>
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


        <!-- SUBAREAS -->

        <h5>Subareas (<span id="contadorSubarea">0 / 10</span>)</h5>

        <hr>

        <div id="listaSubarea">

            <?php foreach ($subareas as $i => $sub): ?>

                <div class="subarea row mb-3 align-items-center g-2">

                    <input type="hidden"
                        name="subarea[<?= $i ?>][id_subarea]"
                        value="<?= $sub['id_subarea'] ?>">

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


        <!-- BOTON AGREGAR -->

        <div class="mt-3">

            <button
                type="button"
                class="btn btn-agregar-sub w-100"
                onclick="agregarSubarea()">

                Agregar subarea

            </button>

        </div>

        <hr>

        <button
            type="submit"
            class="btn btn-guardar-area">

            Guardar cambios

        </button>

    </div>

</form>


<script src="../../publico/js/subareas.js"></script>

<?php

$contenido = ob_get_clean();
$titulo = "Editar Área de conocimiento";
$bodyClass = "proyectos-page";

include __DIR__ . '/../../layout.php';
?>