<?php
if (!isset($_SESSION)) session_start();

require __DIR__ . "/../publico/config/conexion.php";
require __DIR__ . "/../Controladores/CalendarioControlador.php";

$controlador = new CalendarioControlador(
    $conn,
    $_SESSION['rol'] ?? 'estudiante',
    intval($_SESSION['id_usuario'])
);

echo json_encode($controlador->getProyectos());