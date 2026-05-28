<?php
// Modelos/principal.php

require_once __DIR__ . '/../publico/config/conexion.php';
require_once __DIR__ . '/BaseModelo.php';

class principal extends BaseModelo
{

    // 
    //  CATÁLOGOS PARA EL FILTRO
    // 

    /** Temáticas activas (para el <select> de temática) */
    public function obtenerTematicas(): array
    {
        return $this->ejecutar(
            "SELECT id_tematica, nombre_tematica FROM tematica WHERE estado = 1 ORDER BY nombre_tematica ASC"
        );
    }

    /** Subtémáticas activas, opcionalmente filtradas por temática */
    public function obtenerSubtematicas(int $id_tematica = 0): array
    {
        if ($id_tematica > 0) {
            return $this->ejecutar(
                "SELECT id_subtematica, nombre_subtematica
                 FROM subtematica WHERE estado = 1 AND id_tematica = ?
                 ORDER BY nombre_subtematica ASC",
                "i",
                [$id_tematica]
            );
        }

        return $this->ejecutar(
            "SELECT id_subtematica, nombre_subtematica
             FROM subtematica WHERE estado = 1
             ORDER BY nombre_subtematica ASC"
        );
    }

    // 
    //  ROL: INVESTIGADOR / SUPERVISOR
    // 

    public function obtenerProyectosInvestigador(
        int    $id_investigador,
        string $rol,
        string $buscar         = '',
        string $modalidad      = '',
        int    $id_tematica    = 0,
        int    $id_subtematica = 0,
        int    $pagina         = 1,
        int    $por_pagina     = 30
    ): array {
        $desde = ($pagina - 1) * $por_pagina;

        $filtroInvestigador = ($rol === 'supervisor') ? "" : "AND p.id_investigador = ?";
        $filtroBuscar       = $buscar         ? "AND (p.titulo LIKE ? OR p.descripcion LIKE ?)" : "";
        $filtroModalidad    = $modalidad      ? "AND p.modalidad = ?"                           : "";
        $filtroTematica     = $id_tematica    ? "AND t.id_tematica = ?"                         : "";
        $filtroSubt         = $id_subtematica ? "AND ps.id_subtematica = ?"                     : "";

        $sql = "
            SELECT
                p.id_proyectos,
                p.titulo,
                p.descripcion,
                p.modalidad,
                p.cantidad_estudiante,
                p.fecha_inicio,
                p.fecha_fin,
                DATE_FORMAT(p.creado_en, '%d/%m/%Y')         AS fecha_creacion,
                ep.nombre                                     AS estado_proyecto,
                p.id_estadoP,
                i.nombre                                      AS instituto,
                CONCAT(u.nombre, ' ', u.apellido_paterno)     AS investigador,
                per.periodo,
                (
                    SELECT t2.nombre_tematica
                    FROM proyectos_subtematica ps2
                    JOIN subtematica st2 ON st2.id_subtematica = ps2.id_subtematica
                    JOIN tematica t2     ON t2.id_tematica     = st2.id_tematica
                    WHERE ps2.id_proyectos = p.id_proyectos
                    LIMIT 1
                ) AS tematica,
                (
                    SELECT st3.nombre_subtematica
                    FROM proyectos_subtematica ps3
                    JOIN subtematica st3 ON st3.id_subtematica = ps3.id_subtematica
                    WHERE ps3.id_proyectos = p.id_proyectos
                    LIMIT 1
                ) AS subtematica,
                (
                    SELECT COUNT(*) FROM proyectos_usuarios pu
                    WHERE pu.id_proyectos = p.id_proyectos AND pu.estado = 'activo'
                ) AS inscritos_actuales,
                p.cantidad_estudiante - (
                    SELECT COUNT(*) FROM proyectos_usuarios pu
                    WHERE pu.id_proyectos = p.id_proyectos AND pu.estado = 'activo'
                ) AS lugares_disponibles
            FROM proyectos p
            INNER JOIN estados_proyectos ep ON ep.id_estadoP  = p.id_estadoP
            INNER JOIN instituto i          ON i.id_instituto  = p.id_instituto
            INNER JOIN usuarios u           ON u.id_usuarios   = p.id_investigador
            INNER JOIN periodos per         ON per.id_periodos = p.id_periodos
            LEFT JOIN proyectos_subtematica ps ON ps.id_proyectos  = p.id_proyectos
            LEFT JOIN subtematica st           ON st.id_subtematica = ps.id_subtematica
            LEFT JOIN tematica t               ON t.id_tematica     = st.id_tematica
            WHERE p.id_estadoP NOT IN (4)
            {$filtroInvestigador}
            {$filtroBuscar}
            {$filtroModalidad}
            {$filtroTematica}
            {$filtroSubt}
            GROUP BY p.id_proyectos
            ORDER BY FIELD(p.id_estadoP, 2, 5, 3, 1, 6) ASC, p.creado_en DESC
            LIMIT ?, ?
        ";

        $tipos  = "";
        $params = [];

        if ($rol !== 'supervisor') {
            $tipos   .= "i";
            $params[] = $id_investigador;
        }
        if ($buscar) {
            $like     = "%{$buscar}%";
            $tipos   .= "ss";
            $params[] = $like;
            $params[] = $like;
        }
        if ($modalidad) {
            $tipos   .= "s";
            $params[] = $modalidad;
        }
        if ($id_tematica) {
            $tipos   .= "i";
            $params[] = $id_tematica;
        }
        if ($id_subtematica) {
            $tipos   .= "i";
            $params[] = $id_subtematica;
        }

        $tipos   .= "ii";
        $params[] = $desde;
        $params[] = $por_pagina;

        return $this->ejecutar($sql, $tipos, $params);
    }

    /** Cuenta total para paginación (investigador/supervisor) */
    public function contarProyectosInvestigador(
        int    $id_investigador,
        string $rol,
        string $buscar         = '',
        string $modalidad      = '',
        int    $id_tematica    = 0,
        int    $id_subtematica = 0
    ): int {
        $filtroInvestigador = ($rol === 'supervisor') ? "" : "AND p.id_investigador = ?";
        $filtroBuscar       = $buscar         ? "AND (p.titulo LIKE ? OR p.descripcion LIKE ?)" : "";
        $filtroModalidad    = $modalidad      ? "AND p.modalidad = ?"                           : "";
        $filtroTematica     = $id_tematica    ? "AND t.id_tematica = ?"                         : "";
        $filtroSubt         = $id_subtematica ? "AND ps.id_subtematica = ?"                     : "";

        $sql = "
            SELECT COUNT(DISTINCT p.id_proyectos) AS total
            FROM proyectos p
            INNER JOIN estados_proyectos ep ON ep.id_estadoP  = p.id_estadoP
            LEFT JOIN proyectos_subtematica ps ON ps.id_proyectos  = p.id_proyectos
            LEFT JOIN subtematica st           ON st.id_subtematica = ps.id_subtematica
            LEFT JOIN tematica t               ON t.id_tematica     = st.id_tematica
            WHERE p.id_estadoP NOT IN (4)
            {$filtroInvestigador}
            {$filtroBuscar}
            {$filtroModalidad}
            {$filtroTematica}
            {$filtroSubt}
        ";

        $tipos  = "";
        $params = [];

        if ($rol !== 'supervisor') {
            $tipos   .= "i";
            $params[] = $id_investigador;
        }
        if ($buscar) {
            $like     = "%{$buscar}%";
            $tipos   .= "ss";
            $params[] = $like;
            $params[] = $like;
        }
        if ($modalidad) {
            $tipos   .= "s";
            $params[] = $modalidad;
        }
        if ($id_tematica) {
            $tipos   .= "i";
            $params[] = $id_tematica;
        }
        if ($id_subtematica) {
            $tipos   .= "i";
            $params[] = $id_subtematica;
        }

        return (int)($this->ejecutar($sql, $tipos, $params, false)['total'] ?? 0);
    }

    // 
    //  ROL: ESTUDIANTE
    // 

    public function obtenerProyectosEstudiante(
        int    $id_estudiante,
        string $buscar         = '',
        string $modalidad      = '',
        int    $id_tematica    = 0,
        int    $id_subtematica = 0,
        int    $pagina         = 1,
        int    $por_pagina     = 30
    ): array {
        $hoy   = date('Y-m-d');
        $desde = ($pagina - 1) * $por_pagina;

        $filtroBuscar    = $buscar         ? "AND (p.titulo LIKE ? OR p.descripcion LIKE ?)" : "";
        $filtroModalidad = $modalidad      ? "AND p.modalidad = ?"                           : "";
        $filtroTematica  = $id_tematica    ? "AND t.id_tematica = ?"                         : "";
        $filtroSubt      = $id_subtematica ? "AND ps.id_subtematica = ?"                     : "";

        $sql = "
            SELECT
                p.id_proyectos,
                ep.id_estadoP,
                ep.nombre AS estado_proyecto,
                p.titulo,
                p.descripcion,
                p.objetivo,
                p.modalidad,
                p.cantidad_estudiante,
                p.fecha_inicio,
                p.fecha_fin,
                p.requisitos,
                p.pre_requisitos,
                DATE_FORMAT(p.creado_en, '%d/%m/%Y')         AS fecha_creacion,
                i.nombre                                      AS instituto,
                CONCAT(u.nombre, ' ', u.apellido_paterno)     AS investigador,
                per.periodo,
                (
                    SELECT t2.nombre_tematica
                    FROM proyectos_subtematica ps2
                    JOIN subtematica st2 ON st2.id_subtematica = ps2.id_subtematica
                    JOIN tematica t2     ON t2.id_tematica     = st2.id_tematica
                    WHERE ps2.id_proyectos = p.id_proyectos
                    LIMIT 1
                ) AS tematica,
                (
                    SELECT st3.nombre_subtematica
                    FROM proyectos_subtematica ps3
                    JOIN subtematica st3 ON st3.id_subtematica = ps3.id_subtematica
                    WHERE ps3.id_proyectos = p.id_proyectos
                    LIMIT 1
                ) AS subtematica,
                p.cantidad_estudiante - (
                    SELECT COUNT(*) FROM proyectos_usuarios pu
                    WHERE pu.id_proyectos = p.id_proyectos AND pu.estado = 'activo'
                ) AS lugares_disponibles,
                (
                    SELECT sp.estado FROM solicitud_proyecto sp
                    WHERE sp.id_proyectos = p.id_proyectos AND sp.id_estudiante = ?
                    ORDER BY sp.id_solicitud_proyecto DESC LIMIT 1
                ) AS mi_solicitud,
                (
                    SELECT COUNT(*) FROM proyectos_usuarios pu
                    WHERE pu.id_proyectos = p.id_proyectos AND pu.id_usuarios = ? AND pu.estado = 'activo'
                ) AS ya_inscrito
            FROM proyectos p
            INNER JOIN estados_proyectos ep ON ep.id_estadoP  = p.id_estadoP
            INNER JOIN instituto i          ON i.id_instituto  = p.id_instituto
            INNER JOIN usuarios u           ON u.id_usuarios   = p.id_investigador
            INNER JOIN periodos per         ON per.id_periodos = p.id_periodos
            LEFT JOIN proyectos_subtematica ps ON ps.id_proyectos  = p.id_proyectos
            LEFT JOIN subtematica st           ON st.id_subtematica = ps.id_subtematica
            LEFT JOIN tematica t               ON t.id_tematica     = st.id_tematica
            WHERE
                p.id_estadoP = 2
                AND per.estado = 1
                AND per.fecha_inicio <= ?
                AND per.fecha_final  >= ?
                AND (per.fecha_inicio_solicitud IS NULL OR per.fecha_inicio_solicitud <= ?)
                AND (per.fecha_fin_solicitud    IS NULL OR per.fecha_fin_solicitud    >= ?)
                AND (
                    SELECT COUNT(*) FROM proyectos_usuarios pu
                    WHERE pu.id_proyectos = p.id_proyectos AND pu.estado = 'activo'
                ) < p.cantidad_estudiante
                {$filtroBuscar}
                {$filtroModalidad}
                {$filtroTematica}
                {$filtroSubt}
            GROUP BY p.id_proyectos
            ORDER BY p.creado_en DESC
            LIMIT ?, ?
        ";

        $tipos  = "iissss";
        $params = [$id_estudiante, $id_estudiante, $hoy, $hoy, $hoy, $hoy];

        if ($buscar) {
            $like     = "%{$buscar}%";
            $tipos   .= "ss";
            $params[] = $like;
            $params[] = $like;
        }
        if ($modalidad) {
            $tipos   .= "s";
            $params[] = $modalidad;
        }
        if ($id_tematica) {
            $tipos   .= "i";
            $params[] = $id_tematica;
        }
        if ($id_subtematica) {
            $tipos   .= "i";
            $params[] = $id_subtematica;
        }

        $tipos   .= "ii";
        $params[] = $desde;
        $params[] = $por_pagina;

        return $this->ejecutar($sql, $tipos, $params);
    }

    /** Cuenta total para paginación (estudiante) */
    public function contarProyectosEstudiante(
        int    $id_estudiante,
        string $buscar         = '',
        string $modalidad      = '',
        int    $id_tematica    = 0,
        int    $id_subtematica = 0
    ): int {
        $hoy = date('Y-m-d');

        $filtroBuscar    = $buscar         ? "AND (p.titulo LIKE ? OR p.descripcion LIKE ?)" : "";
        $filtroModalidad = $modalidad      ? "AND p.modalidad = ?"                           : "";
        $filtroTematica  = $id_tematica    ? "AND t.id_tematica = ?"                         : "";
        $filtroSubt      = $id_subtematica ? "AND ps.id_subtematica = ?"                     : "";

        $sql = "
            SELECT COUNT(DISTINCT p.id_proyectos) AS total
            FROM proyectos p
            INNER JOIN periodos per         ON per.id_periodos = p.id_periodos
            LEFT JOIN proyectos_subtematica ps ON ps.id_proyectos  = p.id_proyectos
            LEFT JOIN subtematica st           ON st.id_subtematica = ps.id_subtematica
            LEFT JOIN tematica t               ON t.id_tematica     = st.id_tematica
            WHERE
                p.id_estadoP = 2
                AND per.estado = 1
                AND per.fecha_inicio <= ?
                AND per.fecha_final  >= ?
                AND (per.fecha_inicio_solicitud IS NULL OR per.fecha_inicio_solicitud <= ?)
                AND (per.fecha_fin_solicitud    IS NULL OR per.fecha_fin_solicitud    >= ?)
                AND (
                    SELECT COUNT(*) FROM proyectos_usuarios pu
                    WHERE pu.id_proyectos = p.id_proyectos AND pu.estado = 'activo'
                ) < p.cantidad_estudiante
                {$filtroBuscar}
                {$filtroModalidad}
                {$filtroTematica}
                {$filtroSubt}
        ";

        $tipos  = "ssss";
        $params = [$hoy, $hoy, $hoy, $hoy];

        if ($buscar) {
            $like     = "%{$buscar}%";
            $tipos   .= "ss";
            $params[] = $like;
            $params[] = $like;
        }
        if ($modalidad) {
            $tipos   .= "s";
            $params[] = $modalidad;
        }
        if ($id_tematica) {
            $tipos   .= "i";
            $params[] = $id_tematica;
        }
        if ($id_subtematica) {
            $tipos   .= "i";
            $params[] = $id_subtematica;
        }

        return (int)($this->ejecutar($sql, $tipos, $params, false)['total'] ?? 0);
    }

    // 
    //  AUXILIARES DE VENTANA
    // 

    public function ventanaSolicitudAbierta(): bool
    {
        $hoy = date('Y-m-d');
        $row = $this->ejecutar(
            "SELECT COUNT(*) AS abierta FROM periodos
             WHERE estado = 1
               AND fecha_inicio <= ? AND fecha_final >= ?
               AND (fecha_inicio_solicitud IS NULL OR fecha_inicio_solicitud <= ?)
               AND (fecha_fin_solicitud    IS NULL OR fecha_fin_solicitud    >= ?)
             LIMIT 1",
            "ssss",
            [$hoy, $hoy, $hoy, $hoy],
            false
        );
        return (int)($row['abierta'] ?? 0) > 0;
    }

    public function ventanaCreacionAbierta(): bool
    {
        $hoy = date('Y-m-d');
        $row = $this->ejecutar(
            "SELECT COUNT(*) AS abierta FROM periodos
             WHERE estado = 1
               AND fecha_inicio <= ? AND fecha_final >= ?
               AND (fecha_inicio_proyectos IS NULL OR fecha_inicio_proyectos <= ?)
               AND (fecha_fin_proyectos    IS NULL OR fecha_fin_proyectos    >= ?)
             LIMIT 1",
            "ssss",
            [$hoy, $hoy, $hoy, $hoy],
            false
        );
        return (int)($row['abierta'] ?? 0) > 0;
    }

    public function ventanaSolicitudAbiertaParaProyecto(int $id_proyecto): bool
    {
        $hoy = date('Y-m-d');
        $row = $this->ejecutar(
            "SELECT COUNT(*) AS abierta
             FROM periodos per
             INNER JOIN proyectos p ON p.id_periodos = per.id_periodos
             WHERE p.id_proyectos = ?
               AND per.estado = 1
               AND per.fecha_inicio <= ?
               AND per.fecha_final  >= ?
               AND (per.fecha_inicio_solicitud IS NULL OR per.fecha_inicio_solicitud <= ?)
               AND (per.fecha_fin_solicitud    IS NULL OR per.fecha_fin_solicitud    >= ?)
             LIMIT 1",
            "issss",
            [$id_proyecto, $hoy, $hoy, $hoy, $hoy],
            false
        );
        return (int)($row['abierta'] ?? 0) > 0;
    }

    // 
    //  DETALLE DE PROYECTO
    // 

    public function obtenerDetalle(int $id_proyecto): ?array
    {
        $row = $this->ejecutar(
            "SELECT
                p.id_proyectos,
                p.id_estadoP,
                ep.nombre                                        AS estado_proyecto,
                p.titulo,
                p.descripcion,
                p.objetivo,
                p.requisitos,
                p.pre_requisitos,
                p.modalidad,
                p.cantidad_estudiante,
                DATE_FORMAT(p.fecha_inicio, '%d/%m/%Y')          AS fecha_inicio,
                DATE_FORMAT(p.fecha_fin,    '%d/%m/%Y')          AS fecha_fin,
                DATE_FORMAT(p.creado_en,    '%d/%m/%Y')          AS fecha_creacion,
                i.nombre                                         AS instituto,
                CONCAT(u.nombre, ' ', u.apellido_paterno)        AS investigador,
                u.correo_institucional                           AS email_investigador,
                per.periodo,
                (
                    SELECT t2.nombre_tematica
                    FROM   proyectos_subtematica ps2
                    JOIN   subtematica  st2 ON st2.id_subtematica = ps2.id_subtematica
                    JOIN   tematica     t2  ON t2.id_tematica     = st2.id_tematica
                    WHERE  ps2.id_proyectos = p.id_proyectos
                    LIMIT  1
                ) AS tematica,
                (
                    SELECT st3.nombre_subtematica
                    FROM   proyectos_subtematica ps3
                    JOIN   subtematica st3 ON st3.id_subtematica = ps3.id_subtematica
                    WHERE  ps3.id_proyectos = p.id_proyectos
                    LIMIT  1
                ) AS subtematica,
                (
                    SELECT COUNT(*) FROM proyectos_usuarios pu
                    WHERE  pu.id_proyectos = p.id_proyectos AND pu.estado = 'activo'
                ) AS inscritos_actuales,
                p.cantidad_estudiante - (
                    SELECT COUNT(*) FROM proyectos_usuarios pu
                    WHERE  pu.id_proyectos = p.id_proyectos AND pu.estado = 'activo'
                ) AS lugares_disponibles
            FROM   proyectos          p
            INNER JOIN estados_proyectos ep  ON ep.id_estadoP  = p.id_estadoP
            INNER JOIN instituto       i     ON i.id_instituto  = p.id_instituto
            INNER JOIN usuarios        u     ON u.id_usuarios   = p.id_investigador
            INNER JOIN periodos        per   ON per.id_periodos = p.id_periodos
            WHERE  p.id_proyectos = ?
            LIMIT  1",
            "i",
            [$id_proyecto],
            false
        );

        return $row ?: null;
    }

    // 
    //  CARGA Y MEMBRESÍA DEL ESTUDIANTE
    // 

    public function obtenerCargaProyectosEstudiante(int $id_estudiante): array
    {
        $activos = (int)($this->ejecutar(
            "SELECT COUNT(*) AS total FROM proyectos_usuarios
             WHERE id_usuarios = ? AND estado = 'activo'",
            "i",
            [$id_estudiante],
            false
        )['total'] ?? 0);

        $en_espera = (int)($this->ejecutar(
            "SELECT COUNT(*) AS total FROM solicitud_proyecto
             WHERE id_estudiante = ?
               AND estado IN ('pendiente', 'en_revision', 'correcciones', 'aceptado')",
            "i",
            [$id_estudiante],
            false
        )['total'] ?? 0);

        return [
            'activos'   => $activos,
            'en_espera' => $en_espera,
        ];
    }

    public function esIntegrante(int $id_proyecto, int $id_usuario): bool
    {
        $row = $this->ejecutar(
            "SELECT 1 FROM proyectos_usuarios
             WHERE id_proyectos = ? AND id_usuarios = ? AND estado = 'activo'
             LIMIT 1",
            "ii",
            [$id_proyecto, $id_usuario],
            false
        );
        return !empty($row);
    }

    public function obtenerUltimaSolicitud(int $id_proyecto, int $id_estudiante): ?array
    {
        return $this->ejecutar(
            "SELECT id_solicitud_proyecto, estado
             FROM solicitud_proyecto
             WHERE id_proyectos = ? AND id_estudiante = ?
             ORDER BY fecha_envio DESC
             LIMIT 1",
            "ii",
            [$id_proyecto, $id_estudiante],
            false
        ) ?: null;
    }
}