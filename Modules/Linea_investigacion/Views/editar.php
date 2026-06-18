<?php

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


$id_linea   = (int)($_GET['id_linea'] ?? 0);

if ($rol !== 'supervisor') {
    header("Location: /Modules/Principal/Views/index.php");
    exit;
}

require_once __DIR__ . '/../Controller/linea_investigacion_controller.php';

$ctrl    = new LineaInvestigacionControlador();
$action  = $_POST['action'] ?? null;
$datos   = $ctrl->indexEditar($rol, $id_linea);
$mensaje = '';


//  Procesar acción POST 
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST)) {

    if ($action === 'Guardar') {
        $nombre      = trim($_POST['Nombre']      ?? '');
        $descripcion = trim($_POST['Descripcion'] ?? '');
        $estadoVista = $ctrl->obtenerPorIdDiferente($id_linea, $nombre);

        if ($estadoVista['activo'] === 0 && $estadoVista['desactivado'] === 0) {
            $ctrl->editarLinea($rol, $id_linea, $nombre, $descripcion); // redirige con ?msg=
        } else {
            $mensaje = 'Ya existe una línea de investigación con ese nombre. Por favor elige otro.';
        }

    } elseif ($action === 'Reactivar') {
        $ctrl->reactivar($rol, (int)($_POST['id_linea'] ?? 0)); // redirige con ?msg=

    } elseif ($action === 'Desactivar') {
        $ctrl->eliminar($rol, (int)($_POST['id_linea'] ?? 0)); // redirige con ?msg=
    }
}

//  Mensajes ─
$msg   = $_GET['msg'] ?? '';
$_mapa = [
    'exito_editar'        => ['tipo' => 'exito',  'titulo_msg' => 'Línea actualizada',       'mensaje' => 'La línea de investigación fue editada correctamente.'],
    'exito_desactivar'    => ['tipo' => 'exito',  'titulo_msg' => 'Línea desactivada',        'mensaje' => 'La línea de investigación fue desactivada correctamente.'],
    'exito_reactivar'     => ['tipo' => 'exito',  'titulo_msg' => 'Línea reactivada',         'mensaje' => 'La línea de investigación fue reactivada correctamente.'],
    'error_editar'        => ['tipo' => 'error',  'titulo_msg' => 'Error al editar',          'mensaje' => 'No fue posible editar la línea de investigación. Verifica los datos e intenta de nuevo.'],
    'error_desactivar'    => ['tipo' => 'error',  'titulo_msg' => 'Error al desactivar',      'mensaje' => 'No fue posible desactivar la línea de investigación.'],
    'error_reactivar'     => ['tipo' => 'error',  'titulo_msg' => 'Error al reactivar',       'mensaje' => 'No fue posible reactivar la línea de investigación.'],
    'error_duplicado'     => ['tipo' => 'alerta', 'titulo_msg' => 'Registro duplicado',       'mensaje' => 'Ya existe una línea de investigación con ese nombre.'],
    'accion_no_permitida' => ['tipo' => 'alerta', 'titulo_msg' => 'Acción no permitida',      'mensaje' => 'La acción solicitada no está disponible para tu rol.'],
];

ob_start();
?>

<div class="container-fluid py-4 ancho_container">

    <!-- ENCABEZADO -->
    <div class="row mb-4 align-items-center">
        <?php
        $titulo      = 'Editar Línea de Investigación';
        $descripcion = 'Modificar datos de la línea de investigación';
        include __DIR__ . '/../../../public/incluido/_encabezado.php';
        ?>
        <div class="col-md-6 text-md-end">
            <a href="index.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Regresar
            </a>
        </div>
    </div>

    <!-- ALERTAS -->
    <?php
    if (isset($_mapa[$msg])) {
        extract($_mapa[$msg]);
        include __DIR__ . '/../../../public/incluido/_mensaje.php';
    }
    ?>

    <!-- FORMULARIO -->
    <form method="POST" action="">
        <input type="hidden" name="id_linea" value="<?= (int)$datos['id_linea'] ?>">

        <div class="mb-3">
            <label class="form-label">Nombre</label>
            <input
                type="text"
                name="Nombre"
                class="form-control"
                value="<?= htmlspecialchars($datos['nombre']) ?>"
                required>
        </div>
        <div class="mb-3">
            <label class="form-label">Descripción</label>
            <textarea
                name="Descripcion"
                class="form-control"
                rows="4"
                required><?= htmlspecialchars($datos['descripcion']) ?></textarea>
        </div>

        <div class="mb-3">
            <?php if (!empty($mensaje)): ?>
                <div class="alert alert-warning" role="alert">
                    <?= htmlspecialchars($mensaje) ?>
                </div>
            <?php else: ?>
                <?= $ctrl->botonesAccionEditar($rol, $datos['estado']) ?>
            <?php endif; ?>
        </div>
    </form>

</div>

<?php
$contenido = ob_get_clean();
$titulo    = 'Editar línea de investigación';
$bodyClass = 'proyectos-page';
include __DIR__ . '/../../../layout.php';
?>
