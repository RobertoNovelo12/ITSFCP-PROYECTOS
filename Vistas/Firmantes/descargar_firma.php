<?php
/**
 * ============================================================
 * descargar_firma.php
 * ============================================================
 * Script auxiliar para la descarga segura de la imagen de firma.
 *
 * USO: descargar_firma.php?id_firmantes=X
 *
 * FLUJO:
 *   1. Validar sesión y rol.
 *   2. Obtener el nombre del archivo .enc desde la BD.
 *   3. Desencriptar el contenido AES-256-CBC.
 *   4. Enviar el PNG desencriptado como descarga al navegador.
 *
 * SEGURIDAD:
 *   - Solo usuarios con rol 'supervisor' pueden descargar.
 *   - La ruta real del archivo .enc nunca se expone al cliente.
 *   - El navegador solo recibe bytes PNG puros.
 * ============================================================
 */

ini_set('display_errors', 0); // No mostrar errores al cliente en producción
error_reporting(E_ALL);

session_start();

/* Validar sesión */
if (!isset($_SESSION['id_usuario'])) {
    header("Location: /ITSFCP-PROYECTOS/index.php");
    exit;
}

$rol          = strtolower($_SESSION['rol'] ?? '');
$id_firmantes = intval($_GET['id_firmantes'] ?? 0);

if ($id_firmantes <= 0) {
    header("Location: tabla.php?error=id_invalido");
    exit;
}

require_once '../../Controladores/firmanteControlador.php';

$firmanteControlador = new firmanteControlador();

// Llama al método del controlador que desencripta y envía la imagen
$firmanteControlador->descargarFirma($rol, $id_firmantes);
