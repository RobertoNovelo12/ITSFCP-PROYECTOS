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
$id_nivel = $_GET["id_nivel"] ?? null;

//Solo supervisor
if ($rol !== 'supervisor') {
    header("Location: /ITSFCP-PROYECTOS/Vistas/Principal/index.php");
    exit;
}

require_once '../../Controladores/nivelsniControlador.php';

$action = $_POST['action'] ?? null;
$nivelsniControlador = new nivelsniControlador();
$datos = $nivelsniControlador->indexEditar($rol, $id_nivel);
$mensaje  = "";
$estadoVista = ["activo" => 0, "desactivado" => 0];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST) && $action == 'Guardar') {
    $nombre = $_POST['Nombre'];
    $estadoVista = $nivelsniControlador->obtenerPorIdDiferente($id_nivel, $nombre);

    if ($estadoVista['activo'] == 0 && $estadoVista['desactivado'] == 0) {
        $nivelsniControlador->editarNivelSNI($rol, $id_nivel, $nombre);
    } else {
        $mensaje = "Ya hay un Nivel SNI con ese nombre, busca otro";
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST) && $action == 'Reactivar') {
    $nivelsniControlador->reactivar($rol, $_POST['id_nivel']);
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST) && $action == 'Desactivar') {
    $nivelsniControlador->eliminar($rol, $_POST['id_nivel']);
}

ob_start();
include __DIR__ . '/../../mensaje.php';
include __DIR__ . '/../../error.php';
?>

<div class="container-fluid py-4 ancho_container">

    <!-- ENCABEZADO -->
    <div class="row mb-4 align-items-center">
        <?php
        $titulo      = 'Editar Nivel SNI';
        $descripcion = 'Modificar datos del nivel SNI';
        include __DIR__ . '../../../publico/incluido/_encabezado.php';
        ?>
        <div class="col-md-6 text-md-end">
            <a href="index.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Regresar
            </a>
        </div>
    </div>

    <!-- DATOS NIVEL SNI -->
    <form method="POST" action="">
        <input type="hidden" name="id_nivel" value="<?= $datos['id_nivel']; ?>">
        <div class="mb-3">
            <label class="form-label">Nivel SNI</label>
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
                <?php echo $nivelsniControlador->botonesAccionEditar($rol, $datos['estado']); ?>
            <?php } ?>
        </div>
    </form>
</div>

<?php

$contenido = ob_get_clean();
$titulo = "Editar Nivel SNI";
$bodyClass = "proyectos-page";

include __DIR__ . '/../../layout.php';
?>