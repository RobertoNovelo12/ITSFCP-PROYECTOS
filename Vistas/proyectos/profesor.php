<?php
session_start();
if (!isset($_SESSION['rol']) || ($_SESSION['rol'] !== 'Investigador' && $_SESSION['rol'] !== 'Profesor')) {
    header("Location: ../../../login.php");
    exit;
}
?>
<h1>Bienvenido, <?php echo htmlspecialchars($_SESSION['nombre']); ?> (Profesor)</h1>
<a href="/ITSFCP-PROYECTOS/logout.php">Cerrar sesión</a>
