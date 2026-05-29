<?php

if (empty($_GET)) {
    header("Location: ../../Vistas/Proyectos/index.php?msg=sin_argumentos_url");
    exit;
}