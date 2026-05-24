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
$id_director = $_GET["id_director"] ?? null;

if ($rol !== 'supervisor') {
    header("Location: /ITSFCP-PROYECTOS/Vistas/Principal/index.php");
    exit;
}

require_once '../../Controladores/directorControlador.php';

$action = $_POST['action'] ?? null;
$directorControlador = new directorControlador();
$datos = $directorControlador->indexEditar($rol, $id_director);
$mensaje  = "";
$estadoVista = ["activo" => 0, "desactivado" => 0];

// Obtener grados académicos activos para el select
$grados = $directorControlador->obtenerGrados($rol);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST) && $action == 'Guardar') {
    $id_grado  = $_POST['IdGrado'];
    $nombre    = $_POST['Nombre'];
    $apellido  = $_POST['Apellido'];
    $correo    = $_POST['Correo'] ?? null;
    $telefono  = $_POST['Telefono'] ?? null;
    $Fecha_inicio  = $_POST['Fecha_inicio'] ?? null;
    $Fecha_final  = $_POST['Fecha_final'] ?? null;
    $Motivo_fin  = $_POST['Motivo_fin'] ?? null;

    // Verificar duplicado de correo excluyendo el registro actual
    if (!empty($correo)) {
        $estadoVista = $directorControlador->obtenerPorIdDiferente($id_director, $correo);
    }

    if ($estadoVista['activo'] == 0 && $estadoVista['desactivado'] == 0) {
        $directorControlador->editarDirector($rol, $id_director, $id_grado, $nombre, $apellido, $correo, $telefono, $Fecha_inicio, $Fecha_final, $Motivo_fin);
    } else {
        $mensaje = "Ya existe un director con ese correo, busca otro";
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST) && $action == 'Reactivar') {
    $directorControlador->reactivar($rol, $_POST['id_director']);
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST) && $action == 'Desactivar') {
    $directorControlador->eliminar($rol, $_POST['id_director']);
}

ob_start();
include __DIR__ . '/../../mensaje.php';
include __DIR__ . '/../../error.php';
?>

<div class="container-fluid py-4">

    <!-- ENCABEZADO -->
    <div class="row mb-4 align-items-center">
        <div class="col-md-6">
            <h3 class="fw-bold mb-0">Editar Director</h3>
        </div>
        <div class="col-md-6 text-md-end">
            <a href="index.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Regresar
            </a>
        </div>
    </div>

    <!-- DATOS DIRECTOR -->
    <form method="POST" action="">
        <input type="hidden" name="id_director" value="<?= $datos['id_director']; ?>">

        <div class="mb-3">
            <label class="form-label">Grado Académico</label>
            <select name="IdGrado" class="form-select" required>
                <option value="">-- Selecciona un grado --</option>
                <?php foreach ($grados as $grado): ?>
                    <option value="<?= $grado['id_grado'] ?>"
                        <?= ($grado['id_grado'] == $datos['id_grado']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($grado['nombre']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="mb-3">
            <label class="form-label">Nombre</label>
            <input
                type="text"
                name="Nombre"
                class="form-control"
                value="<?= htmlspecialchars($datos['nombre']); ?>"
                required>
        </div>

        <div class="mb-3">
            <label class="form-label">Apellido</label>
            <input
                type="text"
                name="Apellido"
                class="form-control"
                value="<?= htmlspecialchars($datos['apellido']); ?>"
                required>
        </div>

        <div class="mb-3">
            <label class="form-label">Correo</label>
            <input
                type="email"
                name="Correo"
                class="form-control"
                value="<?= htmlspecialchars($datos['correo'] ?? ''); ?>">
        </div>

        <div class="mb-3">
            <label class="form-label">Fecha inicio</label>
            <input
                type="date"
                name="Fecha_inicio"
                class="form-control"
                maxlength="10"
                value="<?= htmlspecialchars($datos['inicio'] ?? ''); ?>">
        </div>

        <div class="mb-3">
            <label class="form-label">Fecha final</label>
            <input
                type="date"
                name="Fecha_final"
                class="form-control"
                maxlength="10"
                value="<?= htmlspecialchars($datos['fin'] ?? ''); ?>">
        </div>

        <div class="mb-3">
            <label class="form-label">Motivo de salida</label>
            <textarea name="Motivo_fin" class="form-control" row="3"><?= htmlspecialchars($datos['motivo_fin'] ?? ''); ?></textarea>
        </div>

        <div class="mb-3">
            <label class="form-label">Teléfono</label>
            <input
                type="text"
                name="Telefono"
                class="form-control"
                maxlength="10"
                value="<?= htmlspecialchars($datos['telefono'] ?? ''); ?>">
        </div>

        <div class="mb-3">
            <?php if (!empty($mensaje)) { ?>
                <div class="alert alert-warning" role="alert">
                    <?= $mensaje ?>
                </div>
            <?php } else { ?>
                <?php echo $directorControlador->botonesAccionEditar($rol, $datos['estado']); ?>
            <?php } ?>
        </div>
    </form>
</div>

<?php

$contenido = ob_get_clean();
$titulo = "Editar Director";
$bodyClass = "proyectos-page";

include __DIR__ . '/../../layout.php';
?>