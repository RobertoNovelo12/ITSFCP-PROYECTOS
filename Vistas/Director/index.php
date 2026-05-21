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

//Solo supervisor
if ($rol ?? '' !== 'supervisor') {
    header("Location: /ITSFCP-PROYECTOS/Vistas/Principal/index.php");
    exit;
}

$action = isset($_GET['action']) ? $_GET['action'] : 'index';
$buscar = $_GET['buscar'] ?? '';
$pagina = intval($_GET['pagina'] ?? 1);

include "../../Controladores/directorControlador.php";

$directorControlador = new directorControlador();

if ($_SERVER['REQUEST_METHOD'] == 'GET' && $action == 'desactivar_director') {
    $id_director = intval($_GET['id_director']);
    $directorControlador->eliminar($rol, $id_director);
    header("Location: index.php");
    exit;
}

if (!method_exists($directorControlador, $action)) {
    die("Error: La acción '$action' no existe en el controlador.");
}

$resultado = $directorControlador->$action($rol, $buscar);

if (is_string($resultado)) {
    $resultado = json_decode($resultado, true);
}

$directores = $resultado['director'] ?? [];

$paginacion = $resultado['paginacion'] ?? [
    'total' => count($directores),
    'por_pagina' => 6,
    'pagina' => $pagina,
    'total_paginas' => max(1, ceil(count($directores) / 6))
];

$filtros = $directorControlador->filtros($rol);
$encabezados = $directorControlador->encabezadosPrincipal($rol);
$opciones = $directorControlador->opciones($rol, $filtros);

ob_start();
include __DIR__ . '/../../mensaje.php';
?>

<div class="container-fluid py-4">

    <!-- TITULO -->
    <div class="row mb-4 align-items-center">

        <div class="col-12 col-md-6">
            <h3 class="fw-bold mb-2 mb-md-0">Director</h3>
        </div>

        <div class="col-12 col-md-6 text-md-end">
            <?php if ($rol == "supervisor"): ?>
                <a href="crear.php" class="btn btn-primary">
                    <i class="bi bi-plus-lg"></i> Crear Director
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- FILTROS Y BUSQUEDA -->
    <div class="row g-2 mb-4">
        <div class="col-12 col-md-4">
            <select class="form-select"
                onchange="location.href='index.php?action=' + this.value;">
                <?php foreach ($opciones as $key => $label): ?>
                    <option value="<?= htmlspecialchars($key) ?>"
                        <?= ($action === $key) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($label) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 col-md-8">
            <form class="d-flex gap-2" method="GET" action="index.php">
                <input type="hidden" name="action" value="<?= htmlspecialchars($action) ?>">
                <input type="text"
                    name="buscar"
                    class="form-control"
                    placeholder="Buscar por nombre, apellido o correo..."
                    value="<?= htmlspecialchars($buscar) ?>">
                <button type="submit" class="btn btn-primary">
                    Buscar
                </button>
            </form>
        </div>
    </div>

    <!-- TABLA LAPTOP -->
    <div class="card shadow-sm d-none d-md-block">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <?php
                            foreach ($encabezados as $encabezado) {
                                echo "<th>{$encabezado}</th>";
                            }
                            ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($rol == "supervisor"): ?>
                            <?php if (!empty($directores)) { ?>
                                <?php foreach ($directores as $dir): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($dir['nombre']) ?></td>
                                        <td><?= htmlspecialchars($dir['apellido']) ?></td>
                                        <td><?= htmlspecialchars($dir['correo'] ?? '—') ?></td>
                                        <td><?= htmlspecialchars($dir['telefono'] ?? '—') ?></td>
                                        <td><?= htmlspecialchars($dir['nombre_grado']) ?></td>
                                        <td>
                                            <span class="badge rounded-pill text-bg-<?php echo $directorControlador->EstiloEstadoLista($dir['estados']); ?>">
                                                <?= htmlspecialchars($dir['estados']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?= $directorControlador->botonesAccionPrincipal($dir['id_director'], $rol, $dir['estados']) ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php } else { ?>
                                <td colspan="9">
                                    <div class="alert alert-danger">
                                        No hay directores registrados
                                    </div>
                                </td>
                                </tr>
                            <?php } ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9">
                                    <div class="alert alert-danger">
                                        No tiene permiso para ver directores
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- TARJETAS MOVIL -->
    <div class="d-block d-md-none">
        <?php foreach ($directores as $dir): ?>
            <div class="card shadow-sm mb-3">
                <div class="card-body text-center">
                    <h5 class="fw-bold">
                        <?= htmlspecialchars($dir['nombre']) ?> <?= htmlspecialchars($dir['apellido']) ?>
                    </h5>
                    <h5 class="fw-bold">
                        <span class="badge rounded-pill text-bg-<?php echo $directorControlador->EstiloEstadoLista($dir['estados']); ?>">
                            <?= htmlspecialchars($dir['estados']) ?>
                        </span>
                    </h5>
                </div>
                <ul class="list-group list-group-flush">
                    <li class="list-group-item">
                        <div class="row text-center">
                            <div class="col-12">
                                <strong>Correo</strong>
                                <p class="mb-0"><?= htmlspecialchars($dir['correo'] ?? '—') ?></p>
                            </div>
                        </div>
                        <div class="row text-center">
                            <div class="col-6">
                                <strong>Teléfono</strong>
                                <p class="mb-0"><?= htmlspecialchars($dir['telefono'] ?? '—') ?></p>
                            </div>
                            <div class="col-6">
                                <strong>Grado</strong>
                                <p class="mb-0"><?= htmlspecialchars($dir['nombre_grado']) ?></p>
                            </div>
                        </div>
                    </li>
                </ul>
                <div class="card-body">
                    <div class="d-flex justify-content-center gap-2">
                        <?php echo $directorControlador->botonesAccionPrincipal($dir['id_director'], $rol, $dir['estados']); ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- PAGINACION -->
    <?php if ($paginacion['total_paginas'] > 1): ?>
        <nav class="mt-4">
            <ul class="pagination justify-content-center">
                <?php
                $inicio = ($paginacion['pagina'] - 1) * $paginacion['por_pagina'] + 1;
                $fin = min($inicio + $paginacion['por_pagina'] - 1, $paginacion['total']);
                ?>
                <li class="page-item disabled">
                    <span class="page-link">
                        Mostrando <?= $inicio ?> a <?= $fin ?> de <?= $paginacion['total'] ?> entradas
                    </span>
                </li>
                <?php for ($i = 1; $i <= $paginacion['total_paginas']; $i++): ?>
                    <li class="page-item <?= ($i == $paginacion['pagina']) ? 'active' : '' ?>">
                        <a class="page-link"
                            href="?action=<?= htmlspecialchars($action) ?>&pagina=<?= $i ?><?= !empty($buscar) ? '&buscar=' . urlencode($buscar) : '' ?>">
                            <?= $i ?>
                        </a>
                    </li>
                <?php endfor; ?>
            </ul>
        </nav>
    <?php endif; ?>
</div>
<?php

$contenido = ob_get_clean();
$titulo = "Director";
$bodyClass = "proyectos-page";

include __DIR__ . '/../../layout.php';

?>