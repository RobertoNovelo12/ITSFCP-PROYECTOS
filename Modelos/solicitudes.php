<?php
require_once __DIR__ . '/../publico/config/conexion.php';

class Solicitud
{

    private $con;

    public function __construct($conn)
    {
        $this->con = $conn;
    }

    /******************************************************************
     *  OBTENER SOLICITUDES (PAGINACIÓN + ROLES)
     ******************************************************************/
    public function obtenerSolicitudes($id, $rol)
    {
        $rol = strtolower($rol);

        // TOTAL DE REGISTROS
        $total_solicitudes = $this->obtenerCantidadSolicitudes($id, $rol);

        $por_pagina = 6;
        $pagina = empty($_GET['pagina']) ? 1 : intval($_GET['pagina']);
        $desde = ($pagina - 1) * $por_pagina;
        $total_paginas = max(1, ceil($total_solicitudes / $por_pagina));

        $params = [];
        $types = "";

        switch ($rol) {

            case 'estudiante':
                $sql = "SELECT 
            sp.id_solicitud_proyecto AS id_solicitud_proyectos,
            sp.estado AS Estado,
            sp.fecha_envio AS Fecha_solicitud,
            sp.carta_presentacion,
            sp.carta_aceptacion,
            u.nombre AS Estudiante,
            e.matricula AS Matricula,
            c.nombre_carrera AS Carrera,
            p.id_proyectos,
            p.titulo AS Proyecto
        FROM solicitud_proyecto sp
        INNER JOIN usuarios u ON sp.id_estudiante = u.id_usuarios
        LEFT JOIN estudiantes e ON sp.id_estudiante = e.id_usuario
        LEFT JOIN carreras c ON e.id_carrera = c.id_carrera
        INNER JOIN proyectos p ON sp.id_proyectos = p.id_proyectos
        WHERE sp.id_estudiante = ?
        ORDER BY sp.id_solicitud_proyecto DESC
        LIMIT ?, ?";
                $params = [$id, $desde, $por_pagina];
                $types = "iii";
                break;

            case 'investigador':
            case 'profesor':
            case 'supervisor':
                $sql = "SELECT 
            sp.id_solicitud_proyecto AS id_solicitud_proyectos,
            sp.estado AS Estado,
            sp.fecha_envio AS Fecha_solicitud,
            sp.carta_presentacion,
            sp.carta_aceptacion,
            u.nombre AS Estudiante,
            e.matricula AS Matricula,
            c.nombre_carrera AS Carrera,
            p.id_proyectos,
            p.titulo AS Proyecto
        FROM solicitud_proyecto sp
        INNER JOIN proyectos p ON sp.id_proyectos = p.id_proyectos
        INNER JOIN usuarios u ON sp.id_estudiante = u.id_usuarios
        LEFT JOIN estudiantes e ON sp.id_estudiante = e.id_usuario
        LEFT JOIN carreras c ON e.id_carrera = c.id_carrera
        WHERE p.id_investigador = ?
        ORDER BY sp.id_solicitud_proyecto DESC
        LIMIT ?, ?";
                $params = [$id, $desde, $por_pagina];
                $types = "iii";
                break;

            default:
                return [
                    "solicitudes" => [],
                    "paginacion" => [
                        "total_solicitudes" => 0,
                        "por_pagina" => $por_pagina,
                        "pagina" => $pagina,
                        "total_paginas" => 1
                    ]
                ];
        }

        // preparar
        $stmt = $this->con->prepare($sql);
        if (!$stmt) {
            die("ERROR SQL PREPARE: " . $this->con->error . "\nSQL: " . $sql);
        }

        if (!empty($types)) {
            // bind dinámico (splat)
            $stmt->bind_param($types, ...$params);
        }

        if (!$stmt->execute()) {
            die("ERROR SQL EXECUTE: " . $stmt->error . "\nSQL: " . $sql);
        }

        // ---- aquí usamos get_result si está disponible, sino fallback con bind_result ----
        $data = [];
        if (method_exists($stmt, 'get_result')) {
            $res = $stmt->get_result();
            $data = $res->fetch_all(MYSQLI_ASSOC);
        } else {
            // Fallback: obtener metadatos y bind_result dinámico
            $meta = $stmt->result_metadata();
            if ($meta) {
                $fields = [];
                $row = [];

                while ($field = $meta->fetch_field()) {
                    $fields[] = $field->name;
                    $row[$field->name] = null;
                }
                // preparar parametros por referencia
                $bindParams = [];
                foreach ($fields as $f) {
                    $bindParams[] = &$row[$f];
                }
                // ligar resultados
                call_user_func_array([$stmt, 'bind_result'], $bindParams);
                // fetch todos
                while ($stmt->fetch()) {
                    $r = [];
                    foreach ($fields as $f) {
                        $r[$f] = $row[$f];
                    }
                    $data[] = $r;
                }
                $meta->free();
            } else {
                // sin metadata -> no hay filas
                $data = [];
            }
        }

        return [
            "solicitudes" => $data,
            "paginacion" => [
                "total_solicitudes" => $total_solicitudes,
                "por_pagina" => $por_pagina,
                "pagina" => $pagina,
                "total_paginas" => $total_paginas
            ]
        ];
    }


    /******************************************************************
     * OBTENER CANTIDAD DE SOLICITUDES
     ******************************************************************/
    public function obtenerCantidadSolicitudes($id, $rol)
    {
        switch ($rol) {

            case 'estudiante':
                $sql = "SELECT COUNT(*) AS total
                    FROM solicitud_proyecto
                    WHERE id_estudiante = ?";
                break;

            case 'investigador':
            case 'profesor':
            case 'supervisor':
                $sql = "SELECT COUNT(*) AS total
                    FROM solicitud_proyecto sp
                    INNER JOIN proyectos p ON sp.id_proyectos = p.id_proyectos
                    WHERE p.id_investigador = ?"; 
                break;

            default:
                return 0;
        }

        $stmt = $this->con->prepare($sql);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc()['total'];
    }

    /******************************************************************
     * RECHAZAR SOLICITUD
     ******************************************************************/
    public function actualizarEstadoSolicitudRechazo($id_usuario, $id_solicitud_proyecto, $tipo, $comentario)
    {
        // UPDATE CORRECTO (SIN COMA ANTES DE WHERE)
        $sql = "UPDATE solicitud_proyecto 
                SET estado = ? 
                WHERE id_solicitud_proyecto = ?";

        $stmt = $this->con->prepare($sql);
        $stmt->bind_param("si", $tipo, $id_solicitud_proyecto);
        $stmt->execute();

        // INSERTAR COMENTARIO
        $sql = "INSERT INTO comentarios_solicitud 
                (id_solicitud_proyecto, id_usuario, comentario)
                VALUES (?, ?, ?)";

        $stmt = $this->con->prepare($sql);
        $stmt->bind_param("iis", $id_solicitud_proyecto, $id_usuario, $comentario);
        $stmt->execute();

        header("Location: tabla.php?msg=rechazada");
        exit;
    }

    /******************************************************************
     * ACEPTAR / CAMBIAR ESTADO
     ******************************************************************/
    public function actualizarestado($id_solicitud_proyecto, $estado)
    {
        $sql = "UPDATE solicitud_proyecto 
                SET estado = ? 
                WHERE id_solicitud_proyecto = ?";

        $stmt = $this->con->prepare($sql);
        $stmt->bind_param("si", $estado, $id_solicitud_proyecto);
        $stmt->execute();

        header("Location: tabla.php?msg=actualizada");
        exit;
    }

    /******************************************************************
     * OBTENER COMENTARIOS (CORREGIDO)
     ******************************************************************/
    public function obtenerSolicitudComentarios($id_solicitud)
    {
        $sql = "SELECT *
                FROM comentarios_solicitud
                WHERE id_solicitud_proyecto = ?
                ORDER BY id_comentario DESC";

        $stmt = $this->con->prepare($sql);
        $stmt->bind_param("i", $id_solicitud);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}
