<?php
require_once __DIR__ . '/../Controladores/plantilladocumentoControlador.php';

if (!isset($_GET['tipo_documento'])) {
    echo json_encode([]);
    exit;
}

$controlador = new plantilladocumentoControlador();
$data = $controlador->obtenerPlantillas($_GET['tipo_documento']);

// SOLO AQUÍ SE HACE echo
header('Content-Type: application/json');
echo json_encode($data);
exit;