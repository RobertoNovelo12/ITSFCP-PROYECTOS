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

    // 
    //  ACTUALIZAR VENCIDOS
    // 
    public function actualizarTareasVencidos(): void
    {
        $hoy = date("Y-m-d");

        $sqlDetectar = "
            SELECT taus.id_asignacion, taus.id_estadoT
            FROM tareas_usuarios taus
            JOIN tareas tare ON taus.id_tarea = tare.id_tarea
            WHERE taus.id_estadoT IN (1, 2, 3, 8)
              AND tare.fecha_entrega < ?
        ";
        $stmt = $this->con->prepare($sqlDetectar);
        $stmt->bind_param("s", $hoy);
        $stmt->execute();
        $vencidos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        if (empty($vencidos)) return;

        $sqlUpdate = "
            UPDATE tareas_usuarios taus
            JOIN tareas tare ON taus.id_tarea = tare.id_tarea
            SET taus.id_estadoT = 6
            WHERE taus.id_estadoT IN (1, 2, 3, 8)
              AND tare.fecha_entrega < ?
        ";
        $stmt = $this->con->prepare($sqlUpdate);
        $stmt->bind_param("s", $hoy);
        $stmt->execute();
        $stmt->close();

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

    // 
    //  MODELO — método actualizarTareasConcluidas()
    //
    //  Lógica:
    //    Una tarea pasa a estado 9 (Concluido) en la tabla `tareas` cuando
    //    TODOS los estudiantes ACTIVOS del proyecto (proyectos_usuarios.estado = 'activo')
    //    que tienen asignación en tareas_usuarios tienen id_estadoT = 5 (Aprobado).
    //
    //    Se ignoran estudiantes con baja/cancelado/concluido en proyectos_usuarios,
    //    ya que dejaron de ser participantes activos.
    //
    //    Se llama igual que actualizarTareasVencidos: antes de cualquier
    //    operación relevante en el controlador.
    //
    //  Parámetro opcional $id_tarea:
    //    - Si se pasa: solo evalúa esa tarea (más eficiente al aprobar una asignación).
    //    - Si es null: evalúa todas las tareas activas (para revisiones globales).
    // 

    public function actualizarTareasConcluidas(?int $id_tarea = null): void
    {
        // ── 1. Obtener tareas candidatas ─────────────────────────────────────
        //    Candidatas: tareas con id_estadoT IN (1,2,3) — activas pero no
        //    concluidas ni vencidas ni sin activar.
        //    Si se pasa $id_tarea solo evaluamos esa.

        if ($id_tarea !== null) {
            $sqlTareas = "
            SELECT t.id_tarea, tbse.id_proyectos
            FROM tareas t
            JOIN tbl_seguimiento tbse ON tbse.id_avances = t.id_avances
            WHERE t.id_tarea     = ?
              AND t.id_estadoT   IN (1, 2, 3)
        ";
            $stmtT = $this->con->prepare($sqlTareas);
            if (!$stmtT) return;
            $stmtT->bind_param("i", $id_tarea);
        } else {
            $sqlTareas = "
            SELECT t.id_tarea, tbse.id_proyectos
            FROM tareas t
            JOIN tbl_seguimiento tbse ON tbse.id_avances = t.id_avances
            WHERE t.id_estadoT IN (1, 2, 3)
        ";
            $stmtT = $this->con->prepare($sqlTareas);
            if (!$stmtT) return;
        }

        $stmtT->execute();
        $tareas = $stmtT->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmtT->close();

        if (empty($tareas)) return;

        // ── 2. Para cada tarea verificar si TODOS los activos están aprobados ─

        // 2a. Contar estudiantes activos del proyecto que tienen asignación
        $sqlTotalActivos = "
        SELECT COUNT(*) AS total
        FROM tareas_usuarios tu
        JOIN proyectos_usuarios pu
             ON pu.id_proyectos = ?
            AND pu.id_usuarios  = tu.id_usuarios
            AND pu.estado       = 'activo'
        WHERE tu.id_tarea = ?
    ";
        $stmtTotal = $this->con->prepare($sqlTotalActivos);
        if (!$stmtTotal) return;

        // 2b. Contar cuántos de esos activos tienen estado Aprobado (5)
        $sqlAprobados = "
        SELECT COUNT(*) AS aprobados
        FROM tareas_usuarios tu
        JOIN proyectos_usuarios pu
             ON pu.id_proyectos = ?
            AND pu.id_usuarios  = tu.id_usuarios
            AND pu.estado       = 'activo'
        WHERE tu.id_tarea    = ?
          AND tu.id_estadoT  = 5
    ";
        $stmtAprobados = $this->con->prepare($sqlAprobados);
        if (!$stmtAprobados) return;

        // 2c. UPDATE tarea a Concluido (9)
        $sqlConcluir = "
        UPDATE tareas
        SET id_estadoT = 9
        WHERE id_tarea = ?
          AND id_estadoT IN (1, 2, 3)
    ";
        $stmtConcluir = $this->con->prepare($sqlConcluir);
        if (!$stmtConcluir) return;

        // 2d. Registrar en historial por cada asignación de la tarea
        $sqlHistorial = "
        INSERT INTO tareas_historial
            (id_asignacion, id_estadoT, id_usuarios, comentario, tipo_cambio)
        SELECT tu.id_asignacion, 9, 1,
               'Tarea marcada como concluida automáticamente', 'estado'
        FROM tareas_usuarios tu
        JOIN proyectos_usuarios pu
             ON pu.id_proyectos = ?
            AND pu.id_usuarios  = tu.id_usuarios
            AND pu.estado       = 'activo'
        WHERE tu.id_tarea = ?
    ";
        $stmtHist = $this->con->prepare($sqlHistorial);
        if (!$stmtHist) return;

        foreach ($tareas as $tarea) {
            $idT  = (int) $tarea['id_tarea'];
            $idP  = (int) $tarea['id_proyectos'];

            // Contar activos con asignación
            $stmtTotal->bind_param("ii", $idP, $idT);
            $stmtTotal->execute();
            $total = (int) $stmtTotal->get_result()->fetch_assoc()['total'];

            // Debe haber al menos 1 activo para poder concluir
            if ($total === 0) continue;

            // Contar aprobados
            $stmtAprobados->bind_param("ii", $idP, $idT);
            $stmtAprobados->execute();
            $aprobados = (int) $stmtAprobados->get_result()->fetch_assoc()['aprobados'];

            // Si todos los activos están aprobados → concluir
            if ($aprobados === $total) {
                $stmtConcluir->bind_param("i", $idT);
                $stmtConcluir->execute();

                // Solo registrar historial si efectivamente se actualizó
                if ($stmtConcluir->affected_rows > 0) {
                    $stmtHist->bind_param("ii", $idP, $idT);
                    $stmtHist->execute();
                }
            }
        }

        $stmtTotal->close();
        $stmtAprobados->close();
        $stmtConcluir->close();
        $stmtHist->close();
    }

    // 
    //  OBTENER TAREAS (tabla principal)
    // 
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
                INNER JOIN tipo_tarea tt        ON t.id_tareatipo   = tt.id_tareatipo
                LEFT  JOIN estados_tarea est    ON t.id_estadoT     = est.id_estadoT
                LEFT  JOIN estados_tarea esu    ON taus.id_estadoT  = esu.id_estadoT
                LEFT  JOIN documentos_subidos ds_rec
                        ON ds_rec.id_documento  = t.id_documento_recurso
                LEFT  JOIN documentos_subidos ds_ent
                        ON ds_ent.id_documento  = taus.id_documento_entrega
                WHERE s.id_proyectos = ? AND taus.id_usuarios = ?
                ORDER BY t.id_tarea ASC
                ";
                $stmt = $this->con->prepare($sql);
                $stmt->bind_param("ii", $id_proyecto, $id_usuario);
                break;

            case 'profesor':
            case 'investigador':
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
                INNER JOIN proyectos proy    ON proy.id_proyectos = s.id_proyectos
                INNER JOIN tipo_tarea tt     ON t.id_tareatipo = tt.id_tareatipo
                LEFT  JOIN estados_tarea est ON t.id_estadoT   = est.id_estadoT
                LEFT  JOIN documentos_subidos ds_rec
                        ON ds_rec.id_documento = t.id_documento_recurso
                WHERE s.id_proyectos = ? AND proy.id_investigador = ?
                ORDER BY t.id_tarea ASC
                ";
                $stmt = $this->con->prepare($sql);
                $stmt->bind_param("ii", $id_proyecto, $id_usuario);
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

    // 
    //  OBTENER TAREAS LISTA (por id_tarea)
    // 
    public function obtenerTareasLista($id_tarea, $rol, $id_usuario)
    {
        switch ($rol) {
            case 'profesor':
            case 'investigador':
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
                INNER JOIN tbl_seguimiento s ON t.id_avances   = s.id_avances
                INNER JOIN proyectos proy    ON proy.id_proyectos = s.id_proyectos
                LEFT  JOIN documentos_subidos ds_ent
                ON ds_ent.id_documento = tu.id_documento_entrega
                WHERE tu.id_tarea = ? AND proy.id_investigador = ?
                ORDER BY estudiante ASC
                ";
                $stmt = $this->con->prepare($sql);
                $stmt->bind_param("ii", $id_tarea, $id_usuario);
                break;
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

    // 
    //  OBTENER TAREAS ESTUDIANTE
    // 
    public function obtenerTareasEstudiante(int $id_usuario, int $id_proyectos)
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
                WHEN 8 THEN 'Borrador'
                ELSE 'Desconocido'
            END AS estado_texto,
            tita.descripcion_tipo AS tipo
        FROM tareas_usuarios tu
        INNER JOIN tareas t ON t.id_tarea = tu.id_tarea
        INNER JOIN tipo_tarea AS tita ON t.id_tareatipo = tita.id_tareatipo
        INNER JOIN tbl_seguimiento AS ts ON ts.id_avances = t.id_avances 
        WHERE tu.id_usuarios = ? AND ts.id_proyectos = ?
        ORDER BY tu.id_asignacion DESC
        ";

        $stmt = $this->con->prepare($sql);
        $stmt->bind_param("ii", $id_usuario, $id_proyectos);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    // 
    //  REGISTRAR DOCUMENTO
    // 
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

    // 
    //  EDITAR TAREA GENERAL (investigador - plantilla)
    // 
    public function editarTareaGeneral(
        int     $id_tarea,
        string  $descripcion,
        string  $instrucciones,
        string  $fecha_entrega,
        ?int    $id_documento_recurso,
        int     $id_usuario
    ): void {

        $sqlActual = "SELECT descripcion, instrucciones, fecha_entrega FROM tareas WHERE id_tarea = ?";
        $stmtA = $this->con->prepare($sqlActual);
        $stmtA->bind_param("i", $id_tarea);
        $stmtA->execute();
        $actual = $stmtA->get_result()->fetch_assoc();
        $stmtA->close();

        $sqlAsig = "SELECT id_asignacion FROM tareas_usuarios WHERE id_tarea = ? LIMIT 1";
        $stmtAsi = $this->con->prepare($sqlAsig);
        $stmtAsi->bind_param("i", $id_tarea);
        $stmtAsi->execute();
        $primeraAsig = $stmtAsi->get_result()->fetch_assoc();
        $stmtAsi->close();

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

        if (!$primeraAsig) return;

        $sqlAllAsig = "SELECT id_asignacion FROM tareas_usuarios WHERE id_tarea = ?";
        $stmtAll = $this->con->prepare($sqlAllAsig);
        $stmtAll->bind_param("i", $id_tarea);
        $stmtAll->execute();
        $todasAsig = $stmtAll->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmtAll->close();

        $cambios = [];
        if ($actual['descripcion'] !== $descripcion) {
            $cambios[] = ['campo_modificado' => 'descripcion',   'valor_anterior' => $actual['descripcion'],   'valor_nuevo' => $descripcion];
        }
        if ($actual['instrucciones'] !== $instrucciones) {
            $cambios[] = ['campo_modificado' => 'instrucciones', 'valor_anterior' => $actual['instrucciones'], 'valor_nuevo' => $instrucciones];
        }
        if ($actual['fecha_entrega'] !== $fecha_entrega) {
            $cambios[] = ['campo_modificado' => 'fecha_entrega', 'valor_anterior' => $actual['fecha_entrega'], 'valor_nuevo' => $fecha_entrega];
        }
        if ($id_documento_recurso !== null) {
            $cambios[] = ['campo_modificado' => 'archivo_guia',  'valor_anterior' => null,                    'valor_nuevo' => 'Nuevo archivo subido'];
        }

        if (empty($cambios)) return;

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

    // 
    //  EDITAR TAREA ESTUDIANTE
    // 
    public function editarTareaEstudiante(
        int     $id_asignacion,
        int     $id_tarea,
        string  $contenido,
        string  $comentarios,
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

    // 
    //  GUARDAR BORRADOR (estado 8)
    // 
    public function guardar_borrador(
        int     $id_tarea,
        int     $id_asignacion,
        int     $id_usuarios,
        string  $contenido,
        string  $comentarios = '',
        ?int    $id_documento_entrega = null
    ): void {

        if (!$id_asignacion) return;

        $sql = "
            UPDATE tareas_usuarios
            SET id_estadoT           = 8,
                contenido            = ?,
                id_documento_entrega = COALESCE(?, id_documento_entrega)
            WHERE id_asignacion = ?
              AND id_tarea      = ?
        ";

        $stmt = $this->con->prepare($sql);
        if (!$stmt) throw new Exception("Error prepare (guardar_borrador): " . $this->con->error);
        $stmt->bind_param("siii", $contenido, $id_documento_entrega, $id_asignacion, $id_tarea);
        if (!$stmt->execute()) throw new Exception("Error execute (guardar_borrador): " . $stmt->error);
        $stmt->close();

        $sqlHist = "
            INSERT INTO tareas_historial
                (id_asignacion, id_estadoT, id_usuarios, comentario, tipo_cambio)
            VALUES (?, 8, ?, 'Borrador guardado por el estudiante', 'estado')
        ";
        $stmtH = $this->con->prepare($sqlHist);
        if (!$stmtH) throw new Exception("Error prepare (historial borrador): " . $this->con->error);
        $stmtH->bind_param("ii", $id_asignacion, $id_usuarios);
        $stmtH->execute();
        $stmtH->close();
    }

    // 
    //  ACTUALIZAR ESTADO
    //
    public function actualizarestado(
        $id_tarea,
        $numeroEstado,
        $id_proyectos,
        $id_asignacion,
        $id_usuarios,
        $comentario
    ): void {

        $numeroEstado = (int) $numeroEstado;

        if ($numeroEstado == 0) {
            error_log("actualizarestado (modelo): numeroEstado=0, se ignora la operación.");
            return;
        }

        // 
        //  ESTADO 1 — ACTIVAR TAREA
        // 
        if ($numeroEstado == 1) {

            $sql  = "UPDATE tareas SET id_estadoT = ? WHERE id_tarea = ?";
            $stmt = $this->con->prepare($sql);
            $stmt->bind_param("ii", $numeroEstado, $id_tarea);
            $stmt->execute();
            $stmt->close();

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

            if (!$proy) {
                error_log("actualizarestado: no se encontró proyecto para id_tarea={$id_tarea}");
                return;
            }

            $id_proyectos = $proy['id_proyectos'];
            $id_tarea     = $proy['id_tarea'];

            $sqlEstudiante = "SELECT id_usuarios FROM proyectos_usuarios WHERE id_proyectos = ?";
            $stmtAlumnos   = $this->con->prepare($sqlEstudiante);
            $stmtAlumnos->bind_param("i", $id_proyectos);
            $stmtAlumnos->execute();
            $alumnos = $stmtAlumnos->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmtAlumnos->close();

            $sqlInsert = "
                INSERT INTO tareas_usuarios (id_tarea, id_usuarios, id_estadoT)
                SELECT ?, ?, 1
                WHERE NOT EXISTS (
                    SELECT 1 FROM tareas_usuarios WHERE id_tarea = ? AND id_usuarios = ?
                )
            ";
            $stmtInsert = $this->con->prepare($sqlInsert);

            $sqlGetAsig  = "SELECT id_asignacion FROM tareas_usuarios WHERE id_tarea = ? AND id_usuarios = ?";
            $stmtGetAsig = $this->con->prepare($sqlGetAsig);

            $sqlHistAct  = "
                INSERT INTO tareas_historial (id_asignacion, id_estadoT, id_usuarios, comentario, tipo_cambio)
                VALUES (?, 1, ?, 'Tarea activada', 'estado')
            ";
            $stmtHistAct = $this->con->prepare($sqlHistAct);

            foreach ($alumnos as $al) {
                $stmtInsert->bind_param("iiii", $id_tarea, $al['id_usuarios'], $id_tarea, $al['id_usuarios']);
                $stmtInsert->execute();

                $stmtGetAsig->bind_param("ii", $id_tarea, $al['id_usuarios']);
                $stmtGetAsig->execute();
                $asigRow = $stmtGetAsig->get_result()->fetch_assoc();

                if ($asigRow && $id_usuarios) {
                    $id_asig_nuevo = $asigRow['id_asignacion'];
                    $stmtHistAct->bind_param("ii", $id_asig_nuevo, $id_usuarios);
                    $stmtHistAct->execute();
                }
            }

            $stmtInsert->close();
            $stmtGetAsig->close();
            $stmtHistAct->close();
        } else {

            // ─
            //  ESTADOS 2 (Revisar), 3 (Corregir), 5 (Aprobado)
            // ─
            switch ($numeroEstado) {

                case 2:
                case 3:
                case 5:

                    if ($id_asignacion != 0) {
                        //  UPDATE estado de la asignación 
                        $sqlUpd  = "UPDATE tareas_usuarios SET id_estadoT = ? WHERE id_asignacion = ?";
                        $stmtUpd = $this->con->prepare($sqlUpd);
                        if (!$stmtUpd) {
                            throw new Exception("Error prepare (actualizarestado UPDATE): " . $this->con->error);
                        }
                        $stmtUpd->bind_param("ii", $numeroEstado, $id_asignacion);
                        if (!$stmtUpd->execute()) {
                            throw new Exception("Error execute (actualizarestado UPDATE): " . $stmtUpd->error);
                        }
                        $stmtUpd->close();

                        //  INSERT historial 
                        $sqlHist  = "
                            INSERT INTO tareas_historial
                                (id_asignacion, id_estadoT, id_usuarios, comentario, tipo_cambio)
                            VALUES (?, ?, ?, ?, 'estado')
                        ";
                        $stmtHist = $this->con->prepare($sqlHist);
                        if (!$stmtHist) {
                            throw new Exception("Error prepare (actualizarestado historial): " . $this->con->error);
                        }
                        $stmtHist->bind_param("iiis", $id_asignacion, $numeroEstado, $id_usuarios, $comentario);
                        if (!$stmtHist->execute()) {
                            throw new Exception("Error execute (actualizarestado historial): " . $stmtHist->error);
                        }
                        $stmtHist->close();
                    } else {
                        // Sin asignación específica: actualizar todas las filas de la tarea
                        $sqlUpd  = "UPDATE tareas_usuarios SET id_estadoT = ? WHERE id_tarea = ?";
                        $stmtUpd = $this->con->prepare($sqlUpd);
                        $stmtUpd->bind_param("ii", $numeroEstado, $id_tarea);
                        if (!$stmtUpd) {
                            throw new Exception("Error prepare (actualizarestado UPDATE): " . $this->con->error);
                        }
                        if (!$stmtUpd->execute()) {
                            throw new Exception("Error execute (actualizarestado UPDATE): " . $stmtUpd->error);
                        }
                        $stmtUpd->close();
                    }
                    break;

                default:
                    error_log("actualizarestado (modelo): estado no válido recibido: {$numeroEstado}");
                    break;
            }
        }
    }

    // 
    //  OBTENER TAREA ALUMNO
    // 
    public function obtenerTareaAlumno($id_asignacion, $id_usuario)
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
        WHERE a.id_asignacion = ? AND a.id_usuarios = ?
        LIMIT 1
        ";

        $stmt = $this->con->prepare($sql);
        if (!$stmt) die("Error al preparar consulta: " . $this->con->error);
        $stmt->bind_param("i", $id_asignacion, $id_usuario);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    // 
    //  OBTENER TAREA GENERAL
    // 
    public function obtenerTareaGeneral($id_tarea, $rol, $id_usuario)
    {
        switch ($rol) {
            case 'estudiante':
                $sql = "
        SELECT
            tare.id_tarea,
            tita.descripcion_tipo       AS tipo,
            tita.id_tareatipo,
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
        INNER JOIN tareas_usuarios taus ON taus.id_tarea = tare.id_tarea
        LEFT  JOIN documentos_subidos ds_rec
                ON ds_rec.id_documento  = tare.id_documento_recurso
        WHERE tare.id_tarea = ? AND taus.id_usuarios = ?
        ";


                $stmt = $this->con->prepare($sql);
                if (!$stmt) die("Error en SQL: " . $this->con->error);
                $stmt->bind_param("ii", $id_tarea, $id_usuario);
                $stmt->execute();
                return $stmt->get_result()->fetch_assoc();
                break;
            case 'investigador':
                $sql = "
        SELECT
            tare.id_tarea,
            tita.descripcion_tipo       AS tipo,
            tita.id_tareatipo,
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
        INNER JOIN tbl_seguimiento tbse     ON tbse.id_avances = tarea.id_avances
        INNER JOIN proyectos tbse proy  ON proy.id_proyectos = tbse.id_proyectos
        LEFT  JOIN documentos_subidos ds_rec
                ON ds_rec.id_documento  = tare.id_documento_recurso
        WHERE tare.id_tarea = ? AND proy.id_investigador = ?
        ";


                $stmt = $this->con->prepare($sql);
                if (!$stmt) die("Error en SQL: " . $this->con->error);
                $stmt->bind_param("ii", $id_tarea, $id_usuario);
                $stmt->execute();
                return $stmt->get_result()->fetch_assoc();
                break;
            case 'supervisor':
                $sql = "
        SELECT
            tare.id_tarea,
            tita.descripcion_tipo       AS tipo,
            tita.id_tareatipo,
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
                break;
        }
    }

    public function obtenerTareaPorId($id_asignacion)
    {
        $sql = "
    SELECT
        ds.id_documento,
        ds.nombre_archivo,
        ds.nombre,
        ds.ruta,
        ds.tipo_mime,
        ds.extension,
        ds.activo
    FROM tareas_usuarios t
    JOIN documentos_subidos ds
           ON ds.id_documento = t.id_documento_entrega
    WHERE t.id_asignacion          = ?
      AND ds.tipo             = 'entrega'
      AND ds.activo           = 1
    LIMIT 1";

        $stmt = $this->con->prepare($sql);
        if (!$stmt) {
            http_response_code(500);
            exit('Error interno del servidor.');
        }

        $stmt->bind_param('i', $id_asignacion);
        $stmt->execute();
        $file = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $file;
    }

    public function obtenerTareaGuiaPorId($id_documento_recurso)
    {
        $sql = "
        SELECT
            ds.id_documento,
            ds.nombre_archivo,
            ds.nombre,
            ds.ruta,
            ds.tipo_mime,
            ds.extension,
            ds.activo
        FROM tareas t
        JOIN documentos_subidos ds
            ON ds.id_documento = t.id_documento_recurso
        WHERE t.id_tarea          = ?
        AND ds.tipo             = 'recurso'
        AND ds.activo           = 1
        LIMIT 1";

        $stmt = $this->con->prepare($sql);
        if (!$stmt) {
            http_response_code(500);
            exit('Error interno del servidor.');
        }

        $stmt->bind_param('i', $id_documento_recurso);
        $stmt->execute();
        $file = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$file) {
            http_response_code(404);
            exit('Esta tarea no tiene guía adjunta o no está disponible.');
        }
        return $file;
    }

    // 
    //  EDICIONES RECIENTES
    // 
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
        LIMIT ?";
        $stmt = $this->con->prepare($sql);
        if (!$stmt) die("Error al preparar consulta: " . $this->con->error);
        $stmt->bind_param("ii", $id_tarea, $limite);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    // 
    //  LÍNEA DE TIEMPO
    // 
    public function linea_tiempo_tarea($id_asignacion, $pagina = 1, $por_pagina = 6)
    {
        $pagina = max(1, (int)$pagina);
        $desde  = ($pagina - 1) * $por_pagina;

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

        $agrupado = [];
        foreach ($historial as $item) {
            $fecha = date("d/m/Y", strtotime($item['fecha']));
            $agrupado[$fecha][] = $item;
        }

        return [
            "datos"      => $agrupado,
            "paginacion" => [
                "total"         => $total,
                "por_pagina"    => $por_pagina,
                "pagina"        => $pagina,
                "total_paginas" => $total_paginas,
            ],
        ];
    }

    /**
     * Obtiene datos de una plantilla por su ID para descarga segura.
     */
    public function obtenerPlantillaPorId(int $id_plantilla): ?array
    {
        $sql = "
            SELECT
                pd.id_plantilla,
                pd.activo          AS plantilla_activa,
                ds.nombre_archivo,
                ds.ruta,
                ds.activo          AS archivo_activo,
                ds.tipo_mime,
                ds.extension
            FROM plantillas_documentos pd
            JOIN documentos_subidos ds ON ds.id_documento = pd.id_documento
            WHERE pd.id_plantilla = ?
            LIMIT 1
        ";
        $stmt = $this->con->prepare($sql);
        if (!$stmt) throw new Exception("Error prepare (obtenerPlantillaPorId): " . $this->con->error);
        $stmt->bind_param("i", $id_plantilla);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc() ?: null;
    }
}
