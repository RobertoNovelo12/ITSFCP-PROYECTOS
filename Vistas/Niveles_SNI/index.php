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

include "../../Controladores/nivelsniControlador.php";

$nivelsniControlador = new nivelsniControlador();

if ($_SERVER['REQUEST_METHOD'] == 'GET' && $action == 'desactivar_niveles_sni') {
    $id_nivel = intval($_GET['id_nivel']);
    $nivelsniControlador->eliminar($rol, $id_nivel);
    header("Location: index.php");
    exit;
}

if (!method_exists($nivelsniControlador, $action)) {
    die("Error: La acción '$action' no existe en el controlador.");
}

$resultado = $nivelsniControlador->$action($rol, $buscar);

if (is_string($resultado)) {
    $resultado = json_decode($resultado, true);
}

$registros = $resultado['niveles_sni'] ?? [];

$paginacion = $resultado['paginacion'] ?? [
    'total' => count($registros),
    'por_pagina' => 6,
    'pagina' => $pagina,
    'total_paginas' => max(1, ceil(count($registros) / 6))
];

$filtros = $nivelsniControlador->filtros($rol);
$encabezados = $nivelsniControlador->encabezadosPrincipal($rol);
$opciones = $nivelsniControlador->opciones($rol, $filtros);

ob_start();
include __DIR__ . '/../../mensaje.php';
?>

<div class="container-fluid py-4">

    <!-- TITULO -->
    <div class="row mb-4 align-items-center">

        <div class="col-12 col-md-6">
            <h3 class="fw-bold mb-2 mb-md-0">Nivel SNI</h3>
        </div>

        <div class="col-12 col-md-6 text-md-end">
            <?php if ($rol == "supervisor"): ?>
                <a href="crear.php" class="btn btn-primary">
                    <i class="bi bi-plus-lg"></i> Crear Nivel SNI
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
                            <?php if (!empty($registros)) { ?>
                                <?php foreach ($registros as $reg): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($reg['nombre']) ?></td>
                                        <td>
                                            <?= date("d/m/Y", strtotime($reg['crear'])) ?>
                                        </td>
                                        <td>
                                            <?= date("H:i", strtotime($reg['crear'])) ?>
                                        </td>
                                        <td>
                                            <span class="badge rounded-pill text-bg-<?php echo $nivelsniControlador->EstiloEstadoLista($reg['estados']); ?>">
                                                <?= htmlspecialchars($reg['estados']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?= $nivelsniControlador->botonesAccionPrincipal($reg['id_nivel'], $rol, $reg['estados']) ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php } else { ?>
                                <td colspan="5">
                                    <div class="alert alert-danger">
                                        No hay Nivel SNI registrados
                                    </div>
                                </td>
                                </tr>
                            <?php } ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5">
                                    <div class="alert alert-danger">
                                        No tiene permiso para ver Nivel SNI
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- TARJETAS MOVIL -->
            <div class="d-block d-md-none">
                <?php foreach ($registros as $reg): ?>
                    <div class="card shadow-sm mb-3">
                        <div class="card-body text-center">
                            <h5 class="fw-bold">
                                <?= htmlspecialchars($reg['nombre']) ?>
                            </h5>
                            <h5 class="fw-bold">
                                <span class="badge rounded-pill text-bg-<?php echo $nivelsniControlador->EstiloEstadoLista($reg['estados']); ?>">
                                    <?= htmlspecialchars($reg['estados']) ?>
                                </span>
                            </h5>
                        </div>
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item">
                                <div class="row text-center">
                                    <div class="col-6">
                                        <strong>Fecha creación</strong>
                                        <p class="mb-0">
                                            <?= date("d/m/Y", strtotime($reg['crear'])) ?>
                                        </p>
                                    </div>
                                    <div class="col-6">
                                        <strong>Hora creación</strong>
                                        <p class="mb-0">
                                            <?= date("H:i", strtotime($reg['crear'])) ?>
                                        </p>
                                    </div>
                                </div>
                            </li>
                        </ul>
                        <div class="card-body">
                            <div class="d-flex justify-content-center gap-2">
                                <?php echo $nivelsniControlador->botonesAccionPrincipal($reg['id_nivel'], $rol, $reg['estados']); ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- PAGINACION -->
    <?php if ($paginacion['total_paginas'] > 1):
        $qBase = 'action=' . urlencode($action)
            . (!empty($buscar) ? '&buscar=' . urlencode($buscar) : '')
            . (!empty($tipo) ? '&tipo=' . urlencode($tipo) : '');
        $entidad = 'entradas';
        include __DIR__ . '/../../../publico/incluido/_paginacion.php';?>
    <?php endif; ?>
</div>
<?php

$contenido = ob_get_clean();
$titulo = "Nivel SNI";
$bodyClass = "proyectos-page";

include __DIR__ . '/../../layout.php';

?>