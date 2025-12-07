<?php
session_start();
include("conexion.php");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ============================
    // VERIFICAR SESIÓN
    // ============================
    if (!isset($_SESSION['id_usuario'])) {
        die("Error: No hay sesión activa. Por favor regístrate primero.");
    }

    $id_usuario = intval($_SESSION['id_usuario']);

    // ============================
    // RECUPERAR DATOS DEL USUARIO DESDE LA BD
    // ============================
    $stmtUser = $conn->prepare("
        SELECT nombre, apellido_paterno, apellido_materno, curp, correo_institucional, telefono, fecha_nacimiento, id_genero
        FROM usuarios 
        WHERE id_usuarios = ?
    ");
    $stmtUser->bind_param("i", $id_usuario);
    $stmtUser->execute();
    $result = $stmtUser->get_result();

    if ($result->num_rows === 0) {
        die("Error: Usuario no encontrado en la base de datos.");
    }

    $usuario = $result->fetch_assoc();
    $stmtUser->close();

    // ============================
    // OBTENER SOLO EL ROL DEL FORMULARIO
    // ============================
    $rol = strtolower(trim($_POST['rol'] ?? ''));

    if (empty($rol)) {
        die("El rol es obligatorio.");
    }

    // ============================
    // INSERTAR DATOS SEGÚN ROL
    // ============================
    switch ($rol) {
        case 'alumno':
            $matricula  = trim($_POST['matricula']);
            $id_carrera = intval($_POST['carrera']);
            $id_area    = intval($_POST['area']);

            if (empty($matricula) || !$id_carrera || !$id_area) {
                die("Matrícula, carrera y área son obligatorios para alumnos.");
            }

            $stmtAlumno = $conn->prepare("
                INSERT INTO estudiantes (id_usuario, matricula, id_carrera, id_area)
                VALUES (?, ?, ?, ?)
            ");
            $stmtAlumno->bind_param("isii", $id_usuario, $matricula, $id_carrera, $id_area);
            
            if (!$stmtAlumno->execute()) {
                die("Error al registrar estudiante: " . $conn->error);
            }
            $stmtAlumno->close();
            break;

        case 'investigador':
            $id_area   = intval($_POST['area']);
            $id_sni    = intval($_POST['nivel_sni']);
            $id_grado  = intval($_POST['grado']);
            $id_linea  = intval($_POST['linea']);
            $rfc       = strtoupper(trim($_POST['rfc']));

            if (!$id_area || !$id_grado || !$id_linea || empty($rfc)) {
                die("Área, grado académico, línea de investigación y RFC son obligatorios para investigadores.");
            }

            $stmtInv = $conn->prepare("
                INSERT INTO investigadores (id_usuario, id_area, id_nivel_sni, id_grado, id_linea, rfc)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmtInv->bind_param("iiiiis", $id_usuario, $id_area, $id_sni, $id_grado, $id_linea, $rfc);
            
            if (!$stmtInv->execute()) {
                die("Error al registrar investigador: " . $conn->error);
            }
            $stmtInv->close();
            break;

        case 'supervisor':
            $departamento = trim($_POST['departamento']);
            $cargo        = trim($_POST['cargo']);
            $rfc          = strtoupper(trim($_POST['rfc']));

            if (empty($departamento) || empty($cargo) || empty($rfc)) {
                die("Departamento, cargo y RFC son obligatorios para supervisores.");
            }

            $pdf_blob = null;
            if (!empty($_FILES['solicitud_pdf']['tmp_name'])) {
                $pdf_blob = file_get_contents($_FILES['solicitud_pdf']['tmp_name']);
            }

            $stmtSup = $conn->prepare("
                INSERT INTO supervisores (id_usuario, departamento, cargo, RFC, pdf_solicitud)
                VALUES (?, ?, ?, ?, ?)
            ");
            $null = null;
            $stmtSup->bind_param("isssb", $id_usuario, $departamento, $cargo, $rfc, $null);
            
            if ($pdf_blob !== null) {
                $stmtSup->send_long_data(4, $pdf_blob);
            }
            
            if (!$stmtSup->execute()) {
                die("Error al registrar supervisor: " . $conn->error);
            }
            $stmtSup->close();
            break;

        default:
            die("Rol desconocido.");
    }

    // ============================
    // FINALIZAR SESIÓN Y REDIRECCIONAR
    // ============================
    session_unset();
    session_destroy();
    header("Location: /ITSFCP-PROYECTOS/Vistas/usuarios/usuario.php?solicitud_enviada=1");
    exit;
}
?>