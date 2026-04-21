<?php
// Modelo de tareas
require_once __DIR__ . '/../publico/config/conexion.php';

class Tarea
{
    private $con;

    public function __construct($conn)
    {
        $this->con = $conn;
    }

    // ─────────────────────────────────────────────
    //  ACTUALIZAR VENCIDOS
    // ─────────────────────────────────────────────
    public function actualizarTareasVencidos(): void
    {
        $hoy = date("Y-m-d");

        // 1. Detectar qué asignaciones se van a vencer (para registrar historial)
        $sqlDetectar = "
            SELECT taus.id_asignacion, taus.id_estadoT
            FROM tareas_usuarios taus
            JOIN tareas tare ON taus.id_tarea = tare.id_tarea
            WHERE taus.id_estadoT IN (1,2,3)
              AND tare.fecha_entrega < ?
        ";
        $stmt = $this->con->prepare($sqlDetectar);
        $stmt->bind_param("s", $hoy);
        $stmt->execute();
        $vencidos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        if (empty($vencidos)) return;

        // 2. Actualizar estado a Vencido (6)
        $sqlUpdate = "
            UPDATE tareas_usuarios taus
            JOIN tareas tare ON taus.id_tarea = tare.id_tarea
            SET taus.id_estadoT = 6
            WHERE taus.id_estadoT IN (1,2,3)
              AND tare.fecha_entrega < ?
        ";
        $stmt = $this->con->prepare($sqlUpdate);
        $stmt->bind_param("s", $hoy);
        $stmt->execute();
        $stmt->close();

        // 3. Registrar en historial (tipo_cambio = 'estado', sistema = id_usuarios = 1)
        $sqlHist = "
            INSERT INTO tareas_historial (id_asignacion, id_estadoT, id_usuarios, comentario, tipo_cambio)
            VALUES (?, 6, 1, 'Tarea marcada como vencida automáticamente', 'estado')
        ";
        $stmtH = $this->con->prepare($sqlHist);
        foreach ($vencidos as $v) {
            $stmtH->bind_param("i", $v['id_asignacion']);
            $stmtH->execute();
        }
        $stmtH->close();
    }

    // ─────────────────────────────────────────────
    //  OBTENER TAREAS (tabla principal)
    // ─────────────────────────────────────────────
    public function obtenerTareas($id_proyecto, $id_usuario, $rol)
    {
        switch ($rol) {

            case 'estudiante':
                $sql = "
                SELECT
                    t.id_tarea,
                    taus.id_asignacion,
                    tt.descripcion_tipo          AS tipo,
                    ds_rec.nombre               AS archivo_nombre,
                    ds_rec.ruta                 AS archivo_ruta,
                    ds_rec.tipo_mime            AS archivo_tipo,
                    ds_rec.extension            AS archivo_extension,
                    t.fecha_entrega,
                    est.nombre                  AS estado_plantilla,
                    ds_ent.nombre               AS archivo_entregado_nombre,
                    ds_ent.ruta                 AS archivo_entregado_ruta,
                    esu.nombre                  AS estado_entrega,
                    t.fecha_modificacion
                FROM tareas t
                INNER JOIN tbl_seguimiento s    ON t.id_avances     = s.id_avances
                LEFT  JOIN tareas_usuarios taus ON taus.id_tarea    = t.id_tarea
                                               AND taus.id_usuarios = ?
                INNER JOIN tipo_tarea tt        ON t.id_tareatipo   = tt.id_tareatipo
                LEFT  JOIN estados_tarea est    ON t.id_estadoT     = est.id_estadoT
                LEFT  JOIN estados_tarea esu    ON taus.id_estadoT  = esu.id_estadoT
                LEFT  JOIN documentos_subidos ds_rec
                        ON ds_rec.id_documento  = t.id_documento_recurso
                LEFT  JOIN documentos_subidos ds_ent
                        ON ds_ent.id_documento  = taus.id_documento_entrega
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
                    ds_rec.nombre               AS archivo_nombre,
                    ds_rec.ruta                 AS archivo_ruta,
                    t.fecha_entrega,
                    t.fecha_modificacion,
                    est.nombre                  AS estado_plantilla,
                    (SELECT COUNT(*) FROM tareas_usuarios tu WHERE tu.id_tarea = t.id_tarea) AS total_asignados,
                    (SELECT COUNT(*) FROM tareas_usuarios tu WHERE tu.id_tarea = t.id_tarea AND tu.id_documento_entrega IS NOT NULL) AS total_entregados
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

    // ─────────────────────────────────────────────
    //  OBTENER TAREAS LISTA (por id_tarea)
    // ─────────────────────────────────────────────
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
                    u.id_usuarios,
                    CONCAT(u.nombre, ' ', u.apellido_paterno, ' ', u.apellido_materno) AS estudiante,
                    et.nombre                   AS estados_tarea,
                    ds_ent.nombre               AS archivo_nombre,
                    ds_ent.ruta                 AS archivo_ruta,
                    ta.id_tarea,
                    (SELECT MAX(th.fecha)
                     FROM tareas_historial th
                     WHERE th.id_asignacion = tu.id_asignacion
                       AND th.tipo_cambio = 'estado'
                       AND th.id_estadoT = 2) AS fecha_revision,
                    (SELECT MAX(th.fecha)
                     FROM tareas_historial th
                     WHERE th.id_asignacion = tu.id_asignacion
                       AND th.tipo_cambio = 'estado'
                       AND th.id_estadoT = 3) AS fecha_correccion,
                    (SELECT MAX(th.fecha)
                     FROM tareas_historial th
                     WHERE th.id_asignacion = tu.id_asignacion
                       AND th.tipo_cambio = 'estado'
                       AND th.id_estadoT = 5) AS fecha_aprobacion
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

    // ─────────────────────────────────────────────
    //  OBTENER TAREAS ESTUDIANTE (vista classroom)
    // ─────────────────────────────────────────────
    public function obtenerTareasEstudiante($id_usuario)
    {
        $sql = "
        SELECT 
            tu.id_asignacion,
            tu.id_estadoT,
            tu.id_tarea,
            ts.id_proyectos,
            t.fecha_entrega,
            t.descripcion,
            t.instrucciones,
            t.fecha_modificacion,
            CASE tu.id_estadoT
                WHEN 1 THEN 'Pendiente'
                WHEN 2 THEN 'En revisión'
                WHEN 3 THEN 'Corregir'
                WHEN 5 THEN 'Aprobado'
                WHEN 6 THEN 'Vencido'
                WHEN 7 THEN 'Entregado'
                ELSE 'Desconocido'
            END AS estado_texto,
            tita.descripcion_tipo AS tipo
        FROM tareas_usuarios tu
        INNER JOIN tareas t ON t.id_tarea = tu.id_tarea
        INNER JOIN tipo_tarea AS tita ON t.id_tareatipo = tita.id_tareatipo
        INNER JOIN tbl_seguimiento AS ts ON ts.id_avances = t.id_avances 
        WHERE tu.id_usuarios = ?
        ORDER BY tu.id_asignacion DESC
        ";

        $stmt = $this->con->prepare($sql);
        $stmt->bind_param("i", $id_usuario);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    // ─────────────────────────────────────────────
    //  REGISTRAR DOCUMENTO
    // ─────────────────────────────────────────────
    public function registrarDocumento(
        string  $nombre,
        string  $nombre_archivo,
        string  $ruta,
        string  $tipo_mime,
        string  $extension,
        int     $tamano_bytes,
        string  $tipo,
        string  $visibilidad,
        int     $id_usuario,
        int     $id_proyecto    = 0,
        ?int    $etapa          = null,
        int     $version        = 1
    ): int {
        $id_proyecto = $id_proyecto ?: null;

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

    // ─────────────────────────────────────────────
    //  EDITAR TAREA GENERAL (investigador - plantilla)
    //  Registra en historial los campos modificados
    // ─────────────────────────────────────────────
    public function editarTareaGeneral(
        int     $id_tarea,
        string  $descripcion,
        string  $instrucciones,
        string  $fecha_entrega,
        ?int    $id_documento_recurso,
        int     $id_usuario             // quien edita (investigador)
    ): void {

        // Obtener valores actuales para comparar
        $sqlActual = "SELECT descripcion, instrucciones, fecha_entrega FROM tareas WHERE id_tarea = ?";
        $stmtA = $this->con->prepare($sqlActual);
        $stmtA->bind_param("i", $id_tarea);
        $stmtA->execute();
        $actual = $stmtA->get_result()->fetch_assoc();
        $stmtA->close();

        // Obtener cualquier id_asignacion para registrar en historial
        // (el historial de edición se asocia a todas las asignaciones de esta tarea)
        $sqlAsig = "SELECT id_asignacion FROM tareas_usuarios WHERE id_tarea = ? LIMIT 1";
        $stmtAsi = $this->con->prepare($sqlAsig);
        $stmtAsi->bind_param("i", $id_tarea);
        $stmtAsi->execute();
        $primeraAsig = $stmtAsi->get_result()->fetch_assoc();
        $stmtAsi->close();

        // UPDATE tareas
        if ($id_documento_recurso === null) {
            $sql  = "UPDATE tareas SET descripcion=?, instrucciones=?, fecha_entrega=?, fecha_modificacion=NOW() WHERE id_tarea=?";
            $stmt = $this->con->prepare($sql);
            if (!$stmt) throw new Exception("Error prepare (editarTareaGeneral): " . $this->con->error);
            $stmt->bind_param("sssi", $descripcion, $instrucciones, $fecha_entrega, $id_tarea);
        } else {
            $sql  = "UPDATE tareas SET descripcion=?, instrucciones=?, fecha_entrega=?, id_documento_recurso=?, fecha_modificacion=NOW() WHERE id_tarea=?";
            $stmt = $this->con->prepare($sql);
            if (!$stmt) throw new Exception("Error prepare (editarTareaGeneral): " . $this->con->error);
            $stmt->bind_param("sssii", $descripcion, $instrucciones, $fecha_entrega, $id_documento_recurso, $id_tarea);
        }

        if (!$stmt->execute()) throw new Exception("Error execute (editarTareaGeneral): " . $stmt->error);
        $stmt->close();

        // Registrar cambios en historial si hay asignaciones
        if (!$primeraAsig) return;

        // Obtener TODAS las asignaciones de esta tarea para registrar en cada una
        $sqlAllAsig = "SELECT id_asignacion FROM tareas_usuarios WHERE id_tarea = ?";
        $stmtAll = $this->con->prepare($sqlAllAsig);
        $stmtAll->bind_param("i", $id_tarea);
        $stmtAll->execute();
        $todasAsig = $stmtAll->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmtAll->close();

        // Detectar qué campos cambiaron
        $cambios = [];
        if ($actual['descripcion'] !== $descripcion) {
            $cambios[] = ['campo_modificado' => 'descripcion', 'valor_anterior' => $actual['descripcion'], 'valor_nuevo' => $descripcion];
        }
        if ($actual['instrucciones'] !== $instrucciones) {
            $cambios[] = ['campo_modificado' => 'instrucciones', 'valor_anterior' => $actual['instrucciones'], 'valor_nuevo' => $instrucciones];
        }
        if ($actual['fecha_entrega'] !== $fecha_entrega) {
            $cambios[] = ['campo_modificado' => 'fecha_entrega', 'valor_anterior' => $actual['fecha_entrega'], 'valor_nuevo' => $fecha_entrega];
        }
        if ($id_documento_recurso !== null) {
            $cambios[] = ['campo_modificado' => 'archivo_guia', 'valor_anterior' => null, 'valor_nuevo' => 'Nuevo archivo subido'];
        }

        if (empty($cambios)) return;

        // Insertar en historial por cada asignación y cada campo modificado
        $sqlHist = "
            INSERT INTO tareas_historial
                (id_asignacion, id_estadoT, id_usuarios, comentario, tipo_cambio, campo_modificado, valor_anterior, valor_nuevo)
            VALUES (?, 1, ?, 'Tarea editada por el investigador', 'edicion', ?, ?, ?)
        ";
        $stmtH = $this->con->prepare($sqlHist);
        if (!$stmtH) throw new Exception("Error prepare (historial edicion): " . $this->con->error);

        foreach ($todasAsig as $asig) {
            foreach ($cambios as $c) {
                $stmtH->bind_param(
                    "iisss",
                    $asig['id_asignacion'],
                    $id_usuario,
                    $c['campo_modificado'],
                    $c['valor_anterior'],
                    $c['valor_nuevo']
                );
                $stmtH->execute();
            }
        }
        $stmtH->close();
    }

    // ─────────────────────────────────────────────
    //  EDITAR TAREA ESTUDIANTE (entrega de actividad)
    // ─────────────────────────────────────────────
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

    // ─────────────────────────────────────────────
    //  EDITAR TAREA REVISAR (investigador deja comentario)
    // ─────────────────────────────────────────────
    public function editarTareaRevisar($id_tareas, $comentarios): void
    {
        // tareas_usuarios no tiene columna comentarios, los comentarios van al historial
        // Solo actualizamos fecha_revision si existe columna, si no, simplemente continuamos
        // La inserción en historial la hace actualizarestado() con el comentario
    }

    // ─────────────────────────────────────────────
    //  ACTUALIZAR ESTADO
    // ─────────────────────────────────────────────
    public function actualizarestado($id_tarea, $numeroEstado, $id_proyectos, $id_asignacion, $id_usuarios, $comentario): void
    {
        // ── ACTIVAR TAREA (estado 1) ─────────────────────────────────────────
        if ($numeroEstado == 1) {
            // Actualizar la plantilla general
            $sql = "UPDATE tareas SET id_estadoT = ? WHERE id_tarea = ?";
            $stmt = $this->con->prepare($sql);
            $stmt->bind_param("ii", $numeroEstado, $id_tarea);
            $stmt->execute();
            $stmt->close();

            // Obtener proyecto
            $sqlProyecto = "
                SELECT tbse.id_proyectos, tare.id_tarea
                FROM tareas tare
                JOIN tbl_seguimiento tbse ON tbse.id_avances = tare.id_avances
                WHERE tare.id_tarea = ?
            ";
            $stmtP = $this->con->prepare($sqlProyecto);
            $stmtP->bind_param("i", $id_tarea);
            $stmtP->execute();
            $proy = $stmtP->get_result()->fetch_assoc();
            $stmtP->close();

            $id_proyectos = $proy['id_proyectos'];
            $id_tarea     = $proy['id_tarea'];

            // Obtener alumnos del proyecto
            $sqlEstudiante = "SELECT id_usuarios FROM proyectos_usuarios WHERE id_proyectos = ?";
            $stmtAlumnos = $this->con->prepare($sqlEstudiante);
            $stmtAlumnos->bind_param("i", $id_proyectos);
            $stmtAlumnos->execute();
            $alumnos = $stmtAlumnos->get_result();
            $stmtAlumnos->close();

            // INSERT en tareas_usuarios (evita duplicados)
            $sqlInsert = "INSERT INTO tareas_usuarios (id_tarea, id_usuarios, id_estadoT)
                SELECT ?, ?, 1
                WHERE NOT EXISTS (
                    SELECT 1 FROM tareas_usuarios WHERE id_tarea = ? AND id_usuarios = ?
                )";
            $stmtInsert = $this->con->prepare($sqlInsert);

            // Obtener id_asignacion tras insertar para el historial
            $sqlGetAsig = "SELECT id_asignacion FROM tareas_usuarios WHERE id_tarea = ? AND id_usuarios = ?";
            $stmtGetAsig = $this->con->prepare($sqlGetAsig);

            $sqlHistAct = "INSERT INTO tareas_historial (id_asignacion, id_estadoT, id_usuarios, comentario, tipo_cambio)
                           VALUES (?, 1, ?, 'Tarea activada', 'estado')";
            $stmtHistAct = $this->con->prepare($sqlHistAct);

            while ($al = $alumnos->fetch_assoc()) {
                $stmtInsert->bind_param("iiii", $id_tarea, $al['id_usuarios'], $id_tarea, $al['id_usuarios']);
                $stmtInsert->execute();

                // Registrar en historial
                $stmtGetAsig->bind_param("ii", $id_tarea, $al['id_usuarios']);
                $stmtGetAsig->execute();
                $asigRow = $stmtGetAsig->get_result()->fetch_assoc();
                if ($asigRow && $id_usuarios) {
                    $id_asig_nuevo = $asigRow['id_asignacion'];
                    $stmtHistAct->bind_param("ii", $id_asig_nuevo, $id_usuarios);
                    $stmtHistAct->execute();
                }
            }
        } else {
            // ── OTROS ESTADOS (Revisar=2, Corregir=3, Aprobado=5) ────────────
            switch ($numeroEstado) {
                case 2: // Revisar
                case 3: // Corregir
                case 5: // Aprobado
                    if ($id_asignacion != null) {
                        // Actualizar solo la asignación específica
                        $sql = "UPDATE tareas_usuarios SET id_estadoT = ? WHERE id_asignacion = ?";
                        $stmt = $this->con->prepare($sql);
                        $stmt->bind_param("ii", $numeroEstado, $id_asignacion);
                        $stmt->execute();
                        $stmt->close();

                        // Historial con tipo_cambio = 'estado'
                        $sqlHist = "INSERT INTO tareas_historial
                            (id_asignacion, id_estadoT, id_usuarios, comentario, tipo_cambio)
                            VALUES (?, ?, ?, ?, 'estado')";
                        $stmtH = $this->con->prepare($sqlHist);
                        $stmtH->bind_param("iiis", $id_asignacion, $numeroEstado, $id_usuarios, $comentario);
                        $stmtH->execute();
                        $stmtH->close();
                    } else {
                        // Sin asignación específica, actualizar por id_tarea
                        $sql = "UPDATE tareas_usuarios SET id_estadoT = ? WHERE id_tarea = ?";
                        $stmt = $this->con->prepare($sql);
                        $stmt->bind_param("ii", $numeroEstado, $id_tarea);
                        $stmt->execute();
                        $stmt->close();
                    }
                    break;

                default:
                    die("Estado no válido");
            }
        }
    }

    // ─────────────────────────────────────────────
    //  OBTENER TAREA ALUMNO (para vista tarea.php)
    // ─────────────────────────────────────────────
    public function obtenerTareaAlumno($id_asignacion)
    {
        $sql = "
        SELECT
            a.id_asignacion,
            a.id_tarea,
            tbse.id_proyectos,
            ds_ent.nombre               AS archivo_nombre,
            ds_ent.ruta                 AS archivo_ruta,
            ds_ent.tipo_mime            AS archivo_tipo,
            ds_ent.extension            AS archivo_extension,
            esta.nombre                 AS estado,
            a.id_estadoT,
            t.descripcion,
            t.instrucciones,
            t.fecha_entrega,
            t.fecha_modificacion,
            tt.descripcion_tipo         AS tipo_tarea,
            a.contenido,
            ds_rec.nombre               AS guia_nombre,
            ds_rec.ruta                 AS guia_ruta
        FROM tareas_usuarios a
        INNER JOIN tareas t             ON t.id_tarea       = a.id_tarea
        INNER JOIN tbl_seguimiento tbse ON t.id_avances     = tbse.id_avances
        INNER JOIN tipo_tarea tt        ON tt.id_tareatipo  = t.id_tareatipo
        INNER JOIN estados_tarea esta   ON esta.id_estadoT  = a.id_estadoT
        LEFT  JOIN documentos_subidos ds_ent
                ON ds_ent.id_documento  = a.id_documento_entrega
        LEFT  JOIN documentos_subidos ds_rec
                ON ds_rec.id_documento  = t.id_documento_recurso
        WHERE a.id_asignacion = ?
        LIMIT 1
        ";

        $stmt = $this->con->prepare($sql);
        if (!$stmt) die("Error al preparar consulta: " . $this->con->error);
        $stmt->bind_param("i", $id_asignacion);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    // ─────────────────────────────────────────────
    //  OBTENER TAREA GENERAL (para editar.php / detalles.php)
    // ─────────────────────────────────────────────
    public function obtenerTareaGeneral($id_tarea)
    {
        $sql = "
        SELECT
            tare.id_tarea,
            tita.descripcion_tipo       AS tipo,
            tare.descripcion,
            tare.instrucciones,
            tare.fecha_entrega,
            tare.fecha_modificacion,
            tita.descripcion_tipo       AS titulo_tarea,
            esta.nombre                 AS estado,
            tare.id_estadoT,
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

        // ─────────────────────────────────────────────
    //  EDICIONES RECIENTES DE UNA TAREA (para detalles/editar)
    // ─────────────────────────────────────────────

        public function obtenerEdicionesRecientes($id_tarea, $limite = 5)
    {
        $sql = "SELECT
            th.campo_modificado,
            th.valor_anterior,
            th.valor_nuevo,
            th.fecha,
            u.nombre AS editor
        FROM tareas_historial th
        LEFT JOIN usuarios u ON u.id_usuarios = th.id_usuarios
        WHERE th.tipo_cambio = 'edicion'
          AND th.id_asignacion IN (
              SELECT id_asignacion FROM tareas_usuarios WHERE id_tarea = ?
          )
        ORDER BY th.fecha DESC
        LIMIT ? ";
        $stmt = $this->con->prepare($sql);
        if (!$stmt) {
            die("Error al preparar consulta: " . $this->con->error);
        }
        $stmt->bind_param("ii", $id_tarea, $limite);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    
    // ─────────────────────────────────────────────
    //  LÍNEA DE TIEMPO (solo cambios de estado)
    // ─────────────────────────────────────────────
    public function linea_tiempo_tarea($id_asignacion, $pagina = 1, $por_pagina = 10)
    {
        $pagina    = max(1, (int)$pagina);
        $desde     = ($pagina - 1) * $por_pagina;

        // Total solo de registros tipo 'estado'
        $sqlTotal = "SELECT COUNT(*) AS total
                     FROM tareas_historial
                     WHERE id_asignacion = ?
                       AND tipo_cambio = 'estado'";
        $stmt = $this->con->prepare($sqlTotal);
        $stmt->bind_param("i", $id_asignacion);
        $stmt->execute();
        $total = $stmt->get_result()->fetch_assoc()['total'];
        $stmt->close();

        $total_paginas = max(1, (int)ceil($total / $por_pagina));

        // Datos de cambios de estado
        $sql = "
        SELECT
            tahi.id_tareas_historial,
            tahi.id_estadoT,
            et.nombre                   AS estado,
            CASE
                WHEN e.id_usuarios IS NOT NULL THEN 1
                ELSE 0
            END AS esEstudiante,
            tahi.comentario,
            tahi.fecha,
            u.nombre AS usuario
        FROM tareas_historial tahi
        LEFT JOIN estados_tarea et  ON et.id_estadoT  = tahi.id_estadoT
        LEFT JOIN estudiantes e     ON e.id_usuarios  = tahi.id_usuarios
        LEFT JOIN usuarios u        ON u.id_usuarios  = tahi.id_usuarios
        WHERE tahi.id_asignacion = ?
          AND tahi.tipo_cambio = 'estado'
        ORDER BY tahi.fecha DESC
        LIMIT ?, ?
        ";

        $stmt = $this->con->prepare($sql);
        if (!$stmt) die("Error al preparar consulta: " . $this->con->error);
        $stmt->bind_param("iii", $id_asignacion, $desde, $por_pagina);
        $stmt->execute();
        $historial = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        // Agrupar por fecha
        $agrupado = [];
        foreach ($historial as $item) {
            $fecha = date("d/m/Y", strtotime($item['fecha']));
            $agrupado[$fecha][] = $item;
        }

        return [
            "datos"      => $agrupado,
            "paginacion" => [
                "total"        => $total,
                "por_pagina"   => $por_pagina,
                "pagina"       => $pagina,
                "total_paginas" => $total_paginas
            ]
        ];
    }
}
