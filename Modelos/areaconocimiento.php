<?php
// Modelos/areaconocimiento.php

require_once __DIR__ . '/../publico/config/conexion.php';
require_once __DIR__ . '/BaseModelo.php';

class AreaConocimiento extends BaseModelo
{
    public function __construct(mysqli $conn)
    {
        parent::__construct($conn);
    }


    // 
    // TABLA PRINCIPAL
    // 

    public function obtenerAreasTablaFiltro(?string $buscar, int $filtro): array
    {
        $total         = $this->obtenerCantidadArea($buscar, $filtro);
        $por_pagina    = 6;
        $pagina        = max(1, (int)($_GET['pagina'] ?? 1));
        $desde         = ($pagina - 1) * $por_pagina;
        $total_paginas = max(1, (int)ceil($total / $por_pagina));

        $where  = [];
        $params = [];
        $types  = '';

        // Filtro por estado: 1 = Activo, 0 = Desactivado, 2 = Total (sin filtro)
        if ($filtro === 0 || $filtro === 1) {
            $where[]  = "area.estado = ?";
            $params[] = $filtro;
            $types   .= 'i';
        }

        if (!empty($buscar)) {
            $where[]  = "area.nombre_area LIKE ?";
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
            $sql .= " WHERE " . implode(" AND ", $where);
        }

        $sql .= " GROUP BY area.id_area ORDER BY area.id_area ASC LIMIT ?, ?";

        $params[] = $desde;
        $params[] = $por_pagina;
        $types   .= 'ii';

        return [
            "area"       => $this->ejecutar($sql, $types, $params),
            "paginacion" => compact("total", "por_pagina", "pagina") + ["total_paginas" => $total_paginas],
        ];
    }

    private function obtenerCantidadArea(?string $buscar, int $filtro): int
    {
        $where  = ["1 = 1"];
        $params = [];
        $types  = '';

        if ($filtro === 0 || $filtro === 1) {
            $where[]  = "area.estado = ?";
            $params[] = $filtro;
            $types   .= 'i';
        }

        if (!empty($buscar)) {
            $where[]  = "area.nombre_area LIKE ?";
            $params[] = "%$buscar%";
            $types   .= 's';
        }

        $sql = "SELECT COUNT(*) AS total
                FROM areas_conocimiento area
                WHERE " . implode(" AND ", $where);

        return (int)($this->ejecutar($sql, $types, $params, false)['total'] ?? 0);
    }


    // 
    // DATOS PARA EL FORMULARIO DE EDICIÓN
    // 

    public function obtenerAreaEditar(int $id_area): array
    {
        $area = $this->ejecutar(
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
            "i",
            [$id_area],
            false
        );

        if (!$area) {
            throw new Exception("Área no encontrada");
        }

        $subareas = $this->ejecutar(
            "SELECT id_subarea, nombre_subarea AS nombre, estado
             FROM subareas_conocimiento
             WHERE id_area = ? AND estado = 1",
            "i",
            [$id_area]
        );

        return [
            "area"     => $area,
            "subareas" => $subareas,
        ];
    }


    // 
    // DATOS PARA DETALLES
    // 

    public function obtenerAreasDetalles(int $id_area): array
    {
        $area = $this->ejecutar(
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
            "i",
            [$id_area],
            false
        );

        $subareas = $this->ejecutar(
            "SELECT id_subarea, nombre_subarea AS nombre, estado
             FROM subareas_conocimiento
             WHERE id_area = ?",
            "i",
            [$id_area]
        );

        return [
            "area"     => $area,
            "subareas" => $subareas,
        ];
    }


    // 
    // CREAR
    // 

    /**
     * Inserta el área y sus subareas.
     * La transacción es responsabilidad del controlador.
     *
     * @return int  ID del área creada.
     * @throws Exception
     */
    public function crearAreaCompleta(string $nombre, string $descripcion, array $subareas): int
    {
        $this->ejecutar(
            "INSERT INTO areas_conocimiento (nombre_area, descripcion_area, estado)
             VALUES (?, ?, 1)",
            "ss",
            [$nombre, $descripcion]
        );

        $id_area = (int)$this->conn->insert_id;

        foreach ($subareas as $sub) {
            $this->ejecutar(
                "INSERT INTO subareas_conocimiento (id_area, nombre_subarea, estado)
                 VALUES (?, ?, 1)",
                "is",
                [$id_area, $sub]
            );
        }

        return $id_area;
    }

    public function registrarsubarea(int $id_area, string $nombre_subarea): void
    {
        $this->ejecutar(
            "INSERT INTO subareas_conocimiento (id_area, nombre_subarea) VALUES (?, ?)",
            "is",
            [$id_area, $nombre_subarea]
        );
    }


    // 
    // ACTUALIZAR
    // 

    public function editarArea(string $nombre, string $descripcion, int $id_area): void
    {
        $this->ejecutar(
            "UPDATE areas_conocimiento
             SET nombre_area = ?, descripcion_area = ?, fecha_modificacion = NOW()
             WHERE id_area = ?",
            "ssi",
            [$nombre, $descripcion, $id_area]
        );
    }

    public function editarSubarea(int $id_subarea, string $nombre): void
    {
        $this->ejecutar(
            "UPDATE subareas_conocimiento
             SET nombre_subarea = ?, fecha_modificacion = NOW()
             WHERE id_subarea = ?",
            "si",
            [$nombre, $id_subarea]
        );
    }


    // 
    // SOFT DELETE
    // 

    /**
     * Desactiva (o reactiva) el área y todas sus subareas en cascada.
     *
     * @param int $estado  0 = desactivar, 1 = reactivar
     */
    public function eliminar_area(int $id_area, int $estado): void
    {
        $this->ejecutar(
            "UPDATE areas_conocimiento SET estado = ? WHERE id_area = ?",
            "ii",
            [$estado, $id_area]
        );

        $this->ejecutar(
            "UPDATE subareas_conocimiento SET estado = ? WHERE id_area = ?",
            "ii",
            [$estado, $id_area]
        );
    }

    public function eliminar_subarea(int $id_subarea, int $estado): void
    {
        $this->ejecutar(
            "UPDATE subareas_conocimiento
             SET estado = ?, fecha_modificacion = NOW()
             WHERE id_subarea = ?",
            "ii",
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
    public function comparar_Duplicidad_Subareas(int $id_area, string $nombre, mixed $id_excluir = null): void
    {
        $sql    = "SELECT COUNT(*) AS total
                   FROM subareas_conocimiento
                   WHERE id_area = ?
                     AND LOWER(nombre_subarea) = LOWER(?)
                     AND estado = 1";
        $params = [$id_area, $nombre];
        $types  = "is";

        // Solo excluir si es un ID numérico válido (no 'nuevo', no null, no '')
        $id_excluir_int = filter_var($id_excluir, FILTER_VALIDATE_INT);
        if ($id_excluir_int !== false) {
            $sql    .= " AND id_subarea != ?";
            $params[] = $id_excluir_int;
            $types   .= "i";
        }

        $total = (int)($this->ejecutar($sql, $types, $params, false)['total'] ?? 0);

        if ($total > 0) {
            throw new Exception("La subárea ya existe en esta área de conocimiento.");
        }
    }


    // 
    // OBTENER IDS DE SUBAREAS
    // 

    /**
     * Devuelve los IDs de las subareas activas de un área.
     * Usado en editarArea para comparar contra el formulario y detectar eliminaciones.
     *
     * @return int[]
     */
    public function obtenerIdsSubareas(int $id_area): array
    {
        $filas = $this->ejecutar(
            "SELECT id_subarea FROM subareas_conocimiento WHERE id_area = ? AND estado = 1",
            "i",
            [$id_area]
        );

        return array_column($filas, 'id_subarea');
    }
}