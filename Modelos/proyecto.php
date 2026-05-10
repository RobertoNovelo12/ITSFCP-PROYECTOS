<?php
//Modelo de proyectos -> Maneja sobre proyectos y solicitudes de integración a proyectos

require_once __DIR__ . '/../publico/config/conexion.php';

class Proyectos
{

    private $con;

    public function __construct($conn)
    {
        $this->con = $conn;
    }

    // =========================================================
    // HELPER INTERNO
    // =========================================================

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

    // =========================================================
    // MANTENIMIENTO AUTOMÁTICO DE ESTADOS
    // =========================================================

    public function actualizarProyectosVencidos()
    {
        $sql = "UPDATE proyectos 
                SET id_estadoP = 6
                WHERE id_estadoP IN (2,3,4,5,7)
                AND fecha_fin < CURDATE()";
        return $this->ejecutar($sql, "", []);
    }

    public function actualizarEstadoEstudiantesVencidos()
    {
        // 1. Historial de baja por vencimiento
        $sql_historial_baja = "
            INSERT INTO historial_proyectos_usuarios 
                (id_proyectos, id_estudiante, accion, motivo, realizado_por, fecha)
            SELECT pu.id_proyectos, pu.id_usuarios, 'baja', 'Proyecto vencido', 0, NOW()
            FROM proyectos_usuarios pu
            JOIN proyectos p ON p.id_proyectos = pu.id_proyectos
            WHERE p.fecha_fin < CURDATE()
              AND p.id_estadoP != 1
              AND pu.estado = 'activo'
              AND NOT EXISTS (
                  SELECT 1 FROM historial_proyectos_usuarios h
                  WHERE h.id_proyectos = pu.id_proyectos
                    AND h.id_estudiante = pu.id_usuarios
                    AND h.accion = 'baja'
                    AND h.motivo = 'Proyecto vencido'
              )";
        $this->ejecutar($sql_historial_baja);

        // 2. Baja por vencimiento
        $sql_update_baja = "
            UPDATE proyectos_usuarios pu
            JOIN proyectos p ON p.id_proyectos = pu.id_proyectos
            SET pu.estado = 'baja', pu.fecha_baja = NOW(), pu.motivo_baja = 'Proyecto vencido'
            WHERE p.fecha_fin < CURDATE()
              AND p.id_estadoP != 1
              AND pu.estado = 'activo'";
        $this->ejecutar($sql_update_baja);

        // 3. Historial concluido
        $sql_historial_concluido = "
            INSERT INTO historial_proyectos_usuarios 
                (id_proyectos, id_estudiante, accion, motivo, realizado_por, fecha)
            SELECT pu.id_proyectos, pu.id_usuarios, 'concluido', 'Proyecto finalizado', 0, NOW()
            FROM proyectos_usuarios pu
            JOIN proyectos p ON p.id_proyectos = pu.id_proyectos
            WHERE p.id_estadoP = 1
              AND pu.estado = 'activo'
              AND NOT EXISTS (
                  SELECT 1 FROM historial_proyectos_usuarios h
                  WHERE h.id_proyectos = pu.id_proyectos
                    AND h.id_estudiante = pu.id_usuarios
                    AND h.accion = 'concluido'
              )";
        $this->ejecutar($sql_historial_concluido);

        // 4. Concluir estudiantes
        $sql_update_concluido = "
            UPDATE proyectos_usuarios pu
            JOIN proyectos p ON p.id_proyectos = pu.id_proyectos
            SET pu.estado = 'concluido'
            WHERE p.id_estadoP = 1 AND pu.estado = 'activo'";
        return $this->ejecutar($sql_update_concluido);
    }

    // =========================================================
    // FILTROS / CONTEOS
    // =========================================================

    public function obtenerProyectosDatosFiltro($id, $rol)
    {
        switch ($rol) {
            case 'estudiante':
                $sql = "SELECT 
                    COUNT(*) AS Total,
                    COALESCE(SUM(CASE WHEN espr.nombre='Activo'      THEN 1 ELSE 0 END),0) AS Activos,
                    COALESCE(SUM(CASE WHEN espr.nombre='Por aprobar' THEN 1 ELSE 0 END),0) AS PorAprobar,
                    COALESCE(SUM(CASE WHEN espr.nombre='Cierre'      THEN 1 ELSE 0 END),0) AS Cierre,
                    COALESCE(SUM(CASE WHEN espr.nombre='Por cerrar'  THEN 1 ELSE 0 END),0) AS PorCerrar,
                    COALESCE(SUM(CASE WHEN espr.nombre='Vencido'     THEN 1 ELSE 0 END),0) AS Vencido
                FROM proyectos AS proy
                JOIN proyectos_usuarios AS prus ON proy.id_proyectos = prus.id_proyectos
                JOIN estudiantes AS estu ON prus.id_usuarios = estu.id_usuarios
                JOIN estados_proyectos AS espr ON proy.id_estadoP = espr.id_estadoP
                WHERE estu.id_usuarios = ?";
                $stmt = $this->con->prepare($sql);
                $stmt->bind_param("i", $id);
                break;

            case 'investigador':
            case 'profesor':
                $sql = "SELECT 
                    COUNT(*) AS Total,
                    COALESCE(SUM(CASE WHEN espr.nombre='Activo'           THEN 1 ELSE 0 END),0) AS Activos,
                    COALESCE(SUM(CASE WHEN espr.nombre='Por aprobar'      THEN 1 ELSE 0 END),0) AS PorAprobar,
                    COALESCE(SUM(CASE WHEN espr.nombre='Cierre'           THEN 1 ELSE 0 END),0) AS Cierre,
                    COALESCE(SUM(CASE WHEN espr.nombre='Por cerrar'       THEN 1 ELSE 0 END),0) AS PorCerrar,
                    COALESCE(SUM(CASE WHEN espr.nombre='Rechazado'        THEN 1 ELSE 0 END),0) AS Rechazados,
                    COALESCE(SUM(CASE WHEN espr.nombre='Vencido'          THEN 1 ELSE 0 END),0) AS Vencido,
                    COALESCE(SUM(CASE WHEN espr.nombre='Cierre rechazado' THEN 1 ELSE 0 END),0) AS Cierrerechazado
                FROM proyectos AS proy
                JOIN investigadores AS inv ON inv.id_usuarios = proy.id_investigador
                JOIN estados_proyectos AS espr ON proy.id_estadoP = espr.id_estadoP
                WHERE proy.id_investigador = ?";
                $stmt = $this->con->prepare($sql);
                $stmt->bind_param("i", $id);
                break;

            case 'supervisor':
                $sql = "SELECT 
                    COUNT(*) AS Total,
                    SUM(CASE WHEN espr.nombre='Activo'           THEN 1 ELSE 0 END) AS Activos,
                    SUM(CASE WHEN espr.nombre='Por aprobar'      THEN 1 ELSE 0 END) AS PorAprobar,
                    SUM(CASE WHEN espr.nombre='Cierre'           THEN 1 ELSE 0 END) AS Cierre,
                    SUM(CASE WHEN espr.nombre='Por cerrar'       THEN 1 ELSE 0 END) AS PorCerrar,
                    SUM(CASE WHEN espr.nombre='Rechazado'        THEN 1 ELSE 0 END) AS Rechazados,
                    SUM(CASE WHEN espr.nombre='Vencido'          THEN 1 ELSE 0 END) AS Vencido,
                    SUM(CASE WHEN espr.nombre='Cierre rechazado' THEN 1 ELSE 0 END) AS Cierrerechazado
                FROM proyectos AS proy
                JOIN estados_proyectos AS espr ON proy.id_estadoP = espr.id_estadoP";
                $stmt = $this->con->prepare($sql);
                break;

            default:
                return [];
        }

        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    // =========================================================
    // TABLA DE PROYECTOS (centralizado)
    // =========================================================

    public function obtenerProyectosTablaFiltro($id, $filtro, $rol, $buscar)
    {
        $rol = strtolower($rol);
        switch ($rol) {
            case 'estudiante':
                return $this->obtenerProyectosTablaEstudiante($id, $filtro, $buscar);
            case 'investigador':
            case 'profesor':
                return $this->obtenerProyectosTablaInvestigador($id, $filtro, $buscar);
            case 'supervisor':
                return $this->obtenerProyectosTablaSupervisor($filtro, $buscar);
            default:
                return json_encode([]);
        }
    }

    private function obtenerProyectosTablaEstudiante($id, $filtro, $buscar)
    {
        $por_pagina = 6;
        $pagina     = isset($_GET['pagina']) && $_GET['pagina'] > 0 ? intval($_GET['pagina']) : 1;
        $desde      = ($pagina - 1) * $por_pagina;

        $total       = $this->obtenerCantidadEstudiante($id, $filtro, $buscar);
        $total_paginas = ceil($total / $por_pagina);

        $sql = "SELECT 
            proy.id_proyectos,
            proy.titulo,
            proy.fecha_inicio,
            proy.fecha_fin,
            espr.nombre AS estado_proyecto,
            peri.periodo,
            pu.estado AS estado_estudiante,
            COALESCE(tr.total, 0) AS total
        FROM proyectos proy
        JOIN proyectos_usuarios pu ON pu.id_proyectos = proy.id_proyectos
        JOIN estados_proyectos espr ON proy.id_estadoP = espr.id_estadoP
        JOIN periodos peri ON proy.id_periodos = peri.id_periodos
        LEFT JOIN (
            SELECT ts.id_proyectos, COUNT(CASE WHEN tu.id_estadoT = 2 THEN 1 END) AS total
            FROM tbl_seguimiento ts
            JOIN tareas t ON t.id_avances = ts.id_avances
            LEFT JOIN tareas_usuarios tu ON tu.id_tarea = t.id_tarea
            GROUP BY ts.id_proyectos
        ) tr ON tr.id_proyectos = proy.id_proyectos
        WHERE pu.id_usuarios = ?";

        $params = [$id];
        $types  = "i";

        if ($filtro != 0) {
            $sql    .= " AND proy.id_estadoP = ?";
            $params[] = $filtro;
            $types  .= "i";
        }
        if (!empty($buscar)) {
            $sql    .= " AND proy.titulo LIKE ?";
            $params[] = "%$buscar%";
            $types  .= "s";
        }

        $sql    .= " ORDER BY proy.id_proyectos DESC LIMIT ?, ?";
        $params[] = $desde;
        $params[] = $por_pagina;
        $types  .= "ii";

        $data = $this->ejecutar($sql, $types, $params);
        return json_encode([
            "proyectos"  => $data,
            "paginacion" => compact("total", "por_pagina", "pagina") + ["total_paginas" => $total_paginas]
        ]);
    }

    private function obtenerProyectosTablaInvestigador($id, $filtro, $buscar)
    {
        $por_pagina    = 6;
        $pagina        = isset($_GET['pagina']) && $_GET['pagina'] > 0 ? intval($_GET['pagina']) : 1;
        $desde         = ($pagina - 1) * $por_pagina;

        $total         = $this->obtenerCantidadInvestigador($id, $filtro, $buscar);
        $total_paginas = ceil($total / $por_pagina);

        $sql = "SELECT 
            proy.id_proyectos,
            proy.titulo,
            proy.fecha_inicio,
            proy.fecha_fin,
            espr.nombre AS estado_proyecto,
            peri.periodo,
            COALESCE(tr.total, 0) AS total,
            CASE 
                WHEN COALESCE(pa.total_alumnos,0) > 0
                AND  COALESCE(tt.total_tareas,0) >= 11
                AND  COALESCE(tc.tareas_completadas,0) = (COALESCE(tt.total_tareas,0) * COALESCE(pa.total_alumnos,0))
                THEN 1 ELSE 0 
            END AS puede_cerrar
        FROM proyectos proy
        JOIN estados_proyectos espr ON proy.id_estadoP = espr.id_estadoP
        JOIN periodos peri ON proy.id_periodos = peri.id_periodos
        LEFT JOIN (
            SELECT ts.id_proyectos, COUNT(CASE WHEN tu.id_estadoT = 2 THEN 1 END) AS total
            FROM tbl_seguimiento ts
            JOIN tareas t ON t.id_avances = ts.id_avances
            LEFT JOIN tareas_usuarios tu ON tu.id_tarea = t.id_tarea
            GROUP BY ts.id_proyectos
        ) tr ON tr.id_proyectos = proy.id_proyectos
        LEFT JOIN (
            SELECT id_proyectos, COUNT(*) total_alumnos
            FROM proyectos_usuarios WHERE estado = 'activo' GROUP BY id_proyectos
        ) pa ON pa.id_proyectos = proy.id_proyectos
        LEFT JOIN (
            SELECT ts.id_proyectos, COUNT(DISTINCT t.id_tarea) total_tareas
            FROM tbl_seguimiento ts JOIN tareas t ON t.id_avances = ts.id_avances
            GROUP BY ts.id_proyectos
        ) tt ON tt.id_proyectos = proy.id_proyectos
        LEFT JOIN (
            SELECT ts.id_proyectos, COUNT(*) tareas_completadas
            FROM tbl_seguimiento ts
            JOIN tareas t ON t.id_avances = ts.id_avances
            JOIN tareas_usuarios tu ON tu.id_tarea = t.id_tarea
            JOIN proyectos_usuarios pu ON pu.id_usuarios = tu.id_usuarios
            WHERE tu.id_estadoT = 5 AND pu.estado = 'activo'
            GROUP BY ts.id_proyectos
        ) tc ON tc.id_proyectos = proy.id_proyectos
        WHERE proy.id_investigador = ?";

        $params = [$id];
        $types  = "i";

        if ($filtro != 0) {
            $sql    .= " AND proy.id_estadoP = ?";
            $params[] = $filtro;
            $types  .= "i";
        }
        if (!empty($buscar)) {
            $sql    .= " AND proy.titulo LIKE ?";
            $params[] = "%$buscar%";
            $types  .= "s";
        }

        $sql    .= " ORDER BY proy.id_proyectos DESC LIMIT ?, ?";
        $params[] = $desde;
        $params[] = $por_pagina;
        $types  .= "ii";

        $data = $this->ejecutar($sql, $types, $params);
        return json_encode([
            "proyectos"  => $data,
            "paginacion" => compact("total", "por_pagina", "pagina") + ["total_paginas" => $total_paginas]
        ]);
    }

    private function obtenerProyectosTablaSupervisor($filtro, $buscar)
    {
        $por_pagina = 6;
        $pagina     = isset($_GET['pagina']) && $_GET['pagina'] > 0 ? intval($_GET['pagina']) : 1;
        $desde      = ($pagina - 1) * $por_pagina;

        // Total
        $sql_total    = "SELECT COUNT(*) as total FROM proyectos proy WHERE 1";
        $params_total = [];
        $types_total  = "";

        // El supervisor en /proyectos/index.php solo ve aprobados (estados 2,5,1,6,7)
        // excluye Por aprobar (3) y Rechazados (4) que van a Solicitudes
        $excluir = "AND proy.id_estadoP NOT IN (3, 4)";
        $sql_total .= " $excluir";

        if ($filtro != 0) {
            $sql_total  .= " AND proy.id_estadoP = ?";
            $params_total[] = $filtro;
            $types_total  .= "i";
        }
        if (!empty($buscar)) {
            $sql_total  .= " AND proy.titulo LIKE ?";
            $params_total[] = "%$buscar%";
            $types_total  .= "s";
        }

        $total_result  = $this->ejecutar($sql_total, $types_total, $params_total);
        $total         = $total_result[0]['total'] ?? 0;
        $total_paginas = ceil($total / $por_pagina);

        $sql = "SELECT 
            proy.id_proyectos,
            proy.titulo,
            proy.fecha_inicio,
            proy.fecha_fin,
            espr.nombre AS estado_proyecto,
            peri.periodo,
            COALESCE(tr.total, 0) AS total,
            CASE 
                WHEN COALESCE(pa.total_alumnos,0) > 0
                AND  COALESCE(tt.total_tareas,0) >= 11
                AND  COALESCE(tc.tareas_completadas,0) = (COALESCE(tt.total_tareas,0) * COALESCE(pa.total_alumnos,0))
                THEN 1 ELSE 0 
            END AS puede_cerrar
        FROM proyectos proy
        JOIN estados_proyectos espr ON proy.id_estadoP = espr.id_estadoP
        JOIN periodos peri ON proy.id_periodos = peri.id_periodos
        LEFT JOIN (
            SELECT ts.id_proyectos, COUNT(CASE WHEN tu.id_estadoT = 2 THEN 1 END) AS total
            FROM tbl_seguimiento ts
            JOIN tareas t ON t.id_avances = ts.id_avances
            LEFT JOIN tareas_usuarios tu ON tu.id_tarea = t.id_tarea
            GROUP BY ts.id_proyectos
        ) tr ON tr.id_proyectos = proy.id_proyectos
        LEFT JOIN (
            SELECT id_proyectos, COUNT(*) total_alumnos
            FROM proyectos_usuarios WHERE estado = 'activo' GROUP BY id_proyectos
        ) pa ON pa.id_proyectos = proy.id_proyectos
        LEFT JOIN (
            SELECT ts.id_proyectos, COUNT(DISTINCT t.id_tarea) total_tareas
            FROM tbl_seguimiento ts JOIN tareas t ON t.id_avances = ts.id_avances
            GROUP BY ts.id_proyectos
        ) tt ON tt.id_proyectos = proy.id_proyectos
        LEFT JOIN (
            SELECT ts.id_proyectos, COUNT(*) tareas_completadas
            FROM tbl_seguimiento ts
            JOIN tareas t ON t.id_avances = ts.id_avances
            JOIN tareas_usuarios tu ON tu.id_tarea = t.id_tarea
            JOIN proyectos_usuarios pu ON pu.id_usuarios = tu.id_usuarios
            WHERE tu.id_estadoT = 5 AND pu.estado = 'activo'
            GROUP BY ts.id_proyectos
        ) tc ON tc.id_proyectos = proy.id_proyectos
        WHERE 1 $excluir";

        $params = [];
        $types  = "";

        if ($filtro != 0) {
            $sql    .= " AND proy.id_estadoP = ?";
            $params[] = $filtro;
            $types  .= "i";
        }
        if (!empty($buscar)) {
            $sql    .= " AND proy.titulo LIKE ?";
            $params[] = "%$buscar%";
            $types  .= "s";
        }

        $sql    .= " ORDER BY proy.id_proyectos DESC LIMIT ?, ?";
        $params[] = $desde;
        $params[] = $por_pagina;
        $types  .= "ii";

        $data = $this->ejecutar($sql, $types, $params);
        return json_encode([
            "proyectos"  => $data,
            "paginacion" => compact("total", "por_pagina", "pagina") + ["total_paginas" => $total_paginas]
        ]);
    }

    // Contadores internos
    private function obtenerCantidadEstudiante($id, $filtro, $buscar)
    {
        $sql    = "SELECT COUNT(*) total FROM proyectos proy
                   JOIN proyectos_usuarios pu ON pu.id_proyectos = proy.id_proyectos
                   WHERE pu.id_usuarios = ?";
        $params = [$id];
        $types  = "i";

        if ($filtro != 0) { $sql .= " AND proy.id_estadoP = ?"; $params[] = $filtro; $types .= "i"; }
        if (!empty($buscar)) { $sql .= " AND proy.titulo LIKE ?"; $params[] = "%$buscar%"; $types .= "s"; }

        return $this->ejecutar($sql, $types, $params, false)['total'] ?? 0;
    }

    private function obtenerCantidadInvestigador($id, $filtro, $buscar)
    {
        $sql    = "SELECT COUNT(*) total FROM proyectos WHERE id_investigador = ?";
        $params = [$id];
        $types  = "i";

        if ($filtro != 0) { $sql .= " AND id_estadoP = ?"; $params[] = $filtro; $types .= "i"; }
        if (!empty($buscar)) { $sql .= " AND titulo LIKE ?"; $params[] = "%$buscar%"; $types .= "s"; }

        return $this->ejecutar($sql, $types, $params, false)['total'] ?? 0;
    }

    // =========================================================
    // MÓDULO SOLICITUDES
    // =========================================================

    /**
     * Resumen de conteos para el bloque de tarjetas en solicitudes/index.php
     */
    public function resumenSolicitudes($rol, $id_usuario, $id_periodo = 0)
    {
        $where_periodo = $id_periodo ? " AND proy.id_periodos = $id_periodo" : "";

        if ($rol === 'supervisor') {
            // Supervisor ve todas las solicitudes
            $sql = "SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN espr.nombre = 'Por aprobar' THEN 1 ELSE 0 END) AS pendientes_creacion,
                SUM(CASE WHEN espr.nombre = 'Por cerrar'  THEN 1 ELSE 0 END) AS pendientes_cierre,
                SUM(CASE WHEN espr.nombre IN ('Activo','Cierre') THEN 1 ELSE 0 END) AS aprobadas
            FROM proyectos proy
            JOIN estados_proyectos espr ON proy.id_estadoP = espr.id_estadoP
            WHERE proy.id_estadoP IN (3, 5, 2, 1)
            $where_periodo";

            $stmt = $this->con->prepare($sql);
            $stmt->execute();
        } else {
            // Investigador/Profesor ven sus propias solicitudes
            $sql = "SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN espr.nombre = 'Por aprobar'      THEN 1 ELSE 0 END) AS pendientes_creacion,
                SUM(CASE WHEN espr.nombre = 'Por cerrar'       THEN 1 ELSE 0 END) AS pendientes_cierre,
                SUM(CASE WHEN espr.nombre IN ('Activo','Cierre') THEN 1 ELSE 0 END) AS aprobadas
            FROM proyectos proy
            JOIN estados_proyectos espr ON proy.id_estadoP = espr.id_estadoP
            WHERE proy.id_investigador = ?
              AND proy.id_estadoP IN (3, 4, 5, 7, 2, 1)
            $where_periodo";

            $stmt = $this->con->prepare($sql);
            $stmt->bind_param("i", $id_usuario);
            $stmt->execute();
        }

        return $stmt->get_result()->fetch_assoc() ?? [
            'total' => 0, 'pendientes_creacion' => 0, 'pendientes_cierre' => 0, 'aprobadas' => 0
        ];
    }

    /**
     * Listado paginado de solicitudes para solicitudes/index.php
     *
     * tipo_filtro: Todas | Creacion | Cierre | Pendientes
     * Los proyectos de tipo "Creación" son los que están en estado Por aprobar (3) o Rechazado (4)
     * Los proyectos de tipo "Cierre" son Por cerrar (5), Cierre rechazado (7), Cierre (1)
     * "Pendientes" = Por aprobar + Por cerrar
     */
    public function listarSolicitudes($rol, $id_usuario, $tipo_filtro = 'Todas', $buscar = '', $pagina = 1, $id_periodo = 0)
    {
        $por_pagina = 6;
        $pagina     = max(1, intval($pagina));
        $desde      = ($pagina - 1) * $por_pagina;

        // Determinar estados según tipo de filtro
        $estados_creacion  = [3, 4];       // Por aprobar, Rechazado
        $estados_cierre    = [5, 7, 1];    // Por cerrar, Cierre rechazado, Cierre
        $estados_pendientes = [3, 5];      // Por aprobar, Por cerrar
        $estados_todos      = [3, 4, 5, 7, 1, 2]; // Todo excepto Vencido (6)

        switch ($tipo_filtro) {
            case 'Creacion':   $estados = $estados_creacion;   break;
            case 'Cierre':     $estados = $estados_cierre;     break;
            case 'Pendientes': $estados = $estados_pendientes; break;
            default:           $estados = $estados_todos;      break;
        }

        // Supervisor ve todos; investigador/profesor solo los suyos
        $where_rol  = ($rol === 'supervisor') ? "" : " AND proy.id_investigador = ?";
        $base_where = "proy.id_estadoP IN (" . implode(',', $estados) . ") $where_rol";

        if ($id_periodo) {
            $base_where .= " AND proy.id_periodos = ?";
        }
        if (!empty($buscar)) {
            $base_where .= " AND proy.titulo LIKE ?";
        }

        // -- TOTAL --
        $sql_total = "SELECT COUNT(*) AS total
            FROM proyectos proy
            WHERE $base_where";

        $stmt_total = $this->con->prepare($sql_total);
        $bind_total_params = [];
        $bind_total_types  = "";

        if ($rol !== 'supervisor') {
            $bind_total_params[] = $id_usuario;
            $bind_total_types   .= "i";
        }
        if ($id_periodo) {
            $bind_total_params[] = $id_periodo;
            $bind_total_types   .= "i";
        }
        if (!empty($buscar)) {
            $bind_total_params[] = "%$buscar%";
            $bind_total_types   .= "s";
        }

        if (!empty($bind_total_params)) {
            $stmt_total->bind_param($bind_total_types, ...$bind_total_params);
        }
        $stmt_total->execute();
        $total         = $stmt_total->get_result()->fetch_assoc()['total'] ?? 0;
        $total_paginas = max(1, ceil($total / $por_pagina));

        // -- DATOS --
        $sql = "SELECT 
            proy.id_proyectos,
            proy.titulo,
            espr.nombre AS estado_proyecto,
            peri.periodo,
            CONCAT(u.nombre, ' ', u.apellido_paterno, ' ', u.apellido_materno) AS investigador,
            proy.creado_en AS fecha_solicitud,
            CASE
                WHEN proy.id_estadoP IN (3, 4) THEN 'creacion'
                ELSE 'cierre'
            END AS tipo_solicitud
        FROM proyectos proy
        JOIN estados_proyectos espr ON proy.id_estadoP = espr.id_estadoP
        JOIN periodos peri ON proy.id_periodos = peri.id_periodos
        JOIN usuarios u ON u.id_usuarios = proy.id_investigador
        WHERE $base_where
        ORDER BY proy.id_proyectos DESC
        LIMIT ?, ?";

        $stmt = $this->con->prepare($sql);
        $params = [];
        $types  = "";

        if ($rol !== 'supervisor') {
            $params[] = $id_usuario;
            $types   .= "i";
        }
        if ($id_periodo) {
            $params[] = $id_periodo;
            $types   .= "i";
        }
        if (!empty($buscar)) {
            $params[] = "%$buscar%";
            $types   .= "s";
        }
        $params[] = $desde;
        $params[] = $por_pagina;
        $types   .= "ii";

        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $solicitudes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        return json_encode([
            "solicitudes" => $solicitudes,
            "paginacion"  => compact("total", "por_pagina", "pagina") + ["total_paginas" => $total_paginas]
        ]);
    }

    // =========================================================
    // CATÁLOGOS
    // =========================================================

    public function tematica()
    {
        $sql  = "SELECT id_tematica, nombre_tematica FROM gestion_proyectos.tematica";
        $stmt = $this->con->prepare($sql);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function obtenersubtematica($id_tematica)
    {
        $sql  = "SELECT sub.id_subtematica, sub.nombre_subtematica
                 FROM gestion_proyectos.subtematica AS sub
                 JOIN tematica AS te ON sub.id_tematica = te.id_tematica
                 WHERE te.id_tematica = ? AND sub.estado = 1";
        $stmt = $this->con->prepare($sql);
        $stmt->bind_param("i", $id_tematica);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Periodo vigente (para crear proyecto)
     */
    public function obtenerperiodo()
    {
        $sql  = "SELECT id_periodos, periodo,
                    fecha_inicio AS FechaInicio,
                    fecha_final  AS FechaFinal,
                    CASE 
                        WHEN CURDATE() BETWEEN fecha_inicio AND fecha_final THEN 'Activo'
                        WHEN CURDATE() < fecha_inicio THEN 'Pendiente'
                        ELSE 'Terminado'
                    END AS estado
                 FROM periodos ORDER BY periodo DESC LIMIT 1";
        $stmt = $this->con->prepare($sql);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    /**
     * Todos los periodos (para el filtro por periodo en solicitudes)
     */
    public function obtenerTodosPeriodos()
    {
        $sql  = "SELECT id_periodos, periodo FROM periodos ORDER BY periodo DESC";
        $stmt = $this->con->prepare($sql);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function obtenerinstituto()
    {
        $sql  = "SELECT id_instituto FROM gestion_proyectos.instituto ORDER BY id_instituto DESC LIMIT 1";
        $stmt = $this->con->prepare($sql);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    // =========================================================
    // CRUD DE PROYECTOS
    // =========================================================

    public function registrarProyecto($id_investigador, $id_estadoP, $id_instituto, $id_periodos, $titulo, $descripcion, $objetivo, $fecha_inicio, $fecha_final, $presupuesto, $requisitos, $Pre_requisitos, $modalidad, $AlumnosCantidad)
    {
        $sql  = "INSERT INTO proyectos 
                 (id_investigador, id_estadoP, id_instituto, id_periodos, titulo, descripcion, objetivo,
                  fecha_inicio, fecha_fin, presupuesto, actualizado_en, requisitos, pre_requisitos, modalidad, cantidad_estudiante)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?, ?)";
        $stmt = $this->con->prepare($sql);
        if (!$stmt) die("Error en prepare(): " . $this->con->error);

        $stmt->bind_param(
            "iiiisssssssssi",
            $id_investigador, $id_estadoP, $id_instituto, $id_periodos,
            $titulo, $descripcion, $objetivo, $fecha_inicio, $fecha_final,
            $presupuesto, $requisitos, $Pre_requisitos, $modalidad, $AlumnosCantidad
        );

        if (!$stmt->execute()) die("Error en execute(): " . $stmt->error);
        return $this->con->insert_id;
    }

    public function editarProyecto($id_proyecto, $id_investigador, $titulo, $descripcion, $objetivo, $fecha_inicio, $fecha_final, $presupuesto, $requisitos, $Pre_requisitos, $modalidad, $AlumnosCantidad)
    {
        $sql  = "UPDATE proyectos SET 
                    titulo = ?, descripcion = ?, objetivo = ?, pre_requisitos = ?, requisitos = ?,
                    cantidad_estudiante = ?, modalidad = ?, actualizado_en = NOW(),
                    presupuesto = ?, fecha_inicio = ?, fecha_fin = ?
                 WHERE id_proyectos = ? AND id_investigador = ?";
        $stmt = $this->con->prepare($sql);
        if (!$stmt) die("Error en prepare(): " . $this->con->error);

        $stmt->bind_param(
            "sssssiisissi",
            $titulo, $descripcion, $objetivo, $Pre_requisitos, $requisitos,
            $AlumnosCantidad, $modalidad, $presupuesto, $fecha_inicio, $fecha_final,
            $id_proyecto, $id_investigador
        );

        if (!$stmt->execute()) die("Error en execute(): " . $stmt->error);
    }

    // =========================================================
    // ACTUALIZAR ESTADO
    // =========================================================

    public function actualizarEstadoProyectoRechazo($id_usuario, $id_proyectos, $tipo, $comentario)
    {
        $num_motivo = ($tipo === 'cierre_rechazado') ? 7 : 4;

        $sql  = "UPDATE proyectos SET id_estadoP = ?, actualizado_en = NOW() WHERE id_proyectos = ?";
        $stmt = $this->con->prepare($sql);
        $stmt->bind_param("ii", $num_motivo, $id_proyectos);
        if (!$stmt->execute()) die("Error execute(): " . $stmt->error);

        $sql  = "INSERT INTO proyectos_comentarios (id_proyectos, id_usuarios, tipo, comentario, fecha)
                 VALUES (?, ?, ?, ?, CURDATE())";
        $stmt = $this->con->prepare($sql);
        $stmt->bind_param("iiss", $id_proyectos, $id_usuario, $tipo, $comentario);
        if (!$stmt->execute()) die("Error execute(): " . $stmt->error);

        if ($tipo === 'cierre_rechazado') {
            $sql    = "UPDATE tbl_cierres SET fecha_resultado = CURDATE(), estado = ? WHERE id_proyectos = ?";
            $estado = 'rechazado';
            $stmtC  = $this->con->prepare($sql);
            $stmtC->bind_param("si", $estado, $id_proyectos);
            $stmtC->execute();
        }
    }

    public function actualizarestado(int $id_proyectos, int $numeroEstado, $porcentaje = null): void
    {
        $sql  = "UPDATE proyectos SET id_estadoP = ?, actualizado_en = NOW() WHERE id_proyectos = ?";
        $stmt = $this->con->prepare($sql);
        if (!$stmt) throw new Exception("Error prepare: " . $this->con->error);
        $stmt->bind_param("ii", $numeroEstado, $id_proyectos);
        if (!$stmt->execute()) throw new Exception("Error execute: " . $stmt->error);
        $stmt->close();

        // Estado 2: Activo → crear tareas
        if ($numeroEstado === 2) {

            $sqlPlantilla  = "SELECT pd.id_plantilla, pd.id_documento
                              FROM plantillas_documentos pd
                              INNER JOIN tipo_documento td ON td.id_tipo_documento = pd.id_tipo_documento
                              WHERE pd.activo = 1 AND LOWER(td.nombre) LIKE 'reporte%' LIMIT 1";
            $resPlantilla  = $this->con->query($sqlPlantilla);
            $plantillaRep  = $resPlantilla ? $resPlantilla->fetch_assoc() : null;
            $id_doc_reporte = $plantillaRep['id_documento'] ?? null;

            $stmtSeg = $this->con->prepare(
                "INSERT INTO tbl_seguimiento (id_proyectos, fecha_activacion) VALUES (?, CURDATE())"
            );
            if (!$stmtSeg) throw new Exception("Error prepare tbl_seguimiento: " . $this->con->error);
            $stmtSeg->bind_param("i", $id_proyectos);
            $stmtSeg->execute();
            $id_avances = $stmtSeg->insert_id;
            $stmtSeg->close();

            $result = $this->con->query("SELECT id_tareatipo FROM tipo_tarea ORDER BY id_tareatipo ASC");
            if (!$result) throw new Exception("Error al obtener tipos de tarea.");

            $estadoSinActivar = 4;

            $stmtTarea = $this->con->prepare(
                "INSERT INTO tareas (id_avances, id_tareatipo, id_estadoT) VALUES (?, ?, ?)"
            );
            $stmtTareaDoc = $this->con->prepare(
                "INSERT INTO tareas (id_avances, id_tareatipo, id_estadoT, id_documento_recurso) VALUES (?, ?, ?, ?)"
            );

            while ($row = $result->fetch_assoc()) {
                $id_tipo = (int)$row['id_tareatipo'];
                if ($id_tipo === 12 && $id_doc_reporte !== null) {
                    $stmtTareaDoc->bind_param("iiii", $id_avances, $id_tipo, $estadoSinActivar, $id_doc_reporte);
                    $stmtTareaDoc->execute();
                } else {
                    $stmtTarea->bind_param("iii", $id_avances, $id_tipo, $estadoSinActivar);
                    $stmtTarea->execute();
                }
            }
            $stmtTarea->close();
            $stmtTareaDoc->close();

        // Estado 5: Por cerrar → insertar tbl_cierres
        } elseif ($numeroEstado === 5) {

            $stmtInv = $this->con->prepare("SELECT id_investigador FROM proyectos WHERE id_proyectos = ?");
            $stmtInv->bind_param("i", $id_proyectos);
            $stmtInv->execute();
            $row = $stmtInv->get_result()->fetch_assoc();
            $stmtInv->close();

            if ($row) {
                $estado   = 'espera';
                $stmtC    = $this->con->prepare(
                    "INSERT INTO tbl_cierres (id_proyectos, id_supervisor, fecha_solicitud, porcentaje, estado)
                     VALUES (?, ?, CURDATE(), ?, ?)"
                );
                $stmtC->bind_param("iiis", $id_proyectos, $row['id_investigador'], $porcentaje, $estado);
                $stmtC->execute();
                $stmtC->close();
            }

        // Estado 1: Cerrado → aprobar cierre
        } elseif ($numeroEstado === 1) {

            $stmtInv = $this->con->prepare("SELECT id_investigador FROM proyectos WHERE id_proyectos = ?");
            $stmtInv->bind_param("i", $id_proyectos);
            $stmtInv->execute();
            $row = $stmtInv->get_result()->fetch_assoc();
            $stmtInv->close();

            if ($row) {
                $estadoCierre = 'aprobado';
                $stmtC = $this->con->prepare(
                    "UPDATE tbl_cierres SET fecha_resultado = CURDATE(), estado = ? WHERE id_proyectos = ?"
                );
                $stmtC->bind_param("si", $estadoCierre, $id_proyectos);
                $stmtC->execute();
                $stmtC->close();

                $estadoUsuario = 'concluido';
                $stmtU = $this->con->prepare(
                    "UPDATE proyectos_usuarios SET fecha_terminacion = CURDATE(), estado = ? WHERE id_proyectos = ?"
                );
                $stmtU->bind_param("si", $estadoUsuario, $id_proyectos);
                $stmtU->execute();
                $stmtU->close();
            }
        }
    }

    // =========================================================
    // PORCENTAJE DE AVANCE
    // =========================================================

    function valorPorEstado($estado)
    {
        return match ((int)$estado) {
            5 => 100,
            2, 3 => 50,
            default => 0,
        };
    }

    public function obtenerTareasAvance($id_proyecto)
    {
        $sql  = "SELECT taus.id_estadoT FROM tareas_usuarios AS taus
                 JOIN tareas AS tare ON tare.id_tarea = taus.id_tarea
                 JOIN tbl_seguimiento AS tbse ON tare.id_avances = tbse.id_avances
                 WHERE tbse.id_proyectos = ? AND taus.id_estadoT = 5";
        $stmt = $this->con->prepare($sql);
        $stmt->bind_param("i", $id_proyecto);
        $stmt->execute();
        $result     = $stmt->get_result();
        $totalTareas = 11;
        $suma       = 0;

        while ($row = $result->fetch_assoc()) {
            $suma += $this->valorPorEstado($row['id_estadoT']);
        }

        return round(min(100, ($suma / $totalTareas) * 100), 2);
    }

    // =========================================================
    // DETALLES DEL PROYECTO
    // =========================================================

    function obtenerProyecto($id_proyecto)
    {
        $sql = "SELECT 
            proy.id_proyectos,
            espr.nombre AS estado_proyecto,
            tema.nombre_tematica AS tematica,
            peri.periodo,
            CASE 
                WHEN CURDATE() BETWEEN peri.fecha_inicio AND peri.fecha_final THEN 'Activo'
                WHEN CURDATE() < peri.fecha_inicio THEN 'Pendiente'
                ELSE 'Terminado'
            END AS estado_periodo,
            proy.titulo, proy.descripcion, proy.objetivo,
            proy.fecha_inicio, proy.fecha_fin, proy.presupuesto,
            proy.creado_en, proy.requisitos, proy.pre_requisitos,
            proy.modalidad, proy.cantidad_estudiante
        FROM proyectos AS proy
        JOIN estados_proyectos AS espr ON proy.id_estadoP = espr.id_estadoP
        JOIN proyectos_subtematica AS proy_sub ON proy.id_proyectos = proy_sub.id_proyectos
        JOIN subtematica AS subt ON proy_sub.id_subtematica = subt.id_subtematica
        JOIN tematica AS tema ON tema.id_tematica = subt.id_tematica
        JOIN periodos AS peri ON peri.id_periodos = proy.id_periodos
        WHERE proy.id_proyectos = ?
        GROUP BY proy.id_proyectos, espr.nombre, tema.nombre_tematica
        ORDER BY proy.id_proyectos DESC";

        $stmt = $this->con->prepare($sql);
        if (!$stmt) die("Error en prepare: " . $this->con->error);
        $stmt->bind_param("i", $id_proyecto);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    function obtenerProyectoInvestigador($id_proyecto)
    {
        $sql  = "SELECT usua.id_usuarios, usua.nombre, usua.apellido_paterno, usua.apellido_materno,
                        nisn.nombre AS nivel_sni, grac.nombre AS grado_academico
                 FROM investigadores AS inve
                 JOIN usuarios AS usua ON usua.id_usuarios = inve.id_usuarios
                 JOIN niveles_sni AS nisn ON nisn.id_nivel = inve.id_nivel_sni
                 JOIN grados_academicos AS grac ON grac.id_grado = inve.id_grado
                 JOIN proyectos AS proy ON proy.id_investigador = inve.id_usuarios
                 WHERE proy.id_proyectos = ?";
        $stmt = $this->con->prepare($sql);
        $stmt->bind_param("i", $id_proyecto);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    function obtenerUsuarioArea($id_usuario)
    {
        $sql  = "SELECT arco.nombre_area AS area_conocimiento, GROUP_CONCAT(subco.nombre_subarea) AS subarea
                 FROM usuarios AS us
                 JOIN usuarios_subareas AS ussu ON ussu.id_usuarios = us.id_usuarios
                 JOIN subareas_conocimiento AS subco ON ussu.id_subarea = subco.id_subarea
                 JOIN areas_conocimiento AS arco ON arco.id_area = subco.id_area
                 WHERE us.id_usuarios = ?
                 GROUP BY us.id_usuarios, subco.id_subarea, arco.id_area";
        $stmt = $this->con->prepare($sql);
        $stmt->bind_param("i", $id_usuario);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    function obtenerInvestigadorLinea($id_proyecto)
    {
        $sql  = "SELECT liin.nombre AS linea
                 FROM investigadores AS inve
                 JOIN investigador_lineas_investigacion AS inliin ON inliin.id_usuarios = inve.id_usuarios
                 JOIN lineas_investigacion AS liin ON liin.id_linea = inliin.id_linea
                 JOIN proyectos AS proy ON proy.id_investigador = inve.id_usuarios
                 WHERE proy.id_proyectos = ?";
        $stmt = $this->con->prepare($sql);
        $stmt->bind_param("i", $id_proyecto);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    public function obtenersubtematicasProyecto($id_proyecto)
    {
        $sql  = "SELECT sub.id_subtematica, sub2.nombre_subtematica AS nombre
                 FROM proyectos_subtematica AS sub
                 JOIN subtematica AS sub2 ON sub.id_subtematica = sub2.id_subtematica
                 WHERE sub.id_proyectos = ? AND sub2.estado = 1";
        $stmt = $this->con->prepare($sql);
        $stmt->bind_param("i", $id_proyecto);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    function obtenerProyectoEstudiante($id_proyecto)
    {
        $sql  = "SELECT usua.id_usuarios, usua.nombre, usua.apellido_paterno, usua.apellido_materno,
                        carr.nombre_carrera AS carrera
                 FROM estudiantes AS estu
                 JOIN usuarios AS usua ON usua.id_usuarios = estu.id_usuarios
                 JOIN carreras AS carr ON carr.id_carrera = estu.id_carrera
                 JOIN proyectos_usuarios AS prus ON prus.id_usuarios = estu.id_usuarios
                 JOIN proyectos AS proy ON proy.id_proyectos = prus.id_proyectos
                 WHERE proy.id_proyectos = ?";
        $stmt = $this->con->prepare($sql);
        $stmt->bind_param("i", $id_proyecto);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function obtenerProyectoComentarios($id_proyecto)
    {
        $sql  = "SELECT 
                    CASE 
                        WHEN prco.tipo = 'creacion_rechazada' THEN 'Creación rechazada'
                        WHEN prco.tipo = 'cierre_rechazado'   THEN 'Cierre rechazado'
                        ELSE 'Rechazo'
                    END AS tipo,
                    CONCAT(usua.nombre, ' ', usua.apellido_paterno, ' ', usua.apellido_materno) AS nombre_completo,
                    prco.comentario,
                    prco.fecha
                 FROM proyectos_comentarios AS prco
                 JOIN proyectos AS proy ON proy.id_proyectos = prco.id_proyectos
                 JOIN usuarios AS usua ON usua.id_usuarios = prco.id_usuarios
                 WHERE proy.id_proyectos = ?
                 ORDER BY fecha DESC";
        $stmt = $this->con->prepare($sql);
        $stmt->bind_param("i", $id_proyecto);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    // =========================================================
    // SUBTEMATICAS
    // =========================================================

    public function vincularSubtematica($id_proyecto, $id_subtematica)
    {
        $sql  = "INSERT INTO proyectos_subtematica (id_proyectos, id_subtematica) VALUES (?, ?)";
        $stmt = $this->con->prepare($sql);
        if (!$stmt) die("Error prepare(): " . $this->con->error);
        $stmt->bind_param("ii", $id_proyecto, $id_subtematica);
        if (!$stmt->execute()) die("Error execute(): " . $stmt->error);
    }

    public function ActualizarvincularSubtematica($id_proyecto, $id_subtematica)
    {
        $stmtE = $this->con->prepare("DELETE FROM proyectos_subtematica WHERE id_proyectos = ?");
        $stmtE->bind_param("i", $id_proyecto);
        $stmtE->execute();

        $stmt = $this->con->prepare("INSERT INTO proyectos_subtematica (id_subtematica, id_proyectos) VALUES (?, ?)");
        $stmt->bind_param("ii", $id_subtematica, $id_proyecto);
        if (!$stmt->execute()) die("Error execute(): " . $stmt->error);
    }

    // =========================================================
    // ESTUDIANTES EN EL PROYECTO
    // =========================================================

    public function estudiantes($id_proyecto)
    {
        $sql  = "SELECT 
            u.id_usuarios,
            u.nombre, u.apellido_paterno, u.apellido_materno,
            c.nombre_carrera AS carrera,
            pu.estado,
            hpu.motivo
        FROM proyectos_usuarios pu
        JOIN usuarios u ON u.id_usuarios = pu.id_usuarios
        JOIN estudiantes e ON e.id_usuarios = u.id_usuarios
        JOIN carreras c ON e.id_carrera = c.id_carrera
        LEFT JOIN (
            SELECT h1.id_proyectos, h1.id_estudiante, h1.motivo
            FROM historial_proyectos_usuarios h1
            INNER JOIN (
                SELECT id_proyectos, id_estudiante, MAX(id_historial) AS max_id
                FROM historial_proyectos_usuarios
                WHERE accion = 'baja'
                GROUP BY id_proyectos, id_estudiante
            ) h2 ON h1.id_historial = h2.max_id
        ) hpu ON hpu.id_proyectos = pu.id_proyectos AND hpu.id_estudiante = pu.id_usuarios
        WHERE pu.id_proyectos = ?";
        $stmt = $this->con->prepare($sql);
        $stmt->bind_param("i", $id_proyecto);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function obtenerEstudianteProyecto($id_proyecto, $id_estudiante)
    {
        $sql  = "SELECT u.nombre, u.apellido_paterno, u.apellido_materno, p.titulo
                 FROM usuarios u
                 JOIN proyectos_usuarios pu ON pu.id_usuarios = u.id_usuarios
                 JOIN proyectos p ON p.id_proyectos = pu.id_proyectos
                 WHERE pu.id_proyectos = ? AND pu.id_usuarios = ?";
        $stmt = $this->con->prepare($sql);
        $stmt->bind_param("ii", $id_proyecto, $id_estudiante);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    public function bajaEstudiante($id_proyecto, $id_estudiante, $motivo, $usuario)
    {
        $this->con->begin_transaction();
        try {
            $check = "SELECT estado FROM proyectos_usuarios WHERE id_proyectos = ? AND id_usuarios = ?";
            $stmt  = $this->con->prepare($check);
            $stmt->bind_param("ii", $id_proyecto, $id_estudiante);
            $stmt->execute();
            $estado = $stmt->get_result()->fetch_assoc()['estado'] ?? null;

            if ($estado !== 'activo') throw new Exception("El estudiante no está activo");

            $sql  = "UPDATE proyectos_usuarios SET estado = 'baja', fecha_baja = NOW(), motivo_baja = ?, reincorporacion = 0
                     WHERE id_proyectos = ? AND id_usuarios = ?";
            $stmt = $this->con->prepare($sql);
            $stmt->bind_param("sii", $motivo, $id_proyecto, $id_estudiante);
            $stmt->execute();

            $sql  = "INSERT INTO historial_proyectos_usuarios (id_proyectos, id_estudiante, accion, motivo, realizado_por)
                     VALUES (?, ?, 'baja', ?, ?)";
            $stmt = $this->con->prepare($sql);
            $stmt->bind_param("iisi", $id_proyecto, $id_estudiante, $motivo, $usuario);
            $stmt->execute();

            $this->con->commit();
            return ["success" => true];
        } catch (Throwable $e) {
            $this->con->rollback();
            return ["success" => false, "msg" => $e->getMessage()];
        }
    }

    public function reactivarEstudiante($id_proyecto, $id_estudiante, $usuario)
    {
        $this->con->begin_transaction();
        try {
            $check = "SELECT pu.estado, p.fecha_fin
                      FROM proyectos_usuarios pu
                      JOIN proyectos p ON p.id_proyectos = pu.id_proyectos
                      WHERE pu.id_proyectos = ? AND pu.id_usuarios = ?";
            $stmt  = $this->con->prepare($check);
            $stmt->bind_param("ii", $id_proyecto, $id_estudiante);
            $stmt->execute();
            $data = $stmt->get_result()->fetch_assoc();

            if (!$data)                         throw new Exception("Registro no encontrado");
            if ($data['estado'] !== 'baja')     throw new Exception("Solo se puede reactivar si está en baja");
            if ($data['fecha_fin'] < date('Y-m-d')) throw new Exception("El proyecto está vencido, requiere prórroga");

            $sql  = "UPDATE proyectos_usuarios SET estado = 'activo', fecha_baja = NULL, motivo_baja = NULL, reincorporacion = 1
                     WHERE id_proyectos = ? AND id_usuarios = ?";
            $stmt = $this->con->prepare($sql);
            $stmt->bind_param("ii", $id_proyecto, $id_estudiante);
            $stmt->execute();

            $sql  = "INSERT INTO historial_proyectos_usuarios (id_proyectos, id_estudiante, accion, realizado_por)
                     VALUES (?, ?, 'reactivado', ?)";
            $stmt = $this->con->prepare($sql);
            $stmt->bind_param("iii", $id_proyecto, $id_estudiante, $usuario);
            $stmt->execute();

            $this->con->commit();
            return ["success" => true];
        } catch (Throwable $e) {
            $this->con->rollback();
            return ["success" => false, "msg" => $e->getMessage()];
        }
    }

    // =========================================================
    // HISTORIAL DE ESTUDIANTE EN PROYECTO
    // =========================================================

    public function lineaTiempoProyectoUsuarios($id_proyecto, $id_usuario, $pagina = 1, $por_pagina = 5)
    {
        $pagina   = max(1, (int)$pagina);
        $desde    = ($pagina - 1) * $por_pagina;

        $sqlTotal = "SELECT COUNT(*) AS total FROM historial_proyectos_usuarios
                     WHERE id_proyectos = ? AND id_estudiante = ?";
        $stmt     = $this->con->prepare($sqlTotal);
        $stmt->bind_param("ii", $id_proyecto, $id_usuario);
        $stmt->execute();
        $total         = $stmt->get_result()->fetch_assoc()['total'];
        $stmt->close();
        $total_paginas = ceil($total / $por_pagina);

        $sql  = "SELECT h.accion AS tipo_evento, h.motivo AS descripcion, h.fecha,
                        u.nombre AS usuario
                 FROM historial_proyectos_usuarios h
                 LEFT JOIN usuarios u ON h.id_estudiante = u.id_usuarios
                 WHERE h.id_proyectos = ? AND h.id_estudiante = ?
                 ORDER BY h.fecha DESC
                 LIMIT ?, ?";
        $stmt = $this->con->prepare($sql);
        $stmt->bind_param("iiii", $id_proyecto, $id_usuario, $desde, $por_pagina);
        $stmt->execute();
        $historial = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $agrupado = [];
        foreach ($historial as $item) {
            $fecha = date("d/m/Y", strtotime($item['fecha']));
            $agrupado[$fecha][] = $item;
        }

        return [
            "datos"      => $agrupado,
            "paginacion" => compact("total", "por_pagina", "pagina") + ["total_paginas" => $total_paginas]
        ];
    }
}