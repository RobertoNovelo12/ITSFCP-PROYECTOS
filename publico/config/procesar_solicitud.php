<?php
session_start();
include("conexion.php");

// Si no hay sesión, impedir acceso
if (!isset($_SESSION['id_usuario']) && !isset($_GET['solicitud_enviada'])) {
    header("Location: ../../login.php");
    exit;
}

$id_usuario = $_SESSION['id_usuario'];
$rol = strtolower(trim($_POST['rol'] ?? ''));

// Seguridad básica
if (!$rol) {
    die("Error: rol no recibido.");
}


/* --------------------------------------------------
   1. CAMBIAR ESTADO DEL USUARIO A 'espera'
-------------------------------------------------- */
$updateEstado = $conn->prepare("
    UPDATE usuarios 
    SET estado_usuario = 'espera'
    WHERE id_usuarios = ?
");
$updateEstado->bind_param("i", $id_usuario);
$updateEstado->execute();


/* --------------------------------------------------
   2. PROCESAR SEGÚN TIPO DE ROL
-------------------------------------------------- */
switch ($rol) {


/* -------------------------------
   ROL: ALUMNO
--------------------------------- */
case 'alumno':

    $matricula  = trim($_POST['matricula']);
    $id_carrera = intval($_POST['carrera']);
    $id_area    = intval($_POST['area']);
    $id_subarea = intval($_POST['subarea']);

    $stmt = $conn->prepare("
        INSERT INTO estudiantes (id_usuario, matricula, id_carrera, id_area, id_subarea)
        VALUES (?, ?, ?, ?, ?)
    ");

    $stmt->bind_param("isiii", $id_usuario, $matricula, $id_carrera, $id_area, $id_subarea);
    $stmt->execute();

    break;


/* -------------------------------
   ROL: INVESTIGADOR
--------------------------------- */
case 'investigador':

    $id_area   = intval($_POST['area']);
    $id_sni    = intval($_POST['nivel_sni']);
    $id_grado  = intval($_POST['grado']);
    $id_linea  = intval($_POST['linea']);
    $rfc       = trim($_POST['rfc']);

    $stmt = $conn->prepare("
        INSERT INTO investigadores (id_usuario, id_area, id_nivel_sni, id_grado, id_linea, rfc)
        VALUES (?, ?, ?, ?, ?, ?)
    ");

    $stmt->bind_param("iiiiis",
        $id_usuario,
        $id_area,
        $id_sni,
        $id_grado,
        $id_linea,
        $rfc
    );
    $stmt->execute();

    break;


/* -------------------------------
   ROL: SUPERVISOR
--------------------------------- */
case 'supervisor':

    $departamento = trim($_POST['departamento']);
    $cargo        = trim($_POST['cargo']);
    $rfc          = trim($_POST['rfc']);

    $pdf_blob = null;

    if (!empty($_FILES['solicitud_pdf']['tmp_name'])) {
        $pdf_blob = file_get_contents($_FILES['solicitud_pdf']['tmp_name']);
    }

    $stmt = $conn->prepare("
        INSERT INTO supervisores (id_usuario, departamento, cargo, rfc, pdf_solicitud)
        VALUES (?, ?, ?, ?, ?)
    ");
    $null = null;
    $stmt->bind_param("issss",
        $id_usuario,
        $departamento,
        $cargo,
        $rfc,
        $null
    );

    if ($pdf_blob !== null) {
        $stmt->send_long_data(4, $pdf_blob);
    }

    $stmt->execute();
    break;


/* -------------------------------
   ROL DESCONOCIDO
--------------------------------- */
default:
    die("Error: rol desconocido.");
}


/* --------------------------------------------------
   3. REDIRECCIÓN FINAL
-------------------------------------------------- */

session_unset();
session_destroy();

header("Location: /ITSFCP-PROYECTOS/Vistas/usuarios/usuario.php?solicitud_enviada=1");
exit;

?>