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
$id_fuente = $_GET["id_fuente"] ?? null;

require_once '../../Controladores/fuenteControlador.php';

$action = $_POST['action'] ?? null;
$fuenteControlador = new fuenteControlador();
$datos = $fuenteControlador->indexEditar($rol, $id_fuente);
$mensaje  = "";
$estadoVista = ["activo" => 0, "desactivado" => 0];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST) && $action == 'Guardar') {
    $fuente = $_POST['Fuente'];
    $estadoVista = $fuenteControlador->obtenerPorIdDiferente($id_fuente, $fuente);

    if ($estadoVista['activo'] == 0 && $estadoVista['desactivado'] == 0) {
        $fuenteControlador->editarFuente($rol, $id_fuente, $fuente);
    } else {
        $mensaje = "Ya hay un Fuente con ese nombre, busca otro";
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST) && $action == 'Reactivar') {
    $fuenteControlador->reactivar($rol, $_POST['id_fuente']);
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST) && $action == 'Desactivar') {
    $fuenteControlador->eliminar($rol, $_POST['id_fuente']);
}

ob_start();
include __DIR__ . '/../../mensaje.php';
include __DIR__ . '/../../error.php';
?>

<div class="container-fluid py-4">

    <!-- ENCABEZADO -->
    <div class="row mb-4 align-items-center">
        <div class="col-md-6">
            <h3 class="fw-bold mb-0">Editar Fuente</h3>
        </div>
        <div class="col-md-6 text-md-end">
            <a href="tabla.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Regresar
            </a>
        </div>
    </div>

    <!-- DATOS FUENTE -->
    <form method="POST" action="">
        <input type="hidden" name="id_fuente" value="<?= $datos['id_fuente']; ?>">
        <div class="mb-3">
            <label class="form-label">Fuente</label>
            <input
                type="text"
                name="Fuente"
                class="form-control"
                value="<?= htmlspecialchars($datos['fuente']); ?>"
                required>
        </div>
        <div class="mb-3">
            <?php if (!empty($mensaje)) { ?>
                <div class="alert alert-warning" role="alert">
                    <?= $mensaje ?>
                </div>
            <?php } else { ?>
                <?php echo $fuenteControlador->botonesAccionEditar($rol, $datos['estado']); ?>
            <?php } ?>
        </div>
    </form>
</div>

<?php

$contenido = ob_get_clean();
$titulo = "Editar Fuente";
$bodyClass = "proyectos-page";

include __DIR__ . '/../../layout.php';
?>
