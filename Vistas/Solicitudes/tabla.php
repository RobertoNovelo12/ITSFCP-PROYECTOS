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
$pagina = intval($_GET['pagina'] ?? 1);

require_once "../../Controladores/solicitudesControlador.php";
$solicitudesoControlador = new solicitudesControlador();

// VALIDACIÓN DE MÉTODO EXISTENTE EN EL CONTROLADOR
if (!method_exists($solicitudesoControlador, $action)) {
    die("Error: La acción '$action' no existe en el controlador.");
}

// ---- ACTUALIZACIONES ----
if (
    $_SERVER['REQUEST_METHOD'] == 'POST'
    && ($_POST['action'] ?? '') == 'actualizarestadoRechazo'
    && $rol == "supervisor"
) {
    $solicitudesoControlador->actualizarestadoRechazo($_POST, $id_usuario, $rol);
}

if ($_SERVER['REQUEST_METHOD'] == 'GET' && $action == 'actualizarestado') {
    if (isset($_GET['id_solicitud_proyecto'], $_GET['tipo'])) {
        $solicitudesoControlador->actualizarestado($_GET['id_solicitud_proyecto'], $rol, $_GET['tipo']);
    }
}

// OBTENER SOLICITUDES
$resultado = $solicitudesoControlador->$action($id_usuario, $rol);

if (is_string($resultado)) {
    $resultado = json_decode($resultado, true);
}

if (!is_array($resultado)) {
    die("Error: La acción '$action' no devolvió un array válido.");
}

$solicitudes = $resultado['solicitudes'] ?? [];
$paginacion = $resultado['paginacion'] ?? [
    'total_proyectos' => count($solicitudes),
    'por_pagina' => 6,
    'pagina' => $pagina,
    'total_paginas' => max(1, ceil(count($solicitudes) / 6))
];

$encabezados = $solicitudesoControlador->encabezados();

ob_start();
?>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
        [...tooltipTriggerList].map(t => new bootstrap.Tooltip(t));
    });

    function abrirRechazoSolicitud(id) {
        document.getElementById('id_solicitud_proyectos').value = id;
        const modal = new bootstrap.Modal(document.getElementById('modalRechazoSolicitud'));
        modal.show();
    }
</script>

<div class="container-fluid py-4">

    <div class="row mb-3 align-items-center">
        <div class="col-md-12">
            <h2 class="mb-0">Solicitudes a proyectos</h2>
        </div>
    </div>

    <div class="row">
        <div class="col-12">

            <?php if (!empty($solicitudes)): ?>

                <!-- TABLA DESKTOP -->
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

                            <?php foreach ($solicitudes as $soli): ?>
                                <tr>
                                    <th scope="row"><?= $soli['id_solicitud_proyectos'] ?? '-' ?></th>
                                    <td><?= htmlspecialchars($soli['Estudiante'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= $soli['Carrera'] ?? '-' ?></td>
                                    <td><?= $soli['Matricula'] ?? '-' ?></td>
                                    <td><?= $soli['Proyecto'] ?? '-' ?></td>
                                    <td><?= $soli['Fecha_solicitud'] ?? '-' ?></td>

                                    <td>
                                        <button type="button" class="btn btn-info btn-sm btn-comentarios"
                                            data-id="<?= $soli['id_solicitud_proyectos'] ?? 0 ?>">
                                            <i class="bi bi-chat-dots-fill"></i>
                                        </button>
                                    </td>

                                    <td><?= $soli['Estado'] ?? '-' ?></td>

                                    <td>
                                        <?= $solicitudesoControlador->botonesAccion(
                                            $soli['id_solicitud_proyectos'] ?? 0,
                                            $rol,
                                            $soli['id_proyectos'] ?? 0,
                                            $soli['Estado'] ?? null
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

                <!-- TARJETAS MÓVILES -->
                <div class="d-block d-md-none mt-4">
                    <?php foreach ($solicitudes as $soli): ?>
                        <div class="card mb-3 shadow-sm">
                            <div class="card-body">
                                <h5 class="card-title">ID: <?= $soli['id_solicitud_proyectos'] ?? '-' ?></h5>
                                <p><strong><?= htmlspecialchars($soli['Estudiante'] ?? '-', ENT_QUOTES, 'UTF-8') ?></strong></p>
                                <p><strong>Carrera:</strong>
                                    <?= htmlspecialchars($soli['Carrera'] ?? '-', ENT_QUOTES, 'UTF-8') ?></p>
                                <p><strong>Matricula:</strong> <?= $soli['Matricula'] ?? '-' ?></p>
                                <p><strong>Proyecto:</strong> <?= $soli['Proyecto'] ?? '-' ?></p>
                                <p><strong>Estado:</strong> <?= $soli['Estado'] ?? '-' ?></p>
                                <p><strong>Fecha solicitud:</strong> <?= $soli['Fecha_solicitud'] ?? '-' ?></p>

                                <div class="d-flex flex-wrap gap-2 mt-2">

                                    <button type="button" class="btn btn-info btn-sm btn-comentarios"
                                        data-id="<?= $soli['id_solicitud_proyectos'] ?? 0 ?>">
                                        <i class="bi bi-chat-dots-fill"></i>
                                    </button>

                                    <?= $solicitudesoControlador->botonesAccion(
                                        $soli['id_solicitud_proyectos'] ?? 0,
                                        $rol,
                                        $soli['id_proyectos'] ?? 0,
                                        $soli['Estado'] ?? null
                                    ); ?>
                                </div>

                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

            <?php else: ?>

                <div class="alert alert-info text-center">
                    No hay solicitudes para mostrar
                    <?= !empty($buscar) ? ' con el criterio "' . htmlspecialchars($buscar, ENT_QUOTES, 'UTF-8') . '"' : '' ?>.
                </div>

            <?php endif; ?>

        </div>
    </div>
</div>

<!-- MODAL RECHAZO -->
<div class="modal fade" id="modalRechazoSolicitud" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" id="formRechazo" action="/ITSFCP-PROYECTOS/Vistas/Solicitudes/tabla.php">
            <div class="modal-content">

                <div class="modal-header">
                    <h5 class="modal-title">Motivo de rechazo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <label>Motivo del rechazo:</label>
                    <textarea class="form-control" name="comentario" required></textarea>

                    <input type="hidden" name="tipo" value="Rechazado">
                    <input type="hidden" name="action" value="actualizarestadoRechazo">
                    <input type="hidden" id="id_solicitud_proyectos" name="id_solicitud_proyecto">
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger">Confirmar rechazo</button>
                </div>

            </div>
        </form>
    </div>
</div>

<!-- MODAL MENSAJE -->
<div class="modal fade" id="mensaje" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h1 class="modal-title fs-5">Operación realizada correctamente</h1>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <img src="/ITSFCP-PROYECTOS/publico/icons/comprobar.svg" alt="">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- MODAL COMENTARIOS -->
<div class="modal fade" id="modalComentarios" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title">Comentarios</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <div class="accordion" id="comentariosAccordion"></div>
                <input type="hidden" id="idProyectoComentarios">
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<?php
$contenido = ob_get_clean();
$titulo = "Solicitudes de integracion";
$bodyClass = "proyectos-page";

include __DIR__ . '/../../layout.php';
?>

<?php if (isset($_GET['msg']) && $_GET['msg'] == 'mensaje'): ?>
    <script>abrirMensaje();</script>
    <?php unset($_SESSION['mensaje']); 
endif; ?>