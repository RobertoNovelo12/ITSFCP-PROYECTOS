<?php
session_start();
require_once __DIR__ . "/../../publico/config/conexion.php";

// Verificar que el usuario esté logueado
if (!isset($_SESSION['id_usuario'])) {
    header("Location: /ITSFCP-PROYECTOS/index.php");
    exit;
}

$titulo = "Soporte - Centro de Ayuda";

// Preguntas frecuentes
$preguntas = [
    [
        'id' => 'faq1',
        'pregunta' => '¿He olvidado mi contraseña, cómo puedo restablecerla?',
        'respuesta' => 'Puedes restablecer tu contraseña haciendo clic en "¿Olvidaste tu contraseña?" en la página de inicio de sesión. Recibirás un correo electrónico con instrucciones para crear una nueva contraseña.'
    ],
    [
        'id' => 'faq2',
        'pregunta' => '¿Mi cuenta puede tener varios roles (investigador, coordinador, administrador)?',
        'respuesta' => 'Sí, una cuenta puede tener múltiples roles asignados dependiendo de tus responsabilidades en la institución. Puedes cambiar entre roles desde el menú de tu perfil.'
    ],
    [
        'id' => 'faq3',
        'pregunta' => '¿Cómo puedo crear un nuevo proyecto de investigación?',
        'respuesta' => 'Ve a la sección "Proyectos" en el menú lateral y haz clic en "Nuevo Proyecto". Completa el formulario con la información requerida: título, descripción, objetivos, requisitos y selecciona la temática correspondiente.'
    ],
    [
        'id' => 'faq4',
        'pregunta' => '¿Cómo se añaden miembros al equipo de un proyecto?',
        'respuesta' => 'Los estudiantes pueden enviar solicitudes de integración desde la página de detalles del proyecto. Como investigador, recibirás estas solicitudes y podrás aprobarlas o rechazarlas desde el panel de gestión del proyecto.'
    ],
    [
        'id' => 'faq5',
        'pregunta' => '¿La plataforma funciona en dispositivos móviles o tablets?',
        'respuesta' => 'Sí, la plataforma está completamente optimizada para funcionar en dispositivos móviles, tablets y computadoras de escritorio. Puedes acceder desde cualquier navegador moderno.'
    ],
    [
        'id' => 'faq6',
        'pregunta' => '¿Puedo editar la información de un proyecto después de crearlo?',
        'respuesta' => 'Sí, puedes editar la información de tus proyectos en cualquier momento. Ve a "Proyectos", selecciona el proyecto que deseas modificar y haz clic en el botón "Editar".'
    ],
    [
        'id' => 'faq7',
        'pregunta' => '¿Dónde puedo enviar sugerencias de mejora o nuevas funcionalidades?',
        'respuesta' => 'Utiliza el formulario de contacto en esta página para enviarnos tus sugerencias. También puedes reportar problemas o solicitar nuevas características. Te responderemos en un máximo de 24 horas.'
    ]
];

$contenido = '

<div class="container-fluid py-4">
    <div class="soporte-container">
        <!-- Título principal -->
        <div class="soporte-header">
            <h1 class="soporte-titulo">¿Tienes algún problema o duda?</h1>
        </div>

        <div class="row">
            <!-- Columna de preguntas frecuentes -->
            <div class="col-lg-7">
                <div class="soporte-card">
                    <div class="soporte-card-header">
                        <h2 class="soporte-seccion-titulo">Preguntas frecuentes</h2>
                    </div>
                    <div class="soporte-card-body">
                        <div class="soporte-accordion">';

foreach ($preguntas as $index => $faq) {
    $contenido .= '
                            <div class="soporte-accordion-item">
                                <button class="soporte-accordion-header" type="button" onclick="toggleFAQ(\''.$faq['id'].'\')">
                                    <span class="soporte-pregunta">'.$faq['pregunta'].'</span>
                                    <i class="bi bi-chevron-down soporte-icon" id="icon-'.$faq['id'].'"></i>
                                </button>
                                <div class="soporte-accordion-content collapsed" id="content-'.$faq['id'].'">
                                    <p class="soporte-respuesta">'.$faq['respuesta'].'</p>
                                </div>
                            </div>';
}

$contenido .= '
                        </div>
                    </div>
                </div>
            </div>

            <!-- Columna de contacto -->
            <div class="col-lg-5">
                <div class="soporte-card soporte-contacto">
                    <div class="soporte-contacto-header">
                        <div class="soporte-contacto-icon">
                            <i class="bi bi-person-circle"></i>
                        </div>
                        <h3 class="soporte-contacto-titulo">Contacta con nosotros</h3>
                    </div>
                    
                    <form class="soporte-form" id="formContacto" method="POST" action="/ITSFCP-PROYECTOS/Vistas/soporte/procesar_contacto.php">
                        <div class="soporte-form-group">
                            <label class="soporte-label">Escribe tú nombre</label>
                            <input type="text" name="nombre" class="soporte-input" required>
                        </div>

                        <div class="soporte-form-group">
                            <label class="soporte-label">Escribe tú correo</label>
                            <input type="email" name="correo" class="soporte-input" required>
                        </div>

                        <div class="soporte-form-group">
                            <label class="soporte-label">Escribe tú mensaje</label>
                            <textarea name="mensaje" class="soporte-textarea" rows="5" required></textarea>
                        </div>

                        <button type="submit" class="soporte-btn-enviar">
                            <i class="bi bi-send-fill"></i>
                            Enviar mensaje
                        </button>

                        <p class="soporte-tiempo-respuesta">Te responderemos en un máximo de 24 horas</p>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="/ITSFCP-PROYECTOS/publico/js/soporte.js"></script>
';

include __DIR__ . '/../../layout.php';
?>