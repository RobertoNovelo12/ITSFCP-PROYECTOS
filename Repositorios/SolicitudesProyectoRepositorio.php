<?php
// Repositorios/SolicitudesProyectoRepositorio.php

require_once __DIR__ . '/../Modelos/BaseModelo.php';

/**
 * SolicitudesProyectoRepositorio
 *
 * Responsabilidad exclusiva: ejecutar consultas SQL del módulo
 * de solicitudes de creación/cierre de proyectos.
 * No contiene lógica de negocio.
 */
class SolicitudesProyectoRepositorio extends BaseModelo
{
    public function __construct(mysqli $conn)
    {
        parent::__construct($conn);
    }


    // 
    // MANTENIMIENTO AUTOMÁTICO
    // 

    public function actualizarProyectosVencidos(): bool
    {
        return $this->ejecutar(
            "UPDATE proyectos
             SET id_estadoP = 6
             WHERE id_estadoP IN (2,3,4,5,7)
               AND fecha_fin < CURDATE()"
        );
    }


    // 
    // CAMBIOS DE ESTADO
    // 

    public function actualizarEstado(int $id_proyectos, int $numeroEstado): void
    {
        $this->ejecutar(
            "UPDATE proyectos
             SET id_estadoP = ?, actualizado_en = NOW()
             WHERE id_proyectos = ?",
            "ii",
            [$numeroEstado, $id_proyectos]
        );
    }

    public function obtenerPlantillaReporte(): ?array
    {
        return $this->ejecutar(
            "SELECT pd.id_plantilla, pd.id_documento
             FROM plantillas_documentos pd
             INNER JOIN tipo_documento td ON td.id_tipo_documento = pd.id_tipo_documento
             WHERE pd.activo = 1
               AND LOWER(td.nombre) LIKE 'reporte%'
             LIMIT 1",
            "",
            [],
            false
        );
    }

    public function insertarSeguimiento(int $id_proyectos): int
    {
        $this->ejecutar(
            "INSERT INTO tbl_seguimiento (id_proyectos, fecha_activacion)
             VALUES (?, CURDATE())",
            "i",
            [$id_proyectos]
        );
        return (int)$this->conn->insert_id;
    }

    public function obtenerTiposTarea(): array
    {
        return $this->ejecutar(
            "SELECT id_tareatipo
             FROM tipo_tarea
             ORDER BY id_tareatipo ASC"
        );
    }

    /**
     * Inserta una tarea con documento de recurso adjunto.
     * Campos reales de `tareas`: id_avances, id_tareatipo, id_estadoT, id_documento_recurso.
     */
    public function insertarTareaConDocumento(int $id_avances, int $id_tipo, int $estado, int $id_doc): void
    {
        $this->ejecutar(
            "INSERT INTO tareas (id_avances, id_tareatipo, id_estadoT, id_documento_recurso)
             VALUES (?, ?, ?, ?)",
            "iiii",
            [$id_avances, $id_tipo, $estado, $id_doc]
        );
    }

    /**
     * Inserta una tarea sin documento de recurso.
     * id_documento_recurso es nullable, se omite para que quede NULL.
     */
    public function insertarTarea(int $id_avances, int $id_tipo, int $estado): void
    {
        $this->ejecutar(
            "INSERT INTO tareas (id_avances, id_tareatipo, id_estadoT)
             VALUES (?, ?, ?)",
            "iii",
            [$id_avances, $id_tipo, $estado]
        );
    }

    public function obtenerInvestigadorDeProyecto(int $id_proyectos): ?array
    {
        return $this->ejecutar(
            "SELECT id_investigador
             FROM proyectos
             WHERE id_proyectos = ?",
            "i",
            [$id_proyectos],
            false
        ) ?: null;
    }

    /**
     * Aprueba el cierre del proyecto:
     *   1. Marca tbl_cierres como aprobado.
     *   2. Actualiza todos los integrantes activos a 'concluido' en proyectos_usuarios.
     */
    public function aprobarCierre(int $id_proyectos): void
    {
        $this->ejecutar(
            "UPDATE tbl_cierres
             SET fecha_resultado = CURDATE(), estado = 'aprobado'
             WHERE id_proyectos = ?",
            "i",
            [$id_proyectos]
        );
        $this->ejecutar(
            "UPDATE proyectos_usuarios
             SET fecha_terminacion = CURDATE(), estado = 'concluido'
             WHERE id_proyectos = ?
               AND estado = 'activo'",
            "i",
            [$id_proyectos]
        );
    }

    /**
     * Inserta la solicitud de cierre en tbl_cierres.
     *
     */
    public function insertarCierre(int $id_proyectos, ?float $porcentaje): void
    {
        $this->ejecutar(
            "INSERT INTO tbl_cierres
                (id_proyectos, porcentaje, fecha_solicitud, estado)
             VALUES (?, ?, CURDATE(), 'espera')",
            "id",
            [$id_proyectos, $porcentaje ?? 0.0]
        );
    }

    /**
     * Reactiva un cierre rechazado: restablece estado a 'espera',
     * actualiza el porcentaje y limpia fecha_resultado.
     * Se usa al reenviar la solicitud (en lugar de ON DUPLICATE KEY).
     */
    public function actualizarCierreParaReenvio(int $id_proyectos, ?float $porcentaje): void
    {
        $this->ejecutar(
            "UPDATE tbl_cierres
             SET estado           = 'espera',
                 porcentaje       = ?,
                 fecha_solicitud  = CURDATE(),
                 fecha_resultado  = NULL
             WHERE id_proyectos   = ?
               AND estado         = 'rechazado'",
            "di",
            [$porcentaje ?? 0.0, $id_proyectos]
        );
    }

    /**
     * Rechaza una solicitud e inserta el comentario de rechazo.
     *
     */
    public function rechazarConComentario(int $id_usuario, int $id_proyectos, string $tipo, string $comentario): void
    {
        // $tipo debe ser 'creacion_rechazada' (→ estado 4) o 'cierre_rechazado' (→ estado 7)
        $num_motivo = ($tipo === 'cierre_rechazado') ? 7 : 4;

        $this->ejecutar(
            "UPDATE proyectos
             SET id_estadoP = ?, actualizado_en = NOW()
             WHERE id_proyectos = ?",
            "ii",
            [$num_motivo, $id_proyectos]
        );

        // El ENUM del campo tipo solo acepta: 'creacion_rechazada' | 'cierre_rechazado'
        $this->ejecutar(
            "INSERT INTO proyectos_comentarios
                (id_proyectos, id_usuarios, tipo, comentario, fecha)
             VALUES (?, ?, ?, ?, CURDATE())",
            "iiss",
            [$id_proyectos, $id_usuario, $tipo, $comentario]
        );

        if ($tipo === 'cierre_rechazado') {
            $this->ejecutar(
                "UPDATE tbl_cierres
                 SET fecha_resultado = CURDATE(), estado = 'rechazado'
                 WHERE id_proyectos = ?
                   AND estado = 'espera'",
                "i",
                [$id_proyectos]
            );
        }
    }

    /**
     * Reenviar cierre rechazado (estado 7) → Por cerrar (estado 5).
     * Solo el investigador dueño del proyecto puede ejecutarla.
     *
     */
    public function reenviarCierre(int $id_proyectos, int $id_investigador): void
    {
        $row = $this->ejecutar(
            "SELECT id_proyectos
             FROM proyectos
             WHERE id_proyectos    = ?
               AND id_investigador = ?
               AND id_estadoP      = 7
             LIMIT 1",
            "ii",
            [$id_proyectos, $id_investigador],
            false
        );

        if (empty($row)) {
            exit; // No se encontró el proyecto en estado 7 para este investigador, no se hace nada.
        }

        $this->ejecutar(
            "UPDATE proyectos
             SET id_estadoP = 5, actualizado_en = NOW()
             WHERE id_proyectos    = ?
               AND id_investigador = ?
               AND id_estadoP      = 7",
            "ii",
            [$id_proyectos, $id_investigador]
        );
    }


    // 
    // AVANCE / TAREAS
    // 

    /**
     * Devuelve las tareas con estado aprobado (id_estadoT = 5) del proyecto.
     *
     * CORRECCIÓN: la columna de estado en tareas_usuarios se llama id_estadoT
     * (verificado en la estructura real de la tabla).
     */
    public function obtenerTareasAprobadas(int $id_proyecto): array
    {
        return $this->ejecutar(
            "SELECT taus.id_estadoT
             FROM tareas_usuarios AS taus
             JOIN tareas           AS tare ON tare.id_tarea   = taus.id_tarea
             JOIN tbl_seguimiento  AS tbse ON tare.id_avances = tbse.id_avances
             WHERE tbse.id_proyectos = ?
               AND taus.id_estadoT   = 5",
            "i",
            [$id_proyecto]
        );
    }


    // 
    // RESUMEN (dashboard)
    // 

    public function resumenSolicitudesSupervisor(?int $id_periodo): array
    {
        $where_periodo = $id_periodo ? " AND proy.id_periodos = ?" : "";
        $params        = $id_periodo ? [$id_periodo] : [];
        $types         = $id_periodo ? "i" : "";

        return $this->ejecutar(
            "SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN espr.nombre = 'Por aprobar'        THEN 1 ELSE 0 END) AS pendientes_creacion,
                SUM(CASE WHEN espr.nombre = 'Por cerrar'         THEN 1 ELSE 0 END) AS pendientes_cierre,
                SUM(CASE WHEN espr.nombre IN ('Activo','Cierre') THEN 1 ELSE 0 END) AS aprobadas
             FROM proyectos proy
             JOIN estados_proyectos espr ON proy.id_estadoP = espr.id_estadoP
             WHERE proy.id_estadoP IN (3, 5, 2, 1)
             $where_periodo",
            $types,
            $params,
            false
        ) ?? ['total' => 0, 'pendientes_creacion' => 0, 'pendientes_cierre' => 0, 'aprobadas' => 0];
    }

    /**
     * Resumen de solicitudes para el investigador.
     * Incluye rechazados (4, 7) como tarjeta independiente.
     */
    public function resumenSolicitudesInvestigador(int $id_usuario, ?int $id_periodo): array
    {
        $where_periodo = $id_periodo ? " AND proy.id_periodos = ?" : "";
        $params        = [$id_usuario];
        $types         = "i";
        if ($id_periodo) {
            $params[] = $id_periodo;
            $types   .= "i";
        }

        return $this->ejecutar(
            "SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN proy.id_estadoP = 3          THEN 1 ELSE 0 END) AS pendientes_creacion,
                SUM(CASE WHEN proy.id_estadoP = 5          THEN 1 ELSE 0 END) AS pendientes_cierre,
                SUM(CASE WHEN proy.id_estadoP IN (4, 7)    THEN 1 ELSE 0 END) AS requieren_accion,
                SUM(CASE WHEN proy.id_estadoP IN (2, 1)    THEN 1 ELSE 0 END) AS aprobadas
             FROM proyectos proy
             JOIN estados_proyectos espr ON proy.id_estadoP = espr.id_estadoP
             WHERE proy.id_investigador  = ?
               AND proy.id_estadoP IN (3, 4, 5, 7, 2, 1)
             $where_periodo",
            $types,
            $params,
            false
        ) ?? ['total' => 0, 'pendientes_creacion' => 0, 'pendientes_cierre' => 0, 'requieren_accion' => 0, 'aprobadas' => 0];
    }


    // 
    // LISTADO PAGINADO
    // 

    public function contarSolicitudes(string $in_estados, string $where_base, string $bind_types, array $bind_params): int
    {
        return (int)($this->ejecutar(
            "SELECT COUNT(*) AS total
             FROM proyectos proy
             WHERE $where_base",
            $bind_types,
            $bind_params,
            false
        )['total'] ?? 0);
    }

    /**
     * Listado base (supervisor e investigador).
     * `comentario_preview`: último comentario de rechazo (útil para "Requieren acción").
     */
    public function listarSolicitudes(string $where_base, string $types, array $params): array
    {
        return $this->ejecutar(
            "SELECT
                proy.id_proyectos,
                proy.titulo,
                espr.nombre AS estado_proyecto,
                peri.periodo,
                CONCAT(u.nombre, ' ', u.apellido_paterno, ' ', u.apellido_materno) AS investigador,
                proy.creado_en AS fecha_solicitud,
                CASE
                    WHEN proy.id_estadoP IN (3, 4) THEN 'creacion'
                    ELSE 'cierre'
                END AS tipo_solicitud,
                (
                    SELECT LEFT(pc.comentario, 100)
                    FROM proyectos_comentarios pc
                    WHERE pc.id_proyectos = proy.id_proyectos
                    ORDER BY pc.fecha DESC
                    LIMIT 1
                ) AS comentario_preview
             FROM proyectos proy
             JOIN estados_proyectos espr ON proy.id_estadoP  = espr.id_estadoP
             JOIN periodos peri          ON proy.id_periodos  = peri.id_periodos
             JOIN usuarios u             ON u.id_usuarios     = proy.id_investigador
             WHERE $where_base
             ORDER BY proy.id_proyectos DESC
             LIMIT ?, ?",
            $types,
            $params
        );
    }


    // 
    // CATÁLOGOS
    // 

    public function obtenerTodosPeriodos(): array
    {
        return $this->ejecutar(
            "SELECT
                id_periodos,
                periodo,
                fecha_inicio AS FechaInicio,
                fecha_final  AS FechaFinal,
                CASE
                    WHEN CURDATE() BETWEEN fecha_inicio AND fecha_final THEN 'Activo'
                    WHEN CURDATE() < fecha_inicio                       THEN 'Pendiente'
                    ELSE 'Terminado'
                END AS estado
             FROM periodos
             ORDER BY periodo DESC"
        );
    }


    // 
    // DETALLE DE SOLICITUD
    // 

    public function obtenerProyecto(int $id_proyecto): ?array
    {
        return $this->ejecutar(
            "SELECT
                proy.id_proyectos,
                espr.nombre AS estado_proyecto,
                tema.nombre_tematica AS tematica,
                peri.periodo,
                CASE
                    WHEN CURDATE() BETWEEN peri.fecha_inicio AND peri.fecha_final THEN 'Activo'
                    WHEN CURDATE() < peri.fecha_inicio                            THEN 'Pendiente'
                    ELSE 'Terminado'
                END AS estado_periodo,
                proy.titulo, proy.descripcion, proy.objetivo,
                proy.fecha_inicio, proy.fecha_fin, proy.presupuesto,
                proy.creado_en, proy.requisitos, proy.pre_requisitos,
                proy.modalidad, proy.cantidad_estudiante
             FROM proyectos AS proy
             JOIN estados_proyectos AS espr         ON proy.id_estadoP   = espr.id_estadoP
             JOIN proyectos_subtematica AS proy_sub  ON proy.id_proyectos = proy_sub.id_proyectos
             JOIN subtematica AS subt                ON proy_sub.id_subtematica = subt.id_subtematica
             JOIN tematica AS tema                   ON tema.id_tematica  = subt.id_tematica
             JOIN periodos peri                      ON proy.id_periodos  = peri.id_periodos
             WHERE proy.id_proyectos = ?
             GROUP BY proy.id_proyectos, espr.nombre, tema.nombre_tematica
             ORDER BY proy.id_proyectos DESC",
            "i",
            [$id_proyecto],
            false
        );
    }

    public function obtenerProyectoInvestigador(int $id_proyecto): ?array
    {
        return $this->ejecutar(
            "SELECT
                usua.id_usuarios,
                usua.nombre, usua.apellido_paterno, usua.apellido_materno,
                nisn.nombre AS nivel_sni,
                grac.nombre AS grado_academico
             FROM investigadores AS inve
             JOIN usuarios AS usua         ON usua.id_usuarios    = inve.id_usuarios
             JOIN niveles_sni AS nisn       ON nisn.id_nivel       = inve.id_nivel_sni
             JOIN grados_academicos AS grac ON grac.id_grado       = inve.id_grado
             JOIN proyectos AS proy         ON proy.id_investigador = inve.id_usuarios
             WHERE proy.id_proyectos = ?",
            "i",
            [$id_proyecto],
            false
        );
    }

    public function obtenerUsuarioArea(?int $id_usuario): ?array
    {
        if (!$id_usuario) return null;
        return $this->ejecutar(
            "SELECT
                arco.nombre_area AS area_conocimiento,
                GROUP_CONCAT(subco.nombre_subarea) AS subarea
             FROM usuarios AS us
             JOIN usuarios_subareas AS ussu      ON ussu.id_usuarios = us.id_usuarios
             JOIN subareas_conocimiento AS subco  ON ussu.id_subarea  = subco.id_subarea
             JOIN areas_conocimiento AS arco      ON arco.id_area     = subco.id_area
             WHERE us.id_usuarios = ?
             GROUP BY us.id_usuarios, subco.id_subarea, arco.id_area",
            "i",
            [$id_usuario],
            false
        );
    }

    public function obtenerInvestigadorLinea(int $id_proyecto): ?array
    {
        return $this->ejecutar(
            "SELECT liin.nombre AS linea
             FROM investigadores AS inve
             JOIN investigador_lineas_investigacion AS inliin ON inliin.id_usuarios = inve.id_usuarios
             JOIN lineas_investigacion AS liin                ON liin.id_linea      = inliin.id_linea
             JOIN proyectos AS proy                           ON proy.id_investigador = inve.id_usuarios
             WHERE proy.id_proyectos = ?",
            "i",
            [$id_proyecto],
            false
        );
    }

    public function obtenerSubtematicasProyecto(int $id_proyecto): array
    {
        return $this->ejecutar(
            "SELECT sub.id_subtematica, sub2.nombre_subtematica AS nombre
             FROM proyectos_subtematica AS sub
             JOIN subtematica AS sub2 ON sub.id_subtematica = sub2.id_subtematica
             WHERE sub.id_proyectos = ?
               AND sub2.estado = 1",
            "i",
            [$id_proyecto]
        );
    }

    /**
     * Comentarios de rechazo del proyecto.
     *
     */
    public function obtenerProyectoComentarios(int $id_proyecto): array
    {
        return $this->ejecutar(
            "SELECT
                CASE prco.tipo
                    WHEN 'creacion_rechazada' THEN 'Creación rechazada'
                    WHEN 'cierre_rechazado'   THEN 'Cierre rechazado'
                END AS tipo,
                CONCAT(usua.nombre, ' ', usua.apellido_paterno, ' ', usua.apellido_materno) AS nombre_completo,
                prco.comentario,
                prco.fecha
             FROM proyectos_comentarios AS prco
             JOIN proyectos AS proy ON proy.id_proyectos = prco.id_proyectos
             JOIN usuarios AS usua  ON usua.id_usuarios  = prco.id_usuarios
             WHERE proy.id_proyectos = ?
             ORDER BY prco.fecha DESC",
            "i",
            [$id_proyecto]
        );
    }

    /**
     * Estudiantes del proyecto con su estado actual.
     *
     */
    public function obtenerEstudiantes(int $id_proyecto): array
    {
        return $this->ejecutar(
            "SELECT
                u.id_usuarios,
                u.nombre, u.apellido_paterno, u.apellido_materno,
                c.nombre_carrera AS carrera,
                pu.estado
             FROM proyectos_usuarios pu
             JOIN usuarios    u ON u.id_usuarios = pu.id_usuarios
             JOIN estudiantes e ON e.id_usuarios = u.id_usuarios
             JOIN carreras    c ON e.id_carrera  = c.id_carrera
             WHERE pu.id_proyectos = ?",
            "i",
            [$id_proyecto]
        );
    }
}
