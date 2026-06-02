<?php
// Vistas/Niveles_SNI/crear.php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

if (!isset($_SESSION['id_usuario'])) {
    header('Location: /index.php');
    exit;
}

$rol        = strtolower($_SESSION['rol'] ?? '');
$id_usuario = intval($_SESSION['id_usuario']);

if ($rol !== 'supervisor') {
    header('Location: /Vistas/Principal/index.php');
    exit;
}

require_once __DIR__ .  '/../../Controladores/nivelsniControlador.php';

$ctrl        = new NivelsniControlador();
$action      = $_POST['action'] ?? null;
$estadoVista = ['activo' => 0, 'desactivado' => 0];
$mensaje     = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'Registrar') {
    $nombre = trim($_POST['Nombre'] ?? '');

    if ($nombre !== '') {
        $estadoVista = $ctrl->verificarNivelSNI($nombre);

        if ($estadoVista['activo'] === 0 && $estadoVista['desactivado'] === 0) {
            // Controlador redirige con ?msg= → no continúa
            $ctrl->registrarNivelSNI($rol, $nombre);
        } else {
            $mensaje = 'Ya existe un Nivel SNI con ese nombre. Por favor elige otro.';
        }
    } else {
        $mensaje = 'El nombre es obligatorio.';
    }
}

//  Mensajes ─
$msg   = $_GET['msg'] ?? '';
$_mapa = [
    'error_crear'         => ['tipo' => 'error',  'titulo_msg' => 'Error al crear',      'mensaje' => 'No fue posible crear el Nivel SNI. Verifica los datos e intenta de nuevo.'],
    'error_duplicado'     => ['tipo' => 'alerta', 'titulo_msg' => 'Registro duplicado',  'mensaje' => 'Ya existe un Nivel SNI con ese nombre.'],
    'accion_no_permitida' => ['tipo' => 'alerta', 'titulo_msg' => 'Acción no permitida', 'mensaje' => 'La acción solicitada no está disponible para tu rol.'],
];

ob_start();
?>

<div class="container-fluid py-4 ancho_container">

    <!-- ALERTAS -->
    <?php
    if (isset($_mapa[$msg])) {
        extract($_mapa[$msg]);
        include __DIR__ . '../../../publico/incluido/_mensaje.php';
    }
    ?>

    <!-- ENCABEZADO -->
    <div class="row mb-3">
        <?php
        $titulo      = 'Nuevo Nivel SNI';
        $descripcion = 'Registro de un nuevo nivel SNI';
        include __DIR__ . '../../../publico/incluido/_encabezado.php';
        ?>
        <div class="col-6 text-end">
            <a href="index.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Regresar
            </a>
        </div>
    </div>

    <!-- FORMULARIO -->
    <form method="POST" action="">
        <div class="mb-3">
            <label class="form-label">Nivel SNI</label>
            <input
                type="text"
                name="Nombre"
                class="form-control"
                value="<?= htmlspecialchars($_POST['Nombre'] ?? '') ?>"
                required>
        </div>

        <?php if (!empty($mensaje)): ?>
            <div class="alert alert-warning" role="alert">
                <?= htmlspecialchars($mensaje) ?>
            </div>
        <?php endif; ?>

        <button type="submit" name="action" value="Registrar" class="btn btn-guardar"><i class="bi bi-floppy me-1"></i> Guardar cambios</button>
    </form>

</div>

<?php
$contenido = ob_get_clean();
$titulo    = 'Crear Nivel SNI';
$bodyClass = 'proyectos-page';
include __DIR__ . '/../../layout.php';
?>
