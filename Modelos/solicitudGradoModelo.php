<?php
require_once __DIR__ . '/../publico/config/conexion.php';

class SolicitudGrado
{
    private $con;

    public function __construct($conn)
    {
        $this->con = $conn;
    }

    // 
    //  CATÁLOGO
    // 

    public function obtenerGrados()
    {
        $sql  = "SELECT id_grado, nombre FROM grados_academicos WHERE estado = 1 ORDER BY nombre ASC";
        $stmt = $this->con->prepare($sql);
        if (!$stmt) die("Error prepare grados_academicos: " . $this->con->error);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    // 
    //  DATOS ACTUALES DEL INVESTIGADOR
    // 

    public function obtenerDatosInvestigador($id_usuario)
    {
        $sql = "SELECT
                    u.id_usuarios,
                    u.nombre,
                    u.apellido_paterno,
                    u.apellido_materno,
                    inv.id_grado,
                    ga.nombre AS grado_nombre
                FROM usuarios u
                INNER JOIN investigadores    inv ON inv.id_usuarios = u.id_usuarios
                LEFT  JOIN grados_academicos ga  ON ga.id_grado     = inv.id_grado
                WHERE u.id_usuarios = ?";
        $stmt = $this->con->prepare($sql);
        if (!$stmt) die("Error prepare investigador Grado: " . $this->con->error);
        $stmt->bind_param("i", $id_usuario);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    // 
    //  VERIFICAR SOLICITUD PENDIENTE ACTIVA
    // 

    public function tieneSolicitudPendiente($id_usuario)
    {
        $sql  = "SELECT id_solicitudes_actualizacion
                 FROM solicitudes_actualizacion
                 WHERE id_usuarios = ? AND tipo = 'grado' AND estado = 'pendiente'
                 LIMIT 1";
        $stmt = $this->con->prepare($sql);
        if (!$stmt) die("Error prepare pendiente Grado: " . $this->con->error);
        $stmt->bind_param("i", $id_usuario);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        return !empty($row);
    }

    // 
    //  CREAR SOLICITUD GRADO (investigador)
    // 

    public function crearSolicitud($id_usuario, $valor_actual_id, $valor_nuevo_id, $archivo)
    {
        // ── 1. Validar que el valor cambió ───────────────────────
        if ((int)$valor_actual_id === (int)$valor_nuevo_id) {
            return ['ok' => false, 'msg' => 'El grado académico nuevo es igual al actual.'];
        }

        // ── 2. Verificar que no hay pendiente ────────────────────
        if ($this->tieneSolicitudPendiente($id_usuario)) {
            return ['ok' => false, 'msg' => 'Ya tienes una solicitud de grado académico en proceso.'];
        }

        // ── 3. Validar PDF ───────────────────────────────────────
        if (empty($archivo['tmp_name'])) {
            return ['ok' => false, 'msg' => 'El documento PDF es obligatorio.'];
        }

        $finfo     = finfo_open(FILEINFO_MIME_TYPE);
        $mime_real = finfo_file($finfo, $archivo['tmp_name']);
        finfo_close($finfo);

        if ($mime_real !== 'application/pdf' || $archivo['type'] !== 'application/pdf') {
            return ['ok' => false, 'msg' => 'Solo se aceptan archivos PDF.'];
        }
        if ($archivo['size'] > 2 * 1024 * 1024) {
            return ['ok' => false, 'msg' => 'El archivo no debe superar 2 MB.'];
        }

        // ── 4. Guardar PDF en storage ────────────────────────────
        $dir = __DIR__ . '/../storage/academico/usuario_' . $id_usuario . '/';
        if (!is_dir($dir)) mkdir($dir, 0755, true);

        $nombre_archivo = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $archivo['name']);
        $ruta_relativa  = '/ITSFCP-PROYECTOS/storage/academico/usuario_' . $id_usuario . '/' . $nombre_archivo;

        if (!move_uploaded_file($archivo['tmp_name'], $dir . $nombre_archivo)) {
            return ['ok' => false, 'msg' => 'Error al guardar el documento.'];
        }

        // ── 5. Insertar en documentos_subidos ────────────────────
        $nombre_display = 'Evidencia Grado - ' . date('d/m/Y');
        $sql_doc = "INSERT INTO documentos_subidos
                        (nombre, nombre_archivo, ruta, tipo_mime, extension, tamano_bytes, tipo, visibilidad, id_usuario)
                    VALUES (?, ?, ?, 'application/pdf', 'pdf', ?, 'academico', 'privado', ?)";
        $stmt = $this->con->prepare($sql_doc);
        if (!$stmt) return ['ok' => false, 'msg' => 'Error prepare documento: ' . $this->con->error];
        $stmt->bind_param("sssii", $nombre_display, $nombre_archivo, $ruta_relativa, $archivo['size'], $id_usuario);
        if (!$stmt->execute()) return ['ok' => false, 'msg' => 'Error al registrar documento: ' . $stmt->error];
        $id_documento = $this->con->insert_id;

        // ── 6. Insertar solicitud ────────────────────────────────
        $tipo  = 'grado';
        $sql_sol = "INSERT INTO solicitudes_actualizacion
                        (id_usuarios, id_documento, tipo, valor_actual_id, valor_nuevo_id, estado)
                    VALUES (?, ?, ?, ?, ?, 'pendiente')";
        $stmt2 = $this->con->prepare($sql_sol);
        if (!$stmt2) return ['ok' => false, 'msg' => 'Error prepare solicitud: ' . $this->con->error];
        $stmt2->bind_param("iisii", $id_usuario, $id_documento, $tipo, $valor_actual_id, $valor_nuevo_id);
        if (!$stmt2->execute()) return ['ok' => false, 'msg' => 'Error al crear solicitud: ' . $stmt2->error];
        $id_solicitud = $this->con->insert_id;

        // ── 7. Historial inicial ─────────────────────────────────
        $this->insertarHistorial($id_solicitud, $id_usuario, null, 'pendiente', 'Solicitud de grado académico creada por el investigador.');

        return ['ok' => true, 'msg' => 'Solicitud de grado académico enviada correctamente. Queda pendiente de revisión.'];
    }

    // 
    //  HISTORIAL DEL INVESTIGADOR (línea de tiempo)
    // 

    public function historialInvestigador($id_usuario, $pagina = 1, $por_pagina = 8)
    {
        $pagina = max(1, (int)$pagina);
        $desde  = ($pagina - 1) * $por_pagina;

        // Total
        $sqlTotal = "SELECT COUNT(*) AS total
                     FROM historial_solicitudes_actualizacion h
                     INNER JOIN solicitudes_actualizacion s ON s.id_solicitudes_actualizacion = h.id_solicitudes_actualizacion
                     WHERE s.id_usuarios = ? AND s.tipo = 'grado'";
        $stmt = $this->con->prepare($sqlTotal);
        $stmt->bind_param("i", $id_usuario);
        $stmt->execute();
        $total         = (int)$stmt->get_result()->fetch_assoc()['total'];
        $total_paginas = max(1, (int)ceil($total / $por_pagina));

        // Datos
        $sql = "SELECT
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
                LIMIT ?, ?";
        $stmt2 = $this->con->prepare($sql);
        if (!$stmt2) die("Error prepare historial Grado: " . $this->con->error);
        $stmt2->bind_param("iii", $id_usuario, $desde, $por_pagina);
        $stmt2->execute();
        $filas = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);

        // Agrupar por fecha
        $agrupado = [];
        foreach ($filas as $f) {
            $fecha = date("d/m/Y", strtotime($f['fecha']));
            $agrupado[$fecha][] = $f;
        }

        return [
            'datos' => $agrupado,
            'paginacion' => [
                'total'         => $total,
                'por_pagina'    => $por_pagina,
                'pagina'        => $pagina,
                'total_paginas' => $total_paginas,
            ],
        ];
    }

    // 
    //  SOLICITUDES PARA SUPERVISOR
    // 

    public function conteosFiltros()
    {
        $sql = "SELECT
                    COUNT(*)                                                    AS Total,
                    SUM(CASE WHEN estado = 'pendiente'  THEN 1 ELSE 0 END)     AS Pendiente,
                    SUM(CASE WHEN estado = 'aprobado'   THEN 1 ELSE 0 END)     AS Aprobado,
                    SUM(CASE WHEN estado = 'rechazado'  THEN 1 ELSE 0 END)     AS Rechazado
                FROM solicitudes_actualizacion
                WHERE tipo = 'grado'";
        $stmt = $this->con->prepare($sql);
        if (!$stmt) die("Error prepare conteos Grado: " . $this->con->error);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    public function obtenerCantidadSolicitudes($estado = null, $buscar = null)
    {
        $params = [];
        $types  = "";
        $where  = ["s.tipo = 'grado'"];

        $sql = "SELECT COUNT(*) AS total
                FROM solicitudes_actualizacion s
                INNER JOIN usuarios u ON u.id_usuarios = s.id_usuarios";

        if (!empty($estado)) {
            $where[]  = "s.estado = ?";
            $params[] = $estado;
            $types   .= "s";
        }
        if (!empty($buscar)) {
            $where[] = "(u.nombre LIKE ? OR u.apellido_paterno LIKE ? OR u.apellido_materno LIKE ?)";
            $like     = "%$buscar%";
            $params[] = $like; $params[] = $like; $params[] = $like;
            $types   .= "sss";
        }

        $sql .= " WHERE " . implode(" AND ", $where);

        $stmt = $this->con->prepare($sql);
        if (!$stmt) die("Error prepare count Grado: " . $this->con->error);
        if (!empty($types)) $stmt->bind_param($types, ...$params);
        $stmt->execute();
        return (int)$stmt->get_result()->fetch_assoc()['total'];
    }

    public function obtenerSolicitudes($estado = null, $buscar = null)
    {
        $por_pagina    = 8;
        $pagina        = max(1, intval($_GET['pagina'] ?? 1));
        $desde         = ($pagina - 1) * $por_pagina;
        $total         = $this->obtenerCantidadSolicitudes($estado, $buscar);
        $total_paginas = max(1, ceil($total / $por_pagina));

        $params = [];
        $types  = "";
        $where  = ["s.tipo = 'grado'"];

        $sql = "SELECT
                    s.id_solicitudes_actualizacion,
                    s.tipo,
                    s.estado,
                    s.fecha_solicitud,
                    s.fecha_respuesta,
                    CONCAT(u.nombre, ' ', u.apellido_paterno, ' ', u.apellido_materno) AS investigador,
                    u.correo_institucional,
                    ga.nombre     AS valor_nuevo_nombre,
                    ga_act.nombre AS valor_actual_nombre,
                    d.nombre_archivo,
                    d.ruta
                FROM solicitudes_actualizacion s
                INNER JOIN usuarios u              ON u.id_usuarios   = s.id_usuarios
                LEFT  JOIN documentos_subidos d    ON d.id_documento   = s.id_documento
                LEFT  JOIN grados_academicos ga    ON ga.id_grado      = s.valor_nuevo_id
                LEFT  JOIN grados_academicos ga_act ON ga_act.id_grado = s.valor_actual_id";

        if (!empty($estado)) {
            $where[]  = "s.estado = ?";
            $params[] = $estado;
            $types   .= "s";
        }
        if (!empty($buscar)) {
            $where[] = "(u.nombre LIKE ? OR u.apellido_paterno LIKE ? OR u.apellido_materno LIKE ?)";
            $like     = "%$buscar%";
            $params[] = $like; $params[] = $like; $params[] = $like;
            $types   .= "sss";
        }

        $sql .= " WHERE " . implode(" AND ", $where);
        $sql .= " ORDER BY s.fecha_solicitud DESC LIMIT ?, ?";
        $params[] = $desde; $params[] = $por_pagina;
        $types   .= "ii";

        $stmt = $this->con->prepare($sql);
        if (!$stmt) die("Error prepare solicitudes Grado: " . $this->con->error);
        $stmt->bind_param($types, ...$params);
        if (!$stmt->execute()) die("Error execute solicitudes Grado: " . $stmt->error);

        return json_encode([
            'solicitudes' => $stmt->get_result()->fetch_all(MYSQLI_ASSOC),
            'paginacion'  => [
                'total'         => $total,
                'por_pagina'    => $por_pagina,
                'pagina'        => $pagina,
                'total_paginas' => $total_paginas,
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
                    CONCAT(u.nombre, ' ', u.apellido_paterno, ' ', u.apellido_materno) AS investigador,
                    u.correo_institucional,
                    ga.nombre     AS valor_nuevo_nombre,
                    ga_act.nombre AS valor_actual_nombre,
                    d.nombre_archivo,
                    d.ruta,
                    d.nombre AS doc_nombre,
                    CONCAT(rev.nombre, ' ', rev.apellido_paterno) AS revisado_por_nombre
                FROM solicitudes_actualizacion s
                INNER JOIN usuarios u              ON u.id_usuarios    = s.id_usuarios
                LEFT  JOIN documentos_subidos d    ON d.id_documento    = s.id_documento
                LEFT  JOIN grados_academicos ga    ON ga.id_grado       = s.valor_nuevo_id
                LEFT  JOIN grados_academicos ga_act ON ga_act.id_grado  = s.valor_actual_id
                LEFT  JOIN usuarios rev            ON rev.id_usuarios   = s.id_usuarios
                WHERE s.id_solicitudes_actualizacion = ? AND s.tipo = 'grado'";
        $stmt = $this->con->prepare($sql);
        if (!$stmt) die("Error prepare detalle Grado: " . $this->con->error);
        $stmt->bind_param("i", $id_solicitud);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    // 
    //  HISTORIAL DE UNA SOLICITUD (supervisor)
    // 

    public function historialDeSolicitud($id_solicitud)
    {
        $sql = "SELECT
                    h.estado_anterior,
                    h.estado_nuevo,
                    h.comentario,
                    h.fecha,
                    CONCAT(u.nombre, ' ', u.apellido_paterno) AS usuario_accion
                FROM historial_solicitudes_actualizacion h
                LEFT JOIN usuarios u ON u.id_usuarios = h.id_usuario_accion
                WHERE h.id_solicitudes_actualizacion = ?
                ORDER BY h.fecha DESC";
        $stmt = $this->con->prepare($sql);
        if (!$stmt) die("Error prepare historial solicitud Grado: " . $this->con->error);
        $stmt->bind_param("i", $id_solicitud);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    // 
    //  APROBAR SOLICITUD (supervisor) — TRANSACCIONAL
    // 

    public function aprobarSolicitud($id_solicitud, $id_supervisor)
    {
        $detalle = $this->obtenerDetalle($id_solicitud);
        if (empty($detalle) || $detalle['estado'] !== 'pendiente') {
            return ['ok' => false, 'msg' => 'La solicitud ya no está pendiente.'];
        }

        $id_usuario  = (int)$detalle['id_usuarios'];
        $valor_nuevo = (int)$detalle['valor_nuevo_id'];

        $this->con->begin_transaction();
        try {
            // 1. Actualizar grado académico del investigador
            $stmt = $this->con->prepare("UPDATE investigadores SET id_grado = ? WHERE id_usuarios = ?");
            $stmt->bind_param("ii", $valor_nuevo, $id_usuario);
            if (!$stmt->execute()) throw new Exception("Error actualizando grado académico: " . $stmt->error);

            // 2. Actualizar solicitud
            $stmt2 = $this->con->prepare(
                "UPDATE solicitudes_actualizacion
                 SET estado = 'aprobado', id_usuario_accion = ?, fecha_respuesta = NOW()
                 WHERE id_solicitudes_actualizacion = ? AND estado = 'pendiente'"
            );
            $stmt2->bind_param("ii", $id_supervisor, $id_solicitud);
            if (!$stmt2->execute() || $stmt2->affected_rows === 0)
                throw new Exception("Error actualizando solicitud de grado.");

            // 3. Historial
            $this->insertarHistorial($id_solicitud, $id_supervisor, 'pendiente', 'aprobado', 'Grado académico validado y aprobado por supervisor.');

            $this->con->commit();
            return ['ok' => true, 'msg' => 'Solicitud de grado académico aprobada correctamente.'];
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
            $stmt = $this->con->prepare(
                "UPDATE solicitudes_actualizacion
                 SET estado = 'rechazado', id_usuario_accion = ?, fecha_respuesta = NOW()
                 WHERE id_solicitudes_actualizacion = ? AND estado = 'pendiente'"
            );
            $stmt->bind_param("ii", $id_supervisor, $id_solicitud);
            if (!$stmt->execute() || $stmt->affected_rows === 0)
                throw new Exception("Error rechazando solicitud de grado.");

            $this->insertarHistorial($id_solicitud, $id_supervisor, 'pendiente', 'rechazado', $comentario);

            $this->con->commit();
            return ['ok' => true, 'msg' => 'Solicitud de grado académico rechazada.', 'detalle' => $detalle];
        } catch (Exception $e) {
            $this->con->rollback();
            return ['ok' => false, 'msg' => $e->getMessage()];
        }
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

    // 
    //  HELPER: INSERTAR EN HISTORIAL
    // 

    private function insertarHistorial($id_solicitud, $id_usuario_accion, $estado_anterior, $estado_nuevo, $comentario)
    {
        $sql = "INSERT INTO historial_solicitudes_actualizacion
                    (id_solicitudes_actualizacion, id_usuario_accion, estado_anterior, estado_nuevo, comentario)
                VALUES (?, ?, ?, ?, ?)";
        $stmt = $this->con->prepare($sql);
        if (!$stmt) die("Error prepare historial Grado: " . $this->con->error);
        $stmt->bind_param("iisss", $id_solicitud, $id_usuario_accion, $estado_anterior, $estado_nuevo, $comentario);
        $stmt->execute();
    }
}
