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
$id_usuario = $_SESSION['id_usuario'];
$id_proyectos = $_SESSION['id_usuario'] ?? null;
require_once "../../Controladores/tareasControlador.php";
$tareaControlador = new TareaControlador();

// Traer TODAS las tareas del estudiante
$tareas = $tareaControlador->listarTareasEstudiante($id_usuario);
// ======================
// GENERAR CONTENIDO
// ======================
ob_start();
?>
<div class="container-fluid py-4">

    <div class="row mb-3 align-items-center">
        <div class="row  mb-3">
        <div class="col-md-6">
            <h3 class="mb-0">Tareas</h3>
        </div>
            <div class="col-12 col-md-6 text-md-end text-center mb-2 mb-md-0">
                <a href="../../Vistas/Proyectos/tabla.php" class="btn btn-danger w-100 w-md-auto">Regresar</a>
            </div>
        </div>

    <?php if (empty($tareas)): ?>
        <div class="alert alert-info text-center">No hay tareas asignadas.</div>
    <?php endif; ?>

    <?php foreach ($tareas as $tarea): ?>

        <!-- TARJETA TIPO GOOGLE CLASSROOM -->
        <div class="card shadow-sm mb-3 tarea-card">
            <div class="card-body">

                <!-- ENCABEZADO -->
                <div class="d-flex justify-content-between">
                    <h5 class="fw-bold"><?= htmlspecialchars($tarea['tipo']) ?></h5>
                    <span class="badge text-bg-<?= $tareaControlador->estiloEstado($tarea['id_estadoT']) ?>">
                        <?= htmlspecialchars($tarea['estado_texto']) ?>
                    </span>
                </div>

                <!-- INSTRUCCIONES LIMITADAS -->
                <p class="text-muted descripcion-limit mt-2">
                    <?= htmlspecialchars($tarea['instrucciones']) ?>
                </p>

                <!-- INFORMACIÓN -->
                <p class="small text-secondary mb-1">
                    <strong>Entrega:</strong> <?= $tarea['fecha_entrega'] ?? '--' ?>
                </p>

                <!-- BOTÓN PRINCIPAL -->
                <div class="text-end">
                    <a href="tarea.php?id_asignacion=<?= $tarea['id_asignacion']; ?>"
                       class="btn btn-outline-primary">
                        Ver / Entregar tarea
                    </a>
                </div>
            </div>
        </div>

    <?php endforeach; ?>
</div>
<?php
$contenido = ob_get_clean();
$titulo = "Proyectos";
$bodyClass = "proyectos-page";

include __DIR__ . '/../../layout.php';
?>
