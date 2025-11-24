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

$encabezados = $proyectoControlador->encabezados($rol);
$opciones = $proyectoControlador->datosopciones($rol, $filtros);
//}
?>
<script>
    (() => {
        try {
            const sidebarCollapsed = localStorage.getItem("sidebar-collapsed");

            if (sidebarCollapsed === "true") {
                document.documentElement.classList.add("sidebar-collapsed-initial");
            }

            const isDark = localStorage.getItem("darkModeEnabled") === "true";
            if (isDark) {
                document.documentElement.classList.add("dark-mode");
            }
        } catch (e) {
            console.warn("Error al acceder a localStorage", e);
        }
    })();
</script>
<?php include '../../publico/incluido/header.php'; ?>
<div class="container-main">
    <?php include '../../sidebar.php'; ?>
    <div class="main-content-index">
        <div class="row mb-1">
            <div class="col-12">
                <h3>Proyectos</h3>
            </div>
            <div class="row mb-1">
                <div class="col-12 mb-3" id="crear_busqueda">

                    <div class="col-12 col-md-6 mb-2">
                        <?php if ($rol == "investigador" || $rol == "profesor"): ?>
                            <a href="crear.php">
                                <button type="button" class="btn btn-primary">Crear proyecto</button></a>
                        <?php endif; ?>
                    </div>

                    <div class="col-12 col-md-6 mb-2">
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
                        <table class="table table-light" id="tabla_informacion">
                            <thead>
                                <tr>
                                    <?php
                                    foreach ($encabezados as $encabezado) {
                                        echo "<th scope='col'>{$encabezado}</th>";
                                    }
                                    ?>
                                </tr>
                            </thead>
                            <tbody class="text-center">
                                <?php foreach ($proyectos as $proyecto):
                                    echo "<tr>";
                                    echo "<th scope='row'>{$proyecto['id_proyectos']}</th>";
                                    echo "<td>{$proyecto['titulo']}</td>";
                                    echo "<td>{$proyecto['fecha_inicio']}</td>";
                                    echo "<td>{$proyecto['fecha_fin']}</td>";
                                    echo "<td>{$proyecto['nombre']}</td>";
                                    echo "<td>{$proyecto['periodo']}</td>";
                                    if (isset($proyecto['total']) && ($rol == 'alumno' || $rol === 'investigador' || $rol === 'profesor')) {
                                        echo "<td>{$proyecto['total']}</td>";
                                    }
                                    echo "<td>{$proyectoControlador->botonesAccion($proyecto['id_proyectos'],$rol,$proyecto)}</td>";
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
                                        <?php $proyectoControlador->botonesAccion($id, $rol, $proyecto); ?>
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
