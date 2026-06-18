<?php
// Repositorios/SupervisorRepositorio.php

require_once __DIR__ . '/../../../public/incluido/BaseModelo.php';

/**
 * SupervisorRepositorio
 *
 * Responsabilidad exclusiva: ejecutar consultas SQL sobre las tablas
 * del panel de supervisor. No contiene lógica de negocio.
 *
 * Es instanciado por SupervisorModelo mediante inyección por constructor.
 */
class SupervisorRepositorio extends BaseModelo
{
    public function __construct(mysqli $conn)
    {
        parent::__construct($conn);
    }


    // 
    // AUXILIARES PARA FILTROS (selects)
    // 

    /**
     * Lista de periodos con su estado calculado (Activo / Terminado).
     *
     * @return array[]
     */
    public function obtenerPeriodos(): array
    {
        return $this->ejecutar(
            "SELECT id_periodos, periodo,
                    CASE
                        WHEN CURDATE() BETWEEN fecha_inicio AND fecha_final THEN 'Activo'
                        ELSE 'Terminado'
                    END AS estado
             FROM periodos
             ORDER BY id_periodos DESC"
        );
    }

    /**
     * Lista de investigadores con nombre completo.
     *
     * @return array[]
     */
    public function obtenerInvestigadores(): array
    {
        return $this->ejecutar(
            "SELECT i.id_usuarios,
                    CONCAT(u.nombre,' ',u.apellido_paterno,' ',u.apellido_materno) AS nombre_completo
             FROM investigadores i
             JOIN usuarios u ON u.id_usuarios = i.id_usuarios
             ORDER BY u.nombre ASC"
        );
    }

    /**
     * Lista de estados de proyecto.
     *
     * @return array[]
     */
    public function obtenerEstadosProyecto(): array
    {
        return $this->ejecutar(
            "SELECT id_estadoP, nombre FROM estados_proyectos ORDER BY nombre"
        );
    }

    /**
     * Lista de carreras activas.
     *
     * @return array[]
     */
    public function obtenerCarreras(): array
    {
        return $this->ejecutar(
            "SELECT id_carrera, nombre_carrera FROM carreras WHERE estado = 1 ORDER BY nombre_carrera"
        );
    }


    // 
    // RESUMEN GLOBAL
    // 

    /**
     * Resumen de proyectos (totales y por estado) filtrado opcionalmente por periodo.
     *
     * @return array
     */
    public function resumenProyectos(string $whereP, array $paramsP, string $typesP): array
    {
        $sql = "SELECT
                    COUNT(*)                              AS total_proyectos,
                    SUM(ep.nombre = 'Activo')             AS activos,
                    SUM(ep.nombre = 'Por aprobar')        AS por_aprobar,
                    SUM(ep.nombre = 'Por cerrar')         AS por_cerrar,
                    SUM(ep.nombre = 'Rechazado')          AS rechazados,
                    SUM(ep.nombre = 'Vencido')            AS vencidos,
                    SUM(ep.nombre = 'Cierre')             AS cerrados
                FROM proyectos p
                JOIN estados_proyectos ep ON ep.id_estadoP = p.id_estadoP
                WHERE 1=1 {$whereP}";

        $fila = $this->ejecutar($sql, $typesP, $paramsP, false);
        return $fila ?? [];
    }

    /**
     * Resumen de estudiantes únicos con proyectos, filtrado opcionalmente por periodo.
     *
     * @return array
     */
    public function resumenEstudiantes(string $whereP, array $paramsP, string $typesP): array
    {
        $sql = "SELECT
                    COUNT(DISTINCT pu.id_usuarios)      AS total_estudiantes,
                    SUM(pu.estado = 'activo')            AS activos,
                    SUM(pu.estado = 'concluido')         AS concluidos,
                    SUM(pu.estado = 'baja')              AS bajas,
                    SUM(pu.estado = 'cancelado')         AS cancelados
                FROM proyectos_usuarios pu
                JOIN proyectos p ON p.id_proyectos = pu.id_proyectos
                WHERE 1=1 {$whereP}";

        $fila = $this->ejecutar($sql, $typesP, $paramsP, false);
        return $fila ?? [];
    }

    /**
     * Resumen de solicitudes de integración, filtrado opcionalmente por periodo.
     *
     * @return array
     */
    public function resumenSolicitudes(string $whereP, array $paramsP, string $typesP): array
    {
        $sql = "SELECT
                    COUNT(*)                           AS total_solicitudes,
                    SUM(sp.estado = 'pendiente')       AS pendientes,
                    SUM(sp.estado = 'en_revision')     AS en_revision,
                    SUM(sp.estado = 'correcciones')    AS correcciones,
                    SUM(sp.estado = 'aceptado')        AS aceptadas,
                    SUM(sp.estado = 'rechazado')       AS rechazadas
                FROM solicitud_proyecto sp
                JOIN proyectos p ON p.id_proyectos = sp.id_proyectos
                WHERE 1=1 {$whereP}";

        $fila = $this->ejecutar($sql, $typesP, $paramsP, false);
        return $fila ?? [];
    }

    /**
     * Resumen de tareas / secciones del documento, filtrado opcionalmente por periodo.
     * El JOIN con proyectos varía según si hay filtro de periodo.
     *
     * @return array
     */
    public function resumenTareas(string $sqlPeriodoJoin, array $paramsP, string $typesP): array
    {
        $sql = "SELECT
                    COUNT(*)                              AS total_tareas,
                    SUM(et.nombre = 'Pendiente')          AS pendientes,
                    SUM(et.nombre = 'Entregado')          AS entregadas,
                    SUM(et.nombre = 'Revisar')            AS en_revision,
                    SUM(et.nombre = 'Corregir')           AS corregir,
                    SUM(et.nombre = 'Aprobado')           AS aprobadas,
                    SUM(et.nombre = 'Vencido')            AS vencidas
                FROM tareas t
                JOIN estados_tarea et       ON et.id_estadoT = t.id_estadoT
                JOIN tbl_seguimiento ts     ON ts.id_avances = t.id_avances
                {$sqlPeriodoJoin}
                WHERE et.nombre != 'Sin activar'";

        $fila = $this->ejecutar($sql, $typesP, $paramsP, false);
        return $fila ?? [];
    }


    // 
    // PROYECTOS — tabla paginada con filtros
    // 

    /**
     * Cuenta proyectos según los filtros dados.
     *
     * @return int
     */
    public function contarProyectos(string $where, array $params, string $types): int
    {
        $sql = "SELECT COUNT(*)
                FROM proyectos p
                JOIN estados_proyectos ep ON ep.id_estadoP  = p.id_estadoP
                JOIN usuarios ui          ON ui.id_usuarios = p.id_investigador
                {$where}";

        $fila = $this->ejecutar($sql, $types, $params, false);
        return (int)array_values($fila ?? [0])[0];
    }

    /**
     * Lista paginada de proyectos con subqueries de conteo.
     *
     * @return array[]
     */
    public function listarProyectos(string $where, array $params, string $types, int $desde, int $limite): array
    {
        $sql = "SELECT
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
                LIMIT ?, ?";

        $params[] = $desde;
        $params[] = $limite;
        $types   .= 'ii';

        return $this->ejecutar($sql, $types, $params);
    }


    // 
    // DETALLE PROYECTO
    // 

    /**
     * Datos generales del proyecto (cabecera).
     *
     * @return array
     */
    public function detalleProyectoCabecera(int $id_proyecto): array
    {
        $fila = $this->ejecutar(
            "SELECT p.*, ep.nombre AS estado,
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
             WHERE p.id_proyectos = ?",
            'i',
            [$id_proyecto],
            false
        );
        return $fila ?? [];
    }

    /**
     * Estudiantes del proyecto con conteo de tareas.
     *
     * @return array[]
     */
    public function detalleEstudiantesDeProyecto(int $id_proyecto): array
    {
        return $this->ejecutar(
            "SELECT
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
            JOIN usuarios u    ON u.id_usuarios  = pu.id_usuarios
            JOIN estudiantes e ON e.id_usuarios  = pu.id_usuarios
            JOIN carreras c    ON c.id_carrera   = e.id_carrera
            WHERE pu.id_proyectos = ?
            ORDER BY pu.estado ASC, u.nombre ASC",
            'iiii',
            [$id_proyecto, $id_proyecto, $id_proyecto, $id_proyecto]
        );
    }

    /**
     * Solicitudes de integración del proyecto.
     *
     * @return array[]
     */
    public function detalleSolicitudesDeProyecto(int $id_proyecto): array
    {
        return $this->ejecutar(
            "SELECT sp.*,
                    CONCAT(u.nombre,' ',u.apellido_paterno,' ',u.apellido_materno) AS estudiante_nombre,
                    e.matricula, c.nombre_carrera AS carrera
             FROM solicitud_proyecto sp
             JOIN usuarios u    ON u.id_usuarios  = sp.id_estudiante
             JOIN estudiantes e ON e.id_usuarios  = sp.id_estudiante
             JOIN carreras c    ON c.id_carrera   = e.id_carrera
             WHERE sp.id_proyectos = ?
             ORDER BY sp.fecha_envio DESC",
            'i',
            [$id_proyecto]
        );
    }

    /**
     * Tareas del proyecto con resumen de estados por estudiante.
     *
     * @return array[]
     */
    public function detalleTareasDeProyecto(int $id_proyecto): array
    {
        return $this->ejecutar(
            "SELECT t.id_tarea, tt.descripcion_tipo AS tipo, et.nombre AS estado,
                    t.fecha_entrega, t.descripcion,
                    COUNT(tu.id_asignacion)          AS total_asignados,
                    SUM(etu.nombre = 'Aprobado')      AS aprobados,
                    SUM(etu.nombre = 'Entregado')     AS entregados,
                    SUM(etu.nombre = 'Revisar')       AS en_revision,
                    SUM(etu.nombre = 'Corregir')      AS en_correccion,
                    SUM(etu.nombre = 'Pendiente')     AS pendientes,
                    SUM(etu.nombre = 'Vencido')       AS vencidos
             FROM tareas t
             JOIN tipo_tarea tt          ON tt.id_tareatipo = t.id_tareatipo
             JOIN estados_tarea et       ON et.id_estadoT   = t.id_estadoT
             JOIN tbl_seguimiento ts     ON ts.id_avances   = t.id_avances
             LEFT JOIN tareas_usuarios tu ON tu.id_tarea    = t.id_tarea
             LEFT JOIN estados_tarea etu ON etu.id_estadoT  = tu.id_estadoT
             WHERE ts.id_proyectos = ? AND et.nombre != 'Sin activar'
             GROUP BY t.id_tarea
             ORDER BY t.id_tarea ASC",
            'i',
            [$id_proyecto]
        );
    }

    /**
     * Historial reciente del proyecto (últimas 20 entradas).
     *
     * @return array[]
     */
    public function detalleHistorialDeProyecto(int $id_proyecto): array
    {
        return $this->ejecutar(
            "SELECT hp.*,
                    CONCAT(u.nombre,' ',u.apellido_paterno) AS usuario_nombre,
                    ep.nombre AS estado_nombre
             FROM historial_proyectos_usuarios hp
             LEFT JOIN usuarios u             ON u.id_usuarios  = hp.id_historial
             JOIN proyectos AS p              ON p.id_proyectos = hp.id_proyectos
             LEFT JOIN estados_proyectos ep   ON p.id_estadoP   = ep.id_estadoP
             WHERE hp.id_proyectos = ?
             ORDER BY hp.fecha DESC
             LIMIT 20",
            'i',
            [$id_proyecto]
        );
    }


    // 
    // SOLICITUDES — tabla paginada con filtros
    // 

    /**
     * Cuenta solicitudes según los filtros dados.
     *
     * @return int
     */
    public function contarSolicitudes(string $where, array $params, string $types): int
    {
        $sql = "SELECT COUNT(*)
                FROM solicitud_proyecto sp
                JOIN proyectos p    ON p.id_proyectos  = sp.id_proyectos
                JOIN usuarios u     ON u.id_usuarios   = sp.id_estudiante
                JOIN estudiantes e  ON e.id_usuarios   = sp.id_estudiante
                JOIN usuarios ui    ON ui.id_usuarios  = p.id_investigador
                {$where}";

        $fila = $this->ejecutar($sql, $types, $params, false);
        return (int)array_values($fila ?? [0])[0];
    }

    /**
     * Lista paginada de solicitudes.
     *
     * @return array[]
     */
    public function listarSolicitudes(string $where, array $params, string $types, int $desde, int $limite): array
    {
        $sql = "SELECT
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
                LIMIT ?, ?";

        $params[] = $desde;
        $params[] = $limite;
        $types   .= 'ii';

        return $this->ejecutar($sql, $types, $params);
    }


    // 
    // ETAPAS — resumen por tipo_documento y tareas
    // 

    /**
     * Resumen de alumnos por etapa (tipo_documento), filtrado por periodo.
     *
     * @return array[]
     */
    public function resumenEtapas(string $whereP, array $paramsP, string $typesP): array
    {
        $sql = "SELECT
                    td.id_tipo_documento,
                    td.nombre   AS etapa,
                    td.categoria,
                    COUNT(sd.id_seguimiento)          AS total,
                    SUM(sd.estado = 'pendiente')       AS pendientes,
                    SUM(sd.estado = 'proceso')         AS en_proceso,
                    SUM(sd.estado = 'completado')      AS completados,
                    SUM(sd.estado = 'rechazado')       AS rechazados
                FROM tipo_documento td
                LEFT JOIN seguimiento_documento sd ON sd.id_tipo_documento = td.id_tipo_documento
                LEFT JOIN proyectos p              ON p.id_proyectos       = sd.id_proyectos
                WHERE td.estado = 1 {$whereP}
                GROUP BY td.id_tipo_documento
                ORDER BY td.orden ASC";

        return $this->ejecutar($sql, $typesP, $paramsP);
    }

    /**
     * Resumen de secciones del documento con todos los estados separados.
     *
     * @return array[]
     */
    public function resumenSecciones(string $sqlPeriodoJoin, array $paramsP, string $typesP): array
    {
        $sql = "SELECT
                    tt.id_tareatipo,
                    tt.descripcion_tipo                  AS seccion,
                    COUNT(DISTINCT t.id_tarea)            AS total_instancias,
                    SUM(et.nombre = 'Aprobado')           AS aprobadas,
                    SUM(et.nombre = 'Entregado')          AS entregadas,
                    SUM(et.nombre = 'Revisar')            AS en_revision,
                    SUM(et.nombre = 'Corregir')           AS correcciones,
                    SUM(et.nombre = 'Pendiente')          AS pendientes,
                    SUM(et.nombre = 'Vencido')            AS vencidas
                FROM tareas t
                JOIN tipo_tarea tt      ON tt.id_tareatipo = t.id_tareatipo
                JOIN estados_tarea et   ON et.id_estadoT   = t.id_estadoT
                JOIN tbl_seguimiento ts ON ts.id_avances   = t.id_avances
                {$sqlPeriodoJoin}
                WHERE et.nombre != 'Sin activar'
                GROUP BY tt.id_tareatipo
                ORDER BY tt.id_tareatipo ASC";

        return $this->ejecutar($sql, $typesP, $paramsP);
    }


    // 
    // ESTUDIANTES — tabla paginada con filtros
    // 

    /**
     * Cuenta estudiantes según los filtros dados.
     *
     * @return int
     */
    public function contarEstudiantes(string $where, array $params, string $types): int
    {
        $sql = "SELECT COUNT(*)
                FROM estudiantes e
                JOIN usuarios u ON u.id_usuarios = e.id_usuarios
                {$where}";

        $fila = $this->ejecutar($sql, $types, $params, false);
        return (int)array_values($fila ?? [0])[0];
    }

    /**
     * Lista paginada de estudiantes con conteo de proyectos y tareas.
     *
     * @return array[]
     */
    public function listarEstudiantes(string $where, array $params, string $types, int $desde, int $limite): array
    {
        $sql = "SELECT
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
                LIMIT ?, ?";

        $params[] = $desde;
        $params[] = $limite;
        $types   .= 'ii';

        return $this->ejecutar($sql, $types, $params);
    }


    // 
    // DETALLE ESTUDIANTE
    // 

    /**
     * Datos generales del estudiante.
     *
     * @return array
     */
    public function detalleUsuario(int $id_usuario): array
    {
        $fila = $this->ejecutar(
            "SELECT u.*, e.matricula, c.nombre_carrera AS carrera, g.genero, ep.estado AS estado_proceso
             FROM usuarios u
             JOIN estudiantes e         ON e.id_usuarios           = u.id_usuarios
             JOIN carreras c            ON c.id_carrera            = e.id_carrera
             JOIN proyectos_usuarios pu ON u.id_usuarios           = e.id_usuarios
             JOIN estados_proceso ep    ON ep.id_estados_proceso   = pu.id_estados_proceso
             LEFT JOIN genero_usuario g ON g.id_genero             = u.id_genero
             WHERE u.id_usuarios = ?",
            'i',
            [$id_usuario],
            false
        );
        return $fila ?? [];
    }

    /**
     * Proyectos del estudiante con conteo de tareas.
     *
     * @return array[]
     */
    public function detalleProyectosDeEstudiante(int $id_usuario): array
    {
        return $this->ejecutar(
            "SELECT p.id_proyectos, p.titulo, p.modalidad, p.fecha_inicio, p.fecha_fin,
                    ep.nombre AS estado_proyecto,
                    per.periodo,
                    pu.estado AS estado_participacion, pu.fecha_asignacion, pu.fecha_terminacion,
                    CONCAT(ui.nombre,' ',ui.apellido_paterno) AS investigador_nombre,
                    (SELECT COUNT(*) FROM tareas_usuarios tu2
                     JOIN tareas t2 ON t2.id_tarea = tu2.id_tarea
                     JOIN tbl_seguimiento ts2 ON ts2.id_avances = t2.id_avances
                     JOIN estados_tarea et2 ON et2.id_estadoT = tu2.id_estadoT
                     WHERE ts2.id_proyectos = p.id_proyectos AND tu2.id_usuarios = ?
                       AND et2.nombre != 'Sin activar') AS tareas_total,
                    (SELECT COUNT(*) FROM tareas_usuarios tu3
                     JOIN tareas t3 ON t3.id_tarea = tu3.id_tarea
                     JOIN tbl_seguimiento ts3 ON ts3.id_avances = t3.id_avances
                     JOIN estados_tarea et3 ON et3.id_estadoT = tu3.id_estadoT
                     WHERE ts3.id_proyectos = p.id_proyectos AND tu3.id_usuarios = ?
                       AND et3.nombre = 'Aprobado') AS tareas_aprobadas
             FROM proyectos_usuarios pu
             JOIN proyectos p          ON p.id_proyectos  = pu.id_proyectos
             JOIN estados_proyectos ep ON ep.id_estadoP   = p.id_estadoP
             JOIN periodos per         ON per.id_periodos = p.id_periodos
             JOIN usuarios ui          ON ui.id_usuarios  = p.id_investigador
             WHERE pu.id_usuarios = ?
             ORDER BY pu.fecha_asignacion DESC",
            'iii',
            [$id_usuario, $id_usuario, $id_usuario]
        );
    }

    /**
     * Tareas del estudiante en todos sus proyectos.
     *
     * @return array[]
     */
    public function detalleTareasDeEstudiante(int $id_usuario): array
    {
        return $this->ejecutar(
            "SELECT tt.descripcion_tipo AS seccion, et.nombre AS estado,
                    t.fecha_entrega, tu.contenido,
                    p.titulo AS proyecto_titulo, p.id_proyectos
             FROM tareas_usuarios tu
             JOIN tareas t          ON t.id_tarea      = tu.id_tarea
             JOIN tipo_tarea tt     ON tt.id_tareatipo = t.id_tareatipo
             JOIN estados_tarea et  ON et.id_estadoT   = tu.id_estadoT
             JOIN tbl_seguimiento ts ON ts.id_avances  = t.id_avances
             JOIN proyectos p       ON p.id_proyectos  = ts.id_proyectos
             WHERE tu.id_usuarios = ?
             ORDER BY p.id_proyectos ASC, t.id_tarea ASC",
            'i',
            [$id_usuario]
        );
    }

    /**
     * Solicitudes de integración del estudiante en todos sus proyectos.
     *
     * @return array[]
     */
    public function detalleSolicitudesDeEstudiante(int $id_usuario): array
    {
        return $this->ejecutar(
            "SELECT sp.*, p.titulo AS proyecto_titulo
             FROM solicitud_proyecto sp
             JOIN proyectos p ON p.id_proyectos = sp.id_proyectos
             WHERE sp.id_estudiante = ?
             ORDER BY sp.fecha_envio DESC",
            'i',
            [$id_usuario]
        );
    }


    // 
    // RESUMEN POR INVESTIGADOR
    // 

    /**
     * Lista de investigadores con sus conteos de proyectos, filtrado por periodo.
     *
     * @return array[]
     */
    public function resumenInvestigadores(string $whereP, array $paramsP, string $typesP): array
    {
        $sql = "SELECT
                    ui.id_usuarios,
                    CONCAT(ui.nombre,' ',ui.apellido_paterno,' ',ui.apellido_materno) AS nombre,
                    ui.correo_institucional,
                    COUNT(p.id_proyectos)               AS total_proyectos,
                    SUM(ep.nombre = 'Activo')            AS activos,
                    SUM(ep.nombre = 'Por aprobar')       AS por_aprobar,
                    SUM(ep.nombre = 'Cierre')            AS cerrados
                FROM usuarios ui
                JOIN investigadores inv ON inv.id_usuarios = ui.id_usuarios
                LEFT JOIN proyectos p ON p.id_investigador = ui.id_usuarios {$whereP}
                LEFT JOIN estados_proyectos ep ON ep.id_estadoP = p.id_estadoP
                GROUP BY ui.id_usuarios
                ORDER BY total_proyectos DESC";

        return $this->ejecutar($sql, $typesP, $paramsP);
    }
}