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
    header("Location: /Modules/Principal/Views/index.php");
    exit;
}

require_once __DIR__ . '/../Controller/usuario_controller.php';

$controlador = new UsuariosControlador();

//  Validaciones de URL 
include __DIR__ . '/../../../public/incluido/_validar_get.php';

$id_ver = isset($_GET['id_usuarios']) ? intval($_GET['id_usuarios']) : 0;

$id_validar = $id_ver;
include __DIR__ . '/../../../public/incluido/_validar_id.php';

//  Cargar datos ─
$usuario = $controlador->indexDetalles($rol, $id_ver);

$registro = $usuario;
include __DIR__ . '/../../../public/incluido/_validar_datos.php';

//  Iconos reutilizables ─
include __DIR__ . '/../../../public/incluido/_iconos.php';

ob_start();
?>

<div class="container-fluid py-4 ancho_container">

    <!-- ENCABEZADO -->
    <div class="row mb-4 align-items-center">
        <?php
        $titulo      = 'Detalle de Usuario';
        $descripcion = 'Información del usuario seleccionado';
        include __DIR__ . '/../../../public/incluido/_encabezado.php';
        ?>
        <div class="col-6 col-md-6 text-md-end">
            <?php if ($rol === 'supervisor'): ?>
                <a href="index.php" class="btn btn-secondary btn-sm px-4">
                    <i class="<?= $iconos['tabla']['regresar'] ?>"></i> Regresar
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- INFORMACIÓN GENERAL -->
    <div class="card shadow-sm mb-4">
        <div class="card-header">
            <h5 class="mb-0">
                <i class="<?= $iconos['detalles']['informacion'] ?> me-2"></i>Información general
            </h5>
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
                            ) ?></dd>
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
                                : '—' ?></dd>
                    </dl>
                </div>
                <div class="col-md-6">
                    <dl>
                        <dt>Tipo de usuario</dt>
                        <dd>
                            <span class="badge rounded-pill text-bg-secondary">
                                <?= htmlspecialchars(ucfirst($usuario['tipo_usuario'] ?? 'Sin tipo')) ?> </span>
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
                <h5 class="mb-0">
                    <i class="<?= $iconos['detalles']['subinformacion'] ?> me-2"></i>Datos de estudiante
                </h5>
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
                <h5 class="mb-0">
                    <i class="bi bi-person-circle me-2"></i> <i class="<?= $iconos['detalles']['subinformacion'] ?> me-2"></i>Datos de investigador
                </h5>
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

    <!-- ACCIONES DE APROBACIÓN (solo si está en espera) -->
    <?php if ($usuario['estado_usuario'] === 'espera'): ?>
        <div class="card shadow-sm mb-4 border-warning">
            <div class="card-header bg-opacity-25">
                <h5 class="mb-0">
                    <i class="<?= $iconos['detalles']['espera'] ?> me-2"></i>Acciones de aprobación
                </h5>
            </div>
            <div class="card-body">
                <p class="text-muted">Este usuario está en espera de aprobación. Elige una acción:</p>
                <div class="d-flex gap-2 flex-wrap">
                    <a href="index.php?action=aprobar&id_usuarios=<?= $usuario['id_usuarios'] ?>"
                        class="btn btn-success"
                        onclick="return confirm('¿Aprobar el acceso de este usuario?')">
                        <i class="<?= $iconos['detalles']['exito_verificado'] ?> me-1"></i> Aprobar
                    </a>
                    <a href="respuesta.php?id_usuarios=<?= $usuario['id_usuarios'] ?>"
                        class="btn btn-danger">
                        <i class="<?= $iconos['tabla']['solicitar_cierre'] ?> me-1"></i> Rechazar
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

include __DIR__ . '/../../../layout.php';
?>