<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once __DIR__ . "/publico/config/conexion.php";
require_once __DIR__ . "/Controladores/LoginControlador.php";

$controlador = new LoginControlador($conn);

// Si ya hay sesión, redirige
$controlador->manejarSesionActiva();

// Si es POST, procesa el login (redirige al dashboard o de vuelta con error)
$controlador->procesarLogin();

// Si llega aquí, es un GET normal: mostrar el formulario
include __DIR__ . "/Vistas/Login/index.php";