<?php
session_start();
if (!isset($_SESSION['rol']) || !in_array(strtolower($_SESSION['rol']), ['profesor', 'investigador'])) {
    header("Location: /ITSFCP-PROYECTOS/login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

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