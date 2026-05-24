<?php
//Solicitudes_integracion_proyecto/index.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();

if (!isset($_SESSION['id_usuario'])) {
    header('Location: /ITSFCP-PROYECTOS/index.php');
    exit;
}

$rol        = strtolower($_SESSION['rol'] ?? '');
$id_usuario = intval($_SESSION['id_usuario']);

//Solo investigador o profesor pueden acceder
if (!in_array($rol, ['investigador', 'profesor'], true)) {
    header("Location: /ITSFCP-PROYECTOS/Vistas/Principal/index.php");
    exit;
}

require_once '../../Controladores/solicitudesControlador.php';
$ctrl = new solicitudesControlador();

//  Procesar acciones POST directas desde index ─
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion       = $_POST['accion']       ?? '';
    $id_solicitud = intval($_POST['id_solicitud'] ?? 0);

    if ($id_solicitud && $accion === 'aceptar') {
        $ctrl->aceptar($id_solicitud, $id_usuario, $rol);
        // aceptar() redirige internamente
    }
}

$resultado   = $ctrl->index($id_usuario, $rol);
$solicitudes = $resultado['solicitudes'] ?? [];
$resumen     = $resultado['resumen']     ?? [];
$proyectos   = $resultado['proyectos']   ?? [];
$filtros     = $resultado['filtros']     ?? [];
$paginacion  = $resultado['paginacion']  ?? [];
$periodos    = $resultado['periodos']    ?? [];

ob_start();
include __DIR__ . '/../../mensaje.php';
?>
<div class="container-fluid py-4">

    <div class="row mb-4 align-items-center">
        <div class="col-md-6">
            <h2 class="mb-0 fw-semibold" style="color: var(--color-primario); font-size: 1.6rem; letter-spacing: -.3px;">
                Solicitudes de integración
            </h2>
            <p class="mb-0 small" style="color: var(--color-texto-secundario); margin-top: 2px;">
                Gestiona las solicitudes de estudiantes para tus proyectos.
            </p>
        </div>
        <div class="col-md-6 text-md-end">
            <div class="d-flex align-items-center gap-2 flex-wrap justify-content-md-end">
                <label class="fw-semibold small mb-0 text-nowrap" style="color: var(--color-texto-principal);">Periodo:</label>
                <form method="GET" action="" id="formPeriodo" class="d-flex align-items-center gap-2 flex-wrap">
                    <?php foreach (['estado', 'buscar', 'proyecto', 'fecha_desde', 'fecha_hasta'] as $f): ?>
                        <?php if (!empty($filtros[$f])): ?>
                            <input type="hidden" name="<?= $f ?>" value="<?= htmlspecialchars($filtros[$f]) ?>">
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <select name="periodo" class="form-select form-select-sm" style="min-width: 180px; border-color: var(--borde-menu); color: var(--color-texto-principal); border-radius: 6px;"
                        onchange="document.getElementById('formPeriodo').submit()">
                        <option value="">Todos los periodos</option>
                        <?php foreach ($periodos as $p): ?>
                            <option value="<?= htmlspecialchars($p['id_periodos']) ?>"
                                <?= ($filtros['periodo'] ?? '') == $p['id_periodos'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($p['periodo']) ?>
                                <?php if (!empty($p['estado']) && $p['estado']): ?>
                                    (Activo)
                                <?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (!empty($filtros['periodo'])): ?>
                        <a href="index.php" class="btn btn-sm text-nowrap"
                            style="border: 1px solid var(--borde-menu); color: var(--color-texto-secundario); border-radius: 6px; background: var(--fondo-inputs);">
                            Limpiar
                        </a>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>



    <!-- RESUMEN -->
    <div class="row g-3 mb-4">
        <?php
        $cards = [
            ['num' => $resumen['total']       ?? 0, 'lbl' => 'Total',        'color' => 'primary'],
            ['num' => $resumen['pendientes']   ?? 0, 'lbl' => 'Pendientes',   'color' => 'secondary'],
            ['num' => $resumen['en_revision']  ?? 0, 'lbl' => 'En revisión',  'color' => 'info'],
            ['num' => $resumen['correcciones'] ?? 0, 'lbl' => 'Correcciones', 'color' => 'warning'],
            ['num' => $resumen['aceptadas']    ?? 0, 'lbl' => 'Aceptadas',    'color' => 'success'],
            ['num' => $resumen['rechazadas']   ?? 0, 'lbl' => 'Rechazadas',   'color' => 'danger'],
        ];
        foreach ($cards as $c): ?>
            <div class="col-6 col-md-4 col-xl-2">
                <div class="resumen-card border bg-white shadow-sm h-100">
                    <div class="resumen-num text-<?= $c['color'] ?>"><?= $c['num'] ?></div>
                    <div class="resumen-lbl"><?= $c['lbl'] ?></div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- FILTROS -->
    <form method="GET" action="" class="row g-2 mb-3 align-items-end">
        <?php if (!empty($filtros['periodo'])): ?>
            <input type="hidden" name="periodo" value="<?= htmlspecialchars($filtros['periodo']) ?>">
        <?php endif; ?>
        <div class="col-12 col-sm-6 col-md-4 col-lg-3">
            <label class="form-label small fw-medium mb-1">Buscar</label>
            <input type="text" name="buscar" class="form-control form-control-sm"
                placeholder="Nombre, matrícula, proyecto…"
                value="<?= htmlspecialchars($filtros['buscar'] ?? '') ?>">
        </div>
        <div class="col-6 col-sm-4 col-md-2">
            <label class="form-label small fw-medium mb-1">Estado</label>
            <select name="estado" class="form-select form-select-sm">
                <option value="">Todos</option>
                <?php foreach (
                    [
                        'pendiente'    => 'Pendiente',
                        'en_revision'  => 'En revisión',
                        'correcciones' => 'Correcciones',
                        'aceptado'     => 'Aceptado',
                        'rechazado'    => 'Rechazado',
                    ] as $val => $lbl
                ): ?>
                    <option value="<?= $val ?>" <?= ($filtros['estado'] ?? '') === $val ? 'selected' : '' ?>>
                        <?= $lbl ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-sm-4 col-md-3">
            <label class="form-label small fw-medium mb-1">Proyecto</label>
            <select name="proyecto" class="form-select form-select-sm">
                <option value="">Todos</option>
                <?php foreach ($proyectos as $p): ?>
                    <option value="<?= $p['id_proyectos'] ?>"
                        <?= ($filtros['proyecto'] ?? '') == $p['id_proyectos'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars(mb_strimwidth($p['titulo'], 0, 40, '…')) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-sm-3 col-md-2">
            <label class="form-label small fw-medium mb-1">Desde</label>
            <input type="date" name="fecha_desde" class="form-control form-control-sm"
                value="<?= htmlspecialchars($filtros['fecha_desde'] ?? '') ?>">
        </div>
        <div class="col-6 col-sm-3 col-md-2">
            <label class="form-label small fw-medium mb-1">Hasta</label>
            <input type="date" name="fecha_hasta" class="form-control form-control-sm"
                value="<?= htmlspecialchars($filtros['fecha_hasta'] ?? '') ?>">
        </div>
        <div class="col-auto d-flex gap-2">
            <button type="submit" class="btn btn-primary btn-sm">
                <i class="bi bi-search"></i> Filtrar
            </button>
            <a href="index.php<?= !empty($filtros['periodo']) ? '?periodo=' . urlencode($filtros['periodo']) : '' ?>"
                class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-x-circle"></i> Limpiar
            </a>
        </div>
    </form>

    <!-- TABLA ESCRITORIO -->
    <?php if (!empty($solicitudes)): ?>
        <div class="card shadow-sm d-none d-md-block">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <?php foreach ($ctrl->encabezados() as $h): ?>
                                    <th class="small fw-semibold"><?= $h ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($solicitudes as $sol): ?>
                                <tr>
                                    <td class="text-muted small"><?= $sol['id_solicitud_proyecto'] ?></td>
                                    <td class="text-start">
                                        <div class="fw-medium"><?= htmlspecialchars($sol['estudiante_nombre']) ?></div>
                                        <div class="text-muted small"><?= htmlspecialchars($sol['correo_institucional'] ?? '') ?></div>
                                    </td>
                                    <td><?= htmlspecialchars($sol['matricula'] ?? '-') ?></td>
                                    <td class="small"><?= htmlspecialchars($sol['carrera'] ?? '-') ?></td>
                                    <td class="text-start small"><?= htmlspecialchars(mb_strimwidth($sol['proyecto_titulo'], 0, 45, '…')) ?></td>
                                    <td><?= $sol['semestre'] ? $sol['semestre'] . '°' : '-' ?></td>
                                    <td><?= $sol['promedio'] ?? '-' ?></td>
                                    <td class="small"><?= $sol['fecha_envio'] ?></td>
                                    <td><?= $ctrl->badgeEstado($sol['estado']) ?></td>
                                    <td class="text-nowrap">
                                        <?= $ctrl->botonesAccion(
                                            $sol['id_solicitud_proyecto'],
                                            $sol['estado'],
                                            $sol['id_proyectos'],
                                            $filtros
                                        ) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- MÓVIL -->
        <div class="d-md-none">
            <?php foreach ($solicitudes as $sol): ?>
                <div class="card mb-3 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <div class="fw-semibold"><?= htmlspecialchars($sol['estudiante_nombre']) ?></div>
                                <div class="text-muted small"><?= htmlspecialchars($sol['matricula'] ?? '') ?></div>
                            </div>
                            <?= $ctrl->badgeEstado($sol['estado']) ?>
                        </div>
                        <p class="small mb-1">
                            <strong>Proyecto:</strong>
                            <?= htmlspecialchars(mb_strimwidth($sol['proyecto_titulo'], 0, 50, '…')) ?>
                        </p>
                        <p class="small mb-2"><strong>Fecha:</strong> <?= $sol['fecha_envio'] ?></p>
                        <div class="d-flex gap-2 flex-wrap">
                            <?= $ctrl->botonesAccion(
                                $sol['id_solicitud_proyecto'],
                                $sol['estado'],
                                $sol['id_proyectos'],
                                $filtros
                            ) ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- PAGINACIÓN -->
        <?php if ($paginacion['total_paginas'] > 1):
            $qBase = http_build_query(array_filter([
                'periodo'     => $filtros['periodo'],
                'buscar'      => $filtros['buscar'],
                'estado'      => $filtros['estado'],
                'proyecto'    => $filtros['proyecto'],
                'fecha_desde' => $filtros['fecha_desde'],
                'fecha_hasta' => $filtros['fecha_hasta'],
            ]));
            $entidad = 'solicitudes';
            include __DIR__ . '../../../publico/incluido/_paginacion.php';
        endif; ?>

    <?php else: ?>
        <div class="alert alert-info text-center">
            <i class="bi bi-inbox me-2"></i>No hay solicitudes que coincidan con los filtros.
        </div>
    <?php endif; ?>

</div>

<?php
$contenido = ob_get_clean();
$titulo    = 'Solicitudes de integración';
$bodyClass = 'proyectos-page';
include __DIR__ . '/../../layout.php';
?>