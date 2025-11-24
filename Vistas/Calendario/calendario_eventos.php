<?php
if (!isset($_SESSION)) session_start();

header("Content-Type: application/json");

require __DIR__ . "/../../publico/config/conexion.php";

if (!isset($_SESSION['id_usuario'])) {
    echo json_encode([]);
    exit;
}

$idUsuario = $_SESSION['id_usuario'];
$rol = strtolower($_SESSION['rol'] ?? 'estudiante');

// ============================================
//  OBTENER EVENTOS COMPLETOS SEGÚN EL ROL
// ============================================

if ($rol === "supervisor") {
    // Supervisor ve TODOS los eventos
    $sql = "
        SELECT 
            e.id_eventos,
            e.id_proyectos,
            p.titulo AS proyecto,
            e.titulo AS title,
            e.descripcion,
            e.ubicacion,
            e.fecha_inicio AS start,
            e.fecha_fin AS end
        FROM eventos_calendario e
        LEFT JOIN proyectos p ON p.id_proyectos = e.id_proyectos
    ";
    $stmt = $conn->prepare($sql);

} elseif ($rol === "investigador" || $rol === "profesor") {
    // Solo eventos de sus proyectos
    $sql = "
        SELECT 
            e.id_eventos,
            e.id_proyectos,
            p.titulo AS proyecto,
            e.titulo AS title,
            e.descripcion,
            e.ubicacion,
            e.fecha_inicio AS start,
            e.fecha_fin AS end
        FROM eventos_calendario e
        INNER JOIN proyectos p ON p.id_proyectos = e.id_proyectos
        WHERE p.id_investigador = ?
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $idUsuario);

} else {
    // Estudiante → proyectos donde participa
    $sql = "
        SELECT 
            e.id_eventos,
            e.id_proyectos,
            p.titulo AS proyecto,
            e.titulo AS title,
            e.descripcion,
            e.ubicacion,
            e.fecha_inicio AS start,
            e.fecha_fin AS end
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

$eventos = [];

while ($row = $res->fetch_assoc()) {
    $eventos[] = $row;
}

echo json_encode($eventos);