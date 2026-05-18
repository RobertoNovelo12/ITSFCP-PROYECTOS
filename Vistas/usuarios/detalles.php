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

$id_ver = isset($_GET['id_usuarios']) ? intval($_GET['id_usuarios']) : 0;

if ($id_ver <= 0) {
    die("ID de usuario no válido.");
}

$usuario = $controlador->indexDetalles($rol, $id_ver);

if (empty($usuario)) {
    die("No se encontró el usuario.");
}

ob_start();
?>

<div class="container-fluid py-4">

    <!-- ENCABEZADO -->
    <div class="row mb-4 align-items-center">
        <div class="col-md-6">
            <h3 class="fw-bold mb-0">Detalles del usuario</h3>
        </div>
        <div class="col-md-6 text-md-end">
            <a href="index.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Regresar
            </a>
        </div>
    </div>

    <!-- INFORMACIÓN GENERAL -->
    <div class="card shadow-sm mb-4">
        <div class="card-header">
            <h5 class="mb-0"></i>Información general</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <dl>
                        <dt>Nombre completo</dt>
                        <dd><?= htmlspecialchars(
                                $usuario['nombre'] . ' ' .
                                $usuario['apellido_paterno'] . ' ' .
                                $usuario['apellido_materno']
                            ) ?>
                        </dd>
                    </dl>
                </div>
                <div class="col-md-6">
                    <dl>
                        <dt>Estado</dt>
                        <dd>
                            <span class="badge rounded-pill text-bg-<?= $controlador->EstiloEstado($usuario['estado_usuario']) ?>">
                                <?= htmlspecialchars(ucfirst($usuario['estado_usuario'])) ?>
                            </span>
                        </dd>
                    </dl>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <dl>
                        <dt>Correo institucional</dt>
                        <dd><?= htmlspecialchars($usuario['correo_institucional']) ?></dd>
                    </dl>
                </div>
                <div class="col-md-6">
                    <dl>
                        <dt>Teléfono</dt>
                        <dd><?= htmlspecialchars($usuario['telefono']) ?></dd>
                    </dl>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <dl>
                        <dt>CURP</dt>
                        <dd><?= htmlspecialchars($usuario['curp']) ?></dd>
                    </dl>
                </div>
                <div class="col-md-6">
                    <dl>
                        <dt>Género</dt>
                        <dd><?= htmlspecialchars($usuario['genero'] ?? '—') ?></dd>
                    </dl>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <dl>
                        <dt>Fecha de nacimiento</dt>
                        <dd><?= !empty($usuario['fecha_nacimiento'])
                                ? date("d/m/Y", strtotime($usuario['fecha_nacimiento']))
                                : '—' ?>
                        </dd>
                    </dl>
                </div>
                <div class="col-md-6">
                    <dl>
                        <dt>Tipo de usuario</dt>
                        <dd>
                            <span class="badge rounded-pill text-bg-secondary">
                                <?= htmlspecialchars(ucfirst($usuario['tipo_usuario'])) ?>
                            </span>
                        </dd>
                    </dl>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <dl>
                        <dt>Fecha de registro</dt>
                        <dd><?= date("d/m/Y H:i", strtotime($usuario['fecha_registro'])) ?></dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>

    <!-- DATOS ESPECÍFICOS: ESTUDIANTE -->
    <?php if (!empty($usuario['matricula'])): ?>
    <div class="card shadow-sm mb-4">
        <div class="card-header">
            <h5 class="mb-0">Datos de estudiante</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <dl>
                        <dt>Matrícula</dt>
                        <dd><?= htmlspecialchars($usuario['matricula']) ?></dd>
                    </dl>
                </div>
                <div class="col-md-6">
                    <dl>
                        <dt>Carrera</dt>
                        <dd><?= htmlspecialchars($usuario['nombre_carrera'] ?? '—') ?></dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- DATOS ESPECÍFICOS: INVESTIGADOR -->
    <?php if (!empty($usuario['rfc'])): ?>
    <div class="card shadow-sm mb-4">
        <div class="card-header">
            <h5 class="mb-0"><i class="bi bi-journal-text me-2"></i>Datos de investigador</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-4">
                    <dl>
                        <dt>RFC</dt>
                        <dd><?= htmlspecialchars($usuario['rfc']) ?></dd>
                    </dl>
                </div>
                <div class="col-md-4">
                    <dl>
                        <dt>Grado académico</dt>
                        <dd><?= htmlspecialchars($usuario['grado_academico'] ?? '—') ?></dd>
                    </dl>
                </div>
                <div class="col-md-4">
                    <dl>
                        <dt>Nivel SNI</dt>
                        <dd><?= htmlspecialchars($usuario['nivel_sni'] ?? '—') ?></dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ACCIONES -->
    <?php if ($usuario['estado_usuario'] === 'espera'): ?>
    <div class="card shadow-sm mb-4 border-warning">
        <div class="card-header bg-warning bg-opacity-25">
            <h5 class="mb-0"><i class="bi bi-shield-check me-2"></i>Acciones de aprobación</h5>
        </div>
        <div class="card-body">
            <p class="text-muted">Este usuario está en espera de aprobación. Elige una acción:</p>
            <div class="d-flex gap-2 flex-wrap">
                <a href="tabla.php?action=aprobar&id_usuarios=<?= $usuario['id_usuarios'] ?>"
                   class="btn btn-success"
                   onclick="return confirm('¿Aprobar el acceso de este usuario?')">
                    <i class="bi bi-check-circle-fill me-1"></i> Aprobar
                </a>
                <a href="respuesta.php?id_usuarios=<?= $usuario['id_usuarios'] ?>"
                   class="btn btn-danger">
                    <i class="bi bi-x-circle-fill me-1"></i> Rechazar
                </a>
            </div>
        </div>
    </div>
    <?php endif; ?>

</div>

<?php
$contenido = ob_get_clean();
$titulo    = "Detalles del usuario";
$bodyClass = "usuarios-page";

include __DIR__ . '/../../layout.php';
?>