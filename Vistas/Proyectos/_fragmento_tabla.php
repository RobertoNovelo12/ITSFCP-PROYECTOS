<div id="tabla-wrap">
<?php if (!empty($proyectos)): ?>

    <!-- ESCRITORIO -->
    <div class="card shadow-sm d-none d-md-block">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <?php foreach ($encabezados as $encabezado): ?>
                                <th><?= htmlspecialchars($encabezado) ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($proyectos as $proyecto): ?>
                            <tr>
                                <th><?= $proyecto['id_proyectos'] ?></th>

                                <td title="<?= htmlspecialchars($proyecto['titulo']) ?>">
                                    <?= strlen($proyecto['titulo']) > 60
                                        ? substr($proyecto['titulo'], 0, 60) . '...'
                                        : htmlspecialchars($proyecto['titulo']); ?>
                                </td>

                                <td><?= $proyecto['fecha_inicio'] ?></td>
                                <td><?= $proyecto['fecha_fin'] ?></td>

                                <td>
                                    <span class="badge text-bg-<?= $proyectoControlador->EstiloEstado($proyecto['estado_proyecto']) ?>">
                                        <?= htmlspecialchars($proyecto['estado_proyecto']) ?>
                                    </span>
                                </td>

                                <?php if ($rol === 'estudiante'): ?>
                                    <td>
                                        <?php
                                        $estadoEst = $proyecto['estado_estudiante'] ?? 'sin_asignar';
                                        $clase = ($estadoEst == 'baja') ? 'danger'
                                            : (($estadoEst == 'concluido') ? 'success' : 'primary');
                                        ?>
                                        <span class="badge text-bg-<?= $clase ?>">
                                            <?= strtoupper($estadoEst) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="../Seguimiento/index.php?id_proyectos=<?= $proyecto['id_proyectos'] ?>"
                                            class="btn btn-sm btn-primary">
                                            <i class="bi bi-folder2-open"></i>
                                        </a>
                                    </td>
                                <?php endif; ?>

                                <td><?= $proyecto['periodo'] ?></td>
                                <td><?= $proyecto['total'] ?></td>

                                <td>
                                    <?= $proyectoControlador->botonesAccion(
                                        $proyecto['id_proyectos'],
                                        $rol,
                                        $proyecto['estado_proyecto'],
                                        $id_usuario,
                                        $proyecto['puede_cerrar'] ?? 0,
                                        $proyecto['estado_estudiante'] ?? null,
                                        $puedeCrear_Editar
                                    ); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- MÓVIL -->
    <div class="d-block d-md-none mt-4">
        <?php foreach ($proyectos as $proyecto): ?>
            <div class="card mb-3 shadow-sm">
                <div class="card-body">
                    <h5>ID: <?= $proyecto['id_proyectos'] ?></h5>
                    <p><strong>Título:</strong> <?= htmlspecialchars($proyecto['titulo']) ?></p>
                    <p><strong>Fecha inicio:</strong> <?= $proyecto['fecha_inicio'] ?></p>
                    <p><strong>Fecha final:</strong> <?= $proyecto['fecha_fin'] ?></p>
                    <p>
                        <strong>Estado proyecto:</strong>
                        <span class="badge text-bg-<?= $proyectoControlador->EstiloEstado($proyecto['estado_proyecto']) ?>">
                            <?= htmlspecialchars($proyecto['estado_proyecto']) ?>
                        </span>
                    </p>

                    <?php if ($rol === 'estudiante'): ?>
                        <p>
                            <strong>Estado estudiante:</strong>
                            <?php
                            $estadoEst = $proyecto['estado_estudiante'] ?? 'activo';
                            $clase = ($estadoEst == 'baja') ? 'danger'
                                : (($estadoEst == 'concluido') ? 'success' : 'primary');
                            ?>
                            <span class="badge text-bg-<?= $clase ?>">
                                <?= strtoupper($estadoEst) ?>
                            </span>
                        </p>
                    <?php endif; ?>

                    <p><strong>Periodo:</strong> <?= $proyecto['periodo'] ?></p>
                    <p><strong>Pendientes:</strong> <?= $proyecto['total'] ?></p>

                    <div class="d-flex gap-2 flex-wrap">
                        <?= $proyectoControlador->botonesAccion(
                            $proyecto['id_proyectos'],
                            $rol,
                            $proyecto['estado_proyecto'],
                            null,
                            $proyecto['puede_cerrar'] ?? 0,
                            $proyecto['estado_estudiante'] ?? null,
                            $puedeCrear_Editar
                        ); ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- PAGINACIÓN -->
    <?php
    $qBase  = 'action=' . urlencode($action)
            . (!empty($buscar) ? '&buscar=' . urlencode($buscar) : '');
    $entidad = 'proyectos';
    include __DIR__ . '../../../publico/incluido/_paginacion.php';
    ?>

<?php else: ?>
    <div class="alert alert-info text-center">
        No hay proyectos para mostrar
    </div>
<?php endif; ?>
</div>