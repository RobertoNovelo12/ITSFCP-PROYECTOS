<?php
// Repositorios/DirectorRepositorio.php

require_once $_SERVER['DOCUMENT_ROOT'] . '/public/incluido/BaseModelo.php';

/**
 * DirectorRepositorio
 *
 * Responsabilidad exclusiva: ejecutar consultas SQL sobre la tabla `director`
 * y sus tablas relacionadas.
 * No contiene lógica de negocio.
 */
class DirectorRepositorio extends BaseModelo
{
    public function __construct(mysqli $conn)
    {
        parent::__construct($conn);
    }


    // 
    // CONTEO PARA PAGINACIÓN
    // 

    public function contarDirectores(?string $buscar, int $filtro): int
    {
        $params = [];
        $types  = '';
        $sql    = 'SELECT COUNT(*) AS total FROM director d';
        $sql   .= $this->construirWhere($params, $types, $buscar, $filtro);

        return (int)($this->ejecutar($sql, $types, $params, false)['total'] ?? 0);
    }


    // 
    // LISTADO CON FILTROS Y PAGINACIÓN
    // 

    public function listarDirectores(?string $buscar, int $filtro, int $desde, int $por_pagina): array
    {
        $params = [];
        $types  = '';

        $sql = "SELECT
                    d.id_director,
                    d.nombre,
                    d.apellido,
                    d.correo,
                    d.telefono,
                    g.nombre AS nombre_grado,
                    CASE
                        WHEN d.estado = 1 THEN 'Activo'
                        WHEN d.estado = 0 THEN 'Desactivado'
                        ELSE 'Desconocido'
                    END AS estados
                FROM director d
                INNER JOIN grados_academicos g ON d.id_grado = g.id_grado";

        $sql     .= $this->construirWhere($params, $types, $buscar, $filtro);
        $sql     .= ' ORDER BY d.id_director ASC LIMIT ?, ?';
        $params[] = $desde;
        $params[] = $por_pagina;
        $types   .= 'ii';

        return $this->ejecutar($sql, $types, $params);
    }


    // 
    // DETALLE / EDICIÓN
    // 

    public function buscarParaEditar(int $id_director): ?array
    {
        $fila = $this->ejecutar(
            "SELECT
                id_director, id_grado, nombre, apellido, correo, telefono,
                fecha_inicio AS inicio, fecha_final AS fin, motivo_fin,
                CASE
                    WHEN estado = 1 THEN 'Activo'
                    WHEN estado = 0 THEN 'Desactivado'
                    ELSE 'Desconocido'
                END AS estado
             FROM director
             WHERE id_director = ?",
            'i',
            [$id_director],
            false
        );

        return $fila ?: null;
    }

    public function buscarDetalle(int $id_director): ?array
    {
        $fila = $this->ejecutar(
            "SELECT
                d.id_director, d.nombre, d.apellido, d.correo, d.telefono,
                g.nombre AS nombre_grado,
                d.fecha_inicio AS inicio, d.fecha_final AS fin,
                d.motivo_fin, d.fecha_creacion, d.fecha_modificacion,
                CASE
                    WHEN d.estado = 1 THEN 'Activo'
                    WHEN d.estado = 0 THEN 'Desactivado'
                    ELSE 'Desconocido'
                END AS estado
             FROM director d
             INNER JOIN grados_academicos g ON d.id_grado = g.id_grado
             WHERE d.id_director = ?",
            'i',
            [$id_director],
            false
        );

        return $fila ?: null;
    }

    public function buscarCorreoPorId(int $id_director): ?array
    {
        $fila = $this->ejecutar(
            'SELECT correo FROM director WHERE id_director = ?',
            'i',
            [$id_director],
            false
        );

        return $fila ?: null;
    }

    public function buscarPorId(int $id_director, bool $forUpdate = false): ?array
    {
        $sql = 'SELECT estado FROM director WHERE id_director = ?';
        if ($forUpdate) {
            $sql .= ' FOR UPDATE';
        }

        return $this->ejecutar($sql, 'i', [$id_director], false) ?: null;
    }

    public function listarGradosActivos(): array
    {
        return $this->ejecutar(
            'SELECT id_grado, nombre FROM grados_academicos WHERE estado = 1 ORDER BY nombre ASC'
        );
    }


    // 
    // CREAR
    // 

    public function insertarDirector(
        int $id_grado,
        string $nombre,
        string $apellido,
        ?string $correo,
        ?string $telefono,
        ?string $fecha_inicio,
        ?string $fecha_final
    ): int {
        $this->ejecutar(
            'INSERT INTO director
                (id_grado, nombre, apellido, correo, telefono, estado, fecha_creacion, fecha_inicio, fecha_final)
             VALUES (?, ?, ?, ?, ?, 1, NOW(), ?, ?)',
            'issssss',
            [$id_grado, $nombre, $apellido, $correo, $telefono, $fecha_inicio, $fecha_final]
        );

        return (int)$this->conn->insert_id;
    }


    // 
    // ACTUALIZAR
    // 

    public function actualizarDirector(
        int $id_grado,
        string $nombre,
        string $apellido,
        ?string $correo,
        ?string $telefono,
        int $id_director,
        ?string $fecha_inicio,
        ?string $fecha_final,
        ?string $motivo_fin
    ): void {
        $this->ejecutar(
            'UPDATE director
             SET id_grado = ?, nombre = ?, apellido = ?, correo = ?, telefono = ?,
                 fecha_modificacion = NOW(), fecha_inicio = ?, fecha_final = ?, motivo_fin = ?
             WHERE id_director = ?',
            'isssssssi',
            [$id_grado, $nombre, $apellido, $correo, $telefono,
             $fecha_inicio, $fecha_final, $motivo_fin, $id_director]
        );
    }

    public function reactivarDirector(int $id_director): int
    {
        $this->ejecutar(
            'UPDATE director
             SET estado = 1, fecha_modificacion = NOW()
             WHERE id_director = ? AND estado = 0',
            'i',
            [$id_director]
        );

        return $this->conn->affected_rows;
    }


    // 
    // SOFT DELETE
    // 

    public function desactivarDirector(int $id_director): int
    {
        $this->ejecutar(
            'UPDATE director
             SET estado = 0, fecha_modificacion = NOW()
             WHERE id_director = ? AND estado <> 0',
            'i',
            [$id_director]
        );

        return $this->conn->affected_rows;
    }


    // 
    // VERIFICACIÓN DE DUPLICIDAD
    // 

    /**
     * @return array{activo: int, desactivado: int}
     */
    public function verificarDirector(?string $correo): array
    {
        if (empty($correo)) {
            return ['activo' => 0, 'desactivado' => 0];
        }

        $fila = $this->ejecutar(
            "SELECT
                EXISTS(SELECT 1 FROM director WHERE estado = 1 AND correo = ?) AS activo,
                EXISTS(SELECT 1 FROM director WHERE estado = 0 AND correo = ?) AS desactivado",
            'ss',
            [$correo, $correo],
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
    public function verificarDirectorOtroId(int $id_director, ?string $correo): array
    {
        if (empty($correo)) {
            return ['activo' => 0, 'desactivado' => 0];
        }

        $fila = $this->ejecutar(
            "SELECT
                EXISTS(SELECT 1 FROM director WHERE estado = 1 AND correo = ? AND id_director != ?) AS activo,
                EXISTS(SELECT 1 FROM director WHERE estado = 0 AND correo = ? AND id_director != ?) AS desactivado",
            'sisi',
            [$correo, $id_director, $correo, $id_director],
            false
        );

        return [
            'activo'      => (int)($fila['activo']      ?? 0),
            'desactivado' => (int)($fila['desactivado'] ?? 0),
        ];
    }


    // 
    // UTILIDADES
    // 

    public function desactivarDirectoresVencidos(): void
    {
        $this->ejecutar(
            'UPDATE director
             SET estado = 0
             WHERE estado = 1 AND fecha_final IS NOT NULL AND CURDATE() > fecha_final'
        );
    }

    public function bloquearTabla(): void
    {
        $this->ejecutar('SELECT id_director FROM director WHERE estado = 1 FOR UPDATE');
    }


    // 
    // HISTORIAL
    // 

    public function insertarHistorial(int $id_director, string $accion, string $descripcion): void
    {
        $this->ejecutar(
            'INSERT INTO historial_director (id_director, accion, descripcion, fecha) VALUES (?, ?, ?, NOW())',
            'iss',
            [$id_director, $accion, $descripcion]
        );
    }

    public function contarHistorial(int $id_director): int
    {
        $fila = $this->ejecutar(
            'SELECT COUNT(*) AS total FROM historial_director WHERE id_director = ?',
            'i',
            [$id_director],
            false
        );

        return (int)($fila['total'] ?? 0);
    }

    public function listarHistorial(int $id_director, int $desde, int $por_pagina): array
    {
        return $this->ejecutar(
            'SELECT h.accion AS tipo_evento, h.descripcion, h.fecha
             FROM historial_director h
             WHERE h.id_director = ?
             ORDER BY h.fecha DESC
             LIMIT ?, ?',
            'iii',
            [$id_director, $desde, $por_pagina]
        );
    }


    // 
    // HELPER PRIVADO: WHERE
    // 

    private function construirWhere(array &$params, string &$types, ?string $buscar, int $filtro): string
    {
        $where = [];

        if ($filtro === 0) {
            $where[] = 'd.estado = 0';
        } elseif ($filtro === 1) {
            $where[] = 'd.estado = 1';
        } else {
            $where[] = 'd.estado IN (0,1)';
        }

        if (!empty($buscar)) {
            $where[]  = '(d.nombre LIKE ? OR d.apellido LIKE ? OR d.correo LIKE ? OR d.fecha_creacion LIKE ?)';
            $params[] = "%$buscar%";
            $params[] = "%$buscar%";
            $params[] = "%$buscar%";
            $params[] = "%$buscar%";
            $types   .= 'ssss';
        }

        return ' WHERE ' . implode(' AND ', $where);
    }
}
