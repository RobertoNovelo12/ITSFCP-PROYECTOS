<?php
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

$action = $_GET['action'] ?? 'index';


require_once "../../Controladores/proyectoControlador.php";
$proyectoControlador = new ProyectoControlador();

if (!method_exists($proyectoControlador, $action)) {
    die("Error: La acción '$action' no existe en el controlador.");
}

// Obtener proyectos
$resultado = $proyectoControlador->$action($id_usuario, $rol);

// Si viene como JSON, decodificar
if (is_string($resultado)) {
    $resultado = json_decode($resultado, true);
}

if (!is_array($resultado)) {
    die("Error: La acción '$action' no devolvió un array válido.");
}

$registros = $resultado['etapas'] ?? [];


$filtros = $proyectoControlador->filtros($id_usuario, $rol);
$encabezados = $proyectoControlador->encabezados($rol);
$opciones = $proyectoControlador->datosopciones($rol, $filtros);

// GENERAR CONTENIDO
ob_start();
include __DIR__ . '/../../mensaje.php';
?>

<div class="container-fluid py-4">

    <div class="row mb-3 align-items-center">
        <div class="col-md-6">
            <h2 class="mb-0 fw-bold">Proyectos</h2>
        </div>
        <div class="col-md-6 text-md-end">
            <?php if ($rol == "investigador" || $rol == "profesor"): ?>
                <a href="crear.php" class="btn btn-primary">
                    <i class="bi bi-plus-lg"></i> Crear proyecto
                </a>
            <?php endif; ?>
        </div>
    </div>
    <div class="timeline-documentacion">
        <div class="timeline-D-item">
            <div class="t-dot"></div>
            <div class="t-fecha">2024</div>
            <div class="t-contenido">
                <h3>Concept</h3>
                <p>Initial brainstorming and design phase.</p>
            </div>
        </div>
        <div class="timeline-D-item">
            <div class="t-dot"></div>
            <div class="t-fecha">2025</div>
            <div class="t-contenido">
                <h3>Development</h3>
                <p>Building the core infrastructure and features.</p>
            </div>
        </div>
        <div class="timeline-D-item">
            <div class="t-dot"></div>
            <div class="t-fecha">2026</div>
            <div class="t-contenido">
                <h3>Launch</h3>
                <p>Official release to the global market.</p>
            </div>
        </div>
        <div class="timeline-D-item">
            <div class="t-dot"></div>
            <div class="t-fecha">2026</div>
            <div class="t-contenido">
                <h3>Launch</h3>
                <p>Official release to the global market.</p>
            </div>
        </div>
    </div>
</div>

<?php
$contenido = ob_get_clean();
$titulo = "Proyectos";
$bodyClass = "proyectos-page";

include __DIR__ . '/../../layout.php';
?>