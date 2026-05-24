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

include "../../Controladores/lineainvestigacionControlador.php";

$lineaControlador = new lineaControlador();


if ($_SERVER['REQUEST_METHOD'] == 'GET' && $action == 'desactivar_linea') {
    $id_linea = intval($_GET['id_linea']);

    $lineaControlador->eliminar($rol, $id_linea);

    // Redirigir para evitar doble ejecución
    header("Location: index.php");
    exit;
}

if (!method_exists($lineaControlador, $action)) {
    die("Error: La acción '$action' no existe en el controlador.");
}

$resultado = $lineaControlador->$action($rol, $buscar);

if (is_string($resultado)) {
    $resultado = json_decode($resultado, true);
}

$lineas = $resultado['linea'] ?? [];

$paginacion = $resultado['paginacion'] ?? [
    'total' => count($lineas),
    'por_pagina' => 6,
    'pagina' => $pagina,
    'total_paginas' => max(1, ceil(count($lineas) / 6))
];

$filtros = $lineaControlador->filtros($rol);
$encabezados = $lineaControlador->encabezadosPrincipal($rol);
$opciones = $lineaControlador->opciones($rol, $filtros);

ob_start();
include __DIR__ . '/../../mensaje.php';
?>

<div class="container-fluid py-4">

    <!-- TITULO -->
    <div class="row mb-4 align-items-center">

        <div class="col-12 col-md-6">
            <h3 class="fw-bold mb-2 mb-md-0">Línea de investigación</h3>
        </div>

        <div class="col-12 col-md-6 text-md-end">
            <?php if ($rol == "supervisor"): ?>
                <a href="crear.php" class="btn btn-primary">
                    <i class="bi bi-plus-lg"></i> Crear Línea de investigación
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
                    placeholder="Buscar..."
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
                            <?php if (!empty($lineas)) { ?>
                                <?php foreach ($lineas as $lin): ?>
                                    <tr>
                                        <td><?= $lin['nombre'] ?></td>
                                        <td title="<?= htmlspecialchars($lin['descripcion']) ?>">
                                            <?= strlen($lin['descripcion']) > 60
                                                ? substr($lin['descripcion'], 0, 60) . '...'
                                                : $lin['descripcion']; ?>
                                        </td>
                                        <td>
                                            <?= date("d/m/Y", strtotime($lin['crear'])) ?>
                                        </td>
                                        <td>
                                            <?= date("H:i", strtotime($lin['crear'])) ?>
                                        </td>
                                        <td>
                                            <span class="badge rounded-pill text-bg-<?php echo $lineaControlador->EstiloEstadoLista($lin['estados']); ?>">
                                                <?= htmlspecialchars($lin['estados']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?= $lineaControlador->botonesAccionPrincipal($lin['id_linea'], $rol, $lin['estados']) ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php } else { ?>
                                <td colspan="7">
                                    <div class="alert alert-danger">
                                        No hay líneas de investigación
                                    </div>
                                </td>
                                </tr>
                            <?php } ?>
                        <?php else: ?>

                            <tr>
                                <td colspan="7">
                                    <div class="alert alert-danger">
                                        No tiene permiso para editar la línea de investigación
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
        <?php foreach ($lineas as $linea_item): ?>
            <div class="card shadow-sm mb-3">
                <div class="card-body text-center">
                    <h5 class="fw-bold">
                        <?= $linea_item['nombre'] ?>
                    </h5>
                    <h5 class="fw-bold">
                        <span class="badge rounded-pill text-bg-<?php echo $lineaControlador->EstiloEstadoLista($linea_item['estados']); ?>">
                            <?= htmlspecialchars($linea_item['estados']) ?>
                        </span>
                    </h5>
                </div>
                <ul class="list-group list-group-flush">
                    <li class="list-group-item">
                        <div class="row text-center">
                            <div class="col-12">
                                <strong>Descripción</strong>
                                <p class="mb-0" title="<?= htmlspecialchars($lin['descripcion']) ?>">
                                    <?= strlen($lin['descripcion']) > 60
                                        ? substr($lin['descripcion'], 0, 60) . '...'
                                        : $lin['descripcion']; ?>
                                </p>
                            </div>
                        </div>
                        <div class="row text-center">
                            <div class="col-6">
                                <strong>Fecha creacion</strong>
                                <p class="mb-0">
                                    <?= date("d/m/Y", strtotime($lin['crear'])) ?>
                                </p>
                            </div>
                            <div class="col-6">
                                <strong>Hora creación</strong>
                                <p class="mb-0">
                                    <?= date("H:i", strtotime($lin['crear'])) ?>
                                </p>
                            </div>
                        </div>
                    </li>
                </ul>
                <div class="card-body">
                    <div class="d-flex justify-content-center gap-2">
                        <?php echo $lineaControlador->botonesAccionPrincipal($linea_item['id_linea'], $rol, $linea_item['estados']); ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- PAGINACION -->

    <?php if ($paginacion['total_paginas'] > 1):
        $qBase = 'action=' . urlencode($action)
            . (!empty($buscar) ? '&buscar=' . urlencode($buscar) : '');        $entidad = 'entradas';
        include __DIR__ . '/../../../publico/incluido/_paginacion.php';?>
    <?php endif; ?>
</div>
<?php

$contenido = ob_get_clean();
$titulo = "Líneas de investigación";
$bodyClass = "proyectos-page";

include __DIR__ . '/../../layout.php';

?>