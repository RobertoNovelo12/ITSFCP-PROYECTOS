<?php
// Modelos/TematicaRepositorio.php

require_once __DIR__ . '/../Modelos/BaseModelo.php';

/**
 * TematicaRepositorio
 *
 * Responsabilidad exclusiva: ejecutar consultas SQL sobre las tablas
 * `tematica` y `subtematica`. No contiene lógica de negocio.
 *
 * Es instanciado por Tematica (el modelo) mediante inyección por constructor.
 */
class TematicaRepositorio extends BaseModelo
{
    public function __construct(mysqli $conn)
    {
        parent::__construct($conn);
    }


    // ─────────────────────────────────────────────
    // CONTEO PARA PAGINACIÓN
    // ─────────────────────────────────────────────

    /**
     * Cuenta temáticas según el filtro de estado y búsqueda.
     *
     * @param int         $filtro  2 = todos | 1 = activos | 0 = desactivados
     * @param string|null $buscar  Texto libre sobre nombre_tematica
     */
    public function contarTematicas(int $filtro, ?string $buscar): int
    {
        $sql    = "SELECT COUNT(*) AS total FROM tematica AS tema WHERE 1";
        $params = [];
        $types  = '';

        if ($filtro === 0 || $filtro === 1) {
            $sql     .= ' AND tema.estado = ?';
            $params[] = $filtro;
            $types   .= 'i';
        }

        if (!empty($buscar)) {
            $sql     .= ' AND tema.nombre_tematica LIKE ?';
            $params[] = "%$buscar%";
            $types   .= 's';
        }

        $fila = $this->ejecutar($sql, $types, $params, false);
        return (int)($fila['total'] ?? 0);
    }


    // ─────────────────────────────────────────────
    // LISTADO PRINCIPAL
    // ─────────────────────────────────────────────

    /**
     * Devuelve la página de temáticas con INNER JOIN a subtematica
     * (muestra solo las que tienen al menos una subtemática).
     */
    public function listarTematicas(?string $buscar, int $desde, int $por_pagina): array
    {
        $sql    = "SELECT DISTINCT
                       tema.id_tematica,
                       tema.nombre_tematica       AS tematica,
                       tema.descripcion_tematica  AS descripcion,
                       tema.fecha_creacion        AS creacion,
                       tema.fecha_modificacion    AS modificacion,
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
            $sql     .= ' WHERE tema.nombre_tematica LIKE ?';
            $params[] = "%$buscar%";
            $types   .= 's';
        }

        $sql     .= ' GROUP BY tema.id_tematica ORDER BY tema.id_tematica ASC LIMIT ?, ?';
        $params[] = $desde;
        $params[] = $por_pagina;
        $types   .= 'ii';

        return $this->ejecutar($sql, $types, $params);
    }


    // ─────────────────────────────────────────────
    // LISTADO FILTRADO POR ESTADO
    // ─────────────────────────────────────────────

    /**
     * Devuelve la página de temáticas filtradas por estado (y búsqueda).
     *
     * @param int  $filtro  2 = todos | 1 = activos | 0 = desactivados
     */
    public function listarTematicasFiltro(int $filtro, ?string $buscar, int $desde, int $por_pagina): array
    {
        $sql = "SELECT
                    tema.id_tematica,
                    tema.nombre_tematica       AS tematica,
                    tema.descripcion_tematica  AS descripcion,
                    tema.fecha_creacion        AS creacion,
                    tema.fecha_modificacion    AS modificacion,
                    (SELECT COUNT(*)
                        FROM subtematica AS subt2
                        WHERE subt2.id_tematica = tema.id_tematica
                          AND subt2.estado = 1) AS total,
                    CASE
                        WHEN tema.estado = 1 THEN 'Activo'
                        ELSE 'Desactivado'
                    END AS estado
                FROM tematica AS tema";

        $where  = [];
        $params = [];
        $types  = '';

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

        $sql     .= ' GROUP BY tema.id_tematica ORDER BY tema.id_tematica ASC LIMIT ?, ?';
        $params[] = $desde;
        $params[] = $por_pagina;
        $types   .= 'ii';

        return $this->ejecutar($sql, $types, $params);
    }


    // ─────────────────────────────────────────────
    // DATOS PARA EDITAR
    // ─────────────────────────────────────────────

    /** Devuelve los datos de una temática por su ID. */
    public function buscarTematicaPorId(int $id_tematica): array
    {
        return $this->ejecutar(
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
    }

    /**
     * Devuelve las subtemáticas activas de una temática.
     * Con $soloActivas = false devuelve todas (usado en detalles).
     */
    public function buscarSubtematicas(int $id_tematica, bool $soloActivas = true): array
    {
        $sql = "SELECT subt.id_subtematica AS id,
                       subt.nombre_subtematica AS nombre,
                       subt.estado
                FROM tematica AS tema
                INNER JOIN subtematica AS subt ON tema.id_tematica = subt.id_tematica
                WHERE tema.id_tematica = ?";

        if ($soloActivas) {
            $sql .= ' AND subt.estado = 1';
        }

        return $this->ejecutar($sql, 'i', [$id_tematica]);
    }


    // ─────────────────────────────────────────────
    // CRUD TEMÁTICA
    // ─────────────────────────────────────────────

    /** Inserta una nueva temática y devuelve su ID. */
    public function insertarTematica(string $nombre, string $descripcion): int
    {
        $this->ejecutar(
            "INSERT INTO tematica (nombre_tematica, descripcion_tematica, estado) VALUES (?, ?, 1)",
            'ss',
            [$nombre, $descripcion]
        );
        return (int)$this->conn->insert_id;
    }

    /** Actualiza nombre y descripción de una temática. */
    public function actualizarTematica(string $nombre, string $descripcion, int $id_tematica): void
    {
        $this->ejecutar(
            "UPDATE tematica
             SET nombre_tematica = ?, descripcion_tematica = ?, fecha_modificacion = NOW()
             WHERE id_tematica = ?",
            'ssi',
            [$nombre, $descripcion, $id_tematica]
        );
    }

    /**
     * Cambia el estado de una temática y todas sus subtemáticas.
     * Ejecuta su propia transacción interna.
     */
    public function cambiarEstadoTematica(int $id_tematica, int $estado): void
    {
        $this->conn->begin_transaction();
        try {
            $this->ejecutar(
                "UPDATE tematica SET estado = ?, fecha_modificacion = NOW() WHERE id_tematica = ?",
                'ii',
                [$estado, $id_tematica]
            );
            $this->ejecutar(
                "UPDATE subtematica SET estado = ?, fecha_modificacion = NOW() WHERE id_tematica = ?",
                'ii',
                [$estado, $id_tematica]
            );
            $this->conn->commit();
        } catch (Exception $e) {
            $this->conn->rollback();
            throw $e;
        }
    }


    // ─────────────────────────────────────────────
    // CRUD SUBTEMÁTICAS
    // ─────────────────────────────────────────────

    /** Inserta una subtemática nueva. */
    public function insertarSubtematica(int $id_tematica, string $nombre): void
    {
        $this->ejecutar(
            "INSERT INTO subtematica (id_tematica, nombre_subtematica) VALUES (?, ?)",
            'is',
            [$id_tematica, $nombre]
        );
    }

    /** Actualiza el nombre de una subtemática. */
    public function actualizarSubtematica(int $id_subtematica, string $nombre): void
    {
        $this->ejecutar(
            "UPDATE subtematica
             SET nombre_subtematica = ?, fecha_modificacion = NOW()
             WHERE id_subtematica = ?",
            'si',
            [$nombre, $id_subtematica]
        );
    }

    /** Cambia el estado de una subtemática (0 = desactivada). */
    public function cambiarEstadoSubtematica(int $id_subtematica, int $estado): void
    {
        $this->ejecutar(
            "UPDATE subtematica SET estado = ?, fecha_modificacion = NOW() WHERE id_subtematica = ?",
            'ii',
            [$estado, $id_subtematica]
        );
    }

    /**
     * Devuelve los IDs de las subtemáticas activas de una temática.
     *
     * @return int[]
     */
    public function obtenerIdsSubtematicas(int $id_tematica): array
    {
        $filas = $this->ejecutar(
            "SELECT id_subtematica FROM subtematica WHERE id_tematica = ? AND estado = 1",
            'i',
            [$id_tematica]
        );
        return array_column($filas, 'id_subtematica');
    }


    // ─────────────────────────────────────────────
    // VALIDACIÓN DE DUPLICADOS
    // ─────────────────────────────────────────────

    /**
     * Verifica si ya existe una subtemática con el mismo nombre
     * dentro de la misma temática (excluyendo opcionalmente un ID).
     *
     * @return bool  true si existe duplicado.
     */
    public function existeSubtematicaDuplicada(int $id_tematica, string $nombre, ?int $id_excluir = null): bool
    {
        $sql    = "SELECT COUNT(*) AS total
                   FROM subtematica
                   WHERE id_tematica = ?
                     AND nombre_subtematica = ?
                     AND estado = 1";
        $params = [$id_tematica, $nombre];
        $types  = 'is';

        if ($id_excluir !== null) {
            $sql     .= ' AND id_subtematica != ?';
            $params[] = $id_excluir;
            $types   .= 'i';
        }

        $fila = $this->ejecutar($sql, $types, $params, false);
        return (int)($fila['total'] ?? 0) > 0;
    }
}
