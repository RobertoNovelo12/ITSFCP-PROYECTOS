<?php

session_start();
require_once "../../Controladores/proyectoControlador.php";

$rol = strtolower($_SESSION['rol'] ?? '');
$id_usuario = intval($_SESSION['id_usuario']);

$buscar = $_GET['buscar'] ?? '';
$pagina = intval($_GET['pagina'] ?? 1);
$por_pagina = 6;

$proyectoControlador = new ProyectoControlador();

$resultado = $proyectoControlador->index($id_usuario, $rol, $buscar, $pagina, $por_pagina);

echo json_encode($resultado);

?>
<link rel="stylesheet" href="https://cdn.datatables.net/2.3.7/css/dataTables.dataTables.min.css">

<div class="container-fluid py-4">

    <div class="row mb-3 align-items-center">
        <div class="col-md-6">
            <h2 class="mb-0 fw-bold">Proyectos</h2>
        </div>
        <div class="col-md-6 text-md-end">
            <?php if ($rol == "investigador" || $rol == "profesor"): ?>
                <a href="crear.php" class="btn btn-primary">
                    <i class="bi bi-plus-lg"></i> Crear proyecto
                </a>
            <?php endif; ?>
        </div>
    </div>
    <div class="table-responsive d-none d-md-block">
        <table id="tablaProyectos" class="table table-hover">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Título</th>
                    <th>Inicio</th>
                    <th>Fin</th>
                    <th>Estado</th>
                    <th>Periodo</th>
                    <th>Pendientes</th>
                    <th>Acciones</th>
                </tr>
            </thead>
        </table>
    </div>

    <div class="row d-md-none" id="cardsProyectos"></div>
</div>
<script src="https://cdn.datatables.net/2.3.7/js/dataTables.min.js">
<?php
$contenido = ob_get_clean();
$titulo = "Proyectos";
$bodyClass = "proyectos-page";

include __DIR__ . '/../../layout.php';
?>