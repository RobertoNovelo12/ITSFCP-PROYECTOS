<?php
session_start();
require_once __DIR__ . "/../../publico/config/conexion.php";

// Incluir layout
$contenido = "";

// Consulta todos los proyectos activos
$sql = "
    SELECT 
        p.id_proyectos,
        p.titulo,
        p.descripcion,
        t.nombre_tematica AS tematica,
        p.cantidad_estudiante,
        p.modalidad,
        u.nombre AS investigador,
        DATE_FORMAT(p.creado_en, '%d / %m / %Y') AS fecha_creacion
    FROM proyectos p
    LEFT JOIN tematica t ON p.id_tematica = t.id_tematica
    LEFT JOIN usuarios u ON p.id_investigador = u.id_usuarios
    WHERE p.id_estadoP = 1
    ORDER BY p.creado_en DESC
";

$result = $conn->query($sql);
$contenido = '
<div class="row mb-4">
    <div class="col-12 text-left">
        <h2 class="fw-bold">Propuestas</h2>
    </div>
</div>
<div class="row">
';
if ($result && $result->num_rows > 0) {
    while ($proyecto = $result->fetch_assoc()) {
        $contenido .= '
        <div class="col-lg-4 col-md-6 mb-4">
            <div class="card card-propuesta h-100">
                <div class="card-body">
                    <h5 class="card-title fw-bold mb-3">'.htmlspecialchars($proyecto['titulo']).'</h5>
                    <div class="info-line">
                        <p class="mb-0 small"><strong>Temática:</strong> '.htmlspecialchars($proyecto['tematica']).'</p>
                    </div>
                    <div class="info-line">
                        <p class="mb-0 small"><strong>Número de alumnos permitidos:</strong> '.htmlspecialchars($proyecto['cantidad_estudiante']).'</p>
                    </div>
                    <div class="info-line">
                        <p class="mb-0 small"><strong>Modalidad:</strong> '.ucfirst($proyecto['modalidad']).'</p>
                    </div>
                    <div class="info-line">
                        <p class="mb-0 small"><strong>Investigador:</strong> '.htmlspecialchars($proyecto['investigador']).'</p>
                    </div>
                    <div class="mb-3">
                        <p class="mb-0 small"><strong>Fecha de creación:</strong> '.htmlspecialchars($proyecto['fecha_creacion']).'</p>
                    </div>
                    <a href="/ITSFCP-PROYECTOS/Vistas/Proyectos/editar.php?id='.$proyecto['id_proyectos'].'" class="btn btn-ver-detalle">Ver detalle</a>
                </div>
            </div>
        </div>
        ';
    }
} else {
    $contenido = '<div class="col-12 text-center"><p class="text-muted fw-semibold">Aún no hay proyectos disponibles</p></div>';
}

include __DIR__ . '/../../layout.php';