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
if ($rol !== 'supervisor') {
    header("Location: /ITSFCP-PROYECTOS/Vistas/Principal/index.php");
    exit;
}

$action = isset($_GET['action']) ? $_GET['action'] : 'index';
$buscar = $_GET['buscar'] ?? '';
$pagina = intval($_GET['pagina'] ?? 1);

include "../../Controladores/areaconocimientoControlador.php";

$areaControlador = new AreaConocimientoControlador();

if ($_SERVER['REQUEST_METHOD'] == 'GET' && $action == 'desactivar_area') {
    $id_area = intval($_GET['id_area']);

    $areaControlador->eliminar_area($id_area, $rol);

    // Redirigir para evitar doble ejecución
    header("Location: index.php");
    exit;
}


if (!method_exists($areaControlador, $action)) {
    die("Error: La acción '$action' no existe en el controlador.");
}

$resultado = $areaControlador->$action($rol, $buscar);

if (is_string($resultado)) {
    $resultado = json_decode($resultado, true);
}

$area = $resultado['area'] ?? [];

$paginacion = $resultado['paginacion'] ?? [
    'total' => count($area),
    'por_pagina' => 6,
    'pagina' => $pagina,
    'total_paginas' => max(1, ceil(count($area) / 6))
];

$filtros = $areaControlador->filtros($rol);

$encabezados = $areaControlador->encabezadosPrincipal($rol);
$opciones = $areaControlador->opciones($rol, $filtros);

ob_start();
include __DIR__ . '/../../mensaje.php';
?>

<div class="container-fluid py-4">

    <!-- TITULO -->
    <div class="row mb-4 align-items-center">

        <div class="col-6 col-md-6">
            <h3 class="fw-bold mb-2 mb-md-0">Áreas de conocimientos</h3>
        </div>

        <div class="col-6 col-md-6 text-md-end">
            <?php if ($rol == "supervisor"): ?>
                <a href="crear.php" class="btn btn-primary">
                    <i class="bi bi-plus-lg"></i> Crear área de conocimiento
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
                    placeholder="Buscar Área..."
                    value="<?= htmlspecialchars($buscar) ?>">
                <button type="submit" class="btn btn-primary">
                    Buscar
                </button>
            </form>
        </div>
    </div>
    <!-- TABLA LAPTOP -->
    <div class="row">
        <div class="col-12">
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
                                    <?php foreach ($area as $ar): ?>
                                        <tr>
                                            <td><?= $ar['nombre'] ?></td>
                                            <td title="<?= htmlspecialchars($ar['descripcion']) ?>">
                                                <?= strlen($ar['descripcion']) > 60
                                                    ? substr($ar['descripcion'], 0, 60) . '...'
                                                    : $ar['descripcion']; ?>
                                            </td>
                                            <td><?= $ar['total'] ?></td>
                                            <td>
                                                <span class="badge rounded-pill text-bg-<?php echo $areaControlador->EstiloEstadoLista($ar['estado']); ?>">
                                                    <?= htmlspecialchars($ar['estado']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?= date("d/m/Y", strtotime($ar['creacion'])) ?>
                                                <br>
                                                <?= date("H:i", strtotime($ar['creacion'])) ?>
                                            </td>
                                            <td>
                                                <?php
                                                if (!empty($ar['modificacion'])) {
                                                    echo date("d/m/Y", strtotime($ar['modificacion'])) . "<br>";
                                                    echo date("H:i", strtotime($ar['modificacion']));
                                                } else {
                                                    echo "No modificado";
                                                }
                                                ?>
                                            </td>
                                            <td>
                                                <?= $areaControlador->botonesAccionPrincipal($ar['id_area'], $rol, $ar['estado']) ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7">
                                            <div class="alert alert-danger">
                                                No tiene permiso para editar el área y subarea de
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
                <?php foreach ($area as $ar_item): ?>
                    <div class="card shadow-sm mb-3">
                        <div class="card-body text-center">
                            <h5 class="fw-bold">
                                <?= $ar_item['tematica'] ?>
                            </h5>
                        </div>
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item">
                                <strong>Descripción</strong>
                                <p class="mb-0" title="<?= htmlspecialchars($ar_item['descripcion']) ?>">
                                    <?= strlen($ar_item['descripcion']) > 60
                                        ? substr($ar_item['descripcion'], 0, 60) . '...'
                                        : $ar_item['descripcion']; ?>
                                </p>
                            </li>
                            <li class="list-group-item">
                                <div class="row text-center">
                                    <div class="col-6">
                                        <strong>Creación</strong>
                                        <p class="mb-0">
                                            <?= date("d/m/Y", strtotime($ar_item['creacion'])) ?>
                                            <br>
                                            <?= date("H:i", strtotime($ar_item['creacion'])) ?>
                                        </p>
                                    </div>
                                    <div class="col-6">
                                        <strong>Modificación</strong>
                                        <br>
                                        <?php if (!empty($ar_item['modificacion'])) {
                                            echo date("d/m/Y", strtotime($ar_item['modificacion'])) . "<br>";
                                            echo date("H:i", strtotime($ar_item['modificacion']));
                                        } else {
                                            echo "No modificado";
                                        } ?>
                                    </div>
                                </div>
                            </li>
                            <li class="list-group-item">
                                <div class="row text-center">
                                    <div class="col-6">
                                        <strong>Subtemáticas</strong>
                                        <p class="mb-0">
                                            <?= $ar_item['total'] ?>
                                        </p>
                                    </div>
                                    <div class="col-6">
                                        <strong>Estado</strong>
                                        <br>
                                        <span class="badge rounded-pill text-bg-<?php echo $areaControlador->EstiloEstadoLista($ar_item['estado']); ?>">
                                            <?= htmlspecialchars($ar_item['estado']) ?>
                                        </span>
                                    </div>
                                </div>
                            </li>
                        </ul>
                        <div class="card-body">
                            <div class="d-flex justify-content-center gap-2">
                                <?php echo $areaControlador->botonesAccionPrincipal($ar_item['id_area'], $rol, $ar_item['estado']); ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- PAGINACION -->

            <?php if ($paginacion['total_paginas'] > 1):
                $qBase = 'action=' . urlencode($action)
                    . (!empty($buscar) ? '&buscar=' . urlencode($buscar) : '')
                    . (!empty($tipo) ? '&tipo=' . urlencode($tipo) : '');
                $entidad = 'entradas';
                include __DIR__ . '../../../publico/incluido/_paginacion.php'; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php

$contenido = ob_get_clean();
$titulo = "Área y subarea de conocimientos";
$bodyClass = "proyectos-page";

include __DIR__ . '/../../layout.php';

?>