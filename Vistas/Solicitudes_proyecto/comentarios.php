<!--Solicitudes_proyecto/comentarios.php - Página para enviar el motivo de rechazo
     de la solicitud y cambia el estado a rechazado.-->

<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

if (!isset($_SESSION['id_usuario'])) {
    header("Location: /ITSFCP-PROYECTOS/index.php");
    exit;
}

$rol          = strtolower($_SESSION['rol'] ?? '');
$id_usuario   = intval($_SESSION['id_usuario']);
$id_proyectos = $_GET['id_proyectos'] ?? null;
$motivo       = $_GET['motivo']       ?? null;

// Solo supervisor
if ($rol !== 'supervisor') {
    header("Location: /ITSFCP-PROYECTOS/Vistas/Principal/index.php");
    exit;
}

// Validar que llegaron los parámetros mínimos
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

// Procesar el rechazo cuando se envía el formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'actualizarestadoRechazo') {
    $error = $SolicitudesProyectoControlador->actualizarestadoRechazo($_POST, $id_usuario, $rol);
    // Si actualizarestadoRechazo tuvo éxito hace exit() internamente;
    // si retorna algo es un mensaje de error.
}

ob_start();
include __DIR__ . '/../../mensaje.php';
?>

<div class="container-fluid py-4" style="max-width:95%;">
    <div class="row mb-3 align-items-center">
        <div class="row mb-1">

            <div class="col-6">
                <h3>Comentario</h3>
            </div>

            <div class="col-6 text-end">
                <a href="index.php" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> Regresar
                </a>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger mt-2"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

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

                    <input type="hidden" name="tipo"         value="<?= htmlspecialchars($motivo) ?>">
                    <input type="hidden" name="action"       value="actualizarestadoRechazo">
                    <input type="hidden" name="id_proyectos" value="<?= htmlspecialchars($id_proyectos) ?>">
                    <input type="hidden" name="desde"        value="solicitudes">

                    <div class="col-12 text-center">
                        <button type="submit" class="btn btn-danger">Confirmar</button>
                    </div>
                </div>

            </form>
        </div>
    </div>
</div>

<?php
$contenido = ob_get_clean();
$titulo    = "Comentarios";
$bodyClass = "solicitudes-page";
include __DIR__ . '/../../layout.php';
?>