<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

if (!isset($_SESSION['id_usuario'])) {
    header("Location: /ITSFCP-PROYECTOS/index.php");
    exit;
}

require_once '../../Controladores/tematicaControlador.php';

$tematicaControlador = new TematicaControlador();

$rol = strtolower($_SESSION['rol'] ?? '');
$id_usuario = intval($_SESSION['id_usuario']);

require_once '../../../publico/incluido/_validar_get.php';

if ($rol !== 'supervisor') {
    header("Location: /ITSFCP-PROYECTOS/Vistas/Principal/index.php");
    exit;
}

$id_tematica = isset($_GET['id_tematica']) ? intval($_GET['id_tematica']) : 0;

//Validación de argumentos en url
$id_validar = $id_tematica;
require_once '../../../publico/incluido/_validar_id.php';


$datos = $tematicaControlador->indexEditar($rol, $id_tematica);

$tematica = $datos['tematica'][0];
$subtematicas = $datos['subtematicas'];

$action = $_POST['action'] ?? null;

if ($action === 'Modificar') {

    $subtematicas = $_POST['subtematicas'] ?? [];

    $tematicaControlador->editarTematica(
        $rol
    );
}

ob_start();
?>
<div class="container-fluid py-4 ancho_container">
    <!-- ENCABEZADO -->

    <div class="row mb-3">

        <?php
        $titulo      = 'Editar Temática';
        $descripcion = 'Modificar datos de la temática';
        include __DIR__ . '../../../publico/incluido/_encabezado.php';
        ?>

        <div class="col-6 col-md-6 text-md-end">
            <?php if ($rol == "supervisor"): ?>
                <a href="index.php" class="btn btn-secondary btn-sm px-4">
                    <i class="bi bi-arrow-left"></i> Regresar</a>

            <?php endif; ?>
        </div>
    </div>
    <form method="POST" action="" id="formCrearTematica">

        <input type="hidden" name="action" value="Modificar">

        <input type="hidden" name="id_tematica" value="<?= $tematica['id_tematica'] ?>">


        <!-- DATOS TEMATICA -->

        <h5>Información de la temática</h5>

        <div class="mb-3">

            <label class="form-label">Estado</label>

            <select name="Estado" class="form-select">

                <option value="0" <?= $tematica['estado'] == "Desactivado" ? 'selected' : '' ?>>
                    Desactivado
                </option>

                <option value="1" <?= $tematica['estado'] == "Activo" ? 'selected' : '' ?>>
                    Activo
                </option>

            </select>

        </div>

        <div class="mb-3">

            <label class="form-label">Nombre</label>

            <input
                type="text"
                id="NombreTematica"
                name="NombreTematica"
                class="form-control"
                value="<?= htmlspecialchars($tematica['nombre']) ?>"
                required>

        </div>

        <div class="mb-3">

            <label class="form-label">Descripción</label>

            <textarea
                name="Descripcion"
                class="form-control"
                required><?= htmlspecialchars($tematica['descripcion']) ?></textarea>

        </div>


        <!-- SUBTEMATICAS -->

        <h5>Subtemáticas (<span id="contadorSubtematicas">0 / 10</span>)</h5>

        <hr>

        <div id="listaSubtematicas">

            <?php foreach ($subtematicas as $i => $sub): ?>

                <div class="subtematica row mb-3 align-items-center g-2">

                    <input type="hidden"
                        name="subtematicas[<?= $i ?>][id]"
                        value="<?= $sub['id'] ?>">

                    <div class="col-12 col-md-8">

                        <input
                            class="form-control subtematica-input"
                            name="subtematicas[<?= $i ?>][nombre]"
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
                onclick="agregarSubtematica()">

                Agregar subtemática

            </button>

        </div>

        <hr>

        <button
            type="submit"
            class="btn btn-guardar-tematica">

            Guardar cambios

        </button>

    </form>
</div>

<script src="../../publico/js/subtematicas.js"></script>

<?php

$contenido = ob_get_clean();
$titulo = "Editar temática";
$bodyClass = "proyectos-page";

include __DIR__ . '/../../layout.php';
?>