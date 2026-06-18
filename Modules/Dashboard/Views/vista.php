<?php
// Extraer variables para uso cómodo en la vista
$proyectos       = $datos['proyectos'];
$primer_proyecto = $datos['primer_proyecto'];
$porcentaje      = $datos['porcentaje'];
$tareas          = $datos['tareas'];
$modificaciones  = $datos['modificaciones'];
$rol             = $datos['rol'];
?>

<div class="container-fluid py-4">

    <!-- Encabezado -->
    <div class="row mb-4 align-items-center">
        <div class="col-md-6">
            <h2 class="mb-0">Hola, <?= htmlspecialchars($nombre_user) ?>! 👋</h2>
        </div>
        <div class="col-md-6 text-md-end">
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
                                        <circle class="progress-circle-bar" id="circleProgress" cx="90" cy="90" r="70"
                                            style="stroke-dasharray:440;stroke-dashoffset:<?= 440 - 440 * $porcentaje / 100 ?>">
                                        </circle>
                                    </svg>
                                    <div class="progress-text" id="porcentajeTexto"><?= $porcentaje ?>%</div>
                                </div>
                            </div>
                            <div class="col-md-7">
                                <h5 class="fw-bold mb-1" id="tituloProyecto"><?= htmlspecialchars($primer_proyecto['titulo']) ?></h5>
                                <p class="mb-3" id="descripcionProyecto"><?= htmlspecialchars($primer_proyecto['descripcion']) ?></p>
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
                        <h5 class="mb-4 fw-bold">Tareas del proyecto seleccionado</h5>
                        <?php if (empty($tareas)): ?>
                            <div>En este espacio encontrarás tus tareas asignadas.</div>
                        <?php else: ?>
                            <div id="contenedorTareas">
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
                            </div>
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

                        <div id="listaProyectos">

                            <?php foreach ($proyectos as $proyecto): ?>

                                <div
                                    class="proyecto-item <?= $proyecto['id_proyectos'] == $primer_proyecto['id_proyectos'] ? 'activo' : '' ?>"
                                    data-id="<?= $proyecto['id_proyectos'] ?>">

                                    <div class="titulo-proyecto">
                                        <?= htmlspecialchars($proyecto['titulo']) ?>
                                    </div>

                                    <small class="estado-proyecto">
                                        <?= htmlspecialchars($proyecto['estado']) ?>
                                    </small>

                                </div>

                            <?php endforeach; ?>

                        </div>

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
                        <div id="contenedorMods">
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
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>
</div>
<script>
    document.querySelectorAll('.proyecto-item')
        .forEach(item => {

            item.addEventListener('click', async function() {

                document
                    .querySelectorAll('.proyecto-item')
                    .forEach(p => p.classList.remove('activo'));

                this.classList.add('activo');

                const idProyecto = this.dataset.id;

                const response =
                    await fetch(
                        '../ajax_dashboard.php?id=' + idProyecto
                    );

                const data = await response.json();

                document.getElementById(
                        'tituloProyecto'
                    ).textContent =
                    data.proyecto.titulo;

                document.getElementById(
                        'descripcionProyecto'
                    ).textContent =
                    data.proyecto.descripcion;

                document.getElementById(
                        'nombreProyectoTareas'
                    ).textContent =
                    data.proyecto.titulo;

                document.getElementById(
                        'porcentajeTexto'
                    ).textContent =
                    data.porcentaje + '%';

                document.getElementById(
                        'circleProgress'
                    ).style.strokeDashoffset =
                    440 - (440 * data.porcentaje / 100);

                actualizarTareas(data.tareas);
                actualizarMods(data.modificaciones);

            });

        });

    function actualizarTareas(tareas) {
        let html = '';

        tareas.forEach(t => {

            const checked =
                t.estado === 'Aprobado' ?
                'checked' :
                '';

            html += `
            <div class="task-item">
                <div class="d-flex align-items-center">

                    <input
                        type="checkbox"
                        class="task-checkbox me-3"
                        ${checked}>

                    <div class="flex-grow-1">
                        ${t.nombre_tarea}
                    </div>

                    <span class="badge-date">
                        ${t.fecha_entrega}
                    </span>

                </div>
            </div>
        `;
        });

        document.getElementById(
            'contenedorTareas'
        ).innerHTML = html;
    }

    function actualizarMods(mods) {
        let html = '';

        mods.forEach(m => {

            html += `
            <div class="modificacion-item">

                <div class="d-flex align-items-center">

                    <div class="flex-grow-1">
                        ${m.nombre}
                    </div>

                    <span class="small">
                        ${m.fecha}
                    </span>

                </div>

            </div>
        `;
        });

        document.getElementById(
            'contenedorMods'
        ).innerHTML = html;
    }
</script>