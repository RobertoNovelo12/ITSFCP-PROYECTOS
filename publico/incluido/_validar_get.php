<?php

if (empty($_GET)) {
    header("Location: index.php?msg=sin_argumentos_url");
    exit;
}