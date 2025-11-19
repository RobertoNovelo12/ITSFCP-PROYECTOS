<?php
if (!isset($_SESSION)) session_start();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $titulo ?? 'Panel' ?></title>
    <link rel="stylesheet" href="/ITSFCP-PROYECTOS/publico/css/styles.css">
</head>
<body class="<?= $bodyClass ?? '' ?>">

<?php include __DIR__ . '/publico/incluido/header.php'; ?>
<?php include __DIR__ . '/sidebar.php'; ?>

<div class="main-content">
    <?php
    // Contenido específico de cada página
    if (isset($contenido)) {
        echo $contenido;
    }
    ?>
</div>

</body>
</html>