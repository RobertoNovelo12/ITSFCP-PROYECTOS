<?php
if (!isset($_SESSION)) {
    session_start();
}

//error_reporting(E_ALL);
//ini_set('display_errors', 1);
$_SESSION['id_usuario'] = 401; // Simulación de sesión activa para pruebas
$_SESSION['rol'] = 'supervisor'; // Simulación de sesión activa para pruebas

/*
if (isset($_SESSION['rol'])) {
    $base_url = "/ITSFCP-PROYECTOS/";

    switch (strtolower($_SESSION['rol'])) {
        case 'alumno':
            header("Location: {$base_url}Vistas/usuarios/alumno.php");
            exit;
        case 'profesor':
        case 'investigador':
            header("Location: {$base_url}Vistas/usuarios/profesor.php");
            exit;
        case 'supervisor':
            header("Location: {$base_url}Vistas/usuarios/supervisor.php");
            exit;
    }
}
*/

$rol = $_SESSION['rol'];
$id = $_SESSION['id_usuario'];

$action = isset($_GET['action']) ? $_GET['action'] : 'index';
$buscar = $_GET['buscar'] ?? null;

//Se llama al controlador
require_once "../../Controladores/proyectoControlador.php";

$proyectoControlador = new ProyectoControlador();

//Si no existe el controlador que mande un mensaje
if (!method_exists($proyectoControlador, $action)) {
    die("Error: La acción '$action' no existe en el controlador.");
}

//Se ejecuta la acción del controlador
$proyectos = $proyectoControlador->$action($id, $rol, $buscar);
$filtros = $proyectoControlador->filtros($id, $rol);
$datosPaginacion = $proyectoControlador->tabla($id, $rol, $buscar);

$encabezados = $proyectoControlador->encabezados($rol);
$opciones = $proyectoControlador->datosopciones($rol, $filtros);

// Configuración para el layout
$titulo = "Proyectos";

// Contenido de la página
$contenido = '
<div class="container-fluid py-4">
    <div class="row mb-3">
        <div class="col-12">
            <h3>Proyectos</h3>
        </div>
    </div>
    
    <div class="row mb-3">
        <div class="col-12 col-md-6 mb-2">';

if ($rol == "investigador" || $rol == "profesor") {
    $contenido .= '
            <a href="crear.php">
                <button type="button" class="btn btn-primary">
                    <i class="bi bi-plus-circle"></i> Crear proyecto
                </button>
            </a>';
}

$contenido .= '
        </div>

        <div class="col-12 col-md-6 mb-2">
            <form class="d-flex flex-wrap gap-2" method="GET" action="tabla.php">
                <div class="flex-grow-1">
                    <div class="input-group">
                        <input type="hidden" name="action" value="' . htmlspecialchars($action) . '">
                        <input type="text"
                            name="buscar"
                            placeholder="Buscar..."
                            class="form-control"
                            value="' . htmlspecialchars($_GET['buscar'] ?? '') . '">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-search"></i> Buscar
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Filtros -->
    <div class="row mb-3">
        <div class="col-12">
            <div class="d-flex flex-wrap gap-2" role="group" aria-label="Filtros">';

foreach ($opciones as $key => $label) {
    $clase = ($action === $key) ? "btn btn-primary" : "btn btn-outline-primary";
    $contenido .= '
                <a href="tabla.php?action=' . htmlspecialchars($key) . '">
                    <button type="button" class="' . $clase . '">
                        ' . htmlspecialchars($label) . '
                    </button>
                </a>';
}

$contenido .= '
            </div>
        </div>
    </div>
    
    <!-- Tabla -->
    <div class="row mb-3">
        <div class="col-12">
            <div class="table-responsive">
                <table class="table table-light table-striped table-hover" id="tabla_informacion">
                    <thead class="table-dark">
                        <tr>';

foreach ($encabezados as $encabezado) {
    $contenido .= '<th scope="col">' . htmlspecialchars($encabezado) . '</th>';
}

$contenido .= '
                        </tr>
                    </thead>
                    <tbody class="text-center">';

foreach ($proyectos as $proyecto) {
    $contenido .= '<tr>';
    $contenido .= '<th scope="row">' . htmlspecialchars($proyecto['id_proyectos']) . '</th>';
    $contenido .= '<td>' . htmlspecialchars($proyecto['titulo']) . '</td>';
    $contenido .= '<td>' . htmlspecialchars($proyecto['fecha_inicio']) . '</td>';
    $contenido .= '<td>' . htmlspecialchars($proyecto['fecha_fin']) . '</td>';
    $contenido .= '<td>' . htmlspecialchars($proyecto['nombre']) . '</td>';
    $contenido .= '<td>' . htmlspecialchars($proyecto['periodo']) . '</td>';
    
    if (isset($proyecto['total']) && ($rol == 'alumno' || $rol === 'investigador' || $rol === 'profesor')) {
        $contenido .= '<td>' . htmlspecialchars($proyecto['total']) . '</td>';
    }
    
    $contenido .= '<td>' . $proyectoControlador->botonesAccion($proyecto['id_proyectos'], $rol, $proyecto) . '</td>';
    $contenido .= '</tr>';
}

$contenido .= '
                    </tbody>
                </table>
            </div>
            
            <!-- Tarjetas móviles -->';

foreach ($proyectos as $proyecto) {
    $contenido .= '
            <div class="card mb-3 d-md-none" style="width: 100%;">
                <div class="card-body">
                    <h5 class="card-title">ID: ' . htmlspecialchars($proyecto['id_proyectos']) . '</h5>
                    <p class="card-text"><strong>' . htmlspecialchars($proyecto['titulo']) . '</strong></p>
                </div>
                <ul class="list-group list-group-flush">
                    <li class="list-group-item">
                        <div class="row">
                            <div class="col-6">
                                <label><strong>Fecha Inicio</strong></label>
                                <p class="card-text">' . htmlspecialchars($proyecto['fecha_inicio']) . '</p>
                            </div>
                            <div class="col-6">
                                <label><strong>Fecha Fin</strong></label>
                                <p class="card-text">' . htmlspecialchars($proyecto['fecha_fin']) . '</p>
                            </div>
                        </div>
                    </li>
                    <li class="list-group-item">
                        <div class="row">
                            <div class="col-6">
                                <label><strong>Periodo</strong></label>
                                <p class="card-text">' . htmlspecialchars($proyecto['periodo']) . '</p>
                            </div>';
    
    if (isset($proyecto['total']) && ($rol === 'alumno' || $rol === 'investigador' || $rol === 'profesor')) {
        $contenido .= '
                            <div class="col-6">
                                <label><strong>Avances</strong></label>
                                <p class="card-text">' . htmlspecialchars($proyecto['total']) . '</p>
                            </div>';
    }
    
    $contenido .= '
                        </div>
                    </li>
                </ul>
                <div class="card-body">
                    <div class="row">
                        <div class="col-12 mb-2">
                            <p class="card-text"><strong>Responsable:</strong> ' . htmlspecialchars($proyecto['nombre']) . '</p>
                        </div>
                        <div class="col-12">
                            ' . $proyectoControlador->botonesAccion($proyecto['id_proyectos'], $rol, $proyecto) . '
                        </div>
                    </div>
                </div>
            </div>';
}

$contenido .= '
            
            <!-- Paginación -->
            <div class="row mt-3">
                <div class="col-12">
                    <nav aria-label="Paginación de proyectos">
                        <ul class="pagination justify-content-center flex-wrap">';

// Cálculo de inicio y fin
$inicio = ($datosPaginacion['pagina'] - 1) * $datosPaginacion['por_pagina'] + 1;
$fin = min($inicio + $datosPaginacion['por_pagina'] - 1, $datosPaginacion['total_proyectos']);

$contenido .= '
                            <li class="page-item disabled">
                                <span class="page-link">
                                    Mostrando ' . $inicio . ' a ' . $fin . ' de ' . $datosPaginacion['total_proyectos'] . ' entradas
                                </span>
                            </li>
                            
                            <!-- Botón Primero -->
                            <li class="page-item ' . (($datosPaginacion['pagina'] == 1) ? 'disabled' : '') . '">
                                <a class="page-link" href="?pagina=1&action=' . htmlspecialchars($action) . (isset($_GET['buscar']) ? '&buscar=' . urlencode($_GET['buscar']) : '') . '">|&lt;</a>
                            </li>
                            
                            <!-- Botón Anterior -->
                            <li class="page-item ' . (($datosPaginacion['pagina'] == 1) ? 'disabled' : '') . '">
                                <a class="page-link" href="?pagina=' . ($datosPaginacion['pagina'] - 1) . '&action=' . htmlspecialchars($action) . (isset($_GET['buscar']) ? '&buscar=' . urlencode($_GET['buscar']) : '') . '">&lt;&lt;</a>
                            </li>';

// Loop de páginas
for ($i = 1; $i <= $datosPaginacion['total_paginas']; $i++) {
    $active = ($i == $datosPaginacion['pagina']) ? 'active' : '';
    $contenido .= '
                            <li class="page-item ' . $active . '">
                                <a class="page-link" href="?pagina=' . $i . '&action=' . htmlspecialchars($action) . (isset($_GET['buscar']) ? '&buscar=' . urlencode($_GET['buscar']) : '') . '">' . $i . '</a>
                            </li>';
}

$contenido .= '
                            <!-- Botón Siguiente -->
                            <li class="page-item ' . (($datosPaginacion['pagina'] == $datosPaginacion['total_paginas']) ? 'disabled' : '') . '">
                                <a class="page-link" href="?pagina=' . ($datosPaginacion['pagina'] + 1) . '&action=' . htmlspecialchars($action) . (isset($_GET['buscar']) ? '&buscar=' . urlencode($_GET['buscar']) : '') . '">&gt;&gt;</a>
                            </li>
                            
                            <!-- Botón Último -->
                            <li class="page-item ' . (($datosPaginacion['pagina'] == $datosPaginacion['total_paginas']) ? 'disabled' : '') . '">
                                <a class="page-link" href="?pagina=' . $datosPaginacion['total_paginas'] . '&action=' . htmlspecialchars($action) . (isset($_GET['buscar']) ? '&buscar=' . urlencode($_GET['buscar']) : '') . '">&gt;|</a>
                            </li>
                        </ul>
                    </nav>
                </div>
            </div>
        </div>
    </div>
</div>';

// Incluir el layout
include __DIR__ . '/../../layout.php';
?>