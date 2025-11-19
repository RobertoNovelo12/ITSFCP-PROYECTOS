<?php
if (!isset($_SESSION)) session_start();

header('Content-Type: application/json');

require __DIR__ . '/../../publico/config/conexion.php';

if (!isset($_SESSION['id_usuario'])) {
    echo json_encode([]);
    exit;
}

$idUsuario = $_SESSION['id_usuario'];

$sql = "
    SELECT 
        t.id_tareas,
        t.contenido,
        t.fecha_creacion
    FROM tareas t
    INNER JOIN tareas_usuarios tu ON tu.id_tarea = t.id_tareas
    WHERE tu.id_usuario = ?
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $idUsuario);
$stmt->execute();
$res = $stmt->get_result();

$eventos = [];

while ($r = $res->fetch_assoc()) {

    // contenido es JSON, lo decodificamos
    $contenido = json_decode($r['contenido'], true);

    // si no tiene descripcion, ponemos un nombre genérico
    $titulo = $contenido['descripcion'] ?? 'Tarea sin descripción';

    $eventos[] = [
        "id"    => $r['id_tareas'],
        "title" => $titulo,
        "start" => $r['fecha_creacion'],
        "end"   => $r['fecha_creacion']
    ];
}

echo json_encode($eventos);