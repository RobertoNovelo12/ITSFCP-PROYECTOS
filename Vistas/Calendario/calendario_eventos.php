<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (!isset($_SESSION))
    session_start();
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
// OBTENER TAREAS
// =============================

if ($rol === "supervisor") {
    // SUPERVISOR: Ver todas las tareas de todos los proyectos
    $sqlT = "
        SELECT 
            t.id_tarea AS id_eventos,
            MIN(p.id_proyectos) AS id_proyectos,
            MIN(p.titulo) AS proyecto,
            tt.descripcion_tipo AS title,
            t.descripcion AS descripcion,
            NULL AS ubicacion,
            t.fecha_entrega AS start,
            t.fecha_entrega AS end,
            'tarea' AS tipo
        FROM tareas t
        INNER JOIN tareas_usuarios tu ON tu.id_tarea = t.id_tarea
        INNER JOIN proyectos_usuarios pu ON pu.id_usuarios = tu.id_usuario
        INNER JOIN proyectos p ON p.id_proyectos = pu.id_proyectos
        INNER JOIN tipo_tarea tt ON tt.id_tareatipo = t.id_tipotarea
        WHERE pu.estado = 'activo'
        GROUP BY t.id_tarea, tt.descripcion_tipo, t.descripcion, t.fecha_entrega
    ";
    $stmtT = $conn->prepare($sqlT);

} elseif ($rol === "investigador" || $rol === "profesor") {
    // INVESTIGADOR/PROFESOR: Ver tareas de sus proyectos (de sus alumnos)
    $sqlT = "
        SELECT 
            t.id_tarea AS id_eventos,
            MIN(p.id_proyectos) AS id_proyectos,
            MIN(p.titulo) AS proyecto,
            tt.descripcion_tipo AS title,
            t.descripcion AS descripcion,
            NULL AS ubicacion,
            t.fecha_entrega AS start,
            t.fecha_entrega AS end,
            'tarea' AS tipo
        FROM tareas t
        INNER JOIN tareas_usuarios tu ON tu.id_tarea = t.id_tarea
        INNER JOIN proyectos_usuarios pu ON pu.id_usuarios = tu.id_usuario
        INNER JOIN proyectos p ON p.id_proyectos = pu.id_proyectos
        INNER JOIN tipo_tarea tt ON tt.id_tareatipo = t.id_tipotarea
        WHERE p.id_investigador = ?
          AND pu.estado = 'activo'
        GROUP BY t.id_tarea, tt.descripcion_tipo, t.descripcion, t.fecha_entrega
    ";
    $stmtT = $conn->prepare($sqlT);
    $stmtT->bind_param("i", $idUsuario);

} else {
    // ESTUDIANTE: Solo ver sus propias tareas
    $sqlT = "
        SELECT 
            t.id_tarea AS id_eventos,
            MIN(p.id_proyectos) AS id_proyectos,
            MIN(p.titulo) AS proyecto,
            tt.descripcion_tipo AS title,
            t.descripcion AS descripcion,
            NULL AS ubicacion,
            t.fecha_entrega AS start,
            t.fecha_entrega AS end,
            'tarea' AS tipo
        FROM tareas t
        INNER JOIN tareas_usuarios tu ON tu.id_tarea = t.id_tarea
        INNER JOIN proyectos_usuarios pu ON pu.id_usuarios = tu.id_usuario
        INNER JOIN proyectos p ON p.id_proyectos = pu.id_proyectos
        INNER JOIN tipo_tarea tt ON tt.id_tareatipo = t.id_tipotarea
        WHERE tu.id_usuario = ?
          AND pu.estado = 'activo'
        GROUP BY t.id_tarea, tt.descripcion_tipo, t.descripcion, t.fecha_entrega
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
// OBTENER EVENTOS (SOLO PROPIOS)
// Los eventos son PRIVADOS para cada usuario
// =============================
$sqlE = "
    SELECT 
        e.id_eventos,
        e.id_proyectos,
        COALESCE(p.titulo, 'Sin proyecto') AS proyecto,
        e.titulo AS title,
        e.descripcion,
        e.ubicacion,
        DATE(e.fecha_inicio) AS start,
        DATE(e.fecha_fin) AS end,
        'evento' AS tipo
    FROM eventos_calendario e
    LEFT JOIN proyectos p ON p.id_proyectos = e.id_proyectos
    INNER JOIN eventos_usuarios eu ON eu.id_eventos = e.id_eventos
    WHERE eu.id_usuarios = ?
";

$stmtE = $conn->prepare($sqlE);
$stmtE->bind_param("i", $idUsuario);
$stmtE->execute();
$resE = $stmtE->get_result();

while ($row = $resE->fetch_assoc()) {
    $items[] = $row;
}

// =============================
// RETORNAR JSON
// =============================
echo json_encode($items);
?>