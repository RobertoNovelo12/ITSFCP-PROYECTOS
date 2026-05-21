<?php
require_once __DIR__ . '/../publico/config/conexion.php';

/**
 * Modelo Solicitud de integración a proyecto
 *
 * 
 *  SECCIÓN A  │  Módulo del INVESTIGADOR
 *             │  Gestión de solicitudes recibidas: listado, detalle, aceptar,
 *             │  rechazar, pedir correcciones, vencido automático.
 * 
 *  SECCIÓN B  │  Módulo del ESTUDIANTE
 *             │  Envío de solicitud de integración con carta compromiso,
 *             │  cancelación, respuesta a correcciones, consulta de estado.
 * 
 */
class Solicitud
{
    private mysqli $con;

    public function __construct(mysqli $conn)
    {
        $this->con = $conn;
    }

    // 
    //  UTILIDADES INTERNAS
    // 

    /**
     * Inserta un comentario en solicitud_comentarios.
     */
    private function insertarComentario(
        int    $id_solicitud,
        int    $id_usuario,
        string $tipo,
        string $comentario,
        ?int   $id_documento = null
    ): bool {
        $sql = "
            INSERT INTO solicitud_comentarios
                (id_solicitud, id_usuario, tipo, comentario, id_documento_adjunto, fecha)
            VALUES (?, ?, ?, ?, ?, NOW())
        ";
        $stmt = $this->con->prepare($sql);
        if (!$stmt) throw new Exception("Error prepare (insertarComentario): " . $this->con->error);
        $stmt->bind_param("iissi", $id_solicitud, $id_usuario, $tipo, $comentario, $id_documento);
        if (!$stmt->execute()) throw new Exception("Error execute (insertarComentario): " . $stmt->error);
        $stmt->close();
        return true;
    }

    // 
    //  SECCIÓN A — INVESTIGADOR
    // 

    //  Archivos / documentos 

    /**
     * Registra un archivo en documentos_subidos y devuelve su id_documento.
     */
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
        ?int    $id_etapa       = null,
        int     $version        = 1,
        ?int    $id_plantilla   = null,
        ?int    $id_seguimiento = null
    ): int {
        $id_proyecto_val = $id_proyecto ?: null;

        $visibilidad = strtolower(trim($_POST['visibilidad'] ?? ''));

        if (!in_array($visibilidad, ['publico', 'privado'])) {
            throw new Exception("Visibilidad inválida");
        }
        $sql = "
            INSERT INTO documentos_subidos
                (nombre, nombre_archivo, ruta, tipo_mime, extension, tamano_bytes,
                 tipo, visibilidad, id_usuarios, id_proyectos, id_etapa, version,
                 activo, fecha_subida, id_plantilla, id_seguimiento)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW(), ?, ?)
        ";
        $stmt = $this->con->prepare($sql);
        if (!$stmt) throw new Exception("Error prepare (registrarDocumento): " . $this->con->error);

        $stmt->bind_param(
            "sssssisisiiiii",
            $nombre,
            $nombre_archivo,
            $ruta,
            $tipo_mime,
            $extension,
            $tamano_bytes,
            $tipo,
            $visibilidad,
            $id_usuario,
            $id_proyecto_val,
            $id_etapa,
            $version,
            $id_plantilla,
            $id_seguimiento
        );

        if (!$stmt->execute()) throw new Exception("Error execute (registrarDocumento): " . $stmt->error);
        $id = $stmt->insert_id;
        $stmt->close();
        return $id;
    }

    //  Periodos ─

    public function periodosDelInvestigador(int $id): array
    {
        $sql = "
            SELECT DISTINCT
                pe.id_periodos,
                pe.periodo,
                pe.estado
            FROM solicitud_proyecto sp
            JOIN proyectos p  ON p.id_proyectos = sp.id_proyectos
            JOIN periodos  pe ON pe.id_periodos  = p.id_periodos
            WHERE p.id_investigador = ?
              AND pe.periodo IS NOT NULL
            ORDER BY pe.periodo DESC
        ";
        $stmt = $this->con->prepare($sql);
        if (!$stmt) throw new Exception("Error prepare (periodosDelInvestigador): " . $this->con->error);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    //  Resumen / listado 

    public function resumen(int $id, ?int $id_periodo = null): array
    {
        $wherePeriodo = $id_periodo ? "AND p.id_periodos = ?" : "";
        $sql = "
            SELECT
                COUNT(*)                          total,
                SUM(sp.estado='pendiente')        pendientes,
                SUM(sp.estado='en_revision')      en_revision,
                SUM(sp.estado='correcciones')     correcciones,
                SUM(sp.estado='aceptado')         aceptadas,
                SUM(sp.estado='rechazado')        rechazadas
            FROM solicitud_proyecto sp
            JOIN proyectos p ON p.id_proyectos = sp.id_proyectos
            WHERE p.id_investigador = ?
            $wherePeriodo
        ";
        $stmt = $this->con->prepare($sql);
        if ($id_periodo) {
            $stmt->bind_param("ii", $id, $id_periodo);
        } else {
            $stmt->bind_param("i", $id);
        }
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc() ?? [];
    }

    public function contarSolicitudes(int $id, array $f): int
    {
        [$where, $params, $types] = $this->construirWhere($id, $f);
        $sql = "
            SELECT COUNT(*)
            FROM solicitud_proyecto sp
            JOIN proyectos p   ON p.id_proyectos  = sp.id_proyectos
            JOIN usuarios u    ON u.id_usuarios   = sp.id_estudiante
            JOIN estudiantes e ON e.id_usuarios   = sp.id_estudiante
            JOIN carreras c    ON c.id_carrera    = e.id_carrera
            $where
        ";
        $stmt = $this->con->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        return $stmt->get_result()->fetch_row()[0] ?? 0;
    }

    public function obtenerSolicitudes(int $id, array $f, int $desde, int $limite): array
    {
        [$where, $params, $types] = $this->construirWhere($id, $f);
        $sql = "
            SELECT
                sp.*,
                CONCAT(u.nombre,' ',u.apellido_paterno,' ',u.apellido_materno) estudiante_nombre,
                u.correo_institucional,
                e.matricula,
                c.nombre_carrera carrera,
                p.titulo proyecto_titulo
            FROM solicitud_proyecto sp
            JOIN proyectos p   ON p.id_proyectos  = sp.id_proyectos
            JOIN usuarios u    ON u.id_usuarios   = sp.id_estudiante
            JOIN estudiantes e ON e.id_usuarios   = sp.id_estudiante
            JOIN carreras c    ON c.id_carrera    = e.id_carrera
            $where
            ORDER BY sp.fecha_envio ASC
            LIMIT ?, ?
        ";
        $params[] = $desde;
        $params[] = $limite;
        $types   .= "ii";
        $stmt = $this->con->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    private function construirWhere(int $id, array $f): array
    {
        $cond   = ["p.id_investigador = ?"];
        $params = [$id];
        $types  = "i";

        if (!empty($f['periodo'])) {
            $cond[]   = "p.id_periodos = ?";
            $params[] = intval($f['periodo']);
            $types   .= "i";
        }
        if (!empty($f['estado'])) {
            $cond[]   = "sp.estado = ?";
            $params[] = $f['estado'];
            $types   .= "s";
        }
        if (!empty($f['buscar'])) {
            $cond[] = "(u.nombre LIKE ? OR e.matricula LIKE ? OR p.titulo LIKE ?)";
            $b = "%" . $f['buscar'] . "%";
            array_push($params, $b, $b, $b);
            $types .= "sss";
        }
        if (!empty($f['proyecto'])) {
            $cond[]   = "sp.id_proyectos = ?";
            $params[] = intval($f['proyecto']);
            $types   .= "i";
        }
        if (!empty($f['semestre'])) {
            $cond[]   = "e.semestre = ?";
            $params[] = intval($f['semestre']);
            $types   .= "i";
        }
        if (!empty($f['fecha_desde'])) {
            $cond[]   = "sp.fecha_envio >= ?";
            $params[] = $f['fecha_desde'];
            $types   .= "s";
        }
        if (!empty($f['fecha_hasta'])) {
            $cond[]   = "sp.fecha_envio <= ?";
            $params[] = $f['fecha_hasta'];
            $types   .= "s";
        }

        return ["WHERE " . implode(" AND ", $cond), $params, $types];
    }

    //  Detalle para el investigador ─

    /**
     * Detalle completo de una solicitud incluyendo la carta compromiso del estudiante
     * (documento vinculado a seguimiento_documento etapa 1 / tipo_documento 1).
     */
    public function obtenerDetalle(int $id): ?array
    {
        $sql = "
            SELECT
                sp.*,
                CONCAT(u.nombre,' ',u.apellido_paterno,' ',u.apellido_materno) AS estudiante_nombre,
                u.correo_institucional,
                e.matricula,
                c.nombre_carrera          AS carrera,
                p.titulo                  AS proyecto_titulo,
                p.modalidad,
                p.id_investigador,
                -- CV / constancias adjuntas al enviar la solicitud
                ds_cv.nombre              AS cv_nombre,
                ds_cv.ruta                AS cv_ruta,
                ds_cv.extension           AS cv_extension,
                -- Carta compromiso firmada (seguimiento etapa 1)
                sd.id_seguimiento         AS seg_id,
                sd.estado                 AS seg_estado,
                sd.fecha_revision         AS seg_fecha_revision,
                sd.comentario_supervisor  AS seg_comentario,
                ds_carta.id_documento     AS carta_id_documento,
                ds_carta.nombre           AS carta_nombre,
                ds_carta.ruta             AS carta_ruta,
                ds_carta.extension        AS carta_extension,
                ds_carta.fecha_subida     AS carta_fecha_subida,
                -- Plantilla vigente de carta compromiso
                pd.id_plantilla           AS plantilla_id,
                pd.nombre                 AS plantilla_nombre,
                pd.version                AS plantilla_version,
                ds_plantilla.ruta         AS plantilla_ruta,
                ds_plantilla.nombre_archivo AS plantilla_archivo
            FROM solicitud_proyecto sp
            JOIN proyectos p           ON p.id_proyectos      = sp.id_proyectos
            JOIN usuarios u            ON u.id_usuarios       = sp.id_estudiante
            JOIN estudiantes e         ON e.id_usuarios       = sp.id_estudiante
            JOIN carreras c            ON c.id_carrera        = e.id_carrera
            -- CV adjunto (puede ser nulo si no subió)
            LEFT JOIN documentos_subidos ds_cv
                   ON ds_cv.id_documento = sp.id_documento
            -- Seguimiento etapa 1 (carta compromiso) de este estudiante en este proyecto
            LEFT JOIN seguimiento_documento sd
                   ON sd.id_proyectos      = sp.id_proyectos
                  AND sd.id_usuarios       = sp.id_estudiante
                  AND sd.id_tipo_documento = 1
            -- Documento subido vinculado al seguimiento (carta firmada)
            LEFT JOIN documentos_subidos ds_carta
                   ON ds_carta.id_seguimiento = sd.id_seguimiento
                  AND ds_carta.activo         = 1
                  AND ds_carta.tipo           = 'etapa'
            -- Plantilla vigente de carta compromiso (tipo_documento = 1)
            LEFT JOIN plantillas_documentos pd
                   ON pd.id_tipo_documento = 1
                  AND pd.activo            = 1
            LEFT JOIN documentos_subidos ds_plantilla
                   ON ds_plantilla.id_documento = pd.id_documento
            WHERE sp.id_solicitud_proyecto = ?
            LIMIT 1
        ";
        $stmt = $this->con->prepare($sql);
        if (!$stmt) throw new Exception("Error prepare (obtenerDetalle): " . $this->con->error);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    //  Permiso 

    public function verificarPermiso(int $id, int $inv): bool
    {
        $sql = "
            SELECT COUNT(*)
            FROM solicitud_proyecto sp
            JOIN proyectos p ON p.id_proyectos = sp.id_proyectos
            WHERE sp.id_solicitud_proyecto = ? AND p.id_investigador = ?
        ";
        $stmt = $this->con->prepare($sql);
        $stmt->bind_param("ii", $id, $inv);
        $stmt->execute();
        return $stmt->get_result()->fetch_row()[0] > 0;
    }

    //  Cambios de estado 

    public function marcarEnRevision(int $id): bool
    {
        $stmt = $this->con->prepare("
            UPDATE solicitud_proyecto
            SET estado = 'en_revision'
            WHERE id_solicitud_proyecto = ? AND estado = 'pendiente'
        ");
        $stmt->bind_param("i", $id);
        return $stmt->execute();
    }

    /**
     * Acepta la solicitud y actualiza seguimiento_documento (etapa 1) a 'completado'
     * cuando la carta compromiso ya fue subida y revisada.
     */
    public function aceptar(int $id): bool
    {
        $stmt = $this->con->prepare("
            UPDATE solicitud_proyecto
            SET estado = 'aceptado', fecha_respuesta = CURDATE()
            WHERE id_solicitud_proyecto = ?
        ");
        $stmt->bind_param("i", $id);
        if (!$stmt->execute()) return false;
        $stmt->close();

        // Marcar seguimiento etapa 1 como completado si existe y estaba en proceso
        $stmt2 = $this->con->prepare("
            UPDATE seguimiento_documento sd
            JOIN solicitud_proyecto sp
                ON sp.id_proyectos = sd.id_proyectos
               AND sp.id_estudiante = sd.id_usuarios
            SET sd.estado        = 'completado',
                sd.fecha_fin     = NOW(),
                sd.fecha_revision = NOW()
            WHERE sp.id_solicitud_proyecto = ?
              AND sd.id_tipo_documento     = 1
              AND sd.estado               IN ('pendiente','proceso')
        ");
        if ($stmt2) {
            $stmt2->bind_param("i", $id);
            $stmt2->execute();
            $stmt2->close();
        }

        return true;
    }

    public function pedirCorrecciones(int $id, int $id_usuario, string $comentario, ?int $id_documento = null): bool
    {
        $stmt = $this->con->prepare("
            UPDATE solicitud_proyecto SET estado = 'correcciones'
            WHERE id_solicitud_proyecto = ?
        ");
        if (!$stmt) throw new Exception("Error prepare (pedirCorrecciones): " . $this->con->error);
        $stmt->bind_param("i", $id);
        if (!$stmt->execute()) throw new Exception("Error execute: " . $stmt->error);
        $stmt->close();

        // Marcar seguimiento carta como 'pendiente' (en_correccion) si existe
        $stmt2 = $this->con->prepare("
            UPDATE seguimiento_documento sd
            JOIN solicitud_proyecto sp
                ON sp.id_proyectos  = sd.id_proyectos
               AND sp.id_estudiante = sd.id_usuarios
            SET sd.estado                 = 'pendiente',
                sd.comentario_supervisor  = ?,
                sd.fecha_revision         = NOW(),
                sd.revisado_por           = ?
            WHERE sp.id_solicitud_proyecto = ?
              AND sd.id_tipo_documento     = 1
        ");
        if ($stmt2) {
            $stmt2->bind_param("sii", $comentario, $id_usuario, $id);
            $stmt2->execute();
            $stmt2->close();
        }

        // Actualizar estado del proceso en proyectos_usuarios si ya fue vinculado
        $this->actualizarEstadoProceso($id, 3); // 3 = en_correccion

        return $this->insertarComentario($id, $id_usuario, 'investigador', $comentario, $id_documento);
    }

    public function rechazar(int $id, int $id_usuario, string $comentario, ?int $id_documento = null): bool
    {
        $stmt = $this->con->prepare("
            UPDATE solicitud_proyecto
            SET estado = 'rechazado', motivo_rechazo = ?, fecha_respuesta = CURDATE()
            WHERE id_solicitud_proyecto = ?
        ");
        if (!$stmt) throw new Exception("Error prepare (rechazar): " . $this->con->error);
        $stmt->bind_param("si", $comentario, $id);
        if (!$stmt->execute()) throw new Exception("Error execute: " . $stmt->error);
        $stmt->close();

        return $this->insertarComentario($id, $id_usuario, 'investigador', $comentario, $id_documento);
    }

    /**
     * Actualiza id_estados_proceso en proyectos_usuarios para el estudiante/proyecto
     * vinculado a la solicitud, si la fila ya existe.
     */
    private function actualizarEstadoProceso(int $id_solicitud, int $id_estados_proceso): void
    {
        $stmt = $this->con->prepare("
            UPDATE proyectos_usuarios pu
            JOIN solicitud_proyecto sp
                ON sp.id_proyectos  = pu.id_proyectos
               AND sp.id_estudiante = pu.id_usuarios
            SET pu.id_estados_proceso = ?
            WHERE sp.id_solicitud_proyecto = ?
        ");
        if (!$stmt) return;
        $stmt->bind_param("ii", $id_estados_proceso, $id_solicitud);
        $stmt->execute();
        $stmt->close();
    }

    //  Vencido 

    /**
     * Obtiene las solicitudes que deben pasar a 'vencido'.
     *
     * Condición: la fecha actual ya salió del rango de solicitud del periodo
     * (fecha_fin_solicitud < HOY) y la solicitud aún no tiene estado definitivo.
     *
     * CORRECCIÓN: ya no usa fecha_fin del proyecto sino fecha_fin_solicitud del periodo.
     */
    public function obtenervencido(): array
    {
        $stmt = $this->con->prepare("
            SELECT sp.id_solicitud_proyecto
            FROM solicitud_proyecto sp
            JOIN proyectos p  ON p.id_proyectos = sp.id_proyectos
            JOIN periodos  pe ON pe.id_periodos  = p.id_periodos
            WHERE pe.fecha_fin_solicitud IS NOT NULL
              AND CURDATE() > pe.fecha_fin_solicitud
              AND sp.estado NOT IN ('vencido','rechazado','aceptado','cancelado')
        ");
        if (!$stmt) throw new Exception("Error prepare (obtenervencido): " . $this->con->error);
        if (!$stmt->execute()) throw new Exception("Error execute (obtenervencido): " . $stmt->error);
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return array_column($rows, 'id_solicitud_proyecto');
    }

    public function vencido(int $id): void
    {
        $stmt = $this->con->prepare("
            UPDATE solicitud_proyecto
            SET estado = 'vencido', fecha_respuesta = CURDATE()
            WHERE id_solicitud_proyecto = ?
        ");
        if (!$stmt) throw new Exception("Error prepare (vencido): " . $this->con->error);
        $stmt->bind_param("i", $id);
        if (!$stmt->execute()) throw new Exception("Error execute (vencido): " . $stmt->error);
        $stmt->close();
    }

    //  Comentarios (lectura) 

    public function obtenerComentarios(int $id): array
    {
        $stmt = $this->con->prepare("
            SELECT
                sc.id_comentario,
                sc.id_usuario,
                sc.tipo,
                sc.comentario,
                sc.fecha,
                CONCAT(u.nombre,' ',u.apellido_paterno) AS autor_nombre,
                ds.nombre       AS archivo_nombre,
                ds.ruta         AS archivo_ruta,
                ds.extension    AS archivo_extension,
                ds.tipo_mime    AS archivo_mime
            FROM solicitud_comentarios sc
            JOIN usuarios u ON u.id_usuarios = sc.id_usuario
            LEFT JOIN documentos_subidos ds ON ds.id_documento = sc.id_documento_adjunto
            WHERE sc.id_solicitud = ?
            ORDER BY sc.fecha ASC
        ");
        if (!$stmt) throw new Exception("Error prepare (obtenerComentarios): " . $this->con->error);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    //  Tareas / vinculación ─

    public function obtenerDatosSolicitud(int $id_solicitud): ?array
    {
        $stmt = $this->con->prepare("
            SELECT id_estudiante AS id_usuarios, id_proyectos
            FROM solicitud_proyecto
            WHERE id_solicitud_proyecto = ?
        ");
        $stmt->bind_param("i", $id_solicitud);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    public function vincularTareasAlNuevoEstudiante(int $id_proyecto, int $id_usuario): void
    {
        $sql = "
            INSERT INTO tareas_usuarios (id_tarea, id_usuarios, id_estadoT)
            SELECT t.id_tarea, ?, 1
            FROM tareas t
            INNER JOIN tbl_seguimiento s ON t.id_avances = s.id_avances
            WHERE s.id_proyectos = ?
              AND NOT EXISTS (
                  SELECT 1 FROM tareas_usuarios tu
                  WHERE tu.id_tarea = t.id_tarea AND tu.id_usuarios = ?
              )
        ";
        $stmt = $this->con->prepare($sql);
        if (!$stmt) throw new Exception("Error prepare (vincularTareas): " . $this->con->error);
        $stmt->bind_param("iii", $id_usuario, $id_proyecto, $id_usuario);
        if (!$stmt->execute()) throw new Exception("Error execute (vincularTareas): " . $stmt->error);
        $stmt->close();
    }

    /**
     * Vincula el estudiante al proyecto en proyectos_usuarios.
     * Estado de proceso inicial: 1 (en_proceso).
     */
    public function vincularEstudianteProyecto(int $id_proyecto, int $id_usuario): void
    {
        $stmt = $this->con->prepare("
            INSERT INTO proyectos_usuarios
                (id_proyectos, id_usuarios, fecha_asignacion, estado, reincorporacion, id_estados_proceso)
            VALUES (?, ?, CURDATE(), 'activo', 0, 1)
            ON DUPLICATE KEY UPDATE
                estado            = 'activo',
                fecha_baja        = NULL,
                motivo_baja       = NULL,
                reincorporacion   = reincorporacion + 1,
                id_estados_proceso = 1
        ");
        if (!$stmt) throw new Exception("Error prepare (vincularEstudianteProyecto): " . $this->con->error);
        $stmt->bind_param("ii", $id_proyecto, $id_usuario);
        if (!$stmt->execute()) throw new Exception("Error execute: " . $stmt->error);
        $stmt->close();
    }

    public function registrarHistorialUsuario(
        int    $id_proyecto,
        int    $id_estudiante,
        string $accion,
        string $motivo,
        int    $realizado_por
    ): void {
        $stmt = $this->con->prepare("
            INSERT INTO historial_proyectos_usuarios
                (id_proyectos, id_estudiante, accion, motivo, realizado_por)
            VALUES (?, ?, ?, ?, ?)
        ");
        if (!$stmt) throw new Exception("Error prepare (registrarHistorial): " . $this->con->error);
        $stmt->bind_param("iissi", $id_proyecto, $id_estudiante, $accion, $motivo, $realizado_por);
        if (!$stmt->execute()) throw new Exception("Error execute: " . $stmt->error);
        $stmt->close();
    }

    //  Cierre (etapa 3) ─

    public function actualizarEstadoCierre(
        int    $id_seguimiento,
        string $estado,
        int    $id_usuario,
        string $comentario = ''
    ): bool {
        $stmt = $this->con->prepare("
            UPDATE seguimiento_documento
            SET estado                = ?,
                fecha_revision        = NOW(),
                comentario_supervisor = ?,
                revisado_por          = ?
            WHERE id_seguimiento = ?
        ");
        if (!$stmt) throw new Exception("Error prepare (actualizarEstadoCierre): " . $this->con->error);
        $stmt->bind_param("ssii", $estado, $comentario, $id_usuario, $id_seguimiento);
        if (!$stmt->execute()) throw new Exception("Error execute: " . $stmt->error);
        $ok = $stmt->affected_rows > 0;
        $stmt->close();
        return $ok;
    }

    //  Seguimiento etapas ─

    public function getDatosSeguimientoEstudiante(int $id_proyecto, int $id_estudiante, int $id_investigador): array
    {
        // Etapa 1: estado de la solicitud
        $stmt1 = $this->con->prepare("
            SELECT estado FROM solicitud_proyecto
            WHERE id_proyectos = ? AND id_estudiante = ?
            ORDER BY id_solicitud_proyecto DESC LIMIT 1
        ");
        $stmt1->bind_param("ii", $id_proyecto, $id_estudiante);
        $stmt1->execute();
        $sol       = $stmt1->get_result()->fetch_assoc();
        $e1_estado = $sol['estado'] ?? 'pendiente';

        // Etapa 2: actividades aprobadas
        $stmt2 = $this->con->prepare("
            SELECT
                COUNT(*)               AS total,
                SUM(tu.id_estadoT = 5) AS aprobadas
            FROM tareas_usuarios tu
            JOIN tareas t           ON t.id_tarea    = tu.id_tarea
            JOIN tbl_seguimiento ts ON ts.id_avances = t.id_avances
            WHERE ts.id_proyectos = ? AND tu.id_usuarios = ?
        ");
        $stmt2->bind_param("ii", $id_proyecto, $id_estudiante);
        $stmt2->execute();
        $act           = $stmt2->get_result()->fetch_assoc();
        $total_act     = (int)($act['total']     ?? 0);
        $aprobadas_act = (int)($act['aprobadas'] ?? 0);
        $fase2_ok      = $total_act > 0 && $aprobadas_act >= 11;
        $e2_estado     = $fase2_ok ? 'completado' : ($aprobadas_act > 0 ? 'proceso' : 'pendiente');

        // Etapa 3: seguimiento Reporte Final
        $stmt3 = $this->con->prepare("
            SELECT sd.id_seguimiento, sd.estado
            FROM seguimiento_documento sd
            WHERE sd.id_proyectos = ? AND sd.id_usuarios = ? AND sd.id_tipo_documento = 3
            ORDER BY sd.id_seguimiento DESC LIMIT 1
        ");
        $stmt3->bind_param("ii", $id_proyecto, $id_estudiante);
        $stmt3->execute();
        $cierre        = $stmt3->get_result()->fetch_assoc();
        $e3_estado     = $cierre['estado']        ?? 'pendiente';
        $id_seg_cierre = $cierre['id_seguimiento'] ?? null;

        // Documentos del estudiante
        $stmt4 = $this->con->prepare("
            SELECT
                ds.id_documento,
                ds.nombre,
                ds.ruta,
                ds.extension,
                ds.fecha_subida,
                td.nombre AS tipo_nombre
            FROM documentos_subidos ds
            LEFT JOIN seguimiento_documento seg ON seg.id_seguimiento  = ds.id_seguimiento
            LEFT JOIN tipo_documento        td  ON td.id_tipo_documento = seg.id_tipo_documento
            WHERE ds.id_usuarios  = ?
              AND ds.id_proyectos = ?
              AND ds.activo       = 1
              AND ds.tipo         = 'etapa'
            ORDER BY ds.fecha_subida DESC
        ");
        $stmt4->bind_param("ii", $id_estudiante, $id_proyecto);
        $stmt4->execute();
        $documentos = $stmt4->get_result()->fetch_all(MYSQLI_ASSOC);

        return [
            'e1_estado'             => $e1_estado,
            'e2_estado'             => $e2_estado,
            'e3_estado'             => $e3_estado,
            'fase2_ok'              => $fase2_ok,
            'id_seguimiento_cierre' => $id_seg_cierre,
            'documentos'            => $documentos,
            'actividades_total'     => $total_act,
            'actividades_aprobadas' => $aprobadas_act,
        ];
    }

    //  Enviar correcciones (respuesta del estudiante) 

    public function enviarCorrecciones(int $id, int $id_usuario, string $comentario, ?int $id_documento = null): bool
    {
        // Cambiar estado de solicitud a 'en_revision' para que el investigador la revise de nuevo
        $stmt = $this->con->prepare("
            UPDATE solicitud_proyecto
            SET estado = 'en_revision'
            WHERE id_solicitud_proyecto = ? AND estado = 'correcciones'
        ");
        if ($stmt) {
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $stmt->close();
        }

        return $this->insertarComentario($id, $id_usuario, 'estudiante', $comentario, $id_documento);
    }

    //  Otros 

    public function proyectosDelInvestigador(int $id): array
    {
        $stmt = $this->con->prepare("
            SELECT id_proyectos, titulo FROM proyectos WHERE id_investigador = ?
        ");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function getEtapasPorProyecto(int $id_proyecto, int $id_usuario): array
    {
        $sql = "
            SELECT
                e.*,
                COALESCE(s.estado, 'pendiente') AS estado,
                s.id_seguimiento,
                s.observaciones,
                s.comentario_supervisor,
                td.id_tipo_documento,
                pd.id_plantilla
            FROM etapas_documento e
            LEFT JOIN tipo_documento td
                   ON td.orden = e.orden AND td.estado = 1
            LEFT JOIN seguimiento_documento s
                   ON s.id_tipo_documento = td.id_tipo_documento
                  AND s.id_proyectos      = ?
                  AND s.id_usuarios       = ?
            LEFT JOIN plantillas_documentos pd
                   ON pd.id_tipo_documento = td.id_tipo_documento AND pd.activo = 1
            WHERE e.estado = 1
            ORDER BY e.orden
        ";
        $stmt = $this->con->prepare($sql);
        $stmt->bind_param("ii", $id_proyecto, $id_usuario);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    // 
    //  SECCIÓN B — ESTUDIANTE
    // 


    // 
    //  Plantilla vigente de carta compromiso (tipo_documento = 1, activo = 1)
    //  Devuelve id_plantilla, nombre, ruta, nombre_archivo, extension para
    //  generar el enlace de descarga y mostrarlo en la vista.
    // 
    public function obtenerPlantillaCartaCompromiso(): ?array
    {
        $sql = "
            SELECT
                pd.id_plantilla,
                pd.nombre          AS plantilla_nombre,
                pd.version,
                ds.ruta            AS archivo_ruta,
                ds.nombre_archivo  AS archivo_nombre,
                ds.extension,
                ds.tipo_mime
            FROM plantillas_documentos pd
            JOIN documentos_subidos ds ON ds.id_documento = pd.id_documento
            WHERE pd.id_tipo_documento = 1
              AND pd.activo            = 1
              AND ds.activo            = 1
            ORDER BY pd.version DESC
            LIMIT 1
        ";
        $stmt = $this->con->prepare($sql);
        if (!$stmt) return null;
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc() ?: null;
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

    //  Envío de solicitud ─

    /**
     * Crea la solicitud de integración y su seguimiento de carta compromiso.
     *
     * Flujo:
     *  1. Insertar fila en solicitud_proyecto
     *  2. Si se subió carta: registrar en documentos_subidos, crear seguimiento etapa 1,
     *     vincular documento → seguimiento, actualizar solicitud con id_documento (carta)
     *  3. Si NO hay carta: igual crear seguimiento en estado 'pendiente'
     *
     * Devuelve el id_solicitud_proyecto creado.
     */
    public function crearSolicitud(
        int     $id_proyecto,
        int     $id_estudiante,
        int     $id_periodo,
        ?float  $promedio,
        string  $motivacion,
        string  $experiencia,
        ?int    $semestre,
        ?int    $id_doc_cv = null
    ): int {
        $sql = "
        INSERT INTO solicitud_proyecto
            (id_proyectos, id_estudiante, id_periodos, id_documento,
             promedio, motivacion, experiencia, semestre,
             estado, fecha_envio)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pendiente', CURDATE())
    ";
        $stmt = $this->con->prepare($sql);
        if (!$stmt) throw new Exception("Error prepare (crearSolicitud): " . $this->con->error);

        // Usar 's' para promedio y semestre permite pasar NULL sin error en mysqli
        $stmt->bind_param(
            "iiiisssi",
            $id_proyecto,
            $id_estudiante,
            $id_periodo,
            $id_doc_cv,
            $promedio,
            $motivacion,
            $experiencia,
            $semestre
        );

        if (!$stmt->execute()) throw new Exception("Error execute (crearSolicitud): " . $stmt->error);
        $id_solicitud = $stmt->insert_id;
        $stmt->close();
        return $id_solicitud;
    }

    /**
     * Crea el registro en seguimiento_documento para la etapa 1 (carta compromiso).
     * Devuelve el id_seguimiento generado.
     */
    public function crearSeguimientoCartaCompromiso(
        int    $id_proyecto,
        int    $id_estudiante,
        string $estado = 'pendiente'   // 'pendiente' si no hay carta aún; 'proceso' si ya la subió
    ): int {
        // Verificar si ya existe un seguimiento para esta combinación
        $check = $this->con->prepare("
            SELECT id_seguimiento FROM seguimiento_documento
            WHERE id_proyectos = ? AND id_usuarios = ? AND id_tipo_documento = 1
            LIMIT 1
        ");
        if ($check) {
            $check->bind_param("ii", $id_proyecto, $id_estudiante);
            $check->execute();
            $row = $check->get_result()->fetch_assoc();
            $check->close();
            if ($row) return (int)$row['id_seguimiento'];
        }

        $sql = "
            INSERT INTO seguimiento_documento
                (id_proyectos, id_tipo_documento, id_usuarios, estado, fecha_inicio)
            VALUES (?, 1, ?, ?, NOW())
        ";
        $stmt = $this->con->prepare($sql);
        if (!$stmt) throw new Exception("Error prepare (crearSeguimientoCartaCompromiso): " . $this->con->error);
        $stmt->bind_param("iis", $id_proyecto, $id_estudiante, $estado);
        if (!$stmt->execute()) throw new Exception("Error execute: " . $stmt->error);
        $id = $stmt->insert_id;
        $stmt->close();
        return $id;
    }

    /**
     * Vincula un documento subido al seguimiento (actualiza id_seguimiento en documentos_subidos).
     */
    public function vincularDocumentoSeguimiento(int $id_documento, int $id_seguimiento): void
    {
        $stmt = $this->con->prepare("
            UPDATE documentos_subidos
            SET id_seguimiento = ?
            WHERE id_documento = ?
        ");
        if (!$stmt) return;
        $stmt->bind_param("ii", $id_seguimiento, $id_documento);
        $stmt->execute();
        $stmt->close();
    }

    /**
     * Actualiza el seguimiento a estado 'proceso' cuando el estudiante sube la carta.
     */
    public function marcarSeguimientoEnProceso(int $id_seguimiento): void
    {
        $stmt = $this->con->prepare("
            UPDATE seguimiento_documento
            SET estado = 'proceso', fecha_inicio = NOW()
            WHERE id_seguimiento = ? AND estado = 'pendiente'
        ");
        if (!$stmt) return;
        $stmt->bind_param("i", $id_seguimiento);
        $stmt->execute();
        $stmt->close();
    }

    /**
     * Actualiza el campo id_documento de solicitud_proyecto (carta o CV).
     * Aquí se usa para guardar el CV si aplica; la carta va ligada al seguimiento.
     */
    public function actualizarDocumentoSolicitud(int $id_solicitud, int $id_documento): void
    {
        $stmt = $this->con->prepare("
            UPDATE solicitud_proyecto SET id_documento = ? WHERE id_solicitud_proyecto = ?
        ");
        if (!$stmt) return;
        $stmt->bind_param("ii", $id_documento, $id_solicitud);
        $stmt->execute();
        $stmt->close();
    }

    //  Cancelación por el estudiante 

    /**
     * Cancela la solicitud si el estudiante aún puede hacerlo.
     * Solo se permite si el estado es pendiente, en_revision o correcciones.
     */
    public function cancelarSolicitud(int $id_solicitud, int $id_estudiante): bool
    {
        $stmt = $this->con->prepare("
            UPDATE solicitud_proyecto
            SET estado = 'cancelado', fecha_respuesta = CURDATE()
            WHERE id_solicitud_proyecto = ?
              AND id_estudiante         = ?
              AND estado IN ('pendiente','en_revision','correcciones')
        ");
        if (!$stmt) throw new Exception("Error prepare (cancelarSolicitud): " . $this->con->error);
        $stmt->bind_param("ii", $id_solicitud, $id_estudiante);
        $stmt->execute();
        $afectadas = $stmt->affected_rows;
        $stmt->close();
        return $afectadas > 0;
    }

    //  Consulta de estado para el estudiante 

    /**
     * Devuelve la solicitud más reciente del estudiante para un proyecto,
     * incluyendo información de la carta compromiso vinculada.
     */
    public function obtenerSolicitudEstudiante(int $id_proyecto, int $id_estudiante): ?array
    {
        $sql = "
            SELECT
                sp.id_solicitud_proyecto,
                sp.estado,
                sp.fecha_envio,
                sp.fecha_respuesta,
                sp.motivo_rechazo,
                sp.comentarios,
                p.titulo AS proyecto_titulo,
                -- Carta compromiso (seguimiento etapa 1)
                sd.id_seguimiento         AS seg_id,
                sd.estado                 AS seg_estado,
                sd.comentario_supervisor  AS seg_comentario,
                ds_carta.id_documento     AS carta_id,
                ds_carta.nombre           AS carta_nombre,
                ds_carta.ruta             AS carta_ruta,
                ds_carta.extension        AS carta_extension,
                ds_carta.fecha_subida     AS carta_fecha_subida,
                -- Plantilla vigente
                pd.id_plantilla,
                pd.nombre                 AS plantilla_nombre
            FROM solicitud_proyecto sp
            JOIN proyectos p ON p.id_proyectos = sp.id_proyectos
            LEFT JOIN seguimiento_documento sd
                   ON sd.id_proyectos      = sp.id_proyectos
                  AND sd.id_usuarios       = sp.id_estudiante
                  AND sd.id_tipo_documento = 1
            LEFT JOIN documentos_subidos ds_carta
                   ON ds_carta.id_seguimiento = sd.id_seguimiento
                  AND ds_carta.activo         = 1
                  AND ds_carta.tipo           = 'etapa'
            LEFT JOIN plantillas_documentos pd
                   ON pd.id_tipo_documento = 1
                  AND pd.activo            = 1
            WHERE sp.id_proyectos  = ?
              AND sp.id_estudiante = ?
            ORDER BY sp.id_solicitud_proyecto DESC
            LIMIT 1
        ";
        $stmt = $this->con->prepare($sql);
        if (!$stmt) throw new Exception("Error prepare (obtenerSolicitudEstudiante): " . $this->con->error);
        $stmt->bind_param("ii", $id_proyecto, $id_estudiante);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc() ?: null;
    }

    /**
     * Verifica si el periodo activo del proyecto tiene la ventana de solicitud abierta.
     * Devuelve el id_periodos o null si no hay ventana activa.
     */
    public function obtenerPeriodoActivoParaProyecto(int $id_proyecto): ?array
    {
        $hoy = date('Y-m-d');
        $sql = "
            SELECT per.id_periodos, per.periodo
            FROM periodos per
            JOIN proyectos p ON p.id_periodos = per.id_periodos
            WHERE p.id_proyectos = ?
              AND per.estado = 1
              AND (per.fecha_inicio_solicitud IS NULL OR per.fecha_inicio_solicitud <= ?)
              AND (per.fecha_fin_solicitud    IS NULL OR per.fecha_fin_solicitud    >= ?)
            LIMIT 1
        ";
        $stmt = $this->con->prepare($sql);
        if (!$stmt) return null;
        $stmt->bind_param("iss", $id_proyecto, $hoy, $hoy);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc() ?: null;
    }

    /*solicitud_proyecto.php */
    // 
    //  Proyecto: título e investigador para mostrar en el formulario
    // 
    public function obtenerProyecto(int $id_proyecto): ?array
    {
        $sql = "
            SELECT
                p.id_proyectos,
                p.titulo,
                p.descripcion,
                p.modalidad,
                CONCAT(u.nombre, ' ', u.apellido_paterno) AS investigador
            FROM proyectos p
            LEFT JOIN usuarios u ON u.id_usuarios = p.id_investigador
            WHERE p.id_proyectos = ?
            LIMIT 1
        ";
        $stmt = $this->con->prepare($sql);
        if (!$stmt) return null;
        $stmt->bind_param("i", $id_proyecto);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc() ?: null;
    }

    // 
    //  Estudiante: datos personales + carrera actual
    //  FK real: estudiantes.id_usuarios (no id_usuario)
    // 
    public function obtenerEstudiante(int $id_usuario): ?array
    {
        $sql = "
            SELECT
                u.id_usuarios,
                u.nombre,
                u.apellido_paterno,
                u.apellido_materno,
                u.correo_institucional,
                e.matricula,
                e.id_carrera,
                c.nombre_carrera
            FROM usuarios u
            LEFT JOIN estudiantes e ON e.id_usuarios = u.id_usuarios
            LEFT JOIN carreras c    ON c.id_carrera  = e.id_carrera
            WHERE u.id_usuarios = ?
            LIMIT 1
        ";
        $stmt = $this->con->prepare($sql);
        if (!$stmt) return null;
        $stmt->bind_param("i", $id_usuario);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc() ?: null;
    }

    // 
    //  Catálogo de carreras para el <select>
    // 
    public function obtenerCarreras(): array
    {
        $sql  = "SELECT id_carrera, nombre_carrera FROM carreras ORDER BY nombre_carrera ASC";
        $stmt = $this->con->prepare($sql);
        if (!$stmt) return [];
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }




    //  Título del proyecto (para notificaciones) 
    public function obtenerTituloProyecto(int $id_proyecto): string
    {
        $stmt = $this->con->prepare("SELECT titulo FROM proyectos WHERE id_proyectos = ? LIMIT 1");
        if (!$stmt) return '';
        $stmt->bind_param("i", $id_proyecto);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row['titulo'] ?? '';
    }

    //  Supervisores activos (rol = 4 en usuarios_roles) ─
    public function obtenerSupervisoresActivos(): array
    {
        $sql = "
        SELECT u.id_usuarios
        FROM usuarios u
        INNER JOIN usuarios_roles r ON r.id_usuario = u.id_usuarios
        WHERE r.id_rol = 4
          AND u.estado_usuario = 'activo'
    ";
        $stmt = $this->con->prepare($sql);
        if (!$stmt) return [];
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    //  Insertar notificación ─
    public function insertarNotificacion(
        int    $id_usuario,
        string $titulo,
        string $contenido,
        string $enlace = ''
    ): void {
        $sql = "
        INSERT INTO notificaciones (usuario_id, titulo, contenido, enlace, leido, creado_en)
        VALUES (?, ?, ?, ?, 0, NOW())
    ";
        $stmt = $this->con->prepare($sql);
        if (!$stmt) return;   // notificaciones no son críticas; no lanzar excepción
        $stmt->bind_param("isss", $id_usuario, $titulo, $contenido, $enlace);
        $stmt->execute();
        $stmt->close();
    }

    public function periodoactualSolicitud(): ?array
    {
        $sql = "SELECT fecha_inicio_solicitud, fecha_fin_solicitud
             FROM periodos ORDER BY id_periodos DESC LIMIT 1";
        $stmt = $this->con->prepare($sql);
        $stmt->execute();
        $stmt->close();
        return $stmt->get_result()->fetch_assoc();
    }
}
