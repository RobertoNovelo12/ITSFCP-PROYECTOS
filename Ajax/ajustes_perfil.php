<?php
if (!isset($_SESSION)) session_start();
header('Content-Type: application/json');

require __DIR__ . '/../public/config/conexion.php';
require_once __DIR__ . '/../Modules/Ajustes/Controller/ajustes_controller.php';

if (!isset($_SESSION['id_usuario'])) {
    echo json_encode(['ok' => false, 'msg' => 'Sin sesión.']);
    exit;
}

$ctrl = new AjustesControlador($conn, intval($_SESSION['id_usuario']));
echo json_encode($ctrl->actualizarPerfil($_POST));