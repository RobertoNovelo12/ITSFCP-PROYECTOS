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
    <link rel="stylesheet" href="/ITSFCP-PROYECTOS/publico/css/styles.css">
</head>
<body>
<?php include __DIR__ . '/../../publico/incluido/header.php'; ?>
<?php include __DIR__ . '/../../sidebar.php'; ?>
</body>
</html>