<?php
/*Proyectos/baja_estudiante.php - Página secundaria para dar de baja a un estudiante por el investigador */

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

if (!isset($_SESSION['id_usuario'])) {
    header("Location: /ITSFCP-PROYECTOS/index.php");
    exit;
}

$rol = strtolower($_SESSION['rol'] ?? '');
$id = $_SESSION['id_usuario'];
$id_proyecto = $_GET["id_proyectos"];
$id_estudiante = $_GET["id_usuarios"];

//Solo el investigador puede acceder
if (!in_array($rol, ['investigador', 'profesor'], true)) {
    header("Location: /ITSFCP-PROYECTOS/Vistas/Principal/index.php");
    exit;
}

require_once '..\..\Controladores\proyectoControlador.php';

$proyectoControlador = new ProyectoControlador();

$proyecto = $proyectoControlador->datosproyecto($id_proyecto);


$estudiante = $proyecto['investigador'] ?? [];
$proyecto = $proyecto['area'] ?? [];

if ($rol == "investigador" || $rol == "profesor" || $rol == "supervisor") {
    $estudiantes = $proyectoControlador->datosestudiantes($id_proyecto);
}

ob_start();
?>
<div class="container-fluid py-4">
    <!-- ENCABEZADO -->
    <div class="row mb-4 align-items-center">

        <div class="col-md-6">
            <h3 class="fw-bold mb-0">Dar de baja al estudiante</h3>
        </div>
        <div class="col-6 col-md-6 text-md-end mb-2 mb-md-0 text-end">
            <a href="editar.php?id_proyectos=<?= $id_proyecto ?>" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Regresar
            </a>
        </div>

    </div>

    <div class="card shadow-lg border-0">
        <div class="card-body">

            <div class="mb-3">
                <h5 class="text-primary">Información</h5>
                <p><strong>Estudiante:</strong> <?= $estudiante['nombre'] . " " . $estudiante['apellido_paterno'] ?></p>
                <p><strong>Proyecto:</strong> <?= $estudiante['titulo'] ?></p>
            </div>

            <form method="POST">

                <input type="hidden" name="id_estudiante" value="<?= $id_estudiante ?>">
                <input type="hidden" name="id_proyecto" value="<?= $id_proyecto ?>">
                <input type="hidden" name="action" value="baja">

                <div class="mb-3">
                    <label class="form-label">Motivo de baja</label>
                    <textarea name="motivo" class="form-control" rows="4"
                        placeholder="Ejemplo: Incumplimiento de actividades, abandono del proyecto..."></textarea>
                </div>

                <div class="d-flex justify-content-between">
                    <a href="editar.php?id_proyectos=<?= $id_proyecto ?>" class="btn btn-secondary">
                        Cancelar
                    </a>

                    <button class="btn btn-danger">
                        Confirmar baja
                    </button>
                </div>

            </form>

        </div>
    </div>
</div>
<?php
$contenido = ob_get_clean();
$titulo = "Dar baja a estudiante del proyecto";
$bodyClass = "proyectos-page";

include __DIR__ . '/../../layout.php';
?>