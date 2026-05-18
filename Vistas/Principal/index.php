<?php
// Vistas/menu/principal.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

if (!isset($_SESSION['id_usuario'])) {
    header("Location: /ITSFCP-PROYECTOS/index.php");
    exit;
}

//  AJAX: subtémáticas dependientes ─
// Se atiende antes de cualquier HTML para no contaminar la respuesta JSON
if (isset($_GET['action']) && $_GET['action'] === 'subtematicas') {
    require_once '../../Controladores/principalControlador.php';
    $c = new principalControlador();
    $c->subtematicasPorTematica(); // hace header + echo + exit internamente
}

require_once '../../Controladores/principalControlador.php';

$rol        = strtolower($_SESSION['rol'] ?? '');
$id_usuario = (int)$_SESSION['id_usuario'];

$ctrl      = new principalControlador();
$resultado = $ctrl->listarProyectos($rol, $id_usuario);

$proyectos      = $resultado['proyectos'];
$tematicas      = $resultado['tematicas'];
$subtematicas   = $resultado['subtematicas'];
$filtros        = $resultado['filtros'];
$paginacion     = $resultado['paginacion'];
$ventanaAbierta = $resultado['ventana_abierta'];
$puedeCrear     = $resultado['puede_crear'];

// Parámetros de paginación para construir URLs
$qBase = http_build_query(array_filter([
    'buscar'         => $filtros['buscar'],
    'modalidad'      => $filtros['modalidad'],
    'id_tematica'    => $filtros['id_tematica']   ?: null,
    'id_subtematica' => $filtros['id_subtematica'] ?: null,
]));

ob_start();
?>

<!-- 
     CONTENIDO
      -->
<div class="container-fluid py-4" style="max-width:1200px;">

    <!-- Encabezado de página -->
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h4 class="fw-bold mb-0" style="color:var(--tecnm-navy);">Propuestas de Investigación</h4>
            <p class="text-muted mb-0" style="font-size:13px;">
                Periodo <?= htmlspecialchars($proyectos[0]['periodo'] ?? '—') ?>
            </p>
        </div>
    </div>

    <!--  FILTRO  -->
    <div class="filtro-section">
        <p class="filtro-titulo">Criterios de búsqueda</p>
        <form method="GET" action="" id="form-filtro">
            <div class="row g-2 align-items-end">

                <!-- Palabra clave -->
                <div class="col-12 col-md-6 col-lg-4">
                    <label class="form-label" for="buscar">Palabra clave</label>
                    <input
                        type="text"
                        id="buscar"
                        name="buscar"
                        class="form-control"
                        placeholder="Título o descripción…"
                        value="<?= htmlspecialchars($filtros['buscar']) ?>"
                        autocomplete="off">
                </div>

                <!-- Modalidad -->
                <div class="col-12 col-sm-6 col-md-3 col-lg-2">
                    <label class="form-label" for="modalidad">Modalidad</label>
                    <select id="modalidad" name="modalidad" class="form-select">
                        <option value="">Todas</option>
                        <option value="virtual" <?= $filtros['modalidad'] === 'virtual' ? 'selected' : '' ?>>Virtual</option>
                        <option value="fisico" <?= $filtros['modalidad'] === 'fisico'  ? 'selected' : '' ?>>Presencial</option>
                        <option value="mixto" <?= $filtros['modalidad'] === 'mixto'   ? 'selected' : '' ?>>Mixta</option>
                    </select>
                </div>

                <!-- Temática -->
                <div class="col-12 col-sm-6 col-md-3 col-lg-3">
                    <label class="form-label" for="id_tematica">Temática</label>
                    <select id="id_tematica" name="id_tematica" class="form-select">
                        <option value="0">Todas</option>
                        <?php foreach ($tematicas as $t): ?>
                            <option value="<?= $t['id_tematica'] ?>"
                                <?= $filtros['id_tematica'] === $t['id_tematica'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($t['nombre_tematica']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Subtémática (dependiente vía JS) -->
                <div class="col-12 col-sm-6 col-md-4 col-lg-3">
                    <label class="form-label" for="id_subtematica">Subtémática</label>
                    <select id="id_subtematica" name="id_subtematica" class="form-select">
                        <option value="0">Todas</option>
                        <?php foreach ($subtematicas as $s): ?>
                            <option value="<?= $s['id_subtematica'] ?>"
                                <?= $filtros['id_subtematica'] === $s['id_subtematica'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($s['nombre_subtematica']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Botones -->
                <div class="col-12 col-sm-auto d-flex gap-2">
                    <button type="submit" class="btn-filtrar">
                        <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="currentColor" class="me-1" viewBox="0 0 16 16">
                            <path d="M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001q.044.06.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1 1 0 0 0-.115-.099zm-5.242 1.656a5.5 5.5 0 1 1 0-11 5.5 5.5 0 0 1 0 11" />
                        </svg>
                        Buscar
                    </button>
                    <a href="?" class="btn-limpiar">Limpiar</a>
                </div>

            </div>
        </form>
    </div>

    <!--  Nota para estudiante ─ -->
    <?php if ($rol === 'estudiante' && $ventanaAbierta): ?>
        <div class="nota-convocatoria">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 20 20">
                <circle cx="10" cy="10" r="8.5" stroke="currentColor" stroke-width="1.4" />
                <path d="M10 9v5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
                <circle cx="10" cy="6.5" r="0.8" fill="currentColor" />
            </svg>
            <span>La convocatoria está abierta. Puedes elegir <strong>hasta 3 proyectos</strong> de tu interés para solicitar integración.</span>
        </div>
    <?php elseif ($rol === 'estudiante' && !$ventanaAbierta): ?>
        <div class="nota-convocatoria" style="border-left-color:var(--tecnm-gold);background:rgba(212,160,23,.08);">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 20 20" style="color:#a87a10">
                <path d="M9.13 3.4L2.2 15.1A1 1 0 0 0 3.07 16.6h13.86a1 1 0 0 0 .87-1.5L10.87 3.4a1 1 0 0 0-1.74 0z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round" />
                <path d="M10 8.5v3.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
                <circle cx="10" cy="13.8" r="0.75" fill="currentColor" />
            </svg>
            <span style="color:#3d2a00">El periodo de <strong style="color:#a87a10">solicitud de integración está cerrado</strong>. Puedes consultar los proyectos disponibles.</span>
        </div>
    <?php endif; ?>

    <!--  Encabezado de resultados ─ -->
    <div class="resultados-header">
        <h6 class="resultados-titulo">
            <?= $rol === 'estudiante' ? 'Propuestas disponibles' : 'Proyectos' ?>
        </h6>
        <span class="resultados-count">
            <?= number_format($paginacion['total']) ?> resultado<?= $paginacion['total'] !== 1 ? 's' : '' ?>
        </span>
    </div>

    <!--  GRID DE PROYECTOS ─ -->
    <?php if (!empty($proyectos)): ?>
        <div class="proyectos-grid">
            <?php foreach ($proyectos as $p):
                // Clases de stripe y badge según estado
                $stripeClass = match ((int)$p['id_estadoP']) {
                    2 => 'stripe-activo',
                    5 => 'stripe-porcerrar',
                    3 => 'stripe-poraprobar',
                    1 => 'stripe-cierre',
                    6 => 'stripe-vencido',
                    default => '',
                };
                $badgeEstado   = $ctrl->badgeEstado((int)$p['id_estadoP']);
                $badgeModal    = $ctrl->badgeModalidad($p['modalidad']);
                $inscritos     = (int)($p['inscritos_actuales'] ?? ($p['cantidad_estudiante'] - ($p['lugares_disponibles'] ?? 0)));
                $total_cupo    = (int)$p['cantidad_estudiante'];
                $pct           = $total_cupo > 0 ? min(100, round($inscritos / $total_cupo * 100)) : 0;
                $cupoLleno     = ($p['lugares_disponibles'] ?? 1) <= 0;
            ?>
                <div class="card-proyecto <?= $stripeClass ?>">
                    <div class="card-stripe"></div>
                    <div class="card-body-inner">

                        <!-- Badges -->
                        <div class="card-badges">
                            <?php if (isset($p['id_estadoP'])): ?>
                                <span class="badge-estado <?= $badgeEstado ?>">
                                    <?= htmlspecialchars($p['estado_proyecto'] ?? '') ?>
                                </span>
                            <?php endif; ?>
                            <span class="badge-modalidad <?= $badgeModal ?>">
                                <?= match ($p['modalidad']) {
                                    'virtual' => 'Virtual',
                                    'fisico' => 'Presencial',
                                    'mixto' => 'Mixta',
                                    default => ucfirst($p['modalidad'])
                                } ?>
                            </span>
                        </div>

                        <!-- Título -->
                        <p class="card-titulo"><?= htmlspecialchars($p['titulo']) ?></p>

                        <!-- Meta -->
                        <ul class="card-meta">
                            <?php if (!empty($p['instituto'])): ?>
                                <li><strong>Institución:</strong> <?= htmlspecialchars($p['instituto']) ?></li>
                            <?php endif; ?>
                            <?php if (!empty($p['tematica'])): ?>
                                <li><strong>Temática:</strong> <?= htmlspecialchars($p['tematica']) ?></li>
                            <?php endif; ?>
                            <?php if (!empty($p['subtematica'])): ?>
                                <li><strong>Área:</strong> <?= htmlspecialchars($p['subtematica']) ?></li>
                            <?php endif; ?>
                            <li><strong>Investigador:</strong> <?= htmlspecialchars($p['investigador']) ?></li>
                            <li><strong>Creación:</strong> <?= htmlspecialchars($p['fecha_creacion']) ?></li>
                        </ul>

                        <!-- Barra de cupo -->
                        <div class="cupo-bar-wrap">
                            <div class="cupo-label">
                                <span>Cupo</span>
                                <span><?= $inscritos ?> / <?= $total_cupo ?> estudiantes</span>
                            </div>
                            <div class="cupo-bar">
                                <div class="cupo-bar-fill <?= $cupoLleno ? 'lleno' : '' ?>"
                                    style="width:<?= $pct ?>%"></div>
                            </div>
                        </div>

                        <!-- Estado solicitud para estudiante -->
                        <?php if ($rol === 'estudiante'): ?>
                            <?php if ((int)($p['ya_inscrito'] ?? 0)): ?>
                                <p class="mb-2" style="font-size:12.5px;color:#1a6f35;font-weight:600;">
                                    Ya estás inscrito en este proyecto
                                </p>
                            <?php elseif (($p['mi_solicitud'] ?? '') === 'pendiente'): ?>
                                <p class="mb-2" style="font-size:12.5px;color:#a87a10;font-weight:600;">
                                    Solicitud enviada — en revisión
                                </p>
                            <?php elseif (($p['mi_solicitud'] ?? '') === 'aceptado'): ?>
                                <p class="mb-2" style="font-size:12.5px;color:#1a6f35;font-weight:600;">
                                    Solicitud aceptada
                                </p>
                            <?php elseif (($p['mi_solicitud'] ?? '') === 'rechazado'): ?>
                                <p class="mb-2" style="font-size:12.5px;color:var(--tecnm-red);font-weight:600;">
                                    Solicitud rechazada
                                </p>
                            <?php endif; ?>
                        <?php endif; ?>

                    </div>

                    <!-- Botón -->
                    <div class="card-footer-inner">
                        <a href="detalles_proyecto.php?id=<?= (int)$p['id_proyectos'] ?>"
                            class="btn-ver-detalle">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 16 16">
                                <path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8M1.173 8a13 13 0 0 1 1.66-2.043C4.12 4.668 5.88 3.5 8 3.5s3.879 1.168 5.168 2.457A13 13 0 0 1 14.828 8q-.086.13-.195.288c-.335.48-.83 1.12-1.465 1.755C11.879 11.332 10.119 12.5 8 12.5s-3.879-1.168-5.168-2.457A13 13 0 0 1 1.172 8z" />
                                <path d="M8 5.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5M4.5 8a3.5 3.5 0 1 1 7 0 3.5 3.5 0 0 1-7 0" />
                            </svg>
                            Ver detalle
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!--  PAGINACIÓN  -->
        <?php if ($paginacion['total_paginas'] > 1): ?>
            <div class="paginacion-wrap">
                <ul class="pagination justify-content-center mb-1">

                    <!-- Anterior -->
                    <li class="page-item <?= $paginacion['pagina'] <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link"
                            href="?<?= $qBase ?>&pagina=<?= $paginacion['pagina'] - 1 ?>">
                            &laquo;
                        </a>
                    </li>

                    <?php
                    // Mostrar máximo 7 botones con elipsis para no desbordar en mobile
                    $total_pag = $paginacion['total_paginas'];
                    $pag_actual = $paginacion['pagina'];
                    $rango = 2; // páginas a cada lado de la actual
                    $paginas_mostrar = [];

                    for ($i = 1; $i <= $total_pag; $i++) {
                        if (
                            $i === 1 || $i === $total_pag
                            || ($i >= $pag_actual - $rango && $i <= $pag_actual + $rango)
                        ) {
                            $paginas_mostrar[] = $i;
                        }
                    }

                    $prev = null;
                    foreach ($paginas_mostrar as $i):
                        if ($prev !== null && $i - $prev > 1): ?>
                            <li class="page-item disabled"><span class="page-link">…</span></li>
                        <?php endif; ?>
                        <li class="page-item <?= $i === $pag_actual ? 'active' : '' ?>">
                            <a class="page-link" href="?<?= $qBase ?>&pagina=<?= $i ?>"><?= $i ?></a>
                        </li>
                    <?php $prev = $i;
                    endforeach; ?>

                    <!-- Siguiente -->
                    <li class="page-item <?= $paginacion['pagina'] >= $total_pag ? 'disabled' : '' ?>">
                        <a class="page-link"
                            href="?<?= $qBase ?>&pagina=<?= $paginacion['pagina'] + 1 ?>">
                            &raquo;
                        </a>
                    </li>

                </ul>

                <?php
                $inicio = ($paginacion['pagina'] - 1) * $paginacion['por_pagina'] + 1;
                $fin    = min($inicio + $paginacion['por_pagina'] - 1, $paginacion['total']);
                ?>
                <p class="paginacion-info">
                    Mostrando <?= $inicio ?>–<?= $fin ?> de <?= number_format($paginacion['total']) ?> proyectos
                </p>
            </div>
        <?php endif; ?>

    <?php else: ?>
        <!-- Estado vacío -->
        <div class="vacio-wrap">
            <svg xmlns="http://www.w3.org/2000/svg" width="52" height="52" fill="currentColor" viewBox="0 0 16 16">
                <path d="M2 2a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2zm2 0v12h8V2z" />
                <path d="M4 6h8v1H4zm0 2.5h8v1H4zm0 2.5h5v1H4z" />
            </svg>
            <p class="fw-semibold">No se encontraron proyectos con los criterios seleccionados.</p>
            <a href="?" style="font-size:13px;color:var(--tecnm-blue);">Limpiar filtros</a>
        </div>
    <?php endif; ?>

</div>

<!-- 
     JS: subtémáticas dependientes de temática (fetch AJAX)
      -->
<script>
    (function() {
        const selectTematica = document.getElementById('id_tematica');
        const selectSubtematica = document.getElementById('id_subtematica');
        const valorActualSubt = <?= (int)$filtros['id_subtematica'] ?>;

        if (!selectTematica || !selectSubtematica) return;

        function cargarSubtematicas(id_tematica, seleccionado = 0) {
            const url = '?action=subtematicas&id_tematica=' + encodeURIComponent(id_tematica);
            fetch(url)
                .then(r => r.json())
                .then(data => {
                    selectSubtematica.innerHTML = '<option value="0">Todas</option>';
                    data.forEach(s => {
                        const opt = document.createElement('option');
                        opt.value = s.id_subtematica;
                        opt.textContent = s.nombre_subtematica;
                        if (parseInt(s.id_subtematica) === seleccionado) opt.selected = true;
                        selectSubtematica.appendChild(opt);
                    });
                })
                .catch(() => {
                    selectSubtematica.innerHTML = '<option value="0">Todas</option>';
                });
        }

        // Al cambiar la temática, recargar subtémáticas
        selectTematica.addEventListener('change', function() {
            cargarSubtematicas(this.value, 0);
        });

        // Al cargar la página con filtro activo, mantener la subtémática seleccionada
        const tematicaInicial = selectTematica.value;
        if (parseInt(tematicaInicial) > 0) {
            cargarSubtematicas(tematicaInicial, valorActualSubt);
        }
    })();
</script>

<?php
$page_content = ob_get_clean();
$titulo       = 'Propuestas de Investigación';
$contenido    = $page_content;
include __DIR__ . '/../../layout.php';
?>