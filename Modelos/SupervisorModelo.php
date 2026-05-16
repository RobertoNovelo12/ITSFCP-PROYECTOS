<?php

/**
 * SupervisorModelo.php
 * Modelo exclusivo del panel de supervisor — Dashboard de solo lectura.
 * Usa mysqli (igual que el resto del sistema).
 */
class SupervisorModelo
{
    private mysqli $con;

    public function __construct(mysqli $conn)
    {
        $this->con = $conn;
    }

    // ================================================================
    // AUXILIARES PARA FILTROS (selects)
    // ================================================================

    public function obtenerPeriodos(): array
    {
        $res = $this->con->query("
            SELECT id_periodos, periodo, CASE
                        WHEN CURDATE() BETWEEN fecha_inicio AND fecha_final THEN 'Activo'
                        ELSE 'Terminado'
                    END AS estado
            FROM periodos
            ORDER BY id_periodos DESC
        ");
        return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    }

    public function obtenerInvestigadores(): array
    {
        $res = $this->con->query("
            SELECT i.id_usuarios,
                   CONCAT(u.nombre,' ',u.apellido_paterno,' ',u.apellido_materno) AS nombre_completo
            FROM investigadores i
            JOIN usuarios u ON u.id_usuarios = i.id_usuarios
            ORDER BY u.nombre ASC
        ");
        return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    }

    public function obtenerEstadosProyecto(): array
    {
        $res = $this->con->query("SELECT id_estadoP, nombre FROM estados_proyectos ORDER BY nombre");
        return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    }

    public function obtenerCarreras(): array
    {
        $res = $this->con->query("SELECT id_carrera, nombre_carrera FROM carreras WHERE estado = 1 ORDER BY nombre_carrera");
        return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    }

    // ================================================================
    // RESUMEN GLOBAL — tarjetas principales del dashboard
    // ================================================================

    public function resumenGlobal(array $f): array
    {
        $wherePeriodo  = !empty($f['periodo']) ? "AND p.id_periodos = ?" : "";
        $typesPeriodo  = !empty($f['periodo']) ? "i" : "";
        $paramsPeriodo = !empty($f['periodo']) ? [(int)$f['periodo']] : [];

        // ── Proyectos ──────────────────────────────────────────────
        $sql = "
            SELECT
                COUNT(*)                              AS total_proyectos,
                SUM(ep.nombre = 'Activo')             AS activos,
                SUM(ep.nombre = 'Por aprobar')        AS por_aprobar,
                SUM(ep.nombre = 'Por cerrar')         AS por_cerrar,
                SUM(ep.nombre = 'Rechazado')          AS rechazados,
                SUM(ep.nombre = 'Vencido')            AS vencidos,
                SUM(ep.nombre = 'Cierre')             AS cerrados
            FROM proyectos p
            JOIN estados_proyectos ep ON ep.id_estadoP = p.id_estadoP
            WHERE 1=1 {$wherePeriodo}
        ";
        $stmt = $this->con->prepare($sql);
        if ($typesPeriodo) $stmt->bind_param($typesPeriodo, ...$paramsPeriodo);
        $stmt->execute();
        $proyectos = $stmt->get_result()->fetch_assoc() ?? [];

        // ── Estudiantes únicos con proyectos ───────────────────────
        $sql = "
            SELECT
                COUNT(DISTINCT pu.id_usuarios)      AS total_estudiantes,
                SUM(pu.estado = 'activo')            AS activos,
                SUM(pu.estado = 'concluido')         AS concluidos,
                SUM(pu.estado = 'baja')              AS bajas,
                SUM(pu.estado = 'cancelado')         AS cancelados
            FROM proyectos_usuarios pu
            JOIN proyectos p ON p.id_proyectos = pu.id_proyectos
            WHERE 1=1 {$wherePeriodo}
        ";
        $stmt = $this->con->prepare($sql);
        if ($typesPeriodo) $stmt->bind_param($typesPeriodo, ...$paramsPeriodo);
        $stmt->execute();
        $estudiantes = $stmt->get_result()->fetch_assoc() ?? [];

        // ── Solicitudes ────────────────────────────────────────────
        $sql = "
            SELECT
                COUNT(*)                           AS total_solicitudes,
                SUM(sp.estado = 'pendiente')       AS pendientes,
                SUM(sp.estado = 'en_revision')     AS en_revision,
                SUM(sp.estado = 'correcciones')    AS correcciones,
                SUM(sp.estado = 'aceptado')        AS aceptadas,
                SUM(sp.estado = 'rechazado')       AS rechazadas
            FROM solicitud_proyecto sp
            JOIN proyectos p ON p.id_proyectos = sp.id_proyectos
            WHERE 1=1 {$wherePeriodo}
        ";
        $stmt = $this->con->prepare($sql);
        if ($typesPeriodo) $stmt->bind_param($typesPeriodo, ...$paramsPeriodo);
        $stmt->execute();
        $solicitudes = $stmt->get_result()->fetch_assoc() ?? [];

        // ── Tareas / secciones del documento ──────────────────────
        // Solo contamos tareas activas (excluye 'Sin activar')
        // Mapeamos todos los estados existentes de forma coherente:
        //   Pendiente   → pendientes
        //   Entregado   → entregadas  (alumno entregó, aún no revisada)
        //   Revisar     → en_revision (investigador está revisando)
        //   Corregir    → corregir
        //   Aprobado    → aprobadas
        //   Vencido     → vencidas
        $sqlPeriodoJoin = !empty($f['periodo'])
            ? "JOIN proyectos p ON p.id_proyectos = ts.id_proyectos AND p.id_periodos = ?"
            : "JOIN proyectos p ON p.id_proyectos = ts.id_proyectos";

        $sql = "
            SELECT
                COUNT(*)                              AS total_tareas,
                SUM(et.nombre = 'Pendiente')          AS pendientes,
                SUM(et.nombre = 'Entregado')          AS entregadas,
                SUM(et.nombre = 'Revisar')            AS en_revision,
                SUM(et.nombre = 'Corregir')           AS corregir,
                SUM(et.nombre = 'Aprobado')           AS aprobadas,
                SUM(et.nombre = 'Vencido')            AS vencidas
            FROM tareas t
            JOIN estados_tarea et ON et.id_estadoT = t.id_estadoT
            JOIN tbl_seguimiento ts ON ts.id_avances = t.id_avances
            {$sqlPeriodoJoin}
            WHERE et.nombre != 'Sin activar'
        ";
        $stmt = $this->con->prepare($sql);
        if ($typesPeriodo) $stmt->bind_param($typesPeriodo, ...$paramsPeriodo);
        $stmt->execute();
        $tareas = $stmt->get_result()->fetch_assoc() ?? [];

        return compact('proyectos', 'estudiantes', 'solicitudes', 'tareas');
    }

    // ================================================================
    // PROYECTOS — tabla paginada con filtros
    // ================================================================

    private function wheresProyectos(array $f): array
    {
        $cond   = ["1=1"];
        $params = [];
        $types  = "";

        if (!empty($f['periodo'])) {
            $cond[]   = "p.id_periodos = ?";
            $params[] = (int)$f['periodo'];
            $types   .= "i";
        }
        if (!empty($f['estado_proyecto'])) {
            $cond[]   = "ep.nombre = ?";
            $params[] = $f['estado_proyecto'];
            $types   .= "s";
        }
        if (!empty($f['investigador'])) {
            $cond[]   = "p.id_investigador = ?";
            $params[] = (int)$f['investigador'];
            $types   .= "i";
        }
        if (!empty($f['modalidad'])) {
            $cond[]   = "p.modalidad = ?";
            $params[] = $f['modalidad'];
            $types   .= "s";
        }
        if (!empty($f['buscar_proy'])) {
            $b        = "%" . $f['buscar_proy'] . "%";
            $cond[]   = "(p.titulo LIKE ? OR ui.nombre LIKE ? OR ui.apellido_paterno LIKE ?)";
            array_push($params, $b, $b, $b);
            $types   .= "sss";
        }

        return ["WHERE " . implode(" AND ", $cond), $params, $types];
    }

    public function contarProyectos(array $f): int
    {
        [$where, $params, $types] = $this->wheresProyectos($f);
        $sql = "
            SELECT COUNT(*) FROM proyectos p
            JOIN estados_proyectos ep ON ep.id_estadoP = p.id_estadoP
            JOIN usuarios ui          ON ui.id_usuarios = p.id_investigador
            {$where}
        ";
        $stmt = $this->con->prepare($sql);
        if ($types) $stmt->bind_param($types, ...$params);
        $stmt->execute();
        return (int)$stmt->get_result()->fetch_row()[0];
    }

    public function obtenerProyectos(array $f, int $desde, int $limite): array
    {
        [$where, $params, $types] = $this->wheresProyectos($f);
        $sql = "
            SELECT
                p.id_proyectos,
                p.titulo,
                p.modalidad,
                p.cantidad_estudiante,
                p.fecha_inicio,
                p.fecha_fin,
                p.creado_en,
                ep.nombre                                               AS estado,
                CONCAT(ui.nombre,' ',ui.apellido_paterno)               AS investigador_nombre,
                (SELECT COUNT(*) FROM proyectos_usuarios pu
                 WHERE pu.id_proyectos = p.id_proyectos AND pu.estado = 'activo')    AS alumnos_activos,
                (SELECT COUNT(*) FROM solicitud_proyecto sp2
                 WHERE sp2.id_proyectos = p.id_proyectos AND sp2.estado = 'pendiente') AS sol_pendientes,
                (SELECT COUNT(*) FROM tareas t2
                 JOIN tbl_seguimiento ts2 ON ts2.id_avances = t2.id_avances
                 JOIN estados_tarea et2 ON et2.id_estadoT = t2.id_estadoT
                 WHERE ts2.id_proyectos = p.id_proyectos AND et2.nombre = 'Vencido')  AS tareas_vencidas
            FROM proyectos p
            JOIN estados_proyectos ep ON ep.id_estadoP  = p.id_estadoP
            JOIN usuarios ui          ON ui.id_usuarios = p.id_investigador
            {$where}
            ORDER BY p.creado_en DESC
            LIMIT ?, ?
        ";
        $params[] = $desde;
        $params[] = $limite;
        $types   .= "ii";
        $stmt = $this->con->prepare($sql);
        if ($types) $stmt->bind_param($types, ...$params);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    // ================================================================
    // DETALLE PROYECTO (vista completa para supervisor)
    // ================================================================

    public function detalleProyecto(int $id_proyecto): array
    {
        $stmt = $this->con->prepare("
            SELECT p.*, ep.nombre AS estado,
                   CONCAT(ui.nombre,' ',ui.apellido_paterno,' ',ui.apellido_materno) AS investigador_nombre,
                   ui.correo_institucional AS investigador_correo,
                   per.periodo, per.estado AS estado_periodo,
                   t.nombre_tematica AS tematica_nombre
            FROM proyectos p
            JOIN estados_proyectos ep ON ep.id_estadoP  = p.id_estadoP
            JOIN usuarios ui          ON ui.id_usuarios = p.id_investigador
            JOIN periodos per         ON per.id_periodos = p.id_periodos
            JOIN proyectos_subtematica AS ps ON p.id_proyectos = ps.id_proyectos
            JOIN subtematica AS sub ON ps.id_subtematica = sub.id_subtematica
            LEFT JOIN tematica t      ON t.id_tematica   = sub.id_tematica
            WHERE p.id_proyectos = ?
        ");
        $stmt->bind_param("i", $id_proyecto);
        $stmt->execute();
        $proyecto = $stmt->get_result()->fetch_assoc() ?? [];

        $stmt = $this->con->prepare("
            SELECT
                u.id_usuarios,
                CONCAT(u.nombre,' ',u.apellido_paterno,' ',u.apellido_materno) AS nombre_completo,
                u.correo_institucional,
                e.matricula,
                c.nombre_carrera AS carrera,
                pu.estado        AS estado_participacion,
                pu.fecha_asignacion,
                (SELECT COUNT(*) FROM tareas_usuarios tu2
                 JOIN tareas t2 ON t2.id_tarea = tu2.id_tarea
                 JOIN tbl_seguimiento ts2 ON ts2.id_avances = t2.id_avances
                 JOIN estados_tarea et2 ON et2.id_estadoT = tu2.id_estadoT
                 WHERE ts2.id_proyectos = ? AND tu2.id_usuarios = u.id_usuarios
                   AND et2.nombre != 'Sin activar') AS tareas_totales,
                (SELECT COUNT(*) FROM tareas_usuarios tu2
                 JOIN tareas t2 ON t2.id_tarea = tu2.id_tarea
                 JOIN tbl_seguimiento ts2 ON ts2.id_avances = t2.id_avances
                 JOIN estados_tarea et2 ON et2.id_estadoT = tu2.id_estadoT
                 WHERE ts2.id_proyectos = ? AND tu2.id_usuarios = u.id_usuarios
                   AND et2.nombre = 'Aprobado') AS tareas_aprobadas,
                (SELECT tt.descripcion_tipo FROM tareas t3
                 JOIN tipo_tarea tt ON tt.id_tareatipo = t3.id_tareatipo
                 JOIN tbl_seguimiento ts3 ON ts3.id_avances = t3.id_avances
                 JOIN estados_tarea et3 ON et3.id_estadoT = t3.id_estadoT
                 WHERE ts3.id_proyectos = ? AND et3.nombre IN ('Pendiente','Revisar','Corregir','Entregado')
                 ORDER BY t3.id_tarea DESC LIMIT 1) AS tarea_actual
            FROM proyectos_usuarios pu
            JOIN usuarios u  ON u.id_usuarios  = pu.id_usuarios
            JOIN estudiantes e ON e.id_usuarios = pu.id_usuarios
            JOIN carreras c  ON c.id_carrera   = e.id_carrera
            WHERE pu.id_proyectos = ?
            ORDER BY pu.estado ASC, u.nombre ASC
        ");
        $stmt->bind_param("iiii", $id_proyecto, $id_proyecto, $id_proyecto, $id_proyecto);
        $stmt->execute();
        $estudiantes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        $stmt = $this->con->prepare("
            SELECT sp.*,
                   CONCAT(u.nombre,' ',u.apellido_paterno,' ',u.apellido_materno) AS estudiante_nombre,
                   e.matricula, c.nombre_carrera AS carrera
            FROM solicitud_proyecto sp
            JOIN usuarios u   ON u.id_usuarios  = sp.id_estudiante
            JOIN estudiantes e ON e.id_usuarios = sp.id_estudiante
            JOIN carreras c   ON c.id_carrera   = e.id_carrera
            WHERE sp.id_proyectos = ?
            ORDER BY sp.fecha_envio DESC
        ");
        $stmt->bind_param("i", $id_proyecto);
        $stmt->execute();
        $solicitudes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        $stmt = $this->con->prepare("
            SELECT t.id_tarea, tt.descripcion_tipo AS tipo, et.nombre AS estado,
                   t.fecha_entrega, t.descripcion,
                   COUNT(tu.id_asignacion)                          AS total_asignados,
                   SUM(etu.nombre = 'Aprobado')                     AS aprobados,
                   SUM(etu.nombre = 'Entregado')                    AS entregados,
                   SUM(etu.nombre = 'Revisar')                      AS en_revision,
                   SUM(etu.nombre = 'Corregir')                     AS en_correccion,
                   SUM(etu.nombre = 'Pendiente')                    AS pendientes,
                   SUM(etu.nombre = 'Vencido')                      AS vencidos
            FROM tareas t
            JOIN tipo_tarea tt ON tt.id_tareatipo = t.id_tareatipo
            JOIN estados_tarea et ON et.id_estadoT = t.id_estadoT
            JOIN tbl_seguimiento ts ON ts.id_avances = t.id_avances
            LEFT JOIN tareas_usuarios tu ON tu.id_tarea = t.id_tarea
            LEFT JOIN estados_tarea etu ON etu.id_estadoT = tu.id_estadoT
            WHERE ts.id_proyectos = ? AND et.nombre != 'Sin activar'
            GROUP BY t.id_tarea
            ORDER BY t.id_tarea ASC
        ");
        $stmt->bind_param("i", $id_proyecto);
        $stmt->execute();
        $tareas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        $stmt = $this->con->prepare("
            SELECT hp.*,
                   CONCAT(u.nombre,' ',u.apellido_paterno) AS usuario_nombre,
                   ep.nombre AS estado_nombre
            FROM historial_proyectos_usuarios hp
            LEFT JOIN usuarios u ON u.id_usuarios = hp.id_historial
            JOIN proyectos AS p ON p.id_proyectos = hp.id_proyectos
            LEFT JOIN estados_proyectos ep ON p.id_estadoP = ep.id_estadoP
            WHERE hp.id_proyectos = ?
            ORDER BY hp.fecha DESC
            LIMIT 20
        ");
        $stmt->bind_param("i", $id_proyecto);
        $stmt->execute();
        $historial = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        return compact('proyecto', 'estudiantes', 'solicitudes', 'tareas', 'historial');
    }

    // ================================================================
    // SOLICITUDES — tabla paginada con filtros
    // ================================================================

    private function wheresSolicitudes(array $f): array
    {
        $cond   = ["1=1"];
        $params = [];
        $types  = "";

        if (!empty($f['periodo'])) {
            $cond[]   = "p.id_periodos = ?";
            $params[] = (int)$f['periodo'];
            $types   .= "i";
        }
        if (!empty($f['estado_sol'])) {
            $cond[]   = "sp.estado = ?";
            $params[] = $f['estado_sol'];
            $types   .= "s";
        }
        if (!empty($f['investigador'])) {
            $cond[]   = "p.id_investigador = ?";
            $params[] = (int)$f['investigador'];
            $types   .= "i";
        }
        if (!empty($f['carrera'])) {
            $cond[]   = "e.id_carrera = ?";
            $params[] = (int)$f['carrera'];
            $types   .= "i";
        }
        if (!empty($f['buscar_sol'])) {
            $b        = "%" . $f['buscar_sol'] . "%";
            $cond[]   = "(u.nombre LIKE ? OR e.matricula LIKE ? OR p.titulo LIKE ?)";
            array_push($params, $b, $b, $b);
            $types   .= "sss";
        }
        if (!empty($f['fecha_desde'])) {
            $cond[]   = "sp.fecha_envio >= ?";
            $params[] = $f['fecha_desde'];
            $types   .= "s";
        }
        if (!empty($f['fecha_hasta'])) {
            $cond[]   = "sp.fecha_envio <= ?";
            $params[] = $f['fecha_hasta'];
            $types   .= "s";
        }

        return ["WHERE " . implode(" AND ", $cond), $params, $types];
    }

    public function contarSolicitudes(array $f): int
    {
        [$where, $params, $types] = $this->wheresSolicitudes($f);
        $sql = "
            SELECT COUNT(*) FROM solicitud_proyecto sp
            JOIN proyectos p    ON p.id_proyectos  = sp.id_proyectos
            JOIN usuarios u     ON u.id_usuarios   = sp.id_estudiante
            JOIN estudiantes e  ON e.id_usuarios   = sp.id_estudiante
            JOIN usuarios ui    ON ui.id_usuarios  = p.id_investigador
            {$where}
        ";
        $stmt = $this->con->prepare($sql);
        if ($types) $stmt->bind_param($types, ...$params);
        $stmt->execute();
        return (int)$stmt->get_result()->fetch_row()[0];
    }

    public function obtenerSolicitudes(array $f, int $desde, int $limite): array
    {
        [$where, $params, $types] = $this->wheresSolicitudes($f);
        $sql = "
            SELECT
                sp.id_solicitud_proyecto,
                sp.estado,
                sp.fecha_envio,
                sp.fecha_respuesta,
                sp.semestre,
                sp.promedio,
                sp.motivacion,
                sp.experiencia,
                sp.id_proyectos,
                CONCAT(u.nombre,' ',u.apellido_paterno,' ',u.apellido_materno) AS estudiante_nombre,
                u.correo_institucional,
                e.matricula,
                c.nombre_carrera AS carrera,
                p.titulo         AS proyecto_titulo,
                p.modalidad,
                CONCAT(ui.nombre,' ',ui.apellido_paterno) AS investigador_nombre
            FROM solicitud_proyecto sp
            JOIN proyectos p    ON p.id_proyectos  = sp.id_proyectos
            JOIN usuarios u     ON u.id_usuarios   = sp.id_estudiante
            JOIN estudiantes e  ON e.id_usuarios   = sp.id_estudiante
            JOIN carreras c     ON c.id_carrera    = e.id_carrera
            JOIN usuarios ui    ON ui.id_usuarios  = p.id_investigador
            {$where}
            ORDER BY FIELD(sp.estado,'en_revision','correcciones','pendiente','aceptado','rechazado'),
                     sp.fecha_envio ASC
            LIMIT ?, ?
        ";
        $params[] = $desde;
        $params[] = $limite;
        $types   .= "ii";
        $stmt = $this->con->prepare($sql);
        if ($types) $stmt->bind_param($types, ...$params);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    // ================================================================
    // ETAPAS — resumen de alumnos por etapa (tipo_documento)
    // ================================================================

    public function resumenEtapas(array $f): array
    {
        $wherePeriodo  = !empty($f['periodo']) ? "AND p.id_periodos = ?" : "";
        $typesPeriodo  = !empty($f['periodo']) ? "i" : "";
        $paramsPeriodo = !empty($f['periodo']) ? [(int)$f['periodo']] : [];

        $sql = "
            SELECT
                td.id_tipo_documento,
                td.nombre   AS etapa,
                td.categoria,
                COUNT(sd.id_seguimiento)                           AS total,
                SUM(sd.estado = 'pendiente')                       AS pendientes,
                SUM(sd.estado = 'proceso')                         AS en_proceso,
                SUM(sd.estado = 'completado')                      AS completados,
                SUM(sd.estado = 'rechazado')                       AS rechazados
            FROM tipo_documento td
            LEFT JOIN seguimiento_documento sd ON sd.id_tipo_documento = td.id_tipo_documento
            LEFT JOIN proyectos p ON p.id_proyectos = sd.id_proyectos
            WHERE td.estado = 1 {$wherePeriodo}
            GROUP BY td.id_tipo_documento
            ORDER BY td.orden ASC
        ";
        $stmt = $this->con->prepare($sql);
        if ($typesPeriodo) $stmt->bind_param($typesPeriodo, ...$paramsPeriodo);
        $stmt->execute();
        $etapas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        // Resumen de secciones del documento con TODOS los estados separados
        $sqlPeriodoJoin = !empty($f['periodo'])
            ? "JOIN proyectos p ON p.id_proyectos = ts.id_proyectos AND p.id_periodos = ?"
            : "JOIN proyectos p ON p.id_proyectos = ts.id_proyectos";

        $sqlTareas = "
            SELECT
                tt.id_tareatipo,
                tt.descripcion_tipo                                       AS seccion,
                COUNT(DISTINCT t.id_tarea)                                AS total_instancias,
                SUM(et.nombre = 'Aprobado')                               AS aprobadas,
                SUM(et.nombre = 'Entregado')                              AS entregadas,
                SUM(et.nombre = 'Revisar')                                AS en_revision,
                SUM(et.nombre = 'Corregir')                               AS correcciones,
                SUM(et.nombre = 'Pendiente')                              AS pendientes,
                SUM(et.nombre = 'Vencido')                                AS vencidas
            FROM tareas t
            JOIN tipo_tarea tt ON tt.id_tareatipo = t.id_tareatipo
            JOIN estados_tarea et ON et.id_estadoT = t.id_estadoT
            JOIN tbl_seguimiento ts ON ts.id_avances = t.id_avances
            {$sqlPeriodoJoin}
            WHERE et.nombre != 'Sin activar'
            GROUP BY tt.id_tareatipo
            ORDER BY tt.id_tareatipo ASC
        ";
        $stmt2 = $this->con->prepare($sqlTareas);
        if ($typesPeriodo) $stmt2->bind_param($typesPeriodo, ...$paramsPeriodo);
        $stmt2->execute();
        $secciones = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);

        return ['etapas' => $etapas, 'secciones' => $secciones];
    }

    // ================================================================
    // USUARIOS — estudiantes paginados con filtros
    // ================================================================

    private function wheresEstudiantes(array $f): array
    {
        $cond   = ["1=1"];
        $params = [];
        $types  = "";

        if (!empty($f['carrera'])) {
            $cond[]   = "e.id_carrera = ?";
            $params[] = (int)$f['carrera'];
            $types   .= "i";
        }
        if (!empty($f['estado_usuario'])) {
            $cond[]   = "u.estado_usuario = ?";
            $params[] = $f['estado_usuario'];
            $types   .= "s";
        }
        if (!empty($f['buscar_usr'])) {
            $b        = "%" . $f['buscar_usr'] . "%";
            $cond[]   = "(u.nombre LIKE ? OR u.apellido_paterno LIKE ? OR e.matricula LIKE ? OR u.correo_institucional LIKE ?)";
            array_push($params, $b, $b, $b, $b);
            $types   .= "ssss";
        }

        return ["WHERE " . implode(" AND ", $cond), $params, $types];
    }

    public function contarEstudiantes(array $f): int
    {
        [$where, $params, $types] = $this->wheresEstudiantes($f);
        $sql = "
            SELECT COUNT(*)
            FROM estudiantes e
            JOIN usuarios u ON u.id_usuarios = e.id_usuarios
            {$where}
        ";
        $stmt = $this->con->prepare($sql);
        if ($types) $stmt->bind_param($types, ...$params);
        $stmt->execute();
        return (int)$stmt->get_result()->fetch_row()[0];
    }

    public function obtenerEstudiantes(array $f, int $desde, int $limite): array
    {
        [$where, $params, $types] = $this->wheresEstudiantes($f);
        $sql = "
            SELECT
                u.id_usuarios,
                CONCAT(u.nombre,' ',u.apellido_paterno,' ',u.apellido_materno) AS nombre_completo,
                u.correo_institucional,
                u.estado_usuario,
                ep.estado AS estado_proceso,
                u.fecha_registro,
                e.matricula,
                c.nombre_carrera AS carrera,
                (SELECT COUNT(*) FROM proyectos_usuarios pu2
                 WHERE pu2.id_usuarios = u.id_usuarios AND pu2.estado = 'activo')    AS proyectos_activos,
                (SELECT COUNT(*) FROM proyectos_usuarios pu3
                 WHERE pu3.id_usuarios = u.id_usuarios)                              AS proyectos_total,
                (SELECT COUNT(*) FROM tareas_usuarios tu
                 JOIN estados_tarea eta ON eta.id_estadoT = tu.id_estadoT
                 WHERE tu.id_usuarios = u.id_usuarios AND eta.nombre = 'Aprobado')   AS tareas_aprobadas,
                (SELECT COUNT(*) FROM tareas_usuarios tu2
                 JOIN estados_tarea eta2 ON eta2.id_estadoT = tu2.id_estadoT
                 WHERE tu2.id_usuarios = u.id_usuarios AND eta2.nombre != 'Sin activar') AS tareas_total
            FROM estudiantes e
            JOIN usuarios u  ON u.id_usuarios = e.id_usuarios
            JOIN carreras c  ON c.id_carrera  = e.id_carrera
            LEFT JOIN proyectos_usuarios pu ON pu.id_usuarios = e.id_usuarios
            AND pu.id_integrante = (
                SELECT MAX(pu2.id_integrante)
                FROM proyectos_usuarios pu2
                WHERE pu2.id_usuarios = e.id_usuarios
            )
            LEFT JOIN estados_proceso ep ON ep.id_estados_proceso = pu.id_estados_proceso
            {$where}
            ORDER BY u.nombre ASC
            LIMIT ?, ?
        ";
        $params[] = $desde;
        $params[] = $limite;
        $types   .= "ii";
        $stmt = $this->con->prepare($sql);
        if ($types) $stmt->bind_param($types, ...$params);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    // ================================================================
    // DETALLE ESTUDIANTE (vista completa para supervisor)
    // ================================================================

    public function detalleEstudiante(int $id_usuario): array
    {
        $stmt = $this->con->prepare("
            SELECT u.*, e.matricula, c.nombre_carrera AS carrera, g.genero, ep.estado AS estado_proceso
            FROM usuarios u
            JOIN estudiantes e ON e.id_usuarios = u.id_usuarios
            JOIN carreras c    ON c.id_carrera  = e.id_carrera
            JOIN proyectos_usuarios pu ON u.id_usuarios = e.id_usuarios
            JOIN estados_proceso ep ON ep.id_estados_proceso = pu.id_estados_proceso
            LEFT JOIN genero_usuario g ON g.id_genero = u.id_genero
            WHERE u.id_usuarios = ?
        ");
        if (!$stmt) throw new Exception("Error en prepare (detalleEstudiante): " . $this->con->error);
        $stmt->bind_param("i", $id_usuario);
        if (!$stmt->execute()) throw new Exception("Error en execute (detalleEstudiante): " . $stmt->error);
        $usuario = $stmt->get_result()->fetch_assoc() ?? [];

        $stmt = $this->con->prepare("
            SELECT p.id_proyectos, p.titulo, p.modalidad, p.fecha_inicio, p.fecha_fin,
                   ep.nombre AS estado_proyecto,
                   per.periodo,
                   pu.estado AS estado_participacion, pu.fecha_asignacion, pu.fecha_terminacion,
                   CONCAT(ui.nombre,' ',ui.apellido_paterno) AS investigador_nombre,
                   (SELECT COUNT(*) FROM tareas_usuarios tu2
                    JOIN tareas t2 ON t2.id_tarea = tu2.id_tarea
                    JOIN tbl_seguimiento ts2 ON ts2.id_avances = t2.id_avances
                    JOIN estados_tarea et2 ON et2.id_estadoT = tu2.id_estadoT
                    WHERE ts2.id_proyectos = p.id_proyectos AND tu2.id_usuarios = ?
                      AND et2.nombre != 'Sin activar')    AS tareas_total,
                   (SELECT COUNT(*) FROM tareas_usuarios tu3
                    JOIN tareas t3 ON t3.id_tarea = tu3.id_tarea
                    JOIN tbl_seguimiento ts3 ON ts3.id_avances = t3.id_avances
                    JOIN estados_tarea et3 ON et3.id_estadoT = tu3.id_estadoT
                    WHERE ts3.id_proyectos = p.id_proyectos AND tu3.id_usuarios = ?
                    AND et3.nombre = 'Aprobado')           AS tareas_aprobadas
            FROM proyectos_usuarios pu
            JOIN proyectos p          ON p.id_proyectos  = pu.id_proyectos
            JOIN estados_proyectos ep ON ep.id_estadoP   = p.id_estadoP
            JOIN periodos per         ON per.id_periodos = p.id_periodos
            JOIN usuarios ui          ON ui.id_usuarios  = p.id_investigador
            WHERE pu.id_usuarios = ?
            ORDER BY pu.fecha_asignacion DESC
        ");
        if (!$stmt) throw new Exception("Error en prepare (proyectos): " . $this->con->error);
        $stmt->bind_param("iii", $id_usuario, $id_usuario, $id_usuario);
        if (!$stmt->execute()) throw new Exception("Error en execute (proyectos): " . $stmt->error);
        $proyectos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        $stmt = $this->con->prepare("
            SELECT tt.descripcion_tipo AS seccion, et.nombre AS estado,
                   t.fecha_entrega, tu.contenido,
                   p.titulo AS proyecto_titulo, p.id_proyectos
            FROM tareas_usuarios tu
            JOIN tareas t          ON t.id_tarea      = tu.id_tarea
            JOIN tipo_tarea tt     ON tt.id_tareatipo = t.id_tareatipo
            JOIN estados_tarea et  ON et.id_estadoT   = tu.id_estadoT
            JOIN tbl_seguimiento ts ON ts.id_avances  = t.id_avances
            JOIN proyectos p       ON p.id_proyectos  = ts.id_proyectos
            WHERE tu.id_usuarios = ?
            ORDER BY p.id_proyectos ASC, t.id_tarea ASC
        ");
        if (!$stmt) throw new Exception("Error en prepare (tareas): " . $this->con->error);
        $stmt->bind_param("i", $id_usuario);
        if (!$stmt->execute()) throw new Exception("Error en execute (tareas): " . $stmt->error);
        $tareas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        $stmt = $this->con->prepare("
            SELECT sp.*, p.titulo AS proyecto_titulo
            FROM solicitud_proyecto sp
            JOIN proyectos p ON p.id_proyectos = sp.id_proyectos
            WHERE sp.id_estudiante = ?
            ORDER BY sp.fecha_envio DESC
        ");
        $stmt->bind_param("i", $id_usuario);
        $stmt->execute();
        $solicitudes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        return compact('usuario', 'proyectos', 'tareas', 'solicitudes');
    }

    // ================================================================
    // RESUMEN POR INVESTIGADOR
    // ================================================================

    public function resumenInvestigadores(array $f): array
    {
        $wherePeriodo  = !empty($f['periodo']) ? "AND p.id_periodos = ?" : "";
        $typesPeriodo  = !empty($f['periodo']) ? "i" : "";
        $paramsPeriodo = !empty($f['periodo']) ? [(int)$f['periodo']] : [];

        $sql = "
            SELECT
                ui.id_usuarios,
                CONCAT(ui.nombre,' ',ui.apellido_paterno,' ',ui.apellido_materno) AS nombre,
                ui.correo_institucional,
                COUNT(p.id_proyectos)               AS total_proyectos,
                SUM(ep.nombre = 'Activo')            AS activos,
                SUM(ep.nombre = 'Por aprobar')       AS por_aprobar,
                SUM(ep.nombre = 'Cierre')            AS cerrados
            FROM usuarios ui
            JOIN investigadores inv ON inv.id_usuarios = ui.id_usuarios
            LEFT JOIN proyectos p ON p.id_investigador = ui.id_usuarios {$wherePeriodo}
            LEFT JOIN estados_proyectos ep ON ep.id_estadoP = p.id_estadoP
            GROUP BY ui.id_usuarios
            ORDER BY total_proyectos DESC
        ";
        $stmt = $this->con->prepare($sql);
        if ($typesPeriodo) $stmt->bind_param($typesPeriodo, ...$paramsPeriodo);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}