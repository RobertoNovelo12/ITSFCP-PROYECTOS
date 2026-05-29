<?php
// Solicitudes_proyecto/comentarios.php
// Formulario para enviar el motivo de rechazo de la solicitud y cambiar el estado a rechazado.

session_start();

if (!isset($_SESSION['id_usuario'])) {
    header("Location: /ITSFCP-PROYECTOS/index.php");
    exit;
}

$rol          = strtolower($_SESSION['rol'] ?? '');
$id_usuario   = intval($_SESSION['id_usuario']);

require_once '../../../publico/incluido/_validar_get.php';


$id_proyectos = $_GET['id_proyectos'] ?? null;

//Validación de argumentos en url
$id_validar = $id_proyectos;
require_once '../../../publico/incluido/_validar_id.php';

$motivo = $_GET['motivo']       ?? null;

//Validación de argumentos en url
$id_validar = $motivo;
require_once '../../../publico/incluido/_validar_id.php';

if ($rol !== 'supervisor') {
    header("Location: /ITSFCP-PROYECTOS/Vistas/Principal/index.php");
    exit;
}

if (!$id_proyectos || !$motivo) {
    header("Location: index.php");
    exit;
}

$texto_motivo = match ($motivo) {
    'cierre_rechazado'   => 'Cierre rechazado',
    'creacion_rechazada' => 'Creación rechazada',
    default              => 'Rechazo',
};

require_once '../../Controladores/solicitudes_proyectoControlador.php';
$SolicitudesProyectoControlador = new SolicitudesProyectoControlador();

// 
// ACCIÓN POST — antes de cualquier output
// 
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'actualizarestadoRechazo') {
    $SolicitudesProyectoControlador->actualizarestadoRechazo($_POST, $id_usuario, $rol);
    // actualizarestadoRechazo() siempre llama redirigir() → exit.
}

// 
// MAPA DE ALERTAS
// 
$msg   = $_GET['msg'] ?? '';
$_mapa = [
    'exito_rechazo'       => ['tipo' => 'exito',  'titulo_msg' => 'Rechazo registrado',  'mensaje' => 'El rechazo fue registrado y el investigador fue notificado.'],
    'error_rechazo'       => ['tipo' => 'error',  'titulo_msg' => 'Error en el rechazo', 'mensaje' => 'No fue posible registrar el rechazo. Verifica los datos e intenta de nuevo.'],
    'accion_no_permitida' => ['tipo' => 'alerta', 'titulo_msg' => 'Acción no permitida', 'mensaje' => 'La acción solicitada no está disponible para tu rol.'],
];

ob_start();
?>

<div class="container-fluid py-4 ancho_container">
    <div class="row mb-3 align-items-center">
        <div class="row mb-1">
            <?php
            $titulo      = 'Comentarios del Supervisor';
            $descripcion = 'Observaciones del supervisor al investigador';
            include __DIR__ . '../../../publico/incluido/_encabezado.php';
            ?>
            <div class="col-6 text-end">
                <a href="index.php" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> Regresar
                </a>
            </div>
        </div>

        <!-- ALERTAS -->
        <?php if (isset($_mapa[$msg])) : extract($_mapa[$msg]); include __DIR__ . '../../../publico/incluido/_mensaje.php'; endif; ?>

        <form method="POST" action="comentarios.php?id_proyectos=<?= htmlspecialchars($id_proyectos) ?>&motivo=<?= htmlspecialchars($motivo) ?>">
            <div class="row mb-1">
                <div class="mb-3">
                    <label class="form-label">Motivo</label>
                    <input type="text" class="form-control"
                        value="<?= htmlspecialchars($texto_motivo) ?>" disabled>
                </div>
                <div class="mb-3">
                    <label class="form-label">Comentario</label>
                    <textarea class="form-control" name="comentario" rows="3" required></textarea>
                </div>
                <input type="hidden" name="tipo"          value="<?= htmlspecialchars($motivo) ?>">
                <input type="hidden" name="action"        value="actualizarestadoRechazo">
                <input type="hidden" name="id_proyectos"  value="<?= htmlspecialchars($id_proyectos) ?>">
                <input type="hidden" name="desde"         value="solicitudes">
                <div class="col-12 text-center">
                    <button type="submit" class="btn btn-danger">Confirmar</button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php
$contenido = ob_get_clean();
$titulo    = "Comentarios";
$bodyClass = "solicitudes-page";
include __DIR__ . '/../../layout.php';
?>