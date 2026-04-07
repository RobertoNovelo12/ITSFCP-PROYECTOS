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

        $sql = "
        UPDATE tareas_usuarios AS taus
        JOIN tareas AS tare ON taus.id_tarea = tare.id_tarea
        SET taus.id_estadoT = 6
        WHERE taus.id_estadoT IN (1,2,3)
          AND tare.fecha_entrega < ?
    ";

        $stmt = $this->con->prepare($sql);
        $stmt->bind_param("s", $hoy);

        if (!$stmt->execute()) {
            die('Error al actualizar tareas vencidas: ' . $stmt->error);
        }
    }


    //DATOS PRINCIPAL
    public function obtenerTareas($id_proyecto, $id_usuario, $rol)
    {
        switch ($rol) {
            case 'estudiante':
                $sql = "SELECT 
    t.id_tarea,
    taus.id_asignacion,

    tt.descripcion_tipo AS tipo,

    -- Datos de la plantilla
    t.archivo_guia,
    t.archivo_nombre,
    t.archivo_tipo,
    t.fecha_entrega,
    est.nombre AS estado_plantilla,

    -- Datos de la entrega del estudiante
    taus.archivo AS archivo_entregado,
    taus.archivo_nombre AS archivo_entregado_nombre,
    taus.archivo_tipo AS archivo_entregado_tipo,
    taus.fecha_entrega_estudiante,
    taus.fecha_revision,
    esu.nombre AS estado_entrega

FROM tareas t
INNER JOIN tbl_seguimiento s 
        ON t.id_avances = s.id_avances

LEFT JOIN tareas_usuarios taus
        ON taus.id_tarea = t.id_tarea
       AND taus.id_usuarios = ?

INNER JOIN tipo_tarea tt 
        ON t.id_tareatipo = tt.id_tareatipo

LEFT JOIN estados_tarea est 
        ON t.id_estadoT = est.id_estadoT   -- Estado de la plantilla

LEFT JOIN estados_tarea esu 
        ON taus.id_estadoT = esu.id_estadoT -- Estado de la entrega

WHERE s.id_proyectos = ?
ORDER BY t.id_tarea ASC;
";
                $stmt = $this->con->prepare($sql);
                $stmt->bind_param("ii", $id_usuario, $id_proyecto);
                break;
            case 'profesor':
            case 'investigador':
            case 'supervisor':
                $sql = "SELECT 
    t.id_tarea,
    tt.descripcion_tipo AS tipo,

    -- Datos de la plantilla
    t.archivo_guia,
    t.archivo_nombre,
    t.archivo_tipo,
    t.fecha_entrega,
    est.nombre AS estado_plantilla,

    -- Datos de asignaciones
    (
        SELECT COUNT(*) 
        FROM tareas_usuarios tu
        WHERE tu.id_tarea = t.id_tarea
    ) AS total_asignados,

    (
        SELECT COUNT(*) 
        FROM tareas_usuarios tu
        WHERE tu.id_tarea = t.id_tarea
          AND tu.archivo IS NOT NULL
    ) AS total_entregados

FROM tareas t
INNER JOIN tbl_seguimiento s 
        ON t.id_avances = s.id_avances

INNER JOIN tipo_tarea tt 
        ON t.id_tareatipo = tt.id_tareatipo

LEFT JOIN estados_tarea est 
        ON t.id_estadoT = est.id_estadoT -- Estado plantilla

WHERE s.id_proyectos = ?
ORDER BY t.id_tarea ASC;
";
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
    public function obtenerTareasLista($id_tarea, $rol)
    {
        switch ($rol) {
            case 'profesor':
            case 'investigador':
            case 'supervisor':
                $sql = "SELECT 
                tu.id_asignacion,
                
                -- Tipo de tarea (nombre)
                tita.descripcion_tipo AS tipo,

                -- Datos del alumno
                u.id_usuarios,
                CONCAT(u.nombre, ' ', u.apellido_paterno, ' ', u.apellido_materno) AS estudiante,

                -- Estado actual de la entrega
                et.nombre AS estados_tarea,

                -- Datos del archivo entregado
                tu.archivo,
                tu.archivo_nombre,
                tu.archivo_tipo,

                -- Control: la tarea a la que pertenece
                ta.id_tarea

            FROM tareas_usuarios tu
            INNER JOIN usuarios u 
                ON tu.id_usuarios = u.id_usuarios
            INNER JOIN estados_tarea et 
                ON tu.id_estadoT = et.id_estadoT
            INNER JOIN tareas ta 
                ON ta.id_tarea = tu.id_tarea
            INNER JOIN tipo_tarea tita
                ON ta.id_tareatipo = tita.id_tareatipo

            WHERE tu.id_tarea = ?
            ORDER BY estudiante ASC
";
                $stmt = $this->con->prepare($sql);
                $stmt->bind_param("i", $id_tarea);
                break;
            default:
                break;
        }
        $stmt->execute();


        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    //Obtener tareas para alumno
    public function obtenerTareasEstudiante($id_usuario)
    {
        $sql = "
        SELECT 
            tu.id_asignacion,
            tu.id_estadoT,
            tu.id_tarea,
            t.fecha_entrega,
            t.descripcion,
            t.instrucciones,
            CASE tu.id_estadoT
                WHEN 1 THEN 'Pendiente'
                WHEN 2 THEN 'En revisión'
                WHEN 3 THEN 'Corregir'
                WHEN 5 THEN 'Aprobado'
                WHEN 6 THEN 'Vencido'
                WHEN 7 THEN 'Entregado'
                ELSE 'Desconocido'
            END AS estado_texto,
            tita.descripcion_tipo as tipo
        FROM tareas_usuarios tu
        INNER JOIN tareas t 
            ON t.id_tarea = tu.id_tarea
        INNER JOIN tipo_tarea as tita ON t.id_tareatipo = tita.id_tareatipo
        WHERE tu.id_usuarios = ?
        ORDER BY tu.id_asignacion DESC
    ";

        $stmt = $this->con->prepare($sql);
        $stmt->bind_param("i", $id_usuario);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }


    //ACTUALIZAR TAREA
    public function editarTareaGeneral($id_tarea, $descripcion, $instrucciones, $fecha_entrega, $archivo_guia, $archivo_nombre, $archivo_tipo)
    {
        if ($archivo_guia === null) {
            // sin archivo nuevo
            $sql = "UPDATE tareas
                SET descripcion = ?, instrucciones = ?, fecha_entrega = ?
                WHERE id_tarea = ?";
            $stmt = $this->con->prepare($sql);
            $stmt->bind_param("sssi", $descripcion, $instrucciones, $fecha_entrega, $id_tarea);
        } else {
            // con archivo nuevo
            $sql = "UPDATE tareas
                SET descripcion = ?, instrucciones = ?, fecha_entrega = ?,
                    archivo_guia = ?, archivo_nombre = ?, archivo_tipo = ?
                WHERE id_tarea = ?";
            $stmt = $this->con->prepare($sql);
            $stmt->bind_param(
                "ssssssi",
                $descripcion,
                $instrucciones,
                $fecha_entrega,
                $archivo_guia,
                $archivo_nombre,
                $archivo_tipo,
                $id_tarea
            );
        }

        $stmt->execute();
    }


    //Estudiante
    public function editarTareaEstudiante($id_asignacion, $id_tarea, $contenido, $archivo = null)
    {
        $sql = "UPDATE tareas_usuarios 
            SET contenido = ?,                
                archivo = COALESCE(?, archivo),
                archivo_nombre = COALESCE(?, archivo_nombre),
                archivo_tipo = COALESCE(?, archivo_tipo)
            WHERE id_asignacion = ?
              AND id_tarea = ?";

        $stmt = $this->con->prepare($sql);

        if (!$stmt) {
            die("Error al preparar consulta: " . $this->con->error);
        }

        $archivo_blob   = $archivo['data'] ?? null;
        $archivo_nombre = $archivo['name'] ?? null;
        $archivo_tipo   = $archivo['type'] ?? null;

        // OBLIGATORIO: convertir null a variables válidas
        if ($archivo_blob === null) {
            $archivo_blob = null;
        }
        if ($archivo_nombre === null) {
            $archivo_nombre = null;
        }
        if ($archivo_tipo === null) {
            $archivo_tipo = null;
        }

        $stmt->bind_param(
            "ssssii",
            $contenido,
            $archivo_blob,
            $archivo_nombre,
            $archivo_tipo,
            $id_asignacion,
            $id_tarea
        );

        return $stmt->execute();
    }



    //Investigador
    public function editarTareaRevisar($id_tareas, $comentarios)
    {
        $sql = "UPDATE tareas_usuarios
            SET comentarios = ?,
                fecha_revision = NOW()
            WHERE id_tarea = ?";

        $stmt = $this->con->prepare($sql);
        if (!$stmt) {
            die("Error al preparar: " . $this->con->error);
        }

        $stmt->bind_param("si", $comentarios, $id_tareas);

        $stmt->execute();
    }
    public function VincularTareasAntiguas($id_proyectos, $id_tarea)
    {
        // Obtener usuarios NO vinculados
        $sqlUsuario = "SELECT prus.id_usuarios
        FROM proyectos_usuarios AS prus
        WHERE prus.id_proyectos = ?
        AND NOT EXISTS (
            SELECT 1
            FROM tareas_usuarios AS taus
            WHERE taus.id_tarea = ?
            AND taus.id_usuarios = prus.id_usuarios
        )
    ";
        // justo antes de $stmtUsuario = $this->con->prepare($sqlUsuario);
        $stmtUsuario = $this->con->prepare($sqlUsuario);
        if ($stmtUsuario === false) {
            // muestra el SQL y el error de MySQL para depuración
            error_log("MySQL prepare failed: " . $this->con->error);
            error_log("SQL: " . $sqlUsuario);
            throw new Exception("MySQL prepare failed: " . $this->con->error);
        }
        $stmtUsuario = $this->con->prepare($sqlUsuario);
        $stmtUsuario->bind_param("ii", $id_proyectos, $id_tarea);
        $stmtUsuario->execute();
        $result = $stmtUsuario->get_result();
        $stmtUsuario->close();

        // Insert seguro (evita duplicados)
        $sqlInsert = "INSERT INTO tareas_usuarios (id_tarea, id_usuarios, id_estadoT)
        SELECT ?, ?, 1
        WHERE NOT EXISTS (
            SELECT 1 FROM tareas_usuarios 
            WHERE id_tarea = ? AND id_usuarios = ?
        )
    ";

        $stmtInsert = $this->con->prepare($sqlInsert);

        // Recorrer todos los usuarios no vinculados
        while ($alumno = $result->fetch_assoc()) {

            $stmtInsert->bind_param(
                "iiii",
                $id_tarea,
                $alumno['id_usuarios'],
                $id_tarea,
                $alumno['id_usuarios']
            );

            $stmtInsert->execute();
        }
    }

    public function actualizarestado($id_tarea, $numeroEstado, $id_proyectos, $id_asignacion, $id_usuarios, $comentario)
    {
        // 1. ACTIVAR TAREA (estado 1) //ACTIVAR LA TAREA A TODOS LOS ALUMNOS
        if ($numeroEstado == 1) {
            //Estado de la tarea en general
            $sql = "UPDATE tareas 
                    SET id_estadoT = ? 
                    WHERE id_tarea = ?";
            $stmt = $this->con->prepare($sql);
            $stmt->bind_param("ii", $numeroEstado, $id_tarea);
            $stmt->execute();
            $stmt->close();

            // Obtener proyecto y tarea
            $sqlProyecto = "SELECT tbse.id_proyectos, tare.id_tarea
            FROM tareas as tare
            JOIN tbl_seguimiento tbse ON tbse.id_avances = tare.id_avances
            WHERE tare.id_tarea = ?
        ";
            $stmtProyecto = $this->con->prepare($sqlProyecto);
            $stmtProyecto->bind_param("i", $id_tarea);
            $stmtProyecto->execute();

            $proy = $stmtProyecto->get_result()->fetch_assoc();
            $stmtProyecto->close();

            $id_proyectos = $proy['id_proyectos'];
            $id_tarea     = $proy['id_tarea'];

            // Obtener alumnos del proyecto
            $sqlEstudiante = "SELECT id_usuarios
            FROM proyectos_usuarios
            WHERE id_proyectos = ?
        ";
            $stmtAlumnos = $this->con->prepare($sqlEstudiante);
            $stmtAlumnos->bind_param("i", $id_proyectos);
            $stmtAlumnos->execute();
            $alumnos = $stmtAlumnos->get_result();
            $stmtAlumnos->close();

            // INSERT seguro (evita duplicados)
            $sqlInsert = "INSERT INTO tareas_usuarios (id_tarea, id_usuarios, id_estadoT)
            SELECT ?, ?, 1
            WHERE NOT EXISTS (
                SELECT 1 FROM tareas_usuarios 
                WHERE id_tarea = ? AND id_usuarios = ?
            )
        ";
            $stmtInsert = $this->con->prepare($sqlInsert);


            while ($al = $alumnos->fetch_assoc()) {
                $stmtInsert->bind_param(
                    "iiii",
                    $id_tarea,
                    $al['id_usuarios'],
                    $id_tarea,
                    $al['id_usuarios']
                );
                $stmtInsert->execute();
            }
        } else {
            // 2. OTROS ESTADOS (REVISAR, CORREGIR, APROBAR)
            switch ($numeroEstado) {
                case 2: // Revisar (Enviado)
                case 3: // Corregir
                case 5:    // Aprobar
                    //Consulta a la tabla de tareas_usuarios
                    $sql = "UPDATE tareas_usuarios 
                    SET id_estadoT = ?
                    WHERE id_tarea = ?";
                    //Consulta a la tabla de tareas_historial
                    if ($id_asignacion != null) {
                        $sql2 = "INSERT INTO tareas_historial (id_asignacion, id_estadoT, id_usuarios, comentario, fecha)
                    VALUES (?, ?, ?, ?, CURDATE())";
                    }


                    break;
                /*case 6: // Entregado
                    $sql = "UPDATE tareas_usuarios 
                    SET id_estadoT = ? 
                    WHERE id_tarea = ?";
                    break;*/
                default:
                    die("Estado no válido");
            }
            $stmt = $this->con->prepare($sql);
            $stmt->bind_param("ii", $numeroEstado, $id_tarea);
            $stmt->execute();
            if ($id_asignacion != null) {
                $stmt2 = $this->con->prepare($sql2);
                $stmt2->bind_param("iiis", $id_asignacion, $numeroEstado, $id_usuarios, $comentario);
                $stmt2->execute();
            }
        }
    }

    //Obtener los datos para el formulario de alumno
    function obtenerTareaAlumno($id_asignacion)
    {
        $sql = "SELECT
a.id_asignacion,
a.id_tarea,
tbse.id_proyectos,
a.archivo,
a.archivo_nombre,
a.archivo_tipo,

esta.nombre AS estado,

            t.descripcion,
            t.instrucciones,
            tt.descripcion_tipo AS tipo_tarea,

            a.contenido
        FROM tareas_usuarios a
        INNER JOIN tareas t ON t.id_tarea = a.id_tarea
        INNER JOIN tbl_seguimiento as tbse ON t.id_avances = tbse.id_avances 
        INNER JOIN tipo_tarea tt ON tt.id_tareatipo = t.id_tareatipo
        INNER JOIN estados_tarea as esta ON esta.id_estadoT = a.id_estadoT
        WHERE a.id_asignacion = ?
        LIMIT 1";

        $stmt = $this->con->prepare($sql);

        if (!$stmt) {
            die("Error al preparar consulta: " . $this->con->error);
        }

        $stmt->bind_param("i", $id_asignacion);
        $stmt->execute();
        $tarea = $stmt->get_result()->fetch_assoc();

        return $tarea;
    }

    //Obtener información de tarea con seguimiento para modificar los datos
    function obtenerTareaGeneral($id_tarea)
    {
        // OBTENER TAREA
        $sqlTarea = "SELECT 
                    tare.id_tarea,
                    tita.descripcion_tipo AS tipo,
                    tare.descripcion,
                    tare.instrucciones,
                    tare.fecha_entrega,
                    tare.archivo_guia,
                    tare.archivo_nombre,
                    tare.archivo_tipo,
                    tita.descripcion_tipo AS titulo_tarea,
                    esta.nombre as estado
                 FROM tareas AS tare
                 JOIN tipo_tarea AS tita ON tare.id_tareatipo = tita.id_tareatipo
                 JOIN estados_tarea as esta ON esta.id_estadoT = tare.id_estadoT
                 WHERE tare.id_tarea = ?";

        $stmt = $this->con->prepare($sqlTarea);
        if (!$stmt) {
            die("Error en SQL: " . $this->con->error . "<br><br>QUERY:<br>$sqlTarea");
        }

        $stmt->bind_param("i", $id_tarea);
        $stmt->execute();
        $tarea = $stmt->get_result()->fetch_assoc();

        return $tarea;
    }
    //OBTENR INFORMACIÓN DEL HISTORIAL DE TAREA PARA EL TIMELINE
    public function linea_tiempo_tarea($id_asignacion)
    {

        $sqlHistorial = "SELECT 
                    CASE id_estadoT
                WHEN 2 THEN 'Enviado'
                WHEN 3 THEN 'Corregir'
                WHEN 5 THEN 'Aprobado'
            END AS estado,(
        SELECT 1 
        FROM  estudiantes
        WHERE id_usuarios = tahi.id_usuarios
    ) AS esEstudiante, comentario, fecha FROM tareas_historial AS tahi
                 WHERE id_asignacion = ?";

        $stmt = $this->con->prepare($sqlHistorial);
        $stmt->bind_param("i", $id_asignacion);
        $stmt->execute();
        $historial = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $historialAgrupado = [];
        //Para agrupar el historial por fechas
        foreach ($historial as $item) {
            $fecha = date("d/m/Y", strtotime($item['fecha']));
            $historialAgrupado[$fecha][] = $item;
        }
        return $historialAgrupado;
    }
}
