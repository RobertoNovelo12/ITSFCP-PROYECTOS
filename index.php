<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (isset($_SESSION['rol'])) {
    $base_url = "/ITSFCP-PROYECTOS/";

    switch (strtolower($_SESSION['rol'])) {
        case 'alumno':
            header("Location: {$base_url}Vistas/usuarios/alumno.php");
            exit;
        case 'profesor':
        case 'investigador':
            header("Location: {$base_url}Vistas/usuarios/profesor.php");
            exit;
        case 'supervisor':
            header("Location: {$base_url}Vistas/usuarios/supervisor.php");
            exit;
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ITSFCP/PROYECTOS</title>
    
    <!-- ⬇️ AGREGAR: Script para aplicar estado del sidebar antes del CSS -->
    <script>
        (() => {
            try {
                const sidebarCollapsed = localStorage.getItem("sidebar-collapsed");
                
                if (sidebarCollapsed === "true") {
                    document.documentElement.classList.add("sidebar-collapsed-initial");
                }
                
                const isDark = localStorage.getItem("darkModeEnabled") === "true";
                if (isDark) {
                    document.documentElement.classList.add("dark-mode");
                }
            } catch (e) {
                console.warn("Error al acceder a localStorage", e);
            }
        })();
    </script>
    
    <link rel="stylesheet" href="/ITSFCP-PROYECTOS/publico/css/styles.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body>
    <?php include './publico/incluido/header.php'; ?>
    
    <div class="container-main">
        <?php include 'sidebar_publico.php'; ?>
        
        <div class="main-content-index">
            <h1 class="title">Sistema web responsive para gestión de proyectos en Instituto Tecnológico Superior
                Felipe Carrillo Puerto</h1>

            <div class="content-section">
                <div class="text-content">
                    <p>Esta plataforma ha sido desarrollada para optimizar la <strong>administración, monitoreo y
                            control de proyectos</strong> en el Instituto Tecnológico Superior de Felipe Carrillo
                        Puerto.</p>

                    <p>Su principal objetivo es <strong>centralizar el registro y seguimiento de los proyectos
                            académicos</strong>, especialmente los de investigación, ofreciendo un entorno
                        accesible, organizado y eficiente.</p>

                    <p>A través de este sistema, es posible <strong>registrar información, generar reportes por
                            periodo, exportar datos en formato PDF</strong> y <strong>consultar en tiempo real el
                            estado de los proyectos en ejecución.</strong></p>

                    <p>Con ello, se promueve una <strong>gestión más ágil y sustentable</strong>, reduciendo el uso
                        de documentos físicos, mejorando la comunicación institucional y fortaleciendo los procesos
                        de investigación.</p>
                </div>

                <div class="image-container">
                    <img class="image-container" src="./publico/img/home-img.webp" alt="Ilustración del sistema" srcset="">
                </div>
            </div>

            <h2 class="section-title">Nuestro objetivo</h2>
            <div class="text-content">
                <p>Desarrollar un sistema web responsivo que facilite la gestión y el seguimiento de los proyectos del Instituto Tecnológico Superior de Felipe Carrillo Puerto.</p>
                <p>Buscamos automatizar procesos como la entrega de reportes y la organización de la información del personal, docentes y estudiantes, aprovechando el uso de tecnologías web y bases de datos modernas.</p>
            </div>
        </div>
    </div>
    
    <!-- ⬇️ AGREGAR: Scripts al final del body -->
    <script src="/ITSFCP-PROYECTOS/publico/js/javascript.js"></script>
    <script src="/ITSFCP-PROYECTOS/publico/js/sidebar.js"></script>
</body>

</html>