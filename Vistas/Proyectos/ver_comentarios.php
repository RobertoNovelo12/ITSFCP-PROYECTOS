<?php
/*Proyectos/ver_comentario.php - Página secundaria para ver comentarios del supervisor */

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
$rol = strtolower($_SESSION['rol'] ?? '');

if (!isset($_SESSION['id_usuario'])) {
    header("Location: /ITSFCP-PROYECTOS/index.php");
    exit;
}

//Solo investigador puede acceder
if (!in_array($rol, ['investigador', 'profesor'], true)) {
    header("Location: /ITSFCP-PROYECTOS/Vistas/Principal/index.php");
    exit;
}

$id_proyectos = $_GET['id_proyectos'] ?? null;

//Validación de argumentos en url
$id_validar = $id_proyectos;
require_once __DIR__ .  '/../../../publico/incluido/_validar_id.php';

ob_start();
?>
<div class="container-fluid py-4 ancho_container">
    <div class="row mb-3 align-items-center">
        <?php
        $titulo      = 'Comentarios del Supervisor';
        $descripcion = 'Observaciones del supervisor al investigador';
        include __DIR__ . '../../../publico/incluido/_encabezado.php';
        ?>
        <div class="col-6 col-md-6 text-md-end mb-2 mb-md-0 text-end">
            <a href="index.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Regresar
            </a>
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