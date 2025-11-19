<?php
session_start();
if (!isset($_SESSION['rol']) || !in_array(strtolower($_SESSION['rol']), ['profesor', 'investigador'])) {
    header("Location: /ITSFCP-PROYECTOS/login.php");
    exit;
}

$titulo = "Panel del Profesor";
$bodyClass = "body-dashboard";

ob_start();
?>
<h1 class="title">Panel de Profesor</h1>
<p>Bienvenido, <?= htmlspecialchars($_SESSION['nombre'] ?? '') ?>.</p>
<?php
$contenido = ob_get_clean();
include __DIR__ . '/../../layout.php';