<?php
// Vistas/Director/crear.php

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

if ($rol !== 'supervisor') {
    header("Location: /ITSFCP-PROYECTOS/Vistas/Principal/index.php");
    exit;
}

require_once __DIR__ .  '/../../Controladores/directorControlador.php';

$directorControlador = new directorControlador();
$grados              = $directorControlador->obtenerGrados($rol);

//  Mapa de mensajes 
$msg = $_GET['msg'] ?? '';
$_mapa = [
    'error_duplicado'     => ['tipo' => 'alerta', 'titulo_msg' => 'Registro duplicado',  'mensaje' => 'Ya existe un director con ese correo. Intenta con otro.'],
    'error_crear'         => ['tipo' => 'error',  'titulo_msg' => 'Error al crear',       'mensaje' => 'No fue posible registrar el director. Verifica los datos e intenta de nuevo.'],
    'accion_no_permitida' => ['tipo' => 'alerta', 'titulo_msg' => 'Acción no permitida',  'mensaje' => 'La acción solicitada no está disponible para tu rol.'],
];

//  Procesar POST 
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'Registrar') {
    $directorControlador->registrarDirector(
        $rol,
        $_POST['IdGrado']       ?? '',
        $_POST['Nombre']        ?? '',
        $_POST['Apellido']      ?? '',
        $_POST['Correo']        ?? null,
        $_POST['Telefono']      ?? null,
        $_POST['Fecha_inicio']  ?? null,
        $_POST['Fecha_final']   ?? null
    );
    // registrarDirector() siempre redirige;
}

ob_start();
?>

<!-- ALERTAS -->
<?php if (isset($_mapa[$msg])):
    extract($_mapa[$msg]);
    include __DIR__ . '../../../publico/incluido/_mensaje.php';
endif; ?>

<div class="container-fluid py-4 ancho_container">

    <!-- ENCABEZADO -->
    <div class="row mb-3 align-items-center">
        <?php
        $titulo      = 'Nuevo Director';
        $descripcion = 'Registro de un nuevo director';
        include __DIR__ . '../../../publico/incluido/_encabezado.php';
        ?>
        <div class="col-6 text-end">
            <a href="index.php" class="btn btn-danger">Regresar</a>
        </div>
    </div>

    <!-- FORMULARIO -->
    <form method="POST" action="">
        <input type="hidden" name="action" value="Registrar">

        <div class="mb-3">
            <label class="form-label">Grado Académico</label>
            <select name="IdGrado" class="form-select" required>
                <option value="">-- Selecciona un grado --</option>
                <?php foreach ($grados as $grado): ?>
                    <option value="<?= (int)$grado['id_grado'] ?>">
                        <?= htmlspecialchars($grado['nombre']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="mb-3">
            <label class="form-label">Nombre</label>
            <input type="text" name="Nombre" class="form-control" required>
        </div>

        <div class="mb-3">
            <label class="form-label">Apellido</label>
            <input type="text" name="Apellido" class="form-control" required>
        </div>

        <div class="mb-3">
            <label class="form-label">Correo</label>
            <input type="email" name="Correo" class="form-control">
        </div>

        <div class="mb-3">
            <label class="form-label">Teléfono</label>
            <input type="text" name="Telefono" class="form-control" maxlength="10">
        </div>

        <div class="mb-3">
            <label class="form-label">Fecha inicio</label>
            <input type="date" name="Fecha_inicio" class="form-control">
        </div>

        <div class="mb-3">
            <label class="form-label">Fecha final</label>
            <input type="date" name="Fecha_final" class="form-control">
        </div>

        <button type="submit" class="btn btn-guardar">Guardar cambios</button>
    </form>

</div>

<?php
$contenido = ob_get_clean();
$titulo    = 'Crear Director';
$bodyClass = 'proyectos-page';
include __DIR__ . '/../../layout.php';
