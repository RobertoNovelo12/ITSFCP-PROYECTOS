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
    header("Location: /ITSFCP-PROYECTOS/Vistas/Principal/index.php");
    exit;
}

require_once __DIR__ . '/../../Controladores/usuarioControlador.php';

$controlador = new UsuariosControlador();

//  Parámetros GET 
$action = $_GET['action'] ?? 'index';
$buscar = trim($_GET['buscar'] ?? '');
$tipo   = trim($_GET['tipo']   ?? '');
$pagina = max(1, intval($_GET['pagina'] ?? 1));

//  Acción directa: aprobar desde tabla ─
if ($action === 'aprobar' && isset($_GET['id_usuarios'])) {
    $controlador->aprobar(intval($_GET['id_usuarios']), $rol);
    exit;
}

//  Validar acción permitida ─
$accionesPermitidas = ['index', 'Espera', 'Activo', 'Cancelado'];
if (!in_array($action, $accionesPermitidas, true)) {
    header("Location: index.php?msg=accion_no_permitida");
    exit;
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
    'total_paginas'=> 1,
];

$encabezados = $controlador->encabezados($rol);
$opciones    = $controlador->opciones();

//  Mensajes ─
$msg   = $_GET['msg'] ?? '';
$_mapa = [
    'exito_operacion'    => ['tipo' => 'exito',  'titulo_msg' => 'Operación completada',   'mensaje' => 'La operación sobre el usuario fue realizada correctamente.'],
    'error_operacion'    => ['tipo' => 'error',  'titulo_msg' => 'Error en la operación',  'mensaje' => 'No fue posible completar la operación. Intenta de nuevo.'],
    'error_cargar'       => ['tipo' => 'error',  'titulo_msg' => 'Error al cargar',        'mensaje' => 'No fue posible cargar los datos. Intenta de nuevo.'],
    'error_sin_registro' => ['tipo' => 'error',  'titulo_msg' => 'Sin registro',           'mensaje' => 'No fue posible cargar los datos. Intenta de nuevo.'],
    'sin_permiso'        => ['tipo' => 'alerta', 'titulo_msg' => 'Acceso restringido',     'mensaje' => 'No tienes permiso para ver esta sección.'],
    'sin_argumentos_url' => ['tipo' => 'alerta', 'titulo_msg' => 'Parámetros faltantes',   'mensaje' => 'La acción solicitada no está disponible por falta de parámetros en la URL.'],
    'accion_no_permitida'=> ['tipo' => 'alerta', 'titulo_msg' => 'Acción no permitida',    'mensaje' => 'La acción solicitada no está disponible para tu rol.'],
];

ob_start();
?>

<div class="container-fluid py-4 ancho_container">

    <!-- ENCABEZADO -->
    <div class="row mb-4 align-items-center">
        <?php
        $titulo      = 'Usuarios';
        $descripcion = 'Gestión de cuentas del sistema';
        include __DIR__ . '../../../publico/incluido/_encabezado.php';
        ?>
        <div class="col-6 col-md-6 text-md-end"></div>
    </div>

    <!-- ALERTAS -->
    <?php
    if (isset($_mapa[$msg])) {
        extract($_mapa[$msg]);
        include __DIR__ . '../../../publico/incluido/_mensaje.php';
    }
    ?>

    <!-- FILTROS Y BÚSQUEDA -->
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body py-2">

            <!-- TOTAL REGISTROS -->
            <?php include __DIR__ . '../../../publico/incluido/_total_registros.php'; ?>

            <div class="row g-2 align-items-end">

                <!-- FILTRO ESTADO -->
                <div class="col-md-3 mb-1">
                    <label class="form-label mb-1 small fw-semibold">Estado</label>
                    <select class="form-select"
                        onchange="location.href='?action=' + this.value
                            + '&buscar=<?= urlencode($buscar) ?>'
                            + '&tipo=<?= urlencode($tipo) ?>'">
                        <?php foreach ($opciones as $key => $label): ?>
                            <option value="<?= htmlspecialchars($key) ?>"
                                <?= ($action === $key) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- FILTRO TIPO -->
                <div class="col-md-3 mb-1">
                    <label class="form-label mb-1 small fw-semibold">Tipo</label>
                    <select class="form-select"
                        onchange="location.href='?action=<?= urlencode($action) ?>'
                            + '&buscar=<?= urlencode($buscar) ?>'
                            + '&tipo=' + this.value">
                        <option value="" <?= ($tipo === '') ? 'selected' : '' ?>>Todos los tipos</option>
                        <option value="estudiante"   <?= ($tipo === 'estudiante')   ? 'selected' : '' ?>>Estudiante</option>
                        <option value="investigador" <?= ($tipo === 'investigador') ? 'selected' : '' ?>>Investigador</option>
                        <option value="supervisor"   <?= ($tipo === 'supervisor')   ? 'selected' : '' ?>>Supervisor</option>
                    </select>
                </div>

                <!-- BUSCADOR -->
                <div class="col-md-6 mb-1">
                    <label class="form-label mb-1 small fw-semibold">Buscar</label>
                    <form class="d-flex gap-2" method="GET">
                        <input type="hidden" name="action" value="<?= htmlspecialchars($action) ?>">
                        <input type="hidden" name="tipo"   value="<?= htmlspecialchars($tipo) ?>">
                        <input type="text"
                            name="buscar"
                            class="form-control"
                            placeholder="Buscar por nombre..."
                            value="<?= htmlspecialchars($buscar) ?>">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-search"></i>
                        </button>
                        <?php if (!empty($buscar)): ?>
                            <a href="?action=<?= urlencode($action) ?>&tipo=<?= urlencode($tipo) ?>"
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
        $qBase   = 'action=' . urlencode($action)
            . (!empty($buscar) ? '&buscar=' . urlencode($buscar) : '')
            . (!empty($tipo)   ? '&tipo='   . urlencode($tipo)   : '');
        $entidad = 'usuarios';
        include __DIR__ . '../../../publico/incluido/_paginacion.php';
    endif; ?>

</div>

<?php
$contenido = ob_get_clean();
$titulo    = "Usuarios";
$bodyClass = "usuarios-page";

include __DIR__ . '/../../layout.php';
?>
