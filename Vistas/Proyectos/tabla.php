<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (isset($_SESSION['rol'])) {
    $base_url = "/ITSFCP-PROYECTOS/";

    switch (strtolower($_SESSION['rol'])) {
        case 'alumno':
            header("Location: {$base_url}Vistas/usuarios/alumno.php");
            exit;
        case 'profesor':
        case 'investigador':
            header("Location: {$base_url}Vistas/usuarios/profesor.php");
            exit;
        case 'supervisor':
            header("Location: {$base_url}Vistas/usuarios/supervisor.php");
            exit;
    }
$rol = $_SESSION['rol'];
$id = $_SESSION['id_usuario'];
//ACCIONES
$action = isset($_GET['action']) ? $_GET['action'] : 'index';
//BÚSQUEDA DE DATOS
$buscar = $_GET['buscar'] ?? null;

//LLAMADA AL CONTROLADOR
require_once "../../Controladores/proyectoControlador.php";

$proyectoControlador = new ProyectoControlador();
//Si no existe el controlador que mande un mensaje
if (!method_exists($proyectoControlador, $action)) {
    die("Error: La acción '$action' no existe en el controlador.");
}
//Se ejecuta la acción del controlador
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $_POST['action'] == 'actualizarestadoRechazo' && $rol == "supervisor") {
    $proyectoControlador->actualizarestadoRechazo($_POST, $id, $rol);
}
if ($_SERVER['REQUEST_METHOD'] == 'GET' && $action == 'actualizarestado') {
    $proyectoControlador->actualizarestado($_GET['id_proyectos'], $rol, $_GET['tipo']);
}
//EJECUTAR ACCION
$proyectos = $proyectoControlador->$action($id, $rol, $buscar);
//Descifrar datos
$proyectos = json_decode($proyectos, true);

$filtros = $proyectoControlador->filtros($id, $rol);

$encabezados = $proyectoControlador->encabezados($rol);
$opciones = $proyectoControlador->datosopciones($rol, $filtros);

}
?>

<script>
    //MOSTRAR TOOLTIP, QUE ES UN TEXTO AL SOBREPONER MOUSE EN BOTÓN
    document.addEventListener('DOMContentLoaded', function() {
        const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
        const tooltipList = [...tooltipTriggerList].map(t => new bootstrap.Tooltip(t));
    });
</script>



<?php include '../../publico/incluido/header.php'; ?>
<div class="container-main">
    <?php include '../../sidebar.php'; ?>
    <div class="main-content-index">
        <div class="row mb-1">
            <div class="col-12">
                <h3>Proyectos</h3>
            </div>
            <div class="row mb-1">
                <div class="col-12 mb-3" id="crear_busqueda">

                    <div class="col-12 col-md-6 mb-2">
                        <?php if ($rol == "investigador" || $rol == "profesor"): ?>
                            <a href="crear.php">
                                <button type="button" class="btn btn-primary">Crear proyecto</button></a>
                        <?php endif; ?>
                    </div>

                    <div class="col-12 col-md-6 mb-2">
                        <form class="d-flex flex-wrap gap-2" method="GET" action="tabla.php">
                            <div class="flex-grow-1">
                                <div class="input-group">
                                    <input type="hidden" id="input_hidden" name="action" value="<?= $action ?>">
                                    <input type="text"
                                        name="buscar"
                                        placeholder="Buscar..."
                                        class="form-control"
                                        id="input_busqueda"
                                        value="<?= $_GET['buscar'] ?? '' ?>">
                                    <button type="submit" id="boton_busqueda" class="btn btn-primary">Buscar</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <div class="row mb-1">
                <div class="d-flex flex-wrap gap-2" aria-label="Filtros">
                    <?php foreach ($opciones as $key => $label): ?>
                        <?php
                        // Si este filtro es el elegido → mantenerlo activo
                        $clase = ($action === $key)
                            ? "btn btn-primary"            // marcado
                            : "btn btn-outline-primary";   // normal
                        ?>
                        <a href="tabla.php?action=<?= $key ?>">
                            <button type="button" class="<?= $clase ?>">
                                <?= $label ?>
                            </button>
                        </a>
                    <?php endforeach;
                    $proyectoControlador->filtros($id, $rol)
                    ?>
                </div>
            </div>
            <div class="row mb-1">
                <div class="col-12">
                    <div class="table-responsive">
                        <table class="table table-light" id="tabla_informacion">
                            <thead class="text-center">
                                <tr>
                                    <?php
                                    foreach ($encabezados as $encabezado) {
                                        echo "<th scope='col'>{$encabezado}</th>";
                                    }
                                    ?>
                                </tr>
                            </thead>
                            <tbody class="text-center">
                                <?php foreach ($proyectos["proyectos"] as $proyecto):
                                    echo "<tr>";
                                    echo "<th scope='row'>{$proyecto['id_proyectos']}</th>";
                                    echo "<td>{$proyecto['titulo']}</td>";
                                    echo "<td>{$proyecto['fecha_inicio']}</td>";
                                    echo "<td>{$proyecto['fecha_fin']}</td>";
                                    echo "<td>{$proyecto['nombre']}</td>";
                                    echo "<td>{$proyecto['periodo']}</td>";
                                    echo '<td><button type="button" class="btn btn-info btn-comentarios" data-bs-toggle="tooltip" data-bs-placement="top"
        data-bs-custom-class="custom-tooltip" data-bs-title="Comentarios" data-id="' . $proyecto['id_proyectos'] . '"><span><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-chat-dots-fill" viewBox="0 0 16 16">
  <path d="M16 8c0 3.866-3.582 7-8 7a9 9 0 0 1-2.347-.306c-.584.296-1.925.864-4.181 1.234-.2.032-.352-.176-.273-.362.354-.836.674-1.95.77-2.966C.744 11.37 0 9.76 0 8c0-3.866 3.582-7 8-7s8 3.134 8 7M5 8a1 1 0 1 0-2 0 1 1 0 0 0 2 0m4 0a1 1 0 1 0-2 0 1 1 0 0 0 2 0m3 1a1 1 0 1 0 0-2 1 1 0 0 0 0 2"/>
</svg></span></button></td>';
                                    if (isset($proyecto['total']) && ($rol == 'alumno' || $rol === 'investigador' || $rol === 'profesor')) {
                                        echo "<td>{$proyecto['total']}</td>";
                                    }
                                    echo "<td>{$proyectoControlador->botonesAccion($proyecto['id_proyectos'],$rol,$proyecto['nombre'])}</td>";
                                    echo "</tr>";
                                endforeach;
                                ?>
                            </tbody>
                        </table>
                        <div class="row">
                            <div class="col-12 d-flex justify-content-start">
                                <nav aria-label="Paginación de proyectos">
                                    <ul class="pagination justify-content-center">
                                        <?php
                                        // Cálculo de inicio y fin
                                        $inicio = ($proyectos["paginacion"]['pagina'] - 1) * $proyectos["paginacion"]['por_pagina'] + 1;
                                        $fin = min($inicio + $proyectos["paginacion"]['por_pagina'] - 1, $proyectos["paginacion"]['total_proyectos']);
                                        ?>
                                        <li class="page-item disabled">
                                            <span class="page-link">
                                                Mostrando <?php echo $inicio; ?> a <?php echo $fin; ?> de <?php echo $proyectos["paginacion"]['total_proyectos']; ?> entradas
                                            </span>
                                        </li>
                                        <!-- Botón Primero -->
                                        <li class="page-item <?php echo ($proyectos["paginacion"]['pagina'] == 1) ? 'disabled' : ''; ?>">
                                            <a class="page-link" href="?pagina=1">|&lt;</a>
                                        </li>
                                        <!-- Botón Anterior -->
                                        <li class="page-item <?php echo ($proyectos["paginacion"]['pagina'] == 1) ? 'disabled' : ''; ?>">
                                            <a class="page-link" href="?pagina=<?php echo $proyectos["paginacion"]['pagina'] - 1; ?>">&lt;&lt;</a>
                                        </li>
                                        <?php
                                        // Loop de páginas
                                        for ($i = 1; $i <= $proyectos["paginacion"]['total_paginas']; $i++) {
                                            $active = ($i == $proyectos["paginacion"]['pagina']) ? 'active' : '';
                                            echo '<li class="page-item ' . $active . '"><a class="page-link" href="?pagina=' . $i . '">' . $i . '</a></li>';
                                        }
                                        ?>
                                        <!-- Botón Siguiente -->
                                        <li class="page-item <?php echo ($proyectos["paginacion"]['pagina'] == $proyectos["paginacion"]['total_paginas']) ? 'disabled' : ''; ?>">
                                            <a class="page-link" href="?pagina=<?php echo $proyectos["paginacion"]['pagina'] + 1; ?>">&gt;&gt;</a>
                                        </li>
                                        <!-- Botón Último -->
                                        <li class="page-item <?php echo ($proyectos["paginacion"]['pagina'] == $proyectos["paginacion"]['total_paginas']) ? 'disabled' : ''; ?>">
                                            <a class="page-link" href="?pagina=<?php echo $proyectos["paginacion"]['total_paginas']; ?>">&gt;|</a>
                                        </li>
                                    </ul>
                                </nav>
                            </div>
                        </div>
                    </div>
                    <?php foreach ($proyectos as $proyecto): ?>
                        <div class="card mb-3" id="tarjeta_móvil" style="width: 18rem;">
                            <div class="card-body">
                                <h5 class="card-title"><?php echo $proyecto['id_proyectos'] ?></h5>
                                <p class="card-text"><?php echo $proyecto['titulo'] ?></p>
                            </div>
                            <ul class="list-group list-group-flush">
                                <li class="list-group-item">
                                    <div class="row">
                                        <div class="col-6">
                                            <label>Fecha Inicio</label>
                                            <p class="card-text"><?php echo $proyecto['fecha_inicio'] ?></p>
                                        </div>
                                        <div class="col-6">
                                            <label>Fecha Fin</label>
                                            <p class="card-text"><?php echo $proyecto['fecha_fin'] ?></p>
                                        </div>
                                    </div>
                                </li>
                                <li class="list-group-item">

                                    <div class="row">
                                        <div class="col-6">
                                            <label>Periodo</label>
                                            <p class="card-text"><?php echo $proyecto['periodo'] ?></p>
                                        </div>
                                        <?php if (isset($proyecto['total']) && ($rol === 'alumno' || $rol === 'investigador' || $rol === 'profesor')): ?>
                                            <div class="col-6">
                                                <label>Avances</label>
                                                <p class="card-text"><?php echo $proyecto['total'] ?></p>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </li>
                            </ul>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-5">
                                        <p class="card-text"><?php echo $proyecto['nombre'] ?></p>
                                    </div>
                                    <div class="col-7">
                                        <?php $proyectoControlador->botonesAccion($proyecto['id_proyectos'], $rol, $proyecto['estado']); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>

                </div>
            </div>
        </div>
    </div>
</div>
<?php if ($rol == "supervisor"): ?>
    <!-- MODAL FORMULARIO RECHAZO CREACIÓN -->
    <div class="modal fade" id="modalRechazoCreacion" tabindex="-1">
        <div class="modal-dialog">
            <form method="POST" id="formRechazoCreacion" action="/ITSFCP-PROYECTOS/Vistas/Proyectos/tabla.php">
                <div class="modal-content">

                    <div class="modal-header">
                        <h5 class="modal-title">Motivo de rechazo de creación</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>

                    <div class="modal-body">
                        <label>Motivo del rechazo:</label>
                        <textarea class="form-control" name="comentario" required></textarea>

                        <input type="hidden" name="tipo" value="creacion_rechazada">
                        <input type="hidden" name="action" value="actualizarestadoRechazo">
                        <!-- ID dinámico -->
                        <input type="hidden" id="idProyectoRechazoCreacion" name="id_proyectos">
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-danger">Confirmar rechazo</button>
                    </div>

                </div>
            </form>
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
<?php endif; ?>
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


<!-- COMENTARIOS -->
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

<?php include "../../publico/incluido/footer.php"; ?>
<script>


</script>
<!--- MODAL MENSAJE -->
<?php if (isset($_GET['msg']) && $_GET['msg'] == 'mensaje'): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const myModal = new bootstrap.Modal(document.getElementById('mensaje'));
            myModal.show();
        });
    </script>
<?php unset($_SESSION['mensaje']);
endif; ?>
