<?php

require_once __DIR__ . '/../../../public/incluido/BaseModelo.php';

/**
 * SolicitudActualizacionRepositorio
 *
 * Responsabilidad exclusiva: ejecutar consultas SQL del módulo de solicitudes
 * de actualización (SNI / grado académico) del investigador.
 * No contiene lógica de negocio.
 *
 *  Adaptación especial 
 * aprobarSolicitud() y rechazarSolicitud() gestionan transacciones propias
 * porque son operaciones de persistencia compuestas e indivisibles.
 * El modelo las delega completamente y solo recibe el resultado.
 * 
 */
class SolicitudActualizacionRepositorio extends BaseModelo
{
    public function __construct(mysqli $conn)
    {
        parent::__construct($conn);
    }


    // 
    // CONTEO
    // 

    public function contarSolicitudes(string $where, array $params, string $types): int
    {
        $fila = $this->ejecutar(
            "SELECT COUNT(*) AS total
             FROM solicitudes_actualizacion s
             INNER JOIN usuarios u ON u.id_usuarios = s.id_usuarios
             $where",
            $types,
            $params,
            false
        );

        return (int)($fila['total'] ?? 0);
    }


    // 
    // LISTADO PAGINADO
    // 

    public function listarSolicitudes(string $where, array $params, string $types, int $desde, int $por_pagina): array
    {
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
                CONCAT(u.nombre,' ',u.apellido_paterno,' ',u.apellido_materno) AS investigador,
                u.correo_institucional,
                CASE s.tipo
                    WHEN 'sni'   THEN ns.nombre
                    WHEN 'grado' THEN ga.nombre
                END AS valor_nuevo_nombre,
                CASE s.tipo
                    WHEN 'sni'   THEN ns_act.nombre
                    WHEN 'grado' THEN ga_act.nombre
                END AS valor_actual_nombre,
                d.nombre_archivo,
                d.ruta
             FROM solicitudes_actualizacion s
             INNER JOIN usuarios u         ON u.id_usuarios    = s.id_usuarios
             LEFT JOIN  documentos_subidos d  ON d.id_documento  = s.id_documento
             LEFT JOIN  niveles_sni      ns     ON ns.id_nivel    = s.valor_nuevo_id   AND s.tipo = 'sni'
             LEFT JOIN  niveles_sni      ns_act ON ns_act.id_nivel = s.valor_actual_id AND s.tipo = 'sni'
             LEFT JOIN  grados_academicos ga    ON ga.id_grado    = s.valor_nuevo_id   AND s.tipo = 'grado'
             LEFT JOIN  grados_academicos ga_act ON ga_act.id_grado = s.valor_actual_id AND s.tipo = 'grado'
             $where
             ORDER BY s.fecha_solicitud DESC
             LIMIT ?, ?",
            $types,
            $params
        );
    }


    // 
    // DETALLE
    // 

    public function buscarDetalle(int $id_solicitud): ?array
    {
        return $this->ejecutar(
            "SELECT
                s.*,
                CONCAT(u.nombre,' ',u.apellido_paterno,' ',u.apellido_materno) AS investigador,
                u.correo_institucional,
                CASE s.tipo
                    WHEN 'sni'   THEN ns.nombre
                    WHEN 'grado' THEN ga.nombre
                END AS valor_nuevo_nombre,
                CASE s.tipo
                    WHEN 'sni'   THEN ns_act.nombre
                    WHEN 'grado' THEN ga_act.nombre
                END AS valor_actual_nombre,
                d.nombre_archivo,
                d.ruta,
                d.nombre AS doc_nombre,
                CONCAT(rev.nombre,' ',rev.apellido_paterno) AS revisado_por_nombre
             FROM solicitudes_actualizacion s
             INNER JOIN usuarios u ON u.id_usuarios = s.id_usuarios
             LEFT JOIN  documentos_subidos  d    ON d.id_documento   = s.id_documento
             LEFT JOIN  niveles_sni         ns   ON ns.id_nivel       = s.valor_nuevo_id  AND s.tipo = 'sni'
             LEFT JOIN  niveles_sni      ns_act  ON ns_act.id_nivel   = s.valor_actual_id AND s.tipo = 'sni'
             LEFT JOIN  grados_academicos   ga   ON ga.id_grado       = s.valor_nuevo_id  AND s.tipo = 'grado'
             LEFT JOIN  grados_academicos ga_act ON ga_act.id_grado   = s.valor_actual_id AND s.tipo = 'grado'
             LEFT JOIN  usuarios rev ON rev.id_usuarios = s.id_usuarios
             WHERE s.id_solicitudes_actualizacion = ?",
            'i',
            [$id_solicitud],
            false
        ) ?: null;
    }


    // 
    // HISTORIAL
    // 

    public function listarHistorial(int $id_solicitud): array
    {
        return $this->ejecutar(
            "SELECT
                h.estado_anterior,
                h.estado_nuevo,
                h.comentario,
                h.fecha,
                CONCAT(u.nombre,' ',u.apellido_paterno) AS usuario_accion
             FROM historial_solicitudes_actualizacion h
             LEFT JOIN usuarios u ON u.id_usuarios = h.id_usuario_accion
             WHERE h.id_solicitudes_actualizacion = ?
             ORDER BY h.fecha DESC",
            'i',
            [$id_solicitud]
        );
    }


    // 
    // APROBAR (transaccional)
    // 

    public function aprobarSolicitud(int $id_solicitud, int $id_supervisor): array
    {
        $detalle = $this->buscarDetalle($id_solicitud);

        if (empty($detalle) || $detalle['estado'] !== 'pendiente') {
            return ['ok' => false, 'msg' => 'La solicitud ya no está pendiente.'];
        }

        $id_usuario  = (int)$detalle['id_usuarios'];
        $tipo        = $detalle['tipo'];
        $valor_nuevo = (int)$detalle['valor_nuevo_id'];

        $this->conn->begin_transaction();
        try {
            $campo = ($tipo === 'sni') ? 'id_nivel_sni' : 'id_grado';
            $this->ejecutar(
                "UPDATE investigadores SET {$campo} = ? WHERE id_usuarios = ?",
                'ii',
                [$valor_nuevo, $id_usuario]
            );

            $this->ejecutar(
                "UPDATE solicitudes_actualizacion
                 SET estado = 'aprobado', id_usuarios = ?, fecha_respuesta = NOW()
                 WHERE id_solicitudes_actualizacion = ? AND estado = 'pendiente'",
                'ii',
                [$id_supervisor, $id_solicitud]
            );

            $this->insertarHistorial(
                $id_solicitud, $id_supervisor,
                'pendiente', 'aprobado',
                'Validado y aprobado por supervisor.'
            );

            $this->conn->commit();
            return ['ok' => true, 'msg' => 'Solicitud aprobada correctamente.'];

        } catch (Exception $e) {
            $this->conn->rollback();
            return ['ok' => false, 'msg' => $e->getMessage()];
        }
    }


    // 
    // RECHAZAR (transaccional)
    // 

    public function rechazarSolicitud(int $id_solicitud, int $id_supervisor, string $comentario): array
    {
        $comentario = trim($comentario);

        if (empty($comentario)) {
            return ['ok' => false, 'msg' => 'El comentario es obligatorio para rechazar.'];
        }

        $detalle = $this->buscarDetalle($id_solicitud);

        if (empty($detalle) || $detalle['estado'] !== 'pendiente') {
            return ['ok' => false, 'msg' => 'La solicitud ya no está pendiente.'];
        }

        $this->conn->begin_transaction();
        try {
            $this->ejecutar(
                "UPDATE solicitudes_actualizacion
                 SET estado = 'rechazado', revisado_por = ?, fecha_respuesta = NOW()
                 WHERE id_solicitudes_actualizacion = ? AND estado = 'pendiente'",
                'ii',
                [$id_supervisor, $id_solicitud]
            );

            $this->insertarHistorial(
                $id_solicitud, $id_supervisor,
                'pendiente', 'rechazado',
                $comentario
            );

            $this->conn->commit();
            return ['ok' => true, 'msg' => 'Solicitud rechazada.', 'detalle' => $detalle];

        } catch (Exception $e) {
            $this->conn->rollback();
            return ['ok' => false, 'msg' => $e->getMessage()];
        }
    }


    // 
    // HELPER PRIVADO: HISTORIAL
    // 

    private function insertarHistorial(
        int    $id_solicitud,
        int    $id_usuario_accion,
        string $estado_anterior,
        string $estado_nuevo,
        string $comentario
    ): void {
        $this->ejecutar(
            'INSERT INTO historial_solicitudes_actualizacion
                (id_solicitudes_actualizacion, id_usuario_accion, estado_anterior, estado_nuevo, comentario)
             VALUES (?, ?, ?, ?, ?)',
            'iisss',
            [$id_solicitud, $id_usuario_accion, $estado_anterior, $estado_nuevo, $comentario]
        );
    }


    // 
    // DATOS DE CORREO
    // 

    public function buscarCorreoInvestigador(int $id_usuario): ?array
    {
        $fila = $this->ejecutar(
            'SELECT correo_institucional, nombre, apellido_paterno
             FROM usuarios WHERE id_usuarios = ?',
            'i',
            [$id_usuario],
            false
        );

        return $fila ?: null;
    }
}