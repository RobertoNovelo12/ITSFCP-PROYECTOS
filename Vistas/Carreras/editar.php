<?php
// Carreras/editar.php

session_start();

if (!isset($_SESSION['id_usuario'])) {
    header("Location: /index.php");
    exit;
}

$rol        = strtolower($_SESSION['rol'] ?? '');
$id_usuario = (int)$_SESSION['id_usuario'];


$id_carrera = (int)($_GET['id_carrera'] ?? 0);


if ($rol !== 'supervisor') {
    header("Location: /Vistas/Principal/index.php");
    exit;
}

require_once __DIR__ .  '/../../Controladores/carreraControlador.php';

$carreraControlador = new carreraControlador();

//  Acciones POST 
// Cada método valida internamente el método HTTP, el rol y redirige con ?msg=.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'Guardar') {
        $carreraControlador->editarCarrera($rol, $_POST);
    } elseif ($action === 'Reactivar') {
        $carreraControlador->reactivarCarrera($rol, $_POST);
    } elseif ($action === 'Desactivar') {
        $carreraControlador->desactivarCarrera($rol, (int)($_POST['id_carrera'] ?? 0));
    }
    // Si alguna acción no redirigió (valor desconocido), la página recarga normalmente.
}

//  Cargar datos actuales de la carrera 
$datos = $carreraControlador->indexEditar($rol, $id_carrera);

//  Mapa de mensajes ─
$msg   = $_GET['msg'] ?? '';
$_mapa = [
    'exito_editar'        => ['tipo' => 'exito',  'titulo_msg' => 'Carrera actualizada',    'mensaje' => 'La carrera fue editada correctamente.'],
    'exito_desactivar'    => ['tipo' => 'exito',  'titulo_msg' => 'Carrera desactivada',    'mensaje' => 'La carrera fue desactivada correctamente.'],
    'exito_reactivar'     => ['tipo' => 'exito',  'titulo_msg' => 'Carrera reactivada',     'mensaje' => 'La carrera fue reactivada correctamente.'],
    'error_editar'        => ['tipo' => 'error',  'titulo_msg' => 'Error al editar',         'mensaje' => 'No fue posible editar la carrera. Verifica los datos e intenta de nuevo.'],
    'error_desactivar'    => ['tipo' => 'error',  'titulo_msg' => 'Error al desactivar',     'mensaje' => 'No fue posible desactivar la carrera.'],
    'error_reactivar'     => ['tipo' => 'error',  'titulo_msg' => 'Error al reactivar',      'mensaje' => 'No fue posible reactivar la carrera.'],
    'error_duplicado'     => ['tipo' => 'error',  'titulo_msg' => 'Registro duplicado',      'mensaje' => 'Ya existe una carrera con ese nombre.'],
    'error_cargar'        => ['tipo' => 'error',  'titulo_msg' => 'Error al cargar',         'mensaje' => 'No se encontró la carrera solicitada.'],
    'accion_no_permitida' => ['tipo' => 'alerta', 'titulo_msg' => 'Acción no permitida',     'mensaje' => 'La acción solicitada no está disponible para tu rol.'],
];

ob_start();
?>

<?php if (isset($_mapa[$msg])): extract($_mapa[$msg]); include __DIR__ . '../../../publico/incluido/_mensaje.php'; endif; ?>

<div class="container-fluid py-4 ancho_container">

    <!-- ENCABEZADO -->
    <div class="row mb-4 align-items-center">
        <?php
        $titulo      = 'Editar Carrera';
        $descripcion = 'Modificar datos de la carrera';
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

        <input type="hidden" name="id_carrera" value="<?= (int)$datos['id_carrera'] ?>">

        <div class="mb-3">
            <label class="form-label">Nombre de la Carrera</label>
            <input
                type="text"
                name="NombreCarrera"
                class="form-control"
                value="<?= htmlspecialchars($datos['nombre_carrera']) ?>"
                required>
        </div>

        <div class="mb-3">
            <?= $carreraControlador->botonesAccionEditar($rol, $datos['estado']) ?>
        </div>

    </form>
</div>

<?php
$contenido = ob_get_clean();
$titulo    = 'Editar Carrera';
$bodyClass = 'proyectos-page';
include __DIR__ . '/../../layout.php';
?>