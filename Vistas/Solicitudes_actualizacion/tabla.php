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

require_once '../../Controladores/solicitudactualizacionControlador.php';

$controlador = new SolicitudActualizacionControlador();

// ── Parámetros GET ───────────────────────────────────────────────
$action = isset($_GET['action']) ? $_GET['action'] : 'index';
$buscar = trim($_GET['buscar'] ?? '');
$tipo   = trim($_GET['tipo']   ?? '');  // sni | grado | (vacío = todos)

// ── Acción directa: aprobar ──────────────────────────────────────
if ($action === 'aprobar' && isset($_GET['id_solicitud'])) {
    $controlador->aprobar(intval($_GET['id_solicitud']), $id_usuario);
    exit;
}

// ── Validar acción ───────────────────────────────────────────────
$accionesPermitidas = ['index', 'Pendiente', 'Aprobado', 'Rechazado'];
if (!in_array($action, $accionesPermitidas)) $action = 'index';

// ── Ejecutar acción ──────────────────────────────────────────────
$resultado   = $controlador->$action($buscar ?: null, $tipo ?: null);
$solicitudes = $resultado['solicitudes']  ?? [];
$paginacion  = $resultado['paginacion']   ?? ['total' => 0, 'por_pagina' => 8, 'pagina' => 1, 'total_paginas' => 1];

// ── Filtros y encabezados ────────────────────────────────────────
$filtros     = $controlador->filtros();
$opciones    = $controlador->opciones($filtros);
$encabezados = $controlador->encabezados();

// ── Mensajes ─────────────────────────────────────────────────────
$msg   = $_GET['msg']   ?? '';
$error = $_GET['error'] ?? '';

ob_start();
?>

<div class="container-fluid py-4">

    <!-- TÍTULO -->
    <div class="row mb-4 align-items-center">
        <div class="col-12 col-md-6">
            <h3 class="fw-bold mb-2 mb-md-0">Solicitudes de actualización académica</h3>
        </div>
    </div>

    <!-- ALERTAS -->
    <?php if ($msg === 'aprobado'): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle-fill me-1"></i> Solicitud aprobada correctamente.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php elseif ($msg === 'rechazado'): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-x-circle-fill me-1"></i> Solicitud rechazada y notificación enviada.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php elseif (!empty($error)): ?>
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-1"></i> <?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- FILTROS -->
    <div class="row g-2 mb-4">

        <!-- Select estado -->
        <div class="col-12 col-md-3">
            <select class="form-select"
                    onchange="location.href='tabla.php?action=' + this.value
                        + '&buscar=<?= urlencode($buscar) ?>&tipo=<?= urlencode($tipo) ?>';">
                <?php foreach ($opciones as $key => $label): ?>
                    <option value="<?= htmlspecialchars($key) ?>"
                        <?= ($action === $key) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($label) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Select tipo (SNI / Grado) -->
        <div class="col-12 col-md-3">
            <select class="form-select"
                    onchange="location.href='tabla.php?action=<?= urlencode($action) ?>&buscar=<?= urlencode($buscar) ?>&tipo=' + this.value;">
                <option value=""  <?= ($tipo === '')      ? 'selected' : '' ?>>Todos los tipos</option>
                <option value="sni"   <?= ($tipo === 'sni')   ? 'selected' : '' ?>>Nivel SNI</option>
                <option value="grado" <?= ($tipo === 'grado') ? 'selected' : '' ?>>Grado académico</option>
            </select>
        </div>

        <!-- Búsqueda -->
        <div class="col-12 col-md-6">
            <form class="d-flex gap-2" method="GET" action="tabla.php">
                <input type="hidden" name="action" value="<?= htmlspecialchars($action) ?>">
                <input type="hidden" name="tipo"   value="<?= htmlspecialchars($tipo) ?>">
                <input type="text"
                       name="buscar"
                       class="form-control"
                       placeholder="Buscar por investigador..."
                       value="<?= htmlspecialchars($buscar) ?>">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-search"></i>
                </button>
                <?php if (!empty($buscar)): ?>
                    <a href="tabla.php?action=<?= urlencode($action) ?>&tipo=<?= urlencode($tipo) ?>"
                       class="btn btn-secondary" title="Limpiar">
                        <i class="bi bi-x-lg"></i>
                    </a>
                <?php endif; ?>
            </form>
        </div>

    </div>

    <!-- TABLA ESCRITORIO -->
    <div class="table-responsive d-none d-md-block">
        <table class="table table-hover text-center align-middle">
            <thead>
                <tr>
                    <?php foreach ($encabezados as $enc): ?>
                        <th><?= htmlspecialchars($enc) ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($solicitudes)): ?>
                    <?php foreach ($solicitudes as $s): ?>
                        <tr>
                            <td class="text-start">
                                <span class="fw-semibold"><?= htmlspecialchars($s['investigador']) ?></span><br>
                                <small class="text-muted"><?= htmlspecialchars($s['correo_institucional']) ?></small>
                            </td>
                            <td>
                                <span class="badge rounded-pill bg-<?= $s['tipo'] === 'sni' ? 'primary' : 'success' ?>">
                                    <?= $controlador->etiquetaTipo($s['tipo']) ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($s['valor_actual_nombre'] ?? '—') ?></td>
                            <td><strong><?= htmlspecialchars($s['valor_nuevo_nombre'] ?? '—') ?></strong></td>
                            <td>
                                <?php if (!empty($s['nombre_archivo'])): ?>
                                    <a href="<?= htmlspecialchars($s['ruta']) ?>"
                                       target="_blank"
                                       class="btn btn-sm btn-outline-danger"
                                       data-bs-toggle="tooltip" title="Ver PDF">
                                        <i class="bi bi-file-earmark-pdf-fill"></i>
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted small">Sin doc.</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge rounded-pill bg-<?= $controlador->estiloEstado($s['estado']) ?>">
                                    <i class="bi <?= $controlador->iconoEstado($s['estado']) ?> me-1"></i>
                                    <?= ucfirst($s['estado']) ?>
                                </span>
                            </td>
                            <td>
                                <small><?= date("d/m/Y H:i", strtotime($s['fecha_solicitud'])) ?></small>
                            </td>
                            <td>
                                <div class="d-flex justify-content-center gap-1">
                                    <?= $controlador->botonesAccion($s['id_solicitudes_actualizacion'], $s['estado']) ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="<?= count($encabezados) ?>">
                            <div class="alert alert-info mb-0">No se encontraron solicitudes.</div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- TARJETAS MÓVIL -->
    <div class="d-block d-md-none">
        <?php if (!empty($solicitudes)): ?>
            <?php foreach ($solicitudes as $s): ?>
                <div class="card shadow-sm mb-3">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <h6 class="fw-bold mb-0"><?= htmlspecialchars($s['investigador']) ?></h6>
                                <small class="text-muted"><?= htmlspecialchars($s['correo_institucional']) ?></small>
                            </div>
                            <span class="badge rounded-pill bg-<?= $controlador->estiloEstado($s['estado']) ?>">
                                <?= ucfirst($s['estado']) ?>
                            </span>
                        </div>
                        <hr class="my-2">
                        <div class="row g-1 small">
                            <div class="col-6">
                                <strong>Tipo:</strong><br>
                                <span class="badge bg-<?= $s['tipo'] === 'sni' ? 'primary' : 'success' ?> rounded-pill">
                                    <?= $controlador->etiquetaTipo($s['tipo']) ?>
                                </span>
                            </div>
                            <div class="col-6">
                                <strong>Solicitud:</strong><br>
                                <?= htmlspecialchars($s['valor_actual_nombre'] ?? '—') ?>
                                <i class="bi bi-arrow-right mx-1"></i>
                                <strong><?= htmlspecialchars($s['valor_nuevo_nombre'] ?? '—') ?></strong>
                            </div>
                            <div class="col-6 mt-2">
                                <strong>Fecha:</strong><br>
                                <?= date("d/m/Y H:i", strtotime($s['fecha_solicitud'])) ?>
                            </div>
                            <div class="col-6 mt-2">
                                <strong>Documento:</strong><br>
                                <?php if (!empty($s['nombre_archivo'])): ?>
                                    <a href="<?= htmlspecialchars($s['ruta']) ?>" target="_blank"
                                       class="btn btn-sm btn-outline-danger py-0 px-2">
                                        <i class="bi bi-file-earmark-pdf-fill"></i> PDF
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted">Sin doc.</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer bg-white border-top">
                        <div class="d-flex justify-content-center gap-2">
                            <?= $controlador->botonesAccion($s['id_solicitudes_actualizacion'], $s['estado']) ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="alert alert-info">No se encontraron solicitudes.</div>
        <?php endif; ?>
    </div>

    <!-- PAGINACIÓN -->
    <?php if ($paginacion['total_paginas'] > 1): ?>
        <nav class="mt-4">
            <ul class="pagination justify-content-center flex-wrap">
                <?php
                $ini = ($paginacion['pagina'] - 1) * $paginacion['por_pagina'] + 1;
                $fin = min($ini + $paginacion['por_pagina'] - 1, $paginacion['total']);
                ?>
                <li class="page-item disabled">
                    <span class="page-link">
                        Mostrando <?= $ini ?> – <?= $fin ?> de <?= $paginacion['total'] ?>
                    </span>
                </li>
                <?php for ($i = 1; $i <= $paginacion['total_paginas']; $i++): ?>
                    <li class="page-item <?= ($i == $paginacion['pagina']) ? 'active' : '' ?>">
                        <a class="page-link"
                           href="?action=<?= urlencode($action) ?>&pagina=<?= $i ?>
                               <?= !empty($buscar) ? '&buscar=' . urlencode($buscar) : '' ?>
                               <?= !empty($tipo)   ? '&tipo='   . urlencode($tipo)   : '' ?>">
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
$titulo    = "Solicitudes académicas";
$bodyClass = "solicitudes-page";

include __DIR__ . '/../../layout.php';
?>