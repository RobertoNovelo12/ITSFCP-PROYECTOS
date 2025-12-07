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

        // Ahora actualizamos tareas_usuarios en lugar de tbl_seguimiento
        $sql = "UPDATE tareas_usuarios AS tu
            INNER JOIN tareas AS t ON tu.id_tarea = t.id_tarea
            SET tu.id_estadoT = 6
            WHERE tu.id_estadoT IN (1, 2, 3)
              AND t.fecha_entrega < ?";

        $stmt = $this->con->prepare($sql);
        $stmt->bind_param("s", $hoy);
        $stmt->execute();
    }

    //DATOS PRINCIPAL
//DATOS PRINCIPAL
    public function obtenerTareas($id_proyecto, $id_usuario, $rol)
    {
        switch ($rol) {
            case 'estudiante':
                $sql = "SELECT 
                tu.id_asignacion,
                tt.descripcion_tipo AS tipo,
                t.archivo_guia,
                t.archivo_nombre,
                t.archivo_tipo,
                t.fecha_entrega,
                es.nombre as estado
            FROM tareas_usuarios tu
            INNER JOIN tareas t ON tu.id_tarea = t.id_tarea
            INNER JOIN tbl_seguimiento s ON s.id_avances = t.id_avances
            INNER JOIN tipo_tarea tt ON t.id_tipotarea = tt.id_tareatipo
            INNER JOIN estados_tarea es ON tu.id_estadoT = es.id_estadoT
            WHERE s.id_proyectos = ? 
              AND tu.id_usuario = ? 
              AND tu.id_estadoT != 4
            ORDER BY t.id_tarea ASC";

                $stmt = $this->con->prepare($sql);
                $stmt->bind_param("ii", $id_proyecto, $id_usuario);
                break;

            case 'profesor':
            case 'investigador':
            case 'supervisor':
                $sql = "SELECT 
                s.id_avances,
                t.id_tarea,
                tt.descripcion_tipo AS tipo,
                t.archivo_guia,
                t.fecha_entrega,
                
                -- Estado general (tomamos el más común o el primero)
                (SELECT es.nombre 
                 FROM tareas_usuarios tu2
                 INNER JOIN estados_tarea es ON tu2.id_estadoT = es.id_estadoT
                 WHERE tu2.id_tarea = t.id_tarea
                 LIMIT 1) AS estado,

                -- Cantidad de Estudiantes que entregaron (estado >= 2)
                (SELECT COUNT(*) 
                 FROM tareas_usuarios tu3
                 WHERE tu3.id_tarea = t.id_tarea
                   AND tu3.id_estadoT >= 2
                ) AS entregado,

                -- Total de Estudiantes asignados a esta tarea
                (SELECT COUNT(*) 
                 FROM tareas_usuarios tu4
                 WHERE tu4.id_tarea = t.id_tarea
                ) AS total_Estudiantes

            FROM tbl_seguimiento s
            INNER JOIN tareas t ON s.id_avances = t.id_avances
            INNER JOIN tipo_tarea tt ON t.id_tipotarea = tt.id_tareatipo
            WHERE s.id_proyectos = ?
            ORDER BY t.id_tarea ASC";

                $stmt = $this->con->prepare($sql);
                $stmt->bind_param("i", $id_proyecto);
                break;

            default:
                return [];
        }

        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    //DATOS LISTA PROYECTO
    public function obtenerTareasLista($id_tarea, $rol)
    {
        switch ($rol) {
            case 'profesor':
            case 'investigador':
            case 'supervisor':
                $sql = "SELECT 
                    tu.id_asignacion,
                    tita.descripcion_tipo as tipo,
                    u.id_usuarios,
                    CONCAT(u.nombre, ' ', u.apellido_paterno, ' ', u.apellido_materno) AS estudiante,
                    et.nombre AS estados_tarea,
                    tu.fecha_revision AS fecha_entrega,
                    tu.id_tarea
                FROM tareas_usuarios tu
                INNER JOIN usuarios u ON tu.id_usuario = u.id_usuarios
                INNER JOIN estados_tarea et ON tu.id_estadoT = et.id_estadoT
                INNER JOIN tareas as ta ON ta.id_tarea = tu.id_tarea
                INNER JOIN tipo_tarea as tita ON ta.id_tipotarea = tita.id_tareatipo
                WHERE tu.id_tarea = ?
                ORDER BY u.nombre ASC";

                $stmt = $this->con->prepare($sql);
                $stmt->bind_param("i", $id_tarea);
                break;

            default:
                return [];
        }

        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    //ACTUALIZAR TAREA
    public function editarTareaGeneral($id_tareas, $descripcion, $instrucciones, $fecha_entrega, $archivo_guia, $archivo_nombre, $archivo_tipo)
    {
        // 1. ACTUALIZAR TABLA tareas
        $sqlTarea = "UPDATE tareas
                     SET descripcion = ?, instrucciones = ?
                     WHERE id_tarea = ?";

        $stmt1 = $this->con->prepare($sqlTarea);
        if (!$stmt1) {
            die("Error en prepare tareas: " . $this->con->error);
        }

        $stmt1->bind_param("ssi", $descripcion, $instrucciones, $id_tareas);

        if (!$stmt1->execute()) {
            die("Error en execute tareas: " . $stmt1->error);
        }

        // 2. ACTUALIZAR TABLA tbl_seguimiento (vinculado por id_avances)
        $sqlSeg = "UPDATE tbl_seguimiento s
                   INNER JOIN tareas t ON s.id_avances = t.id_avances
                   SET s.fecha_entrega = ?,
                       s.archivo_guia  = ?,
                       s.archivo_nombre = ?,
                       s.archivo_tipo   = ?
                   WHERE t.id_tarea = ?";

        $stmt2 = $this->con->prepare($sqlSeg);
        if (!$stmt2) {
            die("Error en prepare seguimiento: " . $this->con->error);
        }

        $stmt2->bind_param(
            "sbssi",
            $fecha_entrega,
            $archivo_guia,
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
                    fecha_revision = NOW(),
                    id_estadoT = 2, /* 2 = Revisar */
                    archivo = COALESCE(?, archivo),
                    archivo_nombre = COALESCE(?, archivo_nombre),
                    archivo_tipo = COALESCE(?, archivo_tipo)
                WHERE id_asignacion = ?
                  AND id_tarea = ?
                  AND id_usuario = ?";

        $stmt = $this->con->prepare($sql);

        if (!$stmt) {
            die("Error al preparar consulta: " . $this->con->error);
        }

        $archivo_blob = $archivo['data'] ?? null;
        $archivo_nombre = $archivo['name'] ?? null;
        $archivo_tipo = $archivo['type'] ?? null;

        $stmt->bind_param(
            "sbssiii",
            $contenidoJSON,
            $archivo_blob,
            $archivo_nombre,
            $archivo_tipo,
            $id_asignacion,
            $id_tarea,
            $id_estudiante
        );

        // obligatorios para los parámetros tipo "b"
        if ($contenidoJSON !== null) {
            $stmt->send_long_data(0, $contenidoJSON);
        }

        if ($archivo_blob !== null) {
            $stmt->send_long_data(1, $archivo_blob);
        }

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
                  AND id_usuario = ?";

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
        // Mapeo de estados con sus fechas correspondientes
        $campos_fecha = [
            1 => null,                          // Pendiente - sin fecha
            2 => 'fecha_revision = CURDATE()',  // Revisar
            3 => 'fecha_correccion = CURDATE()',// Corregir
            5 => 'fecha_aprobacion = CURDATE()' // Aprobar
        ];

        $fecha_update = $campos_fecha[$numeroEstado] ?? null;

        if ($fecha_update) {
            $sql = "UPDATE tareas_usuarios 
                    SET id_estadoT = ?, $fecha_update 
                    WHERE id_asignacion = ?";
        } else {
            $sql = "UPDATE tareas_usuarios 
                    SET id_estadoT = ? 
                    WHERE id_asignacion = ?";
        }

        $stmt = $this->con->prepare($sql);
        if (!$stmt) {
            die("Error en prepare(): " . $this->con->error);
        }

        $stmt->bind_param("ii", $numeroEstado, $id_asignacion);

        if (!$stmt->execute()) {
            die("Error en execute(): " . $stmt->error);
        }

        header("Location: lista_tareas.php?msg=mensaje");
        exit;
    }

    // DETALLES DE LA TAREA (Estudiante)
    function obtenerTareaAlumno($id_asignacion)
    {
        $sql = "SELECT 
            tu.id_tarea,
            tu.archivo,
            tu.archivo_nombre,
            tu.archivo_tipo,
            tu.contenido,
            tu.comentarios,

            t.descripcion,
            t.instrucciones,
            
            tt.descripcion_tipo AS tipo_tarea,
            
            s.archivo_guia AS guia_archivo,
            s.archivo_nombre AS guia_nombre,
            s.archivo_tipo AS guia_tipo
            
        FROM tareas_usuarios tu
        INNER JOIN tareas t ON t.id_tarea = tu.id_tarea
        INNER JOIN tbl_seguimiento s ON s.id_avances = t.id_avances
        INNER JOIN tipo_tarea tt ON tt.id_tareatipo = t.id_tipotarea
        WHERE tu.id_asignacion = ?
        LIMIT 1";

        $stmt = $this->con->prepare($sql);

        if (!$stmt) {
            die("Error al preparar consulta: " . $this->con->error);
        }

        $stmt->bind_param("i", $id_asignacion);
        $stmt->execute();

        $tarea = $stmt->get_result()->fetch_assoc();

        // Si no hay datos, retornar vacío
        if (!$tarea) {
            return json_encode([]);
        }

        // Si contenido es null → inicializar
        if (empty($tarea['contenido'])) {
            $tarea['contenido'] = [];
        }

        // Si comentarios es null → inicializar
        if (empty($tarea['comentarios'])) {
            $tarea['comentarios'] = "";
        }

        return json_encode($tarea);
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
                 JOIN tipo_tarea AS tita ON tare.id_tipotarea = tita.id_tareatipo
                 WHERE tare.id_tarea = ?";

        $stmt1 = $this->con->prepare($sqlTarea);
        $stmt1->bind_param("i", $id_tarea);
        $stmt1->execute();
        $tarea = $stmt1->get_result()->fetch_assoc();

        // 2) OBTENER SEGUIMIENTO
        $sqlSeg = "SELECT 
                    s.fecha_entrega,
                    s.archivo_guia,
                    s.archivo_nombre,
                    s.archivo_tipo
                   FROM tbl_seguimiento s
                   INNER JOIN tareas t ON s.id_avances = t.id_avances
                   WHERE t.id_tarea = ?";

        $stmt2 = $this->con->prepare($sqlSeg);
        $stmt2->bind_param("i", $id_tarea);
        $stmt2->execute();
        $seguimiento = $stmt2->get_result()->fetch_assoc();

        // Empaquetar JSON
        return json_encode([
            "tarea" => $tarea,
            "seguimiento" => $seguimiento
        ]);
    }
}