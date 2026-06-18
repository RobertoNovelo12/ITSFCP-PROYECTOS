<?php
require_once __DIR__ . '/../Modules/Proyectos/Controller/proyecto_controller.php';

$controlador = new ProyectoControlador();

$id = $_POST['id'] ?? null;

if (!$id) {
    echo json_encode([]);
    exit;
}

$comentarios = $controlador->comentarios($id);

echo json_encode($comentarios);
