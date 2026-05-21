<?php
if (!isset($_SESSION)) session_start();
header('Content-Type: application/json');

require __DIR__ . '/../publico/config/conexion.php';
require __DIR__ . '/../Controladores/AjustesControlador.php';

if (!isset($_SESSION['id_usuario'])) {
    echo json_encode(['ok' => false, 'msg' => 'Sin sesión.']);
    exit;
}

$ctrl = new AjustesControlador($conn, intval($_SESSION['id_usuario']));
echo json_encode($ctrl->actualizarPerfil($_POST));