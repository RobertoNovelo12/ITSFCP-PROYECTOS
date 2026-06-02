<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

if (!isset($_SESSION['id_usuario'])) {
    header("Location: /index.php");
    exit;
}

$rol        = strtolower($_SESSION['rol'] ?? '');
$id_usuario = intval($_SESSION['id_usuario']);

if ($rol !== 'supervisor') {
    header("Location: /Vistas/Principal/index.php");
    exit;
}

require_once __DIR__ . '/../../Controladores/usuarioControlador.php';

$controlador = new UsuariosControlador();

//  Procesar POST (confirmar rechazo) 
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $controlador->rechazar($_POST, $rol);
    exit; // rechazar() redirige
}

//  Validaciones GET ─
include __DIR__ . '../../../publico/incluido/_validar_get.php';

$id_ver = isset($_GET['id_usuarios']) ? intval($_GET['id_usuarios']) : 0;

$id_validar = $id_ver;
include __DIR__ . '../../../publico/incluido/_validar_id.php';

//  Cargar datos ─
$usuario = $controlador->indexDetalles($rol, $id_ver);

$registro = $usuario;
include __DIR__ . '../../../publico/incluido/_validar_datos.php';

// Solo se puede rechazar si el usuario está en espera
if ($usuario['estado_usuario'] !== 'espera') {
    header("Location: detalles.php?id_usuarios=" . $id_ver);
    exit;
}

//  Iconos reutilizables ─
include __DIR__ . '/../../publico/incluido/_iconos.php';

ob_start();
?>

<div class="container-fluid py-4 ancho_container">

    <!-- ENCABEZADO -->
    <div class="row mb-4 align-items-center">
        <?php
        $titulo      = 'Respuesta a Solicitud de Usuario';
        $descripcion = 'Rechazar el acceso al sistema';
        include __DIR__ . '../../../publico/incluido/_encabezado.php';
        ?>
        <div class="col-6 col-md-6 text-md-end">
            <?php if ($rol === 'supervisor'): ?>
                <a href="detalles.php?id_usuarios=<?= $id_ver ?>" class="btn btn-secondary">
                    <i class="<?= $iconos['tabla']['regresar'] ?>"></i> Regresar
                </a>
            <?php endif; ?>
        </div>
    </div>

    <div class="card border-0">
        <div class="card-body">

            <!-- INFORMACIÓN RESUMIDA DEL USUARIO -->
            <div class="mb-4">
                <h5 class="text-danger">
                    <i class="<?= $iconos['detalles']['informacion'] ?> me-2"></i>Información del usuario
                </h5>
                <div class="row">
                    <div class="col-md-6">
                        <p class="mb-1">
                            <strong>Nombre completo:</strong><br>
                            <?= htmlspecialchars(
                                $usuario['nombre'] . ' ' .
                                $usuario['apellido_paterno'] . ' ' .
                                $usuario['apellido_materno']
                            ) ?>
                        </p>
                    </div>
                    <div class="col-md-6">
                        <p class="mb-1">
                            <strong>Tipo de usuario:</strong><br>
                            <span class="badge rounded-pill text-bg-secondary">
                                <?= htmlspecialchars(ucfirst($usuario['tipo_usuario'])) ?>
                            </span>
                        </p>
                    </div>
                </div>
                <div class="row mt-2">
                    <div class="col-md-6">
                        <p class="mb-1">
                            <strong>Correo:</strong><br>
                            <?= htmlspecialchars($usuario['correo_institucional']) ?>
                        </p>
                    </div>
                    <div class="col-md-6">
                        <p class="mb-1">
                            <strong>Fecha de registro:</strong><br>
                            <?= date("d/m/Y H:i", strtotime($usuario['fecha_registro'])) ?>
                        </p>
                    </div>
                </div>
            </div>

            <hr>

            <!-- ESTADO RESULTANTE (informativo) -->
            <div class="mb-3">
                <label class="form-label fw-bold">Estado resultante</label>
                <div>
                    <span class="badge rounded-pill text-bg-danger fs-6 px-3 py-2">
                        Rechazado / Cancelado
                    </span>
                </div>
                <small class="text-muted">
                    El estado del usuario cambiará a <strong>Cancelado</strong> al confirmar.
                </small>
            </div>

            <!-- FORMULARIO DE RECHAZO -->
            <form method="POST" action="respuesta.php">

                <input type="hidden" name="id_usuario" value="<?= $id_ver ?>">

                <div class="mb-3">
                    <label for="comentario" class="form-label fw-bold">
                        Comentario de rechazo <span class="text-danger">*</span>
                    </label>
                    <textarea
                        id="comentario"
                        name="comentario"
                        class="form-control"
                        rows="5"
                        required
                        placeholder="Ejemplo: No cumple con los requisitos de ser un estudiante activo y cursando el ciclo escolar actual."></textarea>
                    <small class="text-muted">
                        Este comentario se enviará al usuario por correo electrónico.
                    </small>
                </div>

                <div class="d-flex justify-content-between flex-wrap gap-2">
                    <a href="detalles.php?id_usuarios=<?= $id_ver ?>" class="btn btn-secondary">
                        <i class="<?= $iconos['tabla']['regresar'] ?> me-1"></i> Cancelar
                    </a>
                    <button type="submit" class="btn btn-danger"
                        onclick="return confirm('¿Confirmar el rechazo de este usuario? Se enviará una notificación por correo.')">
                        <i class="<?= $iconos['tabla']['solicitar_cierre'] ?> me-1"></i> Confirmar rechazo
                    </button>
                </div>

            </form>

        </div>
    </div>

</div>

<?php
$contenido = ob_get_clean();
$titulo    = "Rechazar solicitud de usuario";
$bodyClass = "usuarios-page";

include __DIR__ . '/../../layout.php';
?>
