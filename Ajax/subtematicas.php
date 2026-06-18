<?php
require_once __DIR__ . '/../Modules/Proyectos/Controller/proyecto_controller.php';

if (!isset($_GET['tematica'])) {
    echo json_encode([]);
    exit;
}

$controlador = new ProyectoControlador();
$subtemas = $controlador->subtematicas($_GET['tematica']);

header('Content-Type: application/json');
echo json_encode($subtemas);
?>
