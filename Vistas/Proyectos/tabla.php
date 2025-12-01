<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

if (!isset($_SESSION['id_usuario'])) {
    header("Location: /ITSFCP-PROYECTOS/index.php");
    exit;
}

$rol = strtolower($_SESSION['rol'] ?? '');
$id_usuario = intval($_SESSION['id_usuario']);
$nombre_user = htmlspecialchars($_SESSION['nombre'] ?? 'Usuario', ENT_QUOTES, 'UTF-8');

$action = $_GET['action'] ?? 'index';
$buscar = $_GET['buscar'] ?? '';

require_once "../../Controladores/proyectoControlador.php";
$proyectoControlador = new ProyectoControlador();

if (!method_exists($proyectoControlador, $action)) {
    die("Error: La acción '$action' no existe en el controlador.");
}

// Actualización de estados
if ($_SERVER['REQUEST_METHOD'] == 'POST' && ($_POST['action'] ?? '') == 'actualizarestadoRechazo' && $rol == "supervisor") {
    $proyectoControlador->actualizarestadoRechazo($_POST, $id_usuario, $rol);
}

if ($_SERVER['REQUEST_METHOD'] == 'GET' && $action == 'actualizarestado') {
    if (isset($_GET['id_proyectos'], $_GET['tipo'])) {
        $proyectoControlador->actualizarestado($_GET['id_proyectos'], $rol, $_GET['tipo']);
    }
}

// Obtener proyectos
$resultado = $proyectoControlador->$action($id_usuario, $rol, $buscar);

if (!is_array($resultado)) {
    die("Error: La acción '$action' no devolvió un array.");
}

$proyectos = $resultado['proyectos'] ?? [];
$paginacion = $resultado['paginacion'] ?? [
    'total_proyectos' => count($proyectos),
    'por_pagina' => 6,
    'pagina' => 1,
    'total_paginas' => 1
];

$filtros = $proyectoControlador->filtros($id_usuario, $rol);
$encabezados = $proyectoControlador->encabezados($rol);
$opciones = $proyectoControlador->datosopciones($rol, $filtros);

// ======================
// GENERAR CONTENIDO
// ======================
ob_start();
?>

<div class="container-fluid py-4">

    <div class="row mb-3 align-items-center">
        <div class="col-md-6">
            <h2 class="mb-0">Proyectos</h2>
        </div>
        <div class="col-md-6 text-md-end">
            <?php if ($rol == "investigador" || $rol == "profesor"): ?>
                <a href="crear.php" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Crear proyecto</a>
            <?php endif; ?>
        </div>
    </div>

    <!-- BOTONES DE FILTRO -->
    <div class="row mb-3">
        <div class="d-flex flex-wrap gap-2">
            <?php foreach ($opciones as $key => $label): 
                $clase = ($action === $key) ? "btn btn-primary" : "btn btn-outline-primary"; ?>
                <a href="tabla.php?action=<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>" class="<?= $clase ?>"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- BÚSQUEDA -->
    <div class="row mb-3">
        <div class="col-12 col-md-6">
            <form class="d-flex gap-2" method="GET" action="tabla.php">
                <input type="hidden" name="action" value="<?= htmlspecialchars($action, ENT_QUOTES, 'UTF-8') ?>">
                <input type="text" name="buscar" class="form-control" placeholder="Buscar..." value="<?= htmlspecialchars($buscar ?? '', ENT_QUOTES, 'UTF-8') ?>">
                <button type="submit" class="btn btn-primary">Buscar</button>
            </form>
        </div>
    </div>

    <!-- TABLA DE PROYECTOS -->
    <div class="row">
        <div class="col-12">
            <?php if (!empty($proyectos)): ?>
                <div class="table-responsive">
                    <table class="table table-hover text-center align-middle">
                        <thead>
                            <tr>
                                <?php foreach ($encabezados as $encabezado): ?>
                                    <th><?= htmlspecialchars($encabezado ?? '', ENT_QUOTES, 'UTF-8') ?></th>
                                <?php endforeach; ?>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($proyectos as $proyecto): ?>
                                <tr>
                                    <th scope="row"><?= $proyecto['id_proyectos'] ?? '-' ?></th>
                                    <td><?= htmlspecialchars($proyecto['titulo'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= $proyecto['fecha_inicio'] ?? '-' ?></td>
                                    <td><?= $proyecto['fecha_fin'] ?? '-' ?></td>
                                    <td><?= htmlspecialchars($proyecto['nombre'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= $proyecto['periodo'] ?? '-' ?></td>
                                    <td>
                                        <?= $proyectoControlador->botonesAccion($proyecto['id_proyectos'] ?? 0, $rol, $proyecto['nombre'] ?? '-') ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <!-- PAGINACIÓN -->
                    <?php
                    $inicio = ($paginacion['pagina'] - 1) * $paginacion['por_pagina'] + 1;
                    $fin = min($inicio + $paginacion['por_pagina'] - 1, $paginacion['total_proyectos']);
                    ?>
                    <nav>
                        <ul class="pagination justify-content-center">
                            <li class="page-item disabled">
                                <span class="page-link">
                                    Mostrando <?= $inicio ?> a <?= $fin ?> de <?= $paginacion['total_proyectos'] ?> entradas
                                </span>
                            </li>
                            <?php for ($i = 1; $i <= $paginacion['total_paginas']; $i++): ?>
                                <li class="page-item <?= ($i == $paginacion['pagina']) ? 'active' : '' ?>">
                                    <a class="page-link" href="?action=<?= htmlspecialchars($action, ENT_QUOTES, 'UTF-8') ?>&pagina=<?= $i ?>"><?= $i ?></a>
                                </li>
                            <?php endfor; ?>
                        </ul>
                    </nav>
                </div>

                <!-- TARJETAS MÓVILES -->
                <div class="d-block d-md-none mt-4">
                    <?php foreach ($proyectos as $proyecto): ?>
                        <div class="card mb-3 shadow-sm">
                            <div class="card-body">
                                <h5 class="card-title"><?= $proyecto['id_proyectos'] ?? '-' ?></h5>
                                <p class="card-text"><?= htmlspecialchars($proyecto['titulo'] ?? '-', ENT_QUOTES, 'UTF-8') ?></p>
                                <p class="mb-1"><strong>Inicio:</strong> <?= $proyecto['fecha_inicio'] ?? '-' ?></p>
                                <p class="mb-1"><strong>Fin:</strong> <?= $proyecto['fecha_fin'] ?? '-' ?></p>
                                <p class="mb-1"><strong>Periodo:</strong> <?= $proyecto['periodo'] ?? '-' ?></p>
                                <p><strong>Responsable:</strong> <?= htmlspecialchars($proyecto['nombre'] ?? '-', ENT_QUOTES, 'UTF-8') ?></p>
                                <div class="mt-2">
                                    <?= $proyectoControlador->botonesAccion($proyecto['id_proyectos'] ?? 0, $rol, $proyecto['nombre'] ?? '-') ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

            <?php else: ?>
                <div class="alert alert-info">No hay proyectos para mostrar.</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
    [...tooltipTriggerList].forEach(t => new bootstrap.Tooltip(t));
});
</script>

<?php
$contenido = ob_get_clean();
$titulo = "Proyectos";
$bodyClass = "proyectos-page";

include __DIR__ . '/../../layout.php';
?>