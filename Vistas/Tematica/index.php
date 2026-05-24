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

if (strtolower($_SESSION['rol'] ?? '') !== 'supervisor') {
    header("Location: /ITSFCP-PROYECTOS/Vistas/Principal/index.php");
    exit;
}

$action = isset($_GET['action']) ? $_GET['action'] : 'index';
$buscar = $_GET['buscar'] ?? '';
$pagina = intval($_GET['pagina'] ?? 1);

include "../../Controladores/tematicaControlador.php";

$tematicaControlador = new tematicaControlador();
if ($_SERVER['REQUEST_METHOD'] == 'GET' && $action == 'desactivar_tematica') {
    $id_tematica = intval($_GET['id_tematica']);

    $tematicaControlador->eliminar_tematica($id_tematica, $rol);

    // Redirigir para evitar doble ejecución
    header("Location: index.php");
    exit;
}

if (!method_exists($tematicaControlador, $action)) {
    die("Error: La acción '$action' no existe en el controlador.");
}

$resultado = $tematicaControlador->$action($rol, $buscar);

if (is_string($resultado)) {
    $resultado = json_decode($resultado, true);
}

$tematica = $resultado['tematica'] ?? [];

$paginacion = $resultado['paginacion'] ?? [
    'total' => count($tematica),
    'por_pagina' => 6,
    'pagina' => $pagina,
    'total_paginas' => max(1, ceil(count($tematica) / 6))
];

$filtros = $tematicaControlador->filtros($rol);
$encabezados = $tematicaControlador->encabezadosPrincipal($rol);
$opciones = $tematicaControlador->opciones($rol, $filtros);

ob_start();
include __DIR__ . '/../../mensaje.php';
?>

<div class="container-fluid py-4">

    <!-- TITULO -->
    <div class="row mb-4 align-items-center">

        <div class="col-12 col-md-6">
            <h3 class="fw-bold mb-2 mb-md-0">Temáticas</h3>
        </div>

        <div class="col-12 col-md-6 text-md-end">
            <?php if ($rol == "supervisor"): ?>
                <a href="crear.php" class="btn btn-primary">
                    <i class="bi bi-plus-lg"></i> Crear temática
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
                    placeholder="Buscar temática..."
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
                            <?php foreach ($tematica as $tem): ?>
                                <tr>
                                    <td><?= $tem['tematica'] ?></td>
                                    <td title="<?= htmlspecialchars($tem['descripcion']) ?>">
                                        <?= strlen($tem['descripcion']) > 60
                                            ? substr($tem['descripcion'], 0, 60) . '...'
                                            : $tem['descripcion']; ?>
                                    </td>
                                    <td><?= $tem['total'] ?></td>
                                    <td>
                                        <span class="badge rounded-pill text-bg-<?php echo $tematicaControlador->EstiloEstadoLista($tem['estado']); ?>">
                                            <?= htmlspecialchars($tem['estado']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?= date("d/m/Y", strtotime($tem['creacion'])) ?>
                                        <br>
                                        <?= date("H:i", strtotime($tem['creacion'])) ?>
                                    </td>
                                    <td>
                                        <?php
                                        if (!empty($tem['modificacion'])) {
                                            echo date("d/m/Y", strtotime($tem['modificacion'])) . "<br>";
                                            echo date("H:i", strtotime($tem['modificacion']));
                                        } else {
                                            echo "No modificado";
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <?= $tematicaControlador->botonesAccionPrincipal($tem['id_tematica'], $rol, $tem['estado']) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7">
                                    <div class="alert alert-danger">
                                        No tiene permiso para editar la temática y subtemática
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
        <?php foreach ($tematica as $tematica_item): ?>
            <div class="card shadow-sm mb-3">
                <div class="card-body text-center">
                    <h5 class="fw-bold">
                        <?= $tematica_item['tematica'] ?>
                    </h5>
                </div>
                <ul class="list-group list-group-flush">
                    <li class="list-group-item">
                        <strong>Descripción</strong>
                        <p class="mb-0" title="<?= htmlspecialchars($tematica_item['descripcion']) ?>">
                            <?= strlen($tematica_item['descripcion']) > 60
                                ? substr($tematica_item['descripcion'], 0, 60) . '...'
                                : $tematica_item['descripcion']; ?>
                        </p>
                    </li>
                    <li class="list-group-item">
                        <div class="row text-center">
                            <div class="col-6">
                                <strong>Creación</strong>
                                <p class="mb-0">
                                    <?= date("d/m/Y", strtotime($tematica_item['creacion'])) ?>
                                    <br>
                                    <?= date("H:i", strtotime($tematica_item['creacion'])) ?>
                                </p>
                            </div>
                            <div class="col-6">
                                <strong>Modificación</strong>
                                <br>
                                <?php if (!empty($tematica_item['modificacion'])) {
                                    echo date("d/m/Y", strtotime($tematica_item['modificacion'])) . "<br>";
                                    echo date("H:i", strtotime($tematica_item['modificacion']));
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
                                    <?= $tematica_item['total'] ?>
                                </p>
                            </div>
                            <div class="col-6">
                                <strong>Estado</strong>
                                <br>
                                <span class="badge rounded-pill text-bg-<?php echo $tematicaControlador->EstiloEstadoLista($tematica_item['estado']); ?>">
                                    <?= htmlspecialchars($tematica_item['estado']) ?>
                                </span>
                            </div>
                        </div>
                    </li>
                </ul>
                <div class="card-body">
                    <div class="d-flex justify-content-center gap-2">
                        <?php echo $tematicaControlador->botonesAccionPrincipal($tematica_item['id_tematica'], $rol, $tematica_item['estado']); ?>
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
$titulo = "Tematica y subtematica";
$bodyClass = "proyectos-page";

include __DIR__ . '/../../layout.php';

?>