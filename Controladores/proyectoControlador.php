<?php
require_once '../../Modelos/proyecto.php';
require_once '../../publico/config/conexion.php';
// Encabezados según rol

class ProyectoControlador
{
    public function numerofiltro()
    {
        $action = $_GET['action'] ?? 'Total';
        $numerofiltro = 0;
        switch ($action) {
            case 'Total':
                $numerofiltro = 0;
                break;

            case 'Cierre':
                $numerofiltro = 1;
                break;

            case 'Activos':
                $numerofiltro = 2;
                break;
            case 'PorAprobar':
                $numerofiltro = 3;
                break;
            case 'Rechazados':
                $numerofiltro = 4;
                break;
            case 'PorCerrar':
                $numerofiltro = 5;
                break;
            default:
                break;
        }
        return $numerofiltro;
    }

    public function index($id, $rol, $buscar = null)
    {
        global $conn;

        $proyecto = new Proyectos($conn);

        if ($rol == "investigador" || $rol == "alumno" || $rol == "supervisor") {
            //General
            $proyectos = $proyecto->obtenerProyectos($id, $rol, $buscar);
            return $proyectos;
        } else {
            $proyectos = []; // evita undefined variable
            return $proyectos;
        }
    }
    //Datos filtros GENERAL
    public function filtros($id, $rol)
    {
        global $conn;
        $proyecto = new Proyectos($conn);
        //Datos filtros
        if ($rol == "investigador" || $rol == "alumno" || $rol == "supervisor") {
            $proyectos = $proyecto->obtenerProyectosDatosFiltro($id, $rol);
            return $proyectos;
        } else {
            $proyectos = []; // evita undefined variable
            return $proyectos;
        }
    }
    //Datos tabla por filtro
    //Total
    public function Total($id, $rol, $buscar = null)
    {
        global $conn;
        $proyecto = new Proyectos($conn);
        //Datos filtros
        if ($rol == "investigador" || $rol == "alumno" || $rol == "supervisor") {
            $proyectos = $proyecto->obtenerProyectosTablaFiltro($id, 0, $rol, $buscar);
            return $proyectos;
        } else {
            $proyectos = []; // evita undefined variable
            return $proyectos;
        }
    }
    //Cierre
    public function Cierre($id, $rol, $buscar = null)
    {
        global $conn;
        $proyecto = new Proyectos($conn);
        //Datos filtros
        if ($rol == "investigador" || $rol == "alumno" || $rol == "supervisor") {
            $proyectos = $proyecto->obtenerProyectosTablaFiltro($id, 1, $rol, $buscar);
            return $proyectos;
        } else {
            $proyectos = []; // evita undefined variable
            return $proyectos;
        }
    }
    //Activos
    public function Activos($id, $rol, $buscar = null)
    {
        global $conn;
        $proyecto = new Proyectos($conn);
        //Datos filtros
        if ($rol == "investigador" || $rol == "alumno" || $rol == "supervisor") {
            $proyectos = $proyecto->obtenerProyectosTablaFiltro($id, 2, $rol, $buscar);
            return $proyectos;
        } else {
            $proyectos = []; // evita undefined variable
            return $proyectos;
        }
    }
    //PorAprobar
    public function PorAprobar($id, $rol, $buscar = null)
    {
        global $conn;
        $proyecto = new Proyectos($conn);
        //Datos filtros
        if ($rol == "investigador" || $rol == "alumno" || $rol == "supervisor") {
            $proyectos = $proyecto->obtenerProyectosTablaFiltro($id, 3, $rol, $buscar);
            return $proyectos;
        } else {
            $proyectos = []; // evita undefined variable
            return $proyectos;
        }
    }
    //Rechazados
    public function Rechazados($id, $rol, $buscar = null)
    {
        global $conn;
        $proyecto = new Proyectos($conn);
        //Datos filtros
        if ($rol == "investigador" || $rol == "alumno" || $rol == "supervisor") {
            $proyectos = $proyecto->obtenerProyectosTablaFiltro($id, 4, $rol, $buscar);
            return $proyectos;
        } else {
            $proyectos = []; // evita undefined variable
            return $proyectos;
        }
    }

    //PorCerrar
    public function PorCerrar($id, $rol, $buscar = null)
    {
        global $conn;
        $proyecto = new Proyectos($conn);
        //Datos filtros
        if ($rol == "investigador" || $rol == "alumno" || $rol == "supervisor") {
            $proyectos = $proyecto->obtenerProyectosTablaFiltro($id, 5, $rol, $buscar);
            return $proyectos;
        } else {
            $proyectos = []; // evita undefined variable
            return $proyectos;
        }
    }

    //Paginación
    public function tabla($id, $rol, $buscar = null)
    {
        global $conn;
        $proyecto = new Proyectos($conn);

        // Filtro actual
        $numerofiltro = $this->numerofiltro();

        // Cantidad totals
        $total_proyectos = $proyecto->obtenerCantidadProyectos($id, $numerofiltro, $rol, $buscar);

        // Parámetros de paginación
        $por_pagina = 6;

        $pagina = empty($_GET['pagina']) ? 1 : intval($_GET['pagina']);
        $desde = ($pagina - 1) * $por_pagina;

        $total_paginas = ($total_proyectos > 0)
            ? ceil($total_proyectos / $por_pagina)
            : 1;

        // Retornamos TODOS los datos en un arreglo
        return [
            "total_proyectos" => $total_proyectos,
            "por_pagina"     => $por_pagina,
            "pagina"         => $pagina,
            "desde"          => $desde,
            "total_paginas"  => $total_paginas
        ];
    }

    public function encabezados($rol)
    {
        switch ($rol) {
            case 'alumno':
                $encabezados = [
                    'ID',
                    'Título',
                    'Fecha Inicio',
                    'Fecha Fin',
                    'Estado',
                    'Período',
                    'Tareas pendientes',
                    'Acciones'
                ];
                break;
            case 'investigador':
            case 'profesor':
                $encabezados = [
                    'ID',
                    'Título',
                    'Fecha Inicio',
                    'Fecha Fin',
                    'Estado',
                    'Período',
                    'Avances por revisar',
                    'Acciones'
                ];
                break;
            case 'supervisor':
                $encabezados = [
                    'ID',
                    'Título',
                    'Fecha Inicio',
                    'Fecha Fin',
                    'Estado',
                    'Período',
                    'Acciones'
                ];
                break;
            default:
                $encabezados = [];
                break;
        }
        return $encabezados;
    }

    public function datosopciones($rol, $filtros)
    {
        switch ($rol) {
            case 'alumno':
                $opciones = [
                    'Total'       => "Total ({$filtros[0]['Total']})",
                    'Activos'     => "Activos ({$filtros[0]['Activos']})",
                    'Cierre'      => "Cierre ({$filtros[0]['Cierre']})",
                    'PorCerrar'   => "Por Cerrar ({$filtros[0]['PorCerrar']})",
                ];
                break;
            case 'investigador':
            case 'profesor':
                $opciones = [
                    'Total'       => "Total ({$filtros[0]['Total']})",
                    'Activos'     => "Activos ({$filtros[0]['Activos']})",
                    'PorAprobar'  => "Por Aprobar ({$filtros[0]['PorAprobar']})",
                    'Cierre'      => "Cierre ({$filtros[0]['Cierre']})",
                    'PorCerrar'   => "Por Cerrar ({$filtros[0]['PorCerrar']})",
                    'Rechazados'  => "Rechazados ({$filtros[0]['Rechazados']})"
                ];
                break;
            case 'supervisor':
                $opciones = [
                    'Total'       => "Total ({$filtros[0]['Total']})",
                    'Activos'     => "Activos ({$filtros[0]['Activos']})",
                    'PorAprobar'  => "Por Aprobar ({$filtros[0]['PorAprobar']})",
                    'Cierre'      => "Cierre ({$filtros[0]['Cierre']})",
                    'PorCerrar'   => "Por Cerrar ({$filtros[0]['PorCerrar']})",
                    'Rechazados'  => "Rechazados ({$filtros[0]['Rechazados']})"
                ];
                break;
            default:
                $opciones = [];
                break;
        }
        return $opciones;
    }

    //Botones de acción en la tabla
    public function botonesAccion($id, $rol, $proyecto)
    {
        $boton = "";
        switch ($rol) {
            case 'alumno':
                $boton = "
                    <a href=\"tareas.php? id_proyectos=<?php echo $id; ?>\">
                    <button type=\"button\" class=\"btn btn-primary\">
                    <svg xmlns=\"http://www.w3.org/2000/svg\" width=\"18\" height=\"18\" fill=\"currentColor\" class=\"bi bi-eye-fill\" style=\"padding:0px;margin:auto;\" viewBox=\"0 0 16 16\">
  <path d=\"M10.5 8a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0\"/>
  <path d=\"M0 8s3-5.5 8-5.5S16 8 16 8s-3 5.5-8 5.5S0 8 0 8m8 3.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7\"/>
</svg>
                    </button></a>";
                break;
            case 'investigador':
            case 'profesor':
                if ($proyecto['nombre'] == "Activo") {
                    $boton = "
                    <a href=\"editar.php? id_proyectos=<?php echo $id; ?>\">
                    <button type=\"button\" class=\"btn btn-success\" style=\"background-color: var(--color-boton-modificar); border-color: var(--color-boton-modificar);\">
                    <svg xmlns=\"http://www.w3.org/2000/svg\" width=\"20\" height=\"20\" fill=\"currentColor\" class=\"bi bi-pencil-square\" viewBox=\"0 0 16 16\">
  <path d=\"M15.502 1.94a.5.5 0 0 1 0 .706L14.459 3.69l-2-2L13.502.646a.5.5 0 0 1 .707 0l1.293 1.293zm-1.75 2.456-2-2L4.939 9.21a.5.5 0 0 0-.121.196l-.805 2.414a.25.25 0 0 0 .316.316l2.414-.805a.5.5 0 0 0 .196-.12l6.813-6.814z\"/>
  <path fill-rule=\"evenodd\" d=\"M1 13.5A1.5 1.5 0 0 0 2.5 15h11a1.5 1.5 0 0 0 1.5-1.5v-6a.5.5 0 0 0-1 0v6a.5.5 0 0 1-.5.5h-11a.5.5 0 0 1-.5-.5v-11a.5.5 0 0 1 .5-.5H9a.5.5 0 0 0 0-1H2.5A1.5 1.5 0 0 0 1 2.5z\"/>
</svg>
                    </button></a>
                    <a href=\"tareas.php? id_proyectos=<?php echo $id; ?>\">
                    <button type=\"button\" class=\"btn btn-primary\">
                    <svg xmlns=\"http://www.w3.org/2000/svg\" width=\"20\" height=\"20\" fill=\"currentColor\" class=\"bi bi-eye-fill\" style=\"padding:0;margin:auto;\" viewBox=\"0 0 16 16\">
  <path d=\"M10.5 8a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0\"/>
  <path d=\"M0 8s3-5.5 8-5.5S16 8 16 8s-3 5.5-8 5.5S0 8 0 8m8 3.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7\"/>
</svg>
                    </button></a>
                    <a href=\"tabla.php? action=cerrarproyecto&id_proyectos=<?php echo $id; ?>\">
                    <button type=\"button\" class=\"btn btn-danger\">
                    <svg xmlns=\"http://www.w3.org/2000/svg\" width=\"18\" height=\"18\" fill=\"currentColor\" class=\"bi bi-ban\" style=\"padding:0;margin:auto;\" viewBox=\"0 0 16 16\">
  <path d=\"M15 8a6.97 6.97 0 0 0-1.71-4.584l-9.874 9.875A7 7 0 0 0 15 8M2.71 12.584l9.874-9.875a7 7 0 0 0-9.874 9.874ZM16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0\"/>
</svg></button></a>";
                } else if ($proyecto['nombre'] == "Sin aprobar") {
                    $boton = "
                    <a href=\"tareas.php? id_proyectos=<?php echo $id; ?>\">
                    <button type=\"button\" class=\"btn btn-primary\">
                    <svg xmlns=\"http://www.w3.org/2000/svg\" width=\"18\" height=\"18\" fill=\"currentColor\" class=\"bi bi-eye-fill\" style=\"padding:0px;margin:auto;\" viewBox=\"0 0 16 16\">
  <path d=\"M10.5 8a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0\"/>
  <path d=\"M0 8s3-5.5 8-5.5S16 8 16 8s-3 5.5-8 5.5S0 8 0 8m8 3.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7\"/>
</svg>
                    </button></a>";
                } else if ($proyecto['nombre'] == "Por cerrar") {
                    $boton = "
                    <a href=\"tareas.php? id_proyectos=<?php echo $id ?>\">
                    <button type=\"button\" class=\"btn btn-primary\">
                    <svg xmlns=\"http://www.w3.org/2000/svg\" width=\"18\" height=\"18\" fill=\"currentColor\" class=\"bi bi-eye-fill\" style=\"padding:0px;margin:auto;\" viewBox=\"0 0 16 16\">
  <path d=\"M10.5 8a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0\"/>
  <path d=\"M0 8s3-5.5 8-5.5S16 8 16 8s-3 5.5-8 5.5S0 8 0 8m8 3.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7\"/>
</svg>
                    </button></a>";
                } else if ($proyecto['nombre'] == "Cierre") {
                    $boton = "
                    <a href=\"tareas.php? id_proyectos=<?php echo $id; ?>\">
                    <button type=\"button\" class=\"btn btn-primary\">
                    <svg xmlns=\"http://www.w3.org/2000/svg\" width=\"18\" height=\"18\" fill=\"currentColor\" class=\"bi bi-eye-fill\" style=\"padding:0px;margin:auto;\" viewBox=\"0 0 16 16\">
  <path d=\"M10.5 8a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0\"/>
  <path d=\"M0 8s3-5.5 8-5.5S16 8 16 8s-3 5.5-8 5.5S0 8 0 8m8 3.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7\"/>
</svg>
                    </button></a>";
                }
                break;
            case 'supervisor':
                if ($proyecto['nombre'] == "Activo") {
                    $boton = "
                    </button></a>
                    <a href=\"tareas.php? id_proyectos=<?php echo $id; ?>\">
                    <button type=\"button\" class=\"btn btn-primary\">
                    <svg xmlns=\"http://www.w3.org/2000/svg\" width=\"20\" height=\"20\" fill=\"currentColor\" class=\"bi bi-eye-fill\" style=\"padding:0;margin:auto;\" viewBox=\"0 0 16 16\">
  <path d=\"M10.5 8a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0\"/>
  <path d=\"M0 8s3-5.5 8-5.5S16 8 16 8s-3 5.5-8 5.5S0 8 0 8m8 3.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7\"/>
</svg>";
                } else if ($proyecto['nombre'] == "Por avalar") {
                    $boton = "
                    <a href=\"tareas.php? id_proyectos=<?php echo $id; ?>\">
                    <button type=\"button\" class=\"btn btn-success\">
<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"20\" height=\"20\" fill=\"currentColor\" class=\"bi bi-check-circle-fill\" viewBox=\"0 0 16 16\">
  <path d=\"M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0m-3.97-3.03a.75.75 0 0 0-1.08.022L7.477 9.417 5.384 7.323a.75.75 0 0 0-1.06 1.06L6.97 11.03a.75.75 0 0 0 1.079-.02l3.992-4.99a.75.75 0 0 0-.01-1.05z\"/>
</svg>
                    </button></a>
                    
                    </button></a>
                    <a href=\"tareas.php? id_proyectos=<?php echo $id; ?>\">
                    <button type=\"button\" class=\"btn btn-primary\">
                    <svg xmlns=\"http://www.w3.org/2000/svg\" width=\"20\" height=\"20\" fill=\"currentColor\" class=\"bi bi-eye-fill\" style=\"padding:0;margin:auto;\" viewBox=\"0 0 16 16\">
  <path d=\"M10.5 8a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0\"/>
  <path d=\"M0 8s3-5.5 8-5.5S16 8 16 8s-3 5.5-8 5.5S0 8 0 8m8 3.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7\"/>
</svg>

</button></a>
                    <a href=\"tabla.php? action=proyectonoaprobado&id_proyectos=<?php echo $id; ?>\">
                    <button type=\"button\" class=\"btn btn-danger\">
                    <svg xmlns=\"http://www.w3.org/2000/svg\" width=\"18\" height=\"18\" fill=\"currentColor\" class=\"bi bi-ban\" style=\"padding:0;margin:auto;\" viewBox=\"0 0 16 16\">
  <path d=\"M15 8a6.97 6.97 0 0 0-1.71-4.584l-9.874 9.875A7 7 0 0 0 15 8M2.71 12.584l9.874-9.875a7 7 0 0 0-9.874 9.874ZM16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0\"/>
</svg></button></a>
                    ";
                } else if ($proyecto['nombre'] == "Por cerrar") {
                    $boton = "
                    </button></a>
                    <a href=\"tareas.php? id_proyectos=<?php echo $id; ?>\">
                    <button type=\"button\" class=\"btn btn-primary\">
                    <svg xmlns=\"http://www.w3.org/2000/svg\" width=\"20\" height=\"20\" fill=\"currentColor\" class=\"bi bi-eye-fill\" style=\"padding:0;margin:auto;\" viewBox=\"0 0 16 16\">
  <path d=\"M10.5 8a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0\"/>
  <path d=\"M0 8s3-5.5 8-5.5S16 8 16 8s-3 5.5-8 5.5S0 8 0 8m8 3.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7\"/>
</svg>

</button></a>
                    <a href=\"tabla.php? action=proyectonoaprobado&id_proyectos=<?php echo $id; ?>\">
                    <button type=\"button\" class=\"btn btn-danger\">
                    <svg xmlns=\"http://www.w3.org/2000/svg\" width=\"18\" height=\"18\" fill=\"currentColor\" class=\"bi bi-ban\" style=\"padding:0;margin:auto;\" viewBox=\"0 0 16 16\">
  <path d=\"M15 8a6.97 6.97 0 0 0-1.71-4.584l-9.874 9.875A7 7 0 0 0 15 8M2.71 12.584l9.874-9.875a7 7 0 0 0-9.874 9.874ZM16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0\"/>
</svg></button></a>";
                } else if ($proyecto['nombre'] == "Cierre") {
                    $boton = "
                    </button></a>
                    <a href=\"tareas.php? id_proyectos=<?php echo $id; ?>\">
                    <button type=\"button\" class=\"btn btn-primary\">
                    <svg xmlns=\"http://www.w3.org/2000/svg\" width=\"20\" height=\"20\" fill=\"currentColor\" class=\"bi bi-eye-fill\" style=\"padding:0;margin:auto;\" viewBox=\"0 0 16 16\">
  <path d=\"M10.5 8a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0\"/>
  <path d=\"M0 8s3-5.5 8-5.5S16 8 16 8s-3 5.5-8 5.5S0 8 0 8m8 3.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7\"/>
</svg>

</button></a>";
                }
                break;
            default:
                $proyecto = null;
                break;
        }
        return $boton;
    }
        //CREAR PROYECTO
    public function tematica()
    {
        global $conn;
        $proyecto = new Proyectos($conn);

        $tematica = $proyecto->tematica();
        if ($tematica != []) {
            return $tematica;
        } else {
            $tematica = []; // evita undefined variable
            return $tematica;
        }
    }

    public function subtematicas($id)
    {
        global $conn;
        $proyecto = new Proyectos($conn);
        return $proyecto->obtenersubtematica($id);
    }

    public function obtenerperiodo()
    {
        global $conn;
        $proyecto = new Proyectos($conn);
        return $proyecto->obtenerperiodo();
    }

    public function obtenerInstituto()
    {
        global $conn;
        $proyecto = new Proyectos($conn);
        return $proyecto->obtenerinstituto();
    }

    public function registrarProyecto($data, $id, $rol)
    {
        $periodoData = $this->obtenerperiodo();
        $periodo = $periodoData[0]; // tomas el primer registro
        $estado_periodo = $periodo["estado"]; // O "periodo", según lo que necesites

        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            if ($estado_periodo == "Activo" && $estado_periodo != "") {
                if ($rol == "investigador" || $rol == "profesor") {

                    $action = $data['action'] ?? '';

                    $id_investigador = $id;
                    $id_estadoP = 3;
                    $id_tematica = $data['Tematica'];
                    if ($id_tematica == "") {
                        die("Se debe elegir una temática");
                    }
                    $institutoData = $this->obtenerInstituto();
                    $instituto = $institutoData[0]; // tomas el primer registro
                    $id_instituto = $instituto['id_instituto'];
                    $id_periodos = $periodo["id_periodos"];
                    $titulo = $data['NombreProyecto'];
                    $descripcion = $data['Descripcion'];
                    $objetivo = $data['Objetivos'];
                    $fecha_inicio = $data['FechaInicio'];
                    $fecha_final = $data['FechaFinal'];
                    $presupuesto = $data['Presupuesto'];

                    $actualizado_en = null;
                    $requisitos = $data['Requisitos'];

                    $Pre_requisitos = $data['Pre_requisitos'];
                    $AlumnosCantidad = $data['AlumnosCantidad'];

                    $modalidad = $data['Modalidad'];
                    $id_subtematica = $data['Subtematica'];

                    if ($id_subtematica == "") {
                        die("Se debe elegir una Subtematica");
                    }
                    if ($action === 'registrarProyecto') {
                        global $conn;
                        $proyecto = new Proyectos($conn);
                        $proyecto->registrarProyecto($id_investigador, $id_estadoP, $id_tematica, $id_instituto, $id_periodos, $titulo, $descripcion, $objetivo, $fecha_inicio, $fecha_final, $presupuesto, $actualizado_en, $requisitos, $Pre_requisitos, $modalidad, $AlumnosCantidad);
                    }
                } else {
                    die("El usuario no tiene permiso para crear el proyecto");
                }
            } else {
                die("El periodo ha acabado para registrar proyectos");
            }
        } else {
            die("Los datos no fueron enviados ha acabado para registrar proyectos");
        }
    }
}
