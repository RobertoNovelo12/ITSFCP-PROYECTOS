<?php
require_once __DIR__ . '/../publico/config/conexion.php';

class SolicitudActualizacion
{
    private $con;

    public function __construct($conn)
    {
        $this->con = $conn;
    }

    // 
    //  SOLICITUDES PARA SUPERVISOR
    // 

    public function conteosFiltros()
    {
        $sql = "SELECT
                    COUNT(*)                                                         AS Total,
                    SUM(CASE WHEN estado = 'pendiente'  THEN 1 ELSE 0 END)          AS Pendiente,
                    SUM(CASE WHEN estado = 'aprobado'   THEN 1 ELSE 0 END)          AS Aprobado,
                    SUM(CASE WHEN estado = 'rechazado'  THEN 1 ELSE 0 END)          AS Rechazado
                FROM solicitudes_actualizacion";
        $stmt = $this->con->prepare($sql);
        if (!$stmt) die("Error prepare conteos: " . $this->con->error);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    public function obtenerCantidadSolicitudes($estado = null, $buscar = null, $tipo = null)
    {
        $params = [];
        $types  = "";
        $where  = [];

        $sql = "SELECT COUNT(*) AS total
                FROM solicitudes_actualizacion s
                INNER JOIN usuarios u ON u.id_usuarios = s.id_usuarios";

        if (!empty($estado)) {
            $where[] = "s.estado = ?";
            $params[] = $estado;
            $types   .= "s";
        }
        if (!empty($buscar)) {
            $where[] = "(u.nombre LIKE ? OR u.apellido_paterno LIKE ? OR u.apellido_materno LIKE ?)";
            $like     = "%$buscar%";
            $params[] = $like; $params[] = $like; $params[] = $like;
            $types   .= "sss";
        }
        if (!empty($tipo)) {
            $where[] = "s.tipo = ?";
            $params[] = $tipo;
            $types   .= "s";
        }
        if (!empty($where)) $sql .= " WHERE " . implode(" AND ", $where);

        $stmt = $this->con->prepare($sql);
        if (!$stmt) die("Error prepare count solicitudes: " . $this->con->error);
        if (!empty($types)) $stmt->bind_param($types, ...$params);
        $stmt->execute();
        return (int)$stmt->get_result()->fetch_assoc()['total'];
    }

    public function obtenerSolicitudes($estado = null, $buscar = null, $tipo = null)
    {
        $por_pagina    = 8;
        $pagina        = max(1, intval($_GET['pagina'] ?? 1));
        $desde         = ($pagina - 1) * $por_pagina;
        $total         = $this->obtenerCantidadSolicitudes($estado, $buscar, $tipo);
        $total_paginas = max(1, ceil($total / $por_pagina));

        $params = [];
        $types  = "";
        $where  = [];

        $sql = "SELECT
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
                INNER JOIN usuarios u ON u.id_usuarios = s.id_usuarios
                LEFT JOIN  documentos_subidos d  ON d.id_documento  = s.id_documento
                LEFT JOIN  niveles_sni      ns     ON ns.id_nivel    = s.valor_nuevo_id   AND s.tipo = 'sni'
                LEFT JOIN  niveles_sni      ns_act ON ns_act.id_nivel = s.valor_actual_id AND s.tipo = 'sni'
                LEFT JOIN  grados_academicos ga    ON ga.id_grado    = s.valor_nuevo_id   AND s.tipo = 'grado'
                LEFT JOIN  grados_academicos ga_act ON ga_act.id_grado = s.valor_actual_id AND s.tipo = 'grado'";

        if (!empty($estado)) {
            $where[] = "s.estado = ?"; $params[] = $estado; $types .= "s";
        }
        if (!empty($buscar)) {
            $where[] = "(u.nombre LIKE ? OR u.apellido_paterno LIKE ? OR u.apellido_materno LIKE ?)";
            $like = "%$buscar%";
            $params[] = $like; $params[] = $like; $params[] = $like;
            $types .= "sss";
        }
        if (!empty($tipo)) {
            $where[] = "s.tipo = ?"; $params[] = $tipo; $types .= "s";
        }
        if (!empty($where)) $sql .= " WHERE " . implode(" AND ", $where);
        $sql .= " ORDER BY s.fecha_solicitud DESC LIMIT ?, ?";
        $params[] = $desde; $params[] = $por_pagina; $types .= "ii";

        $stmt = $this->con->prepare($sql);
        if (!$stmt) die("Error prepare solicitudes: " . $this->con->error);
        $stmt->bind_param($types, ...$params);
        if (!$stmt->execute()) die("Error execute solicitudes: " . $stmt->error);

        return json_encode([
            'solicitudes' => $stmt->get_result()->fetch_all(MYSQLI_ASSOC),
            'paginacion'  => [
                'total'        => $total,
                'por_pagina'   => $por_pagina,
                'pagina'       => $pagina,
                'total_paginas'=> $total_paginas,
            ],
        ]);
    }

    // 
    //  DETALLE DE UNA SOLICITUD (supervisor)
    // 

    public function obtenerDetalle($id_solicitud)
    {
        $sql = "SELECT
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
                WHERE s.id_solicitudes_actualizacion = ?";
        $stmt = $this->con->prepare($sql);
        if (!$stmt) die("Error prepare detalle: " . $this->con->error);
        $stmt->bind_param("i", $id_solicitud);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    // 
    //  HISTORIAL DE UNA SOLICITUD (para detalles supervisor)
    // 

    public function historialDeSolicitud($id_solicitud)
    {
        $sql = "SELECT
                    h.estado_anterior,
                    h.estado_nuevo,
                    h.comentario,
                    h.fecha,
                    CONCAT(u.nombre,' ',u.apellido_paterno) AS usuario_accion
                FROM historial_solicitudes_actualizacion h
                LEFT JOIN usuarios u ON u.id_usuarios = h.id_usuario_accion
                WHERE h.id_solicitudes_actualizacion = ?
                ORDER BY h.fecha DESC";
        $stmt = $this->con->prepare($sql);
        if (!$stmt) die("Error prepare historial solicitud: " . $this->con->error);
        $stmt->bind_param("i", $id_solicitud);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    // 
    //  APROBAR SOLICITUD (supervisor) — TRANSACCIONAL
    // 

    public function aprobarSolicitud($id_solicitud, $id_supervisor)
    {
        // Verificar que la solicitud existe y está pendiente
        $detalle = $this->obtenerDetalle($id_solicitud);
        if (empty($detalle) || $detalle['estado'] !== 'pendiente') {
            return ['ok' => false, 'msg' => 'La solicitud ya no está pendiente.'];
        }

        $id_usuario    = (int)$detalle['id_usuarios'];
        $tipo          = $detalle['tipo'];
        $valor_nuevo   = (int)$detalle['valor_nuevo_id'];

        $this->con->begin_transaction();
        try {
            // 1. Actualizar investigador
            if ($tipo === 'sni') {
                $sqlUpd = "UPDATE investigadores SET id_nivel_sni = ? WHERE id_usuarios = ?";
            } else {
                $sqlUpd = "UPDATE investigadores SET id_grado = ? WHERE id_usuarios = ?";
            }
            $stmt = $this->con->prepare($sqlUpd);
            $stmt->bind_param("ii", $valor_nuevo, $id_usuario);
            if (!$stmt->execute()) throw new Exception("Error actualizando investigador: " . $stmt->error);

            // 2. Actualizar solicitud
            $sqlSol = "UPDATE solicitudes_actualizacion
                       SET estado = 'aprobado', id_usuarios = ?, fecha_respuesta = NOW()
                       WHERE id_solicitudes_actualizacion = ? AND estado = 'pendiente'";
            $stmt2 = $this->con->prepare($sqlSol);
            $stmt2->bind_param("ii", $id_supervisor, $id_solicitud);
            if (!$stmt2->execute() || $stmt2->affected_rows === 0)
                throw new Exception("Error actualizando solicitud.");

            // 3. Historial
            $this->insertarHistorial($id_solicitud, $id_supervisor, 'pendiente', 'aprobado', 'Validado y aprobado por supervisor.');

            $this->con->commit();
            return ['ok' => true, 'msg' => 'Solicitud aprobada correctamente.'];
        } catch (Exception $e) {
            $this->con->rollback();
            return ['ok' => false, 'msg' => $e->getMessage()];
        }
    }

    // 
    //  RECHAZAR SOLICITUD (supervisor)
    // 

    public function rechazarSolicitud($id_solicitud, $id_supervisor, $comentario)
    {
        $comentario = trim($comentario);
        if (empty($comentario)) {
            return ['ok' => false, 'msg' => 'El comentario es obligatorio para rechazar.'];
        }

        $detalle = $this->obtenerDetalle($id_solicitud);
        if (empty($detalle) || $detalle['estado'] !== 'pendiente') {
            return ['ok' => false, 'msg' => 'La solicitud ya no está pendiente.'];
        }

        $this->con->begin_transaction();
        try {
            $sqlSol = "UPDATE solicitudes_actualizacion
                       SET estado = 'rechazado', revisado_por = ?, fecha_respuesta = NOW()
                       WHERE id_solicitudes_actualizacion = ? AND estado = 'pendiente'";
            $stmt = $this->con->prepare($sqlSol);
            $stmt->bind_param("ii", $id_supervisor, $id_solicitud);
            if (!$stmt->execute() || $stmt->affected_rows === 0)
                throw new Exception("Error rechazando solicitud.");

            $this->insertarHistorial($id_solicitud, $id_supervisor, 'pendiente', 'rechazado', $comentario);

            $this->con->commit();
            return ['ok' => true, 'msg' => 'Solicitud rechazada.', 'detalle' => $detalle];
        } catch (Exception $e) {
            $this->con->rollback();
            return ['ok' => false, 'msg' => $e->getMessage()];
        }
    }

    // 
    //  HELPER: INSERTAR EN HISTORIAL
    // 

    private function insertarHistorial($id_solicitud, $id_usuario_accion, $estado_anterior, $estado_nuevo, $comentario)
    {
        $sql = "INSERT INTO historial_solicitudes_actualizacion
                    (id_solicitudes_actualizacion, id_usuario_accion, estado_anterior, estado_nuevo, comentario)
                VALUES (?, ?, ?, ?, ?)";
        $stmt = $this->con->prepare($sql);
        if (!$stmt) die("Error prepare historial: " . $this->con->error);
        $stmt->bind_param("iisss", $id_solicitud, $id_usuario_accion, $estado_anterior, $estado_nuevo, $comentario);
        $stmt->execute();
    }

    // 
    //  DATOS DE CORREO
    // 

    public function obtenerCorreoInvestigador($id_usuario)
    {
        $sql  = "SELECT correo_institucional, nombre, apellido_paterno FROM usuarios WHERE id_usuarios = ?";
        $stmt = $this->con->prepare($sql);
        $stmt->bind_param("i", $id_usuario);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }
}