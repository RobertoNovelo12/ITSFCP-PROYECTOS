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

require_once '../../Controladores/directorControlador.php';

$action = $_POST['action'] ?? null;
$directorControlador = new directorControlador();
$estadoVista = ["activo" => 0, "desactivado" => 0];
$mensaje = "";

// Obtener grados académicos activos para el select
$grados = $directorControlador->obtenerGrados($rol);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST) && $action === 'Registrar') {
    $id_grado  = $_POST['IdGrado'];
    $nombre    = $_POST['Nombre'];
    $apellido  = $_POST['Apellido'];
    $correo    = $_POST['Correo'] ?? null;
    $telefono  = $_POST['Telefono'] ?? null;
    $Fecha_inicio  = $_POST['Fecha_inicio'] ?? null;
    $Fecha_final  = $_POST['Fecha_final'] ?? null;

    // Verificar duplicado por correo (campo UNIQUE de la tabla)
    if (!empty($correo)) {
        $estadoVista = $directorControlador->verificarDirector($correo);
    }

    if ($estadoVista['activo'] == 0 && $estadoVista['desactivado'] == 0) {
        $directorControlador->registrarDirector($rol, $id_grado, $nombre, $apellido, $correo, $telefono, $Fecha_inicio, $Fecha_final);
    } else {
        $mensaje = "Ya existe un director con ese correo, intente con otro";
    }
}

ob_start();
include __DIR__ . '/../../mensaje.php';
include __DIR__ . '/../../error.php';
?>

<div class="container-fluid py-4">
    <!-- ENCABEZADO -->
    <div class="row mb-3">
        <div class="col-6">
            <h3>Crear Director</h3>
        </div>
        <div class="col-6 text-end">
            <a href="tabla.php" class="btn btn-danger">Regresar</a>
        </div>
    </div>
    <!-- DATOS DIRECTOR -->
    <form method="POST" action="">
        <input type="hidden" name="action" value="Registrar">

        <div class="mb-3">
            <label class="form-label">Grado Académico</label>
            <select name="IdGrado" class="form-select" required>
                <option value="">-- Selecciona un grado --</option>
                <?php foreach ($grados as $grado): ?>
                    <option value="<?= $grado['id_grado'] ?>">
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
                required>
        </div>

        <div class="mb-3">
            <label class="form-label">Apellido</label>
            <input
                type="text"
                name="Apellido"
                class="form-control"
                required>
        </div>

        <div class="mb-3">
            <label class="form-label">Correo</label>
            <input
                type="email"
                name="Correo"
                class="form-control">
        </div>

        <div class="mb-3">
            <label class="form-label">Teléfono</label>
            <input
                type="text"
                name="Telefono"
                class="form-control"
                maxlength="10">
        </div>

        <div class="mb-3">
            <label class="form-label">Fecha inicio</label>
            <input
                type="date"
                name="Fecha_inicio"
                class="form-control"
                maxlength="10">
        </div>

        <div class="mb-3">
            <label class="form-label">Fecha final</label>
            <input
                type="date"
                name="Fecha_final"
                class="form-control"
                maxlength="10">
        </div>

        <?php if (!empty($mensaje)) { ?>
            <div class="alert alert-warning" role="alert">
                <?= $mensaje ?>
            </div>
        <?php } ?>
        <button type="submit" name="action" value="Registrar" class="btn btn-guardar">Guardar cambios</button>
    </form>
</div>

<?php

$contenido = ob_get_clean();
$titulo = "Crear Director";
$bodyClass = "proyectos-page";

include __DIR__ . '/../../layout.php';
?>