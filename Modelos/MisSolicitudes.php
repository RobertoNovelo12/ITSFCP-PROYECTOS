<?php
// Modelos/misSolicitudes.php

require_once __DIR__ . '/../publico/config/conexion.php';
require_once __DIR__ . '/BaseModelo.php';

class MisSolicitudes extends BaseModelo
{
    // ─
    //  CATÁLOGO
    // ─

    public function periodosDelEstudiante(int $id_estudiante): array
    {
        return $this->ejecutar(
            "SELECT DISTINCT pe.id_periodos, pe.periodo, pe.estado
             FROM solicitud_proyecto sp
             JOIN proyectos p  ON p.id_proyectos = sp.id_proyectos
             JOIN periodos  pe ON pe.id_periodos  = p.id_periodos
             WHERE sp.id_estudiante = ? AND pe.periodo IS NOT NULL
             ORDER BY pe.periodo DESC",
            'i',
            [$id_estudiante]
        );
    }

    // ─
    //  RESUMEN
    // ─

    public function resumen(int $id_estudiante, ?int $id_periodo = null): array
    {
        $wherePeriodo = $id_periodo ? 'AND p.id_periodos = ?' : '';
        $types  = $id_periodo ? 'ii' : 'i';
        $params = $id_periodo ? [$id_estudiante, $id_periodo] : [$id_estudiante];

        return $this->ejecutar(
            "SELECT
                COUNT(*)                               AS total,
                SUM(sp.estado = 'pendiente')           AS pendientes,
                SUM(sp.estado = 'en_revision')         AS en_revision,
                SUM(sp.estado = 'correcciones')        AS correcciones,
                SUM(sp.estado = 'aceptado')            AS aceptadas,
                SUM(sp.estado = 'rechazado')           AS rechazadas
             FROM solicitud_proyecto sp
             JOIN proyectos p ON p.id_proyectos = sp.id_proyectos
             WHERE sp.id_estudiante = ? $wherePeriodo",
            $types,
            $params,
            false
        ) ?: [];
    }

    // ─
    //  LISTADO PAGINADO
    // ─

    public function contarSolicitudes(int $id_estudiante, array $filtros): int
    {
        [$where, $params, $types] = $this->_construirWhere($id_estudiante, $filtros);

        $row = $this->ejecutar(
            "SELECT COUNT(DISTINCT sp.id_solicitud_proyecto) AS total
             FROM solicitud_proyecto sp
             JOIN proyectos p ON p.id_proyectos = sp.id_proyectos
             JOIN usuarios  u ON u.id_usuarios  = p.id_investigador
             $where",
            $types,
            $params,
            false
        );

        return (int)($row['total'] ?? 0);
    }

    public function obtenerSolicitudes(int $id_estudiante, array $filtros, int $desde, int $limite): array
    {
        [$where, $params, $types] = $this->_construirWhere($id_estudiante, $filtros);

        $params[] = $desde;
        $params[] = $limite;
        $types   .= 'ii';

        return $this->ejecutar(
            "SELECT
                sp.id_solicitud_proyecto,
                sp.estado,
                sp.promedio,
                sp.semestre,
                DATE_FORMAT(sp.fecha_envio,     '%d/%m/%Y') AS fecha_envio,
                DATE_FORMAT(sp.fecha_respuesta, '%d/%m/%Y') AS fecha_respuesta,
                sp.motivo_rechazo,
                p.id_proyectos,
                p.titulo                                     AS proyecto_titulo,
                p.modalidad,
                CONCAT(u.nombre, ' ', u.apellido_paterno)    AS investigador,
                per.periodo,
                (
                    SELECT sc.comentario
                    FROM   solicitud_comentarios sc
                    WHERE  sc.id_solicitud = sp.id_solicitud_proyecto
                      AND  sc.tipo = 'investigador'
                    ORDER  BY sc.fecha DESC LIMIT 1
                ) AS ultimo_comentario_inv,
                (
                    SELECT COUNT(*)
                    FROM   solicitud_comentarios sc
                    WHERE  sc.id_solicitud = sp.id_solicitud_proyecto
                ) AS total_mensajes,
                (
                    SELECT COUNT(*)
                    FROM   solicitud_comentarios sc
                    WHERE  sc.id_solicitud = sp.id_solicitud_proyecto
                      AND  sc.tipo = 'estudiante'
                      AND  sc.fecha > COALESCE(
                            (SELECT MAX(sc2.fecha) FROM solicitud_comentarios sc2
                             WHERE sc2.id_solicitud = sp.id_solicitud_proyecto
                               AND sc2.tipo = 'investigador'),
                            '1970-01-01')
                ) AS ya_respondio
             FROM solicitud_proyecto sp
             JOIN proyectos p   ON p.id_proyectos = sp.id_proyectos
             JOIN usuarios  u   ON u.id_usuarios  = p.id_investigador
             JOIN periodos  per ON per.id_periodos = p.id_periodos
             $where
             ORDER BY
                FIELD(sp.estado, 'correcciones', 'en_revision', 'pendiente',
                                 'aceptado', 'rechazado', 'vencido', 'cancelado'),
                sp.fecha_envio DESC
             LIMIT ?, ?",
            $types,
            $params
        );
    }

    // ─
    //  DETALLE
    // ─

    public function obtenerDetalle(int $id_solicitud, int $id_estudiante): ?array
    {
        return $this->ejecutar(
            "SELECT
                sp.id_solicitud_proyecto,
                sp.estado,
                sp.motivacion,
                sp.experiencia,
                sp.promedio,
                sp.semestre,
                sp.motivo_rechazo,
                sp.comentarios                               AS comentario_general,
                DATE_FORMAT(sp.fecha_envio,     '%d/%m/%Y') AS fecha_envio,
                DATE_FORMAT(sp.fecha_respuesta, '%d/%m/%Y') AS fecha_respuesta,
                p.id_proyectos,
                p.titulo                                     AS proyecto_titulo,
                p.modalidad,
                p.descripcion                                AS proyecto_descripcion,
                CONCAT(u.nombre, ' ', u.apellido_paterno)    AS investigador,
                u.correo_institucional                       AS email_investigador,
                per.periodo,
                sd.id_seguimiento,
                sd.estado                                    AS seg_estado,
                ds_carta.nombre                              AS carta_nombre,
                ds_carta.ruta                                AS carta_ruta,
                ds_carta.extension                           AS carta_extension,
                pd.id_plantilla,
                pd.nombre                                    AS plantilla_nombre,
                (
                    SELECT COUNT(*)
                    FROM   solicitud_comentarios sc
                    WHERE  sc.id_solicitud = sp.id_solicitud_proyecto
                      AND  sc.tipo = 'estudiante'
                      AND  sc.fecha > COALESCE(
                            (SELECT MAX(sc2.fecha) FROM solicitud_comentarios sc2
                             WHERE sc2.id_solicitud = sp.id_solicitud_proyecto
                               AND sc2.tipo = 'investigador'),
                            '1970-01-01')
                ) AS ya_respondio
             FROM solicitud_proyecto sp
             JOIN proyectos p   ON p.id_proyectos = sp.id_proyectos
             JOIN usuarios  u   ON u.id_usuarios  = p.id_investigador
             JOIN periodos  per ON per.id_periodos = p.id_periodos
             LEFT JOIN seguimiento_documento sd
                    ON sd.id_proyectos      = sp.id_proyectos
                   AND sd.id_usuarios       = sp.id_estudiante
                   AND sd.id_tipo_documento = 1
             LEFT JOIN documentos_subidos ds_carta
                    ON ds_carta.id_seguimiento = sd.id_seguimiento
                   AND ds_carta.activo         = 1
                   AND ds_carta.tipo           = 'etapa'
             LEFT JOIN plantillas_documentos pd
                    ON pd.id_tipo_documento = 1 AND pd.activo = 1
             WHERE sp.id_solicitud_proyecto = ? AND sp.id_estudiante = ?
             LIMIT 1",
            'ii',
            [$id_solicitud, $id_estudiante],
            false
        ) ?: null;
    }

    // ─
    //  HILO DE COMENTARIOS
    // ─

    public function obtenerHilo(int $id_solicitud, int $id_estudiante): array
    {
        // Verificar pertenencia
        $check = $this->ejecutar(
            'SELECT id_solicitud_proyecto FROM solicitud_proyecto
             WHERE id_solicitud_proyecto = ? AND id_estudiante = ? LIMIT 1',
            'ii',
            [$id_solicitud, $id_estudiante],
            false
        );
        if (!$check) return [];

        return $this->ejecutar(
            "SELECT
                sc.id_comentario,
                sc.tipo,
                sc.comentario,
                DATE_FORMAT(sc.fecha, '%d/%m/%Y %H:%i') AS fecha,
                d.id_documento,
                d.nombre       AS doc_nombre,
                d.extension    AS doc_extension,
                d.tamano_bytes AS doc_tamano,
                d.ruta         AS doc_ruta
             FROM solicitud_comentarios sc
             LEFT JOIN documentos_subidos d ON d.id_documento = sc.id_documento_adjunto
             WHERE sc.id_solicitud = ?
             ORDER BY sc.fecha ASC",
            'i',
            [$id_solicitud]
        );
    }

    // ─
    //  VALIDACIÓN PREVIA A GUARDAR RESPUESTA
    // ─

    public function obtenerSolicitud(int $id_solicitud, int $id_estudiante): ?array
    {
        return $this->ejecutar(
            "SELECT sp.id_solicitud_proyecto, sp.estado, sp.id_proyectos,
                    p.titulo AS proyecto_titulo
             FROM   solicitud_proyecto sp
             INNER JOIN proyectos p ON p.id_proyectos = sp.id_proyectos
             WHERE  sp.id_solicitud_proyecto = ? AND sp.id_estudiante = ?
             LIMIT 1",
            'ii',
            [$id_solicitud, $id_estudiante],
            false
        ) ?: null;
    }

    // ─
    //  GUARDAR RESPUESTA (transaccional)
    // ─

    public function guardarRespuesta(
        int    $id_solicitud,
        int    $id_estudiante,
        int    $id_proyecto,
        string $comentario,
        ?array $archivo
    ): bool {
        $this->conn->begin_transaction();
        try {
            $id_documento = null;

            if ($archivo !== null) {
                $this->ejecutar(
                    "INSERT INTO documentos_subidos
                        (nombre, nombre_archivo, ruta, tipo_mime, extension,
                         tamano_bytes, tipo, visibilidad, id_usuarios, id_proyectos, version)
                     VALUES (?, ?, ?, ?, ?, ?, 'academico', 'privado', ?, ?, 1)",
                    'sssssiii',
                    [
                        $archivo['nombre_display'], $archivo['nombre_fisico'],
                        $archivo['ruta'],           $archivo['mime'],
                        $archivo['extension'],      $archivo['tamano'],
                        $id_estudiante,             $id_proyecto,
                    ]
                );
                $id_documento = (int)$this->conn->insert_id;
            }

            $this->ejecutar(
                "INSERT INTO solicitud_comentarios
                    (id_solicitud, id_usuario, tipo, comentario, id_documento_adjunto)
                 VALUES (?, ?, 'estudiante', ?, ?)",
                'iisi',
                [$id_solicitud, $id_estudiante, $comentario, $id_documento]
            );

            $this->ejecutar(
                "UPDATE solicitud_proyecto SET estado = 'en_revision'
                 WHERE  id_solicitud_proyecto = ? AND id_estudiante = ? AND estado = 'correcciones'",
                'ii',
                [$id_solicitud, $id_estudiante]
            );

            $this->conn->commit();
            return true;

        } catch (Exception $e) {
            $this->conn->rollback();
            error_log('MisSolicitudes::guardarRespuesta() — ' . $e->getMessage());
            return false;
        }
    }

    // ─
    //  CANCELAR
    // ─

    public function cancelarSolicitud(int $id_solicitud, int $id_estudiante): bool
    {
        $this->ejecutar(
            "UPDATE solicitud_proyecto
             SET    estado = 'cancelado', fecha_respuesta = CURDATE()
             WHERE  id_solicitud_proyecto = ? AND id_estudiante = ?
               AND  estado IN ('pendiente', 'en_revision', 'correcciones')",
            'ii',
            [$id_solicitud, $id_estudiante]
        );

        return $this->conn->affected_rows > 0;
    }

    // ─
    //  WHERE DINÁMICO
    // ─

    private function _construirWhere(int $id_estudiante, array $f): array
    {
        $cond   = ['sp.id_estudiante = ?'];
        $params = [$id_estudiante];
        $types  = 'i';

        if (!empty($f['periodo'])) {
            $cond[]   = 'p.id_periodos = ?';
            $params[] = (int)$f['periodo'];
            $types   .= 'i';
        }
        if (!empty($f['estado'])) {
            $cond[]   = 'sp.estado = ?';
            $params[] = $f['estado'];
            $types   .= 's';
        }
        if (!empty($f['buscar'])) {
            $cond[]   = "(p.titulo LIKE ? OR CONCAT(u.nombre,' ',u.apellido_paterno) LIKE ?)";
            $b        = '%' . $f['buscar'] . '%';
            $params[] = $b;
            $params[] = $b;
            $types   .= 'ss';
        }

        return ['WHERE ' . implode(' AND ', $cond), $params, $types];
    }
}