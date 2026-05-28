<?php
// Modelos/solicitudActualizacion.php

require_once __DIR__ . '/../publico/config/conexion.php';
require_once __DIR__ . '/BaseModelo.php';

class SolicitudActualizacion extends BaseModelo
{
    // ─
    //  FILTROS / CONTEOS
    // ─

    public function conteosFiltros(): array
    {
        return $this->ejecutar(
            "SELECT
                COUNT(*) AS Total,
                SUM(CASE WHEN estado = 'pendiente'  THEN 1 ELSE 0 END) AS Pendiente,
                SUM(CASE WHEN estado = 'aprobado'   THEN 1 ELSE 0 END) AS Aprobado,
                SUM(CASE WHEN estado = 'rechazado'  THEN 1 ELSE 0 END) AS Rechazado
             FROM solicitudes_actualizacion",
            '',
            [],
            false
        );
    }

    // ─
    //  WHERE DINÁMICO (reutilizable)
    // ─

    private function construirWhere(?string $estado, ?string $buscar, ?string $tipo): array
    {
        $cond   = [];
        $params = [];
        $types  = '';

        if (!empty($estado)) {
            $cond[]   = 's.estado = ?';
            $params[] = $estado;
            $types   .= 's';
        }
        if (!empty($buscar)) {
            $cond[]   = '(u.nombre LIKE ? OR u.apellido_paterno LIKE ? OR u.apellido_materno LIKE ?)';
            $like     = "%$buscar%";
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $types   .= 'sss';
        }
        if (!empty($tipo)) {
            $cond[]   = 's.tipo = ?';
            $params[] = $tipo;
            $types   .= 's';
        }

        $where = !empty($cond) ? 'WHERE ' . implode(' AND ', $cond) : '';
        return [$where, $params, $types];
    }

    // ─
    //  LISTADO PAGINADO
    // ─

    private function obtenerCantidadSolicitudes(?string $estado, ?string $buscar, ?string $tipo): int
    {
        [$where, $params, $types] = $this->construirWhere($estado, $buscar, $tipo);

        $row = $this->ejecutar(
            "SELECT COUNT(*) AS total
             FROM solicitudes_actualizacion s
             INNER JOIN usuarios u ON u.id_usuarios = s.id_usuarios
             $where",
            $types,
            $params,
            false
        );

        return (int)($row['total'] ?? 0);
    }

    public function obtenerSolicitudes(?string $estado = null, ?string $buscar = null, ?string $tipo = null): string
    {
        $por_pagina    = 8;
        $pagina        = max(1, (int)($_GET['pagina'] ?? 1));
        $desde         = ($pagina - 1) * $por_pagina;
        $total         = $this->obtenerCantidadSolicitudes($estado, $buscar, $tipo);
        $total_paginas = max(1, (int)ceil($total / $por_pagina));

        [$where, $params, $types] = $this->construirWhere($estado, $buscar, $tipo);

        $params[] = $desde;
        $params[] = $por_pagina;
        $types   .= 'ii';

        $data = $this->ejecutar(
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
             INNER JOIN usuarios u        ON u.id_usuarios    = s.id_usuarios
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

        return json_encode([
            'solicitudes' => $data,
            'paginacion'  => compact('total', 'por_pagina', 'pagina', 'total_paginas'),
        ]);
    }

    // ─
    //  DETALLE
    // ─

    public function obtenerDetalle(int $id_solicitud): ?array
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

    // ─
    //  HISTORIAL
    // ─

    public function historialDeSolicitud(int $id_solicitud): array
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

    // ─
    //  APROBAR (transaccional)
    // ─

    public function aprobarSolicitud(int $id_solicitud, int $id_supervisor): array
    {
        $detalle = $this->obtenerDetalle($id_solicitud);
        if (empty($detalle) || $detalle['estado'] !== 'pendiente') {
            return ['ok' => false, 'msg' => 'La solicitud ya no está pendiente.'];
        }

        $id_usuario  = (int)$detalle['id_usuarios'];
        $tipo        = $detalle['tipo'];
        $valor_nuevo = (int)$detalle['valor_nuevo_id'];

        $this->conn->begin_transaction();
        try {
            // 1. Actualizar investigador
            $campo = ($tipo === 'sni') ? 'id_nivel_sni' : 'id_grado';
            $this->ejecutar(
                "UPDATE investigadores SET {$campo} = ? WHERE id_usuarios = ?",
                'ii',
                [$valor_nuevo, $id_usuario]
            );

            // 2. Actualizar solicitud
            $this->ejecutar(
                "UPDATE solicitudes_actualizacion
                 SET estado = 'aprobado', id_usuarios = ?, fecha_respuesta = NOW()
                 WHERE id_solicitudes_actualizacion = ? AND estado = 'pendiente'",
                'ii',
                [$id_supervisor, $id_solicitud]
            );

            // 3. Historial
            $this->insertarHistorial($id_solicitud, $id_supervisor, 'pendiente', 'aprobado', 'Validado y aprobado por supervisor.');

            $this->conn->commit();
            return ['ok' => true, 'msg' => 'Solicitud aprobada correctamente.'];

        } catch (\Exception $e) {
            $this->conn->rollback();
            return ['ok' => false, 'msg' => $e->getMessage()];
        }
    }

    // ─
    //  RECHAZAR (transaccional)
    // ─

    public function rechazarSolicitud(int $id_solicitud, int $id_supervisor, string $comentario): array
    {
        $comentario = trim($comentario);
        if (empty($comentario)) {
            return ['ok' => false, 'msg' => 'El comentario es obligatorio para rechazar.'];
        }

        $detalle = $this->obtenerDetalle($id_solicitud);
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

            $this->insertarHistorial($id_solicitud, $id_supervisor, 'pendiente', 'rechazado', $comentario);

            $this->conn->commit();
            return ['ok' => true, 'msg' => 'Solicitud rechazada.', 'detalle' => $detalle];

        } catch (\Exception $e) {
            $this->conn->rollback();
            return ['ok' => false, 'msg' => $e->getMessage()];
        }
    }

    // ─
    //  HELPER: HISTORIAL
    // ─

    private function insertarHistorial(
        int    $id_solicitud,
        int    $id_usuario_accion,
        string $estado_anterior,
        string $estado_nuevo,
        string $comentario
    ): void {
        $this->ejecutar(
            "INSERT INTO historial_solicitudes_actualizacion
                (id_solicitudes_actualizacion, id_usuario_accion, estado_anterior, estado_nuevo, comentario)
             VALUES (?, ?, ?, ?, ?)",
            'iisss',
            [$id_solicitud, $id_usuario_accion, $estado_anterior, $estado_nuevo, $comentario]
        );
    }

    // ─
    //  DATOS DE CORREO
    // ─

    public function obtenerCorreoInvestigador(int $id_usuario): ?array
    {
        return $this->ejecutar(
            'SELECT correo_institucional, nombre, apellido_paterno FROM usuarios WHERE id_usuarios = ?',
            'i',
            [$id_usuario],
            false
        ) ?: null;
    }
}