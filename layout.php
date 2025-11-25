<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $titulo ?? 'Panel' ?></title>
    
    <?php if (isset($necesitaQuill) && $necesitaQuill): ?>
    <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
    <script src="https://cdn.quilljs.com/1.3.6/quill.js"></script>
    <?php endif; ?>
    
    <!-- ⬇ AGREGAR ESTO ANTES DEL CSS -->
    <script>
        // Aplicar estado del sidebar INMEDIATAMENTE antes de que se renderice
        (() => {
            try {
                const sidebarCollapsed = localStorage.getItem("sidebar-collapsed");
                
                if (sidebarCollapsed === "true") {
                    // Agregar clase al HTML antes de que se renderice
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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>

<body class="<?= $bodyClass ?? '' ?>">

    <?php include __DIR__ . '/publico/incluido/header.php'; ?>
    <?php include __DIR__ . '/sidebar.php'; ?>

    <div class="main-content">
        <?php
        // Contenido específico de cada página
        if (isset($contenido)) {
            echo $contenido;
        }
        ?>
    </div>

    <script src="/ITSFCP-PROYECTOS/publico/js/javascript.js"></script>
    <script src="/ITSFCP-PROYECTOS/publico/js/sidebar.js"></script>

</body>

</html>