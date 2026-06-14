<?php
// Repositorios/PrincipalRepositorio.php

require_once __DIR__ . '/../Modelos/BaseModelo.php';

/**
 * PrincipalRepositorio
 *
 * Responsabilidad exclusiva: ejecutar consultas SQL del módulo principal (catálogo de proyectos).
 * No contiene lógica de negocio.
 *
 *  Adaptación especial 
 * Los métodos obtenerProyectosInvestigador / contarProyectosInvestigador y sus
 * equivalentes para estudiante usan fragmentos SQL dinámicos que se construyen
 * en el modelo según el rol y los filtros activos. El repositorio los recibe
 * como parámetros ($sql, $tipos, $params) ya construidos y llama a ejecutar().
 * 
 */
class PrincipalRepositorio extends BaseModelo
{
    public function __construct(mysqli $conn)
    {
        parent::__construct($conn);
    }


    // 
    // CATÁLOGOS
    // 

    public function listarTematicas(): array
    {
        return $this->ejecutar(
            'SELECT id_tematica, nombre_tematica FROM tematica WHERE estado = 1 ORDER BY nombre_tematica ASC'
        );
    }

    public function listarSubtematicas(int $id_tematica = 0): array
    {
        if ($id_tematica > 0) {
            return $this->ejecutar(
                'SELECT id_subtematica, nombre_subtematica
                 FROM subtematica WHERE estado = 1 AND id_tematica = ?
                 ORDER BY nombre_subtematica ASC',
                'i',
                [$id_tematica]
            );
        }

        return $this->ejecutar(
            'SELECT id_subtematica, nombre_subtematica
             FROM subtematica WHERE estado = 1
             ORDER BY nombre_subtematica ASC'
        );
    }

    // 
    // MANTENIMIENTO AUTOMÁTICO DE ESTADOS
    // 

    public function marcarSolicitudesProyectosVencidos(): void
    {
        $this->ejecutar("
        UPDATE solicitud_proyecto sp
        JOIN periodos pe ON pe.id_periodos = sp.id_periodos
        SET sp.estado = 'vencido', sp.fecha_respuesta = CURDATE()
        WHERE sp.id_solicitud_proyecto > 0
          AND pe.estado = 1
          AND pe.fecha_fin_solicitud IS NOT NULL
          AND CURDATE() > pe.fecha_fin_solicitud
          AND sp.estado NOT IN ('vencido','rechazado','aceptado','cancelado')
    ");
    }

    // 
    // PROYECTOS — consultas con SQL dinámico
    // 

    /**
     * Ejecuta la consulta de listado con SQL y parámetros ya construidos.
     */
    public function ejecutarListado(string $sql, string $tipos, array $params): array
    {
        return $this->ejecutar($sql, $tipos, $params);
    }

    /**
     * Ejecuta la consulta de conteo con SQL y parámetros ya construidos.
     */
    public function ejecutarConteo(string $sql, string $tipos, array $params): int
    {
        $fila = $this->ejecutar($sql, $tipos, $params, false);
        return (int)(array_values($fila ?? [])[0] ?? 0);
    }


    // 
    // AUXILIARES DE VENTANA
    // 

    public function ventanaSolicitudAbierta(): bool
    {
        $hoy = date('Y-m-d');
        $row = $this->ejecutar(
            'SELECT COUNT(*) AS abierta FROM periodos
             WHERE estado = 1
               AND fecha_inicio <= ? AND fecha_final >= ?
               AND (fecha_inicio_solicitud IS NULL OR fecha_inicio_solicitud <= ?)
               AND (fecha_fin_solicitud    IS NULL OR fecha_fin_solicitud    >= ?)
             LIMIT 1',
            'ssss',
            [$hoy, $hoy, $hoy, $hoy],
            false
        );

        return (int)($row['abierta'] ?? 0) > 0;
    }

    public function ventanaCreacionAbierta(): bool
    {
        $hoy = date('Y-m-d');
        $row = $this->ejecutar(
            'SELECT COUNT(*) AS abierta FROM periodos
             WHERE estado = 1
               AND fecha_inicio <= ? AND fecha_final >= ?
               AND (fecha_inicio_proyectos IS NULL OR fecha_inicio_proyectos <= ?)
               AND (fecha_fin_proyectos    IS NULL OR fecha_fin_proyectos    >= ?)
             LIMIT 1',
            'ssss',
            [$hoy, $hoy, $hoy, $hoy],
            false
        );

        return (int)($row['abierta'] ?? 0) > 0;
    }

    public function ventanaSolicitudAbiertaParaProyecto(int $id_proyecto): bool
    {
        $hoy = date('Y-m-d');
        $row = $this->ejecutar(
            'SELECT COUNT(*) AS abierta
             FROM periodos per
             INNER JOIN proyectos p ON p.id_periodos = per.id_periodos
             WHERE p.id_proyectos = ?
               AND per.estado = 1
               AND per.fecha_inicio <= ?
               AND per.fecha_final  >= ?
               AND (per.fecha_inicio_solicitud IS NULL OR per.fecha_inicio_solicitud <= ?)
               AND (per.fecha_fin_solicitud    IS NULL OR per.fecha_fin_solicitud    >= ?)
             LIMIT 1',
            'issss',
            [$id_proyecto, $hoy, $hoy, $hoy, $hoy],
            false
        );

        return (int)($row['abierta'] ?? 0) > 0;
    }


    // 
    // DETALLE DE PROYECTO
    // 

    public function buscarDetalle(int $id_proyecto): ?array
    {
        $fila = $this->ejecutar(
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
            'i',
            [$id_proyecto],
            false
        );

        return $fila ?: null;
    }


    // 
    // CARGA Y MEMBRESÍA DEL ESTUDIANTE
    // 

    public function contarProyectosActivos(int $id_estudiante): int
    {
        $fila = $this->ejecutar(
            "SELECT COUNT(*) AS total
             FROM proyectos_usuarios pu
             JOIN proyectos p         ON p.id_proyectos  = pu.id_proyectos
             JOIN periodos  pe        ON pe.id_periodos   = p.id_periodos
             WHERE pu.id_usuarios = ?
               AND pu.estado = 'activo'
               AND pe.estado = 1
               AND CURDATE() BETWEEN pe.fecha_inicio_solicitud AND pe.fecha_fin_solicitud",
            'i',
            [$id_estudiante],
            false
        );

        return (int)($fila['total'] ?? 0);
    }

    public function contarSolicitudesEnEspera(int $id_estudiante): int
    {
        $fila = $this->ejecutar(
            "SELECT COUNT(*) AS total
         FROM solicitud_proyecto sp
         JOIN periodos pe ON pe.id_periodos = sp.id_periodos
         WHERE sp.id_estudiante = ?
           AND sp.estado IN ('pendiente', 'en_revision', 'correcciones', 'aceptado')
           AND pe.estado = 1
           AND CURDATE() BETWEEN pe.fecha_inicio_solicitud AND pe.fecha_fin_solicitud",
            'i',
            [$id_estudiante],
            false
        );

        return (int)($fila['total'] ?? 0);
    }

    public function esIntegrante(int $id_proyecto, int $id_usuario): bool
    {
        $row = $this->ejecutar(
            "SELECT 1 FROM proyectos_usuarios
             WHERE id_proyectos = ? AND id_usuarios = ? AND estado = 'activo'
             LIMIT 1",
            'ii',
            [$id_proyecto, $id_usuario],
            false
        );

        return !empty($row);
    }

    public function buscarUltimaSolicitud(int $id_proyecto, int $id_estudiante): ?array
    {
        $fila = $this->ejecutar(
            'SELECT id_solicitud_proyecto, estado
             FROM solicitud_proyecto
             WHERE id_proyectos = ? AND id_estudiante = ?
             ORDER BY fecha_envio DESC
             LIMIT 1',
            'ii',
            [$id_proyecto, $id_estudiante],
            false
        );

        return $fila ?: null;
    }

    // 
    // VALIDACIÓN DE SOLICITUD BLOQUEANTE (rechazado / vencido en el período activo)
    // 

    /**
     * Devuelve true si el estudiante ya tiene una solicitud en estado 'rechazado'
     * o 'vencido' para este proyecto dentro de un período activo.
     * Un estado 'cancelado' NO bloquea: el estudiante puede volver a solicitar.
     */
    public function tieneSolicitudBloqueante(int $id_estudiante, int $id_proyectos): bool
    {
        $fila = $this->ejecutar(
            "SELECT COUNT(*) AS total
             FROM solicitud_proyecto sp
             JOIN periodos pe ON pe.id_periodos = sp.id_periodos
             WHERE sp.id_estudiante = ?
               AND sp.id_proyectos  = ?
               AND pe.estado = 1
               AND sp.estado IN ('rechazado', 'vencido')",
            'ii',
            [$id_estudiante, $id_proyectos],
            false
        );

        return (int)($fila['total'] ?? 0) > 0;
    }


    // 
    // CANCELAR
    // 

    /**
     * Cancela todas las solicitudes activas (pendiente / en_revision / correcciones)
     * del estudiante para el proyecto indicado.
     */
    public function cancelarSolicitud(int $id_usuario, int $id_proyectos): void
    {
        $this->ejecutar(
            "UPDATE solicitud_proyecto
             SET    estado = 'cancelado', fecha_respuesta = CURDATE()
             WHERE  id_estudiante = ? AND id_proyectos = ?
               AND  estado IN ('pendiente', 'en_revision', 'correcciones')",
            'ii',
            [$id_usuario, $id_proyectos]
        );
    }
}
