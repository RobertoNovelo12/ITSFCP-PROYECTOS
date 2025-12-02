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

$action = $_POST['action'] ?? null;

//Se llama al controlador

require_once '..\..\Controladores\proyectoControlador.php';

$proyectoControlador = new ProyectoControlador();

$proyectos = $proyectoControlador->datosproyecto($id_proyecto);
$investigador = $proyectoControlador->datosinvestigador($id_proyecto);

if ($rol == "investigador" || $rol == "profesor" || $rol == "supervisor") {
    $estudiantes = $proyectoControlador->datosestudiantes($id_proyecto);
}
// ======================
// GENERAR CONTENIDO
// ======================
ob_start();
?>
<div class="container-fluid py-4">
    <div class="row mb-3 align-items-center">
        <div class="row mb-1">
            <div class="col-6">
                <h3>Detalles del Proyecto</h3>
            </div>
            <div class="col-6 text-end">
                <a href="tabla.php" class="btn btn-danger">Regresar</a>
            </div>
            <div class="row mb-1">
                <h5>Información de proyectos</h5>
                <?php foreach ($proyectos as $proyecto): ?>
                    <div class="mb-3">
                        <label for="exampleFormControlInput1" class="form-label">Nombre del proyecto</label>
                        <input type="text" disabled class="form-control" id="InputFormLimpiar1" value="<?php echo $proyecto['titulo']; ?>">
                    </div>
                    <div class="mb-3">
                        <label for="exampleFormControlTextarea1" class="form-label">Descripcion breve</label>
                        <textarea class="form-control" disabled id="InputFormLimpiar2" rows="3"><?php echo $proyecto['descripcion']; ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="exampleFormControlTextarea1" class="form-label">Objetivos</label>
                        <textarea class="form-control" disabled id="InputFormLimpiar3" rows="3"><?php echo $proyecto['objetivo']; ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="exampleFormControlTextarea1" class="form-label">Pre-requisitos</label>
                        <textarea class="form-control" disabled id="InputFormLimpiar4" rows="3"><?php echo $proyecto['pre_requisitos']; ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="exampleFormControlTextarea1" class="form-label">Requisitos</label>
                        <textarea class="form-control" disabled id="InputFormLimpiar5" rows="3"><?php echo $proyecto['requisitos']; ?></textarea>
                    </div>
            </div>
            <div class="row mb-1">
                <div class="col-md">
                    <div class="mb-3">
                        <label for="exampleFormControlInput1" class="form-label">Cantidad alumnos permitidos</label>
                        <input type="text" disabled class="form-control" id="exampleFormControlInput1" value="<?php echo $proyecto['cantidad_estudiante']; ?>">
                    </div>
                </div>
                <div class="col-md">
                    <div class="mb-3">
                        <label for="exampleFormControlInput1" class="form-label">Temática</label>
                        <input type="text" disabled class="form-control" id="exampleFormControlInput1" value="<?php echo $proyecto['tematica']; ?>">
                    </div>
                </div>
            </div>
            <div class="row mb-1">
                <div class="col-md">
                    <div class="mb-3">
                        <label for="exampleFormControlInput1" class="form-label">Modalidad</label>
                        <input type="text" disabled class="form-control" id="exampleFormControlInput1" value="<?php echo $proyecto['modalidad']; ?>">
                    </div>

                </div>
                <div class="col-md">
                    <div class="mb-3">
                        <label for="exampleFormControlInput1" class="form-label">Subtemática</label>
                        <input type="text" disabled class="form-control" id="exampleFormControlInput1" value="<?php echo $proyecto['subtematica']; ?>">
                    </div>
                </div>
            </div>
            <div class="row mb-1">
                <div class="col-md">
                    <div class="mb-3">
                        <label for="exampleFormControlInput1" class="form-label">Presupuesto</label>
                        <input type="text" disabled class="form-control" id="exampleFormControlInput1" value="<?php echo $proyecto['presupuesto']; ?>">
                    </div>
                </div>
                <div class="col-md">
                    <div class="mb-3">
                        <label for="exampleFormControlInput1" class="form-label">Periodo</label>
                        <input type="text" disabled class="form-control" id="exampleFormControlInput1" value="<?php echo $proyecto['periodo'] . ' - ' . $proyecto['estado_periodo'];  ?>">
                    </div>
                </div>
            </div>
            <div class="row mb-1">
                <div class="col-md">
                    <div class="mb-3">
                        <label for="exampleFormControlInput1" class="form-label">Fecha inicio</label>
                        <input type="text" disabled class="form-control" id="exampleFormControlInput1" value="<?php echo $proyecto['fecha_inicio']; ?>">
                    </div>
                </div>
                <div class="col-md">
                    <div class="mb-3">
                        <label for="exampleFormControlInput1" class="form-label">Fecha final</label>
                        <input type="text" disabled class="form-control" id="exampleFormControlInput1" value="<?php echo $proyecto['fecha_fin']; ?>">
                    </div>
                </div>
            </div>
            <div class="row mb-1">
                <div class="col-md">
                    <div class="mb-3">
                        <label for="exampleFormControlInput1" class="form-label">Estado</label>
                        <input type="text" disabled class="form-control" id="exampleFormControlInput1" value="<?php echo $proyecto['estado_proyecto']; ?>">
                    </div>
                </div>
                <div class="col-md">
                    <div class="mb-3">
                        <label for="exampleFormControlInput1" class="form-label">Fecha de creación</label>
                        <input type="text" disabled class="form-control" id="exampleFormControlInput1" value="<?php echo $proyecto['creado_en']; ?>">
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        <div class="col-12">
            <h5>Información del investigador</h5>
        </div>
        <div class="row mb-1">

            <?php foreach ($investigador as $invest): ?>
                <div class="row mb-1">
                    <div class="col-md">
                        <div class="mb-3">
                            <label for="exampleFormControlInput1" class="form-label">Nombre completo</label>
                            <input type="text" disabled class="form-control" id="InputFormLimpiar1" value="<?php echo $invest['nombre'] . ' ' . $invest['apellido_paterno'] . ' ' . $invest['apellido_materno']; ?>">
                        </div>
                    </div>
                    <div class="col-md">
                        <div class="mb-3">
                            <label for="exampleFormControlTextarea1" class="form-label">Área de conocimientos</label>
                            <input class="form-control" disabled id="InputFormLimpiar2" value="<?php echo $invest['area_conocimiento']; ?>"></input>
                        </div>
                    </div>
                </div>
                <div class="row mb-1">
                    <div class="col-md">
                        <div class="mb-3">
                            <label for="exampleFormControlTextarea1" class="form-label">Subárea de conomientos</label>
                            <input class="form-control" disabled id="InputFormLimpiar2" value="<?php echo $invest['subarea']; ?>"></input>
                        </div>
                    </div>
                    <div class="col-md">
                        <div class="mb-3">
                            <label for="exampleFormControlTextarea1" class="form-label">Nivel de SNI</label>
                            <input class="form-control" disabled id="InputFormLimpiar2" value="<?php echo $invest['nivel_sni']; ?>"></input>
                        </div>
                    </div>
                </div>
                <div class="row mb-1">
                    <div class="col-md">
                        <div class="mb-3">
                            <label for="exampleFormControlTextarea1" class="form-label">Grado acádemico</label>
                            <input class="form-control" disabled id="InputFormLimpiar2" value="<?php echo $invest['grado_academico']; ?>"></input>
                        </div>
                    </div>
                    <div class="col-md">
                        <div class="mb-3">
                            <label for="exampleFormControlTextarea1" class="form-label">Línea de investigación</label>
                            <input class="form-control" disabled id="InputFormLimpiar2" value="<?php echo $invest['linea_investigacion']; ?>"></input>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php if ($rol == "supervisor" || $rol == "profesor" || $rol == "investigador"): ?>
            <div class="row mb-1">
                <div class="col-12">
                    <h5>Estudiantes involucrados</h5>
                </div>
                <div class="col-12">
                    <div class="table-responsive">
                        <table class="table table-light" id="tabla_informacion">
                            <thead class="text-center">
                                <tr>
                                    <th>ID</th>
                                    <th>Nombre</th>
                                    <th>Carrera</th>
                                    <th>Área conocimientos</th>
                                    <th>Subárea conocimientos</th>
                                </tr>
                            </thead>
                            <tbody class="text-center">
                                <?php foreach ($estudiantes as $alumno):
                                    echo "<tr>";
                                    echo "<th scope='row'>{$alumno['id_usuarios']}</th>";
                                    echo "<td>{$alumno['nombre']} {$alumno['apellido_paterno']} {$alumno['apellido_materno']}</td>";
                                    echo "<td>{$alumno['carrera']}</td>";
                                    echo "<td>{$alumno['area']}</td>";
                                    echo "<td>{$alumno['subarea']}</td>";
                                    echo "</tr>";
                                endforeach;
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php foreach ($estudiantes as $alumno): ?>
                <div class="card mb-3" id="tarjeta_móvil" style="width: 18rem;">
                    <div class="card-body">
                        <h5 class="card-title"><b><?php echo $alumno['id_usuarios']; ?></b></h5>
                        <p class="card-text"><b><?php echo $alumno['nombre'] .  ' ' . $alumno['apellido_paterno'] . ' ' . $alumno['apellido_materno']; ?></b></p>
                    </div>
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item">
                            <div class="row">
                                <div class="col-12">
                                    <label><b>Carrera</b></label>
                                    <p class="card-text"><?php echo $alumno['carrera']; ?></p>
                                </div>
                            </div>
                        </li>
                        <li class="list-group-item">
                            <div class="row">
                                <div class="col-12">
                                    <label><b>Área de conocimientos</b></label>
                                    <p class="card-text"><?php echo $alumno['area']; ?></p>
                                </div>
                            </div>
                        </li>
                        <li class="list-group-item">
                            <div class="row">
                                <div class="col-12">
                                    <label><b>Subárea de conomientos</b></label>
                                    <p class="card-text"><?php echo $alumno['subarea']; ?></p>
                                </div>
                            </div>
                        </li>
                    </ul>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
        </div>
    </div>
<?php
$contenido = ob_get_clean();
$titulo = "Proyectos";
$bodyClass = "proyectos-page";

include __DIR__ . '/../../layout.php';
?>
