<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (!isset($_SESSION)) session_start();
header("Content-Type: application/json");

require __DIR__ . "/../../publico/config/conexion.php";

if (!isset($_SESSION['id_usuario'])) {
    echo json_encode([]);
    exit;
}

$idUsuario = $_SESSION['id_usuario'];
$rol = strtolower($_SESSION['rol'] ?? 'estudiante');

$items = [];

// =============================
// OBTENER EVENTOS
// =============================
if ($rol === "supervisor") {
    $sql = "
        SELECT 
            e.id_eventos,
            e.id_proyectos,
            p.titulo AS proyecto,
            e.titulo AS title,
            e.descripcion,
            e.ubicacion,
            e.fecha_inicio AS start,
            e.fecha_fin AS end,
            'evento' AS tipo
        FROM eventos_calendario e
        LEFT JOIN proyectos p ON p.id_proyectos = e.id_proyectos
    ";
    $stmt = $conn->prepare($sql);

} elseif ($rol === "investigador" || $rol === "profesor") {
    $sql = "
        SELECT 
            e.id_eventos,
            e.id_proyectos,
            p.titulo AS proyecto,
            e.titulo AS title,
            e.descripcion,
            e.ubicacion,
            e.fecha_inicio AS start,
            e.fecha_fin AS end,
            'evento' AS tipo
        FROM eventos_calendario e
        INNER JOIN proyectos p ON p.id_proyectos = e.id_proyectos
        WHERE p.id_investigador = ?
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $idUsuario);

} else {
    $sql = "
        SELECT 
            e.id_eventos,
            e.id_proyectos,
            p.titulo AS proyecto,
            e.titulo AS title,
            e.descripcion,
            e.ubicacion,
            e.fecha_inicio AS start,
            e.fecha_fin AS end,
            'evento' AS tipo
        FROM eventos_calendario e
        INNER JOIN proyectos_usuarios pu ON pu.id_proyectos = e.id_proyectos
        INNER JOIN proyectos p ON p.id_proyectos = e.id_proyectos
        WHERE pu.id_usuarios = ?
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $idUsuario);
}

$stmt->execute();
$res = $stmt->get_result();

while ($row = $res->fetch_assoc()) {
    $items[] = $row;
}

// =============================
// OBTENER TAREAS
// =============================
if ($rol === "supervisor") {
    $sqlT = "
        SELECT 
            t.id_tarea AS id_eventos,
            p.id_proyectos AS id_proyectos,
            p.titulo AS proyecto,
            t.contenido AS title,
            t.comentarios AS descripcion,
            NULL AS ubicacion,
            tu.fecha_asignacion AS start,
            tu.fecha_completacion AS end,
            'tarea' AS tipo
        FROM tareas t
        INNER JOIN tareas_usuarios tu ON tu.id_tarea = t.id_tarea
        INNER JOIN proyectos_usuarios pu ON pu.id_usuarios = tu.id_usuario
        INNER JOIN proyectos p ON p.id_proyectos = pu.id_proyectos
    ";
    $stmtT = $conn->prepare($sqlT);

} elseif ($rol === "investigador" || $rol === "profesor") {
    $sqlT = "
 SELECT 
    t.id_tarea AS id_eventos,
    p.id_proyectos AS id_proyectos,
    p.titulo AS proyecto,
    p.titulo AS title,
    p.descripcion AS descripcion,
    NULL AS ubicacion,
    tu.fecha_revision AS start,
    tu.fecha_aprobacion AS end,
    'tarea' AS tipo
FROM tareas t
INNER JOIN tareas_usuarios tu ON tu.id_tarea = t.id_tarea
INNER JOIN proyectos_usuarios pu ON pu.id_usuarios = tu.id_usuario
INNER JOIN proyectos p ON p.id_proyectos = pu.id_proyectos
WHERE tu.id_usuario = ?

    ";
    $stmtT = $conn->prepare($sqlT);
    $stmtT->bind_param("i", $idUsuario);

} else {
    $sqlT = "
        SELECT 
            t.id_tarea AS id_eventos,
            p.id_proyectos AS id_proyectos,
            p.titulo AS proyecto,
            t.contenido AS title,
            t.comentarios AS descripcion,
            NULL AS ubicacion,
            tu.fecha_asignacion AS start,
            tu.fecha_completacion AS end,
            'tarea' AS tipo
        FROM tareas t
        INNER JOIN tareas_usuarios tu ON tu.id_tarea = t.id_tarea
        INNER JOIN proyectos_usuarios pu ON pu.id_usuarios = tu.id_usuario
        INNER JOIN proyectos p ON p.id_proyectos = pu.id_proyectos
        WHERE tu.id_usuario = ?
    ";
    $stmtT = $conn->prepare($sqlT);
    $stmtT->bind_param("i", $idUsuario);
}

$stmtT->execute();
$resT = $stmtT->get_result();

while ($row = $resT->fetch_assoc()) {
    $items[] = $row;
}

// =============================
// RETORNAR JSON
// =============================
echo json_encode($items);
?>