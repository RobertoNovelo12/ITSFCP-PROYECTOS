<?php
// Vistas/Grados_academicos/editar.php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

if (!isset($_SESSION['id_usuario'])) {
    header("Location: /ITSFCP-PROYECTOS/index.php");
    exit;
}

$rol        = strtolower($_SESSION['rol'] ?? '');
$id_usuario = intval($_SESSION['id_usuario']);

include __DIR__ .  '../../../publico/incluido/_validar_get.php';


if ($rol !== 'supervisor') {
    header("Location: /ITSFCP-PROYECTOS/Vistas/Principal/index.php");
    exit;
}

$id_grado   = $_GET['id_grado'] ?? null;

//Validación de argumentos en url
$id_validar = $id_grado;
include __DIR__ .  '../../../publico/incluido/_validar_id.php';


require_once __DIR__ .  '/../../Controladores/gradoacademicoControlador.php';

$gradoacademicoControlador = new gradoacademicoControlador();
$datos = $gradoacademicoControlador->indexEditar($rol, $id_grado);

$registro = $datos;
include __DIR__ .  '../../../publico/incluido/_validar_datos.php';


//  Mapa de mensajes 
$msg   = $_GET['msg'] ?? '';
$_mapa = [
    'exito_editar'        => ['tipo' => 'exito',  'titulo_msg' => 'Grado actualizado',   'mensaje' => 'El grado académico fue editado correctamente.'],
    'exito_desactivar'    => ['tipo' => 'exito',  'titulo_msg' => 'Grado desactivado',    'mensaje' => 'El grado académico fue desactivado correctamente.'],
    'exito_reactivar'     => ['tipo' => 'exito',  'titulo_msg' => 'Grado reactivado',     'mensaje' => 'El grado académico fue reactivado correctamente.'],
    'error_editar'        => ['tipo' => 'error',  'titulo_msg' => 'Error al editar',      'mensaje' => 'No fue posible editar el grado académico. Verifica los datos e intenta de nuevo.'],
    'error_desactivar'    => ['tipo' => 'error',  'titulo_msg' => 'Error al desactivar',  'mensaje' => 'No fue posible desactivar el grado académico.'],
    'error_reactivar'     => ['tipo' => 'error',  'titulo_msg' => 'Error al reactivar',   'mensaje' => 'No fue posible reactivar el grado académico.'],
    'error_duplicado'     => ['tipo' => 'alerta', 'titulo_msg' => 'Registro duplicado',   'mensaje' => 'Ya existe un grado académico con ese nombre. Intenta con otro.'],
    'accion_no_permitida' => ['tipo' => 'alerta', 'titulo_msg' => 'Acción no permitida',  'mensaje' => 'La acción solicitada no está disponible para tu rol.'],
];

//  Procesar POST 
$action = $_POST['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'Guardar') {
        $gradoacademicoControlador->editarGradoAcademico(
            $rol,
            $_POST['id_grado'] ?? 0,
            $_POST['Nombre']   ?? ''
        );
        // editarGradoAcademico() siempre redirige; no llega aquí.
    } elseif ($action === 'Reactivar') {
        $gradoacademicoControlador->reactivar($rol, $_POST['id_grado'] ?? 0);
    } elseif ($action === 'Desactivar') {
        $gradoacademicoControlador->eliminar($rol, $_POST['id_grado'] ?? 0);
    }
}

ob_start();
?>



<div class="container-fluid py-4 ancho_container">

    <!-- ENCABEZADO -->
    <div class="row mb-4 align-items-center">
        <?php
        $titulo      = 'Editar Grado Académico';
        $descripcion = 'Modificar datos del grado académico';
        include __DIR__ . '../../../publico/incluido/_encabezado.php';
        ?>
        <div class="col-md-6 text-md-end">
            <a href="index.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Regresar
            </a>
        </div>
    </div>
    <!-- ALERTAS -->
    <?php if (isset($_mapa[$msg])):
        extract($_mapa[$msg]);
        include __DIR__ . '../../../publico/incluido/_mensaje.php';
    endif; ?>
    <!-- FORMULARIO -->
    <form method="POST" action="">
        <input type="hidden" name="id_grado" value="<?= (int)$datos['id_grado'] ?>">

        <div class="mb-3">
            <label class="form-label">Grado Académico</label>
            <input type="text" name="Nombre" class="form-control"
                value="<?= htmlspecialchars($datos['nombre']) ?>" required>
        </div>

        <div class="mb-3 d-flex gap-2">
            <?= $gradoacademicoControlador->botonesAccionEditar($rol, $datos['estado']) ?>
        </div>

    </form>

</div>

<?php
$contenido = ob_get_clean();
$titulo    = 'Editar Grado Académico';
$bodyClass = 'proyectos-page';
include __DIR__ . '/../../layout.php';
