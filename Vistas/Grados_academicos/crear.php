<?php
// Vistas/Grados_academicos/crear.php

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

require_once __DIR__ .  '/../../Controladores/gradoacademicoControlador.php';

$gradoacademicoControlador = new gradoacademicoControlador();

// ── Mapa de mensajes ──
$msg   = $_GET['msg'] ?? '';
$_mapa = [
    'error_duplicado'     => ['tipo' => 'alerta', 'titulo_msg' => 'Registro duplicado', 'mensaje' => 'Ya existe un grado académico con ese nombre. Intenta con otro.'],
    'error_crear'         => ['tipo' => 'error',  'titulo_msg' => 'Error al crear',      'mensaje' => 'No fue posible registrar el grado académico. Verifica los datos e intenta de nuevo.'],
    'accion_no_permitida' => ['tipo' => 'alerta', 'titulo_msg' => 'Acción no permitida', 'mensaje' => 'La acción solicitada no está disponible para tu rol.'],
];

// ── Procesar POST ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'Registrar') {
    $gradoacademicoControlador->registrarGradoAcademico($rol, $_POST['Nombre'] ?? '');
    // registrarGradoAcademico() siempre redirige; no llega aquí.
}

ob_start();
?>



<div class="container-fluid py-4 ancho_container">

    <!-- ENCABEZADO -->
    <div class="row mb-3 align-items-center">
        <?php
        $titulo      = 'Nuevo Grado Académico';
        $descripcion = 'Registro de un nuevo grado académico';
        include __DIR__ . '../../../publico/incluido/_encabezado.php';
        ?>
        <div class="col-6 text-end">
            <a href="index.php" class="btn btn-secondary"><i class="bi bi-arrow-left me-1"></i> Regresar</a>
        </div>
    </div>
    <!-- ALERTAS -->
    <?php if (isset($_mapa[$msg])):
        extract($_mapa[$msg]);
        include __DIR__ . '../../../publico/incluido/_mensaje.php';
    endif; ?>
    <!-- FORMULARIO -->
    <form method="POST" action="">
        <input type="hidden" name="action" value="Registrar">

        <div class="mb-3">
            <label class="form-label">Grado Académico</label>
            <input type="text" name="Nombre" class="form-control" required>
        </div>

        <button type="submit" class="btn btn-guardar"><i class="bi bi-floppy me-1"></i> Guardar cambios</button>
    </form>

</div>

<?php
$contenido = ob_get_clean();
$titulo    = 'Crear Grado Académico';
$bodyClass = 'proyectos-page';
include __DIR__ . '/../../layout.php';
