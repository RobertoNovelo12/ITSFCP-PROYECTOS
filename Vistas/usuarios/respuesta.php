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

if ($rol !== 'supervisor') {
    header("Location: /ITSFCP-PROYECTOS/index.php");
    exit;
}

require_once '../../Controladores/usuarioControlador.php';

$controlador = new UsuariosControlador();

// ── Procesar POST (confirmar rechazo) ────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $controlador->rechazar($_POST, $rol);
    exit; // rechazar() redirige
}

// ── GET: mostrar formulario ──────────────────────────────────────
$id_ver = isset($_GET['id_usuarios']) ? intval($_GET['id_usuarios']) : 0;

if ($id_ver <= 0) {
    die("ID de usuario no válido.");
}

$usuario = $controlador->indexDetalles($rol, $id_ver);

if (empty($usuario)) {
    die("No se encontró el usuario.");
}

// Solo se puede rechazar si está en espera
if ($usuario['estado_usuario'] !== 'espera') {
    header("Location: detalles.php?id_usuarios=" . $id_ver);
    exit;
}

ob_start();
?>

<div class="container-fluid py-4">

    <!-- ENCABEZADO -->
    <div class="row mb-4 align-items-center">
        <div class="col-md-6">
            <h3 class="fw-bold mb-0">Rechazar solicitud de usuario</h3>
        </div>
        <div class="col-md-6 text-md-end">
            <a href="detalles.php?id_usuarios=<?= $id_ver ?>" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Regresar
            </a>
        </div>
    </div>

    <div class="card shadow-lg border-0">
        <div class="card-body">

            <!-- INFORMACIÓN RESUMIDA DEL USUARIO -->
            <div class="mb-4">
                <h5 class="text-danger">
                    <i class="bi bi-person-x-fill me-2"></i>Información del usuario
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

            <!-- ESTADO (no editable) -->
            <div class="mb-3">
                <label class="form-label fw-bold">Estado resultante</label>
                <div>
                    <span class="badge rounded-pill text-bg-danger fs-6 px-3 py-2">
                        <i class="bi bi-x-circle-fill me-1"></i> Rechazado / Cancelado
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
                        placeholder="Ejemplo: No cumple con los requisitos de ser un estudiante activo y cursando el ciclo escolar actual."
                    ></textarea>
                    <small class="text-muted">
                        Este comentario se enviará al usuario por correo electrónico.
                    </small>
                </div>

                <div class="d-flex justify-content-between flex-wrap gap-2">
                    <a href="detalles.php?id_usuarios=<?= $id_ver ?>" class="btn btn-secondary">
                        <i class="bi bi-arrow-left me-1"></i> Cancelar
                    </a>
                    <button type="submit" class="btn btn-danger"
                            onclick="return confirm('¿Confirmar el rechazo de este usuario? Se enviará una notificación por correo.')">
                        <i class="bi bi-x-circle-fill me-1"></i> Confirmar rechazo
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