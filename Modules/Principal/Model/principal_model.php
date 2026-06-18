<?php
// Modelos/Principal.php

require_once __DIR__ . '/../Repository/principal_repository.php';

/**
 * Principal (Modelo)
 *
 * Responsabilidad exclusiva: lógica de negocio del módulo principal (catálogo de proyectos).
 * Delega toda ejecución SQL a PrincipalRepositorio.
 *
 * La construcción de los fragmentos SQL dinámicos (filtros por rol, búsqueda, temática, etc.)
 * permanece aquí como lógica de dominio. El repositorio recibe el SQL y parámetros ya armados.
 */
class Principal
{
    private PrincipalRepositorio $repo;

    public function __construct(mysqli $conn)
    {
        $this->repo = new PrincipalRepositorio($conn);
    }


    // 
    // CATÁLOGOS
    // 

    public function obtenerTematicas(): array
    {
        return $this->repo->listarTematicas();
    }

    public function obtenerSubtematicas(int $id_tematica = 0): array
    {
        return $this->repo->listarSubtematicas($id_tematica);
    }

    // Mantenimiento automático de estados de proyectos basado en fechas de periodos.
    // Se llama al cargar el index del investigador.
    public function marcarSolicitudesProyectosVencidos(): void
    {
        $this->repo->marcarSolicitudesProyectosVencidos();
    }


    // 
    // ROL: INVESTIGADOR / SUPERVISOR
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

        [$sql, $tipos, $params] = $this->buildSqlInvestigador(
            $id_investigador,
            $rol,
            $buscar,
            $modalidad,
            $id_tematica,
            $id_subtematica
        );

        $sql     .= ' GROUP BY p.id_proyectos ORDER BY FIELD(p.id_estadoP, 2, 5, 3, 1, 6) ASC, p.creado_en DESC LIMIT ?, ?';
        $params[] = $desde;
        $params[] = $por_pagina;
        $tipos   .= 'ii';

        return $this->repo->ejecutarListado($sql, $tipos, $params);
    }

    public function contarProyectosInvestigador(
        int    $id_investigador,
        string $rol,
        string $buscar         = '',
        string $modalidad      = '',
        int    $id_tematica    = 0,
        int    $id_subtematica = 0
    ): int {
        [$sql, $tipos, $params] = $this->buildSqlConteoInvestigador(
            $id_investigador,
            $rol,
            $buscar,
            $modalidad,
            $id_tematica,
            $id_subtematica
        );

        return $this->repo->ejecutarConteo($sql, $tipos, $params);
    }


    // 
    // ROL: ESTUDIANTE
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
        $desde = ($pagina - 1) * $por_pagina;

        [$sql, $tipos, $params] = $this->SqlEstudiante(
            $id_estudiante,
            $buscar,
            $modalidad,
            $id_tematica,
            $id_subtematica
        );

        $sql     .= ' GROUP BY p.id_proyectos ORDER BY p.creado_en DESC LIMIT ?, ?';
        $params[] = $desde;
        $params[] = $por_pagina;
        $tipos   .= 'ii';

        return $this->repo->ejecutarListado($sql, $tipos, $params);
    }

    public function contarProyectosEstudiante(
        int    $id_estudiante,
        string $buscar         = '',
        string $modalidad      = '',
        int    $id_tematica    = 0,
        int    $id_subtematica = 0
    ): int {
        [$sql, $tipos, $params] = $this->buildSqlConteoEstudiante(
            $id_estudiante,
            $buscar,
            $modalidad,
            $id_tematica,
            $id_subtematica
        );

        return $this->repo->ejecutarConteo($sql, $tipos, $params);
    }


    // 
    // AUXILIARES DE VENTANA
    // 

    public function ventanaSolicitudAbierta(): bool
    {
        return $this->repo->ventanaSolicitudAbierta();
    }

    public function ventanaCreacionAbierta(): bool
    {
        return $this->repo->ventanaCreacionAbierta();
    }

    public function ventanaSolicitudAbiertaParaProyecto(int $id_proyecto): bool
    {
        return $this->repo->ventanaSolicitudAbiertaParaProyecto($id_proyecto);
    }


    // 
    // DETALLE DE PROYECTO
    // 

    public function obtenerDetalle(int $id_proyecto): ?array
    {
        return $this->repo->buscarDetalle($id_proyecto);
    }


    // 
    // CARGA Y MEMBRESÍA DEL ESTUDIANTE
    // 

    public function obtenerCargaProyectosEstudiante(int $id_estudiante): array
    {
        return [
            'activos'   => $this->repo->contarProyectosActivos($id_estudiante),
            'en_espera' => $this->repo->contarSolicitudesEnEspera($id_estudiante),
        ];
    }

    public function esIntegrante(int $id_proyecto, int $id_usuario): bool
    {
        return $this->repo->esIntegrante($id_proyecto, $id_usuario);
    }

    public function obtenerUltimaSolicitud(int $id_proyecto, int $id_estudiante): ?array
    {
        return $this->repo->buscarUltimaSolicitud($id_proyecto, $id_estudiante);
    }

    // 
    // VALIDACIÓN DE SOLICITUD BLOQUEANTE
    // 

    /**
     * Delega al repositorio la comprobación de si existe una solicitud bloqueante
     * (estado 'rechazado' o 'vencido') en el período activo para este proyecto.
     * Un 'cancelado' NO bloquea.
     */
    public function tieneSolicitudBloqueante(int $id_estudiante, int $id_proyectos): bool
    {
        return $this->repo->tieneSolicitudBloqueante($id_estudiante, $id_proyectos);
    }


    // 
    // BUILDERS DE SQL DINÁMICO (lógica de dominio)
    // 

    private function buildSqlInvestigador(
        int $id_investigador,
        string $rol,
        string $buscar,
        string $modalidad,
        int $id_tematica,
        int $id_subtematica
    ): array {
        $filtroInvestigador = ($rol === 'supervisor') ? '' : 'AND p.id_investigador = ?';
        $filtroBuscar       = $buscar         ? 'AND (p.titulo LIKE ? OR p.descripcion LIKE ?)' : '';
        $filtroModalidad    = $modalidad      ? 'AND p.modalidad = ?'                           : '';
        $filtroTematica     = $id_tematica    ? 'AND t.id_tematica = ?'                         : '';
        $filtroSubt         = $id_subtematica ? 'AND ps.id_subtematica = ?'                     : '';

        $sql = "
            SELECT
                p.id_proyectos, p.titulo, p.descripcion, p.modalidad,
                p.cantidad_estudiante, p.fecha_inicio, p.fecha_fin,
                DATE_FORMAT(p.creado_en, '%d/%m/%Y') AS fecha_creacion,
                ep.nombre AS estado_proyecto, p.id_estadoP,
                i.nombre AS instituto,
                CONCAT(u.nombre, ' ', u.apellido_paterno) AS investigador,
                per.periodo,
                (SELECT t2.nombre_tematica
                 FROM proyectos_subtematica ps2
                 JOIN subtematica st2 ON st2.id_subtematica = ps2.id_subtematica
                 JOIN tematica t2 ON t2.id_tematica = st2.id_tematica
                 WHERE ps2.id_proyectos = p.id_proyectos LIMIT 1) AS tematica,
                (SELECT st3.nombre_subtematica
                 FROM proyectos_subtematica ps3
                 JOIN subtematica st3 ON st3.id_subtematica = ps3.id_subtematica
                 WHERE ps3.id_proyectos = p.id_proyectos LIMIT 1) AS subtematica,
                (SELECT COUNT(*) FROM proyectos_usuarios pu
                 WHERE pu.id_proyectos = p.id_proyectos AND pu.estado = 'activo') AS inscritos_actuales,
                p.cantidad_estudiante - (SELECT COUNT(*) FROM proyectos_usuarios pu
                 WHERE pu.id_proyectos = p.id_proyectos AND pu.estado = 'activo') AS lugares_disponibles
            FROM proyectos p
            INNER JOIN estados_proyectos ep ON ep.id_estadoP  = p.id_estadoP
            INNER JOIN instituto i          ON i.id_instituto  = p.id_instituto
            INNER JOIN usuarios u           ON u.id_usuarios   = p.id_investigador
            INNER JOIN periodos per         ON per.id_periodos = p.id_periodos
            LEFT JOIN proyectos_subtematica ps ON ps.id_proyectos  = p.id_proyectos
            LEFT JOIN subtematica st           ON st.id_subtematica = ps.id_subtematica
            LEFT JOIN tematica t               ON t.id_tematica     = st.id_tematica
            WHERE p.id_estadoP NOT IN (4)
            {$filtroInvestigador} {$filtroBuscar} {$filtroModalidad} {$filtroTematica} {$filtroSubt}
        ";

        $tipos  = '';
        $params = [];

        if ($rol !== 'supervisor') {
            $tipos .= 'i';
            $params[] = $id_investigador;
        }
        if ($buscar) {
            $like = "%{$buscar}%";
            $tipos .= 'ss';
            $params[] = $like;
            $params[] = $like;
        }
        if ($modalidad) {
            $tipos .= 's';
            $params[] = $modalidad;
        }
        if ($id_tematica) {
            $tipos .= 'i';
            $params[] = $id_tematica;
        }
        if ($id_subtematica) {
            $tipos .= 'i';
            $params[] = $id_subtematica;
        }

        return [$sql, $tipos, $params];
    }

    private function buildSqlConteoInvestigador(
        int $id_investigador,
        string $rol,
        string $buscar,
        string $modalidad,
        int $id_tematica,
        int $id_subtematica
    ): array {
        $filtroInvestigador = ($rol === 'supervisor') ? '' : 'AND p.id_investigador = ?';
        $filtroBuscar       = $buscar         ? 'AND (p.titulo LIKE ? OR p.descripcion LIKE ?)' : '';
        $filtroModalidad    = $modalidad      ? 'AND p.modalidad = ?'                           : '';
        $filtroTematica     = $id_tematica    ? 'AND t.id_tematica = ?'                         : '';
        $filtroSubt         = $id_subtematica ? 'AND ps.id_subtematica = ?'                     : '';

        $sql = "
            SELECT COUNT(DISTINCT p.id_proyectos) AS total
            FROM proyectos p
            INNER JOIN estados_proyectos ep ON ep.id_estadoP = p.id_estadoP
            LEFT JOIN proyectos_subtematica ps ON ps.id_proyectos  = p.id_proyectos
            LEFT JOIN subtematica st           ON st.id_subtematica = ps.id_subtematica
            LEFT JOIN tematica t               ON t.id_tematica     = st.id_tematica
            WHERE p.id_estadoP NOT IN (4)
            {$filtroInvestigador} {$filtroBuscar} {$filtroModalidad} {$filtroTematica} {$filtroSubt}
        ";

        $tipos  = '';
        $params = [];

        if ($rol !== 'supervisor') {
            $tipos .= 'i';
            $params[] = $id_investigador;
        }
        if ($buscar) {
            $like = "%{$buscar}%";
            $tipos .= 'ss';
            $params[] = $like;
            $params[] = $like;
        }
        if ($modalidad) {
            $tipos .= 's';
            $params[] = $modalidad;
        }
        if ($id_tematica) {
            $tipos .= 'i';
            $params[] = $id_tematica;
        }
        if ($id_subtematica) {
            $tipos .= 'i';
            $params[] = $id_subtematica;
        }

        return [$sql, $tipos, $params];
    }

    private function SqlEstudiante(
        int $id_estudiante,
        string $buscar,
        string $modalidad,
        int $id_tematica,
        int $id_subtematica
    ): array {
        $hoy = date('Y-m-d');

        $filtroBuscar    = $buscar         ? 'AND (p.titulo LIKE ? OR p.descripcion LIKE ?)' : '';
        $filtroModalidad = $modalidad      ? 'AND p.modalidad = ?'                           : '';
        $filtroTematica  = $id_tematica    ? 'AND t.id_tematica = ?'                         : '';
        $filtroSubt      = $id_subtematica ? 'AND ps.id_subtematica = ?'                     : '';

        $sql = "
            SELECT
                p.id_proyectos, ep.id_estadoP, ep.nombre AS estado_proyecto,
                p.titulo, p.descripcion, p.objetivo, p.modalidad,
                p.cantidad_estudiante, p.fecha_inicio, p.fecha_fin,
                p.requisitos, p.pre_requisitos,
                DATE_FORMAT(p.creado_en, '%d/%m/%Y') AS fecha_creacion,
                i.nombre AS instituto,
                CONCAT(u.nombre, ' ', u.apellido_paterno) AS investigador,
                per.periodo,
                (SELECT t2.nombre_tematica
                 FROM proyectos_subtematica ps2
                 JOIN subtematica st2 ON st2.id_subtematica = ps2.id_subtematica
                 JOIN tematica t2 ON t2.id_tematica = st2.id_tematica
                 WHERE ps2.id_proyectos = p.id_proyectos LIMIT 1) AS tematica,
                (SELECT st3.nombre_subtematica
                 FROM proyectos_subtematica ps3
                 JOIN subtematica st3 ON st3.id_subtematica = ps3.id_subtematica
                 WHERE ps3.id_proyectos = p.id_proyectos LIMIT 1) AS subtematica,
                p.cantidad_estudiante - (SELECT COUNT(*) FROM proyectos_usuarios pu
                 WHERE pu.id_proyectos = p.id_proyectos AND pu.estado = 'activo') AS lugares_disponibles,
                (SELECT sp.estado FROM solicitud_proyecto sp
                 WHERE sp.id_proyectos = p.id_proyectos AND sp.id_estudiante = ?
                 ORDER BY sp.id_solicitud_proyecto DESC LIMIT 1) AS mi_solicitud,
                (SELECT COUNT(*) FROM proyectos_usuarios pu
                 WHERE pu.id_proyectos = p.id_proyectos AND pu.id_usuarios = ? AND pu.estado = 'activo') AS ya_inscrito
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
                AND per.fecha_inicio <= ? AND per.fecha_final >= ?
                -- AND (per.fecha_inicio_solicitud IS NULL OR per.fecha_inicio_solicitud <= ?)
                -- AND (per.fecha_fin_solicitud    IS NULL OR per.fecha_fin_solicitud    >= ?)
                AND (SELECT COUNT(*) FROM proyectos_usuarios pu
                     WHERE pu.id_proyectos = p.id_proyectos AND pu.estado = 'activo') < p.cantidad_estudiante
                {$filtroBuscar} {$filtroModalidad} {$filtroTematica} {$filtroSubt}
        ";
        // Versión anterior con validación de ventana de solicitud:
        //$tipos  = 'iissss';
        //$params = [$id_estudiante, $id_estudiante, $hoy, $hoy, $hoy, $hoy];
        // Versión actual sin validación de ventana (el botón se oculta en la vista si está cerrada):
        $tipos  = 'iiss';
        $params = [$id_estudiante, $id_estudiante, $hoy, $hoy];

        if ($buscar) {
            $like = "%{$buscar}%";
            $tipos .= 'ss';
            $params[] = $like;
            $params[] = $like;
        }
        if ($modalidad) {
            $tipos .= 's';
            $params[] = $modalidad;
        }
        if ($id_tematica) {
            $tipos .= 'i';
            $params[] = $id_tematica;
        }
        if ($id_subtematica) {
            $tipos .= 'i';
            $params[] = $id_subtematica;
        }

        return [$sql, $tipos, $params];
    }

    private function buildSqlConteoEstudiante(
        int $id_estudiante,
        string $buscar,
        string $modalidad,
        int $id_tematica,
        int $id_subtematica
    ): array {
        $hoy = date('Y-m-d');

        $filtroBuscar    = $buscar         ? 'AND (p.titulo LIKE ? OR p.descripcion LIKE ?)' : '';
        $filtroModalidad = $modalidad      ? 'AND p.modalidad = ?'                           : '';
        $filtroTematica  = $id_tematica    ? 'AND t.id_tematica = ?'                         : '';
        $filtroSubt      = $id_subtematica ? 'AND ps.id_subtematica = ?'                     : '';

        $sql = "
            SELECT COUNT(DISTINCT p.id_proyectos) AS total
            FROM proyectos p
            INNER JOIN periodos per ON per.id_periodos = p.id_periodos
            LEFT JOIN proyectos_subtematica ps ON ps.id_proyectos  = p.id_proyectos
            LEFT JOIN subtematica st           ON st.id_subtematica = ps.id_subtematica
            LEFT JOIN tematica t               ON t.id_tematica     = st.id_tematica
            WHERE
                p.id_estadoP = 2
                AND per.estado = 1
                AND per.fecha_inicio <= ? AND per.fecha_final >= ?
                AND (per.fecha_inicio_solicitud IS NULL OR per.fecha_inicio_solicitud <= ?)
                AND (per.fecha_fin_solicitud    IS NULL OR per.fecha_fin_solicitud    >= ?)
                AND (SELECT COUNT(*) FROM proyectos_usuarios pu
                     WHERE pu.id_proyectos = p.id_proyectos AND pu.estado = 'activo') < p.cantidad_estudiante
                {$filtroBuscar} {$filtroModalidad} {$filtroTematica} {$filtroSubt}
        ";

        $tipos  = 'ssss';
        $params = [$hoy, $hoy, $hoy, $hoy];

        if ($buscar) {
            $like = "%{$buscar}%";
            $tipos .= 'ss';
            $params[] = $like;
            $params[] = $like;
        }
        if ($modalidad) {
            $tipos .= 's';
            $params[] = $modalidad;
        }
        if ($id_tematica) {
            $tipos .= 'i';
            $params[] = $id_tematica;
        }
        if ($id_subtematica) {
            $tipos .= 'i';
            $params[] = $id_subtematica;
        }

        return [$sql, $tipos, $params];
    }


    // 
    // CANCELAR
    // 

    public function cancelarSolicitud(int $id_estudiante, int $id_proyectos): void
    {
        $this->repo->cancelarSolicitud($id_estudiante, $id_proyectos);
    }
}
