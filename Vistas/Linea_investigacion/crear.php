<?php
// Vistas/Linea_investigacion/crear.php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

if (!isset($_SESSION['id_usuario'])) {
    header('Location: /ITSFCP-PROYECTOS/index.php');
    exit;
}

$rol        = strtolower($_SESSION['rol'] ?? '');
$id_usuario = intval($_SESSION['id_usuario']);

if ($rol !== 'supervisor') {
    header('Location: /ITSFCP-PROYECTOS/Vistas/Principal/index.php');
    exit;
}

require_once '../../Controladores/lineaInvestigacionControlador.php';

$ctrl        = new LineaInvestigacionControlador();
$action      = $_POST['action'] ?? null;
$estadoVista = ['activo' => 0, 'desactivado' => 0];
$mensaje     = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'Registrar') {
    $nombre      = trim($_POST['Nombre']      ?? '');
    $descripcion = trim($_POST['Descripcion'] ?? '');

    if ($nombre !== '' && $descripcion !== '') {
        $estadoVista = $ctrl->verificarLinea($nombre);

        if ($estadoVista['activo'] === 0 && $estadoVista['desactivado'] === 0) {
            // Controlador redirige con ?msg= → no continúa
            $ctrl->registrarLinea($rol, $nombre, $descripcion);
        } else {
            $mensaje = 'Ya existe una línea de investigación con ese nombre. Por favor elige otro.';
        }
    } else {
        $mensaje = 'El nombre y la descripción son obligatorios.';
    }
}

//  Mensajes ─
$msg   = $_GET['msg'] ?? '';
$_mapa = [
    'error_crear'         => ['tipo' => 'error',  'titulo_msg' => 'Error al crear',       'mensaje' => 'No fue posible crear la línea de investigación. Verifica los datos e intenta de nuevo.'],
    'error_duplicado'     => ['tipo' => 'alerta', 'titulo_msg' => 'Registro duplicado',   'mensaje' => 'Ya existe una línea de investigación con ese nombre.'],
    'accion_no_permitida' => ['tipo' => 'alerta', 'titulo_msg' => 'Acción no permitida',  'mensaje' => 'La acción solicitada no está disponible para tu rol.'],
];

ob_start();
?>

<div class="container-fluid py-4 ancho_container">


    <!-- ENCABEZADO -->
    <div class="row mb-3">
        <?php
        $titulo      = 'Nueva Línea de Investigación';
        $descripcion = 'Registro de una nueva línea de investigación';
        include __DIR__ . '../../../publico/incluido/_encabezado.php';
        ?>
        <div class="col-6 text-end">
            <a href="index.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Regresar
            </a>
        </div>
    </div>

    <!-- ALERTAS -->
    <?php
    if (isset($_mapa[$msg])) {
        extract($_mapa[$msg]);
        include __DIR__ . '../../../publico/incluido/_mensaje.php';
    }
    ?>
    <!-- FORMULARIO -->
    <form method="POST" action="">
        <div class="mb-3">
            <label class="form-label">Nombre</label>
            <input
                type="text"
                name="Nombre"
                class="form-control"
                value="<?= htmlspecialchars($_POST['Nombre'] ?? '') ?>"
                required>
        </div>
        <div class="mb-3">
            <label class="form-label">Descripción</label>
            <textarea
                name="Descripcion"
                class="form-control"
                rows="4"
                required><?= htmlspecialchars($_POST['Descripcion'] ?? '') ?></textarea>
        </div>

        <?php if (!empty($mensaje)): ?>
            <div class="alert alert-warning" role="alert">
                <?= htmlspecialchars($mensaje) ?>
            </div>
        <?php endif; ?>

        <button type="submit" name="action" value="Registrar" class="btn btn-guardar">
            Guardar cambios
        </button>
    </form>

</div>

<?php
$contenido = ob_get_clean();
$titulo    = 'Crear Línea de investigación';
$bodyClass = 'proyectos-page';
include __DIR__ . '/../../layout.php';
?>