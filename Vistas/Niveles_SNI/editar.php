<?php
// Vistas/Niveles_SNI/editar.php

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

include __DIR__ .  '../../../publico/incluido/_validar_get.php';

if ($rol !== 'supervisor') {
    header('Location: /ITSFCP-PROYECTOS/Vistas/Principal/index.php');
    exit;
}

$id_nivel   = (int)($_GET['id_nivel'] ?? 0);



$id_validar = $id_nivel;
include __DIR__ .  '../../../publico/incluido/_validar_id.php';


require_once __DIR__ .  '/../../Controladores/nivelsniControlador.php';

$ctrl    = new NivelsniControlador();
$action  = $_POST['action'] ?? null;
$datos   = $ctrl->indexEditar($rol, $id_nivel);
$mensaje = '';

// Validación
$registro = $datos;
include __DIR__ .  '../../../publico/incluido/_validar_datos.php';


//  Procesar acción POST 
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST)) {

    if ($action === 'Guardar') {
        $nombre      = trim($_POST['Nombre'] ?? '');
        $estadoVista = $ctrl->obtenerPorIdDiferente($id_nivel, $nombre);

        if ($estadoVista['activo'] === 0 && $estadoVista['desactivado'] === 0) {
            $ctrl->editarNivelSNI($rol, $id_nivel, $nombre); // redirige con ?msg=
        } else {
            $mensaje = 'Ya existe un Nivel SNI con ese nombre. Por favor elige otro.';
        }

    } elseif ($action === 'Reactivar') {
        $ctrl->reactivar($rol, (int)($_POST['id_nivel'] ?? 0)); // redirige con ?msg=

    } elseif ($action === 'Desactivar') {
        $ctrl->eliminar($rol, (int)($_POST['id_nivel'] ?? 0)); // redirige con ?msg=
    }
}

//  Mensajes ─
$msg   = $_GET['msg'] ?? '';
$_mapa = [
    'exito_editar'        => ['tipo' => 'exito',  'titulo_msg' => 'Nivel SNI actualizado',  'mensaje' => 'El Nivel SNI fue editado correctamente.'],
    'exito_desactivar'    => ['tipo' => 'exito',  'titulo_msg' => 'Nivel SNI desactivado',  'mensaje' => 'El Nivel SNI fue desactivado correctamente.'],
    'exito_reactivar'     => ['tipo' => 'exito',  'titulo_msg' => 'Nivel SNI reactivado',   'mensaje' => 'El Nivel SNI fue reactivado correctamente.'],
    'error_editar'        => ['tipo' => 'error',  'titulo_msg' => 'Error al editar',        'mensaje' => 'No fue posible editar el Nivel SNI. Verifica los datos e intenta de nuevo.'],
    'error_desactivar'    => ['tipo' => 'error',  'titulo_msg' => 'Error al desactivar',    'mensaje' => 'No fue posible desactivar el Nivel SNI.'],
    'error_reactivar'     => ['tipo' => 'error',  'titulo_msg' => 'Error al reactivar',     'mensaje' => 'No fue posible reactivar el Nivel SNI.'],
    'error_duplicado'     => ['tipo' => 'alerta', 'titulo_msg' => 'Registro duplicado',     'mensaje' => 'Ya existe un Nivel SNI con ese nombre.'],
    'accion_no_permitida' => ['tipo' => 'alerta', 'titulo_msg' => 'Acción no permitida',    'mensaje' => 'La acción solicitada no está disponible para tu rol.'],
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

    <!-- FORMULARIO -->
    <form method="POST" action="">
        <input type="hidden" name="id_nivel" value="<?= (int)$datos['id_nivel'] ?>">

        <div class="mb-3">
            <label class="form-label">Nivel SNI</label>
            <input
                type="text"
                name="Nombre"
                class="form-control"
                value="<?= htmlspecialchars($datos['nombre']) ?>"
                required>
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
$titulo    = 'Editar Nivel SNI';
$bodyClass = 'proyectos-page';
include __DIR__ . '/../../layout.php';
?>
