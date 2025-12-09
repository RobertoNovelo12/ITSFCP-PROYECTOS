<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

if (!isset($_SESSION['id_usuario'])) {
    header("Location: /ITSFCP-PROYECTOS/index.php");
    exit;
}
$rol = $_SESSION['rol'];
$id = $_SESSION['id_usuario'];
$action = $_POST['action'] ?? null;

$id_proyectos = $_GET['id_proyectos'] ?? null;
$motivo = $_GET['motivo'] ?? null;

if ($motivo == "cierre_rechazado") {
    $texto_motivo = "Cierre rechazado";
} else if ($motivo == "creacion_rechazada") {
    $texto_motivo = "Cierre rechazado";
}
//Se llama al controlador

require_once '..\..\Controladores\proyectoControlador.php';

$proyectoControlador = new ProyectoControlador();

// Actualización de estados
if ($_SERVER['REQUEST_METHOD'] == 'POST' && ($_POST['action'] ?? '') == 'actualizarestadoRechazo' && $rol == "supervisor") {
    $proyectoControlador->actualizarestadoRechazo($_POST, $id, $rol);
}
ob_start();
include __DIR__ . '/../../mensaje.php';
?>
<div class="container-fluid py-4">
    <div class="row mb-3 align-items-center">
        <div class="row mb-1">
            <div class="col-6">
                <h3>Comentario</h3>
            </div>
            <div class="col-6 col-md-6 text-md-end mb-2 mb-md-0 text-end">
                <a href="tabla.php" class="btn btn-danger w-100 w-md-auto">Regresar</a>
            </div>
            <form method="POST" action="comentarios.php">
                <div class="row mb-1">
                    <div class="mb-3">
                        <label for="exampleFormControlInput1" class="form-label">Motivo</label>
                        <input type="text" class="form-control" id="exampleFormControlInput1" value="<?php echo $texto_motivo ?? null; ?>" disabled>
                    </div>
                    <div class="mb-3">
                        <label for="exampleFormControlTextarea1" class="form-label">Comentario</label>
                        <textarea class="form-control" name="comentario" id="InputFormLimpiar2" rows="3" required></textarea>
                    </div>
                    <input type="hidden" name="tipo" value="<?php echo $motivo; ?>">
                    <input type="hidden" name="action" value="actualizarestadoRechazo">
                    <input type="hidden" name="id_proyectos" value="<?php echo $id_proyectos; ?>">
                    <div class="row mb-1">
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
$titulo = "Comentarios";
$bodyClass = "proyectos-page";

include __DIR__ . '/../../layout.php';
?>
