<?php
if (!isset($_SESSION)) session_start();
require __DIR__ . "/../../publico/config/conexion.php";

$idUsuario = $_SESSION["id_usuario"];
$rol = strtolower($_SESSION["rol"]);

if ($rol === "supervisor") {

    $sql = "SELECT id_proyectos AS id, titulo FROM proyectos";
    $stmt = $conn->prepare($sql);

} else {

    $sql = "
        SELECT p.id_proyectos AS id, p.titulo
        FROM proyectos p
        INNER JOIN proyectos_usuarios pu ON pu.id_proyectos = p.id_proyectos
        WHERE pu.id_usuarios = ?
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $idUsuario);
}

$stmt->execute();
$result = $stmt->get_result();

$proyectos = [];
while ($row = $result->fetch_assoc()) {
    $proyectos[] = $row;
}

echo json_encode($proyectos);