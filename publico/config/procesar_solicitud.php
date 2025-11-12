<?php
session_start();
include("../../publico/config/conexion.php");

// Verificar sesion activa
if (!isset($_SESSION['id_usuario'])) {
    header("Location: ../../login.php");
    exit;
}

$id_usuario = $_SESSION['id_usuario'];
$rol = $_POST['rol'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $rol) {

    switch ($rol) {

        case 'alumno':
            $stmt = $conn->prepare("INSERT INTO usuarios_alumnos 
                (usuario_id, matricula, carrera, area_conocimiento, subarea_conocimiento) 
                VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("issss", 
                $id_usuario, 
                $_POST['matricula'], 
                $_POST['carrera'], 
                $_POST['area'], 
                $_POST['subarea']
            );
            $stmt->execute();
            break;

        case 'profesor':
            $stmt = $conn->prepare("INSERT INTO usuarios_profesores 
                (usuario_id, area_conocimiento, subarea_conocimiento, nivel_sni, grado_estudio, linea_investigacion, rfc) 
                VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("issssss", 
                $id_usuario, 
                $_POST['area'], 
                $_POST['subarea'], 
                $_POST['nivel_sni'], 
                $_POST['grado'], 
                $_POST['linea'], 
                $_POST['rfc']
            );
            $stmt->execute();
            break;

        case 'supervisor':
            // Manejo del archivo PDF
            $ruta_archivo = "";
            if (!empty($_FILES['solicitud_pdf']['name'])) {
                $destino = __DIR__ . "/../../publico/docs/solicitudes/";

                $nombre_pdf = uniqid("solicitud_") . ".pdf";
                $ruta_final = $destino . $nombre_pdf;

                if (move_uploaded_file($_FILES['solicitud_pdf']['tmp_name'], $ruta_final)) {
                    $ruta_archivo = "/ITSFCP-PROYECTOS/publico/docs/solicitudes/" . $nombre_pdf;
                }
            }

            $stmt = $conn->prepare("INSERT INTO usuarios_supervisores 
                (usuario_id, departamento, cargo, rfc, solicitud_pdf) 
                VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("issss", 
                $id_usuario, 
                $_POST['departamento'], 
                $_POST['cargo'], 
                $_POST['rfc'], 
                $ruta_archivo
            );
            $stmt->execute();
            break;

        default:
            echo "<script>alert('rol no reconocido.'); window.history.back();</script>";
            exit;
    }

    $conn->query("UPDATE usuarios SET estado_usuario = 0 WHERE id_usuarios = $id_usuario");

    echo "<script>alert('solicitud enviada correctamente.'); window.location.href='../../index.php';</script>";
    exit;
} else {
    echo "<script>alert('solicitud incalida o incompleta.'); window.history.back();</script>";
    exit;
}
?>