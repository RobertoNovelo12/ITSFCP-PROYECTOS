<?php
// Repositorios/NivelSNIRepositorio.php

require_once __DIR__ . '/../Modelos/BaseModelo.php';

/**
 * NivelSNIRepositorio
 *
 * Responsabilidad exclusiva: ejecutar consultas SQL sobre la tabla `niveles_sni`.
 * No contiene lógica de negocio.
 */
class NivelSNIRepositorio extends BaseModelo
{
    public function __construct(mysqli $conn)
    {
        parent::__construct($conn);
    }


    // 
    // CONTEO PARA PAGINACIÓN
    // 

    public function contarNiveles(?string $buscar, int $filtro): int
    {
        $params = [];
        $types  = '';
        $sql    = 'SELECT COUNT(*) AS total FROM niveles_sni';
        $sql   .= $this->construirWhere($params, $types, $buscar, $filtro);

        return (int)($this->ejecutar($sql, $types, $params, false)['total'] ?? 0);
    }


    // 
    // LISTADO CON FILTROS Y PAGINACIÓN
    // 

    public function listarNiveles(?string $buscar, int $filtro, int $desde, int $por_pagina): array
    {
        $params = [];
        $types  = '';

        $sql = "SELECT
                    id_nivel,
                    nombre,
                    fecha_creacion AS crear,
                    CASE
                        WHEN estado = 1 THEN 'Activo'
                        WHEN estado = 0 THEN 'Desactivado'
                        ELSE 'Desconocido'
                    END AS estados
                FROM niveles_sni";

        $sql     .= $this->construirWhere($params, $types, $buscar, $filtro);
        $sql     .= ' ORDER BY id_nivel DESC LIMIT ?, ?';
        $params[] = $desde;
        $params[] = $por_pagina;
        $types   .= 'ii';

        return $this->ejecutar($sql, $types, $params);
    }


    // 
    // DETALLE / EDICIÓN
    // 

    public function buscarParaEditar(int $id_nivel): ?array
    {
        $fila = $this->ejecutar(
            "SELECT
                id_nivel,
                nombre,
                CASE
                    WHEN estado = 1 THEN 'Activo'
                    WHEN estado = 0 THEN 'Desactivado'
                    ELSE 'Desconocido'
                END AS estado
             FROM niveles_sni
             WHERE id_nivel = ?",
            'i',
            [$id_nivel],
            false
        );

        return $fila ?: null;
    }

    public function buscarDetalle(int $id_nivel): ?array
    {
        $fila = $this->ejecutar(
            "SELECT
                id_nivel,
                nombre,
                fecha_creacion,
                fecha_modificacion,
                CASE
                    WHEN estado = 1 THEN 'Activo'
                    WHEN estado = 0 THEN 'Desactivado'
                    ELSE 'Desconocido'
                END AS estado
             FROM niveles_sni
             WHERE id_nivel = ?",
            'i',
            [$id_nivel],
            false
        );

        return $fila ?: null;
    }

    public function buscarPorId(int $id_nivel, bool $forUpdate = false): ?array
    {
        $sql = 'SELECT estado FROM niveles_sni WHERE id_nivel = ?';
        if ($forUpdate) {
            $sql .= ' FOR UPDATE';
        }

        return $this->ejecutar($sql, 'i', [$id_nivel], false) ?: null;
    }

    public function buscarNombrePorId(int $id_nivel): ?array
    {
        $fila = $this->ejecutar(
            'SELECT nombre FROM niveles_sni WHERE id_nivel = ?',
            'i',
            [$id_nivel],
            false
        );

        return $fila ?: null;
    }


    // 
    // CREAR
    // 

    public function insertarNivel(string $nombre): int
    {
        $this->ejecutar(
            'INSERT INTO niveles_sni (nombre, estado, fecha_creacion) VALUES (?, 1, NOW())',
            's',
            [$nombre]
        );

        return (int)$this->conn->insert_id;
    }


    // 
    // ACTUALIZAR
    // 

    public function actualizarNivel(string $nombre, int $id_nivel): void
    {
        $this->ejecutar(
            'UPDATE niveles_sni SET nombre = ?, fecha_modificacion = NOW() WHERE id_nivel = ?',
            'si',
            [$nombre, $id_nivel]
        );
    }

    public function reactivarNivel(int $id_nivel): int
    {
        $this->ejecutar(
            'UPDATE niveles_sni
             SET estado = 1, fecha_modificacion = NOW()
             WHERE id_nivel = ? AND estado = 0',
            'i',
            [$id_nivel]
        );

        return $this->conn->affected_rows;
    }


    // 
    // SOFT DELETE
    // 

    public function desactivarNivel(int $id_nivel): int
    {
        $this->ejecutar(
            'UPDATE niveles_sni
             SET estado = 0, fecha_modificacion = NOW()
             WHERE id_nivel = ? AND estado <> 0',
            'i',
            [$id_nivel]
        );

        return $this->conn->affected_rows;
    }


    // 
    // VERIFICACIÓN DE DUPLICIDAD
    // 

    /**
     * @return array{activo: int, desactivado: int}
     */
    public function verificarNivel(string $nombre): array
    {
        $fila = $this->ejecutar(
            "SELECT
                EXISTS(SELECT 1 FROM niveles_sni WHERE estado = 1 AND nombre = ?) AS activo,
                EXISTS(SELECT 1 FROM niveles_sni WHERE estado = 0 AND nombre = ?) AS desactivado",
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
    public function verificarNivelOtroId(int $id_nivel, string $nombre): array
    {
        $fila = $this->ejecutar(
            "SELECT
                EXISTS(
                    SELECT 1 FROM niveles_sni
                    WHERE estado = 1 AND nombre = ? AND id_nivel != ?
                ) AS activo,
                EXISTS(
                    SELECT 1 FROM niveles_sni
                    WHERE estado = 0 AND nombre = ? AND id_nivel != ?
                ) AS desactivado",
            'sisi',
            [$nombre, $id_nivel, $nombre, $id_nivel],
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
        $this->ejecutar('SELECT id_nivel FROM niveles_sni WHERE estado = 1 FOR UPDATE');
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
            $where[]  = '(nombre LIKE ? OR fecha_creacion LIKE ?)';
            $params[] = "%$buscar%";
            $params[] = "%$buscar%";
            $types   .= 'ss';
        }

        return ' WHERE ' . implode(' AND ', $where);
    }
}