<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once __DIR__ . "/public/config/conexion.php";
require_once __DIR__ . "/Modules/Login/Controller/login_controller.php";

$controlador = new LoginControlador($conn);

// Si ya hay sesión, redirige
$controlador->manejarSesionActiva();

// Si es POST, procesa el login (redirige al dashboard o de vuelta con error)
$controlador->procesarLogin();

// Si llega aquí, es un GET normal: mostrar el formulario
include __DIR__ . "/Modules/Login/Views/index.php";