<?php
require_once __DIR__ . '/../publico/config/conexion.php';

class Solicitud
{
    private mysqli $con;

    public function __construct(mysqli $conn)
    {
        $this->con = $conn;
    }

    // ── Archivo centralizado ──────────────────────────────────────

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
        int     $id_proyecto  = 0,
        ?int    $etapa        = null,
        int     $version      = 1
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
            $nombre, $nombre_archivo, $ruta, $tipo_mime, $extension,
            $tamano_bytes, $tipo, $visibilidad,
            $id_usuario, $id_proyecto, $etapa, $version
        );

        if (!$stmt->execute()) throw new Exception("Error execute (registrarDocumento): " . $stmt->error);

        $id = $stmt->insert_id;
        $stmt->close();
        return $id;
    }

    // ── Comentarios internos ──────────────────────────────────────

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

    // ── Periodos disponibles ──────────────────────────────────────

    /**
     * Devuelve los periodos que tienen proyectos del investigador dado,
     * incluyendo la bandera "estado" del periodo para mostrarlo en el selector.
     */
    public function periodosDelInvestigador(int $id): array
    {
        $sql = "
            SELECT DISTINCT
                pe.id_periodos,
                pe.periodo,
                pe.estado
            FROM solicitud_proyecto sp
            JOIN proyectos p  ON p.id_proyectos = sp.id_proyectos
            JOIN periodos pe  ON pe.id_periodos  = p.id_periodos
            WHERE p.id_investigador = ?
              AND pe.periodo IS NOT NULL
              AND pe.id_periodos <> ''
            ORDER BY pe.periodo DESC
        ";
        $stmt = $this->con->prepare($sql);
        if (!$stmt) throw new Exception("Error prepare (periodosDelInvestigador): " . $this->con->error);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    // ── Resumen / listado ─────────────────────────────────────────

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

        // Filtro global por periodo
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

    // ── Detalle ───────────────────────────────────────────────────

    /**
     * Detalle completo de la solicitud incluyendo carta compromiso
     * (id_documento en solicitud_proyecto → documentos_subidos).
     */
    public function obtenerDetalle(int $id): ?array
    {
        $sql = "
            SELECT
                sp.*,
                CONCAT(u.nombre,' ',u.apellido_paterno,' ',u.apellido_materno) AS estudiante_nombre,
                u.correo_institucional,
                e.matricula,
                c.nombre_carrera       AS carrera,
                p.titulo               AS proyecto_titulo,
                p.modalidad,
                p.id_investigador,

                -- Carta compromiso adjunta por el estudiante al enviar la solicitud
                ds.nombre              AS carta_nombre,
                ds.ruta                AS carta_ruta,
                ds.extension           AS carta_extension

            FROM solicitud_proyecto sp
            JOIN proyectos p    ON p.id_proyectos   = sp.id_proyectos
            JOIN usuarios u     ON u.id_usuarios    = sp.id_estudiante
            JOIN estudiantes e  ON e.id_usuarios    = sp.id_estudiante
            JOIN carreras c     ON c.id_carrera     = e.id_carrera
            LEFT JOIN documentos_subidos ds ON ds.id_documento = sp.id_documento

            WHERE sp.id_solicitud_proyecto = ?
        ";
        $stmt = $this->con->prepare($sql);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    // ── Permiso ───────────────────────────────────────────────────

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

    // ── Cambios de estado ─────────────────────────────────────────

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
     * Acepta la solicitud, inserta en proyectos_usuarios y registra en historial.
     */
    public function aceptar(int $id): bool
    {
        $stmt = $this->con->prepare("
            UPDATE solicitud_proyecto
            SET estado = 'aceptado', fecha_respuesta = CURDATE()
            WHERE id_solicitud_proyecto = ?
        ");
        $stmt->bind_param("i", $id);
        return $stmt->execute();
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

    public function enviarCorrecciones(int $id, int $id_usuario, string $comentario, ?int $id_documento = null): bool
    {
        return $this->insertarComentario($id, $id_usuario, 'estudiante', $comentario, $id_documento);
    }

    // ── Comentarios (lectura) ─────────────────────────────────────

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

    // ── Tareas / vinculación ──────────────────────────────────────

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
     * Inserta en proyectos_usuarios. Si ya existe (UNIQUE key), actualiza estado a activo.
     */
    public function vincularEstudianteProyecto(int $id_proyecto, int $id_usuario): void
    {
        $stmt = $this->con->prepare("
            INSERT INTO proyectos_usuarios
                (id_proyectos, id_usuarios, fecha_asignacion, estado, reincorporacion)
            VALUES (?, ?, CURDATE(), 'activo', 0)
            ON DUPLICATE KEY UPDATE
                estado          = 'activo',
                fecha_baja      = NULL,
                motivo_baja     = NULL,
                reincorporacion = reincorporacion + 1
        ");
        if (!$stmt) throw new Exception("Error prepare (vincularEstudianteProyecto): " . $this->con->error);
        $stmt->bind_param("ii", $id_proyecto, $id_usuario);
        if (!$stmt->execute()) throw new Exception("Error execute: " . $stmt->error);
        $stmt->close();
    }

    /**
     * Registra en historial_proyectos_usuarios.
     */
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

    // ── Seguimiento etapas (para detalles_solicitud) ──────────────

    /**
     * Devuelve el estado de las 3 etapas del estudiante en el proyecto,
     * el id del seguimiento de cierre, los documentos subidos y si la fase 2 está completa.
     */
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
        $sol = $stmt1->get_result()->fetch_assoc();
        $e1_estado = $sol['estado'] ?? 'pendiente';

        // Etapa 2: actividades aprobadas (id_estadoT = 5)
        $stmt2 = $this->con->prepare("
            SELECT
                COUNT(*)                    AS total,
                SUM(tu.id_estadoT = 5)      AS aprobadas
            FROM tareas_usuarios tu
            JOIN tareas t           ON t.id_tarea    = tu.id_tarea
            JOIN tbl_seguimiento ts ON ts.id_avances = t.id_avances
            WHERE ts.id_proyectos = ? AND tu.id_usuarios = ?
        ");
        $stmt2->bind_param("ii", $id_proyecto, $id_estudiante);
        $stmt2->execute();
        $act = $stmt2->get_result()->fetch_assoc();
        $total_act     = (int)($act['total']     ?? 0);
        $aprobadas_act = (int)($act['aprobadas'] ?? 0);
        $fase2_ok      = $total_act > 0 && $aprobadas_act >= 11;
        $e2_estado     = $fase2_ok ? 'completado' : ($aprobadas_act > 0 ? 'proceso' : 'pendiente');

        // Etapa 3: seguimiento del Reporte Final (tipo_documento id=3)
        $stmt3 = $this->con->prepare("
            SELECT sd.id_seguimiento, sd.estado
            FROM seguimiento_documento sd
            WHERE sd.id_proyectos = ? AND sd.id_usuarios = ? AND sd.id_tipo_documento = 3
            ORDER BY sd.id_seguimiento DESC LIMIT 1
        ");
        $stmt3->bind_param("ii", $id_proyecto, $id_estudiante);
        $stmt3->execute();
        $cierre = $stmt3->get_result()->fetch_assoc();
        $e3_estado         = $cierre['estado'] ?? 'pendiente';
        $id_seg_cierre     = $cierre['id_seguimiento'] ?? null;

        // Documentos subidos por el estudiante en este proyecto
        $stmt4 = $this->con->prepare("
            SELECT
                ds.id_documento,
                ds.nombre,
                ds.ruta,
                ds.extension,
                ds.fecha_subida,
                td.nombre AS tipo_nombre
            FROM documentos_subidos ds
            LEFT JOIN seguimiento_documento seg ON seg.id_seguimiento = ds.id_seguimiento
            LEFT JOIN tipo_documento td         ON td.id_tipo_documento = seg.id_tipo_documento
            WHERE ds.id_usuario  = ?
              AND ds.id_proyecto = ?
              AND ds.activo      = 1
              AND ds.tipo        = 'entrega'
            ORDER BY ds.fecha_subida DESC
        ");
        $stmt4->bind_param("ii", $id_estudiante, $id_proyecto);
        $stmt4->execute();
        $documentos = $stmt4->get_result()->fetch_all(MYSQLI_ASSOC);

        return [
            'e1_estado'           => $e1_estado,
            'e2_estado'           => $e2_estado,
            'e3_estado'           => $e3_estado,
            'fase2_ok'            => $fase2_ok,
            'id_seguimiento_cierre' => $id_seg_cierre,
            'documentos'          => $documentos,
            'actividades_total'   => $total_act,
            'actividades_aprobadas' => $aprobadas_act,
        ];
    }

    // ── Otros ─────────────────────────────────────────────────────

    public function proyectosDelInvestigador(int $id): array
    {
        $stmt = $this->con->prepare("SELECT id_proyectos, titulo FROM proyectos WHERE id_investigador = ?");
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
}