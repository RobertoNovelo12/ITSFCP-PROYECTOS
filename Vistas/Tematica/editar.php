<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
require_once '../../Controladores/tematicaControlador.php';

$tematicaControlador = new TematicaControlador();

/* ========= VALIDACIÓN DE SESIÓN ========= */

if (!isset($_SESSION['id_usuario'])) {
    header("Location: /ITSFCP-PROYECTOS/index.php");
    exit;
}

$rol = $_SESSION['rol'];
$id_usuario = $_SESSION['id_usuario'];
$id_tematica = isset($_GET['id_tematica']) ? intval($_GET['id_tematica']) : null;
/* ========= CARGAR TEMÁTICA (SOLO 1 VEZ) ========= */

if ($id_tematica) {

    unset($_SESSION['subtematicas']);


    $tematica = $tematicaControlador->indexEditar($rol, $id_tematica);

    if (is_string($tematica)) {
        $tematica = json_decode($tematica, true);
    }

    $_SESSION['subtematicas'] = $tematica['subtematicas'] ?? [];

    $_SESSION['tematica_temp'] = [
        'id_tematica' => $tematica['tematica'][0]['id_tematica'] ?? '',
        'NombreTematica' => $tematica['tematica'][0]['NombreTematica'] ?? '',
        'Descripcion' => $tematica['tematica'][0]['Descripcion'] ?? ''
    ];

    $subtematicas = $tematica['subtematicas'] ?? [];
}

/* ========= PROCESAR POST ========= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $_SESSION['tematica_temp']['id_tematica'] = $_POST['id_tematica'] ?? '';
    $_SESSION['tematica_temp']['NombreTematica'] = $_POST['NombreTematica'] ?? '';
    $_SESSION['tematica_temp']['Descripcion'] = $_POST['Descripcion'] ?? '';

    /* -------- AGREGAR SUBTEMÁTICA -------- */

    if (isset($_POST['agregar_sub'])) {

        $nombre = trim($_POST['nombre_sub']);

        if ($nombre !== '') {

            $_SESSION['subtematicas'][] = [
                'id_subtematica' => null,
                'temp_id' => uniqid(),
                'nombre' => $nombre
            ];
        }

        header("Location: editar.php?id_tematica=$id_tematica");
        exit;
    }

    /* -------- ACTUALIZAR SUBTEMÁTICA -------- */

    if (isset($_POST['actualizar_sub'])) {

        foreach ($subtematicas as &$sub) {

            if (($sub['id_subtematica'] ?? $sub['temp_id']) == $_POST['id_sub']) {
                $sub['nombre'] = trim($_POST['nombre_sub']);
                break;
            }
        }

        unset($sub);

        header("Location: editar.php?id_tematica=$id_tematica");
        exit;
    }

    /* -------- ELIMINAR SUBTEMÁTICA -------- */

    if (isset($_POST['eliminar_sub'])) {

        $idEliminar = $_POST['eliminar_sub'];

        $_SESSION['subtematicas'] = array_values(array_filter(
            $_SESSION['subtematicas'],
            fn($s) => ($s['id_subtematica'] ?? $s['temp_id']) != $idEliminar
        ));

        header("Location: editar.php?id_tematica=$id_tematica");
        exit;
    }

    /* -------- GUARDAR DEFINITIVO -------- */

    if (isset($_POST['guardar_definitivo'])) {

        $tematicaControlador->editarTematica(
            $_SESSION['rol'],
            $_SESSION['tematica_temp'],
            $_SESSION['subtematicas']
        );

        unset($_SESSION['tematica_temp'], $_SESSION['subtematicas']);

        header("Location: tabla.php?mensaje=1");
        exit;
    }
}

/* ========= DETECTAR EDICIÓN ========= */

$editando = false;
$subEditar = null;
//Seguridad del GET
$editar_sub = isset($_GET['editar_sub']) ? htmlspecialchars($_GET['editar_sub']) : null;
if ($editar_sub) {
    $subtematicas = $_SESSION['subtematicas'] ?? [];
    foreach ($subtematicas as $sub) {

        if (($sub['id_subtematica'] ?? $sub['temp_id']) == $editar_sub) {

            $editando = true;
            $subEditar = $sub;
            break;
        }
    }
}
ob_start();
include __DIR__ . '/../../mensaje.php';
include __DIR__ . '/../../error.php';
?>
<form method="POST" action="">

    <div class="container-fluid py-4">

        <!-- ENCABEZADO -->
        <div class="row mb-3">
            <div class="col-6">
                <h3>Editar Temática</h3>
            </div>
            <div class="col-6 text-end">
                <a href="tabla.php" class="btn btn-danger">Regresar</a>
            </div>
        </div>

        <!-- DATOS DE TEMÁTICA -->
        <h5>Información de la temática</h5>

        <div class="mb-3">
            <label class="form-label">Nombre</label>
            <input type="text"
                class="form-control"
                name="NombreTematica"
                value="<?= htmlspecialchars($_SESSION['tematica_temp']['NombreTematica'] ?? '') ?>"
                required>
        </div>

        <div class="mb-3">
            <label class="form-label">Descripción</label>
            <textarea class="form-control"
                name="Descripcion"
                rows="3"
                required><?= htmlspecialchars($_SESSION['tematica_temp']['Descripcion'] ?? '') ?></textarea>
        </div>

        <!-- SUBTEMÁTICAS -->
        <h5>Subtemáticas</h5>
        <!-- FORM SUBTEMÁTICA -->
        <hr>
        <h6><?= $editando ? 'Editar subtemática' : 'Agregar subtemática' ?></h6>
        <input type="hidden" name="id_sub" value="<?= $subEditar['id_subtematica'] ?? $subEditar['temp_id'] ?? '' ?>">

        <div class="row g-2 mb-3">
            <div class="col-md-8">
                <input type="text"
                    name="nombre_sub"
                    class="form-control"
                    value="<?= $subEditar['nombre'] ?? '' ?>"
                    <?php if ($editando) {
                        echo "required";
                    } ?>>
            </div>
            <div class="col-md-4">
                <button type="submit"
                    name="<?= $editando ? 'actualizar_sub' : 'agregar_sub' ?>"
                    class="btn btn-<?= $editando ? 'warning' : 'success' ?> w-100">
                    <?= $editando ? 'Actualizar' : 'Agregar' ?>
                </button>
            </div>
        </div>

        <?php if ($editando): ?>
            <a href="editar.php?id_tematica=<?= $id_tematica ?>" class="btn btn-secondary btn-sm">
                Cancelar edición
            </a> <?php endif; ?>

        <div class="row">
            <div class="col-12">
                <?php if (!empty($_SESSION['subtematicas'] ?? [])): ?>
                    <!-- TABLA DESKTOP -->
                    <div class="table-responsive d-none d-md-block">
                        <table class="table table-hover text-center align-middle">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Nombre</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($_SESSION['subtematicas'] as $i => $sub):
                                    $idSub = $sub['id_subtematica'] ?? $sub['temp_id'] ?? '';
                                ?>
                                    <tr>
                                        <td><?= $i + 1 ?></td>
                                        <td><?= htmlspecialchars($sub['nombre']) ?></td>
                                        <td class="d-flex justify-content-center gap-1">
                                            <a href="?id_tematica=<?= $id_tematica ?>&editar_sub=<?= $idSub ?>"
                                                class="btn btn-warning btn-sm">
                                                Editar
                                            </a>
                                            <button type="submit"
                                                name="eliminar_sub"
                                                value="<?= $idSub ?>"
                                                class="btn btn-danger btn-sm"
                                                onclick="return confirm('¿Eliminar subtemática?')">
                                                Eliminar
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>

                            </tbody>
                        </table>
                    </div>
                    <!-- TARJETAS MÓVILES -->
                    <div class="d-block d-md-none mt-4">
                        <?php foreach ($_SESSION['subtematicas'] as $i => $sub): ?>
                            <div class="card mb-3 shadow-sm w-100">
                                <div class="card-body">

                                    <h5 class="card-title">
                                        <strong>#:</strong> <?= $i + 1 ?>
                                    </h5>

                                    <p class="card-text">
                                        <strong>Subtemática:</strong>
                                        <?= htmlspecialchars($sub['nombre'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                                    </p>

                                    <div class="d-flex justify-content-between mt-2">

                                        <a href="?id_tematica=<?= $id_tematica ?>&editar_sub=<?= $sub['id_subtematica'] ?? $sub['temp_id'] ?? '' ?>"
                                            class="btn btn-warning btn-sm">
                                            Editar
                                        </a>

                                        <button type="submit"
                                            name="eliminar_sub"
                                            value="<?= $sub['id_subtematica'] ?? $sub['temp_id'] ?? '' ?>"
                                            class="btn btn-danger btn-sm"
                                            onclick="return confirm('¿Eliminar subtemática?')">
                                            Eliminar
                                        </button>

                                    </div>

                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                <?php else: ?>
                    <div class="alert alert-info text-center">
                        No hay subtematicas para mostrar.
                    </div>
                <?php endif; ?>
                <!-- GUARDAR -->
                <hr>
                <div class="text-center">
                    <button type="submit" name="guardar_definitivo" class="btn btn-primary">
                        Guardar cambios
                    </button>
                </div>

            </div>
        </div>
</form>

<?php
$contenido = ob_get_clean();
$titulo = "Editar temática";
$bodyClass = "proyectos-page";

include __DIR__ . '/../../layout.php';
?>