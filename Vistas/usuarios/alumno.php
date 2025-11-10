<?php
session_start();

// Verificar si hay sesion activa
if (!isset($_SESSION['id_usuario'])) {
    header("Location: /ITSFCP-PROYECTOS/login.php");
    exit;
}

// Verificar rol
if (strtolower($_SESSION['rol']) !== 'alumno') {
    header("Location: /ITSFCP-PROYECTOS/index.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel del Estudiante</title>
</head>
<body>
    <h1>Bienvenido, <?= htmlspecialchars($_SESSION['nombre']) ?> 👋</h1>
    <p>Tu rol es: <strong><?= htmlspecialchars($_SESSION['rol']) ?></strong></p>

    <a href="/ITSFCP-PROYECTOS/logout.php">Cerrar sesión</a>
</body>
</html>