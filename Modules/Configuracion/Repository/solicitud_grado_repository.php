<?php

require_once __DIR__ . '/../../../public/incluido/BaseModelo.php';

/**
 * SolicitudGradoRepositorio
 *
 * Responsabilidad exclusiva: ejecutar consultas SQL relacionadas con
 * solicitudes de actualización de grado académico.
 *
 * Hereda BaseModelo para usar ejecutar() en consultas simples.
 * Las operaciones transaccionales exponen acceso directo a $conn
 * para que el Modelo las orqueste con begin_transaction/commit/rollback.
 */
class SolicitudGradoRepositorio extends BaseModelo
{
    public function __construct(mysqli $conn)
    {
        parent::__construct($conn);
    }


    // 
    // CATÁLOGO
    // 

    /**
     * Devuelve todos los grados académicos activos ordenados por nombre.
     *
     * @return array[]
     */
    public function obtenerGrados(): array
    {
        return $this->ejecutar(
            "SELECT id_grado, nombre FROM grados_academicos WHERE estado = 1 ORDER BY nombre ASC"
        );
    }


    // 
    // DATOS ACTUALES DEL INVESTIGADOR
    // 

    /**
     * Devuelve los datos del investigador junto con su grado académico actual.
     *
     * @return array|null
     */
    public function obtenerDatosInvestigador(int $id_usuario): ?array
    {
        $fila = $this->ejecutar(
            "SELECT
                u.id_usuarios,
                u.nombre,
                u.apellido_paterno,
                u.apellido_materno,
                inv.id_grado,
                ga.nombre AS grado_nombre
             FROM usuarios u
             INNER JOIN investigadores    inv ON inv.id_usuarios = u.id_usuarios
             LEFT  JOIN grados_academicos ga  ON ga.id_grado     = inv.id_grado
             WHERE u.id_usuarios = ?",
            'i',
            [$id_usuario],
            false
        );
        return $fila ?: null;
    }


    // 
    // VERIFICAR SOLICITUD PENDIENTE ACTIVA
    // 

    /**
     * Indica si el investigador ya tiene una solicitud de grado pendiente.
     */
    public function tieneSolicitudPendiente(int $id_usuario): bool
    {
        $fila = $this->ejecutar(
            "SELECT id_solicitudes_actualizacion
             FROM solicitudes_actualizacion
             WHERE id_usuarios = ? AND tipo = 'grado' AND estado = 'pendiente'
             LIMIT 1",
            'i',
            [$id_usuario],
            false
        );
        return !empty($fila);
    }


    // 
    // INSERTAR DOCUMENTO SUBIDO
    // 

    /**
     * Registra el PDF en documentos_subidos y devuelve el id generado.
     *
     * @throws Exception
     */
    public function insertarDocumento(
        string $nombre_display,
        string $nombre_archivo,
        string $ruta_relativa,
        int    $tamano,
        int    $id_usuario
    ): int {
        $this->ejecutar(
            "INSERT INTO documentos_subidos
                 (nombre, nombre_archivo, ruta, tipo_mime, extension, tamano_bytes, tipo, visibilidad, id_usuario)
             VALUES (?, ?, ?, 'application/pdf', 'pdf', ?, 'academico', 'privado', ?)",
            'sssii',
            [$nombre_display, $nombre_archivo, $ruta_relativa, $tamano, $id_usuario]
        );
        return $this->conn->insert_id;
    }


    // 
    // INSERTAR SOLICITUD
    // 

    /**
     * Inserta la solicitud de grado y devuelve el id generado.
     *
     * @throws Exception
     */
    public function insertarSolicitud(
        int    $id_usuario,
        int    $id_documento,
        int    $valor_actual_id,
        int    $valor_nuevo_id
    ): int {
        $tipo = 'grado';
        $this->ejecutar(
            "INSERT INTO solicitudes_actualizacion
                 (id_usuarios, id_documento, tipo, valor_actual_id, valor_nuevo_id, estado)
             VALUES (?, ?, ?, ?, ?, 'pendiente')",
            'iisii',
            [$id_usuario, $id_documento, $tipo, $valor_actual_id, $valor_nuevo_id]
        );
        return $this->conn->insert_id;
    }


    // 
    // HISTORIAL DEL INVESTIGADOR (línea de tiempo)
    // 

    /**
     * Devuelve el total de entradas de historial de solicitudes de grado
     * para el investigador indicado.
     */
    public function contarHistorialInvestigador(int $id_usuario): int
    {
        $fila = $this->ejecutar(
            "SELECT COUNT(*) AS total
             FROM historial_solicitudes_actualizacion h
             INNER JOIN solicitudes_actualizacion s
                     ON s.id_solicitudes_actualizacion = h.id_solicitudes_actualizacion
             WHERE s.id_usuarios = ? AND s.tipo = 'grado'",
            'i',
            [$id_usuario],
            false
        );
        return (int)($fila['total'] ?? 0);
    }

    /**
     * Devuelve una página del historial de solicitudes de grado del investigador.
     *
     * @return array[]
     */
    public function listarHistorialInvestigador(int $id_usuario, int $desde, int $por_pagina): array
    {
        return $this->ejecutar(
            "SELECT
                h.id_historial_actualizacion,
                h.estado_anterior,
                h.estado_nuevo,
                h.comentario,
                h.fecha,
                s.tipo,
                ga.nombre     AS valor_nuevo_nombre,
                ga_act.nombre AS valor_actual_nombre,
                CONCAT(u.nombre, ' ', u.apellido_paterno) AS usuario_accion
             FROM historial_solicitudes_actualizacion h
             INNER JOIN solicitudes_actualizacion s    ON s.id_solicitudes_actualizacion = h.id_solicitudes_actualizacion
             LEFT  JOIN grados_academicos ga           ON ga.id_grado     = s.valor_nuevo_id
             LEFT  JOIN grados_academicos ga_act       ON ga_act.id_grado = s.valor_actual_id
             LEFT  JOIN usuarios          u            ON u.id_usuarios   = h.id_usuario_accion
             WHERE s.id_usuarios = ? AND s.tipo = 'grado'
             ORDER BY h.fecha DESC
             LIMIT ?, ?",
            'iii',
            [$id_usuario, $desde, $por_pagina]
        );
    }


    // 
    // SOLICITUDES PARA SUPERVISOR
    // 

    /**
     * Devuelve los conteos por estado de las solicitudes de grado.
     *
     * @return array
     */
    public function conteosFiltros(): array
    {
        $fila = $this->ejecutar(
            "SELECT
                COUNT(*)                                                AS Total,
                SUM(CASE WHEN estado = 'pendiente' THEN 1 ELSE 0 END)  AS Pendiente,
                SUM(CASE WHEN estado = 'aprobado'  THEN 1 ELSE 0 END)  AS Aprobado,
                SUM(CASE WHEN estado = 'rechazado' THEN 1 ELSE 0 END)  AS Rechazado
             FROM solicitudes_actualizacion
             WHERE tipo = 'grado'",
            '',
            [],
            false
        );
        return $fila ?: [];
    }

    /**
     * Devuelve el total de solicitudes de grado que coinciden con los filtros.
     */
    public function contarSolicitudes(?string $estado, ?string $buscar): int
    {
        [$where, $params, $types] = $this->construirFiltros($estado, $buscar);

        $fila = $this->ejecutar(
            "SELECT COUNT(*) AS total
             FROM solicitudes_actualizacion s
             INNER JOIN usuarios u ON u.id_usuarios = s.id_usuarios
             WHERE " . implode(' AND ', $where),
            $types,
            $params,
            false
        );
        return (int)($fila['total'] ?? 0);
    }

    /**
     * Devuelve una página de solicitudes de grado con todos sus datos relacionados.
     *
     * @return array[]
     */
    public function listarSolicitudes(?string $estado, ?string $buscar, int $desde, int $por_pagina): array
    {
        [$where, $params, $types] = $this->construirFiltros($estado, $buscar);

        $params[] = $desde;
        $params[] = $por_pagina;
        $types   .= 'ii';

        return $this->ejecutar(
            "SELECT
                s.id_solicitudes_actualizacion,
                s.tipo,
                s.estado,
                s.fecha_solicitud,
                s.fecha_respuesta,
                CONCAT(u.nombre, ' ', u.apellido_paterno, ' ', u.apellido_materno) AS investigador,
                u.correo_institucional,
                ga.nombre      AS valor_nuevo_nombre,
                ga_act.nombre  AS valor_actual_nombre,
                d.nombre_archivo,
                d.ruta
             FROM solicitudes_actualizacion s
             INNER JOIN usuarios u               ON u.id_usuarios    = s.id_usuarios
             LEFT  JOIN documentos_subidos d     ON d.id_documento    = s.id_documento
             LEFT  JOIN grados_academicos ga     ON ga.id_grado       = s.valor_nuevo_id
             LEFT  JOIN grados_academicos ga_act ON ga_act.id_grado   = s.valor_actual_id
             WHERE " . implode(' AND ', $where) . "
             ORDER BY s.fecha_solicitud DESC
             LIMIT ?, ?",
            $types,
            $params
        );
    }


    // 
    // DETALLE DE UNA SOLICITUD (supervisor)
    // 

    /**
     * Devuelve todos los datos de una solicitud de grado por su ID.
     *
     * @return array|null
     */
    public function obtenerDetalle(int $id_solicitud): ?array
    {
        $fila = $this->ejecutar(
            "SELECT
                s.*,
                CONCAT(u.nombre, ' ', u.apellido_paterno, ' ', u.apellido_materno) AS investigador,
                u.correo_institucional,
                ga.nombre      AS valor_nuevo_nombre,
                ga_act.nombre  AS valor_actual_nombre,
                d.nombre_archivo,
                d.ruta,
                d.nombre AS doc_nombre,
                CONCAT(rev.nombre, ' ', rev.apellido_paterno) AS revisado_por_nombre
             FROM solicitudes_actualizacion s
             INNER JOIN usuarios u               ON u.id_usuarios    = s.id_usuarios
             LEFT  JOIN documentos_subidos d     ON d.id_documento    = s.id_documento
             LEFT  JOIN grados_academicos ga     ON ga.id_grado       = s.valor_nuevo_id
             LEFT  JOIN grados_academicos ga_act ON ga_act.id_grado   = s.valor_actual_id
             LEFT  JOIN usuarios rev             ON rev.id_usuarios   = s.id_usuarios
             WHERE s.id_solicitudes_actualizacion = ? AND s.tipo = 'grado'",
            'i',
            [$id_solicitud],
            false
        );
        return $fila ?: null;
    }


    // 
    // HISTORIAL DE UNA SOLICITUD (supervisor)
    // 

    /**
     * Devuelve el historial completo de una solicitud de grado.
     *
     * @return array[]
     */
    public function historialDeSolicitud(int $id_solicitud): array
    {
        return $this->ejecutar(
            "SELECT
                h.estado_anterior,
                h.estado_nuevo,
                h.comentario,
                h.fecha,
                CONCAT(u.nombre, ' ', u.apellido_paterno) AS usuario_accion
             FROM historial_solicitudes_actualizacion h
             LEFT JOIN usuarios u ON u.id_usuarios = h.id_usuario_accion
             WHERE h.id_solicitudes_actualizacion = ?
             ORDER BY h.fecha DESC",
            'i',
            [$id_solicitud]
        );
    }


    // 
    // OPERACIONES TRANSACCIONALES (llamadas desde el Modelo)
    // 

    /**
     * Actualiza el grado académico del investigador.
     *
     * @throws Exception
     */
    public function actualizarGradoInvestigador(int $id_usuario, int $id_grado): void
    {
        $this->ejecutar(
            "UPDATE investigadores SET id_grado = ? WHERE id_usuarios = ?",
            'ii',
            [$id_grado, $id_usuario]
        );
        if ($this->conn->affected_rows === 0) {
            throw new Exception("Error actualizando grado académico.");
        }
    }

    /**
     * Marca la solicitud como 'aprobado'.
     *
     * @throws Exception
     */
    public function aprobarSolicitudDB(int $id_solicitud, int $id_supervisor): void
    {
        $this->ejecutar(
            "UPDATE solicitudes_actualizacion
             SET estado = 'aprobado', id_usuario_accion = ?, fecha_respuesta = NOW()
             WHERE id_solicitudes_actualizacion = ? AND estado = 'pendiente'",
            'ii',
            [$id_supervisor, $id_solicitud]
        );
        if ($this->conn->affected_rows === 0) {
            throw new Exception("Error actualizando solicitud de grado.");
        }
    }

    /**
     * Marca la solicitud como 'rechazado'.
     *
     * @throws Exception
     */
    public function rechazarSolicitudDB(int $id_solicitud, int $id_supervisor): void
    {
        $this->ejecutar(
            "UPDATE solicitudes_actualizacion
             SET estado = 'rechazado', id_usuario_accion = ?, fecha_respuesta = NOW()
             WHERE id_solicitudes_actualizacion = ? AND estado = 'pendiente'",
            'ii',
            [$id_supervisor, $id_solicitud]
        );
        if ($this->conn->affected_rows === 0) {
            throw new Exception("Error rechazando solicitud de grado.");
        }
    }

    /**
     * Inserta una entrada en el historial de la solicitud.
     *
     * @throws Exception
     */
    public function insertarHistorial(
        int     $id_solicitud,
        int     $id_usuario_accion,
        ?string $estado_anterior,
        string  $estado_nuevo,
        string  $comentario
    ): void {
        $this->ejecutar(
            "INSERT INTO historial_solicitudes_actualizacion
                 (id_solicitudes_actualizacion, id_usuario_accion, estado_anterior, estado_nuevo, comentario)
             VALUES (?, ?, ?, ?, ?)",
            'iisss',
            [$id_solicitud, $id_usuario_accion, $estado_anterior, $estado_nuevo, $comentario]
        );
    }


    // 
    // DATOS DE CORREO
    // 

    /**
     * Devuelve el correo y nombre del investigador para notificaciones.
     *
     * @return array|null
     */
    public function obtenerCorreoInvestigador(int $id_usuario): ?array
    {
        $fila = $this->ejecutar(
            "SELECT correo_institucional, nombre, apellido_paterno
             FROM usuarios
             WHERE id_usuarios = ?",
            'i',
            [$id_usuario],
            false
        );
        return $fila ?: null;
    }


    // 
    // HELPER PRIVADO: construcción de filtros WHERE
    // 

    /**
     * Construye las cláusulas WHERE compartidas entre contarSolicitudes()
     * y listarSolicitudes().
     *
     * @return array{0: string[], 1: array, 2: string}
     */
    private function construirFiltros(?string $estado, ?string $buscar): array
    {
        $where  = ["s.tipo = 'grado'"];
        $params = [];
        $types  = '';

        if (!empty($estado)) {
            $where[]  = 's.estado = ?';
            $params[] = $estado;
            $types   .= 's';
        }

        if (!empty($buscar)) {
            $where[]  = '(u.nombre LIKE ? OR u.apellido_paterno LIKE ? OR u.apellido_materno LIKE ?)';
            $like      = "%$buscar%";
            $params[]  = $like;
            $params[]  = $like;
            $params[]  = $like;
            $types    .= 'sss';
        }

        return [$where, $params, $types];
    }

    // 
    // ACCESO A CONEXIÓN (para transacciones en el Modelo)
    // 

    /**
     * Expone la conexión para que el Modelo gestione transacciones.
     */
    public function getConn(): mysqli
    {
        return $this->conn;
    }
}