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
            $sql = "
                SELECT
                    t.id_tarea,
                    taus.id_asignacion,
                    tt.descripcion_tipo          AS tipo,

                    -- Recurso adjunto por el investigador (antes: t.ruta / t.nombre_archivo)
                    ds_rec.nombre               AS archivo_nombre,
                    ds_rec.ruta                 AS archivo_ruta,
                    ds_rec.tipo_mime            AS archivo_tipo,
                    ds_rec.extension            AS archivo_extension,

                    t.fecha_entrega,
                    est.nombre                  AS estado_plantilla,

                    -- Entrega del estudiante (antes: taus.archivo / taus.nombre_archivo)
                    ds_ent.nombre               AS archivo_entregado_nombre,
                    ds_ent.ruta                 AS archivo_entregado_ruta,
                    ds_ent.tipo_mime            AS archivo_entregado_tipo,
                    ds_ent.extension            AS archivo_entregado_extension,

                    taus.fecha_entrega_estudiante,
                    taus.fecha_revision,
                    esu.nombre                  AS estado_entrega

                FROM tareas t
                INNER JOIN tbl_seguimiento s   ON t.id_avances      = s.id_avances
                LEFT  JOIN tareas_usuarios taus ON taus.id_tarea     = t.id_tarea
                                               AND taus.id_usuarios  = ?
                INNER JOIN tipo_tarea tt        ON t.id_tareatipo    = tt.id_tareatipo
                LEFT  JOIN estados_tarea est    ON t.id_estadoT      = est.id_estadoT
                LEFT  JOIN estados_tarea esu    ON taus.id_estadoT   = esu.id_estadoT

                -- Archivo recurso del investigador
                LEFT  JOIN documentos_subidos ds_rec
                        ON ds_rec.id_documento = t.id_documento_recurso

                -- Entrega del estudiante
                LEFT  JOIN documentos_subidos ds_ent
                        ON ds_ent.id_documento = taus.id_documento_entrega

                WHERE s.id_proyectos = ?
                ORDER BY t.id_tarea ASC
            ";
            $stmt = $this->con->prepare($sql);
            $stmt->bind_param("ii", $id_usuario, $id_proyecto);
            break;

        case 'profesor':
        case 'investigador':
        case 'supervisor':
            $sql = "
                SELECT
                    t.id_tarea,
                    tt.descripcion_tipo          AS tipo,

                    -- Recurso adjunto por el investigador
                    ds_rec.nombre               AS archivo_nombre,
                    ds_rec.ruta                 AS archivo_ruta,
                    ds_rec.tipo_mime            AS archivo_tipo,
                    ds_rec.extension            AS archivo_extension,

                    t.fecha_entrega,
                    est.nombre                  AS estado_plantilla,

                    -- Conteo de asignados
                    (
                        SELECT COUNT(*)
                        FROM tareas_usuarios tu
                        WHERE tu.id_tarea = t.id_tarea
                    ) AS total_asignados,

                    -- Conteo de entregas (antes: tu.archivo IS NOT NULL)
                    (
                        SELECT COUNT(*)
                        FROM tareas_usuarios tu
                        WHERE tu.id_tarea          = t.id_tarea
                          AND tu.id_documento_entrega IS NOT NULL
                    ) AS total_entregados

                FROM tareas t
                INNER JOIN tbl_seguimiento s ON t.id_avances   = s.id_avances
                INNER JOIN tipo_tarea tt     ON t.id_tareatipo = tt.id_tareatipo
                LEFT  JOIN estados_tarea est ON t.id_estadoT   = est.id_estadoT
                LEFT  JOIN documentos_subidos ds_rec
                        ON ds_rec.id_documento = t.id_documento_recurso

                WHERE s.id_proyectos = ?
                ORDER BY t.id_tarea ASC
            ";
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
            $sql = "
                SELECT
                    tu.id_asignacion,
                    tita.descripcion_tipo        AS tipo,

                    -- Datos del alumno
                    u.id_usuarios,
                    CONCAT(u.nombre, ' ', u.apellido_paterno, ' ', u.apellido_materno) AS estudiante,

                    -- Estado de la entrega
                    et.nombre                   AS estados_tarea,

                    -- Entrega del estudiante (antes: tu.archivo / tu.nombre_archivo)
                    ds_ent.nombre               AS archivo_nombre,
                    ds_ent.ruta                 AS archivo_ruta,
                    ds_ent.tipo_mime            AS archivo_tipo,
                    ds_ent.extension            AS archivo_extension,

                    ta.id_tarea

                FROM tareas_usuarios tu
                INNER JOIN usuarios u          ON tu.id_usuarios    = u.id_usuarios
                INNER JOIN estados_tarea et    ON tu.id_estadoT     = et.id_estadoT
                INNER JOIN tareas ta           ON ta.id_tarea       = tu.id_tarea
                INNER JOIN tipo_tarea tita     ON ta.id_tareatipo   = tita.id_tareatipo
                LEFT  JOIN documentos_subidos ds_ent
                        ON ds_ent.id_documento = tu.id_documento_entrega

                WHERE tu.id_tarea = ?
                ORDER BY estudiante ASC
            ";
            $stmt = $this->con->prepare($sql);
            $stmt->bind_param("i", $id_tarea);
            break;

        default:
            return [];
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
    /**
     * Inserta un archivo en documentos_subidos y devuelve el id generado.
     * Reutilizable desde cualquier flujo (recurso, entrega, plantilla, etapa).
     */
    public function registrarDocumento(
        string  $nombre,
        string  $nombre_archivo,
        string  $ruta,
        string  $tipo_mime,
        string  $extension,
        int     $tamano_bytes,
        string  $tipo,          // plantilla | recurso | entrega | etapa
        string  $visibilidad,   // publico | privado
        int     $id_usuario,
        int     $id_proyecto    = 0,
        ?int    $etapa          = null,
        int     $version        = 1
    ): int {
        $id_proyecto = $id_proyecto ?: null; // 0 → NULL en BD

        $sql = "
        INSERT INTO documentos_subidos
            (nombre, nombre_archivo, ruta, tipo_mime, extension, tamano_bytes,
             tipo, visibilidad, id_usuario, id_proyecto, etapa, version, activo, fecha_subida)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())
    ";

        $stmt = $this->con->prepare($sql);
        if (!$stmt) throw new Exception("Error prepare (registrarDocumento): " . $this->con->error);

        $stmt->bind_param(
            "sssssisisiii",
            $nombre,
            $nombre_archivo,
            $ruta,
            $tipo_mime,
            $extension,
            $tamano_bytes,
            $tipo,
            $visibilidad,
            $id_usuario,
            $id_proyecto,
            $etapa,
            $version
        );

        if (!$stmt->execute()) throw new Exception("Error execute (registrarDocumento): " . $stmt->error);

        $id = $stmt->insert_id;
        $stmt->close();
        return $id;
    }


    /**
     * Actualiza la tarea (recurso del investigador).
     * Si $id_documento_recurso es null, NO sobreescribe el archivo existente.
     */
    public function editarTareaGeneral(
        int     $id_tarea,
        string  $descripcion,
        string  $instrucciones,
        string  $fecha_entrega,
        ?int    $id_documento_recurso
    ): void {
        if ($id_documento_recurso === null) {
            $sql  = "UPDATE tareas
                 SET descripcion = ?, instrucciones = ?, fecha_entrega = ?
                 WHERE id_tarea = ?";
            $stmt = $this->con->prepare($sql);
            if (!$stmt) throw new Exception("Error prepare (editarTareaGeneral): " . $this->con->error);
            $stmt->bind_param("sssi", $descripcion, $instrucciones, $fecha_entrega, $id_tarea);
        } else {
            $sql  = "UPDATE tareas
                 SET descripcion = ?, instrucciones = ?, fecha_entrega = ?,
                     id_documento_recurso = ?
                 WHERE id_tarea = ?";
            $stmt = $this->con->prepare($sql);
            if (!$stmt) throw new Exception("Error prepare (editarTareaGeneral): " . $this->con->error);
            $stmt->bind_param("sssii", $descripcion, $instrucciones, $fecha_entrega, $id_documento_recurso, $id_tarea);
        }

        if (!$stmt->execute()) throw new Exception("Error execute (editarTareaGeneral): " . $stmt->error);
        $stmt->close();
    }


    /**
     * Actualiza la entrega del estudiante en tareas_usuarios.
     * Si $id_documento_entrega es null, NO sobreescribe la entrega anterior.
     */
    public function editarTareaEstudiante(
        int     $id_asignacion,
        int     $id_tarea,
        string  $contenido,
        ?int    $id_documento_entrega
    ): bool {
        $sql = "
        UPDATE tareas_usuarios
        SET contenido            = ?,
            id_documento_entrega = COALESCE(?, id_documento_entrega)
        WHERE id_asignacion = ?
          AND id_tarea      = ?
    ";

        $stmt = $this->con->prepare($sql);
        if (!$stmt) throw new Exception("Error prepare (editarTareaEstudiante): " . $this->con->error);

        $stmt->bind_param("siii", $contenido, $id_documento_entrega, $id_asignacion, $id_tarea);

        if (!$stmt->execute()) throw new Exception("Error execute (editarTareaEstudiante): " . $stmt->error);
        $stmt->close();
        return true;
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
    public function obtenerTareaAlumno($id_asignacion)
{
    $sql = "
        SELECT
            a.id_asignacion,
            a.id_tarea,
            tbse.id_proyectos,

            -- Entrega del estudiante
            ds_ent.nombre               AS archivo_nombre,
            ds_ent.ruta                 AS archivo_ruta,
            ds_ent.tipo_mime            AS archivo_tipo,
            ds_ent.extension            AS archivo_extension,

            esta.nombre                 AS estado,
            t.descripcion,
            t.instrucciones,
            tt.descripcion_tipo         AS tipo_tarea,
            a.contenido

        FROM tareas_usuarios a
        INNER JOIN tareas t             ON t.id_tarea       = a.id_tarea
        INNER JOIN tbl_seguimiento tbse ON t.id_avances     = tbse.id_avances
        INNER JOIN tipo_tarea tt        ON tt.id_tareatipo  = t.id_tareatipo
        INNER JOIN estados_tarea esta   ON esta.id_estadoT  = a.id_estadoT
        LEFT  JOIN documentos_subidos ds_ent
                ON ds_ent.id_documento  = a.id_documento_entrega

        WHERE a.id_asignacion = ?
        LIMIT 1
    ";

    $stmt = $this->con->prepare($sql);
    if (!$stmt) die("Error al preparar consulta: " . $this->con->error);

    $stmt->bind_param("i", $id_asignacion);
    $stmt->execute();

    return $stmt->get_result()->fetch_assoc();
}

    //Obtener información de tarea con seguimiento para modificar los datos
   public function obtenerTareaGeneral($id_tarea)
{
    $sql = "
        SELECT
            tare.id_tarea,
            tita.descripcion_tipo       AS tipo,
            tare.descripcion,
            tare.instrucciones,
            tare.fecha_entrega,
            tita.descripcion_tipo       AS titulo_tarea,
            esta.nombre                 AS estado,

            -- Recurso adjunto por el investigador
            ds_rec.nombre               AS archivo_nombre,
            ds_rec.ruta                 AS archivo_ruta,
            ds_rec.tipo_mime            AS archivo_tipo,
            ds_rec.extension            AS archivo_extension

        FROM tareas tare
        INNER JOIN tipo_tarea tita      ON tare.id_tareatipo = tita.id_tareatipo
        INNER JOIN estados_tarea esta   ON esta.id_estadoT   = tare.id_estadoT
        LEFT  JOIN documentos_subidos ds_rec
                ON ds_rec.id_documento  = tare.id_documento_recurso

        WHERE tare.id_tarea = ?
    ";

    $stmt = $this->con->prepare($sql);
    if (!$stmt) die("Error en SQL: " . $this->con->error);

    $stmt->bind_param("i", $id_tarea);
    $stmt->execute();

    return $stmt->get_result()->fetch_assoc();
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
