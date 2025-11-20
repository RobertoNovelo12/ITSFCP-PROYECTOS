<?php
session_start();
require '../../publico/config/conexion.php';

if (!isset($_SESSION['id_usuario']) && !isset($_GET['solicitud_enviada'])) {
    header("Location: ../../login.php");
    exit;
}

$rol = $_GET['rol'] ?? $_SESSION['rol'] ?? '';
$rol = strtolower(trim($rol));


// =============================
// CONSULTAS PARA LOS SELECTS
// =============================

// Carreras
$carreras = $conn->query("SELECT id_carrera, nombre_carrera FROM carreras ORDER BY nombre_carrera");

// Áreas
$areas = $conn->query("SELECT id_area, nombre_area FROM areas_conocimiento ORDER BY nombre_area");

// Subáreas
$subareas = $conn->query("SELECT id_subarea, id_area, nombre_subarea FROM subareas_conocimiento");

// Niveles SNI
$nivelesSNI = $conn->query("SELECT id_nivel, nombre FROM niveles_sni");

// Grados académicos
$grados = $conn->query("SELECT id_grado, nombre FROM grados_academicos");

// Líneas de investigación
$lineas = $conn->query("SELECT id_linea, nombre FROM lineas_investigacion ORDER BY nombre");
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Solicitud de Alta</title>
    <link rel="stylesheet" href="../../publico/css/styles.css">

    <script>
        function filtrarSubareas() {
            let area = document.getElementById("area").value;
            let subareas = document.querySelectorAll("#subarea option");

            subareas.forEach(opt => {
                if (opt.dataset.area == area || opt.value == "") {
                    opt.style.display = "block";
                } else {
                    opt.style.display = "none";
                }
            });

            document.getElementById("subarea").value = "";
        }
    </script>
</head>

<body class="body-register">
    <div class="container-perfil">
        <h1 class="title-perfil">Solicitud de alta - <?= ucfirst($rol) ?></h1>

        <!-- ============================
         FORMULARIO ALUMNO
         ============================ -->
        <?php if ($rol === 'estudiante'): ?>
            <form class="form-perfil" action="../../publico/config/procesar_solicitud.php" method="POST">
                <input type="hidden" name="rol" value="alumno">

                <div class="input-group">
                    <input type="text" name="matricula" class="input-field" placeholder=" " required>
                    <label class="floating-label">Matrícula</label>
                </div>

                <!-- CARRERA -->
                <div class="input-group">
                    <label class="label-select">Carrera</label>
                    <select name="carrera" required>
                        <option value="" disabled selected>Seleccione carrera...</option>
                        <?php while ($c = $carreras->fetch_assoc()): ?>
                            <option value="<?= $c['id_carrera'] ?>"><?= $c['nombre_carrera'] ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <!-- ÁREA -->
                <div class="input-group">
                    <label class="label-select">Área de conocimiento</label>
                    <select name="area" id="area" onchange="filtrarSubareas()" required>
                        <option value="" disabled selected>Seleccione área...</option>
                        <?php while ($a = $areas->fetch_assoc()): ?>
                            <option value="<?= $a['id_area'] ?>"><?= $a['nombre_area'] ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <!-- SUBÁREA -->
                <div class="input-group">
                    <label class="label-select">Subárea de conocimiento</label>
                    <select name="subarea" id="subarea" required>
                        <option value="" disabled selected>Seleccione subárea...</option>
                        <?php while ($s = $subareas->fetch_assoc()): ?>
                            <option value="<?= $s['id_subarea'] ?>" data-area="<?= $s['id_area'] ?>">
                                <?= $s['nombre_subarea'] ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <button type="submit" class="submit-btn">Enviar solicitud</button>
            </form>


            <!-- ============================
         FORMULARIO INVESTIGADOR
         ============================ -->
        <?php elseif ($rol === 'investigador'): ?>
            <form class="form-perfil" action="../../publico/config/procesar_solicitud.php" method="POST">
                <input type="hidden" name="rol" value="investigador">

                <!-- ÁREA -->
                <div class="input-group">
                    <label class="label-select">Área de conocimiento</label>
                    <select name="area" required>
                        <option value="" disabled selected>Seleccione área...</option>
                        <?php
                        $areas->data_seek(0);
                        while ($a = $areas->fetch_assoc()): ?>
                            <option value="<?= $a['id_area'] ?>"><?= $a['nombre_area'] ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <!-- SUBÁREA -->
                <div class="input-group">
                    <label class="label-select">Subárea de conocimiento</label>
                    <select name="subarea" required>
                        <option value="" disabled selected>Seleccione subárea...</option>
                        <?php
                        $subareas->data_seek(0);
                        while ($s = $subareas->fetch_assoc()): ?>
                            <option value="<?= $s['id_subarea'] ?>"><?= $s['nombre_subarea'] ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <!-- NIVEL SNI -->
                <div class="input-group">
                    <label class="label-select">Nivel SNI</label>
                    <select name="nivel_sni" required>
                        <option value="" disabled selected>Seleccione SNI...</option>
                        <?php
                        $nivelesSNI->data_seek(0);
                        while ($n = $nivelesSNI->fetch_assoc()): ?>
                            <option value="<?= $n['id_nivel'] ?>"><?= $n['nombre'] ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <!-- GRADO -->
                <div class="input-group">
                    <label class="label-select">Grado máximo de estudio</label>
                    <select name="grado" required>
                        <option value="" disabled selected>Seleccione grado...</option>
                        <?php
                        $grados->data_seek(0);
                        while ($g = $grados->fetch_assoc()): ?>
                            <option value="<?= $g['id_grado'] ?>"><?= $g['nombre'] ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <!-- LÍNEA DE INVESTIGACIÓN -->
                <div class="input-group">
                    <label class="label-select">Línea de investigación</label>
                    <select name="linea" required>
                        <option value="" disabled selected>Seleccione línea...</option>
                        <?php
                        $lineas->data_seek(0);
                        while ($l = $lineas->fetch_assoc()): ?>
                            <option value="<?= $l['id_linea'] ?>"><?= $l['nombre'] ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <!-- RCF -->
                <div class="input-group">
                    <input type="text" name="rfc" class="input-field" placeholder=" " maxlength="13" required>
                    <label class="floating-label">RFC</label>
                </div>


                <button type="submit" class="submit-btn">Enviar solicitud</button>
            </form>


            <!-- ============================
         FORMULARIO SUPERVISOR
         ============================ -->
        <?php elseif ($rol === 'supervisor'): ?>
            <form class="form-perfil" action="../../publico/config/procesar_solicitud.php" method="POST"
                enctype="multipart/form-data">
                <input type="hidden" name="rol" value="supervisor">

                <div class="input-group">
                    <label class="label-select">Descargar formato de solicitud</label><br>
                    <a href="../../publico/docs/formato_solicitud.pdf" class="submit-btn" download>Descargar Formato</a>
                </div>

                <div class="input-group">
                    <input type="text" name="departamento" class="input-field" placeholder=" " required>
                    <label class="floating-label">Nombre del departamento</label>
                </div>

                <div class="input-group">
                    <input type="text" name="cargo" class="input-field" placeholder=" " required>
                    <label class="floating-label">Cargo</label>
                </div>

                <div class="input-group">
                    <input type="text" name="rfc" class="input-field" placeholder=" " required>
                    <label class="floating-label">RFC</label>
                </div>

                <div class="input-group">
                    <label class="label-select">Subir solicitud en PDF</label>
                    <input type="file" name="solicitud_pdf" accept="application/pdf" required>
                </div>

                <button type="submit" class="submit-btn">Enviar solicitud</button>
            </form>


        <?php else: ?>
            <p>No se reconoce el tipo de usuario. <a href="crear_perfil.php">Volver</a></p>
        <?php endif; ?>
    </div>

    <?php if (isset($_GET['solicitud_enviada'])): ?>
        <div class="modal-overlay" id="modalSolicitud">
            <div class="modal-content">
                <h2>¡Solicitud enviada!</h2>
                <p>Tu solicitud ha sido registrada correctamente. Serás redirigido al inicio.</p>
                <button class="submit-btn" onclick="cerrarModal()">Aceptar</button>
            </div>
        </div>

        <script>
            document.getElementById("modalSolicitud").style.display = "flex";

            // Cerrar modal manual
            function cerrarModal() {
                window.location.href = "/ITSFCP-PROYECTOS/index.php";
            }
        </script>
    <?php endif; ?>


</body>

</html>