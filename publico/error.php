<?php
session_start();

// Si hay sesión activa → Ir al dashboard
if (isset($_SESSION['id_usuario'])) {
    header("Location: /ITSFCP-PROYECTOS/Vistas/Dashboard/dashboard.php?error=1");
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Error | Algo salió mal</title>

    <style>
        :root {
            --color-primario: #11376b;
            --color-boton-modificar: #fcd34d;
            --color-boton-nuevo: #11376b;
            --color-boton-avalar: #198754;
            --hover-boton: #274a83;
            --tamanio-fuente-base: 16px;
            --borde-menu: #e5e7eb;
            --fondo-web: #f9fafb;
            --fondo-inputs: #ffffff;
            --color-placeholder: #6b7280;
            --color-boton-secundario: #e5e7eb;
            --color-texto-secundario: #6b7280;
            --color-texto-principal: #4b5563;
            --fondo-avatar: #cae4ca;
            --color-avatar: #6b7280;
            --color-sombra: rgba(75, 85, 99, 0.15);
            --color-sombra-fuerte: rgba(75, 85, 99, 0.25);
            --color-hover-gris: #f3f4f6;
            --color-rojo-logout: #b91c1c;
        }

        body {
            margin: 0;
            padding: 0;
            background: var(--fondo-web);
            font-family: Arial, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            text-align: center;
        }

        .error-box {
            background: var(--fondo-inputs);
            padding: 40px 35px;
            border-radius: 18px;
            box-shadow: 0px 8px 25px var(--color-sombra);
            max-width: 450px;
            width: 90%;
        }

        h1 {
            font-size: 60px;
            margin-bottom: 15px;
            color: var(--color-rojo-logout);
        }

        p {
            margin-top: 0;
            margin-bottom: 25px;
            color: var(--color-texto-principal);
            font-size: 17px;
        }

        a {
            display: inline-block;
            padding: 12px 22px;
            background: var(--color-boton-nuevo);
            color: white;
            font-weight: bold;
            border-radius: 8px;
            text-decoration: none;
            transition: 0.25s ease;
            font-size: 16px;
        }

        a:hover {
            background: var(--hover-boton);
        }
    </style>
</head>
<body>

    <div class="error-box">
        <h1>⚠️</h1>
        <h2>Ocurrió un error inesperado</h2>
        <p>Esto no es tu culpa. Algo falló en nuestro sistema.<br>
        Intenta regresar al inicio mientras lo resolvemos.</p>

        <a href="/ITSFCP-PROYECTOS/index.php">Volver al inicio</a>
    </div>

</body>
</html>