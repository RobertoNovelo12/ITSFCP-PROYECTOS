<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

if (!isset($_SESSION)) session_start();
header("Content-Type: application/json");

require __DIR__ . "/../publico/config/conexion.php";
require __DIR__ . "/../Controladores/CalendarioControlador.php";

if (!isset($_SESSION['id_usuario'])) {
    echo json_encode([]);
    exit;
}

$controlador = new CalendarioControlador(
    $conn,
    $_SESSION['rol'] ?? 'estudiante',
    intval($_SESSION['id_usuario'])
);

echo json_encode($controlador->getEventos());