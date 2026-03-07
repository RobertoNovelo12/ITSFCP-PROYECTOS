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
//ACCIONES
$action = isset($_GET['action']) ? $_GET['action'] : 'index';
$buscar = $_GET['buscar'] ?? '';
$pagina = intval($_GET['pagina'] ?? 1);

//LLAMADA AL CONTROLADOR
include "../../Controladores/tematicaControlador.php";

$tematicaControlador = new tematicaControlador();
//Si no existe el controlador que mande un mensaje
if (!method_exists($tematicaControlador, $action)) {
    die("Error: La acción '$action' no existe en el controlador.");
}
//Se ejecuta la acción del controlador

if ($_SERVER['REQUEST_METHOD'] == 'GET' && $action == 'desactivar_tematica') {
    $id_tematica = intval($_GET['id_tematica']);
    $tematicaControlador->eliminar_tematica($id_tematica, $rol);
}

//EJECUTAR ACCION
$resultado = $tematicaControlador->$action($rol, $buscar);
// Si viene como JSON, decodificar
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
// GENERAR CONTENIDO
ob_start();
include __DIR__ . '/../../mensaje.php';
?>

<div class="container-fluid py-4">

    <div class="row mb-3 align-items-center">
        <div class="col-md-6">
            <h3 class="mb-0 fw-bold">Temáticas</h3>
        </div>
        <div class="col-md-6 text-md-end">
            <?php if ($rol == "supervisor"): ?>
                <a href="crear.php" class="btn btn-primary">
                    <i class="bi bi-plus-lg"></i> Crear temática
                </a>
            <?php endif; ?>
        </div>

    </div>
    <!-- FILTROS Y BÚSQUEDA -->
    <div class="row mb-3">
        <div class="col-12 text-end">
            <div class="row justify-content-end">
                <div class="col-md-6 mb-3">
                    <select class="form-select"
                        onchange="location.href='tabla.php?action=' + this.value;">

                        <?php foreach ($opciones as $key => $label): ?>
                            <option value="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>"
                                <?= ($action === $key) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <form class="d-flex gap-2" method="GET" action="tabla.php">
                        <input type="hidden" name="action" value="<?= htmlspecialchars($action, ENT_QUOTES, 'UTF-8') ?>">
                        <input type="text"
                            name="buscar"
                            class="form-control"
                            placeholder="Buscar..."
                            value="<?= htmlspecialchars($buscar, ENT_QUOTES, 'UTF-8') ?>">
                        <button type="submit" class="btn btn-primary">Buscar</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <div class="row mb-3">
        <div class="col-12">
            <div class="table-responsive">
                <table class="table table-hover text-center align-middle" id="tabla_informacion">
                    <thead class="text-center">
                        <tr>
                            <?php
                            foreach ($encabezados as $encabezado) {
                                echo "<th scope='col'>{$encabezado}</th>";
                            }
                            ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($rol == "supervisor") { ?>

                            <?php foreach ($tematica as $tem): ?>
                                <tr>
                                    <td><?= $tem['tematica'] ?></td>
                                    <td><?= strlen($tem['descripcion']) > 80
                                            ? substr($tem['descripcion'], 0, 80) . '...'
                                            : $tem['descripcion']; ?></td>
                                    <td><?= $tem['total'] ?></td>


                                    <!-- Estado de la tematica -->
                                    <td><span class="badge text-bg-<?php echo $tematicaControlador->EstiloEstadoLista($tem['estado']); ?>"><?= htmlspecialchars($tem['estado'] ?? '-', ENT_QUOTES, 'UTF-8') ?></span></td>

                                    <td>
                                        <?= $tematicaControlador->botonesAccionPrincipal(
                                            $tem['id_tematica'],
                                            $rol,
                                            $tem['estado']
                                        ) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>


                        <?php } else { ?>

                            <h3 style="font: white;background: red; width:100%;">No tiene permiso para editar la temática y subtematica</h3>

                        <?php } ?>
                    </tbody>

                </table>

                <!-- PAGINACIÓN -->
                <?php if ($paginacion['total_paginas'] > 1): ?>
                    <nav>
                        <ul class="pagination justify-content-center">
                            <?php
                            $inicio = ($paginacion['pagina'] - 1) * $paginacion['por_pagina'] + 1;
                            $fin = min($inicio + $paginacion['por_pagina'] - 1, $paginacion['total_proyectos']);
                            ?>
                            <li class="page-item disabled">
                                <span class="page-link">
                                    Mostrando <?= $inicio ?> a <?= $fin ?> de <?= $paginacion['total_proyectos'] ?> entradas
                                </span>
                            </li>
                            <?php for ($i = 1; $i <= $paginacion['total_paginas']; $i++): ?>
                                <li class="page-item <?= ($i == $paginacion['pagina']) ? 'active' : '' ?>">
                                    <a class="page-link" href="?action=<?= htmlspecialchars($action) ?>&pagina=<?= $i ?><?= !empty($buscar) ? '&buscar=' . urlencode($buscar) : '' ?>"><?= $i ?></a>
                                </li>
                            <?php endfor; ?>
                        </ul>
                    </nav>
                <?php endif; ?>

            </div>
            <div class="d-block d-md-none mt-4">

                <?php foreach ($tematica as $tematica_item): ?>
                    <div class="card mb-3" id="tarjeta_móvil" style="width: 18rem;">
                        <div class="card-body">
                            <p class="card-text fw-bold text-center"><strong><?php echo $tematica_item['tematica'] ?></strong></p>
                        </div>

                        <ul class="list-group list-group-flush">
                            <li class="list-group-item">
                                <div class="row">
                                    <div class="col-12">
                                        <label><strong>Descripción</strong></label>
                                        <p class="card-text"><?= strlen($tematica_item['descripcion']) > 80
                                                                    ? substr($tematica_item['descripcion'], 0, 80) . '...'
                                                                    : $tematica_item['descripcion']; ?></p>
                                    </div>
                                </div>
                            </li>
                            <li class="list-group-item">

                                <div class="row">
                                    <div class="col-6">
                                        <label><strong>Subtematicas</strong></label>
                                        <p class="card-text text-center"><?php echo ($tematica_item['total']) ?></p>
                                    </div>
                                    <div class="col-6">
                                        <label><strong>Estado</strong></label>
                                        <span class="text-center badge text-bg-<?php echo $tematicaControlador->EstiloEstadoLista($tematica_item['estado']); ?>"><?= htmlspecialchars($tematica_item['estado'] ?? '-', ENT_QUOTES, 'UTF-8') ?></span>
                                    </div>
                                </div>
                            </li>
                        </ul>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-12">
                                    <?php echo $tematicaControlador->botonesAccionPrincipal($tematica_item['id_tematica'], $rol, $tematica_item['estado']); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach;  ?>
                <?php if ($paginacion['total_paginas'] > 1): ?>
                    <nav>
                        <ul class="pagination justify-content-center">
                            <?php
                            $inicio = ($paginacion['pagina'] - 1) * $paginacion['por_pagina'] + 1;
                            $fin = min($inicio + $paginacion['por_pagina'] - 1, $paginacion['total_proyectos']);
                            ?>
                            <li class="page-item disabled">
                                <span class="page-link">
                                    Mostrando <?= $inicio ?> a <?= $fin ?> de <?= $paginacion['total_proyectos'] ?> entradas
                                </span>
                            </li>
                            <?php for ($i = 1; $i <= $paginacion['total_paginas']; $i++): ?>
                                <li class="page-item <?= ($i == $paginacion['pagina']) ? 'active' : '' ?>">
                                    <a class="page-link" href="?action=<?= htmlspecialchars($action) ?>&pagina=<?= $i ?><?= !empty($buscar) ? '&buscar=' . urlencode($buscar) : '' ?>"><?= $i ?></a>
                                </li>
                            <?php endfor; ?>
                        </ul>
                    </nav>
                <?php endif; ?>
            </div>
        </div>
    </div>

</div>
</div>
<?php
$contenido = ob_get_clean();
$titulo = "Tematica y subtematica";
$bodyClass = "proyectos-page";

include __DIR__ . '/../../layout.php';
?>