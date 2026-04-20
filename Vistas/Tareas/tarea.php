<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();

if (!isset($_SESSION['id_usuario'])) {
    header("Location: /ITSFCP-PROYECTOS/index.php");
    exit;
}

$rol = $_SESSION['rol'];
$id = $_SESSION['id_usuario'];

$id_proyecto = $_GET["id_proyectos"] ?? null;
$id_asignacion = $_GET["id_asignacion"] ?? null;
$id_tarea = $_GET["id_tarea"] ?? null;

if ($id_asignacion == null) {
    die("ERROR: No se recibió id_asignacion");
}

require_once '../../Controladores/tareasControlador.php';
$tareaControlador = new TareaControlador();

// DATOS
$datos = $tareaControlador->mostrarTarea($id_asignacion, $rol);
$resultado = $tareaControlador->info_linea_tiempo($id_asignacion);

$historialAgrupado = $resultado['datos'];
$paginacion = $resultado['paginacion'];

ob_start();
?>

<div class="container-fluid py-4">

    <div class="row mb-3">

        <div class="col-6">
            <h3>Revisar Tarea</h3>
        </div>

        <div class="col-6 text-end">
            <a href="lista_tareas.php?id_tarea=<?= $id_tarea ?>&id_proyectos=<?= $id_proyecto ?>" class="btn btn-danger">
                Regresar
            </a>
        </div>

    </div>

    <div class="mb-3">
        <h5>Descripción</h5>
        <p><?= $datos['descripcion'] ?? "" ?></p>
    </div>

    <div class="mb-3">
        <h5>Instrucciones</h5>
        <p><?= $datos['instrucciones'] ?? "" ?></p>
    </div>



    <details open>
        <summary><strong>Línea de tiempo</strong></summary>

        <div class="card p-3 mb-3">
            <strong>Resumen</strong><br>
            Última actualización: <?= date("d/m/Y H:i") ?><br>
            ID Asignación: <?= $id_asignacion ?>
        </div>

        <ul class="timeline mt-3">

            <?php if (empty($historialAgrupado)): ?>

                <div class="alert alert-info text-center">
                    Este estudiante no tiene historial en esta tarea.
                </div>

            <?php else: ?>

                <?php foreach ($historialAgrupado as $fecha => $items): ?>

                    <li class="mb-4">
                        <div class="fw-bold text-primary"><?= $fecha ?></div>

                        <?php foreach ($items as $item): ?>

                    <li>
                        <div class="timeline-content">

                            <span class="badge bg-<?= ($item['estado'] == 'Aprobado') ? 'success' : (($item['estado'] == 'Corregir') ? 'warning' : 'primary') ?>">
                                <?= $item['estado'] ?>
                            </span>

                            <small class="text-muted">
                                <?= date("H:i", strtotime($item['fecha'])) ?>
                            </small>

                            <p><?= $item['comentario'] ?></p>

                            <small>
                                <?= ($item['esEstudiante'] ? 'Estudiante' : 'Investigador') ?>
                                - <?= $item['usuario'] ?? 'Sistema' ?>
                            </small>

                        </div>
                    </li>

                <?php endforeach; ?>

                </li>

            <?php endforeach; ?>

        <?php endif; ?>

        </ul>
    </details>

    <!-- PAGINACIÓN -->
    <?php if ($paginacion['total_paginas'] > 1): ?>

        <nav class="mt-4">
            <ul class="pagination justify-content-center">

                <?php
                $inicio = ($paginacion['pagina'] - 1) * $paginacion['por_pagina'] + 1;
                $fin = min($inicio + $paginacion['por_pagina'] - 1, $paginacion['total']);
                ?>

                <li class="page-item disabled">
                    <span class="page-link">
                        Mostrando <?= $inicio ?> a <?= $fin ?> de <?= $paginacion['total'] ?>
                    </span>
                </li>

                <?php for ($i = 1; $i <= $paginacion['total_paginas']; $i++): ?>

                    <li class="page-item <?= ($i == $paginacion['pagina']) ? 'active' : '' ?>">
                        <a class="page-link"
                            href="?id_asignacion=<?= $id_asignacion ?>&pagina=<?= $i ?>">
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
$titulo = "Historial de tarea";
include __DIR__ . '/../../layout.php';
?>