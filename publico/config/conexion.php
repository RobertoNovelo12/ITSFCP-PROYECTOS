<?php
$servidor = "localhost";
$usuario = "root";
$clave = "12345";
$base_datos = "gestion_proyectos";
$puerto = 3306; //

$conn = new mysqli($servidor, $usuario, $clave, $base_datos, $puerto);

if ($conn->connect_error) {
    die("Error de conexión: " . $conn->connect_error);
}
?>