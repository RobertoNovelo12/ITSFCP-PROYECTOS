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

$action = $_GET['action'] ?? 'index';
$buscar = $_GET['buscar'] ?? '';
$pagina = intval($_GET['pagina'] ?? 1);

require_once "../../Controladores/proyectoControlador.php";
$proyectoControlador = new ProyectoControlador();

if (!method_exists($proyectoControlador, $action)) {
    die("Error: La acción '$action' no existe en el controlador.");
}

if ($_SERVER['REQUEST_METHOD'] == 'GET' && $action == 'actualizarestado') {
    if (isset($_GET['id_proyectos'], $_GET['tipo'])) {
        $proyectoControlador->actualizarestado($_GET['id_proyectos'], $rol, $_GET['tipo']);
    }
}

// Obtener proyectos
$resultado = $proyectoControlador->$action($id_usuario, $rol, $buscar);

// Si viene como JSON, decodificar
if (is_string($resultado)) {
    $resultado = json_decode($resultado, true);
}

if (!is_array($resultado)) {
    die("Error: La acción '$action' no devolvió un array válido.");
}

$encabezados = $proyectoControlador->encabezados($rol);
$opciones = $proyectoControlador->datosopciones($rol, $filtros);

// GENERAR CONTENIDO
ob_start();
include __DIR__ . '/../../mensaje.php';
?>

<div class="container-fluid py-4">

    <div class="row mb-3 align-items-center">
        <div class="col-md-6">
            <h2 class="mb-0 fw-bold">Ajustes de documentos</h2>
        </div>
    </div>

        <!-- TABLA DE DOCUMENTOS -->
    <div class="row">
        <div class="col-12">
            <?php if (!empty($categorias)): ?>
                <div class="table-responsive d-none d-md-block">
                    <table class="table table-hover text-center align-middle">
                        <thead>
                            <tr>
                                <?php foreach ($encabezados as $encabezado): ?>
                                    <th><?= htmlspecialchars($encabezado ?? '', ENT_QUOTES, 'UTF-8') ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($categorias as $cate): ?>
                                <tr>
                                    <th scope="row"><?= $proyecto['id_proyectos'] ?? '-' ?></th>
                                    <td><?= htmlspecialchars($proyecto['titulo'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= $proyecto['fecha_inicio'] ?? '-' ?></td>
                                    <td><?= $proyecto['fecha_fin'] ?? '-' ?></td>
                                    <td><span class="badge text-bg-<?php echo $proyectoControlador->EstiloEstado($proyecto['estado']); ?>"><?= htmlspecialchars($proyecto['estado'] ?? '-', ENT_QUOTES, 'UTF-8') ?></span></td>
                                    <td><?= $proyecto['periodo'] ?? '-' ?></td>
                                    <!-- Avances -->
                                    <td><?= $proyecto['total'] ?? '0' ?></td>

                                    <!-- Acciones -->
                                    <td>
                                        <?= $proyectoControlador->botonesAccion(
                                            $proyecto['id_proyectos'] ?? 0,
                                            $rol,
                                            $proyecto['estado'] ?? '-',
                                            $id_usuario
                                        ); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
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

                <!-- TARJETAS MÓVILES -->
                <div class="d-block d-md-none mt-4">
                    <?php foreach ($proyectos as $proyecto): ?>
                        <div class="card mb-3 shadow-sm">
                            <div class="card-body">
                                <h5 class="card-title"><strong>ID: </strong><?= $proyecto['id_proyectos'] ?? '-' ?></h5>
                                <p class="card-text"><strong>Título: </strong><?= htmlspecialchars($proyecto['titulo'] ?? '-', ENT_QUOTES, 'UTF-8') ?></p>
                                <p><strong>Estado:</strong> <span class="badge text-bg-<?php echo $proyectoControlador->EstiloEstado($proyecto['estado']); ?>"><?= htmlspecialchars($proyecto['estado'] ?? '-', ENT_QUOTES, 'UTF-8') ?></span></p>
                                <p><strong>Periodo:</strong> <?= $proyecto['periodo'] ?? '-' ?></p>
                                <p><strong>Pendientes:</strong> <?= $proyecto['total'] ?? '-' ?></p>
                                <p><strong>Inicio:</strong> <?= $proyecto['fecha_inicio'] ?? '-' ?> | <strong>Fin:</strong> <?= $proyecto['fecha_fin'] ?? '-' ?></p>

                                <div class="d-flex flex-wrap gap-2 mt-2">

                                    <?= $proyectoControlador->botonesAccion(
                                        $proyecto['id_proyectos'] ?? 0,
                                        $rol,
                                        $proyecto['estado'] ?? '-'
                                    ); ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
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
            <?php else: ?>
                <div class="alert alert-info text-center">
                    No hay proyectos para mostrar<?= !empty($buscar) ? ' con el criterio "' . htmlspecialchars($buscar, ENT_QUOTES, 'UTF-8') . '"' : '' ?>.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
$contenido = ob_get_clean();
$titulo = "Proyectos";
$bodyClass = "proyectos-page";

include __DIR__ . '/../../layout.php';
?>