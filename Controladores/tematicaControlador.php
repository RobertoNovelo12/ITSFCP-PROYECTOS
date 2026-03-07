<?php

require_once __DIR__ . '/../Modelos/tematica.php';
require_once __DIR__ . '/../publico/config/conexion.php';



class tematicaControlador
{

    //Obtener datos
    public function index($rol, $buscar = null)
    {
        global $conn;

        $tematica = new Tematica($conn);

        if ($rol == "supervisor") {
            //Revisión de estados de tarea
            $tema = $tematica->obtenerTematicas($rol, $buscar);
            return $tema;
        } else {
            $tema = []; // evita undefined variable
            return $tema;
        }
    }

    //Obtener datos para editar
    public function indexEditar($rol, $id_tematica)
    {
        global $conn;

        if ($rol == "supervisor") {
        $tematica = new Tematica($conn);
        $tema = $tematica->obtenerTematicasEditar($id_tematica);
        return $tema;
        } else {
            $tema = []; // evita undefined variable
            return $tema;
        }
    }

    public function eliminar_tematica($id_tematica, $rol)
    {
        // Lógica para cambiar de estado una temática a desactivado
        if ($rol !== 'supervisor') {
            die("Error: No tienes permiso para eliminar temáticas.");
        }

        global $conn;

        $sql = "UPDATE tematicas SET estado = 0 WHERE id_tematica = ?;";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id_tematica);

        if ($stmt->execute()) {
            return ["success" => true, "message" => "Temática desactivada correctamente."];
        } else {
            return ["success" => false, "message" => "Error al desactivada la temática."];
        }
    }

    public function encabezadosPrincipal($rol)
    {
        if ($rol == "supervisor") {
            $encabezados = [
                'Temática',
                'Descripción',
                'Subtemáticas',
                'Estado',
                'Acciones'
            ];
        } else {
            $encabezados = [];
        }
        return $encabezados;
    }

    public function opciones($rol, $filtros)
    {
        switch ($rol) {
            case 'supervisor':
                $opciones = [
                    'Total'       => "Total ({$filtros[0]['Total']})",
                    'Activo'     => "Activos ({$filtros[0]['Activo']})",
                    'Desactivado'  => "Desactivados ({$filtros[0]['Desactivado']})"
                ];
                break;
            default:
                $opciones = [];
                break;
        }
        return $opciones;
    }

        //Para obtener el número del filtro de la tabla
    public function numerofiltro($action)
    {

        $numerofiltro = 0;
        switch ($action) {
            case 'Total':
                $numerofiltro = 2;
                break;
            case 'Activo':
                $numerofiltro = 0;
                break;
            case 'Desactivado':
                $numerofiltro = 1;
                break;
            default:
                break;
        }
        return $numerofiltro;
    }

    //Datos filtros GENERAL
    public function filtros($rol)
    {
        global $conn;
        $tematica = new Tematica($conn);
        //Datos filtros
        if ($rol == "supervisor") {
            $tema = $tematica->obtenerTematicasDatosFiltro($rol);
            return $tema;
        } else {
            $tema = []; // evita undefined variable
            return $tema;
        }
    }

    //Datos tabla por filtro
    //Total
    public function Total($rol, $buscar = null)
    {
        global $conn;
        $tematica = new Tematica($conn);
        //Datos filtros
        if ($rol == "supervisor") {
            $tematicas = $tematica->obtenerTematicasTablaFiltro(2, $rol, $buscar);
            return $tematicas;
        } else {
            $tematicas = []; // evita undefined variable
            return $tematicas;
        }
    }

    //Activos
    public function Activo($rol, $buscar = null)
    {
        global $conn;
        $tematica = new Tematica($conn);
        //Datos filtros
        if ($rol == "supervisor") {
            $tematicas = $tematica->obtenerTematicasTablaFiltro(1, $rol, $buscar);
            return $tematicas;
        } else {
            $tematicas = []; // evita undefined variable
            return $tematicas;
        }
    }

    //Desactivados
    public function Desactivado($rol, $buscar = null)
    {
        global $conn;
        $proyecto = new Tematica($conn);
        //Datos filtros
        if ($rol == "supervisor") {
            $tematicas = $proyecto->obtenerTematicasTablaFiltro(0, $rol, $buscar);
            return $tematicas;
        } else {
            $tematicas = []; // evita undefined variable
            return $tematicas;
        }
    }

    public function EstiloEstadoLista($estado)
    {
        switch ($estado) {

            case 'Activo':
                $estilo = "success";
                break;
            case 'Desactivado':
                $estilo = "danger";
                break;
            default:
                $estilo = "info";
                break;
        }
        return $estilo;
    }

    //BOTONES
    public function obtenerbotones($tipo, $id1 = null)
    {
        $boton = "";
        switch ($tipo) {
            case 'Activar':
                $boton = '<a href="tabla.php?action=actualizarestado&id_tematica=' . $id1 . '&tipo=Activo" type="button" class="btn btn-success" data-bs-toggle="tooltip" data-bs-placement="top"
        data-bs-custom-class="custom-tooltip" data-bs-title="Activar temática"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" class="bi bi-check-circle-fill" viewBox="0 0 16 16">
  <path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0m-3.97-3.03a.75.75 0 0 0-1.08.022L7.477 9.417 5.384 7.323a.75.75 0 0 0-1.06 1.06L6.97 11.03a.75.75 0 0 0 1.079-.02l3.992-4.99a.75.75 0 0 0-.01-1.05z"/></svg> Activar tarea</a>';
                break;
            case 'Editar Tematica':
                $boton = '<a href="editar.php?id_tematica=' . $id1 . '" type="button" class="btn btn-warning" data-bs-toggle="tooltip" data-bs-placement="top"
        data-bs-custom-class="custom-tooltip" data-bs-title="Editar temática"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-pencil-square" viewBox="0 0 16 16">
  <path d="M15.502 1.94a.5.5 0 0 1 0 .706L14.459 3.69l-2-2L13.502.646a.5.5 0 0 1 .707 0l1.293 1.293zm-1.75 2.456-2-2L4.939 9.21a.5.5 0 0 0-.121.196l-.805 2.414a.25.25 0 0 0 .316.316l2.414-.805a.5.5 0 0 0 .196-.12l6.813-6.814z"/>
  <path fill-rule="evenodd" d="M1 13.5A1.5 1.5 0 0 0 2.5 15h11a1.5 1.5 0 0 0 1.5-1.5v-6a.5.5 0 0 0-1 0v6a.5.5 0 0 1-.5.5h-11a.5.5 0 0 1-.5-.5v-11a.5.5 0 0 1 .5-.5H9a.5.5 0 0 0 0-1H2.5A1.5 1.5 0 0 0 1 2.5z"/>
</svg></a>';
                break;
            case 'Detalles':
                $boton = '<a href="detalles.php?id_tematica=' . $id1 . '" type="button" class="btn btn-info" data-bs-toggle="tooltip" data-bs-placement="top"
        data-bs-custom-class="custom-tooltip" data-bs-title="Ver detalles de la temática"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-eye-fill" style="padding:0px;margin:auto;" viewBox="0 0 16 16">
  <path d="M10.5 8a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0"/><path d="M0 8s3-5.5 8-5.5S16 8 16 8s-3 5.5-8 5.5S0 8 0 8m8 3.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7"/></svg></a>';
                break;
            case 'Desactivar':
                $boton = '
                <a href="editar.php?action=actualizarestado&id_tematica=' . $id1 . '&tipo=Inactivo" type="button" class="btn btn-danger" data-bs-toggle="tooltip" data-bs-placement="top"
        data-bs-custom-class="custom-tooltip" data-bs-title="Desactivar temática"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-x-circle-fill" viewBox="0 0 16 16">
  <path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0M5.354 4.646a.5.5 0 1 0-.708.708L7.293 8l-2.647 2.646a.5.5 0 0 0 .708.708L8 8.707l2.646 2.647a.5.5 0 0 0 .708-.708L8.707 8l2.647-2.646a.5.5 0 0 0-.708-.708L8 7.293z"/>
</svg></a>';
                break;
            case 'Eliminar':
                $boton = '<a href="editar.php?id_subtematica=' . $id1 . '" type="button" class="btn btn-info" data-bs-toggle="tooltip" data-bs-placement="top"
        data-bs-custom-class="custom-tooltip" data-bs-title="Elimunar subtemática"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-pencil-square" viewBox="0 0 16 16">
  <path d="M15.502 1.94a.5.5 0 0 1 0 .706L14.459 3.69l-2-2L13.502.646a.5.5 0 0 1 .707 0l1.293 1.293zm-1.75 2.456-2-2L4.939 9.21a.5.5 0 0 0-.121.196l-.805 2.414a.25.25 0 0 0 .316.316l2.414-.805a.5.5 0 0 0 .196-.12l6.813-6.814z"/>
  <path fill-rule="evenodd" d="M1 13.5A1.5 1.5 0 0 0 2.5 15h11a1.5 1.5 0 0 0 1.5-1.5v-6a.5.5 0 0 0-1 0v6a.5.5 0 0 1-.5.5h-11a.5.5 0 0 1-.5-.5v-11a.5.5 0 0 1 .5-.5H9a.5.5 0 0 0 0-1H2.5A1.5 1.5 0 0 0 1 2.5z"/>
</svg></a>';
                break;
            case 'Guardar':
                $boton = '<button type="submit" class="btn btn-primary">Guardar cambios</button>';
                break;
            default:
                break;
        }
        return $boton;
    }

    //Botones de acción en la tabla 
    public function botonesAccionPrincipal($id, $rol, $estado = null)
    {
        $boton = "";

        switch ($rol) {

            case 'supervisor':
                if (in_array($estado, ["Activo"])) {
                    $boton = $this->obtenerbotones("Editar Tematica", $id);
                    $boton .= $this->obtenerbotones("Detalles", $id);
                    $boton .= $this->obtenerbotones("Desactivar", $id);
                } elseif ($estado == "Desactivado") {
                    $boton = $this->obtenerbotones("Detalles", $id);
                    $boton .= $this->obtenerbotones("Activar", $id);
                }
                break;
        }

        return $boton;
    }

    //Crear temática
    public function registrarTematica($data, $rol, $subtematicas = [])
    {
        if ($rol !== 'supervisor') {
            die("No tienes permiso para registrar temáticas.");
        }

        global $conn;

        $nombre = trim($data['NombreTematica']);
        $descripcion = trim($data['Descripcion']);

        $tematica = new Tematica($conn);

        // 1️ Insertar temática
        $id_tematica = $tematica->registrarTematica($nombre, $descripcion);

        if (!$id_tematica) {
            header("Location: crear.php?error=1");
            exit;
        }

        // 2️ Insertar subtemáticas
        if (!empty($subtematicas)) {
            foreach ($subtematicas as $sub) {
                $tematica->registrarSubtematica($id_tematica, $sub['nombre']);
            }
        }

        // 3️ Limpiar sesión
        unset($_SESSION['tematica_temp']);
        unset($_SESSION['subtematicas']);

        header("Location: tabla.php?mensaje=1");
        exit;
    }

     //Crear temática
    public function editarTematica($data, $rol, $subtematicas = [])
    {
        if ($rol !== 'supervisor') {
            die("No tienes permiso para editar temáticas.");
        }

        global $conn;

        $id_tematica = $data['id_tematica'];
        $nombre = trim($data['NombreTematica']);
        $descripcion = trim($data['Descripcion']);

        $tematica = new Tematica($conn);

        // 1️ Actualizar temática
        $id_tematica = $tematica->editarTematica($nombre, $descripcion, $id_tematica);

        if (!$id_tematica) {
            header("Location: crear.php?error=1");
            exit;
        }

        // 2️ Actualizar subtemáticas
        if (!empty($subtematicas)) {
            foreach ($subtematicas as $sub) {
                $tematica->editarSubtematica($id_tematica, $sub['nombre']);
            }
        }

        // 3 Actualizar subtemáticas
        if (!empty($subtematicas)) {
            foreach ($subtematicas as $sub) {
                $tematica->editarSubtematica($id_tematica, $sub['nombre']);
            }
        }

        // 3 Actualizar subtemáticas
        if (!empty($subtematicas)) {
            foreach ($subtematicas as $sub) {
                $tematica->editarSubtematica($id_tematica, $sub['nombre']);
            }
        }

        // 4 Limpiar sesión
        unset($_SESSION['tematica_temp']);
        unset($_SESSION['subtematicas']);

        header("Location: tabla.php?mensaje=1");
        exit;
    }
}