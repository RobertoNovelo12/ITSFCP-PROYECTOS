<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

/* VALIDACIÓN DE SESIÓN */
if (!isset($_SESSION['id_usuario'])) {
    header("Location: /ITSFCP-PROYECTOS/index.php");
    exit;
}

$rol = strtolower($_SESSION['rol'] ?? '');
$id_usuario = intval($_SESSION['id_usuario']);
$id_tipo_documento = $_GET["id_tipo_documento"] ?? null;

if (strtolower($_SESSION['rol'] ?? '') !== 'supervisor') {
    header("Location: /ITSFCP-PROYECTOS/Vistas/Principal/index.php");
    exit;
}

$action = $_POST['action'] ?? null;
require_once '../../Controladores/ajustesTiposDocumentoscontrolador.php';

$ajustesTiposDocumentoscontrolador = new ajustesTiposDocumentoscontrolador();
$datos = $ajustesTiposDocumentoscontrolador->indexEditar($rol, $id_tipo_documento);
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST) && $action == 'Guardar') {

    $ajustesTiposDocumentoscontrolador->editar($rol, $_POST);
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST) && $action == 'Reactivar') {
    $ajustesTiposDocumentoscontrolador->reactivar($rol, $id_tipo_documento);
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST) && $action == 'Desactivar') {
    $ajustesTiposDocumentoscontrolador->desactivar($rol, $id_tipo_documento);
}
ob_start();
include __DIR__ . '/../../mensaje.php';
include __DIR__ . '/../../error.php';
?>


<div class="container-fluid py-4">

    <!-- ENCABEZADO -->
    <div class="row mb-4 align-items-center">

        <div class="col-md-6">
            <h3 class="fw-bold mb-0">Editar Tipo de documento</h3>
        </div>

        <div class="col-md-6 text-md-end">
            <a href="index.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Regresar
            </a>
        </div>

    </div>

    <!-- DATOS TIPO DE DOCUMENTO -->
    <form method="POST" action="">
        <input type="hidden" name="id_tipo" value="<?= $datos['id_tipo_documento']; ?>">
        <div class="mb-3">
            <label class="form-label">Orden</label>
            <input
                type="number"
                name="Orden"
                class="form-control"
                value="<?= $datos['orden']; ?>"
                required>
        </div>
        <div class="mb-3">
            <label class="form-label">Descripción</label>
            <textarea
                name="Descripcion"
                class="form-control"
                required><?= $datos['descripcion']; ?></textarea>
        </div>
        <div class="mb-3">
            <?php echo $ajustesTiposDocumentoscontrolador->botonesAccionEditar($rol, $datos['estados']); ?>
        </div>
    </form>
</div>


<?php

$contenido = ob_get_clean();
$titulo = "Crear Línea de investigación";
$bodyClass = "proyectos-page";

include __DIR__ . '/../../layout.php';
?>