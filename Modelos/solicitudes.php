<?php
require_once __DIR__ . '/../publico/config/conexion.php';

class Solicitud
{

    private $con;

    public function __construct($conn)
    {
        $this->con = $conn;
    }

    //DATOS GENERALES SIN FILTRO
    // Reemplaza la función con esta versión
    public function obtenerSolicitudes($id, $rol)
    {
        // Normalizar rol para evitar problemas de mayúsculas/minúsculas
        $rol = strtolower($rol);

        // Cantidad totales
        $total_solicitudes = $this->obtenerCantidadSolicitudes($id, $rol);

        // Parámetros de paginación
        $por_pagina = 6;
        $pagina = empty($_GET['pagina']) ? 1 : intval($_GET['pagina']);
        $desde = ($pagina - 1) * $por_pagina;

        $total_paginas = ($total_solicitudes > 0) ? ceil($total_solicitudes / $por_pagina) : 1;

        // Inicializar variables
        $sql = "";
        $params = [];
        $types = "";
        $whereAdded = false;

        // Consultas base según rol
        switch ($rol) {
            case 'estudiante':
            case 'alumno': // por si usas 'alumno' en algún lugar
                $sql = "SELECT proy.id_proyectos, proy.titulo, proy.fecha_inicio, proy.fecha_fin, espr.nombre, peri.periodo, 
                           COUNT(CASE WHEN tbse.id_estadoT = 1 THEN 1 END) AS total
                    FROM gestion_proyectos.proyectos AS proy
                    ... = ? ";
                $params[] = $id;
                $types .= "i";
                $whereAdded = true;
                break;

            case 'investigador':
            case 'profesor':
                $sql = "SELECT proy.id_proyectos, proy.titulo, proy.fecha_inicio, proy.fecha_fin, espr.nombre, peri.periodo,
                           COUNT(CASE WHEN tbse.id_estadoT = 2 THEN 1 END) AS total
                    FROM gestion_proyectos.proyectos AS proy
                    ... = ? ";
                $params[] = $id;
                $types .= "i";
                $whereAdded = true;
                break;

            case 'supervisor':
                $sql = "SELECT proy.id_proyectos, proy.titulo, proy.fecha_inicio, proy.fecha_fin, espr.nombre, peri.periodo
                    FROM gestion_proyectos.proyectos AS proy
                    ... ";
                // supervisor no añade WHERE por defecto
                $whereAdded = false;
                break;

            default:
                // Si el rol es inesperado devolvemos vacío (evita errores posteriores)
                return json_encode([
                    "proyectos" => [],
                    "paginacion" => [
                        "total_proyectos" => 0,
                        "por_pagina"      => $por_pagina,
                        "pagina"          => $pagina,
                        "total_paginas"   => 1
                    ]
                ]);
        }


        // GROUP BY y LIMIT al final (LIMIT siempre al final de la query)
        $sql .= " GROUP BY proy.id_solicitud_proyecto ORDER BY proy.id_solicitud_proyecto ASC LIMIT ?, ?";

        // Añadir params para paginación (siempre enteros)
        $params[] = $desde;
        $params[] = $por_pagina;
        $types .= "ii";

        // Preparar y ejecutar
        $stmt = $this->con->prepare($sql);
        if (!$stmt) {
            die("Error en prepare(): " . $this->con->error . "<br>SQL: $sql");
        }

        // bind_param requiere tipos y valores; si types está vacío no bindear
        if ($types !== "") {
            // Usar operador splat para pasar los parámetros
            $stmt->bind_param($types, ...$params);
        }

        if (!$stmt->execute()) {
            die("Error en execute(): " . $stmt->error . "<br>SQL: $sql");
        }

        $resultado = [
            "proyectos" => $stmt->get_result()->fetch_all(MYSQLI_ASSOC),
            "paginacion" => [
                "total_solicitudes" => $total_solicitudes,
                "por_pagina"      => $por_pagina,
                "pagina"          => $pagina,
                "total_paginas"   => $total_paginas
            ]
        ];

        return json_encode($resultado);
    }

        //OBTENER LA CANTIDAD DE SOLICITUDES
    public function obtenerCantidadSolicitudes($id, $rol)
    {

        
            switch ($rol) {
                case 'estudiante':
                    $sql = "SELECT COUNT(*) AS total_solicitudes FROM gestion_proyectos.solicitud_proyecto as proy 
....";

                    $params = [$id];
                    $types  = "i";

                    $stmt = $this->con->prepare($sql);
                    $stmt->bind_param($types, ...$params);
                    break;
                case 'investigador':
                case 'profesor':
                case 'supervisor':
                    $sql = "SELECT COUNT(*) AS total_solicitudes FROM gestion_proyectos.solicitud_proyecto as proy 
...";

                    $params = [$id];
                    $types  = "i";


                    $stmt = $this->con->prepare($sql);
                    $stmt->bind_param($types, ...$params);
                    break;
                default:
                    break; // Retorna 0 si el rol no es válido
            }
            $stmt->execute();
            $resultado = $stmt->get_result()->fetch_assoc();
            return $resultado['total_solicitudes'];   // OBTENER EL NUMERO TOTAL DE PROYECTOS
        
    }


    //ACCIÓN DE RECHAZO DE CIERRE
    public function actualizarEstadoSolicitudRechazo($id_usuario, $id_solicitud_proyecto, $tipo, $comentario)
    {
        //Actualizar estado
        if ($tipo == "rechazado") {
            $motivo = "rechazado";
        }

        $sql = "UPDATE solicitud_proyectos SET estado = ?, WHERE id_solicitud_proyecto = ?";
        $stmt = $this->con->prepare($sql);

        $stmt->bind_param("ii", $motivo, $id_solicitud_proyecto);
        if (!$stmt) {
            die("Error en prepare(): " . $this->con->error);
        }

        if (!$stmt->execute()) {
            die("Error en execute(): " . $stmt->error);
        }
        // Insertar comentario
        $sql = "INSERT INTO solicitud_proyectos 
            (id_solicitud_proyecto, id_usuario, estado, comentario)
            VALUES (?, ?, ?, ?)";

        $stmt = $this->con->prepare($sql);

        $stmt->bind_param("iiss", $id_solicitud_proyecto, $id_usuario, $tipo, $comentario);


        if (!$stmt) {
            die("Error en prepare(): " . $this->con->error);
        }

        if (!$stmt->execute()) {
            die("Error en execute(): " . $stmt->error);
        }
        header("Location: tabla.php?msg=mensaje");
        exit;
    }

    public function actualizarestado($id_solicitud_proyecto, $motivo)
    {
        $sql = "UPDATE solicitud_proyectos SET estado = ?, WHERE id_solicitud_proyecto = ?";

        $stmt = $this->con->prepare($sql);
        $stmt->bind_param("si", $motivo, $id_solicitud_proyecto);
        if (!$stmt) {
            die("Error en prepare(): " . $this->con->error);
        }

        if (!$stmt->execute()) {
            die("Error en execute(): " . $stmt->error);
        }

        header("Location: tabla.php?msg=mensaje");
        exit;
    }




    public function obtenerSolicitudComentarios($id_proyecto)
    {
        $sql = "...;";

        $params = [$id_proyecto];
        $types  = "i";

        $stmt = $this->con->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}

