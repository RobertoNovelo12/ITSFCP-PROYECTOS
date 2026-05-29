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

// Solo supervisores
if ($rol !== 'supervisor') {
    header("Location: /ITSFCP-PROYECTOS/Vistas/Principal/index.php");
    exit;
}

require_once '../../Controladores/usuarioControlador.php';

$controlador = new UsuariosControlador();

//  Parámetros GET ─
$action = isset($_GET['action']) ? $_GET['action'] : 'index';
$buscar = trim($_GET['buscar'] ?? '');
$tipo   = trim($_GET['tipo']   ?? '');  // estudiante | investigador | supervisor | (vacío = todos)
$pagina = max(1, intval($_GET['pagina'] ?? 1));

//  Acciones directas (aprobar desde tabla) 
if ($action === 'aprobar' && isset($_GET['id_usuarios'])) {
    $controlador->aprobar(intval($_GET['id_usuarios']), $rol);
    exit; // aprobar() redirige
}

//  Validar que la acción exista en el controlador ─
$accionesPermitidas = ['index', 'Espera', 'Aprobado', 'Activo', 'Cancelado'];
if (!in_array($action, $accionesPermitidas)) {
    header("Location: index.php?msg=accion_no_permitida");
}

//  Ejecutar acción 
$resultado = $controlador->$action($rol, $buscar ?: null, $tipo ?: null);

if (is_string($resultado)) {
    $resultado = json_decode($resultado, true);
}

$usuarios   = $resultado['usuarios']   ?? [];
$paginacion = $resultado['paginacion'] ?? [
    'total'        => 0,
    'por_pagina'   => 6,
    'pagina'       => $pagina,
    'total_paginas' => 1
];

//  Datos para filtros y tabla ─
$filtros     = $controlador->filtros($rol);
$encabezados = $controlador->encabezados($rol);
$opciones    = $controlador->opciones($rol, $filtros);

//  Mensaje de éxito/error ─
$msg = $_GET['msg'] ?? '';

ob_start();
?>

<div class="container-fluid py-4 ancho_container">

    <!-- TÍTULO -->
    <div class="row mb-4 align-items-center">

        <?php
        $titulo      = 'Usuarios';
        $descripcion = 'Gestión de cuentas del sistema';
        include __DIR__ . '../../../publico/incluido/_encabezado.php';
        ?>

        <div class="col-6 col-md-6 text-md-end">
        </div>
    </div>

    <!-- ALERTAS - ACTUALIZAR A COMO ESTÁN LOS DEMÁS MSG -->
    <?php if ($msg === 'aprobado') {
        $mensaje = ' Usuario aprobado correctamente.';
        include __DIR__ . '../../../publico/incluido/_mensaje_exito.php';

    ?>

    <?php } elseif ($msg === 'rechazado') {
        $mensaje = ' Solicitud rechazada y notificación enviada.';
        include __DIR__ . '../../../publico/incluido/_mensaje_error.php';

    ?>
    <?php } elseif ($msg === 'accion_no_permitida') {
        $mensaje = ' Acción no reconocida.';
        include __DIR__ . '../../../publico/incluido/_mensaje_alerta.php';
    }
    ?>

    <!-- FILTROS -->
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body py-2">
            <div class="row g-2 align-items-end">

                <!-- Select estado -->
                <div class="col-md-3 mb-1">
                    <label class="form-label mb-1 small fw-semibold">Estado</label>
                    <select class="form-select"
                        onchange="location.href='index.php?action=' + this.value
                        + '&buscar=<?= urlencode($buscar) ?>&tipo=<?= urlencode($tipo) ?>';">
                        <?php foreach ($opciones as $key => $label): ?>
                            <option value="<?= htmlspecialchars($key) ?>"
                                <?= ($action === $key) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Select tipo de usuario -->
                <div class="col-md-3 mb-1">
                    <label class="form-label mb-1 small fw-semibold">Tipo</label>
                    <select class="form-select"
                        onchange="location.href='index.php?action=<?= urlencode($action) ?>&buscar=<?= urlencode($buscar) ?>&tipo=' + this.value;">
                        <option value="" <?= ($tipo === '')               ? 'selected' : '' ?>>Todos los tipos</option>
                        <option value="estudiante" <?= ($tipo === 'estudiante')   ? 'selected' : '' ?>>Estudiante</option>
                        <option value="investigador" <?= ($tipo === 'investigador') ? 'selected' : '' ?>>Investigador</option>
                        <option value="supervisor" <?= ($tipo === 'supervisor')   ? 'selected' : '' ?>>Supervisor</option>
                    </select>
                </div>

                <!-- Búsqueda por nombre -->
                <div class="col-md-6 mb-1">
                    <label class="form-label mb-1 small fw-semibold">Buscar</label>
                    <form class="d-flex gap-2" method="GET" action="index.php">
                        <input type="hidden" name="action" value="<?= htmlspecialchars($action) ?>">
                        <input type="hidden" name="tipo" value="<?= htmlspecialchars($tipo) ?>">
                        <input type="text"
                            name="buscar"
                            class="form-control"
                            placeholder="Buscar por nombre..."
                            value="<?= htmlspecialchars($buscar) ?>">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-search"></i>
                        </button>
                        <?php if (!empty($buscar)): ?>
                            <a href="index.php?action=<?= urlencode($action) ?>&tipo=<?= urlencode($tipo) ?>"
                                class="btn btn-secondary" title="Limpiar búsqueda">
                                <i class="bi bi-x-lg"></i>
                            </a>
                        <?php endif; ?>
                    </form>
                </div>

            </div>
        </div>
    </div>

    <!-- TABLA ESCRITORIO -->
    <div class="card shadow-sm d-none d-md-block">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <?php foreach ($encabezados as $enc): ?>
                                <th><?= htmlspecialchars($enc) ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($usuarios)): ?>
                            <?php foreach ($usuarios as $u): ?>
                                <tr>
                                    <td><?= htmlspecialchars($u['nombre_completo']) ?></td>
                                    <td><?= htmlspecialchars($u['correo_institucional']) ?></td>
                                    <td><?= htmlspecialchars($u['telefono']) ?></td>
                                    <td>
                                        <span class="badge rounded-pill text-bg-secondary">
                                            <?= htmlspecialchars(ucfirst($u['tipo_usuario'])) ?>
                                        </span>
                                    </td>
                                    <td><?= date("d/m/Y H:i", strtotime($u['fecha_registro'])) ?></td>
                                    <td>
                                        <span class="badge rounded-pill text-bg-<?= $controlador->EstiloEstado($u['estado_usuario']) ?>">
                                            <?= htmlspecialchars(ucfirst($u['estado_usuario'])) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="d-flex justify-content-center gap-1">
                                            <?= $controlador->botonesAccion($u['id_usuarios'], $rol, $u['estado_usuario']) ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="<?= count($encabezados) ?>">
                                    <div class="alert alert-info mb-0">No se encontraron usuarios.</div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- TARJETAS MÓVIL -->
    <div class="d-block d-md-none">
        <?php if (!empty($usuarios)): ?>
            <?php foreach ($usuarios as $u): ?>
                <div class="card shadow-sm mb-3">
                    <div class="card-body text-center">
                        <h6 class="fw-bold"><?= htmlspecialchars($u['nombre_completo']) ?></h6>
                        <span class="badge rounded-pill text-bg-<?= $controlador->EstiloEstado($u['estado_usuario']) ?>">
                            <?= htmlspecialchars(ucfirst($u['estado_usuario'])) ?>
                        </span>
                    </div>
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item">
                            <strong>Correo:</strong> <?= htmlspecialchars($u['correo_institucional']) ?>
                        </li>
                        <li class="list-group-item">
                            <strong>Tipo:</strong> <?= htmlspecialchars(ucfirst($u['tipo_usuario'])) ?>
                        </li>
                        <li class="list-group-item">
                            <strong>Registro:</strong> <?= date("d/m/Y H:i", strtotime($u['fecha_registro'])) ?>
                        </li>
                    </ul>
                    <div class="card-body">
                        <div class="d-flex justify-content-center gap-2">
                            <?= $controlador->botonesAccion($u['id_usuarios'], $rol, $u['estado_usuario']) ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="alert alert-info">No se encontraron usuarios.</div>
        <?php endif; ?>
    </div>

    <!-- PAGINACIÓN -->
    <?php if ($paginacion['total_paginas'] > 1):
        $qBase = 'action=' . urlencode($action)
            . (!empty($buscar) ? '&buscar=' . urlencode($buscar) : '')
            . (!empty($tipo)   ? '&tipo='   . urlencode($tipo)   : '');
        $entidad = 'usuarios';
        include __DIR__ . '../../../publico/incluido/_paginacion.php'; ?>

    <?php endif; ?>

</div>

<?php
$contenido = ob_get_clean();
$titulo    = "Usuarios";
$bodyClass = "usuarios-page";

include __DIR__ . '/../../layout.php';
?>