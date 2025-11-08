<?php
session_start();
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'Supervisor') {
    header("Location: ../../../login.php");
    exit;
}
?>
<h1>Bienvenido, <?php echo htmlspecialchars($_SESSION['nombre']); ?> (Supervisor)</h1>
<a href="/ITSFCP-PROYECTOS/logout.php">Cerrar sesión</a>
