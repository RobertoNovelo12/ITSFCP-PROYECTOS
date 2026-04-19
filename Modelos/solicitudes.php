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

    /**
     * Inserta un archivo en documentos_subidos y devuelve el id_documento.
     * Compartido con plantillas, tareas y solicitudes.
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

    // ── Comentarios ───────────────────────────────────────────────

    /**
     * Inserta un comentario en solicitud_comentarios.
     * Base común para investigador y estudiante.
     */
    private function insertarComentario(
        int     $id_solicitud,
        int     $id_usuario,
        string  $tipo,           // 'investigador' | 'estudiante'
        string  $comentario,
        ?int    $id_documento = null
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

    // ── Resumen / listado ─────────────────────────────────────────

    public function resumen(int $id): array
    {
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
        ";

        $stmt = $this->con->prepare($sql);
        $stmt->bind_param("i", $id);
        $stmt->execute();

        return $stmt->get_result()->fetch_assoc() ?? [];
    }

    public function contarSolicitudes(int $id, array $f): int
    {
        [$where, $params, $types] = $this->construirWhere($id, $f);

        $sql = "
            SELECT COUNT(*)
            FROM solicitud_proyecto sp
            JOIN proyectos p  ON p.id_proyectos  = sp.id_proyectos
            JOIN usuarios u   ON u.id_usuarios   = sp.id_estudiante
            JOIN estudiantes e ON e.id_usuarios  = sp.id_estudiante
            JOIN carreras c   ON c.id_carrera    = e.id_carrera
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
            JOIN proyectos p  ON p.id_proyectos  = sp.id_proyectos
            JOIN usuarios u   ON u.id_usuarios   = sp.id_estudiante
            JOIN estudiantes e ON e.id_usuarios  = sp.id_estudiante
            JOIN carreras c   ON c.id_carrera    = e.id_carrera
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

        return ["WHERE " . implode(" AND ", $cond), $params, $types];
    }

    // ── Detalle / permiso ─────────────────────────────────────────

    public function obtenerDetalle(int $id): ?array
    {
        $sql = "
            SELECT
                sp.*,
                CONCAT(u.nombre,' ',u.apellido_paterno,' ',u.apellido_materno) estudiante_nombre,
                u.correo_institucional,
                e.matricula,
                c.nombre_carrera carrera,
                p.titulo proyecto_titulo,
                p.modalidad
            FROM solicitud_proyecto sp
            JOIN proyectos p  ON p.id_proyectos  = sp.id_proyectos
            JOIN usuarios u   ON u.id_usuarios   = sp.id_estudiante
            JOIN estudiantes e ON e.id_usuarios  = sp.id_estudiante
            JOIN carreras c   ON c.id_carrera    = e.id_carrera
            WHERE sp.id_solicitud_proyecto = ?
        ";

        $stmt = $this->con->prepare($sql);
        $stmt->bind_param("i", $id);
        $stmt->execute();

        return $stmt->get_result()->fetch_assoc();
    }

    public function verificarPermiso(int $id, int $inv): bool
    {
        $sql = "
            SELECT COUNT(*)
            FROM solicitud_proyecto sp
            JOIN proyectos p ON p.id_proyectos = sp.id_proyectos
            WHERE sp.id_solicitud_proyecto = ?
              AND p.id_investigador = ?
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
            UPDATE solicitud_proyecto
            SET estado = 'correcciones'
            WHERE id_solicitud_proyecto = ?
        ");
        if (!$stmt) throw new Exception("Error prepare (pedirCorrecciones): " . $this->con->error);
        $stmt->bind_param("i", $id);
        if (!$stmt->execute()) throw new Exception("Error execute (pedirCorrecciones): " . $stmt->error);
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
        if (!$stmt->execute()) throw new Exception("Error execute (rechazar): " . $stmt->error);
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
                ds.nombre       AS archivo_nombre,
                ds.ruta         AS archivo_ruta,
                ds.extension    AS archivo_extension,
                ds.tipo_mime    AS archivo_mime
            FROM solicitud_comentarios sc
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
        $sql = "
            SELECT id_estudiante AS id_usuarios, id_proyectos
            FROM solicitud_proyecto
            WHERE id_solicitud_proyecto = ?
        ";

        $stmt = $this->con->prepare($sql);
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
                  WHERE tu.id_tarea   = t.id_tarea
                    AND tu.id_usuarios = ?
              )
        ";

        $stmt = $this->con->prepare($sql);
        if (!$stmt) {
            error_log("MySQL prepare failed: " . $this->con->error);
            throw new Exception("Error al preparar consulta.");
        }

        $stmt->bind_param("iii", $id_usuario, $id_proyecto, $id_usuario);

        if (!$stmt->execute()) {
            error_log("MySQL execute error: " . $stmt->error);
            throw new Exception("Error al vincular tareas.");
        }

        $stmt->close();
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
                COALESCE(s.estado, 'pendiente')  AS estado,
                s.id_seguimiento,
                s.observaciones,
                s.comentario_supervisor,
                td.id_tipo_documento,
                pd.id_plantilla
            FROM etapas_documento e
            LEFT JOIN tipo_documento td
                   ON td.orden  = e.orden AND td.estado = 1
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