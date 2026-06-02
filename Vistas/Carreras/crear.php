<?php
// Carreras/crear.php

session_start();

if (!isset($_SESSION['id_usuario'])) {
    header("Location: /index.php");
    exit;
}

$rol        = strtolower($_SESSION['rol'] ?? '');
$id_usuario = (int)$_SESSION['id_usuario'];

if ($rol !== 'supervisor') {
    header("Location: /Vistas/Principal/index.php");
    exit;
}

require_once __DIR__ .  '/../../Controladores/carreraControlador.php';

//  Acción POST: registrar carrera ─
// registrarCarrera() valida método, rol y redirige internamente con ?msg=.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'Registrar') {
    $carreraControlador = new carreraControlador();
    $carreraControlador->registrarCarrera($rol, $_POST);
    // Siempre redirige → el código no continúa.
}

//  Mapa de mensajes ─
$msg   = $_GET['msg'] ?? '';
$_mapa = [
    'exito_crear'         => ['tipo' => 'exito',  'titulo_msg' => 'Carrera creada',       'mensaje' => 'La carrera fue creada correctamente.'],
    'error_crear'         => ['tipo' => 'error',  'titulo_msg' => 'Error al crear',        'mensaje' => 'No fue posible crear la carrera. Verifica los datos e intenta de nuevo.'],
    'error_duplicado'     => ['tipo' => 'error',  'titulo_msg' => 'Registro duplicado',    'mensaje' => 'Ya existe una carrera con ese nombre.'],
    'accion_no_permitida' => ['tipo' => 'alerta', 'titulo_msg' => 'Acción no permitida',   'mensaje' => 'La acción solicitada no está disponible para tu rol.'],
];

ob_start();
?>

<?php if (isset($_mapa[$msg])): extract($_mapa[$msg]); include __DIR__ . '../../../publico/incluido/_mensaje.php'; endif; ?>

<div class="container-fluid py-4 ancho_container">

    <!-- ENCABEZADO -->
    <div class="row mb-3 align-items-center">
        <?php
        $titulo      = 'Nueva Carrera';
        $descripcion = 'Registro de una nueva carrera';
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
            <label class="form-label">Nombre de la Carrera</label>
            <input
                type="text"
                name="NombreCarrera"
                class="form-control"
                required>
        </div>

        <button type="submit" name="action" value="Registrar" class="btn btn-guardar"><i class="bi bi-floppy me-1"></i> Guardar cambios</button>

    </form>
</div>

<?php
$contenido = ob_get_clean();
$titulo    = 'Crear Carrera';
$bodyClass = 'proyectos-page';
include __DIR__ . '/../../layout.php';
?>