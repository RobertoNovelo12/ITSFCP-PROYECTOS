<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

   //VALIDACIÓN DE SESIÓN
if (!isset($_SESSION['id_usuario'])) {
    header("Location: /ITSFCP-PROYECTOS/index.php");
    exit;
}

$rol = $_SESSION['rol'];
$id  = $_SESSION['id_usuario'];

   //VARIABLES DE SESIÓN TEMPORALES

// Subtemáticas temporales
if (!isset($_SESSION['subtematicas'])) {
    $_SESSION['subtematicas'] = [];
}

// Datos temporales de la temática
if (!isset($_SESSION['tematica_temp'])) {
    $_SESSION['tematica_temp'] = [
        'nombre' => '',
        'descripcion' => ''
    ];
}

   //FUNCIÓN PARA GUARDAR DATOS DE TEMÁTICA
function guardarTematicaTemp()
{
    $_SESSION['tematica_temp']['nombre'] = $_POST['NombreTematica'] ?? $_SESSION['tematica_temp']['nombre'];
    $_SESSION['tematica_temp']['descripcion'] = $_POST['Descripcion'] ?? $_SESSION['tematica_temp']['descripcion'];
}

   //AGREGAR SUBTEMÁTICA
if (isset($_POST['agregar_sub'])) {

    guardarTematicaTemp();

    $_SESSION['subtematicas'][] = [
        'id'     => uniqid(),
        'nombre' => trim($_POST['nombre_sub'])
    ];

    header("Location: crear.php");
    exit;
}

   //ACTUALIZAR SUBTEMÁTICA
if (isset($_POST['actualizar_sub'])) {

    guardarTematicaTemp();

    foreach ($_SESSION['subtematicas'] as &$sub) {
        if ($sub['id'] === $_POST['id_sub']) {
            $sub['nombre'] = trim($_POST['nombre_sub']);
            break;
        }
    }
    unset($sub);

    header("Location: crear.php");
    exit;
}

   //ELIMINAR SUBTEMÁTICA
if (isset($_GET['eliminar_sub'])) {

    // NO perder los datos actuales de la temática
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        guardarTematicaTemp();
    }

    $_SESSION['subtematicas'] = array_values(
        array_filter(
            $_SESSION['subtematicas'],
            fn($s) => $s['id'] !== $_GET['eliminar_sub']
        )
    );

    header("Location: crear.php");
    exit;
}

   //DETECTAR EDICIÓN DE SUBTEMÁTICA

$editando = false;
$subEditar = null;

if (isset($_GET['editar_sub'])) {
    foreach ($_SESSION['subtematicas'] as $sub) {
        if ($sub['id'] === $_GET['editar_sub']) {
            $editando = true;
            $subEditar = $sub;
            break;
        }
    }
}

   //CANCELAR CREACIÓN
if (isset($_GET['cancelar'])) {
    unset($_SESSION['tematica_temp'], $_SESSION['subtematicas']);
    header("Location: tabla.php");
    exit;
}

   //ENVÍO AL CONTROLADOR
require_once '../../Controladores/tematicaControlador.php';

$action = $_POST['action'] ?? null;

if ($action === 'registrarTematica') {
    $tematicaControlador = new TematicaControlador();
    $tematicaControlador->registrarTematica(
        $_POST,
        $rol,
        $_SESSION['subtematicas']
    );
}
ob_start();
include __DIR__ . '/../../mensaje.php';
include __DIR__ . '/../../error.php';
?>
<div class="container-fluid py-4">
    <div class="row mb-3">
        <div class="col-6">
            <h3>Crear Temática</h3>
        </div>
        <div class="col-6 text-end">
            <a href="crear.php?cancelar=1" class="btn btn-danger">Regresar</a>
        </div>
    </div>

    <form method="POST" action="crear.php">

        <input type="hidden" name="action" value="registrarTematica">

        <!-- DATOS DE TEMÁTICA -->
        <h5>Información de la temática</h5>

        <div class="mb-3">
            <label class="form-label">Nombre de la temática</label>
            <input type="text"
                   name="NombreTematica"
                   class="form-control"
                   value="<?= htmlspecialchars($_SESSION['tematica_temp']['nombre']) ?>"
                   required>
        </div>

        <div class="mb-3">
            <label class="form-label">Descripción</label>
            <textarea name="Descripcion"
                      class="form-control"
                      rows="3"
                      required><?= htmlspecialchars($_SESSION['tematica_temp']['descripcion']) ?></textarea>
        </div>

        <!-- TABLA SUBTEMÁTICAS -->
        <h5>Subtemáticas</h5>

        <table class="table table-hover text-center align-middle">
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
                    <td>
                        <a href="?editar_sub=<?= $sub['id'] ?>" class="btn btn-warning btn-sm">Editar</a>
                        <a href="?eliminar_sub=<?= $sub['id'] ?>"
                           class="btn btn-danger btn-sm"
                           onclick="return confirm('¿Eliminar subtemática?')">Eliminar</a>
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

        <div class="text-center">
            <button type="submit" class="btn btn-primary">Crear temática</button>
        </div>
    </form>
</div>

<?php
$contenido = ob_get_clean();
$titulo = "Crear temática";
$bodyClass = "proyectos-page";

include __DIR__ . '/../../layout.php';
?>