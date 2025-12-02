<?php
require_once __DIR__ . '/../publico/config/conexion.php';

class Tarea
{

    private $con;

    public function __construct($conn)
    {
        $this->con = $conn;
    }

    //ACTUALIZAR A VENCIDO LOS PROYECTOS
    public function actualizarTareasVencidos()
    {
        $hoy = date("Y-m-d");

        $sql = "UPDATE tbl_seguimiento 
            SET id_estadoT = 6
            WHERE id_estadoT IN (1, 2, 3)
              AND fecha_entrega < ?";

        $stmt = $this->con->prepare($sql);
        $stmt->bind_param("s", $hoy);
        $stmt->execute();
    }

    //DATOS PRINCIPAL
    public function obtenerTareas($id_proyecto, $id_usuario, $rol)
    {
        switch ($rol) {
            case 'estudiante':

                $sql = "SELECT 
                s.id_avances,
                tt.descripcion_tipo AS tipo,
                s.archivo_guia,
                s.archivo_nombre,
                s.archivo_tipo,
                s.fecha_entrega,
                es.nombre as estado
            FROM tbl_seguimiento s
            INNER JOIN tareas t ON s.id_tarea = t.id_tarea
            INNER JOIN tipo_tarea tt ON t.id_tipotarea = tt.id_tareatipo
            INNER JOIN estados_tarea es ON s.id_estadoT = es.id_estadoT
            INNER JOIN tareas_usuarios taus ON taus.id_tarea = t.id_tarea
            WHERE s.id_proyectos = ? AND taus.id_usuario = ? AND es.id_estadoT != 4
            ORDER BY s.id_tarea ASC";
                $stmt = $this->con->prepare($sql);
                $stmt->bind_param("ii", $id_proyecto, $id_usuario);
                break;
            case 'profesor':
            case 'investigador':
            case 'supervisor':
                $sql = "SELECT 
                s.id_avances,
                tt.descripcion_tipo AS tipo,
                es.nombre AS estado,
                s.archivo_guia,
                s.fecha_entrega,

                -- Cantidad de Estudiantes que entregaron
                (
                    SELECT COUNT(*) 
                    FROM tbl_seguimiento sx
                    WHERE sx.id_proyectos = s.id_proyectos 
                      AND sx.id_tarea = s.id_tarea
                      AND sx.archivo_guia IS NOT NULL
                ) AS entregado,

                -- Total de Estudiantes del proyecto
                (
                    SELECT COUNT(*) 
                    FROM proyectos_usuarios up
                    WHERE up.id_proyectos = s.id_proyectos
                ) AS total_Estudiantes

            FROM tbl_seguimiento s
            INNER JOIN tareas t ON s.id_tarea = t.id_tarea
            INNER JOIN tipo_tarea tt ON t.id_tipotarea = tt.id_tareatipo
            INNER JOIN estados_tarea es ON s.id_estadoT = es.id_estadoT
            WHERE s.id_proyectos = ?
            ORDER BY s.id_tarea ASC";
                $stmt = $this->con->prepare($sql);
                $stmt->bind_param("i", $id_proyecto);
                break;
            default:
                break;
        }
        $stmt->execute();

        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    //DATOS LISTA PROYECTO
    public function obtenerTareasLista($id_avances, $rol)
    {
        switch ($rol) {
            case 'profesor':
            case 'investigador':
            case 'supervisor':
                $sql = "
            SELECT 
    tu.id_asignacion,
    u.id_usuario,
    CONCAT(u.nombre, ' ', u.apellido_p, ' ', u.apellido_m) AS estudiante,
    et.estado AS estados_tarea,
    tu.fecha_revision AS fecha_entrega,
    tu.id_tarea
FROM tareas_usuarios tu
INNER JOIN usuarios u 
        ON tu.id_usuario = u.id_usuario
INNER JOIN estados_tarea et 
        ON tu.id_estadoT = et.id_estadoT
WHERE tu.id_tarea = ?
ORDER BY u.nombre ASC;
";
                $stmt = $this->con->prepare($sql);
                $stmt->bind_param("i", $id_avances);
                break;
            default:
                break;
        }
        $stmt->execute();


        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    //ACTUALIZAR TAREA
    public function editarTareaGeneral($id_tareas, $descripcion, $instrucciones, $fecha_entrega, $archivo_guia, $archivo_nombre, $archivo_tipo)
    {
        // 1. ACTUALIZAR TABLA tareas
        $sqlTarea = "
        UPDATE tareas
        SET descripcion = ?, instrucciones = ?
        WHERE id_tarea = ?
    ";

        $stmt1 = $this->con->prepare($sqlTarea);
        if (!$stmt1) {
            die("Error en prepare tareas: " . $this->con->error);
        }

        $stmt1->bind_param("ssi", $descripcion, $instrucciones, $id_tareas);

        if (!$stmt1->execute()) {
            die("Error en execute tareas: " . $stmt1->error);
        }


        // 2. ACTUALIZAR TABLA tbl_seguimiento
        $sqlSeg = "
        UPDATE tbl_seguimiento
        SET fecha_entrega = ?,
            archivo_guia  = ?,
            archivo_nombre = ?,
            archivo_tipo   = ?
        WHERE id_tarea = ?
    ";

        $stmt2 = $this->con->prepare($sqlSeg);
        if (!$stmt2) {
            die("Error en prepare seguimiento: " . $this->con->error);
        }

        $stmt2->bind_param(
            "sbssi",
            $fecha_entrega,
            $archivo_guia,      // BLOB
            $archivo_nombre,
            $archivo_tipo,
            $id_tareas
        );

        // Para blobs se debe activar:
        if (!empty($archivo_guia)) {
            $stmt2->send_long_data(1, $archivo_guia);
        }

        if (!$stmt2->execute()) {
            die("Error en execute seguimiento: " . $stmt2->error);
        }

        header("Location: editar.php?msg=mensaje");
        exit();
    }
    //Estudiante
    public function editarTareaEstudiante($id_asignacion, $id_tarea, $id_estudiante, $contenidoJSON, $archivo = null)
    {
        $sql = "UPDATE tareas_usuarios 
            SET contenido = ?, 
                fecha_entrega = NOW(),
                id_estadoT = 2, /* 2 = Revisar */
                archivo = COALESCE(?, archivo),
                archivo_nombre = COALESCE(?, archivo_nombre),
                archivo_tipo = COALESCE(?, archivo_tipo)
            WHERE id_asignacion = ?
              AND id_tarea = ?
              AND id_estudiante = ?";

        $stmt = $this->con->prepare($sql);

        if (!$stmt) {
            die("Error al preparar consulta: " . $this->con->error);
        }

        $archivo_blob   = $archivo['data'] ?? null;
        $archivo_nombre = $archivo['name'] ?? null;
        $archivo_tipo   = $archivo['type'] ?? null;

        $stmt->bind_param(
            "ssssiii",
            $contenidoJSON,
            $archivo_blob,
            $archivo_nombre,
            $archivo_tipo,
            $id_asignacion,
            $id_tarea,
            $id_estudiante
        );

        return $stmt->execute();
    }
    //Investigador
    public function editarTareaRevisar($id_tarea, $id_estudiante, $comentarios, $nuevoEstado)
    {
        $sql = "UPDATE tareas_usuarios
            SET comentarios = ?,
                id_estadoT = ?,
                fecha_revision = NOW()
            WHERE id_tarea = ?
              AND id_estudiante = ?";

        $stmt = $this->con->prepare($sql);
        if (!$stmt) {
            die("Error al preparar: " . $this->con->error);
        }

        $stmt->bind_param("siii", $comentarios, $nuevoEstado, $id_tarea, $id_estudiante);

        $stmt->execute();
        header("Location: editar.php?msg=mensaje");
        exit();
    }


    public function actualizarestado($id_asignacion, $numeroEstado)
    {
        if ($numeroEstado == 1) { //Pendiente
            $sql = "UPDATE tareas_usuarios SET id_estadoT = ? WHERE id_asignacion = ?";
        } else if ($numeroEstado == 2) { //Revisar
            $sql = "UPDATE tareas_usuarios SET id_estadoT = ?, fecha_revision = CURDATE() WHERE id_asignacion = ?";
        } else if ($numeroEstado == 3) { //Corregir
            $sql = "UPDATE tareas_usuarios SET id_estadoT = ?, fecha_correccion= CURDATE() WHERE id_asignacion = ?";
        } else if ($numeroEstado == 5) { //Aprobar
            $sql = "UPDATE tareas_usuarios SET id_estadoT = ?, fecha_aprobacion= CURDATE() WHERE id_asignacion = ?";
        }


        $stmt = $this->con->prepare($sql);
        $stmt->bind_param("ii", $numeroEstado, $id_asignacion);
        if (!$stmt) {
            die("Error en prepare(): " . $this->con->error);
        }

        if (!$stmt->execute()) {
            die("Error en execute(): " . $stmt->error);
        }
        header("Location: lista_tareas.php?msg=mensaje");
        exit;
    }

    // DETALLES DE LA TAREA (Estudiante)
    function obtenerTareaAlumno($id_asignacion)
    {
        $sql = "
        SELECT 
            a.id_tarea,
            a.archivo,
            a.archivo_nombre,
            a.archivo_tipo,

            t.instrucciones,
            
            tt.descripcion_tipo AS tipo_tarea,

            a.contenido,
            a.comentarios
        FROM tbl_seguimiento s
        INNER JOIN tareas t          ON t.id_tarea = s.id_tarea
        INNER JOIN tipo_tarea tt     ON tt.id_tareatipo = t.id_tipotarea
        INNER JOIN tareas_usuarios a 
                                     ON a.id_tarea = s.id_tarea 
                                     AND a.id_asignacion = ?
        LIMIT 1
    ";

        $stmt = $this->con->prepare($sql);

        if (!$stmt) {
            die("Error al preparar consulta: " . $this->con->error);
        }

        $stmt->bind_param("i", $id_asignacion);
        $stmt->execute();

        $resultado = $stmt->get_result();
        return $resultado->fetch_assoc();
    }
    //Obtener información de tarea con seguimiento para modificar los datos
    function obtenerTareaGeneral($id_tarea)
    {
        // 1) OBTENER TAREA
        $sqlTarea = "SELECT 
                    tita.descripcion_tipo AS tipo,
                    tare.descripcion,
                    tare.instrucciones
                 FROM tareas AS tare
                 JOIN tipo_tarea AS tita ON tare.id_tarea = tita.id_tarea
                 WHERE tare.id_tarea = ?";

        $stmt1 = $this->con->prepare($sqlTarea);
        $stmt1->bind_param("i", $id_tarea);
        $stmt1->execute();
        $tarea = $stmt1->get_result()->fetch_assoc();


        // 2) OBTENER SEGUIMIENTO
        $sqlSeg = "SELECT 
                    fecha_entrega,
                    archivo_guia,
                    archivo_nombre,
                    archivo_tipo
               FROM tbl_seguimiento
               WHERE id_tarea = ?";

        $stmt2 = $this->con->prepare($sqlSeg);
        $stmt2->bind_param("i", $id_tarea);
        $stmt2->execute();
        $seguimiento = $stmt2->get_result()->fetch_assoc();

        // Empaquetar JSON
        return json_encode([
            "tarea"       => $tarea,
            "seguimiento" => $seguimiento
        ]);
    }
}
