<?php

/**
 * tareas_estudiante.php
 * Vista: Mis Actividades — estudiante
 * Diseño: minimalista inspirado en Classroom · paleta TecNM
 *
 * Estados (tabla estados_tarea):
 *  1=Pendiente  2=Revisar  3=Corregir  4=Sin activar
 *  5=Aprobado   6=Vencido  7=Entregado 8=Borrador  9=Concluido
 */

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

if (!isset($_SESSION['id_usuario'])) {
    header("Location: /index.php");
    exit;
}

$rol          = strtolower($_SESSION['rol'] ?? '');
$id_usuario   = $_SESSION['id_usuario'];

include __DIR__ .  '../../../publico/incluido/_validar_tareas.php';

$id_proyectos = $_GET['id_proyectos'] ?? $_POST['id_proyectos'] ?? null;

//Validación de argumentos en url
$id_validar = $id_proyectos;
include __DIR__ .  '../../../publico/incluido/_validar_id.php';

require_once __DIR__ . '/../../Controladores/tareasControlador.php';
$tareaControlador = new TareaControlador();

$tareas          = $tareaControlador->listarTareasEstudiante($id_usuario, $id_proyectos);
$tareasActivas   = array_filter($tareas, fn($t) => (int)$t['id_estadoT'] !== 4);
$tareasInactivas = array_filter($tareas, fn($t) => (int)$t['id_estadoT'] === 4);

//  Helpers ─

/**
 * Convierte id_estadoT al color Bootstrap del badge.
 * Sigue la misma lógica que EstiloEstado() del módulo de proyectos.
 */
function badgeEstado(int $id): string
{
    return match ($id) {
        1       => 'primary',    // Pendiente
        2       => 'warning',    // Revisar
        3       => 'danger',     // Corregir
        5       => 'success',    // Aprobado
        6       => 'dark',       // Vencido
        7       => 'info',       // Entregado
        8       => 'secondary',  // Borrador
        9       => 'success',    // Concluido
        default => 'secondary',
    };
}

/** Texto legible del estado */
function labelEstado(int $id, string $texto): string
{
    return match ($id) {
        2 => 'En revisión',
        default => $texto ?: '—',
    };
}

/** Ícono Bootstrap Icons según tipo de tarea */
function iconoTipo(string $tipo): string
{
    return match (strtolower(trim($tipo))) {
        'resumen'                                  => 'bi-file-text',
        'introduccion', 'introducción'             => 'bi-book',
        'planteamiento del problema'               => 'bi-question-circle',
        'justificacion', 'justificación'           => 'bi-lightbulb',
        'objetivos'                                => 'bi-bullseye',
        'marco teorico', 'marco teórico',
        'marco teórico y/o de referencia'          => 'bi-journal-bookmark',
        'metodologia', 'metodología'               => 'bi-diagram-3',
        'metas, productos esperados e impactos',
        'metas, productos esperados e impacto'     => 'bi-graph-up-arrow',
        'cronograma y recursos', 'cronograma'      => 'bi-calendar-range',
        'referencias bibliograficas', 'bibliografía' => 'bi-bookmark-star',
        'anexos'                                   => 'bi-paperclip',
        'reporte final'                            => 'bi-file-earmark-check',
        default                                    => 'bi-file-earmark-text',
    };
}

/** Botón de acción según estado: texto, ícono, estilo */
function btnAccion(int $id): array
{
    return match ($id) {
        1, 8    => ['Ver / Entregar',     'bi-upload',        'btn-primary'],
        3       => ['Corregir y enviar',  'bi-pencil-square', 'btn-danger'],
        2, 7    => ['Ver entrega',        'bi-eye',           'btn-outline-secondary'],
        5, 9    => ['Ver actividad',      'bi-eye',           'btn-outline-secondary'],
        6       => ['Ver detalle',        'bi-eye',           'btn-outline-secondary'],
        default => ['Ver / Entregar',     'bi-upload',        'btn-primary'],
    };
}

ob_start();
?>


<div class="container-fluid py-4 ancho_container">
    <!--  Encabezado  -->
    <div class="row mb-4 align-items-center">

        <?php
        $titulo      = 'Mis Actividades';
        $descripcion = 'Actividades asignadas del proyecto';
        include __DIR__ . '../../../publico/incluido/_encabezado.php';
        ?>

        <div class="col-6 col-md-6 text-md-end">
            <a href="../../Vistas/Proyectos/index.php" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i>Regresar
            </a>
        </div>
    </div>
    <div class="act-page">

        <?php if (empty($tareasActivas) && empty($tareasInactivas)): ?>

            <div class="act-empty">
                <i class="bi bi-inbox"></i>
                No tienes actividades asignadas por el momento.
            </div>

        <?php else: ?>

            <!--  Lista de actividades ═ -->
            <?php if (!empty($tareasActivas)): ?>
                <div class="act-list">
                    <?php foreach ($tareasActivas as $idx => $tarea):
                        $idEstado    = (int)$tarea['id_estadoT'];
                        $bsColor     = badgeEstado($idEstado);
                        $estadoLabel = labelEstado($idEstado, $tarea['estado_texto'] ?? '');
                        $iconTipo    = iconoTipo($tarea['tipo'] ?? '');
                        $isVencido   = $idEstado === 6;
                        [$btnTxt, $btnIco, $btnStyle] = btnAccion($idEstado);
                        $bodyId      = 'act-body-' . $idx;
                    ?>
                        <div class="act-item">

                            <!-- Trigger -->
                            <button
                                class="act-trigger"
                                type="button"
                                aria-expanded="false"
                                aria-controls="<?= $bodyId ?>"
                                onclick="toggleAct(this)">
                                <!-- Ícono tipo -->
                                <div class="act-icon" aria-hidden="true">
                                    <i class="bi <?= $iconTipo ?>"></i>
                                </div>

                                <!-- Texto -->
                                <div class="act-trigger-content">
                                    <div class="act-trigger-row1">
                                        <span class="act-tipo"><?= htmlspecialchars($tarea['tipo'] ?? '—') ?></span>
                                        <span class="badge text-bg-<?= $bsColor ?>">
                                            <?= htmlspecialchars($estadoLabel) ?>
                                        </span>
                                    </div>
                                    <div class="act-trigger-row2">
                                        <?php if (!empty($tarea['fecha_entrega'])): ?>
                                            <span class="act-meta <?= $isVencido ? 'vencida' : '' ?>">
                                                <i class="bi bi-calendar3"></i>
                                                Entrega:&nbsp;<strong><?= date('d/m/Y', strtotime($tarea['fecha_entrega'])) ?></strong>
                                            </span>
                                        <?php else: ?>
                                            <span class="act-meta">
                                                <i class="bi bi-calendar-x"></i> Sin fecha
                                            </span>
                                        <?php endif; ?>
                                        <?php if (!empty($tarea['fecha_modificacion'])): ?>
                                            <span class="act-meta-edit">
                                                <i class="bi bi-pencil-fill" style="font-size:.6rem;"></i>
                                                Act.&nbsp;<?= date('d/m/Y', strtotime($tarea['fecha_modificacion'])) ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <i class="bi bi-chevron-down act-chevron" aria-hidden="true"></i>
                            </button>

                            <!-- Cuerpo -->
                            <div class="act-body" id="<?= $bodyId ?>">

                                <!-- Instrucciones -->
                                <?php if (!empty($tarea['instrucciones'])): ?>
                                    <div class="act-instrucciones">
                                        <?= $tarea['instrucciones'] ?>
                                    </div>
                                <?php else: ?>
                                    <div class="act-instrucciones muted">
                                        El investigador no ha agregado instrucciones adicionales.
                                    </div>
                                <?php endif; ?>

                                <!-- Archivo guía -->
                                <?php if (!empty($tarea['archivo_nombre'])): ?>
                                    <a href="descargar_guia.php?id_tarea=<?= (int)$tarea['id_tarea'] ?>"
                                        class="act-guia" target="_blank">
                                        <i class="bi bi-file-earmark-arrow-down"></i>
                                        <?= htmlspecialchars($tarea['archivo_nombre']) ?>
                                    </a>
                                <?php endif; ?>

                                <!-- Footer -->
                                <div class="act-footer">
                                    <span class="act-fecha <?= $isVencido ? 'vencida' : '' ?>">
                                        <?php if (!empty($tarea['fecha_entrega'])): ?>
                                            <i class="bi bi-calendar-event"></i>
                                            Fecha de entrega:&nbsp;
                                            <strong><?= date('d \d\e F \d\e Y', strtotime($tarea['fecha_entrega'])) ?></strong>
                                            <?php if ($isVencido): ?>
                                                &nbsp;<span class="text-danger" style="font-size:.72rem;">— Vencida</span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <i class="bi bi-dash"></i> Sin fecha definida
                                        <?php endif; ?>
                                    </span>
                                    <a href="tarea.php?id_asignacion=<?= (int)$tarea['id_asignacion'] ?>&id_tarea=<?= (int)$tarea['id_tarea'] ?>&id_proyectos=<?= (int)$tarea['id_proyectos'] ?>"
                                        class="btn btn-sm <?= $btnStyle ?>">
                                        <i class="bi <?= $btnIco ?> me-1"></i><?= $btnTxt ?>
                                    </a>
                                </div>

                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!--  Sin activar  -->
            <?php if (!empty($tareasInactivas)): ?>
                <button
                    class="act-inactivas-toggle"
                    type="button"
                    onclick="toggleInactivas(this)"
                    aria-expanded="false">
                    <i class="bi bi-chevron-right me-1"></i>
                    Aún no disponibles (<?= count($tareasInactivas) ?>)
                </button>
                <div class="act-inactivas-list" id="act-inactivas-list">
                    <?php foreach ($tareasInactivas as $tarea): ?>
                        <div class="act-inactiva-row">
                            <div>
                                <p class="act-inactiva-tipo"><?= htmlspecialchars($tarea['tipo'] ?? '—') ?></p>
                                <p class="act-inactiva-nota">Aún no habilitada por el investigador.</p>
                            </div>
                            <span class="badge text-bg-secondary">Sin activar</span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        <?php endif; ?>
    </div>
</div>
<script>
    function toggleAct(btn) {
        const open = btn.getAttribute('aria-expanded') === 'true';
        const bodyId = btn.getAttribute('aria-controls');

        // Cerrar el anterior (modo acordeón — eliminar este bloque para multi-apertura)
        document.querySelectorAll('.act-trigger[aria-expanded="true"]').forEach(b => {
            if (b !== btn) {
                b.setAttribute('aria-expanded', 'false');
                b.classList.remove('is-open');
                const id = b.getAttribute('aria-controls');
                if (id) document.getElementById(id)?.classList.remove('is-open');
            }
        });

        btn.setAttribute('aria-expanded', String(!open));
        btn.classList.toggle('is-open', !open);
        document.getElementById(bodyId)?.classList.toggle('is-open', !open);
    }

    function toggleInactivas(btn) {
        const list = document.getElementById('act-inactivas-list');
        const open = list.classList.toggle('open');
        btn.classList.toggle('open', open);
        btn.setAttribute('aria-expanded', String(open));
    }
</script>

<?php
$contenido = ob_get_clean();
$titulo    = 'Mis Actividades';
$bodyClass = 'proyectos-page';
include __DIR__ . '/../../layout.php';
?>