<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

/* VALIDACIÓN DE SESIÓN */
if (!isset($_SESSION['id_usuario'])) {
    header("Location: /index.php");
    exit;
}

$rol = strtolower($_SESSION['rol'] ?? '');
$id_usuario = intval($_SESSION['id_usuario']);


if ($rol !== 'supervisor') {
    header("Location: /Modules/Principal/Views/index.php");
    exit;
}

$id_tipo_documento = $_GET["id_tipo_documento"] ?? null;


$action = $_POST['action'] ?? null;
require_once __DIR__ . '/../Controller/tipos_documentos_controller.php';

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

?>


<div class="container-fluid py-4 ancho_container">

    <!-- ENCABEZADO -->
    <div class="row mb-4 align-items-center">

        <?php
        $titulo      = 'Editar Tipo de Documento';
        $descripcion = 'Modificar datos del tipo de documento';
        include __DIR__ . '/../../../public/incluido/_encabezado.php';
        ?>

        <div class="col-6 col-md-6 text-md-end">
            <?php if ($rol == "supervisor"): ?>
                <a href="/Modules/Tipos_documentos/Views/index.php" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> Regresar
                </a>
            <?php endif; ?>
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
$titulo = "Editar tipo de documento";
$bodyClass = "proyectos-page";

include __DIR__ . '/../../../layout.php';
?>