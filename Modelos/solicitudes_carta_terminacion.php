<?php
// Modelos/cartaTerminacion.php
// Modelo para el módulo de Solicitudes de Carta de Terminación (supervisor)

require_once __DIR__ . '/../publico/config/conexion.php';

class solicitudes_carta_terminacion
{
    private $con;

    public function __construct($conn)
    {
        $this->con = $conn;
    }

    // 
    // HELPER INTERNO
    // 

    private function ejecutar($sql, $types = "", $params = [], $fetchAll = true)
    {
        $stmt = $this->con->prepare($sql);
        if (!$stmt) {
            throw new Exception("Error en prepare(): " . $this->con->error);
        }
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        if (!$stmt->execute()) {
            throw new Exception("Error en execute(): " . $stmt->error);
        }
        if (stripos(trim($sql), "SELECT") === 0) {
            $result = $stmt->get_result();
            return $fetchAll ? $result->fetch_all(MYSQLI_ASSOC) : $result->fetch_assoc();
        }
        return true;
    }

    // 
    // CATÁLOGOS
    // 

    /**
     * Todos los periodos para el filtro
     */
    public function obtenerTodosPeriodos()
    {
        $sql = "SELECT id_periodos, periodo FROM periodos ORDER BY periodo DESC";
        return $this->ejecutar($sql);
    }

    // 
    // RESUMEN — tarjetas del dashboard
    // 

    /**
     * Conteos para las tarjetas superiores del supervisor
     * Basado en cierres_estudiante vinculado a proyectos_usuarios
     */
    public function resumenCartas($id_periodo = 0)
    {
        $where_periodo = $id_periodo
            ? " AND p.id_periodos = " . intval($id_periodo)
            : "";

        $sql = "SELECT
            COUNT(*)                                                        AS total,
            SUM(CASE WHEN ce.estado = 'pendiente'  THEN 1 ELSE 0 END)      AS pendientes,
            SUM(CASE WHEN ce.estado = 'aprobado'   THEN 1 ELSE 0 END)      AS aprobadas,
            SUM(CASE WHEN ce.estado = 'rechazado'  THEN 1 ELSE 0 END)      AS rechazadas
        FROM cierres_estudiante ce
        JOIN proyectos_usuarios pu ON pu.id_integrante = ce.id_integrante
        JOIN proyectos p           ON p.id_proyectos   = pu.id_proyectos
        WHERE 1 $where_periodo";

        return $this->ejecutar($sql, "", [], false) ?? [
            'total' => 0, 'pendientes' => 0, 'aprobadas' => 0, 'rechazadas' => 0
        ];
    }

    // 
    // LISTADO PAGINADO
    // 

    /**
     * Lista paginada de solicitudes de carta de terminación
     * tipo_filtro: Todas | Pendientes | Aprobadas | Rechazadas
     */
    public function listarCartas($tipo_filtro = 'Todas', $buscar = '', $pagina = 1, $id_periodo = 0)
    {
        $por_pagina = 6;
        $pagina     = max(1, intval($pagina));
        $desde      = ($pagina - 1) * $por_pagina;

        // Condición de estado según filtro
        $where_estado = match ($tipo_filtro) {
            'Pendientes' => "AND ce.estado = 'pendiente'",
            'Aprobadas'  => "AND ce.estado = 'aprobado'",
            'Rechazadas' => "AND ce.estado = 'rechazado'",
            default      => "",
        };

        $where_periodo = $id_periodo ? "AND p.id_periodos = ?" : "";
        $where_buscar  = !empty($buscar) ? "AND p.titulo LIKE ?" : "";

        $base_where = "WHERE 1 $where_estado $where_periodo $where_buscar";

        // -- TOTAL --
        $sql_total = "SELECT COUNT(*) AS total
            FROM cierres_estudiante ce
            JOIN proyectos_usuarios pu ON pu.id_integrante  = ce.id_integrante
            JOIN proyectos p           ON p.id_proyectos    = pu.id_proyectos
            JOIN periodos per          ON per.id_periodos   = p.id_periodos
            $base_where";

        $params_t = [];
        $types_t  = "";
        if ($id_periodo)   { $params_t[] = $id_periodo; $types_t .= "i"; }
        if (!empty($buscar)) { $params_t[] = "%$buscar%"; $types_t .= "s"; }

        $total_row     = $this->ejecutar($sql_total, $types_t, $params_t, false);
        $total         = $total_row['total'] ?? 0;
        $total_paginas = max(1, ceil($total / $por_pagina));

        // -- DATOS --
        $sql = "SELECT
            ce.id_cierre_est,
            ce.estado                                                               AS estado_carta,
            ce.fecha_solicitud,
            ce.fecha_respuesta,
            pu.id_integrante,
            pu.id_usuarios,
            p.id_proyectos,
            p.titulo                                                                AS titulo_proyecto,
            per.periodo,
            CONCAT(u.nombre,' ',u.apellido_paterno,' ',u.apellido_materno)          AS estudiante,
            CONCAT(ui.nombre,' ',ui.apellido_paterno,' ',ui.apellido_materno)       AS investigador,
            ep.estado                                                               AS estado_proceso,
            ds.nombre                                                               AS nombre_documento,
            ds.ruta                                                                 AS ruta_documento,
            ds.extension                                                            AS extension_documento
        FROM cierres_estudiante ce
        JOIN proyectos_usuarios pu  ON pu.id_integrante    = ce.id_integrante
        JOIN proyectos p            ON p.id_proyectos      = pu.id_proyectos
        JOIN periodos per           ON per.id_periodos     = p.id_periodos
        JOIN usuarios u             ON u.id_usuarios       = pu.id_usuarios
        JOIN usuarios ui            ON ui.id_usuarios      = p.id_investigador
        JOIN estados_proceso ep     ON ep.id_estados_proceso = pu.id_estados_proceso
        JOIN documentos_subidos ds  ON ds.id_documento     = ce.id_documento
        $base_where
        ORDER BY
            FIELD(ce.estado, 'pendiente', 'rechazado', 'aprobado'),
            ce.fecha_solicitud DESC
        LIMIT ?, ?";

        $params = $params_t;
        $types  = $types_t;
        $params[] = $desde;
        $params[] = $por_pagina;
        $types   .= "ii";

        $data = $this->ejecutar($sql, $types, $params);

        return [
            "solicitudes" => $data,
            "paginacion"  => [
                "total"         => $total,
                "por_pagina"    => $por_pagina,
                "pagina"        => $pagina,
                "total_paginas" => $total_paginas,
            ]
        ];
    }

    // 
    // DETALLE DE UNA SOLICITUD
    // 

    public function detalleCarta($id_cierre_est)
    {
        $sql = "SELECT
            ce.id_cierre_est,
            ce.estado                                                               AS estado_carta,
            ce.comentarios,
            ce.fecha_solicitud,
            ce.fecha_respuesta,
            pu.id_integrante,
            pu.id_usuarios,
            pu.estado                                                               AS estado_integrante,
            pu.fecha_asignacion,
            pu.fecha_terminacion,
            p.id_proyectos,
            p.titulo                                                                AS titulo_proyecto,
            p.descripcion,
            p.fecha_inicio,
            p.fecha_fin,
            per.periodo,
            CASE
                WHEN CURDATE() BETWEEN per.fecha_inicio AND per.fecha_final THEN 'Activo'
                WHEN CURDATE() < per.fecha_inicio THEN 'Pendiente'
                ELSE 'Terminado'
            END                                                                     AS estado_periodo,
            CONCAT(u.nombre,' ',u.apellido_paterno,' ',u.apellido_materno)          AS estudiante,
            u.nombre                                                                AS nombre_estudiante,
            CONCAT(ui.nombre,' ',ui.apellido_paterno,' ',ui.apellido_materno)       AS investigador,
            ep.estado                                                               AS estado_proceso,
            ep.id_estados_proceso,
            ds.id_documento,
            ds.nombre                                                               AS nombre_documento,
            ds.ruta                                                                 AS ruta_documento,
            ds.extension                                                            AS extension_documento,
            ds.tamano_bytes,
            ds.fecha_subida
        FROM cierres_estudiante ce
        JOIN proyectos_usuarios pu  ON pu.id_integrante      = ce.id_integrante
        JOIN proyectos p            ON p.id_proyectos        = pu.id_proyectos
        JOIN periodos per           ON per.id_periodos       = p.id_periodos
        JOIN usuarios u             ON u.id_usuarios         = pu.id_usuarios
        JOIN usuarios ui            ON ui.id_usuarios        = p.id_investigador
        JOIN estados_proceso ep     ON ep.id_estados_proceso = pu.id_estados_proceso
        JOIN documentos_subidos ds  ON ds.id_documento       = ce.id_documento
        WHERE ce.id_cierre_est = ?
        LIMIT 1";

        return $this->ejecutar($sql, "i", [$id_cierre_est], false);
    }

    // 
    // HISTORIAL DEL PROCESO DEL ESTUDIANTE
    // 

    /**
     * Historial de acciones del estudiante en el proyecto
     * Combina historial_proyectos_usuarios con cambios de estado_proceso
     */
    public function historialProceso($id_proyectos, $id_usuarios)
    {
        // Historial general (bajas, reactivaciones, conclusiones)
        $sql = "SELECT
            h.fecha,
            CASE h.accion
                WHEN 'baja'       THEN 'Baja del proyecto'
                WHEN 'reactivado' THEN 'Reactivado al proyecto'
                WHEN 'concluido'  THEN 'Proceso concluido'
                WHEN 'vencido'    THEN 'Proyecto vencido'
                ELSE h.accion
            END                                                             AS evento,
            h.motivo,
            CASE
                WHEN h.realizado_por = 0 THEN 'Sistema'
                ELSE CONCAT(ur.nombre,' ',ur.apellido_paterno)
            END                                                             AS realizado_por,
            CASE h.accion
                WHEN 'concluido'  THEN 'success'
                WHEN 'baja'       THEN 'danger'
                WHEN 'reactivado' THEN 'warning'
                WHEN 'vencido'    THEN 'dark'
                ELSE 'secondary'
            END                                                             AS badge_color
        FROM historial_proyectos_usuarios h
        LEFT JOIN usuarios ur ON ur.id_usuarios = h.realizado_por
        WHERE h.id_proyectos = ? AND h.id_estudiante = ?

        UNION ALL

        -- Evento de carta subida (cuando se creó el registro en cierres_estudiante)
        SELECT
            ce.fecha_solicitud                                              AS fecha,
            'Carta de terminación subida'                                   AS evento,
            CONCAT('Archivo: ', ds.nombre)                                  AS motivo,
            CONCAT(u.nombre,' ',u.apellido_paterno)                         AS realizado_por,
            'info'                                                          AS badge_color
        FROM cierres_estudiante ce
        JOIN proyectos_usuarios pu ON pu.id_integrante = ce.id_integrante
        JOIN documentos_subidos ds ON ds.id_documento  = ce.id_documento
        JOIN usuarios u            ON u.id_usuarios    = pu.id_usuarios
        WHERE pu.id_proyectos = ? AND pu.id_usuarios = ?

        UNION ALL

        -- Evento de respuesta del supervisor (aprobado / rechazado)
        SELECT
            ce.fecha_respuesta                                              AS fecha,
            CASE ce.estado
                WHEN 'aprobado'  THEN 'Carta aprobada por supervisor'
                WHEN 'rechazado' THEN 'Carta rechazada por supervisor'
                ELSE 'Revisión del supervisor'
            END                                                             AS evento,
            ce.comentarios                                                  AS motivo,
            CONCAT(us.nombre,' ',us.apellido_paterno)                       AS realizado_por,
            CASE ce.estado
                WHEN 'aprobado'  THEN 'success'
                WHEN 'rechazado' THEN 'danger'
                ELSE 'secondary'
            END                                                             AS badge_color
        FROM cierres_estudiante ce
        JOIN proyectos_usuarios pu  ON pu.id_integrante = ce.id_integrante
        JOIN supervisores sv        ON sv.id_usuarios   = ce.id_supervisor
        JOIN usuarios us            ON us.id_usuarios   = sv.id_usuarios
        WHERE pu.id_proyectos = ? AND pu.id_usuarios = ?
          AND ce.fecha_respuesta IS NOT NULL

        ORDER BY fecha ASC";

        return $this->ejecutar($sql, "iiiiii", [
            $id_proyectos, $id_usuarios,
            $id_proyectos, $id_usuarios,
            $id_proyectos, $id_usuarios
        ]);
    }

    // 
    // APROBAR CARTA
    // 

    public function aprobarCarta($id_cierre_est, $id_supervisor)
    {
        $this->con->begin_transaction();
        try {
            // 1. Verificar que existe y está pendiente
            $check = $this->ejecutar(
                "SELECT ce.id_cierre_est, ce.estado, pu.id_integrante, pu.id_proyectos, pu.id_usuarios
                 FROM cierres_estudiante ce
                 JOIN proyectos_usuarios pu ON pu.id_integrante = ce.id_integrante
                 WHERE ce.id_cierre_est = ? AND ce.id_supervisor = ?",
                "ii", [$id_cierre_est, $id_supervisor], false
            );

            if (!$check) {
                throw new Exception("Solicitud no encontrada o sin acceso.");
            }
            if ($check['estado'] !== 'pendiente') {
                throw new Exception("Esta solicitud ya fue procesada.");
            }

            $id_integrante = $check['id_integrante'];
            $id_proyectos  = $check['id_proyectos'];
            $id_usuarios   = $check['id_usuarios'];

            // 2. Actualizar cierres_estudiante → aprobado
            $this->ejecutar(
                "UPDATE cierres_estudiante
                 SET estado = 'aprobado', fecha_respuesta = CURDATE()
                 WHERE id_cierre_est = ?",
                "i", [$id_cierre_est]
            );

            // 3. Actualizar proyectos_usuarios → concluido + estado_proceso liberado_supervisor(2) → luego concluido(3)
            //    Usamos id_estados_proceso = 3 (concluido) y estado = 'concluido'
            $this->ejecutar(
                "UPDATE proyectos_usuarios
                 SET estado = 'concluido',
                     fecha_terminacion = CURDATE(),
                     id_estados_proceso = (SELECT id_estados_proceso FROM estados_proceso WHERE estado = 'concluido' LIMIT 1)
                 WHERE id_integrante = ?",
                "i", [$id_integrante]
            );

            // 4. Registrar en historial_proyectos_usuarios
            $this->ejecutar(
                "INSERT INTO historial_proyectos_usuarios
                    (id_proyectos, id_estudiante, accion, motivo, realizado_por, fecha)
                 VALUES (?, ?, 'concluido', 'Carta de terminación aprobada por supervisor', ?, NOW())",
                "iii", [$id_proyectos, $id_usuarios, $id_supervisor]
            );

            // 5. Notificar al estudiante
            $this->ejecutar(
                "INSERT INTO notificaciones (id_usuarios, titulo, contenido, enlace)
                 VALUES (?, 'Carta de terminación aprobada',
                    'Tu carta de terminación fue aprobada. Tu participación en el proyecto ha concluido oficialmente.',
                    '/ITSFCP-PROYECTOS/Vistas/Estudiante/seguimiento.php')",
                "i", [$id_usuarios]
            );

            $this->con->commit();
            return ["success" => true];
        } catch (Throwable $e) {
            $this->con->rollback();
            return ["success" => false, "msg" => $e->getMessage()];
        }
    }

    // 
    // RECHAZAR CARTA
    // 

    public function rechazarCarta($id_cierre_est, $id_supervisor, $comentario)
    {
        $this->con->begin_transaction();
        try {
            // 1. Verificar
            $check = $this->ejecutar(
                "SELECT ce.id_cierre_est, ce.estado, pu.id_integrante, pu.id_proyectos, pu.id_usuarios
                 FROM cierres_estudiante ce
                 JOIN proyectos_usuarios pu ON pu.id_integrante = ce.id_integrante
                 WHERE ce.id_cierre_est = ? AND ce.id_supervisor = ?",
                "ii", [$id_cierre_est, $id_supervisor], false
            );

            if (!$check) {
                throw new Exception("Solicitud no encontrada o sin acceso.");
            }
            if ($check['estado'] !== 'pendiente') {
                throw new Exception("Esta solicitud ya fue procesada.");
            }

            $id_integrante = $check['id_integrante'];
            $id_proyectos  = $check['id_proyectos'];
            $id_usuarios   = $check['id_usuarios'];

            // 2. Actualizar cierres_estudiante → rechazado con comentario
            $this->ejecutar(
                "UPDATE cierres_estudiante
                 SET estado = 'rechazado', fecha_respuesta = CURDATE(), comentarios = ?
                 WHERE id_cierre_est = ?",
                "si", [$comentario, $id_cierre_est]
            );

            // 3. Revertir estado_proceso a carta_subida (1) para que el estudiante pueda reenviar
            $this->ejecutar(
                "UPDATE proyectos_usuarios
                 SET id_estados_proceso = (SELECT id_estados_proceso FROM estados_proceso WHERE estado = 'carta_subida' LIMIT 1)
                 WHERE id_integrante = ?",
                "i", [$id_integrante]
            );

            // 4. Historial
            $this->ejecutar(
                "INSERT INTO historial_proyectos_usuarios
                    (id_proyectos, id_estudiante, accion, motivo, realizado_por, fecha)
                 VALUES (?, ?, 'baja', ?, ?, NOW())",
                "iisi", [$id_proyectos, $id_usuarios, 'Carta de terminación rechazada: ' . $comentario, $id_supervisor]
            );

            // 5. Notificar al estudiante
            $this->ejecutar(
                "INSERT INTO notificaciones (id_usuarios, titulo, contenido, enlace)
                 VALUES (?, 'Carta de terminación rechazada',
                    'Tu carta de terminación fue rechazada. Revisa los comentarios del supervisor y vuelve a subir el documento.',
                    '/ITSFCP-PROYECTOS/Vistas/Estudiante/seguimiento.php')",
                "i", [$id_usuarios]
            );

            $this->con->commit();
            return ["success" => true];
        } catch (Throwable $e) {
            $this->con->rollback();
            return ["success" => false, "msg" => $e->getMessage()];
        }
    }

    // 
    // ESTILO BADGE SEGÚN ESTADO DE LA CARTA
    // 

    public function estiloCarta($estado)
    {
        return match ($estado) {
            'aprobado'  => 'success',
            'rechazado' => 'danger',
            'pendiente' => 'warning',
            default     => 'secondary',
        };
    }

    public function etiquetaCarta($estado)
    {
        return match ($estado) {
            'aprobado'  => 'Aprobada',
            'rechazado' => 'Rechazada',
            'pendiente' => 'Pendiente',
            default     => ucfirst($estado),
        };
    }
}