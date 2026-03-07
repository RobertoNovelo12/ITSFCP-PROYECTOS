<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once '../../Controladores/tematicaControlador.php';

$tematicaControlador = new TematicaControlador();

//1. VALIDACIONES BÁSICAS
if (!isset($_SESSION['id_usuario'])) {
    header("Location: /ITSFCP-PROYECTOS/index.php");
    exit;
}

$rol = $_SESSION['rol'];
$id_usuario = $_SESSION['id_usuario'];
$id_tematica = $_GET['id_tematica'] ?? null;

//2. CARGAR TEMÁTICA DESDE BD (SOLO 1 VEZ)
if ($id_tematica && !isset($_SESSION['tematica_temp'])) {

    $tematica = $tematicaControlador->indexEditar($rol, $id_tematica);

    if (is_string($tematica)) {
        $tematica = json_decode($tematica, true);
    }

    if (!is_array($tematica)) {
        die("Error al cargar la temática.");
    }

    $_SESSION['tematica_temp'] = [
        'nombre' => $tematica['tematica'][0]['nombre'] ?? '',
        'descripcion' => $tematica['tematica'][0]['descripcion'] ?? ''
    ];

    $_SESSION['subtematicas'] = $tematica['subtematicas'] ?? [];
}

//3. PROCESAR ACCIONES (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Siempre guardar la temática antes de cualquier acción
    $_SESSION['tematica_temp']['nombre'] = $_POST['NombreTematica'] ?? '';
    $_SESSION['tematica_temp']['descripcion'] = $_POST['Descripcion'] ?? '';

    /* -------- AGREGAR SUBTEMÁTICA -------- */
    if (isset($_POST['agregar_sub'])) {

        $_SESSION['subtematicas'][] = [
            'id' => uniqid('tmp_'),
            'nombre' => trim($_POST['nombre_sub'])
        ];

        header("Location: editar.php?mensaje=1");
        exit;
    }

    /* -------- ACTUALIZAR SUBTEMÁTICA -------- */
    if (isset($_POST['actualizar_sub'])) {

        foreach ($_SESSION['subtematicas'] as &$sub) {
            if ($sub['id'] == $_POST['id_sub']) {
                $sub['nombre'] = trim($_POST['nombre_sub']);
                break;
            }
        }
        unset($sub);

        header("Location: editar.php?mensaje=1");
        exit;
    }

    /* -------- ELIMINAR SUBTEMÁTICA -------- */
    if (isset($_POST['eliminar_sub'])) {

        $_SESSION['subtematicas'] = array_values(array_filter(
            $_SESSION['subtematicas'],
            fn($s) => $s['id'] != $_POST['eliminar_sub']
        ));

        header("Location: editar.php?mensaje=1");
        exit;
    }

    /* -------- GUARDAR DEFINITIVO -------- */
    if (isset($_POST['guardar_definitivo'])) {

        $tematicaControlador->editarTematica(
            $id_tematica,
            $_SESSION['tematica_temp'],
            $_SESSION['subtematicas'],
            $rol
        );

        unset($_SESSION['tematica_temp'], $_SESSION['subtematicas']);

        header("Location: tabla.php?mensaje=1");
        exit;
    }
}

//4. DETECTAR EDICIÓN DE SUBTEMÁTICA (GET SOLO VISUAL)
$editando = false;
$subEditar = null;

if (isset($_GET['editar_sub'])) {
    foreach ($_SESSION['subtematicas'] as $sub) {
        if ($sub['id'] == $_GET['editar_sub']) {
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
                value="<?= htmlspecialchars($_SESSION['tematica_temp']['nombre'] ?? '') ?>"
                required>
        </div>

        <div class="mb-3">
            <label class="form-label">Descripción</label>
            <textarea class="form-control"
                name="Descripcion"
                rows="3"
                required><?= htmlspecialchars($_SESSION['tematica_temp']['descripcion'] ?? '') ?></textarea>
        </div>

        <!-- SUBTEMÁTICAS -->
        <h5>Subtemáticas</h5>

        <table class="table table-hover text-center">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Nombre</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>

                <?php if (empty($_SESSION['subtematicas'])): ?>
                    <tr>
                        <td colspan="3">No hay subtemáticas</td>
                    </tr>
                <?php endif; ?>

                <?php foreach ($_SESSION['subtematicas'] as $i => $sub): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td><?= htmlspecialchars($sub['nombre']) ?></td>
                        <td class="d-flex justify-content-center gap-1">
                            <a href="?editar_sub=<?= $sub['id'] ?>" class="btn btn-warning btn-sm">
                                Editar
                            </a>
                            <button type="submit"
                                name="eliminar_sub"
                                value="<?= $sub['id'] ?>"
                                class="btn btn-danger btn-sm"
                                onclick="return confirm('¿Eliminar subtemática?')">
                                Eliminar
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>

            </tbody>
        </table>

        <!-- FORM SUBTEMÁTICA -->
        <hr>
        <h5><?= $editando ? 'Editar subtemática' : 'Agregar subtemática' ?></h5>

        <input type="hidden" name="id_sub" value="<?= $subEditar['id'] ?? '' ?>">

        <div class="row g-2 mb-3">
            <div class="col-md-8">
                <input type="text"
                    name="nombre_sub"
                    class="form-control"
                    value="<?= $subEditar['nombre'] ?? '' ?>"
                    required>
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
            <a href="editar.php" class="btn btn-secondary btn-sm">Cancelar edición</a>
        <?php endif; ?>

        <!-- GUARDAR -->
        <hr>
        <div class="text-center">
            <button type="submit" name="guardar_definitivo" class="btn btn-primary">
                Guardar cambios
            </button>
        </div>

    </div>
</form>

<?php
$contenido = ob_get_clean();
$titulo = "Editar temática";
$bodyClass = "proyectos-page";

include __DIR__ . '/../../layout.php';
?>