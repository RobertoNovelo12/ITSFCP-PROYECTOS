<?php
// Repositorios/GradoAcademicoRepositorio.php

require_once __DIR__ . '/../Modelos/BaseModelo.php';

/**
 * GradoAcademicoRepositorio
 *
 * Responsabilidad exclusiva: ejecutar consultas SQL sobre la tabla `grados_academicos`.
 * No contiene lógica de negocio.
 */
class GradoAcademicoRepositorio extends BaseModelo
{
    public function __construct(mysqli $conn)
    {
        parent::__construct($conn);
    }


    // 
    // FILTROS / CONTEOS
    // 

    public function obtenerDatosFiltro(): array
    {
        return $this->ejecutar(
            "SELECT
                COUNT(*) AS Total,
                COALESCE(SUM(CASE WHEN estado = 1 THEN 1 ELSE 0 END), 0) AS Activo,
                COALESCE(SUM(CASE WHEN estado = 0 THEN 1 ELSE 0 END), 0) AS Desactivado
             FROM grados_academicos"
        );
    }

    public function contarGrados(?string $buscar, int $filtro): int
    {
        $params = [];
        $types  = '';
        $sql    = 'SELECT COUNT(*) AS total FROM grados_academicos';
        $sql   .= $this->construirWhere($params, $types, $buscar, $filtro);

        return (int)($this->ejecutar($sql, $types, $params, false)['total'] ?? 0);
    }


    // 
    // LISTADO CON FILTROS Y PAGINACIÓN
    // 

    public function listarGrados(?string $buscar, int $filtro, int $desde, int $por_pagina): array
    {
        $params = [];
        $types  = '';

        $sql = "SELECT
                    id_grado,
                    nombre,
                    fecha_creacion AS crear,
                    CASE
                        WHEN estado = 1 THEN 'Activo'
                        WHEN estado = 0 THEN 'Desactivado'
                        ELSE 'Desconocido'
                    END AS estados
                FROM grados_academicos";

        $sql     .= $this->construirWhere($params, $types, $buscar, $filtro);
        $sql     .= ' ORDER BY id_grado ASC LIMIT ?, ?';
        $params[] = $desde;
        $params[] = $por_pagina;
        $types   .= 'ii';

        return $this->ejecutar($sql, $types, $params);
    }


    // 
    // DETALLE / EDICIÓN
    // 

    public function buscarParaEditar(int $id_grado): ?array
    {
        $fila = $this->ejecutar(
            "SELECT
                id_grado, nombre,
                CASE
                    WHEN estado = 1 THEN 'Activo'
                    WHEN estado = 0 THEN 'Desactivado'
                    ELSE 'Desconocido'
                END AS estado
             FROM grados_academicos
             WHERE id_grado = ?",
            'i',
            [$id_grado],
            false
        );

        return $fila ?: null;
    }

    public function buscarDetalle(int $id_grado): ?array
    {
        $fila = $this->ejecutar(
            "SELECT
                id_grado, nombre, fecha_creacion, fecha_modificacion,
                CASE
                    WHEN estado = 1 THEN 'Activo'
                    WHEN estado = 0 THEN 'Desactivado'
                    ELSE 'Desconocido'
                END AS estado
             FROM grados_academicos
             WHERE id_grado = ?",
            'i',
            [$id_grado],
            false
        );

        return $fila ?: null;
    }

    public function buscarNombrePorId(int $id_grado): ?array
    {
        $fila = $this->ejecutar(
            'SELECT nombre FROM grados_academicos WHERE id_grado = ?',
            'i',
            [$id_grado],
            false
        );

        return $fila ?: null;
    }

    public function buscarPorId(int $id_grado, bool $forUpdate = false): ?array
    {
        $sql = 'SELECT estado FROM grados_academicos WHERE id_grado = ?';
        if ($forUpdate) {
            $sql .= ' FOR UPDATE';
        }

        return $this->ejecutar($sql, 'i', [$id_grado], false) ?: null;
    }


    // 
    // CREAR
    // 

    public function insertarGrado(string $nombre): int
    {
        $this->ejecutar(
            'INSERT INTO grados_academicos (nombre, estado, fecha_creacion) VALUES (?, 1, NOW())',
            's',
            [$nombre]
        );

        return (int)$this->conn->insert_id;
    }


    // 
    // ACTUALIZAR
    // 

    public function actualizarGrado(string $nombre, int $id_grado): void
    {
        $this->ejecutar(
            'UPDATE grados_academicos SET nombre = ?, fecha_modificacion = NOW() WHERE id_grado = ?',
            'si',
            [$nombre, $id_grado]
        );
    }

    public function reactivarGrado(int $id_grado): int
    {
        $this->ejecutar(
            'UPDATE grados_academicos
             SET estado = 1, fecha_modificacion = NOW()
             WHERE id_grado = ? AND estado = 0',
            'i',
            [$id_grado]
        );

        return $this->conn->affected_rows;
    }


    // 
    // SOFT DELETE
    // 

    public function desactivarGrado(int $id_grado): int
    {
        $this->ejecutar(
            'UPDATE grados_academicos
             SET estado = 0, fecha_modificacion = NOW()
             WHERE id_grado = ? AND estado <> 0',
            'i',
            [$id_grado]
        );

        return $this->conn->affected_rows;
    }


    // 
    // VERIFICACIÓN DE DUPLICIDAD
    // 

    /**
     * @return array{activo: int, desactivado: int}
     */
    public function verificarGrado(string $nombre): array
    {
        $fila = $this->ejecutar(
            "SELECT
                EXISTS(SELECT 1 FROM grados_academicos WHERE estado = 1 AND nombre = ?) AS activo,
                EXISTS(SELECT 1 FROM grados_academicos WHERE estado = 0 AND nombre = ?) AS desactivado",
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
    public function verificarGradoOtroId(int $id_grado, string $nombre): array
    {
        $fila = $this->ejecutar(
            "SELECT
                EXISTS(SELECT 1 FROM grados_academicos WHERE estado = 1 AND nombre = ? AND id_grado != ?) AS activo,
                EXISTS(SELECT 1 FROM grados_academicos WHERE estado = 0 AND nombre = ? AND id_grado != ?) AS desactivado",
            'sisi',
            [$nombre, $id_grado, $nombre, $id_grado],
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
        $this->ejecutar('SELECT id_grado FROM grados_academicos WHERE estado = 1 FOR UPDATE');
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
            $where[] = 'estado IN (0,1)';
        }

        if (!empty($buscar)) {
            $where[]  = '(nombre LIKE ? OR fecha_creacion LIKE ?)';
            $params[] = "%$buscar%";
            $params[] = "%$buscar%";
            $types   .= 'ss';
        }

        return ' WHERE ' . implode(' AND ', $where);
    }
}
