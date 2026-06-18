<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

if (!isset($_SESSION)) session_start();
header("Content-Type: application/json");

require __DIR__ . "/../public/config/conexion.php";
require_once __DIR__ . '/../Modules/Calendario/Controller/calendario_controller.php';

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