<?php
// Repositorios/AreaConocimientoRepositorio.php

require_once __DIR__ . '/../Modelos/BaseModelo.php';

/**
 * AreaConocimientoRepositorio
 *
 * Responsabilidad exclusiva: ejecutar consultas SQL sobre las tablas
 * `areas_conocimiento` y `subareas_conocimiento`.
 * No contiene lógica de negocio.
 */
class AreaConocimientoRepositorio extends BaseModelo
{
    public function __construct(mysqli $conn)
    {
        parent::__construct($conn);
    }


    // 
    // CONTEO PARA PAGINACIÓN
    // 

    public function contarAreas(?string $buscar, int $filtro): int
    {
        $where  = ['1 = 1'];
        $params = [];
        $types  = '';

        if ($filtro === 0 || $filtro === 1) {
            $where[]  = 'area.estado = ?';
            $params[] = $filtro;
            $types   .= 'i';
        }

        if (!empty($buscar)) {
            $where[]  = 'area.nombre_area LIKE ?';
            $params[] = "%$buscar%";
            $types   .= 's';
        }

        $sql = 'SELECT COUNT(*) AS total
                FROM areas_conocimiento area
                WHERE ' . implode(' AND ', $where);

        return (int)($this->ejecutar($sql, $types, $params, false)['total'] ?? 0);
    }


    // 
    // LISTADO CON FILTROS Y PAGINACIÓN
    // 

    public function listarAreas(?string $buscar, int $filtro, int $desde, int $por_pagina): array
    {
        $where  = [];
        $params = [];
        $types  = '';

        if ($filtro === 0 || $filtro === 1) {
            $where[]  = 'area.estado = ?';
            $params[] = $filtro;
            $types   .= 'i';
        }

        if (!empty($buscar)) {
            $where[]  = 'area.nombre_area LIKE ?';
            $params[] = "%$buscar%";
            $types   .= 's';
        }

        $sql = "SELECT
                    area.id_area,
                    area.nombre_area        AS nombre,
                    area.descripcion_area   AS descripcion,
                    area.fecha_creacion     AS creacion,
                    area.fecha_modificacion AS modificacion,
                    (SELECT COUNT(*)
                     FROM subareas_conocimiento suba2
                     WHERE suba2.id_area = area.id_area
                       AND suba2.estado  = 1) AS total,
                    CASE
                        WHEN area.estado = 1 THEN 'Activo'
                        ELSE 'Desactivado'
                    END AS estado
                FROM areas_conocimiento area
                LEFT JOIN subareas_conocimiento suba ON suba.id_area = area.id_area";

        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql     .= ' GROUP BY area.id_area ORDER BY area.id_area ASC LIMIT ?, ?';
        $params[] = $desde;
        $params[] = $por_pagina;
        $types   .= 'ii';

        return $this->ejecutar($sql, $types, $params);
    }


    // 
    // DETALLE DE UN ÁREA
    // 

    public function buscarAreaPorId(int $id_area): ?array
    {
        $fila = $this->ejecutar(
            "SELECT
                id_area,
                nombre_area      AS nombre,
                descripcion_area AS descripcion,
                CASE
                    WHEN estado = 1 THEN 'Activo'
                    ELSE 'Desactivado'
                END AS estado
             FROM areas_conocimiento
             WHERE id_area = ?",
            'i',
            [$id_area],
            false
        );

        return $fila ?: null;
    }

    public function buscarAreaDetalle(int $id_area): ?array
    {
        $fila = $this->ejecutar(
            "SELECT
                id_area,
                nombre_area      AS nombre,
                descripcion_area AS descripcion,
                CASE
                    WHEN estado = 1 THEN 'Activo'
                    ELSE 'Desactivado'
                END AS estado
             FROM areas_conocimiento
             WHERE id_area = ?",
            'i',
            [$id_area],
            false
        );

        return $fila ?: null;
    }

    public function listarSubareasPorArea(int $id_area, bool $soloActivas = false): array
    {
        $sql    = 'SELECT id_subarea, nombre_subarea AS nombre, estado
                   FROM subareas_conocimiento
                   WHERE id_area = ?';
        $types  = 'i';
        $params = [$id_area];

        if ($soloActivas) {
            $sql .= ' AND estado = 1';
        }

        return $this->ejecutar($sql, $types, $params);
    }


    // 
    // CREAR
    // 

    public function insertarArea(string $nombre, string $descripcion): int
    {
        $this->ejecutar(
            'INSERT INTO areas_conocimiento (nombre_area, descripcion_area, estado)
             VALUES (?, ?, 1)',
            'ss',
            [$nombre, $descripcion]
        );

        return (int)$this->conn->insert_id;
    }

    public function insertarSubarea(int $id_area, string $nombre_subarea): void
    {
        $this->ejecutar(
            'INSERT INTO subareas_conocimiento (id_area, nombre_subarea, estado)
             VALUES (?, ?, 1)',
            'is',
            [$id_area, $nombre_subarea]
        );
    }

    public function registrarSubareaSimple(int $id_area, string $nombre_subarea): void
    {
        $this->ejecutar(
            'INSERT INTO subareas_conocimiento (id_area, nombre_subarea) VALUES (?, ?)',
            'is',
            [$id_area, $nombre_subarea]
        );
    }


    // 
    // ACTUALIZAR
    // 

    public function actualizarArea(int $id_area, string $nombre, string $descripcion): void
    {
        $this->ejecutar(
            'UPDATE areas_conocimiento
             SET nombre_area = ?, descripcion_area = ?, fecha_modificacion = NOW()
             WHERE id_area = ?',
            'ssi',
            [$nombre, $descripcion, $id_area]
        );
    }

    public function actualizarSubarea(int $id_subarea, string $nombre): void
    {
        $this->ejecutar(
            'UPDATE subareas_conocimiento
             SET nombre_subarea = ?, fecha_modificacion = NOW()
             WHERE id_subarea = ?',
            'si',
            [$nombre, $id_subarea]
        );
    }


    // 
    // SOFT DELETE
    // 

    public function cambiarEstadoArea(int $id_area, int $estado): void
    {
        $this->ejecutar(
            'UPDATE areas_conocimiento SET estado = ? WHERE id_area = ?',
            'ii',
            [$estado, $id_area]
        );

        $this->ejecutar(
            'UPDATE subareas_conocimiento SET estado = ? WHERE id_area = ?',
            'ii',
            [$estado, $id_area]
        );
    }

    public function cambiarEstadoSubarea(int $id_subarea, int $estado): void
    {
        $this->ejecutar(
            'UPDATE subareas_conocimiento
             SET estado = ?, fecha_modificacion = NOW()
             WHERE id_subarea = ?',
            'ii',
            [$estado, $id_subarea]
        );
    }


    // 
    // VALIDACIÓN DE DUPLICIDAD
    // 

    /**
     * Lanza excepción si ya existe una subárea activa con el mismo nombre
     * dentro del área, excluyendo opcionalmente la propia subárea en edición.
     *
     * @throws Exception
     */
    public function verificarDuplicidadSubarea(int $id_area, string $nombre, mixed $id_excluir = null): void
    {
        $sql    = 'SELECT COUNT(*) AS total
                   FROM subareas_conocimiento
                   WHERE id_area = ?
                     AND LOWER(nombre_subarea) = LOWER(?)
                     AND estado = 1';
        $params = [$id_area, $nombre];
        $types  = 'is';

        $id_excluir_int = filter_var($id_excluir, FILTER_VALIDATE_INT);
        if ($id_excluir_int !== false) {
            $sql    .= ' AND id_subarea != ?';
            $params[] = $id_excluir_int;
            $types   .= 'i';
        }

        $total = (int)($this->ejecutar($sql, $types, $params, false)['total'] ?? 0);

        if ($total > 0) {
            throw new Exception('La subárea ya existe en esta área de conocimiento.');
        }
    }


    // 
    // OBTENER IDS DE SUBAREAS
    // 

    /**
     * @return int[]
     */
    public function obtenerIdsSubareas(int $id_area): array
    {
        $filas = $this->ejecutar(
            'SELECT id_subarea FROM subareas_conocimiento WHERE id_area = ? AND estado = 1',
            'i',
            [$id_area]
        );

        return array_column($filas, 'id_subarea');
    }
}
