<?php
if (!isset($_SESSION)) session_start();

require __DIR__ . "/../public/config/conexion.php";
require_once __DIR__ . '/../Modules/Calendario/Controller/calendario_controller.php';

$controlador = new CalendarioControlador(
    $conn,
    $_SESSION['rol'] ?? 'estudiante',
    intval($_SESSION['id_usuario'])
);

echo json_encode($controlador->getProyectos());