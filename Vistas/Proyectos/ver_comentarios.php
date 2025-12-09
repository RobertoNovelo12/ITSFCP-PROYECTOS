<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

if (!isset($_SESSION['id_usuario'])) {
    header("Location: /ITSFCP-PROYECTOS/index.php");
    exit;
}

$id_proyectos = $_GET['id_proyectos'] ?? null;

ob_start();
?>
<div class="container-fluid py-4">
    <div class="row mb-3 align-items-center">
        <div class="col-6">
            <h3>Comentarios del Proyecto</h3>
        </div>
        <div class="col-6 text-end">
            <a href="tabla.php" class="btn btn-danger">Regresar</a>
        </div>
    </div>

    <div class="accordion" id="comentariosAccordion">
        <!-- Comentarios cargados por JS -->
    </div>

    <input type="hidden" id="idProyectoComentarios" value="<?= $id_proyectos ?>">
</div>

<?php
$contenido = ob_get_clean();
$titulo = "Comentarios";
$bodyClass = "proyectos-page";

include __DIR__ . '/../../layout.php';
?>
