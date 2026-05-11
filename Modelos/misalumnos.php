<?php

/**
 * MisAlumnosModelo.php
 * Modelo para el módulo "Mis Alumnos" del investigador.
 * Solo lectura — sin acciones de baja/reactivación.
 * Ruta sugerida: /ITSFCP-PROYECTOS/Modelos/MisAlumnosModelo.php
 */
class misalumnos
{
    private mysqli $con;

    public function __construct(mysqli $conn)
    {
        $this->con = $conn;
    }

    // ================================================================
    // HELPER INTERNO — ejecuta un SELECT preparado y devuelve filas
    // ================================================================

    /**
     * Prepara, ejecuta y retorna resultados de una consulta SELECT.
     *
     * @param string $sql    Consulta con placeholders ?
     * @param string $types  Cadena de tipos: "iiss"...
     * @param array  $params Valores a enlazar
     * @param bool   $single true → fetch_assoc(), false → fetch_all()
     */
    private function ejecutar(
        string $sql,
        string $types  = "",
        array  $params = [],
        bool   $single = false
    ): array {
        $stmt = $this->con->prepare($sql);
        if (!$stmt) {
            throw new RuntimeException("Error prepare(): " . $this->con->error);
        }
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        if (!$stmt->execute()) {
            throw new RuntimeException("Error execute(): " . $stmt->error);
        }
        $res = $stmt->get_result();
        return $single ? ($res->fetch_assoc() ?? []) : ($res->fetch_all(MYSQLI_ASSOC) ?? []);
    }

    // ================================================================
    // CATÁLOGOS — selects de filtro
    // ================================================================

    /**
     * Todos los periodos ordenados de más reciente a más antiguo.
     * Incluye etiqueta "Activo / Terminado" para el select.
     */
    public function obtenerPeriodos(): array
    {
        return $this->ejecutar("
            SELECT
                id_periodos,
                periodo,
                CASE
                    WHEN CURDATE() BETWEEN fecha_inicio AND fecha_final THEN 'Activo'
                    ELSE 'Terminado'
                END AS estado_periodo
            FROM periodos
            ORDER BY id_periodos DESC
        ");
    }

    /**
     * Proyectos activos del investigador para el select de filtro por proyecto.
     * Se filtra por periodo si se recibe uno; de lo contrario devuelve todos.
     */
    public function obtenerProyectosInvestigador(int $id_investigador, int $id_periodo = 0): array
    {
        $wherePeriodo = $id_periodo ? "AND p.id_periodos = ?" : "";
        $types  = $id_periodo ? "ii" : "i";
        $params = $id_periodo ? [$id_investigador, $id_periodo] : [$id_investigador];

        return $this->ejecutar("
            SELECT p.id_proyectos, p.titulo
            FROM proyectos p
            WHERE p.id_investigador = ? $wherePeriodo
            ORDER BY p.titulo ASC
        ", $types, $params);
    }

    /**
     * Carreras activas para el select de filtro.
     */
    public function obtenerCarreras(): array
    {
        return $this->ejecutar("
            SELECT id_carrera, nombre_carrera
            FROM carreras
            WHERE estado = 1
            ORDER BY nombre_carrera ASC
        ");
    }

    // ================================================================
    // RESUMEN — tarjetas superiores del dashboard
    // ================================================================

    /**
     * Conteos globales de alumnos del investigador según filtros activos.
     * Una fila por combinación (estudiante × proyecto), por eso los totales
     * representan participaciones, no alumnos únicos.
     */
    public function resumenAlumnos(int $id_investigador, array $f): array
    {
        [$where, $params, $types] = $this->buildWheres($id_investigador, $f);

        $row = $this->ejecutar("
            SELECT
                COUNT(*)                                        AS total_participaciones,
                COUNT(DISTINCT pu.id_usuarios)                  AS alumnos_unicos,
                SUM(pu.estado = 'activo')                       AS activos,
                SUM(pu.estado = 'concluido')                    AS concluidos,
                SUM(pu.estado = 'baja')                         AS bajas
            FROM proyectos_usuarios pu
            JOIN proyectos  p  ON p.id_proyectos  = pu.id_proyectos
            JOIN periodos   per ON per.id_periodos = p.id_periodos
            JOIN usuarios   u  ON u.id_usuarios   = pu.id_usuarios
            JOIN estudiantes e ON e.id_usuarios   = pu.id_usuarios
            JOIN carreras   c  ON c.id_carrera    = e.id_carrera
            JOIN estados_proceso ep ON ep.id_estados_proceso = pu.id_estados_proceso
            $where
        ", $types, $params, true);

        return $row ?: [
            'total_participaciones' => 0,
            'alumnos_unicos'        => 0,
            'activos'               => 0,
            'concluidos'            => 0,
            'bajas'                 => 0,
        ];
    }

    // ================================================================
    // LISTADO PAGINADO
    // ================================================================

    /**
     * Cuenta total de filas (sin LIMIT) para la paginación.
     */
    public function contarAlumnos(int $id_investigador, array $f): int
    {
        [$where, $params, $types] = $this->buildWheres($id_investigador, $f);

        $row = $this->ejecutar("
            SELECT COUNT(*) AS total
            FROM proyectos_usuarios pu
            JOIN proyectos  p  ON p.id_proyectos  = pu.id_proyectos
            JOIN periodos   per ON per.id_periodos = p.id_periodos
            JOIN usuarios   u  ON u.id_usuarios   = pu.id_usuarios
            JOIN estudiantes e ON e.id_usuarios   = pu.id_usuarios
            JOIN carreras   c  ON c.id_carrera    = e.id_carrera
            JOIN estados_proceso ep ON ep.id_estados_proceso = pu.id_estados_proceso
            $where
        ", $types, $params, true);

        return (int)($row['total'] ?? 0);
    }

    /**
     * Lista paginada de participaciones (alumno × proyecto).
     * Cada fila incluye avance de tareas y estado del proceso.
     *
     * @param int   $id_investigador  ID del investigador en sesión
     * @param array $f                Filtros activos
     * @param int   $desde            Offset para LIMIT
     * @param int   $limite           Tamaño de página
     */
    public function obtenerAlumnos(
        int $id_investigador,
        array $f,
        int $desde,
        int $limite
    ): array {
        [$where, $params, $types] = $this->buildWheres($id_investigador, $f);

        // Añadimos los dos parámetros del LIMIT al final
        $params[] = $desde;
        $params[] = $limite;
        $types   .= "ii";

        return $this->ejecutar("
            SELECT
                -- Identificadores de navegación
                pu.id_integrante,
                pu.id_proyectos,
                pu.id_usuarios,

                -- Datos del estudiante
                CONCAT(u.nombre, ' ', u.apellido_paterno, ' ', u.apellido_materno)
                    AS nombre_completo,
                u.correo_institucional,
                e.matricula,
                c.nombre_carrera                     AS carrera,

                -- Estado de participación en el proyecto
                pu.estado                            AS estado_participacion,
                pu.fecha_asignacion,
                pu.fecha_terminacion,

                -- Estado del proceso de cierre (etapa del alumno)
                ep.estado                            AS estado_proceso,

                -- Datos del proyecto
                p.titulo                             AS titulo_proyecto,
                p.modalidad,
                per.periodo,
                CASE
                    WHEN CURDATE() BETWEEN per.fecha_inicio AND per.fecha_final THEN 'Activo'
                    ELSE 'Terminado'
                END                                  AS estado_periodo,

                -- Avance: tareas aprobadas / total tareas asignadas al alumno en este proyecto
                (
                    SELECT COUNT(*)
                    FROM tareas_usuarios tu2
                    JOIN tareas t2 ON t2.id_tarea = tu2.id_tarea
                    JOIN tbl_seguimiento ts2 ON ts2.id_avances = t2.id_avances
                    WHERE ts2.id_proyectos = pu.id_proyectos
                      AND tu2.id_usuarios  = pu.id_usuarios
                ) AS tareas_total,
                (
                    SELECT COUNT(*)
                    FROM tareas_usuarios tu3
                    JOIN tareas t3 ON t3.id_tarea = tu3.id_tarea
                    JOIN tbl_seguimiento ts3 ON ts3.id_avances = t3.id_avances
                    JOIN estados_tarea et3 ON et3.id_estadoT = tu3.id_estadoT
                    WHERE ts3.id_proyectos = pu.id_proyectos
                      AND tu3.id_usuarios  = pu.id_usuarios
                      AND et3.nombre = 'Aprobado'
                ) AS tareas_aprobadas

            FROM proyectos_usuarios pu
            JOIN proyectos  p   ON p.id_proyectos   = pu.id_proyectos
            JOIN periodos   per ON per.id_periodos   = p.id_periodos
            JOIN usuarios   u   ON u.id_usuarios     = pu.id_usuarios
            JOIN estudiantes e  ON e.id_usuarios     = pu.id_usuarios
            JOIN carreras   c   ON c.id_carrera      = e.id_carrera
            JOIN estados_proceso ep ON ep.id_estados_proceso = pu.id_estados_proceso
            $where
            ORDER BY
                FIELD(pu.estado, 'activo', 'baja', 'concluido', 'cancelado'),
                per.id_periodos DESC,
                u.nombre ASC
            LIMIT ?, ?
        ", $types, $params);
    }

    // ================================================================
    // CONSTRUCTOR DE CLÁUSULAS WHERE (reutilizable)
    // ================================================================

    /**
     * Construye la cláusula WHERE, los params y los tipos para los filtros.
     * Siempre restringe por investigador.
     *
     * @return array [string $where, array $params, string $types]
     */
    private function buildWheres(int $id_investigador, array $f): array
    {
        // Siempre filtra por investigador
        $cond   = ["p.id_investigador = ?"];
        $params = [$id_investigador];
        $types  = "i";

        // Filtro por periodo (independiente y prioritario)
        if (!empty($f['periodo'])) {
            $cond[]   = "p.id_periodos = ?";
            $params[] = (int)$f['periodo'];
            $types   .= "i";
        }

        // Filtro por proyecto específico
        if (!empty($f['id_proyecto'])) {
            $cond[]   = "pu.id_proyectos = ?";
            $params[] = (int)$f['id_proyecto'];
            $types   .= "i";
        }

        // Filtro por estado de participación
        if (!empty($f['estado'])) {
            $permitidos = ['activo', 'concluido', 'baja', 'cancelado'];
            if (in_array($f['estado'], $permitidos, true)) {
                $cond[]   = "pu.estado = ?";
                $params[] = $f['estado'];
                $types   .= "s";
            }
        }

        // Filtro por estado del proceso
        if (!empty($f['estado_proceso'])) {
            $cond[]   = "ep.estado = ?";
            $params[] = $f['estado_proceso'];
            $types   .= "s";
        }

        // Filtro por carrera
        if (!empty($f['carrera'])) {
            $cond[]   = "e.id_carrera = ?";
            $params[] = (int)$f['carrera'];
            $types   .= "i";
        }

        // Búsqueda por nombre, matrícula o correo
        if (!empty($f['buscar'])) {
            $like     = "%" . $f['buscar'] . "%";
            $cond[]   = "(u.nombre LIKE ? OR u.apellido_paterno LIKE ? OR e.matricula LIKE ? OR u.correo_institucional LIKE ?)";
            array_push($params, $like, $like, $like, $like);
            $types   .= "ssss";
        }

        return ["WHERE " . implode(" AND ", $cond), $params, $types];
    }
}