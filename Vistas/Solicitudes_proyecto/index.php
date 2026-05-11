<!--index.php - > Tabla principal con filtros y acciones.-->
<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

if (!isset($_SESSION['id_usuario'])) {
    header("Location: /ITSFCP-PROYECTOS/index.php");
    exit;
}

$rol        = strtolower($_SESSION['rol'] ?? '');
$id_usuario = intval($_SESSION['id_usuario']);

// Solo supervisor e investigador acceden a solicitudes
if (!in_array($rol, ['supervisor'])) {
    header("Location: ../proyectos/tabla.php");
    exit;
}

$tipo_filtro  = $_GET['tipo']   ?? 'Todas';      // Todas | Creacion | Cierre | Pendientes
$buscar       = $_GET['buscar'] ?? '';
$pagina       = intval($_GET['pagina'] ?? 1);
$id_periodo   = intval($_GET['id_periodo'] ?? 0); // 0 = todos los periodos

require_once "../../Controladores/proyectoControlador.php";
$proyectoControlador = new ProyectoControlador();

// Obtener todos los periodos para el filtro
$periodos = $proyectoControlador->obtenerTodosPeriodos();

// Resumen de conteos para el supervisor
$resumen = $proyectoControlador->resumenSolicitudes($rol, $id_usuario, $id_periodo);

// Listado de solicitudes paginado
$resultado = $proyectoControlador->listarSolicitudes($rol, $id_usuario, $tipo_filtro, $buscar, $pagina, $id_periodo);

if (is_string($resultado)) {
    $resultado = json_decode($resultado, true);
}

$solicitudes = $resultado['solicitudes'] ?? [];
$paginacion  = $resultado['paginacion']  ?? [
    'total'        => count($solicitudes),
    'por_pagina'   => 6,
    'pagina'       => $pagina,
    'total_paginas' => max(1, ceil(count($solicitudes) / 6))
];

ob_start();
include __DIR__ . '/../../mensaje.php';
?>

<div class="container-fluid py-4" style="max-width:95%;">

    <!-- CABECERA -->
    <div class="row mb-3 align-items-center">
        <div class="col-md-6">
            <h2 class="mb-0 fw-bold">Solicitudes de Proyectos</h2>
        </div>
        <div class="col-md-6 text-md-end">
            <!-- Filtro por Periodo -->
            <form class="d-inline-flex align-items-center gap-2" method="GET">
                <input type="hidden" name="tipo" value="<?= htmlspecialchars($tipo_filtro) ?>">
                <input type="hidden" name="buscar" value="<?= htmlspecialchars($buscar) ?>">
                <label class="mb-0 text-nowrap fw-semibold">Periodo:</label>
                <select name="id_periodo" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="0" <?= $id_periodo === 0 ? 'selected' : '' ?>>Todos</option>
                    <?php foreach ($periodos as $p): ?>
                        <option value="<?= $p['id_periodos'] ?>"
                            <?= ($id_periodo === intval($p['id_periodos'])) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($p['periodo']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>
    </div>

    <!-- TARJETAS RESUMEN (solo supervisor) -->
    <?php if ($rol === 'supervisor'): ?>
        <div class="row mb-4 g-3">

            <div class="col-6 col-md-3">
                <div class="card text-center border-primary shadow-sm h-100">
                    <div class="card-body">
                        <div class="display-6 fw-bold text-primary"><?= $resumen['total'] ?? 0 ?></div>
                        <div class="text-muted small">Total solicitudes</div>
                    </div>
                </div>
            </div>

            <div class="col-6 col-md-3">
                <div class="card text-center border-warning shadow-sm h-100">
                    <div class="card-body">
                        <div class="display-6 fw-bold text-warning"><?= $resumen['pendientes_creacion'] ?? 0 ?></div>
                        <div class="text-muted small">Pendientes creación</div>
                    </div>
                </div>
            </div>

            <div class="col-6 col-md-3">
                <div class="card text-center border-info shadow-sm h-100">
                    <div class="card-body">
                        <div class="display-6 fw-bold text-info"><?= $resumen['pendientes_cierre'] ?? 0 ?></div>
                        <div class="text-muted small">Pendientes cierre</div>
                    </div>
                </div>
            </div>

            <div class="col-6 col-md-3">
                <div class="card text-center border-success shadow-sm h-100">
                    <div class="card-body">
                        <div class="display-6 fw-bold text-success"><?= $resumen['aprobadas'] ?? 0 ?></div>
                        <div class="text-muted small">Aprobadas</div>
                    </div>
                </div>
            </div>

        </div>
    <?php endif; ?>

    <!-- FILTROS DE TIPO + BÚSQUEDA -->
    <div class="row mb-3 align-items-end">

        <!-- Tabs / filtros de tipo -->
        <div class="col-md-6 mb-2 mb-md-0">
            <?php
            $tabs = [
                'Todas'      => 'Todas',
                'Creacion'   => 'Creación',
                'Cierre'     => 'Cierre',
                'Pendientes' => 'Pendientes',
            ];
            ?>

            <select class="form-select"
                onchange="location.href='?tipo=' + this.value + '&buscar=<?= urlencode($buscar) ?>&id_periodo=<?= $id_periodo ?>'">

                <?php foreach ($tabs as $key => $label): ?>
                    <option value="<?= htmlspecialchars($key) ?>"
                        <?= ($tipo_filtro === $key) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($label) ?>
                    </option>
                <?php endforeach; ?>

            </select>
        </div>

        <!-- Búsqueda -->
        <div class="col-md-6">
            <form class="d-flex gap-2" method="GET">
                <input type="hidden" name="tipo" value="<?= htmlspecialchars($tipo_filtro) ?>">
                <input type="hidden" name="id_periodo" value="<?= $id_periodo ?>">
                <input type="text" name="buscar" class="form-control"
                    placeholder="Buscar por título..."
                    value="<?= htmlspecialchars($buscar) ?>">
                <button type="submit" class="btn btn-primary">Buscar</button>
            </form>
        </div>

    </div>

    <!-- TABLA SOLICITUDES -->
    <div class="row">
        <div class="col-12">

            <?php if (!empty($solicitudes)): ?>

                <!-- ESCRITORIO -->
                <div class="card shadow-sm d-none d-md-block">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Título</th>
                                        <th>Tipo solicitud</th>
                                        <th>Investigador</th>
                                        <th>Periodo</th>
                                        <th>Fecha solicitud</th>
                                        <th>Estado</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($solicitudes as $sol): ?>
                                        <tr>
                                            <th><?= $sol['id_proyectos'] ?></th>

                                            <td title="<?= htmlspecialchars($sol['titulo']) ?>">
                                                <?= strlen($sol['titulo']) > 50
                                                    ? substr($sol['titulo'], 0, 50) . '...'
                                                    : htmlspecialchars($sol['titulo']) ?>
                                            </td>

                                            <td>
                                                <?php
                                                $tipoLabel = ($sol['tipo_solicitud'] === 'creacion') ? 'Creación' : 'Cierre';
                                                $tipoBadge = ($sol['tipo_solicitud'] === 'creacion') ? 'primary' : 'dark';
                                                ?>
                                                <span class="badge text-bg-<?= $tipoBadge ?>">
                                                    <?= $tipoLabel ?>
                                                </span>
                                            </td>

                                            <td><?= htmlspecialchars($sol['investigador']) ?></td>

                                            <td><?= htmlspecialchars($sol['periodo']) ?></td>

                                            <td><?= $sol['fecha_solicitud'] ?></td>

                                            <td>
                                                <span class="badge text-bg-<?= $proyectoControlador->EstiloEstado($sol['estado_proyecto']) ?>">
                                                    <?= htmlspecialchars($sol['estado_proyecto']) ?>
                                                </span>
                                            </td>

                                            <td>
                                                <?= $proyectoControlador->botonesAccionSolicitud(
                                                    $sol['id_proyectos'],
                                                    $rol,
                                                    $sol['tipo_solicitud'],
                                                    $sol['estado_proyecto']
                                                ) ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- MÓVIL -->
                        <div class="d-block d-md-none mt-3">
                            <?php foreach ($solicitudes as $sol): ?>
                                <div class="card mb-3 shadow-sm">
                                    <div class="card-body">
                                        <h6><?= htmlspecialchars($sol['titulo']) ?></h6>
                                        <?php
                                        $tipoLabel = ($sol['tipo_solicitud'] === 'creacion') ? 'Creación' : 'Cierre';
                                        $tipoBadge = ($sol['tipo_solicitud'] === 'creacion') ? 'primary' : 'dark';
                                        ?>
                                        <p>
                                            <strong>Tipo:</strong>
                                            <span class="badge text-bg-<?= $tipoBadge ?>"><?= $tipoLabel ?></span>
                                        </p>
                                        <p><strong>Investigador:</strong> <?= htmlspecialchars($sol['investigador']) ?></p>
                                        <p><strong>Periodo:</strong> <?= htmlspecialchars($sol['periodo']) ?></p>
                                        <p><strong>Fecha:</strong> <?= $sol['fecha_solicitud'] ?></p>
                                        <p>
                                            <strong>Estado:</strong>
                                            <span class="badge text-bg-<?= $proyectoControlador->EstiloEstado($sol['estado_proyecto']) ?>">
                                                <?= htmlspecialchars($sol['estado_proyecto']) ?>
                                            </span>
                                        </p>
                                        <div class="d-flex gap-2 flex-wrap">
                                            <?= $proyectoControlador->botonesAccionSolicitud(
                                                $sol['id_proyectos'],
                                                $rol,
                                                $sol['tipo_solicitud'],
                                                $sol['estado_proyecto']
                                            ) ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- PAGINACIÓN -->
                        <?php if ($paginacion['total_paginas'] > 1): ?>
                            <nav class="mt-4">
                                <ul class="pagination justify-content-center">

                                    <?php
                                    $inicio = ($paginacion['pagina'] - 1) * $paginacion['por_pagina'] + 1;
                                    $fin    = min($inicio + $paginacion['por_pagina'] - 1, $paginacion['total']);
                                    $base   = '?tipo=' . urlencode($tipo_filtro) . '&id_periodo=' . $id_periodo . (!empty($buscar) ? '&buscar=' . urlencode($buscar) : '');
                                    ?>

                                    <li class="page-item disabled">
                                        <span class="page-link">
                                            Mostrando <?= $inicio ?> a <?= $fin ?> de <?= $paginacion['total'] ?> entradas
                                        </span>
                                    </li>

                                    <li class="page-item <?= ($paginacion['pagina'] <= 1) ? 'disabled' : '' ?>">
                                        <a class="page-link" href="<?= $base ?>&pagina=<?= $paginacion['pagina'] - 1 ?>">&laquo;</a>
                                    </li>

                                    <?php for ($i = 1; $i <= $paginacion['total_paginas']; $i++): ?>
                                        <li class="page-item <?= ($i == $paginacion['pagina']) ? 'active' : '' ?>">
                                            <a class="page-link" href="<?= $base ?>&pagina=<?= $i ?>"><?= $i ?></a>
                                        </li>
                                    <?php endfor; ?>

                                    <li class="page-item <?= ($paginacion['pagina'] >= $paginacion['total_paginas']) ? 'disabled' : '' ?>">
                                        <a class="page-link" href="<?= $base ?>&pagina=<?= $paginacion['pagina'] + 1 ?>">&raquo;</a>
                                    </li>

                                </ul>
                            </nav>
                        <?php endif; ?>

                    <?php else: ?>
                        <div class="alert alert-info text-center">
                            No hay solicitudes para mostrar
                        </div>
                    <?php endif; ?>

                    </div>
                </div>
        </div>
    </div>

</div>

<?php
$contenido = ob_get_clean();
$titulo    = "Solicitudes";
$bodyClass = "solicitudes-page";
include __DIR__ . '/../../layout.php';
?>