<?php
// Repositorios/ProyectosRepositorio.php

require_once __DIR__ . '/../Modelos/BaseModelo.php';

/**
 * ProyectosRepositorio
 *
 * Responsabilidad exclusiva: ejecutar consultas SQL del módulo de proyectos.
 * No contiene lógica de negocio.
 *
 *  Adaptación especial 
 * Los métodos de tabla principal (estudiante / investigador / supervisor) usan
 * SQL dinámico que se construye en el modelo según el rol y los filtros.
 * El repositorio los recibe como ejecutarListado($sql, $tipos, $params).
 *
 * bajaEstudiante() y reactivarEstudiante() gestionan transacciones propias
 * porque son operaciones de persistencia compuestas e indivisibles.
 * 
 */
class ProyectosRepositorio extends BaseModelo
{
    public function __construct(mysqli $conn)
    {
        parent::__construct($conn);
    }


    // 
    // SQL DINÁMICO — tabla principal
    // 

    public function ejecutarListado(string $sql, string $tipos, array $params): array
    {
        return $this->ejecutar($sql, $tipos, $params);
    }

    public function ejecutarConteo(string $sql, string $tipos, array $params): int
    {
        $fila = $this->ejecutar($sql, $tipos, $params, false);
        return (int)(array_values($fila ?? [])[0] ?? 0);
    }


    // 
    // MANTENIMIENTO AUTOMÁTICO DE ESTADOS
    // 

    public function marcarProyectosVencidos(): bool
    {
        return $this->ejecutar('
            UPDATE proyectos
            SET id_estadoP = 6
            WHERE id_estadoP IN (2, 3, 4, 5, 7)
              AND fecha_fin < CURDATE()
        ');
    }

    public function insertarHistorialVencidoCompleto(string $condicionEtapa2): bool
    {
        return $this->ejecutar("
            INSERT INTO historial_proyectos_usuarios
                (id_proyectos, id_estudiante, accion, motivo, realizado_por, fecha)
            SELECT
                pu.id_proyectos, pu.id_usuarios, 'vencido',
                'Proyecto vencido — estudiante concluyó actividades, pendiente de carta de terminación',
                0, NOW()
            FROM proyectos_usuarios pu
            JOIN proyectos p ON p.id_proyectos = pu.id_proyectos
            WHERE p.fecha_fin < CURDATE() AND p.id_estadoP = 6 AND pu.estado = 'activo'
              AND {$condicionEtapa2}
              AND NOT EXISTS (
                  SELECT 1 FROM historial_proyectos_usuarios h
                  WHERE h.id_proyectos = pu.id_proyectos AND h.id_estudiante = pu.id_usuarios AND h.accion = 'vencido'
              )
        ");
    }

    public function insertarHistorialBajaVencido(string $condicionEtapa2): bool
    {
        return $this->ejecutar("
            INSERT INTO historial_proyectos_usuarios
                (id_proyectos, id_estudiante, accion, motivo, realizado_por, fecha)
            SELECT
                pu.id_proyectos, pu.id_usuarios, 'baja',
                'Proyecto vencido sin concluir actividades', 0, NOW()
            FROM proyectos_usuarios pu
            JOIN proyectos p ON p.id_proyectos = pu.id_proyectos
            WHERE p.fecha_fin < CURDATE() AND p.id_estadoP = 6 AND pu.estado = 'activo'
              AND NOT ({$condicionEtapa2})
              AND NOT EXISTS (
                  SELECT 1 FROM historial_proyectos_usuarios h
                  WHERE h.id_proyectos = pu.id_proyectos AND h.id_estudiante = pu.id_usuarios
                    AND h.accion = 'baja' AND h.motivo = 'Proyecto vencido sin concluir actividades'
              )
        ");
    }

    public function darDeBajaEstudiantesVencidos(string $condicionEtapa2): bool
    {
        return $this->ejecutar("
            UPDATE proyectos_usuarios pu
            JOIN proyectos p ON p.id_proyectos = pu.id_proyectos
            SET pu.estado = 'baja', pu.fecha_baja = NOW(),
                pu.motivo_baja = 'Proyecto vencido sin concluir actividades'
            WHERE p.fecha_fin < CURDATE() AND p.id_estadoP = 6 AND pu.estado = 'activo'
              AND NOT ({$condicionEtapa2})
        ");
    }


    // 
    // CATÁLOGOS
    // 

    public function listarTematicas(): array
    {
        return $this->ejecutar(
            'SELECT id_tematica, nombre_tematica FROM gestion_proyectos.tematica'
        );
    }

    public function listarSubtematicas(int $id_tematica): array
    {
        return $this->ejecutar(
            'SELECT sub.id_subtematica, sub.nombre_subtematica
             FROM gestion_proyectos.subtematica AS sub
             JOIN tematica AS te ON sub.id_tematica = te.id_tematica
             WHERE te.id_tematica = ? AND sub.estado = 1',
            'i',
            [$id_tematica]
        );
    }

    public function listarPeriodoActual(): array
    {
        return $this->ejecutar(
            "SELECT id_periodos, periodo,
                    fecha_inicio AS FechaInicio, fecha_final AS FechaFinal,
                    CASE
                        WHEN CURDATE() BETWEEN fecha_inicio AND fecha_final THEN 'Activo'
                        WHEN CURDATE() < fecha_inicio THEN 'Pendiente'
                        ELSE 'Terminado'
                    END AS estado
             FROM periodos ORDER BY periodo DESC LIMIT 1"
        );
    }

    public function listarInstituto(): array
    {
        return $this->ejecutar(
            'SELECT id_instituto FROM gestion_proyectos.instituto ORDER BY id_instituto DESC LIMIT 1'
        );
    }

    public function buscarPeriodoActual(): ?array
    {
        $fila = $this->ejecutar(
            'SELECT fecha_inicio_proyectos, fecha_fin_proyectos
             FROM periodos ORDER BY id_periodos DESC LIMIT 1',
            '',
            [],
            false
        );

        return $fila ?: null;
    }


    // 
    // CRUD DE PROYECTOS
    // 

    public function insertarProyecto(
        int $id_investigador, int $id_estadoP, int $id_instituto, int $id_periodos,
        string $titulo, string $descripcion, string $objetivo,
        string $fecha_inicio, string $fecha_final, string $presupuesto,
        string $requisitos, string $Pre_requisitos, string $modalidad, int $AlumnosCantidad
    ): int {
        $this->ejecutar(
            'INSERT INTO proyectos
                (id_investigador, id_estadoP, id_instituto, id_periodos, titulo, descripcion, objetivo,
                 fecha_inicio, fecha_fin, presupuesto, actualizado_en, requisitos, pre_requisitos, modalidad, cantidad_estudiante)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?, ?)',
            'iiiisssssssssi',
            [
                $id_investigador, $id_estadoP, $id_instituto, $id_periodos,
                $titulo, $descripcion, $objetivo,
                $fecha_inicio, $fecha_final, $presupuesto,
                $requisitos, $Pre_requisitos, $modalidad, $AlumnosCantidad,
            ]
        );

        return (int)$this->conn->insert_id;
    }

    public function actualizarProyecto(
        int $id_proyecto, int $id_investigador,
        string $titulo, string $descripcion, string $objetivo,
        string $fecha_inicio, string $fecha_final, string $presupuesto,
        string $requisitos, string $Pre_requisitos, string $modalidad, int $AlumnosCantidad
    ): void {
        $this->ejecutar(
            'UPDATE proyectos SET
                titulo = ?, descripcion = ?, objetivo = ?, pre_requisitos = ?, requisitos = ?,
                cantidad_estudiante = ?, modalidad = ?, actualizado_en = NOW(),
                presupuesto = ?, fecha_inicio = ?, fecha_fin = ?
             WHERE id_proyectos = ? AND id_investigador = ?',
            'sssssiisisii',
            [
                $titulo, $descripcion, $objetivo, $Pre_requisitos, $requisitos,
                $AlumnosCantidad, $modalidad, $presupuesto,
                $fecha_inicio, $fecha_final,
                $id_proyecto, $id_investigador,
            ]
        );
    }


    // 
    // ACTUALIZAR ESTADO
    // 

    public function actualizarEstado(int $id_proyectos, int $numeroEstado): void
    {
        $this->ejecutar(
            'UPDATE proyectos SET id_estadoP = ?, actualizado_en = NOW() WHERE id_proyectos = ?',
            'ii',
            [$numeroEstado, $id_proyectos]
        );
    }

    public function actualizarEstadoRechazo(int $id_proyectos, int $num_motivo): void
    {
        $this->ejecutar(
            'UPDATE proyectos SET id_estadoP = ?, actualizado_en = NOW() WHERE id_proyectos = ?',
            'ii',
            [$num_motivo, $id_proyectos]
        );
    }

    public function insertarComentarioProyecto(int $id_proyectos, int $id_usuario, string $tipo, string $comentario): void
    {
        $this->ejecutar(
            'INSERT INTO proyectos_comentarios (id_proyectos, id_usuarios, tipo, comentario, fecha)
             VALUES (?, ?, ?, ?, CURDATE())',
            'iiss',
            [$id_proyectos, $id_usuario, $tipo, $comentario]
        );
    }

    public function actualizarCierreCancelado(int $id_proyectos): void
    {
        $this->ejecutar(
            "UPDATE tbl_cierres SET fecha_resultado = CURDATE(), estado = 'rechazado' WHERE id_proyectos = ?",
            'i',
            [$id_proyectos]
        );
    }

    public function buscarInvestigadorDeProyecto(int $id_proyectos): ?array
    {
        $fila = $this->ejecutar(
            'SELECT id_investigador FROM proyectos WHERE id_proyectos = ?',
            'i',
            [$id_proyectos],
            false
        );

        return $fila ?: null;
    }

    public function buscarPlantillaReporte(): ?array
    {
        $fila = $this->ejecutar(
            "SELECT pd.id_plantilla, pd.id_documento
             FROM plantillas_documentos pd
             INNER JOIN tipo_documento td ON td.id_tipo_documento = pd.id_tipo_documento
             WHERE pd.activo = 1 AND LOWER(td.nombre) LIKE 'reporte%' LIMIT 1",
            '',
            [],
            false
        );

        return $fila ?: null;
    }

    public function insertarSeguimiento(int $id_proyectos): int
    {
        $this->ejecutar(
            'INSERT INTO tbl_seguimiento (id_proyectos, fecha_activacion) VALUES (?, CURDATE())',
            'i',
            [$id_proyectos]
        );

        return (int)$this->conn->insert_id;
    }

    public function listarTiposTarea(): array
    {
        return $this->ejecutar('SELECT id_tareatipo FROM tipo_tarea ORDER BY id_tareatipo ASC');
    }

    public function insertarTarea(int $id_avances, int $id_tipo, int $estadoSinActivar, ?int $id_doc_reporte = null): void
    {
        if ($id_doc_reporte !== null) {
            $this->ejecutar(
                'INSERT INTO tareas (id_avances, id_tareatipo, id_estadoT, id_documento_recurso) VALUES (?, ?, ?, ?)',
                'iiii',
                [$id_avances, $id_tipo, $estadoSinActivar, $id_doc_reporte]
            );
        } else {
            $this->ejecutar(
                'INSERT INTO tareas (id_avances, id_tareatipo, id_estadoT) VALUES (?, ?, ?)',
                'iii',
                [$id_avances, $id_tipo, $estadoSinActivar]
            );
        }
    }

    public function insertarCierre(int $id_proyectos, int $id_investigador, ?float $porcentaje): void
    {
        $this->ejecutar(
            "INSERT INTO tbl_cierres (id_proyectos, id_supervisor, fecha_solicitud, porcentaje, estado)
             VALUES (?, ?, CURDATE(), ?, 'espera')",
            'iiid',
            [$id_proyectos, $id_investigador, $porcentaje]
        );
    }

    public function actualizarCierreAprobado(int $id_proyectos): void
    {
        $this->ejecutar(
            "UPDATE tbl_cierres SET fecha_resultado = CURDATE(), estado = 'aprobado' WHERE id_proyectos = ?",
            'i',
            [$id_proyectos]
        );
    }

    public function concluirEstudiantesProyecto(int $id_proyectos): void
    {
        $this->ejecutar(
            "UPDATE proyectos_usuarios SET fecha_terminacion = CURDATE(), estado = 'concluido' WHERE id_proyectos = ?",
            'i',
            [$id_proyectos]
        );
    }


    // 
    // PORCENTAJE DE AVANCE
    // 

    public function listarTareasAprobadas(int $id_proyecto): array
    {
        return $this->ejecutar(
            'SELECT taus.id_estadoT
             FROM tareas_usuarios AS taus
             JOIN tareas AS tare ON tare.id_tarea = taus.id_tarea
             JOIN tbl_seguimiento AS tbse ON tare.id_avances = tbse.id_avances
             WHERE tbse.id_proyectos = ? AND taus.id_estadoT = 5',
            'i',
            [$id_proyecto]
        );
    }


    // 
    // DETALLES DEL PROYECTO
    // 

    public function buscarProyectoPorRol(string $sqlDetalle, string $tipos, array $params): ?array
    {
        $fila = $this->ejecutar($sqlDetalle, $tipos, $params, false);
        return $fila ?: null;
    }

    public function buscarInvestigadorProyecto(int $id_proyecto): ?array
    {
        $fila = $this->ejecutar(
            'SELECT usua.id_usuarios, usua.nombre, usua.apellido_paterno, usua.apellido_materno,
                    nisn.nombre AS nivel_sni, grac.nombre AS grado_academico
             FROM investigadores AS inve
             JOIN usuarios AS usua          ON usua.id_usuarios = inve.id_usuarios
             JOIN niveles_sni AS nisn        ON nisn.id_nivel    = inve.id_nivel_sni
             JOIN grados_academicos AS grac  ON grac.id_grado    = inve.id_grado
             JOIN proyectos AS proy          ON proy.id_investigador = inve.id_usuarios
             WHERE proy.id_proyectos = ?',
            'i',
            [$id_proyecto],
            false
        );

        return $fila ?: null;
    }

    public function buscarAreaUsuario(int $id_usuario): ?array
    {
        $fila = $this->ejecutar(
            'SELECT arco.nombre_area AS area_conocimiento, GROUP_CONCAT(subco.nombre_subarea) AS subarea
             FROM usuarios AS us
             JOIN usuarios_subareas AS ussu       ON ussu.id_usuarios = us.id_usuarios
             JOIN subareas_conocimiento AS subco   ON ussu.id_subarea  = subco.id_subarea
             JOIN areas_conocimiento AS arco       ON arco.id_area     = subco.id_area
             WHERE us.id_usuarios = ?
             GROUP BY us.id_usuarios, subco.id_subarea, arco.id_area',
            'i',
            [$id_usuario],
            false
        );

        return $fila ?: null;
    }

    public function buscarLineaInvestigador(int $id_proyecto): ?array
    {
        $fila = $this->ejecutar(
            'SELECT liin.nombre AS linea
             FROM investigadores AS inve
             JOIN investigador_lineas_investigacion AS inliin ON inliin.id_usuarios = inve.id_usuarios
             JOIN lineas_investigacion AS liin                ON liin.id_linea      = inliin.id_linea
             JOIN proyectos AS proy                           ON proy.id_investigador = inve.id_usuarios
             WHERE proy.id_proyectos = ?',
            'i',
            [$id_proyecto],
            false
        );

        return $fila ?: null;
    }

    public function listarSubtematicasProyecto(int $id_proyecto): array
    {
        return $this->ejecutar(
            'SELECT sub.id_subtematica, sub2.nombre_subtematica AS nombre
             FROM proyectos_subtematica AS sub
             JOIN subtematica AS sub2 ON sub.id_subtematica = sub2.id_subtematica
             WHERE sub.id_proyectos = ? AND sub2.estado = 1',
            'i',
            [$id_proyecto]
        );
    }

    public function listarEstudiantesProyecto(int $id_proyecto): array
    {
        return $this->ejecutar(
            'SELECT usua.id_usuarios, usua.nombre, usua.apellido_paterno, usua.apellido_materno,
                    carr.nombre_carrera AS carrera
             FROM estudiantes AS estu
             JOIN usuarios AS usua         ON usua.id_usuarios  = estu.id_usuarios
             JOIN carreras AS carr          ON carr.id_carrera   = estu.id_carrera
             JOIN proyectos_usuarios AS prus ON prus.id_usuarios = estu.id_usuarios
             JOIN proyectos AS proy          ON proy.id_proyectos = prus.id_proyectos
             WHERE proy.id_proyectos = ?',
            'i',
            [$id_proyecto]
        );
    }

    public function listarComentariosProyecto(int $id_proyecto): array
    {
        return $this->ejecutar(
            "SELECT
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
             JOIN usuarios AS usua  ON usua.id_usuarios  = prco.id_usuarios
             WHERE proy.id_proyectos = ?
             ORDER BY fecha DESC",
            'i',
            [$id_proyecto]
        );
    }

    public function listarIntegrantes(int $id_proyecto): array
    {
        return $this->ejecutar(
            'SELECT
                u.id_usuarios,
                u.nombre, u.apellido_paterno, u.apellido_materno,
                c.nombre_carrera AS carrera,
                pu.estado,
                ep.estado AS estado_proceso,
                hpu.motivo
             FROM proyectos_usuarios pu
             JOIN usuarios u    ON u.id_usuarios   = pu.id_usuarios
             JOIN estudiantes e ON e.id_usuarios   = u.id_usuarios
             JOIN carreras c    ON e.id_carrera     = c.id_carrera
             JOIN estados_proceso ep ON pu.id_estados_proceso = ep.id_estados_proceso
             LEFT JOIN (
                 SELECT h1.id_proyectos, h1.id_estudiante, h1.motivo
                 FROM historial_proyectos_usuarios h1
                 INNER JOIN (
                     SELECT id_proyectos, id_estudiante, MAX(id_historial) AS max_id
                     FROM historial_proyectos_usuarios
                     WHERE accion = \'baja\'
                     GROUP BY id_proyectos, id_estudiante
                 ) h2 ON h1.id_historial = h2.max_id
             ) hpu ON hpu.id_proyectos = pu.id_proyectos AND hpu.id_estudiante = pu.id_usuarios
             WHERE pu.id_proyectos = ?',
            'i',
            [$id_proyecto]
        );
    }

    public function buscarEstudianteEnProyecto(int $id_proyecto, int $id_estudiante): ?array
    {
        $fila = $this->ejecutar(
            'SELECT u.nombre, u.apellido_paterno, u.apellido_materno, p.titulo
             FROM usuarios u
             JOIN proyectos_usuarios pu ON pu.id_usuarios   = u.id_usuarios
             JOIN proyectos p           ON p.id_proyectos   = pu.id_proyectos
             WHERE pu.id_proyectos = ? AND pu.id_usuarios = ?',
            'ii',
            [$id_proyecto, $id_estudiante],
            false
        );

        return $fila ?: null;
    }


    // 
    // SUBTEMATICAS
    // 

    public function vincularSubtematica(int $id_proyecto, int $id_subtematica): void
    {
        $this->ejecutar(
            'INSERT INTO proyectos_subtematica (id_proyectos, id_subtematica) VALUES (?, ?)',
            'ii',
            [$id_proyecto, $id_subtematica]
        );
    }

    public function actualizarSubtematica(int $id_proyecto, int $id_subtematica): void
    {
        $this->ejecutar(
            'DELETE FROM proyectos_subtematica WHERE id_proyectos = ?',
            'i',
            [$id_proyecto]
        );
        $this->ejecutar(
            'INSERT INTO proyectos_subtematica (id_subtematica, id_proyectos) VALUES (?, ?)',
            'ii',
            [$id_subtematica, $id_proyecto]
        );
    }


    // 
    // BAJA Y REACTIVACIÓN DE ESTUDIANTES (transaccional)
    // 

    public function bajaEstudiante(int $id_proyecto, int $id_estudiante, ?string $motivo, int $usuario): array
    {
        $this->conn->begin_transaction();
        try {
            $data = $this->ejecutar(
                'SELECT estado FROM proyectos_usuarios WHERE id_proyectos = ? AND id_usuarios = ?',
                'ii',
                [$id_proyecto, $id_estudiante],
                false
            );
            if (($data['estado'] ?? '') !== 'activo') {
                throw new Exception('El estudiante no está activo');
            }

            $this->ejecutar(
                "UPDATE proyectos_usuarios
                 SET estado = 'baja', fecha_baja = NOW(), motivo_baja = ?, reincorporacion = 0
                 WHERE id_proyectos = ? AND id_usuarios = ?",
                'sii',
                [$motivo, $id_proyecto, $id_estudiante]
            );
            $this->ejecutar(
                "INSERT INTO historial_proyectos_usuarios (id_proyectos, id_estudiante, accion, motivo, realizado_por)
                 VALUES (?, ?, 'baja', ?, ?)",
                'iisi',
                [$id_proyecto, $id_estudiante, $motivo, $usuario]
            );

            $this->conn->commit();
            return ['success' => true];

        } catch (Throwable $e) {
            $this->conn->rollback();
            return ['success' => false, 'msg' => $e->getMessage()];
        }
    }

    public function reactivarEstudiante(int $id_proyecto, int $id_estudiante, int $usuario): array
    {
        $this->conn->begin_transaction();
        try {
            $data = $this->ejecutar(
                'SELECT pu.estado, p.fecha_fin
                 FROM proyectos_usuarios pu
                 JOIN proyectos p ON p.id_proyectos = pu.id_proyectos
                 WHERE pu.id_proyectos = ? AND pu.id_usuarios = ?',
                'ii',
                [$id_proyecto, $id_estudiante],
                false
            );

            if (!$data)                             throw new Exception('Registro no encontrado');
            if ($data['estado'] !== 'baja')         throw new Exception('Solo se puede reactivar si está en baja');
            if ($data['fecha_fin'] < date('Y-m-d')) throw new Exception('El proyecto está vencido, requiere prórroga');

            $this->ejecutar(
                "UPDATE proyectos_usuarios
                 SET estado = 'activo', fecha_baja = NULL, motivo_baja = NULL, reincorporacion = 1
                 WHERE id_proyectos = ? AND id_usuarios = ?",
                'ii',
                [$id_proyecto, $id_estudiante]
            );
            $this->ejecutar(
                "INSERT INTO historial_proyectos_usuarios (id_proyectos, id_estudiante, accion, realizado_por)
                 VALUES (?, ?, 'reactivado', ?)",
                'iii',
                [$id_proyecto, $id_estudiante, $usuario]
            );

            $this->conn->commit();
            return ['success' => true];

        } catch (Throwable $e) {
            $this->conn->rollback();
            return ['success' => false, 'msg' => $e->getMessage()];
        }
    }


    // 
    // HISTORIAL DE ESTUDIANTE EN PROYECTO
    // 

    public function contarHistorial(int $id_proyecto, int $id_usuario): int
    {
        $fila = $this->ejecutar(
            'SELECT COUNT(*) AS total
             FROM historial_proyectos_usuarios
             WHERE id_proyectos = ? AND id_estudiante = ?',
            'ii',
            [$id_proyecto, $id_usuario],
            false
        );

        return (int)($fila['total'] ?? 0);
    }

    public function listarHistorial(int $id_proyecto, int $id_usuario, int $desde, int $por_pagina): array
    {
        return $this->ejecutar(
            'SELECT h.accion AS tipo_evento, h.motivo AS descripcion, h.fecha, u.nombre AS usuario
             FROM historial_proyectos_usuarios h
             LEFT JOIN usuarios u ON h.id_estudiante = u.id_usuarios
             WHERE h.id_proyectos = ? AND h.id_estudiante = ?
             ORDER BY h.fecha DESC
             LIMIT ?, ?',
            'iiii',
            [$id_proyecto, $id_usuario, $desde, $por_pagina]
        );
    }
}