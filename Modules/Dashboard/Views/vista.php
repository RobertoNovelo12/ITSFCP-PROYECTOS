<?php
// Extraer variables para uso cómodo en la vista
$proyectos       = $datos['proyectos'];
$primer_proyecto = $datos['primer_proyecto'];
$porcentaje      = $datos['porcentaje'];
$tareas          = $datos['tareas'];
$modificaciones  = $datos['modificaciones'];
$rol             = $datos['rol'];
$mostrar_btn     = $datos['mostrar_btn'];
?>

<div class="container-fluid py-4">

    <!-- Encabezado -->
    <div class="row mb-4 align-items-center">
        <div class="col-md-6">
            <h2 class="mb-0">Hola, <?= htmlspecialchars($nombre_user) ?>! 👋</h2>
        </div>
        <div class="col-md-6 text-md-end">
            <?php if ($mostrar_btn): ?>
                <button class="btn btn-primary">
                    <i class="bi bi-plus-lg"></i> Nuevo proyecto
                </button>
            <?php endif; ?>
        </div>
    </div>

    <div class="row">
        <!-- Columna izquierda -->
        <div class="col-lg-6 mb-4">

            <!-- Progreso -->
            <div class="card card-progreso shadow-sm mb-4">
                <div class="card-body p-4">
                    <?php if ($primer_proyecto): ?>
                        <div class="row align-items-center">
                            <div class="col-md-5">
                                <h5 class="mb-4 fw-bold">Progreso</h5>
                                <div class="progress-circle">
                                    <svg width="180" height="180">
                                        <circle class="progress-circle-bg" cx="90" cy="90" r="70"></circle>
                                        <circle class="progress-circle-bar" cx="90" cy="90" r="70"
                                            style="stroke-dasharray:440;stroke-dashoffset:<?= 440 - 440 * $porcentaje / 100 ?>">
                                        </circle>
                                    </svg>
                                    <div class="progress-text"><?= $porcentaje ?>%</div>
                                </div>
                            </div>
                            <div class="col-md-7">
                                <h5 class="fw-bold mb-1"><?= htmlspecialchars($primer_proyecto['titulo']) ?></h5>
                                <p class="mb-3"><?= htmlspecialchars($primer_proyecto['descripcion']) ?></p>
                            </div>
                        </div>
                    <?php else: ?>
                        <h5 class="mb-4 fw-bold">Progreso</h5>
                        <p>Aún no tienes proyectos asignados.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Tareas (solo si no es supervisor) -->
            <?php if ($rol !== 'supervisor'): ?>
            <div class="card shadow-sm">
                <div class="card-body p-4">
                    <h5 class="mb-4 fw-bold">Tareas asignadas</h5>
                    <?php if (empty($tareas)): ?>
                        <div>En este espacio encontrarás tus tareas asignadas.</div>
                    <?php else: ?>
                        <?php foreach ($tareas as $tarea): ?>
                            <?php
                            $fecha_entrega = $tarea['fecha_entrega']
                                ? date('d/m/Y', strtotime($tarea['fecha_entrega']))
                                : 'Sin fecha';
                            $checked   = $tarea['estado'] === 'Aprobado' ? 'checked' : '';
                            $completed = $tarea['estado'] === 'Aprobado' ? 'task-completed' : '';
                            ?>
                            <div class="task-item">
                                <div class="d-flex align-items-center">
                                    <input type="checkbox" class="task-checkbox me-3" <?= $checked ?>>
                                    <div class="flex-grow-1">
                                        <span class="<?= $completed ?>">
                                            <?= htmlspecialchars($tarea['nombre_tarea']) ?>
                                        </span>
                                    </div>
                                    <span class="badge-date"><?= $fecha_entrega ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

        </div>

        <!-- Columna derecha -->
        <div class="col-lg-6">

            <!-- Proyectos -->
            <div class="card shadow-sm mb-4">
                <div class="card-body p-4">
                    <h5 class="mb-3 fw-bold">Proyectos</h5>
                    <?php if (empty($proyectos)): ?>
                        <div>En este espacio encontrarás tus proyectos.</div>
                    <?php else: ?>
                        <?php foreach ($proyectos as $proyecto): ?>
                            <a href="/Modules/Proyectos/Views/detalles.php?id_proyectos=<?= $proyecto['id_proyectos'] ?>"
                               class="proyecto-link">
                                <div class="proyecto-item d-flex justify-content-between align-items-center mb-3">
                                    <div>
                                        <span class="icon-proyecto">📁</span>
                                        <span class="proyecto-nombre">
                                            <?= htmlspecialchars($proyecto['titulo']) ?>
                                        </span>
                                    </div>
                                    <span class="estado-proyecto">
                                        <?= htmlspecialchars($proyecto['estado']) ?>
                                    </span>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Modificaciones -->
            <div class="card shadow-sm">
                <div class="card-body p-4">
                    <h5 class="mb-4 fw-bold">Últimas modificaciones</h5>
                    <?php if (empty($modificaciones)): ?>
                        <div>En este espacio encontrarás las últimas modificaciones de tus tareas.</div>
                    <?php else: ?>
                        <?php foreach ($modificaciones as $mod): ?>
                            <?php
                            $contenido = $mod['contenido'];
                            if (!empty($contenido)) {
                                $desc_raw = json_decode($contenido, true);
                                $desc = htmlspecialchars(substr($desc_raw['descripcion'] ?? '', 0, 50));
                            } else {
                                $desc = "Sin contenido";
                            }
                            $inicial      = strtoupper(substr($mod['nombre'], 0, 1));
                            $avatar_class = strtolower($mod['rol']) === 'estudiante' ? 'avatar-u' : 'avatar-e';
                            ?>
                            <div class="modificacion-item">
                                <div class="d-flex align-items-center">
                                    <div class="avatar-dash <?= $avatar_class ?> me-3"><?= $inicial ?></div>
                                    <div class="flex-grow-1">
                                        <div class="fw-semibold"><?= $desc ?></div>
                                    </div>
                                    <span class="small">
                                        <?= date('d/m/Y', strtotime($mod['fecha'])) ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>
</div>