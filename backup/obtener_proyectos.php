<?php
if (!isset($_SESSION))
    session_start();
require __DIR__ . "/../../publico/config/conexion.php";

$idUsuario = $_SESSION["id_usuario"];
$rol = strtolower($_SESSION["rol"]);

// =======================================================
// SI ES SUPERVISOR → ve TODOS los proyectos activos
// =======================================================
if ($rol === "supervisor") {

    $sql = "
        SELECT id_proyectos AS id, titulo
        FROM proyectos
        WHERE id_estadoP = 2
    ";
    $stmt = $conn->prepare($sql);

    // =======================================================
// SI ES INVESTIGADOR → ve SOLO SUS proyectos activos
// =======================================================
} else {

    $sql = "
    SELECT p.id_proyectos AS id, p.titulo
    FROM proyectos p
    WHERE p.id_estadoP = 2
      AND (
            p.id_investigador = ? 
            OR p.id_proyectos IN (
                SELECT id_proyectos 
                FROM proyectos_usuarios 
                WHERE id_usuarios = ?
            )
          )
";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $idUsuario, $idUsuario);

}

$stmt->execute();
$result = $stmt->get_result();

$proyectos = [];
while ($row = $result->fetch_assoc()) {
    $proyectos[] = $row;
}

echo json_encode($proyectos);