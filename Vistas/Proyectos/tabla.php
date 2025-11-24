<?php
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
    }*/
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

switch ($rol) {
    case 'alumno':
        $encabezados = [
            'ID',
            'Título',
            'Fecha Inicio',
            'Fecha Fin',
            'Estado',
            'Período',
            'Tareas pendientes',
            'Acciones'
        ];
        //Filtro de botones
        $opciones = [
            'Total'       => "Total ({$filtros[0]['Total']})",
            'Activos'     => "Activos ({$filtros[0]['Activos']})",
            'Cierre'      => "Cierre ({$filtros[0]['Cierre']})",
            'PorCerrar'   => "Por Cerrar ({$filtros[0]['PorCerrar']})",
        ];
        break;
    case 'profesor':
    case 'investigador':
        $encabezados = [
            'ID',
            'Título',
            'Fecha Inicio',
            'Fecha Fin',
            'Estado',
            'Período',
            'Avances por revisar',
            'Acciones'
        ];
        //Filtro de botones
        $opciones = [
            'Total'       => "Total ({$filtros[0]['Total']})",
            'Activos'     => "Activos ({$filtros[0]['Activos']})",
            'PorAprobar'  => "Por Aprobar ({$filtros[0]['PorAprobar']})",
            'Cierre'      => "Cierre ({$filtros[0]['Cierre']})",
            'PorCerrar'   => "Por Cerrar ({$filtros[0]['PorCerrar']})",
            'Rechazados'  => "Rechazados ({$filtros[0]['Rechazados']})"
        ];
        break;
    case 'supervisor':
        $encabezados = [
            'ID',
            'Título',
            'Fecha Inicio',
            'Fecha Fin',
            'Estado',
            'Período',
            'Acciones'
        ];
        //Filtro de botones
        $opciones = [
            'Total'       => "Total ({$filtros[0]['Total']})",
            'Activos'     => "Activos ({$filtros[0]['Activos']})",
            'PorAprobar'  => "Por Aprobar ({$filtros[0]['PorAprobar']})",
            'Cierre'      => "Cierre ({$filtros[0]['Cierre']})",
            'PorCerrar'   => "Por Cerrar ({$filtros[0]['PorCerrar']})",
            'Rechazados'  => "Rechazados ({$filtros[0]['Rechazados']})"
        ];
        break;
    default:
        $encabezados = [];
}


//}
?>

<?php include '../../publico/incluido/header.php'; ?>
<div class="container-main">
    <?php include '../../sidebar.php'; ?>
    <div class="main-content-index">
        <div class="row mb-1">
            <div class="col-12">
                <h3>Proyectos</h3>
            </div>
            <div class="row mb-1">
                <div class="col-12 d-flex justify-content-end align-items-center mb-3">
                    <form class="d-flex flex-wrap gap-2" method="GET" action="tabla.php">
                        <div class="flex-grow-1">
                            <div class="input-group">
                                <input type="hidden" id="input_hidden" name="action" value="<?= $action ?>">
                                <input type="text"
                                    name="buscar"
                                    placeholder="Buscar..."
                                    class="form-control"
                                    id="input_busqueda"
                                    value="<?= $_GET['buscar'] ?? '' ?>">
                                <button type="submit" id="boton_busqueda" class="btn btn-primary">Buscar</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            <div class="row mb-1">
                <div class="d-flex flex-wrap gap-2" aria-label="Filtros">
                    <?php foreach ($opciones as $key => $label): ?>
                        <?php
                        // Si este filtro es el elegido → mantenerlo activo
                        $clase = ($action === $key)
                            ? "btn btn-primary"            // marcado
                            : "btn btn-outline-primary";   // normal
                        ?>
                        <a href="tabla.php?action=<?= $key ?>">
                            <button type="button" class="<?= $clase ?>">
                                <?= $label ?>
                            </button>
                        </a>
                    <?php endforeach;
                    $proyectoControlador->filtros($id, $rol)
                    ?>
                </div>
            </div>
            <div class="row mb-1">
                <div class="col-12">
                    <div class="table-responsive">
                        <table class="table table-light">
                            <thead>
                                <tr>
                                    <?php
                                    foreach ($encabezados as $encabezado) {
                                        echo "<th scope='col'>{$encabezado}</th>";
                                    }
                                    ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($proyectos as $proyecto):
                                    echo "<tr>";
                                    echo "<th scope='row'>{$proyecto['id_proyectos']}</th>";
                                    echo "<td>{$proyecto['titulo']}</td>";
                                    echo "<td>{$proyecto['fecha_inicio']}</td>";
                                    echo "<td>{$proyecto['fecha_fin']}</td>";
                                    echo "<td>{$proyecto['nombre']}</td>";
                                    echo "<td>{$proyecto['periodo']}</td>";
                                    if (isset($proyecto['total']) && ($rol === 'alumno' || $rol === 'investigador' || $rol === 'profesor')) {
                                        echo "<td>{$proyecto['total']}</td>";
                                    }
                                    if ($proyecto['nombre'] == "Activo") {
                                        echo "<td>
                                <button type=\"button\" class=\"btn btn-success\">
                                <svg xmlns=\"http://www.w3.org/2000/svg\" width=\"20\" height=\"20\" fill=\"currentColor\" class=\"bi bi-file-earmark-plus-fill\" style=\"padding:0;margin:auto;\" viewBox=\"0 0 16 16\">
  <path d=\"M9.293 0H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V4.707A1 1 0 0 0 13.707 4L10 .293A1 1 0 0 0 9.293 0M9.5 3.5v-2l3 3h-2a1 1 0 0 1-1-1M8.5 7v1.5H10a.5.5 0 0 1 0 1H8.5V11a.5.5 0 0 1-1 0V9.5H6a.5.5 0 0 1 0-1h1.5V7a.5.5 0 0 1 1 0\"/>
</svg>
                                </button>
                                <button type=\"button\" class=\"btn btn-primary\">
                                <svg xmlns=\"http://www.w3.org/2000/svg\" width=\"20\" height=\"20\" fill=\"currentColor\" class=\"bi bi-eye-fill\" style=\"padding:0;margin:auto;\" viewBox=\"0 0 16 16\">
  <path d=\"M10.5 8a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0\"/>
  <path d=\"M0 8s3-5.5 8-5.5S16 8 16 8s-3 5.5-8 5.5S0 8 0 8m8 3.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7\"/>
</svg>
                                </button>
                                <button type=\"button\" class=\"btn btn-danger\">
                                <svg xmlns=\"http://www.w3.org/2000/svg\" width=\"18\" height=\"18\" fill=\"currentColor\" class=\"bi bi-ban\" style=\"padding:0;margin:auto;\" viewBox=\"0 0 16 16\">
  <path d=\"M15 8a6.97 6.97 0 0 0-1.71-4.584l-9.874 9.875A7 7 0 0 0 15 8M2.71 12.584l9.874-9.875a7 7 0 0 0-9.874 9.874ZM16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0\"/>
</svg></button>";
                                    } else if ($proyecto['nombre'] == "Sin aprobar") {
                                        echo "<td>
                                <button type=\"button\" class=\"btn btn-primary\">
                                <svg xmlns=\"http://www.w3.org/2000/svg\" width=\"18\" height=\"18\" fill=\"currentColor\" class=\"bi bi-eye-fill\" style=\"padding:0px;margin:auto;\" viewBox=\"0 0 16 16\">
  <path d=\"M10.5 8a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0\"/>
  <path d=\"M0 8s3-5.5 8-5.5S16 8 16 8s-3 5.5-8 5.5S0 8 0 8m8 3.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7\"/>
</svg>
                                </button>
                                {$proyecto['total']}</td>";
                                    } else if ($proyecto['nombre'] == "Por cerrar") {
                                        echo "<td>
                                <button type=\"button\" class=\"btn btn-primary\">
                                <svg xmlns=\"http://www.w3.org/2000/svg\" width=\"18\" height=\"18\" fill=\"currentColor\" class=\"bi bi-eye-fill\" style=\"padding:0px;margin:auto;\" viewBox=\"0 0 16 16\">
  <path d=\"M10.5 8a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0\"/>
  <path d=\"M0 8s3-5.5 8-5.5S16 8 16 8s-3 5.5-8 5.5S0 8 0 8m8 3.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7\"/>
</svg>
                                </button>
                                {$proyecto['total']}</td>";
                                    } else if ($proyecto['nombre'] == "Cierre") {
                                        echo "<td>
                                <button type=\"button\" class=\"btn btn-primary\">
                                <svg xmlns=\"http://www.w3.org/2000/svg\" width=\"18\" height=\"18\" fill=\"currentColor\" class=\"bi bi-eye-fill\" style=\"padding:0px;margin:auto;\" viewBox=\"0 0 16 16\">
  <path d=\"M10.5 8a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0\"/>
  <path d=\"M0 8s3-5.5 8-5.5S16 8 16 8s-3 5.5-8 5.5S0 8 0 8m8 3.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7\"/>
</svg>
                                </button></td>";
                                    }
                                    echo "</tr>";
                                endforeach;
                                ?>
                            </tbody>
                        </table>
                    </div>
                    <?php foreach ($proyectos as $proyecto): ?>
                        <div class="card mb-3" id="tarjeta_móvil" style="width: 18rem;">
                            <div class="card-body">
                                <h5 class="card-title"><?php echo $proyecto['id_proyectos'] ?></h5>
                                <p class="card-text"><?php echo $proyecto['titulo'] ?></p>
                            </div>
                            <ul class="list-group list-group-flush">
                                <li class="list-group-item">
                                    <div class="row">
                                        <div class="col-6">
                                            <label>Fecha Inicio</label>
                                            <p class="card-text"><?php echo $proyecto['fecha_inicio'] ?></p>
                                        </div>
                                        <div class="col-6">
                                            <label>Fecha Fin</label>
                                            <p class="card-text"><?php echo $proyecto['fecha_fin'] ?></p>
                                        </div>
                                    </div>
                                </li>
                                <li class="list-group-item">

                                    <div class="row">
                                        <div class="col-6">
                                            <label>Periodo</label>
                                            <p class="card-text"><?php echo $proyecto['periodo'] ?></p>
                                        </div>
                                        <?php if (isset($proyecto['total']) && ($rol === 'alumno' || $rol === 'investigador' || $rol === 'profesor')): ?>
                                        <div class="col-6">
                                            <label>Avances</label>
                                            <p class="card-text"><?php echo $proyecto['total'] ?></p>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                            </ul>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-5">
                                        <p class="card-text"><?php echo $proyecto['nombre'] ?></p>
                                    </div>
                                    <div class="col-7">
                                        <?php
                                        if ($proyecto['nombre'] == "Activo") {
                                            echo "<button type=\"button\" class=\"btn btn-success\">
                                <svg xmlns=\"http://www.w3.org/2000/svg\" width=\"20\" height=\"20\" fill=\"currentColor\" class=\"bi bi-file-earmark-plus-fill\" style=\"padding:0;margin:auto;\" viewBox=\"0 0 16 16\">
  <path d=\"M9.293 0H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V4.707A1 1 0 0 0 13.707 4L10 .293A1 1 0 0 0 9.293 0M9.5 3.5v-2l3 3h-2a1 1 0 0 1-1-1M8.5 7v1.5H10a.5.5 0 0 1 0 1H8.5V11a.5.5 0 0 1-1 0V9.5H6a.5.5 0 0 1 0-1h1.5V7a.5.5 0 0 1 1 0\"/>
</svg>
                                </button>
                                <button type=\"button\" class=\"btn btn-primary\">
                                <svg xmlns=\"http://www.w3.org/2000/svg\" width=\"20\" height=\"20\" fill=\"currentColor\" class=\"bi bi-eye-fill\" style=\"padding:0;margin:auto;\" viewBox=\"0 0 16 16\">
  <path d=\"M10.5 8a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0\"/>
  <path d=\"M0 8s3-5.5 8-5.5S16 8 16 8s-3 5.5-8 5.5S0 8 0 8m8 3.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7\"/>
</svg>
                                </button>
                                <button type=\"button\" class=\"btn btn-danger\">
                                <svg xmlns=\"http://www.w3.org/2000/svg\" width=\"18\" height=\"18\" fill=\"currentColor\" class=\"bi bi-ban\" style=\"padding:0;margin:auto;\" viewBox=\"0 0 16 16\">
  <path d=\"M15 8a6.97 6.97 0 0 0-1.71-4.584l-9.874 9.875A7 7 0 0 0 15 8M2.71 12.584l9.874-9.875a7 7 0 0 0-9.874 9.874ZM16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0\"/>
</svg></button>";
                                        } else if ($proyecto['nombre'] == "Sin aprobar") {
                                            echo "<button type=\"button\" class=\"btn btn-primary\">
                                <svg xmlns=\"http://www.w3.org/2000/svg\" width=\"18\" height=\"18\" fill=\"currentColor\" class=\"bi bi-eye-fill\" style=\"padding:0px;margin:auto;\" viewBox=\"0 0 16 16\">
  <path d=\"M10.5 8a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0\"/>
  <path d=\"M0 8s3-5.5 8-5.5S16 8 16 8s-3 5.5-8 5.5S0 8 0 8m8 3.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7\"/>
</svg></button>
                                {$proyecto['total']}</td>";
                                        } else if ($proyecto['nombre'] == "Por cerrar") {
                                            echo "<button type=\"button\" class=\"btn btn-primary\">
                                <svg xmlns=\"http://www.w3.org/2000/svg\" width=\"18\" height=\"18\" fill=\"currentColor\" class=\"bi bi-eye-fill\" style=\"padding:0px;margin:auto;\" viewBox=\"0 0 16 16\">
  <path d=\"M10.5 8a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0\"/>
  <path d=\"M0 8s3-5.5 8-5.5S16 8 16 8s-3 5.5-8 5.5S0 8 0 8m8 3.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7\"/>
</svg></button>
                                {$proyecto['total']}</td>";
                                        } else if ($proyecto['nombre'] == "Cierre") {
                                            echo "<td><button type=\"button\" class=\"btn btn-primary\">
                                <svg xmlns=\"http://www.w3.org/2000/svg\" width=\"18\" height=\"18\" fill=\"currentColor\" class=\"bi bi-eye-fill\" style=\"padding:0px;margin:auto;\" viewBox=\"0 0 16 16\">
  <path d=\"M10.5 8a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0\"/>
  <path d=\"M0 8s3-5.5 8-5.5S16 8 16 8s-3 5.5-8 5.5S0 8 0 8m8 3.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7\"/>
</svg></button>";
                                        } ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <div class="row">
                        <div class="col-12 d-flex justify-content-start">
                            <nav aria-label="Paginación de proyectos">
                                <ul class="pagination justify-content-center">
                                    <?php
                                    // Cálculo de inicio y fin
                                    $inicio = ($datosPaginacion['pagina'] - 1) * $datosPaginacion['por_pagina'] + 1;
                                    $fin = min($inicio + $datosPaginacion['por_pagina'] - 1, $datosPaginacion['total_proyectos']);
                                    ?>
                                    <li class="page-item disabled">
                                        <span class="page-link">
                                            Mostrando <?php echo $inicio; ?> a <?php echo $fin; ?> de <?php echo $datosPaginacion['total_proyectos']; ?> entradas
                                        </span>
                                    </li>
                                    <!-- Botón Primero -->
                                    <li class="page-item <?php echo ($datosPaginacion['pagina'] == 1) ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?pagina=1">|&lt;</a>
                                    </li>
                                    <!-- Botón Anterior -->
                                    <li class="page-item <?php echo ($datosPaginacion['pagina'] == 1) ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?pagina=<?php echo $datosPaginacion['pagina'] - 1; ?>">&lt;&lt;</a>
                                    </li>
                                    <?php
                                    // Loop de páginas
                                    for ($i = 1; $i <= $datosPaginacion['total_paginas']; $i++) {
                                        $active = ($i == $datosPaginacion['pagina']) ? 'active' : '';
                                        echo '<li class="page-item ' . $active . '"><a class="page-link" href="?pagina=' . $i . '">' . $i . '</a></li>';
                                    }
                                    ?>
                                    <!-- Botón Siguiente -->
                                    <li class="page-item <?php echo ($datosPaginacion['pagina'] == $datosPaginacion['total_paginas']) ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?pagina=<?php echo $datosPaginacion['pagina'] + 1; ?>">&gt;&gt;</a>
                                    </li>
                                    <!-- Botón Último -->
                                    <li class="page-item <?php echo ($datosPaginacion['pagina'] == $datosPaginacion['total_paginas']) ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?pagina=<?php echo $datosPaginacion['total_paginas']; ?>">&gt;|</a>
                                    </li>

                                </ul>
                            </nav>

                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include "../../publico/incluido/footer.php"; ?>
