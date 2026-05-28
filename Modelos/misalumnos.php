<?php

/**
 * MisAlumnosModelo.php
 * Modelo para el módulo "Mis Alumnos" del investigador.
 * Solo lectura — sin acciones de baja/reactivación.
 * Ruta sugerida: /ITSFCP-PROYECTOS/Modelos/MisAlumnosModelo.php
*/
require_once __DIR__ . '/../publico/config/conexion.php';
require_once __DIR__ . '/BaseModelo.php';

class misalumnos extends BaseModelo
{
    // 
    //  CATÁLOGOS
    // 

    public function obtenerPeriodos(): array
    {
        return $this->ejecutar(
            "SELECT
                id_periodos,
                periodo,
                CASE
                    WHEN CURDATE() BETWEEN fecha_inicio AND fecha_final THEN 'Activo'
                    ELSE 'Terminado'
                END AS estado_periodo
             FROM periodos
             ORDER BY id_periodos DESC"
        );
    }

    public function obtenerProyectosInvestigador(int $id_investigador, int $id_periodo = 0): array
    {
        $wherePeriodo = $id_periodo ? 'AND p.id_periodos = ?' : '';
        $types        = $id_periodo ? 'ii' : 'i';
        $params       = $id_periodo ? [$id_investigador, $id_periodo] : [$id_investigador];

        return $this->ejecutar(
            "SELECT p.id_proyectos, p.titulo
             FROM proyectos p
             WHERE p.id_investigador = ? $wherePeriodo
             ORDER BY p.titulo ASC",
            $types,
            $params
        );
    }

    public function obtenerCarreras(): array
    {
        return $this->ejecutar(
            "SELECT id_carrera, nombre_carrera
             FROM carreras
             WHERE estado = 1
             ORDER BY nombre_carrera ASC"
        );
    }

    // 
    //  RESUMEN
    // 

    public function resumenAlumnos(int $id_investigador, array $f): array
    {
        [$where, $params, $types] = $this->buildWheres($id_investigador, $f);

        $row = $this->ejecutar(
            "SELECT
                COUNT(*)                                AS total_participaciones,
                COUNT(DISTINCT pu.id_usuarios)          AS alumnos_unicos,
                SUM(pu.estado = 'activo')               AS activos,
                SUM(pu.estado = 'concluido')            AS concluidos,
                SUM(pu.estado = 'baja')                 AS bajas
             FROM proyectos_usuarios pu
             JOIN proyectos  p   ON p.id_proyectos   = pu.id_proyectos
             JOIN periodos   per ON per.id_periodos   = p.id_periodos
             JOIN usuarios   u   ON u.id_usuarios     = pu.id_usuarios
             JOIN estudiantes e  ON e.id_usuarios     = pu.id_usuarios
             JOIN carreras   c   ON c.id_carrera      = e.id_carrera
             JOIN estados_proceso ep ON ep.id_estados_proceso = pu.id_estados_proceso
             $where",
            $types,
            $params,
            false
        );

        return $row ?: [
            'total_participaciones' => 0,
            'alumnos_unicos'        => 0,
            'activos'               => 0,
            'concluidos'            => 0,
            'bajas'                 => 0,
        ];
    }

    // 
    //  LISTADO PAGINADO
    // 

    public function contarAlumnos(int $id_investigador, array $f): int
    {
        [$where, $params, $types] = $this->buildWheres($id_investigador, $f);

        $row = $this->ejecutar(
            "SELECT COUNT(*) AS total
             FROM proyectos_usuarios pu
             JOIN proyectos  p   ON p.id_proyectos   = pu.id_proyectos
             JOIN periodos   per ON per.id_periodos   = p.id_periodos
             JOIN usuarios   u   ON u.id_usuarios     = pu.id_usuarios
             JOIN estudiantes e  ON e.id_usuarios     = pu.id_usuarios
             JOIN carreras   c   ON c.id_carrera      = e.id_carrera
             JOIN estados_proceso ep ON ep.id_estados_proceso = pu.id_estados_proceso
             $where",
            $types,
            $params,
            false
        );

        return (int)($row['total'] ?? 0);
    }

    public function obtenerAlumnos(int $id_investigador, array $f, int $desde, int $limite): array
    {
        [$where, $params, $types] = $this->buildWheres($id_investigador, $f);

        $params[] = $desde;
        $params[] = $limite;
        $types   .= 'ii';

        return $this->ejecutar(
            "SELECT
                pu.id_integrante,
                pu.id_proyectos,
                pu.id_usuarios,
                CONCAT(u.nombre, ' ', u.apellido_paterno, ' ', u.apellido_materno) AS nombre_completo,
                u.correo_institucional,
                e.matricula,
                c.nombre_carrera                     AS carrera,
                pu.estado                            AS estado_participacion,
                pu.fecha_asignacion,
                pu.fecha_terminacion,
                ep.estado                            AS estado_proceso,
                p.titulo                             AS titulo_proyecto,
                p.modalidad,
                per.periodo,
                CASE
                    WHEN CURDATE() BETWEEN per.fecha_inicio AND per.fecha_final THEN 'Activo'
                    ELSE 'Terminado'
                END                                  AS estado_periodo,
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
             LIMIT ?, ?",
            $types,
            $params
        );
    }

    // 
    //  WHERE DINÁMICO
    // 

    private function buildWheres(int $id_investigador, array $f): array
    {
        $cond   = ['p.id_investigador = ?'];
        $params = [$id_investigador];
        $types  = 'i';

        if (!empty($f['periodo'])) {
            $cond[]   = 'p.id_periodos = ?';
            $params[] = (int)$f['periodo'];
            $types   .= 'i';
        }
        if (!empty($f['id_proyecto'])) {
            $cond[]   = 'pu.id_proyectos = ?';
            $params[] = (int)$f['id_proyecto'];
            $types   .= 'i';
        }
        if (!empty($f['estado'])) {
            $permitidos = ['activo', 'concluido', 'baja', 'cancelado'];
            if (in_array($f['estado'], $permitidos, true)) {
                $cond[]   = 'pu.estado = ?';
                $params[] = $f['estado'];
                $types   .= 's';
            }
        }
        if (!empty($f['estado_proceso'])) {
            $cond[]   = 'ep.estado = ?';
            $params[] = $f['estado_proceso'];
            $types   .= 's';
        }
        if (!empty($f['carrera'])) {
            $cond[]   = 'e.id_carrera = ?';
            $params[] = (int)$f['carrera'];
            $types   .= 'i';
        }
        if (!empty($f['buscar'])) {
            $like     = '%' . $f['buscar'] . '%';
            $cond[]   = '(u.nombre LIKE ? OR u.apellido_paterno LIKE ? OR e.matricula LIKE ? OR u.correo_institucional LIKE ?)';
            array_push($params, $like, $like, $like, $like);
            $types   .= 'ssss';
        }

        return ['WHERE ' . implode(' AND ', $cond), $params, $types];
    }
}