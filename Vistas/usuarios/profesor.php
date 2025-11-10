<?php
session_start();
if (!isset($_SESSION['rol']) || !in_array(strtolower($_SESSION['rol']), ['profesor', 'investigador'])) {
    header("Location: /ITSFCP-PROYECTOS/login.php");
    exit;
}
?>
<h1>Bienvenido, <?php echo htmlspecialchars($_SESSION['nombre']); ?> (Profesor)</h1>
<a href="/ITSFCP-PROYECTOS/logout.php">Cerrar sesión</a>