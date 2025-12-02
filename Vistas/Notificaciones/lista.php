<?php
if (!isset($_SESSION)) session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/ITSFCP-PROYECTOS/publico/config/conexion.php';

if (!isset($_SESSION['id_usuario'])) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'msg' => 'No autorizado']);
    exit;
}

$usuario_id = $_SESSION['id_usuario'];

// Recuperar últimas 5 notificaciones
$stmt = $conn->prepare("SELECT * FROM notificaciones WHERE usuario_id = ? ORDER BY creado_en DESC LIMIT 5");
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$result = $stmt->get_result();

$notificaciones = [];
$no_leidas = 0;

while ($fila = $result->fetch_assoc()) {
    if ($fila['leido'] == 0) $no_leidas++;
    $notificaciones[] = $fila;
}

// Marcar todas como leídas
$stmt2 = $conn->prepare("UPDATE notificaciones SET leido = 1 WHERE usuario_id = ?");
$stmt2->bind_param("i", $usuario_id);
$stmt2->execute();

// Devolver JSON
echo json_encode([
    'status' => 'ok',
    'notificaciones' => $notificaciones,
    'no_leidas' => $no_leidas
]);
