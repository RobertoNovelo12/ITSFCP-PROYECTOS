<?php
session_start();
if (!isset($_SESSION['rol']) || strtolower($_SESSION['rol']) !== 'supervisor') {
    header("Location: /ITSFCP-PROYECTOS/login.php");
    exit;
}

$titulo = "Panel del Supervisor";
$bodyClass = "body-dashboard";

ob_start();
?>
<h1 class="title">Panel de Supervisor</h1>
<p>Bienvenido, <?= htmlspecialchars($_SESSION['nombre'] ?? '') ?>.</p>
<?php
$contenido = ob_get_clean();
include __DIR__ . '/../../layout.php';