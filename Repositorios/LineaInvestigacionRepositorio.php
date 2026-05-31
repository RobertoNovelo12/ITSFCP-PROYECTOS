<?php
// Repositorios/LineaInvestigacionRepositorio.php

require_once __DIR__ . '/../Modelos/BaseModelo.php';

/**
 * LineaInvestigacionRepositorio
 *
 * Responsabilidad exclusiva: ejecutar consultas SQL sobre la tabla `lineas_investigacion`.
 * No contiene lógica de negocio.
 */
class LineaInvestigacionRepositorio extends BaseModelo
{
    public function __construct(mysqli $conn)
    {
        parent::__construct($conn);
    }


    // 
    // CONTEO PARA PAGINACIÓN
    // 

    public function contarLineas(?string $buscar, int $filtro): int
    {
        $params = [];
        $types  = '';
        $sql    = 'SELECT COUNT(*) AS total FROM lineas_investigacion';
        $sql   .= $this->construirWhere($params, $types, $buscar, $filtro);

        return (int)($this->ejecutar($sql, $types, $params, false)['total'] ?? 0);
    }


    // 
    // LISTADO CON FILTROS Y PAGINACIÓN
    // 

    public function listarLineas(?string $buscar, int $filtro, int $desde, int $por_pagina): array
    {
        $params = [];
        $types  = '';

        $sql = "SELECT
                    id_linea,
                    nombre,
                    descripcion,
                    fecha_creacion AS crear,
                    CASE
                        WHEN estado = 1 THEN 'Activo'
                        WHEN estado = 0 THEN 'Desactivado'
                        ELSE 'Desconocido'
                    END AS estados
                FROM lineas_investigacion";

        $sql     .= $this->construirWhere($params, $types, $buscar, $filtro);
        $sql     .= ' ORDER BY id_linea ASC LIMIT ?, ?';
        $params[] = $desde;
        $params[] = $por_pagina;
        $types   .= 'ii';

        return $this->ejecutar($sql, $types, $params);
    }


    // 
    // DETALLE / EDICIÓN
    // 

    public function buscarParaEditar(int $id_linea): ?array
    {
        $fila = $this->ejecutar(
            "SELECT
                id_linea,
                nombre,
                descripcion,
                CASE
                    WHEN estado = 1 THEN 'Activo'
                    WHEN estado = 0 THEN 'Desactivado'
                    ELSE 'Desconocido'
                END AS estado
             FROM lineas_investigacion
             WHERE id_linea = ?",
            'i',
            [$id_linea],
            false
        );

        return $fila ?: null;
    }

    public function buscarDetalle(int $id_linea): ?array
    {
        $fila = $this->ejecutar(
            "SELECT
                id_linea,
                nombre,
                descripcion,
                fecha_creacion,
                fecha_modificacion,
                CASE
                    WHEN estado = 1 THEN 'Activo'
                    WHEN estado = 0 THEN 'Desactivado'
                    ELSE 'Desconocido'
                END AS estado
             FROM lineas_investigacion
             WHERE id_linea = ?",
            'i',
            [$id_linea],
            false
        );

        return $fila ?: null;
    }

    public function buscarPorId(int $id_linea, bool $forUpdate = false): ?array
    {
        $sql = 'SELECT estado FROM lineas_investigacion WHERE id_linea = ?';
        if ($forUpdate) {
            $sql .= ' FOR UPDATE';
        }

        return $this->ejecutar($sql, 'i', [$id_linea], false) ?: null;
    }

    public function buscarNombrePorId(int $id_linea): ?array
    {
        $fila = $this->ejecutar(
            'SELECT nombre FROM lineas_investigacion WHERE id_linea = ?',
            'i',
            [$id_linea],
            false
        );

        return $fila ?: null;
    }

    public function buscarPorNombre(string $nombre): ?array
    {
        $fila = $this->ejecutar(
            'SELECT id_linea FROM lineas_investigacion WHERE nombre = ? LIMIT 1',
            's',
            [$nombre],
            false
        );

        return $fila ?: null;
    }


    // 
    // CREAR
    // 

    public function insertarLinea(string $nombre, string $descripcion): int
    {
        $this->ejecutar(
            'INSERT INTO lineas_investigacion (nombre, descripcion, estado, fecha_creacion)
             VALUES (?, ?, 1, NOW())',
            'ss',
            [$nombre, $descripcion]
        );

        return (int)$this->conn->insert_id;
    }


    // 
    // ACTUALIZAR
    // 

    public function actualizarLinea(string $nombre, string $descripcion, int $id_linea): void
    {
        $this->ejecutar(
            'UPDATE lineas_investigacion
             SET nombre = ?, descripcion = ?, fecha_modificacion = NOW()
             WHERE id_linea = ?',
            'ssi',
            [$nombre, $descripcion, $id_linea]
        );
    }

    public function reactivarLinea(int $id_linea): int
    {
        $this->ejecutar(
            'UPDATE lineas_investigacion
             SET estado = 1, fecha_modificacion = NOW()
             WHERE id_linea = ? AND estado = 0',
            'i',
            [$id_linea]
        );

        return $this->conn->affected_rows;
    }


    // 
    // SOFT DELETE
    // 

    public function desactivarLinea(int $id_linea): int
    {
        $this->ejecutar(
            'UPDATE lineas_investigacion
             SET estado = 0, fecha_modificacion = NOW()
             WHERE id_linea = ? AND estado <> 0',
            'i',
            [$id_linea]
        );

        return $this->conn->affected_rows;
    }


    // 
    // VERIFICACIÓN DE DUPLICIDAD
    // 

    /**
     * @return array{activo: int, desactivado: int}
     */
    public function verificarLinea(string $nombre): array
    {
        $fila = $this->ejecutar(
            "SELECT
                EXISTS(SELECT 1 FROM lineas_investigacion WHERE estado = 1 AND nombre = ?) AS activo,
                EXISTS(SELECT 1 FROM lineas_investigacion WHERE estado = 0 AND nombre = ?) AS desactivado",
            'ss',
            [$nombre, $nombre],
            false
        );

        return [
            'activo'      => (int)($fila['activo']      ?? 0),
            'desactivado' => (int)($fila['desactivado'] ?? 0),
        ];
    }

    /**
     * @return array{activo: int, desactivado: int}
     */
    public function verificarLineaOtroId(int $id_linea, string $nombre): array
    {
        $fila = $this->ejecutar(
            "SELECT
                EXISTS(
                    SELECT 1 FROM lineas_investigacion
                    WHERE estado = 1 AND nombre = ? AND id_linea != ?
                ) AS activo,
                EXISTS(
                    SELECT 1 FROM lineas_investigacion
                    WHERE estado = 0 AND nombre = ? AND id_linea != ?
                ) AS desactivado",
            'sisi',
            [$nombre, $id_linea, $nombre, $id_linea],
            false
        );

        return [
            'activo'      => (int)($fila['activo']      ?? 0),
            'desactivado' => (int)($fila['desactivado'] ?? 0),
        ];
    }


    // 
    // CONCURRENCIA
    // 

    public function bloquearTabla(): void
    {
        $this->ejecutar('SELECT id_linea FROM lineas_investigacion WHERE estado = 1 FOR UPDATE');
    }


    // 
    // HELPER PRIVADO: WHERE
    // 

    private function construirWhere(array &$params, string &$types, ?string $buscar, int $filtro): string
    {
        $where = [];

        if ($filtro === 0) {
            $where[] = 'estado = 0';
        } elseif ($filtro === 1) {
            $where[] = 'estado = 1';
        } else {
            $where[] = 'estado IN (0, 1)';
        }

        if (!empty($buscar)) {
            $where[]  = '(nombre LIKE ? OR descripcion LIKE ? OR fecha_creacion LIKE ?)';
            $params[] = "%$buscar%";
            $params[] = "%$buscar%";
            $params[] = "%$buscar%";
            $types   .= 'sss';
        }

        return ' WHERE ' . implode(' AND ', $where);
    }
}
