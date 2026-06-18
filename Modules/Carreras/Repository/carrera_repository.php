<?php
// Repositorios/CarreraRepositorio.php

require_once $_SERVER['DOCUMENT_ROOT'] . '/public/incluido/BaseModelo.php';

/**
 * CarreraRepositorio
 *
 * Responsabilidad exclusiva: ejecutar consultas SQL sobre la tabla `carreras`.
 * No contiene lógica de negocio.
 */
class CarreraRepositorio extends BaseModelo
{
    public function __construct(mysqli $conn)
    {
        parent::__construct($conn);
    }


    // 
    // CONTEO PARA PAGINACIÓN
    // 

    public function contarCarreras(?string $buscar, int $filtro): int
    {
        [$where, $params, $types] = $this->construirWhere($buscar, $filtro);

        $sql = "SELECT COUNT(*) AS total FROM carreras $where";

        return (int)($this->ejecutar($sql, $types, $params, false)['total'] ?? 0);
    }


    // 
    // LISTADO CON FILTROS Y PAGINACIÓN
    // 

    public function listarCarreras(?string $buscar, int $filtro, int $desde, int $por_pagina): array
    {
        [$where, $params, $types] = $this->construirWhere($buscar, $filtro);

        $sql = "SELECT
                    id_carrera,
                    nombre_carrera,
                    fecha_creacion AS crear,
                    CASE
                        WHEN estado = 1 THEN 'Activo'
                        WHEN estado = 0 THEN 'Desactivado'
                        ELSE 'Desconocido'
                    END AS estados
                FROM carreras
                $where
                ORDER BY id_carrera DESC
                LIMIT ?, ?";

        $params[] = $desde;
        $params[] = $por_pagina;
        $types   .= 'ii';

        return $this->ejecutar($sql, $types, $params);
    }


    // 
    // DETALLE DE UNA CARRERA
    // 

    public function buscarParaEditar(int $id_carrera): ?array
    {
        $fila = $this->ejecutar(
            "SELECT
                id_carrera,
                nombre_carrera,
                CASE
                    WHEN estado = 1 THEN 'Activo'
                    WHEN estado = 0 THEN 'Desactivado'
                    ELSE 'Desconocido'
                END AS estado
             FROM carreras
             WHERE id_carrera = ?",
            'i',
            [$id_carrera],
            false
        );

        return $fila ?: null;
    }

    public function buscarDetalle(int $id_carrera): ?array
    {
        $fila = $this->ejecutar(
            "SELECT
                id_carrera,
                nombre_carrera,
                fecha_creacion,
                fecha_modificacion,
                CASE
                    WHEN estado = 1 THEN 'Activo'
                    WHEN estado = 0 THEN 'Desactivado'
                    ELSE 'Desconocido'
                END AS estado
             FROM carreras
             WHERE id_carrera = ?",
            'i',
            [$id_carrera],
            false
        );

        return $fila ?: null;
    }

    public function buscarPorId(int $id_carrera, bool $forUpdate = false): ?array
    {
        $sql = 'SELECT estado FROM carreras WHERE id_carrera = ?';
        if ($forUpdate) {
            $sql .= ' FOR UPDATE';
        }

        return $this->ejecutar($sql, 'i', [$id_carrera], false) ?: null;
    }

    public function buscarNombrePorId(int $id_carrera): ?array
    {
        $fila = $this->ejecutar(
            'SELECT nombre_carrera FROM carreras WHERE id_carrera = ?',
            'i',
            [$id_carrera],
            false
        );

        return $fila ?: null;
    }


    // 
    // CREAR
    // 

    public function insertarCarrera(string $nombre_carrera): int
    {
        $this->ejecutar(
            'INSERT INTO carreras (nombre_carrera, estado, fecha_creacion) VALUES (?, 1, NOW())',
            's',
            [$nombre_carrera]
        );

        return (int)$this->conn->insert_id;
    }


    // 
    // ACTUALIZAR
    // 

    public function actualizarCarrera(string $nombre_carrera, int $id_carrera): void
    {
        $this->ejecutar(
            'UPDATE carreras SET nombre_carrera = ?, fecha_modificacion = NOW() WHERE id_carrera = ?',
            'si',
            [$nombre_carrera, $id_carrera]
        );
    }

    public function reactivarCarrera(int $id_carrera): int
    {
        $this->ejecutar(
            'UPDATE carreras
             SET estado = 1, fecha_modificacion = NOW()
             WHERE id_carrera = ? AND estado = 0',
            'i',
            [$id_carrera]
        );

        return $this->conn->affected_rows;
    }


    // 
    // SOFT DELETE
    // 

    public function desactivarCarrera(int $id_carrera): int
    {
        $this->ejecutar(
            'UPDATE carreras
             SET estado = 0, fecha_modificacion = NOW()
             WHERE id_carrera = ? AND estado <> 0',
            'i',
            [$id_carrera]
        );

        return $this->conn->affected_rows;
    }


    // 
    // BLOQUEO OPTIMISTA
    // 

    public function bloquearTabla(): void
    {
        $this->ejecutar('SELECT id_carrera FROM carreras WHERE estado = 1 FOR UPDATE');
    }


    // 
    // VERIFICACIÓN DE DUPLICIDAD
    // 

    /**
     * @return array{activo: int, desactivado: int}
     */
    public function verificarCarrera(string $nombre_carrera): array
    {
        $fila = $this->ejecutar(
            "SELECT
                EXISTS(SELECT 1 FROM carreras WHERE estado = 1 AND nombre_carrera = ?) AS activo,
                EXISTS(SELECT 1 FROM carreras WHERE estado = 0 AND nombre_carrera = ?) AS desactivado",
            'ss',
            [$nombre_carrera, $nombre_carrera],
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
    public function verificarCarreraOtroId(int $id_carrera, string $nombre_carrera): array
    {
        $fila = $this->ejecutar(
            "SELECT
                EXISTS(
                    SELECT 1 FROM carreras
                    WHERE estado = 1 AND nombre_carrera = ? AND id_carrera != ?
                ) AS activo,
                EXISTS(
                    SELECT 1 FROM carreras
                    WHERE estado = 0 AND nombre_carrera = ? AND id_carrera != ?
                ) AS desactivado",
            'sisi',
            [$nombre_carrera, $id_carrera, $nombre_carrera, $id_carrera],
            false
        );

        return [
            'activo'      => (int)($fila['activo']      ?? 0),
            'desactivado' => (int)($fila['desactivado'] ?? 0),
        ];
    }


    // 
    // HELPER PRIVADO: WHERE
    // 

    private function construirWhere(?string $buscar, int $filtro): array
    {
        $conditions = [];
        $params     = [];
        $types      = '';

        if ($filtro === 0 || $filtro === 1) {
            $conditions[] = 'estado = ?';
            $params[]     = $filtro;
            $types       .= 'i';
        } else {
            $conditions[] = 'estado IN (0, 1)';
        }

        if (!empty($buscar)) {
            $conditions[] = '(nombre_carrera LIKE ? OR fecha_creacion LIKE ?)';
            $params[]     = "%$buscar%";
            $params[]     = "%$buscar%";
            $types       .= 'ss';
        }

        $where = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

        return [$where, $params, $types];
    }
}
