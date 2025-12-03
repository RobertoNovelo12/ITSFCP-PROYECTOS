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

require_once "../../Controladores/proyectoControlador.php";
$proyectoControlador = new ProyectoControlador();

if (!method_exists($proyectoControlador, $action)) {
    die("Error: La acción '$action' no existe en el controlador.");
}

// Actualización de estados
if ($_SERVER['REQUEST_METHOD'] == 'POST' && ($_POST['action'] ?? '') == 'actualizarestadoRechazo' && $rol == "supervisor") {
    $proyectoControlador->actualizarestadoRechazo($_POST, $id_usuario, $rol);
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

$proyectos = $resultado['proyectos'] ?? [];
$paginacion = $resultado['paginacion'] ?? [
    'total_proyectos' => count($proyectos),
    'por_pagina' => 6,
    'pagina' => $pagina,
    'total_paginas' => max(1, ceil(count($proyectos) / 6))
];

$filtros = $proyectoControlador->filtros($id_usuario, $rol);
$encabezados = $proyectoControlador->encabezados($rol);
$opciones = $proyectoControlador->datosopciones($rol, $filtros);

// ======================
// GENERAR CONTENIDO
// ======================
ob_start();
?>

<script>
    //MOSTRAR TOOLTIP, QUE ES UN TEXTO AL SOBREPONER MOUSE EN BOTÓN
    document.addEventListener('DOMContentLoaded', function() {
        const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
        const tooltipList = [...tooltipTriggerList].map(t => new bootstrap.Tooltip(t));
    });
</script>
<div class="container-fluid py-4">

    <div class="row mb-3 align-items-center">
        <div class="col-md-6">
            <h2 class="mb-0">Proyectos</h2>
        </div>
        <div class="col-md-6 text-md-end">
            <?php if ($rol == "investigador" || $rol == "profesor"): ?>
                <a href="crear.php" class="btn btn-primary">
                    <i class="bi bi-plus-lg"></i> Crear proyecto
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- BOTONES DE FILTRO -->
    <div class="row mb-3">
        <div class="d-flex flex-wrap gap-2">
            <?php foreach ($opciones as $key => $label):
                $clase = ($action === $key) ? "btn btn-primary" : "btn btn-outline-primary"; ?>
                <a href="tabla.php?action=<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>" class="<?= $clase ?>">
                    <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- BÚSQUEDA -->
    <div class="row mb-3">
        <div class="col-12 text-end">
            <div class="row justify-content-end">
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


    <!-- TABLA DE PROYECTOS - Desktop -->
    <div class="row">
        <div class="col-12">
            <?php if (!empty($proyectos)): ?>
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
                            <?php foreach ($proyectos as $proyecto): ?>
                                <tr>
                                    <th scope="row"><?= $proyecto['id_proyectos'] ?? '-' ?></th>
                                    <td><?= htmlspecialchars($proyecto['titulo'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= $proyecto['fecha_inicio'] ?? '-' ?></td>
                                    <td><?= $proyecto['fecha_fin'] ?? '-' ?></td>
                                    <td><?= htmlspecialchars($proyecto['nombre'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= $proyecto['periodo'] ?? '-' ?></td>

                                    <!-- Comentarios -->
                                    <td>
                                        <button type="button"
                                            class="btn btn-info btn-sm btn-comentarios"
                                            data-id="<?= $proyecto['id_proyectos'] ?? 0 ?>">
                                            <i class="bi bi-chat-dots-fill"></i>
                                        </button>
                                    </td>

                                    <!-- Avances -->
                                    <?php if ($rol == 'alumno' || $rol == 'investigador' || $rol == 'profesor'): ?>
                                        <td><?= $proyecto['total'] ?? '0' ?></td>
                                    <?php endif; ?>

                                    <!-- Acciones -->
                                    <td>
                                        <?= $proyectoControlador->botonesAccion(
                                            $proyecto['id_proyectos'] ?? 0,
                                            $rol,
                                            $proyecto['nombre'] ?? '-'
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
                                <h5 class="card-title">ID: <?= $proyecto['id_proyectos'] ?? '-' ?></h5>
                                <p class="card-text"><strong><?= htmlspecialchars($proyecto['titulo'] ?? '-', ENT_QUOTES, 'UTF-8') ?></strong></p>
                                <p><strong>Responsable:</strong> <?= htmlspecialchars($proyecto['nombre'] ?? '-', ENT_QUOTES, 'UTF-8') ?></p>
                                <p><strong>Periodo:</strong> <?= $proyecto['periodo'] ?? '-' ?></p>
                                <p><strong>Inicio:</strong> <?= $proyecto['fecha_inicio'] ?? '-' ?> | <strong>Fin:</strong> <?= $proyecto['fecha_fin'] ?? '-' ?></p>

                                <div class="d-flex flex-wrap gap-2 mt-2">
                                    <!-- Botón comentarios -->
                                    <button type="button"
                                        class="btn btn-info btn-sm btn-comentarios"
                                        data-id="<?= $proyecto['id_proyectos'] ?? 0 ?>">
                                        <i class="bi bi-chat-dots-fill"></i> Comentarios
                                    </button>

                                    <?= $proyectoControlador->botonesAccion(
                                        $proyecto['id_proyectos'] ?? 0,
                                        $rol,
                                        $proyecto['nombre'] ?? '-'
                                    ); ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

            <?php else: ?>
                <div class="alert alert-info text-center">
                    No hay proyectos para mostrar<?= !empty($buscar) ? ' con el criterio "' . htmlspecialchars($buscar, ENT_QUOTES, 'UTF-8') . '"' : '' ?>.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- MODAL FORMULARIO RECHAZO CIERRE -->
<div class="modal fade" id="modalRechazoCierre" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" id="formRechazoCierre" action="/ITSFCP-PROYECTOS/Vistas/Proyectos/tabla.php">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Motivo de rechazo de cierre</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <label>Motivo del rechazo:</label>
                    <textarea class="form-control" name="comentario" required></textarea>

                    <input type="hidden" name="tipo" value="cierre_rechazado">
                    <input type="hidden" name="action" value="actualizarestadoRechazo">
                    <!-- Aquí va el id dinámico -->
                    <input type="hidden" id="idProyectoRechazoCierre" name="id_proyectos">
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger">Confirmar rechazo</button>
                </div>

            </div>
        </form>
    </div>
</div>

<!-- MODAL FORMULARIO RECHAZO CREACION -->
<div class="modal fade" id="modalRechazoSolicitud" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" id="formRechazoCierre" action="/ITSFCP-PROYECTOS/Vistas/Proyectos/tabla.php">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Motivo de rechazo de proyecto</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <label>Motivo del rechazo:</label>
                    <textarea class="form-control" name="comentario" required></textarea>

                    <input type="hidden" name="tipo" value="creacion_rechazada">
                    <input type="hidden" name="action" value="actualizarestadoRechazo">
                    <!-- Aquí va el id dinámico -->
                    <input type="hidden" id="id_solicitud_proyectos" name="id_proyectos">
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger">Confirmar rechazo</button>
                </div>

            </div>
        </form>
    </div>
</div>
<!-- Modal Mensaje Rechazo  -->
<div class="modal fade" id="mensaje" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="staticBackdropLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h1 class="modal-title fs-5" id="staticBackdropLabel">Operación realizada correctamente</h1>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
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
                <div class="accordion" id="comentariosAccordion">
                    <!-- Aquí se insertarán los comentarios via JS -->
                </div>

                <!-- Aquí se guarda el ID del proyecto -->
                <input type="hidden" id="idProyectoComentarios" name="id_proyecto">
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Cancelar</button>
            </div>
        </div>
    </div>
</div>

<?php
$contenido = ob_get_clean();
$titulo = "Proyectos";
$bodyClass = "proyectos-page";

include __DIR__ . '/../../layout.php';
?>
<?php if (isset($_GET['msg']) && $_GET['msg'] == 'mensaje'): ?>
    <script>
        abrirMensaje();
    </script>
<?php unset($_SESSION['mensaje']);
endif; ?>
