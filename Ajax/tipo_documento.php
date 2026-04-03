<?php
require_once __DIR__ . '/../Controladores/plantilladocumentoControlador.php';

if (!isset($_GET['tipo_documento'])) {
    echo json_encode([]);
    exit;
}

$controlador = new plantilladocumentoControlador();
$subtemas = $controlador->obtenerTipos($_GET['tipo_documento']);

header('Content-Type: application/json');
echo json_encode($subtemas);
?>
