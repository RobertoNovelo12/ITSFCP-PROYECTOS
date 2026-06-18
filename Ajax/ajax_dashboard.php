<?php

session_start();

require_once __DIR__ . "/../public/config/conexion.php";
require_once __DIR__ . "/../Modules/Dashboard/Controller/dashboard_controller.php";

header('Content-Type: application/json');

if (!isset($_SESSION['id_usuario'])) {
    http_response_code(401);
    exit;
}

$id_proyecto = intval($_GET['id'] ?? 0);

$controlador = new DashboardControlador(
    $conn,
    strtolower($_SESSION['rol']),
    intval($_SESSION['id_usuario'])
);

echo json_encode(
    $controlador->getDatosProyecto($id_proyecto)
);