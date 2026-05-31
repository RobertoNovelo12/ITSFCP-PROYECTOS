<?php
// Modelos/tematica.php

require_once __DIR__ . '/../publico/config/conexion.php';
require_once __DIR__ . '/BaseModelo.php';

class Tematica extends BaseModelo
{
    public function __construct(mysqli $conn)
    {
        parent::__construct($conn);
    }


    // 
    // DATOS PRINCIPAL (con paginación)
    // 

    public function obtenerTematicas(string $rol, ?string $buscar = null): string
    {
        $rol = strtolower($rol);

        $total      = $this->obtenerCantidadTematica(2, $rol, $buscar);
        $por_pagina = 6;
        $pagina     = empty($_GET['pagina']) ? 1 : max(1, intval($_GET['pagina']));
        $desde      = ($pagina - 1) * $por_pagina;
        $total_paginas = ($total > 0) ? (int)ceil($total / $por_pagina) : 1;

        if ($rol !== 'supervisor') {
            return json_encode([
                'tematica'   => [],
                'paginacion' => [
                    'total'         => 0,
                    'por_pagina'    => $por_pagina,
                    'pagina'        => $pagina,
                    'total_paginas' => 1,
                ],
            ]);
        }

        $sql = "SELECT DISTINCT
                    tema.id_tematica,
                    tema.nombre_tematica      AS tematica,
                    tema.descripcion_tematica AS descripcion,
                    tema.fecha_creacion       AS creacion,
                    tema.fecha_modificacion   AS modificacion,
                    (SELECT COUNT(*)
                        FROM subtematica AS subt2
                        WHERE subt2.id_tematica = tema.id_tematica
                          AND subt2.estado = 1) AS total,
                    CASE
                        WHEN tema.estado = 1 THEN 'Activo'
                        ELSE 'Desactivado'
                    END AS estado
                FROM tematica AS tema
                INNER JOIN subtematica AS subt ON tema.id_tematica = subt.id_tematica";

        $params = [];
        $types  = '';

        if (!empty($buscar)) {
            $sql     .= " WHERE tema.nombre_tematica LIKE ?";
            $params[] = "%$buscar%";
            $types   .= 's';
        }

        $sql     .= " GROUP BY tema.id_tematica ORDER BY tema.id_tematica ASC LIMIT ?, ?";
        $params[] = $desde;
        $params[] = $por_pagina;
        $types   .= 'ii';

        $filas = $this->ejecutar($sql, $types, $params);

        return json_encode([
            'tematica'   => $filas,
            'paginacion' => [
                'total'         => $total,
                'por_pagina'    => $por_pagina,
                'pagina'        => $pagina,
                'total_paginas' => $total_paginas,
            ],
        ]);
    }


    // 
    // DATOS PARA EDITAR (temática + subtemáticas)
    // 

    public function obtenerTematicasEditar(int $id_tematica): array
    {
        $tematica = $this->ejecutar(
            "SELECT id_tematica,
                    nombre_tematica      AS nombre,
                    descripcion_tematica AS descripcion,
                    CASE
                        WHEN estado = 1 THEN 'Activo'
                        ELSE 'Desactivado'
                    END AS estado
             FROM tematica
             WHERE id_tematica = ?",
            'i',
            [$id_tematica]
        );

        $subtematicas = $this->ejecutar(
            "SELECT subt.id_subtematica AS id,
                    subt.nombre_subtematica AS nombre,
                    subt.estado
             FROM tematica AS tema
             INNER JOIN subtematica AS subt ON tema.id_tematica = subt.id_tematica
             WHERE tema.id_tematica = ?
               AND subt.estado = 1",
            'i',
            [$id_tematica]
        );

        return [
            'tematica'     => $tematica,
            'subtematicas' => $subtematicas,
        ];
    }


    // 
    // DETALLES (temática + todas sus subtemáticas)
    // 

    public function obtenerTematicasDetalles(int $id_tematica): array
    {
        $tematica = $this->ejecutar(
            "SELECT tema.id_tematica,
                    tema.nombre_tematica      AS nombre,
                    tema.descripcion_tematica AS descripcion,
                    CASE
                        WHEN tema.estado = 1 THEN 'Activo'
                        ELSE 'Desactivado'
                    END AS estado
             FROM tematica AS tema
             WHERE tema.id_tematica = ?",
            'i',
            [$id_tematica]
        );

        $subtematicas = $this->ejecutar(
            "SELECT subt.id_subtematica AS id,
                    subt.nombre_subtematica AS nombre,
                    subt.estado
             FROM tematica AS tema
             INNER JOIN subtematica AS subt ON tema.id_tematica = subt.id_tematica
             WHERE tema.id_tematica = ?",
            'i',
            [$id_tematica]
        );

        return [
            'tematica'     => $tematica,
            'subtematicas' => $subtematicas,
        ];
    }


    // 
    // DATOS FILTRADOS (por estado) CON PAGINACIÓN
    // 

    /**
     * $filtro:  0 = Desactivados | 1 = Activos | 2 = Total (sin filtro de estado)
     */
    public function obtenerTematicasTablaFiltro(int $filtro, string $rol, ?string $buscar = null): string
    {
        $total      = $this->obtenerCantidadTematica($filtro, $rol, $buscar);
        $por_pagina = 6;
        $pagina     = empty($_GET['pagina']) ? 1 : max(1, intval($_GET['pagina']));
        $desde      = ($pagina - 1) * $por_pagina;
        $total_paginas = ($total > 0) ? (int)ceil($total / $por_pagina) : 1;

        if (strtolower($rol) !== 'supervisor') {
            return json_encode([
                'tematica'   => [],
                'paginacion' => [],
            ]);
        }

        // ── SQL BASE ─
        $sql = "SELECT
                    tema.id_tematica,
                    tema.nombre_tematica      AS tematica,
                    tema.descripcion_tematica AS descripcion,
                    tema.fecha_creacion       AS creacion,
                    tema.fecha_modificacion   AS modificacion,
                    (SELECT COUNT(*)
                        FROM subtematica AS subt2
                        WHERE subt2.id_tematica = tema.id_tematica
                          AND subt2.estado = 1) AS total,
                    CASE
                        WHEN tema.estado = 1 THEN 'Activo'
                        ELSE 'Desactivado'
                    END AS estado
                FROM tematica AS tema";

        $params = [];
        $types  = '';
        $where  = [];

        // Filtro por estado (2 = "Total", no se filtra)
        if ($filtro === 0 || $filtro === 1) {
            $where[]  = 'tema.estado = ?';
            $params[] = $filtro;
            $types   .= 'i';
        }

        if (!empty($buscar)) {
            $where[]  = 'tema.nombre_tematica LIKE ?';
            $params[] = "%$buscar%";
            $types   .= 's';
        }

        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql     .= " GROUP BY tema.id_tematica ORDER BY tema.id_tematica ASC LIMIT ?, ?";
        $params[] = $desde;
        $params[] = $por_pagina;
        $types   .= 'ii';

        $filas = $this->ejecutar($sql, $types, $params);

        return json_encode([
            'tematica'   => $filas,
            'paginacion' => [
                'total'         => $total,
                'por_pagina'    => $por_pagina,
                'pagina'        => $pagina,
                'total_paginas' => $total_paginas,
            ],
        ]);
    }


    // 
    // CONTEO PARA PAGINACIÓN
    // 

    /**
     * $numerofiltro: 2 = Total | 1 = Activos | 0 = Desactivados
     */
    public function obtenerCantidadTematica(int $numerofiltro, string $rol, ?string $buscar = null): int
    {
        if (strtolower($rol) !== 'supervisor') return 0;

        $sql    = "SELECT COUNT(*) AS total FROM tematica AS tema WHERE 1";
        $params = [];
        $types  = '';

        // Para Activos (1) o Desactivados (0) se añade filtro de estado
        if ($numerofiltro === 0 || $numerofiltro === 1) {
            $sql     .= " AND tema.estado = ?";
            $params[] = $numerofiltro;
            $types   .= 'i';
        }

        if (!empty($buscar)) {
            $sql     .= " AND tema.nombre_tematica LIKE ?";
            $params[] = "%$buscar%";
            $types   .= 's';
        }

        $fila = $this->ejecutar($sql, $types, $params, false);
        return (int)($fila['total'] ?? 0);
    }


    // 
    // CRUD TEMÁTICA
    // 

    public function registrarTematica(string $nombre, string $descripcion): int
    {
        $this->ejecutar(
            "INSERT INTO tematica (nombre_tematica, descripcion_tematica, estado) VALUES (?, ?, 1)",
            'ss',
            [$nombre, $descripcion]
        );
        return (int)$this->conn->insert_id;
    }

    public function editarTematica(string $nombre, string $descripcion, int $id_tematica): bool
    {
        return (bool)$this->ejecutar(
            "UPDATE tematica
             SET nombre_tematica = ?, descripcion_tematica = ?, fecha_modificacion = NOW()
             WHERE id_tematica = ?",
            'ssi',
            [$nombre, $descripcion, $id_tematica]
        );
    }

    /**
     * Desactiva (estado = 0) o reactiva una temática y todas sus subtemáticas.
     */
    public function eliminar_tematica(int $id_tematica, int $estado): bool
    {
        $this->conn->begin_transaction();
        try {
            $this->ejecutar(
                "UPDATE tematica
                 SET estado = ?, fecha_modificacion = NOW()
                 WHERE id_tematica = ?",
                'ii',
                [$estado, $id_tematica]
            );
            $this->ejecutar(
                "UPDATE subtematica
                 SET estado = ?, fecha_modificacion = NOW()
                 WHERE id_tematica = ?",
                'ii',
                [$estado, $id_tematica]
            );
            $this->conn->commit();
            return true;
        } catch (Exception $e) {
            $this->conn->rollback();
            throw $e;
        }
    }


    // 
    // CRUD SUBTEMÁTICAS
    // 

    public function registrarsubtematica(int $id_tematica, string $nombre_subtematica): bool
    {
        return (bool)$this->ejecutar(
            "INSERT INTO subtematica (id_tematica, nombre_subtematica) VALUES (?, ?)",
            'is',
            [$id_tematica, $nombre_subtematica]
        );
    }

    public function editarSubtematica(int $id_subtematica, string $nombre_subtematica): bool
    {
        return (bool)$this->ejecutar(
            "UPDATE subtematica
             SET nombre_subtematica = ?, fecha_modificacion = NOW()
             WHERE id_subtematica = ?",
            'si',
            [$nombre_subtematica, $id_subtematica]
        );
    }

    public function eliminar_subtematica(int $id_subtematica, int $estado): bool
    {
        return (bool)$this->ejecutar(
            "UPDATE subtematica
             SET estado = ?, fecha_modificacion = NOW()
             WHERE id_subtematica = ?",
            'ii',
            [$estado, $id_subtematica]
        );
    }

    public function obtenerIdsSubtematicas(int $id_tematica): array
    {
        $filas = $this->ejecutar(
            "SELECT id_subtematica FROM subtematica WHERE id_tematica = ? AND estado = 1",
            'i',
            [$id_tematica]
        );
        return array_column($filas, 'id_subtematica');
    }

    /**
     * Verifica que no exista duplicado de nombre en la misma temática.
     * Lanza Exception si hay duplicado.
     */
    public function comparar_Duplicidad_Subtematica(int $id_tematica, string $nombre, mixed $id_excluir = null): void
    {
        $sql    = "SELECT COUNT(*) AS total
                   FROM subtematica
                   WHERE id_tematica = ?
                     AND nombre_subtematica = ?
                     AND estado = 1";
        $params = [$id_tematica, $nombre];
        $types  = 'is';

        $id_exc = (!empty($id_excluir) && $id_excluir !== 'nuevo') ? (int)$id_excluir : null;

        if ($id_exc) {
            $sql     .= " AND id_subtematica != ?";
            $params[] = $id_exc;
            $types   .= 'i';
        }

        $fila = $this->ejecutar($sql, $types, $params, false);

        if ((int)($fila['total'] ?? 0) > 0) {
            throw new Exception("La subtemática '$nombre' ya existe en esta temática.");
        }
    }
}
