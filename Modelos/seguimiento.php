<?php

/**
 * seguimiento.php  — Modelo de seguimiento de documentación
 *
 *
 * Tablas principales:
 *   seguimiento_documento, documentos_subidos, proyectos_usuarios,
 *   historial_proyectos_usuarios, solicitud_proyecto,
 *   tareas_usuarios, tareas, tbl_seguimiento,
 *   etapas_documento, tipo_documento, plantillas_documentos
 */

class SeguimientoModelo
{
    private mysqli $con;

    public function __construct(mysqli $conn)
    {
        $this->con = $conn;
    }

    // ══════════════════════════════════════════════════════════════
    // PROYECTOS
    // ══════════════════════════════════════════════════════════════

    /**
     * Proyecto visible para un estudiante (debe estar en proyectos_usuarios).
     * Incluye estado de la solicitud de integración para saber si Etapa 1 está aprobada.
     */
    public function getProyectoPorId(int $id_usuario, int $id_proyecto): ?array
    {
        $sql = "SELECT p.*,
                       ep.nombre AS estado_nombre,
                       sp.estado AS estado_integracion,
                       sp.id_solicitud_proyecto AS id_solicitud
                FROM proyectos p
                JOIN proyectos_usuarios pu ON pu.id_proyectos = p.id_proyectos
                JOIN estados_proyectos  ep ON ep.id_estadoP   = p.id_estadoP
                LEFT JOIN solicitud_proyecto sp
                       ON sp.id_proyectos  = p.id_proyectos
                      AND sp.id_estudiante = ?
                WHERE pu.id_usuarios = ?
                  AND p.id_proyectos = ?
                  AND pu.estado IN ('activo','concluido')
                LIMIT 1";

        $stmt = $this->con->prepare($sql);
        $stmt->bind_param("iii", $id_usuario, $id_usuario, $id_proyecto);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }

    // ══════════════════════════════════════════════════════════════
    // ETAPAS — Vista estudiante
    // ══════════════════════════════════════════════════════════════

    /**
     * Etapas de seguimiento del estudiante en un proyecto.
     * Une etapas_documento → tipo_documento → seguimiento_documento → plantillas_documentos.
     */
    public function getEtapasPorProyecto(int $id_proyecto, int $id_usuario): array
    {
        $sql = "SELECT
                    e.*,
                    COALESCE(s.estado, 'pendiente') AS estado,
                    s.id_seguimiento,
                    s.observaciones,
                    s.comentario_supervisor,
                    td.id_tipo_documento,
                    pd.id_plantilla,
                    e.plantilla_descargable          AS plantilla
                FROM etapas_documento e
                LEFT JOIN tipo_documento td
                       ON td.orden = e.orden AND td.estado = 1
                LEFT JOIN seguimiento_documento s
                       ON s.id_tipo_documento = td.id_tipo_documento
                      AND s.id_proyectos = ?
                      AND s.id_usuarios  = ?
                LEFT JOIN plantillas_documentos pd
                       ON pd.id_tipo_documento = td.id_tipo_documento
                      AND pd.activo = 1
                WHERE e.estado = 1
                ORDER BY e.orden ASC";

        $stmt = $this->con->prepare($sql);
        $stmt->bind_param("ii", $id_proyecto, $id_usuario);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    // ══════════════════════════════════════════════════════════════
    // SOLICITUDES DE INTEGRACIÓN
    // ══════════════════════════════════════════════════════════════

    /**
     * Solicitud más reciente de un estudiante en un proyecto.
     */
    public function getSolicitudPorEstudianteProyecto(int $id_estudiante, int $id_proyecto): ?array
    {
        $sql = "SELECT id_solicitud_proyecto, estado, fecha_envio, comentarios
                FROM solicitud_proyecto
                WHERE id_estudiante = ?
                  AND id_proyectos  = ?
                ORDER BY id_solicitud_proyecto DESC
                LIMIT 1";

        $stmt = $this->con->prepare($sql);
        $stmt->bind_param("ii", $id_estudiante, $id_proyecto);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }

    /**
     * Verificar que una solicitud de integración pertenezca a un proyecto del investigador.
     */
    public function verificarSolicitudDelInvestigador(int $id_solicitud, int $id_investigador): bool
    {
        $sql = "SELECT COUNT(*) AS total
                FROM solicitud_proyecto sp
                JOIN proyectos p ON p.id_proyectos = sp.id_proyectos
                WHERE sp.id_solicitud_proyecto = ?
                  AND p.id_investigador = ?";

        $stmt = $this->con->prepare($sql);
        $stmt->bind_param("ii", $id_solicitud, $id_investigador);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return (int)$row['total'] > 0;
    }

    // ══════════════════════════════════════════════════════════════
    // SEGUIMIENTO CIERRE — Etapa 3
    // ══════════════════════════════════════════════════════════════

    /**
     * Registro de seguimiento_documento correspondiente al Reporte Final
     * (tipo_documento con categoria='final' y nombre='Reporte Final', orden=2).
     */
    public function getSegimientoCierre(int $id_proyecto, int $id_estudiante): ?array
    {
        $sql = "SELECT s.*
                FROM seguimiento_documento s
                JOIN tipo_documento td ON td.id_tipo_documento = s.id_tipo_documento
                WHERE s.id_proyectos = ?
                  AND s.id_usuarios  = ?
                  AND td.categoria   = 'final'
                  AND td.nombre      = 'Reporte Final'
                ORDER BY s.id_seguimiento DESC
                LIMIT 1";

        $stmt = $this->con->prepare($sql);
        $stmt->bind_param("ii", $id_proyecto, $id_estudiante);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }

    /**
     * Obtener un seguimiento_documento por ID.
     */
    public function getSegimientoPorId(int $id_seguimiento): ?array
    {
        $sql  = "SELECT * FROM seguimiento_documento WHERE id_seguimiento = ? LIMIT 1";
        $stmt = $this->con->prepare($sql);
        $stmt->bind_param("i", $id_seguimiento);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }

    // ══════════════════════════════════════════════════════════════
    // SEGUIMIENTO_DOCUMENTO — operaciones
    // ══════════════════════════════════════════════════════════════

    /**
     * Crea un registro nuevo en seguimiento_documento con estado 'proceso'.
     */
    public function crearSeguimiento(int $id_proyecto, int $id_tipo_documento, int $id_usuario): int
    {
        $sql  = "INSERT INTO seguimiento_documento
                 (id_proyectos, id_tipo_documento, id_usuarios, estado, fecha_inicio)
                 VALUES (?, ?, ?, 'proceso', NOW())";
        $stmt = $this->con->prepare($sql);
        $stmt->bind_param("iii", $id_proyecto, $id_tipo_documento, $id_usuario);
        $stmt->execute();
        $id = $this->con->insert_id;
        $stmt->close();
        return $id;
    }

    /**
     * Actualiza el estado de un seguimiento (por el estudiante).
     */
    public function actualizarEstadoEstudiante(int $id, string $estado): bool
    {
        $stmt = $this->con->prepare(
            "UPDATE seguimiento_documento SET estado = ? WHERE id_seguimiento = ?"
        );
        $stmt->bind_param("si", $estado, $id);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    /**
     * Actualiza estado, comentario supervisor y fecha de revisión (por el investigador).
     * Acepta: completado | rechazado | correcciones | proceso
     */
    public function actualizarEstadoSeguimiento(
        int    $id_seg,
        string $estado,
        string $comentario,
        int    $id_rev
    ): bool {
        $sql  = "UPDATE seguimiento_documento
                 SET estado               = ?,
                     comentario_supervisor= ?,
                     revisado_por         = ?,
                     fecha_revision       = NOW()
                 WHERE id_seguimiento     = ?";
        $stmt = $this->con->prepare($sql);
        $stmt->bind_param("ssii", $estado, $comentario, $id_rev, $id_seg);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    /**
     * Verificar que un seguimiento_documento pertenezca a un proyecto del investigador.
     */
    public function verificarPermisoInvestigador(int $id_seg, int $id_inv): bool
    {
        $sql  = "SELECT COUNT(*) AS total
                 FROM seguimiento_documento s
                 JOIN proyectos p ON p.id_proyectos = s.id_proyectos
                 WHERE s.id_seguimiento  = ?
                   AND p.id_investigador = ?";
        $stmt = $this->con->prepare($sql);
        $stmt->bind_param("ii", $id_seg, $id_inv);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return (int)$row['total'] > 0;
    }

    // ══════════════════════════════════════════════════════════════
    // DOCUMENTOS_SUBIDOS — tabla centralizada
    // ══════════════════════════════════════════════════════════════

    /**
     * Registra un documento en la tabla centralizada documentos_subidos.
     *
     * Tipo siempre 'etapa' para entregas del estudiante en el flujo de seguimiento.
     * Ruta esperada: storage/etapas/proyecto_{id}/{nombre_archivo}
     */
    public function registrarDocumentoCentralizado(
        int     $id_seguimiento,
        ?int    $id_plantilla,
        string  $nombre,
        string  $nombre_archivo,
        string  $ruta,
        string  $tipo_mime,
        string  $extension,
        int     $tamano_bytes,
        int     $id_usuario,
        int     $id_proyecto,
        ?int    $etapa
    ): bool {
        $sql = "INSERT INTO documentos_subidos
                    (nombre, nombre_archivo, ruta, tipo_mime, extension, tamano_bytes,
                     tipo, visibilidad, id_usuario, id_proyecto, etapa,
                     version, activo, id_seguimiento, id_plantilla)
                VALUES (?, ?, ?, ?, ?, ?, 'etapa', 'privado', ?, ?, ?, 1, 1, ?, ?)";

        $stmt = $this->con->prepare($sql);
        if (!$stmt) return false;

        // tipos: s s s s s i  i i i  i i
        //        nombre nombre_archivo ruta tipo_mime extension tamano_bytes
        //        id_usuario id_proyecto etapa id_seguimiento id_plantilla
        $stmt->bind_param(
            "sssssiiiiii",
            $nombre,
            $nombre_archivo,
            $ruta,
            $tipo_mime,
            $extension,
            $tamano_bytes,
            $id_usuario,
            $id_proyecto,
            $etapa,
            $id_seguimiento,
            $id_plantilla
        );

        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    /**
     * Documentos activos de tipo 'etapa' subidos por el estudiante en un proyecto.
     * Incluye el nombre del tipo de documento para mostrar en la vista.
     */
    public function getDocumentosEtapaEstudiante(int $id_proyecto, int $id_usuario): array
    {
        $sql = "SELECT ds.id_documento,
                       ds.nombre,
                       ds.nombre_archivo,
                       ds.ruta,
                       ds.extension,
                       ds.etapa,
                       ds.fecha_subida,
                       td.nombre AS tipo_nombre
                FROM documentos_subidos ds
                LEFT JOIN seguimiento_documento seg
                       ON seg.id_seguimiento = ds.id_seguimiento
                LEFT JOIN tipo_documento td
                       ON td.id_tipo_documento = seg.id_tipo_documento
                WHERE ds.id_proyecto = ?
                  AND ds.id_usuario  = ?
                  AND ds.tipo        = 'etapa'
                  AND ds.activo      = 1
                ORDER BY ds.etapa ASC, ds.fecha_subida DESC";

        $stmt = $this->con->prepare($sql);
        $stmt->bind_param("ii", $id_proyecto, $id_usuario);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    // ══════════════════════════════════════════════════════════════
    // TIPO_DOCUMENTO — utilidades
    // ══════════════════════════════════════════════════════════════

    /**
     * Devuelve el orden (1, 2, 3) de un tipo_documento, usado como número de etapa.
     */
    public function getOrdenTipoDocumento(int $id_tipo_documento): ?int
    {
        $sql  = "SELECT orden FROM tipo_documento WHERE id_tipo_documento = ? LIMIT 1";
        $stmt = $this->con->prepare($sql);
        $stmt->bind_param("i", $id_tipo_documento);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ? (int)$row['orden'] : null;
    }

    // ══════════════════════════════════════════════════════════════
    // TAREAS / SECCIONES — para determinar si Etapa 2 está completa
    // ══════════════════════════════════════════════════════════════

    /**
     * Cuenta tareas con id_estadoT = 5 (aprobado) del estudiante en el proyecto.
     * Se consideran ≥11 tareas aprobadas como Etapa 2 completada.
     */
    public function contarTareasAprobadas(int $id_proyecto, int $id_estudiante): int
    {
        $sql  = "SELECT COUNT(*) AS total
                 FROM tareas_usuarios tu
                 JOIN tareas t           ON t.id_tarea    = tu.id_tarea
                 JOIN tbl_seguimiento ts ON ts.id_avances = t.id_avances
                 WHERE tu.id_estadoT   = 5
                   AND ts.id_proyectos = ?
                   AND tu.id_usuarios  = ?";
        $stmt = $this->con->prepare($sql);
        $stmt->bind_param("ii", $id_proyecto, $id_estudiante);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return (int)$row['total'];
    }

    /**
     * Alias público para compatibilidad con código existente.
     */
    public function todasSeccionesAprobadas(int $id_proyecto, int $id_estudiante): int
    {
        return $this->contarTareasAprobadas($id_proyecto, $id_estudiante);
    }

    // ══════════════════════════════════════════════════════════════
    // PROYECTOS_USUARIOS + HISTORIAL
    // ══════════════════════════════════════════════════════════════

    /**
     * Verifica que un usuario (estudiante) pertenezca al proyecto.
     */
    public function verificarProyectoUsuario(int $id_proyecto, int $id_usuario): bool
    {
        $sql  = "SELECT COUNT(*) AS total
                 FROM proyectos_usuarios
                 WHERE id_proyectos = ?
                   AND id_usuarios  = ?
                   AND estado IN ('activo','concluido')";
        $stmt = $this->con->prepare($sql);
        $stmt->bind_param("ii", $id_proyecto, $id_usuario);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return (int)$row['total'] > 0;
    }

    /**
     * Marca al estudiante como 'concluido' en proyectos_usuarios
     * y registra el evento en historial_proyectos_usuarios.
     * Se llama al aprobar el Cierre (Etapa 3).
     */
    public function marcarProyectoUsuarioConcluido(
        int $id_proyecto,
        int $id_estudiante,
        int $realizado_por
    ): bool {
        $this->con->begin_transaction();
        try {
            // Actualizar proyectos_usuarios
            $stmt = $this->con->prepare(
                "UPDATE proyectos_usuarios
                 SET estado           = 'concluido',
                     fecha_terminacion = CURDATE()
                 WHERE id_proyectos = ?
                   AND id_usuarios  = ?"
            );
            $stmt->bind_param("ii", $id_proyecto, $id_estudiante);
            $stmt->execute();
            $stmt->close();

            // Insertar en historial_proyectos_usuarios
            $motivo = 'Proyecto concluido — cierre aprobado por investigador';
            $stmt2  = $this->con->prepare(
                "INSERT INTO historial_proyectos_usuarios
                     (id_proyectos, id_estudiante, accion, motivo, realizado_por)
                 VALUES (?, ?, 'concluido', ?, ?)"
            );
            $stmt2->bind_param("iisi", $id_proyecto, $id_estudiante, $motivo, $realizado_por);
            $stmt2->execute();
            $stmt2->close();

            $this->con->commit();
            return true;
        } catch (Exception $e) {
            $this->con->rollback();
            error_log('marcarProyectoUsuarioConcluido: ' . $e->getMessage());
            return false;
        }
    }
}
