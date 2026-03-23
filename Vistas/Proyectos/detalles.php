<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

if (!isset($_SESSION['id_usuario'])) {
    header("Location: /ITSFCP-PROYECTOS/index.php");
    exit;
}

$rol = $_SESSION['rol'];
$id = $_SESSION['id_usuario'];
$id_proyecto = $_GET["id_proyectos"];

require_once '..\..\Controladores\proyectoControlador.php';

$proyectoControlador = new ProyectoControlador();

$proyectos = $proyectoControlador->datosproyecto($id_proyecto);
$investigador = $proyectoControlador->datosinvestigador($id_proyecto);

if ($rol == "investigador" || $rol == "profesor" || $rol == "supervisor") {
    $estudiantes = $proyectoControlador->datosestudiantes($id_proyecto);
}

ob_start();
?>

<div class="container-fluid py-4">

    <div class="row mb-3">

        <div class="col-6">
            <h3>Detalles del Proyecto</h3>
        </div>

        <div class="col-6 text-end">
            <a href="tabla.php" class="btn btn-danger">Regresar</a>
        </div>

    </div>


    <?php foreach ($proyectos as $proyecto): ?>

        <!-- INFORMACIÓN DEL PROYECTO -->

        <div class="card mb-4 shadow-sm">

            <div class="card-header">
                <b>Información del proyecto</b>
            </div>

            <div class="card-body">

                <h5><?= htmlspecialchars($proyecto['titulo']) ?></h5>

                <p class="text-muted">
                    <?= nl2br(htmlspecialchars($proyecto['descripcion'])) ?>
                </p>

                <hr>

                <div class="row">

                    <div class="col-md-6">

                        <dl>

                            <dt>Objetivos</dt>
                            <dd><?= nl2br(htmlspecialchars($proyecto['objetivo'])) ?></dd>

                            <dt>Pre-requisitos</dt>
                            <dd><?= nl2br(htmlspecialchars($proyecto['pre_requisitos'])) ?></dd>

                            <dt>Requisitos</dt>
                            <dd><?= nl2br(htmlspecialchars($proyecto['requisitos'])) ?></dd>

                        </dl>

                    </div>

                    <div class="col-md-6">

                        <dl>

                            <dt>Cantidad alumnos</dt>
                            <dd><?= $proyecto['cantidad_estudiante'] ?></dd>

                            <dt>Temática</dt>
                            <dd><?= $proyecto['tematica'] ?></dd>

                            <dt>Modalidad</dt>
                            <dd><?= $proyecto['modalidad'] ?></dd>

                            <dt>Presupuesto</dt>
                            <dd>$<?= number_format($proyecto['presupuesto'], 2) ?></dd>

                            <dt>Periodo</dt>
                            <dd><?= $proyecto['periodo'] ?> - <?= $proyecto['estado_periodo'] ?></dd>

                            <dt>Fecha inicio</dt>
                            <dd><?= $proyecto['fecha_inicio'] ?></dd>

                            <dt>Fecha final</dt>
                            <dd><?= $proyecto['fecha_fin'] ?></dd>

                            <dt>Estado</dt>
                            <dd>
                                <span class="badge bg-success">
                                    <?= $proyecto['estado_proyecto'] ?>
                                </span>
                            </dd>

                            <dt>Fecha creación</dt>
                            <dd><?= $proyecto['creado_en'] ?></dd>

                        </dl>

                    </div>

                </div>

                <hr>

                <b>Subtemáticas</b>

                <div class="mt-2">

                    <?php
                    $subs = explode(",", $proyecto['subtematicas']);
                    foreach ($subs as $sub) {
                        echo "<span class='badge bg-primary me-2 mb-2'>" . trim($sub) . "</span>";
                    }
                    ?>

                </div>

            </div>

        </div>

    <?php endforeach; ?>


    <!-- INVESTIGADOR -->

    <div class="card mb-4 shadow-sm">

        <div class="card-header">
            <b>Investigador</b>
        </div>

        <div class="card-body">

            <?php foreach ($investigador as $invest): ?>

                <div class="row">

                    <div class="col-md-6">

                        <dl>

                            <dt>Nombre completo</dt>
                            <dd>
                                <?= $invest['investigador']['nombre'] . " " . $invest['investigador']['apellido_paterno'] . " " . $invest['investigador']['apellido_materno'] ?>
                            </dd>

                            <dt>Área conocimiento</dt>
                            <dd><?= $invest['area']['area_conocimiento'] ?></dd>

                            <dt>Subárea</dt>
                            <dd><?= $invest['investigador']['subarea'] ?></dd>

                        </dl>

                    </div>

                    <div class="col-md-6">

                        <dl>

                            <dt>Nivel SNI</dt>
                            <dd><?= $invest['investigador']['nivel_sni'] ?></dd>

                            <dt>Grado académico</dt>
                            <dd><?= $invest['investigador']['grado_academico'] ?></dd>

                            <dt>Línea investigación</dt>
                            <dd><?= $invest['lineas']['linea_investigacion'] ?></dd>

                        </dl>

                    </div>

                </div>

            <?php endforeach; ?>

        </div>

    </div>



    <?php if ($rol == "supervisor" || $rol == "profesor" || $rol == "investigador"): ?>

        <h5 class="mb-3">Estudiantes involucrados</h5>

        <!-- TABLA (LAPTOP) -->

        <div class="table-responsive d-none d-md-block">

            <table class="table table-striped text-center">

                <thead>

                    <tr>
                        <th>ID</th>
                        <th>Nombre</th>
                        <th>Carrera</th>
                        <th>Área</th>
                        <th>Subárea</th>
                    </tr>

                </thead>

                <tbody>

                    <?php foreach ($estudiantes as $alumno): ?>

                        <tr>

                            <td><?= $alumno['estudiante']['id_usuarios'] ?></td>

                            <td>
                                <?= $alumno['estudiante']['nombre'] . " " . $alumno['estudiante']['apellido_paterno'] . " " . $alumno['estudiante']['apellido_materno'] ?>
                            </td>

                            <td><?= $alumno['estudiante']['carrera'] ?></td>

                            <td><?= $alumno['area']['area'] ?></td>

                            <td><?= $alumno['area']['subarea'] ?></td>

                        </tr>

                    <?php endforeach; ?>

                </tbody>

            </table>

        </div>


        <!-- TARJETAS (MÓVIL) -->

        <div class="d-md-none">

            <?php foreach ($estudiantes as $alumno): ?>

                <div class="card mb-3">

                    <div class="card-body">

                        <h5 class="card-title">
                            <?= $alumno['nombre'] . " " . $alumno['apellido_paterno'] ?>
                        </h5>

                        <ul class="list-group list-group-flush">

                            <li class="list-group-item">
                                <b>ID:</b> <?= $alumno['id_usuarios'] ?>
                            </li>

                            <li class="list-group-item">
                                <b>Carrera:</b> <?= $alumno['carrera'] ?>
                            </li>

                            <li class="list-group-item">
                                <b>Área:</b> <?= $alumno['area'] ?>
                            </li>

                            <li class="list-group-item">
                                <b>Subárea:</b> <?= $alumno['subarea'] ?>
                            </li>

                        </ul>

                    </div>

                </div>

            <?php endforeach; ?>

        </div>

    <?php endif; ?>

</div>

<?php
$contenido = ob_get_clean();
$titulo = "Detalles de proyecto";
$bodyClass = "proyectos-page";

include __DIR__ . '/../../layout.php';
?>