<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

if (!isset($_SESSION['id_usuario'])) {
    header("Location: /ITSFCP-PROYECTOS/index.php");
    exit;
}

$rol = strtolower($_SESSION['rol'] ?? '');
$id_usuario = intval($_SESSION['id_usuario']);
$id_grado = $_GET["id_grado"] ?? null;

//Solo supervisor
if ($rol !== 'supervisor') {
    header("Location: /ITSFCP-PROYECTOS/Vistas/Principal/index.php");
    exit;
}

require_once '../../Controladores/gradoacademicoControlador.php';

$action = $_POST['action'] ?? null;
$gradoacademicoControlador = new gradoacademicoControlador();
$datos = $gradoacademicoControlador->indexEditar($rol, $id_grado);
$mensaje  = "";
$estadoVista = ["activo" => 0, "desactivado" => 0];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST) && $action == 'Guardar') {
    $nombre = $_POST['Nombre'];
    $estadoVista = $gradoacademicoControlador->obtenerPorIdDiferente($id_grado, $nombre);

    if ($estadoVista['activo'] == 0 && $estadoVista['desactivado'] == 0) {
        $gradoacademicoControlador->editarGradoAcademico($rol, $id_grado, $nombre);
    } else {
        $mensaje = "Ya hay un Grado Académico con ese nombre, busca otro";
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST) && $action == 'Reactivar') {
    $gradoacademicoControlador->reactivar($rol, $_POST['id_grado']);
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST) && $action == 'Desactivar') {
    $gradoacademicoControlador->eliminar($rol, $_POST['id_grado']);
}

ob_start();
include __DIR__ . '/../../mensaje.php';
include __DIR__ . '/../../error.php';
?>

<div class="container-fluid py-4">

    <!-- ENCABEZADO -->
    <div class="row mb-4 align-items-center">
        <div class="col-md-6">
            <h3 class="fw-bold mb-0">Editar Grado Académico</h3>
        </div>
        <div class="col-md-6 text-md-end">
            <a href="index.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Regresar
            </a>
        </div>
    </div>

    <!-- DATOS GRADO ACADÉMICO -->
    <form method="POST" action="">
        <input type="hidden" name="id_grado" value="<?= $datos['id_grado']; ?>">
        <div class="mb-3">
            <label class="form-label">Grado Académico</label>
            <input
                type="text"
                name="Nombre"
                class="form-control"
                value="<?= htmlspecialchars($datos['nombre']); ?>"
                required>
        </div>
        <div class="mb-3">
            <?php if (!empty($mensaje)) { ?>
                <div class="alert alert-warning" role="alert">
                    <?= $mensaje ?>
                </div>
            <?php } else { ?>
                <?php echo $gradoacademicoControlador->botonesAccionEditar($rol, $datos['estado']); ?>
            <?php } ?>
        </div>
    </form>
</div>

<?php

$contenido = ob_get_clean();
$titulo = "Editar Grado Académico";
$bodyClass = "proyectos-page";

include __DIR__ . '/../../layout.php';
?>
