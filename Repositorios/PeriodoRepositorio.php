<?php
// Repositorios/PeriodoRepositorio.php

require_once __DIR__ . '/../Modelos/BaseModelo.php';

/**
 * PeriodoRepositorio
 *
 * Responsabilidad exclusiva: ejecutar consultas SQL sobre la tabla `periodos`.
 * No contiene lógica de negocio.
 */
class PeriodoRepositorio extends BaseModelo
{
    public function __construct(mysqli $conn)
    {
        parent::__construct($conn);
    }


    // 
    // CONTEO PARA PAGINACIÓN
    // 

    public function contarPeriodos(?string $buscar, int $filtro): int
    {
        [$where, $params, $types] = $this->construirWhere($buscar, $filtro);

        $sql = "SELECT COUNT(*) AS total FROM periodos $where";

        return (int)($this->ejecutar($sql, $types, $params, false)['total'] ?? 0);
    }


    // 
    // LISTADO CON FILTROS Y PAGINACIÓN
    // 

    public function listarPeriodos(?string $buscar, int $filtro, int $desde, int $por_pagina): array
    {
        [$where, $params, $types] = $this->construirWhere($buscar, $filtro);

        $sql = "SELECT
                    id_periodos,
                    periodo,
                    fecha_inicio            AS inicio,
                    fecha_final             AS final,
                    fecha_inicio_proyectos,
                    fecha_fin_proyectos,
                    fecha_inicio_solicitud,
                    fecha_fin_solicitud,
                    fecha_creacion          AS crear,
                    fecha_modificacion,
                    estado,
                    CASE
                        WHEN estado = 0 THEN 'Desactivado'
                        WHEN CURDATE() BETWEEN fecha_inicio AND fecha_final THEN 'Activo'
                        WHEN CURDATE() > fecha_final THEN 'Terminado'
                        ELSE 'Desconocido'
                    END AS estados,
                    CASE
                        WHEN estado = 0 AND fecha_final >= CURDATE() THEN 1
                        ELSE 0
                    END AS puede_reactivar
                FROM periodos
                $where
                ORDER BY id_periodos DESC
                LIMIT ?, ?";

        $params[] = $desde;
        $params[] = $por_pagina;
        $types   .= 'ii';

        return $this->ejecutar($sql, $types, $params);
    }


    // 
    // DETALLE / EDICIÓN
    // 

    public function buscarParaEditar(int $id_periodos): ?array
    {
        $fila = $this->ejecutar(
            "SELECT
                id_periodos,
                periodo                 AS nombre,
                fecha_inicio            AS inicio,
                fecha_final             AS fin,
                fecha_inicio_proyectos,
                fecha_fin_proyectos,
                fecha_inicio_solicitud,
                fecha_fin_solicitud,
                CASE
                    WHEN CURDATE() BETWEEN fecha_inicio AND fecha_final THEN 'Activo'
                    WHEN CURDATE() > fecha_final THEN 'Terminado'
                    ELSE 'Desconocido'
                END AS estado
             FROM periodos
             WHERE id_periodos = ?",
            'i',
            [$id_periodos],
            false
        );

        return $fila ?: null;
    }

    public function buscarDetalle(int $id_periodos): ?array
    {
        $fila = $this->ejecutar(
            "SELECT
                id_periodos,
                periodo,
                fecha_inicio,
                fecha_final,
                fecha_inicio_proyectos,
                fecha_fin_proyectos,
                fecha_inicio_solicitud,
                fecha_fin_solicitud,
                fecha_creacion,
                fecha_modificacion,
                CASE
                    WHEN CURDATE() BETWEEN fecha_inicio AND fecha_final THEN 'Activo'
                    WHEN CURDATE() > fecha_final THEN 'Terminado'
                    ELSE 'Desconocido'
                END AS estado
             FROM periodos
             WHERE id_periodos = ?",
            'i',
            [$id_periodos],
            false
        );

        return $fila ?: null;
    }

    public function buscarPorId(int $id, bool $forUpdate = false): ?array
    {
        $sql = 'SELECT estado FROM periodos WHERE id_periodos = ?';
        if ($forUpdate) {
            $sql .= ' FOR UPDATE';
        }

        return $this->ejecutar($sql, 'i', [$id], false) ?: null;
    }

    public function buscarDatosPorId(int $id): ?array
    {
        $fila = $this->ejecutar(
            'SELECT periodo, fecha_inicio, fecha_final FROM periodos WHERE id_periodos = ?',
            'i',
            [$id],
            false
        );

        return $fila ?: null;
    }

    public function buscarPorNombre(string $nombre): ?array
    {
        $fila = $this->ejecutar(
            'SELECT id_periodos FROM periodos WHERE periodo = ? LIMIT 1',
            's',
            [$nombre],
            false
        );

        return $fila ?: null;
    }


    // 
    // CREAR
    // 

    public function insertarPeriodo(
        string  $periodo,
        string  $fecha_inicio,
        string  $fecha_final,
        ?string $fecha_inicio_proyectos,
        ?string $fecha_fin_proyectos,
        ?string $fecha_inicio_solicitud,
        ?string $fecha_fin_solicitud
    ): int {
        $this->ejecutar(
            'INSERT INTO periodos
                (periodo, fecha_inicio, fecha_final,
                 fecha_inicio_proyectos, fecha_fin_proyectos,
                 fecha_inicio_solicitud, fecha_fin_solicitud,
                 estado, fecha_creacion)
             VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW())',
            'sssssss',
            [
                $periodo, $fecha_inicio, $fecha_final,
                $fecha_inicio_proyectos, $fecha_fin_proyectos,
                $fecha_inicio_solicitud, $fecha_fin_solicitud,
            ]
        );

        return (int)$this->conn->insert_id;
    }


    // 
    // ACTUALIZAR
    // 

    public function actualizarFechasSubperiodos(
        int     $id_periodos,
        ?string $fecha_inicio_proyectos,
        ?string $fecha_fin_proyectos,
        ?string $fecha_inicio_solicitud,
        ?string $fecha_fin_solicitud
    ): int {
        $this->ejecutar(
            'UPDATE periodos
             SET fecha_inicio_proyectos  = ?,
                 fecha_fin_proyectos     = ?,
                 fecha_inicio_solicitud  = ?,
                 fecha_fin_solicitud     = ?,
                 fecha_modificacion      = NOW()
             WHERE id_periodos = ?
               AND estado <> 0',
            'ssssi',
            [
                $fecha_inicio_proyectos,
                $fecha_fin_proyectos,
                $fecha_inicio_solicitud,
                $fecha_fin_solicitud,
                $id_periodos,
            ]
        );

        return $this->conn->affected_rows;
    }

    public function reactivarPeriodo(int $id): int
    {
        $this->ejecutar(
            'UPDATE periodos
             SET estado = 1, fecha_modificacion = NOW()
             WHERE id_periodos = ? AND estado = 0',
            'i',
            [$id]
        );

        return $this->conn->affected_rows;
    }


    // 
    // SOFT DELETE / AUXILIARES
    // 

    public function desactivarPeriodo(int $id_periodo): int
    {
        $this->ejecutar(
            'UPDATE periodos
             SET estado = 0, fecha_modificacion = NOW()
             WHERE id_periodos = ? AND estado <> 0',
            'i',
            [$id_periodo]
        );

        return $this->conn->affected_rows;
    }

    public function desactivarTodosActivos(): void
    {
        $this->ejecutar(
            'UPDATE periodos SET estado = 0, fecha_modificacion = NOW() WHERE estado = 1'
        );
    }

    public function bloquearTabla(): void
    {
        $this->ejecutar('SELECT id_periodos FROM periodos WHERE estado = 1 FOR UPDATE');
    }


    // 
    // VERIFICACIÓN DE DUPLICIDAD
    // 

    /**
     * @return array{activo: int, desactivado: int, desactivado_pasado: int}
     */
    public function verificarPeriodo(string $nombre, string $fecha_inicio, string $fecha_fin): array
    {
        $fila = $this->ejecutar(
            "SELECT
                EXISTS(
                    SELECT 1 FROM periodos
                    WHERE estado = 1
                      AND (? <= fecha_final AND ? >= fecha_inicio)
                ) AS activo_fecha,
                EXISTS(
                    SELECT 1 FROM periodos
                    WHERE estado = 1 AND periodo = ?
                ) AS activo_nombre,
                EXISTS(
                    SELECT 1 FROM periodos
                    WHERE estado = 0
                      AND (? <= fecha_final AND ? >= fecha_inicio)
                      AND fecha_final >= CURDATE()
                ) AS desactivado_vigente_fecha,
                EXISTS(
                    SELECT 1 FROM periodos
                    WHERE estado = 0 AND periodo = ?
                      AND fecha_final >= CURDATE()
                ) AS desactivado_vigente_nombre,
                EXISTS(
                    SELECT 1 FROM periodos
                    WHERE estado = 0
                      AND (? <= fecha_final AND ? >= fecha_inicio)
                      AND fecha_final < CURDATE()
                ) AS desactivado_pasado_fecha,
                EXISTS(
                    SELECT 1 FROM periodos
                    WHERE estado = 0 AND periodo = ?
                      AND fecha_final < CURDATE()
                ) AS desactivado_pasado_nombre",
            'sssssssss',
            [
                $fecha_inicio, $fecha_fin,
                $nombre,
                $fecha_inicio, $fecha_fin,
                $nombre,
                $fecha_inicio, $fecha_fin,
                $nombre,
            ],
            false
        );

        return [
            'activo'             => (int)(($fila['activo_fecha']              ?? 0) || ($fila['activo_nombre']              ?? 0)),
            'desactivado'        => (int)(($fila['desactivado_vigente_fecha']  ?? 0) || ($fila['desactivado_vigente_nombre']  ?? 0)),
            'desactivado_pasado' => (int)(($fila['desactivado_pasado_fecha']   ?? 0) || ($fila['desactivado_pasado_nombre']   ?? 0)),
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

        if ($filtro === 3) {
            $conditions[] = 'estado = 0';
        } else {
            $conditions[] = 'estado = 1';
            if ($filtro === 0) {
                $conditions[] = 'CURDATE() > fecha_final';
            } elseif ($filtro === 1) {
                $conditions[] = 'CURDATE() BETWEEN fecha_inicio AND fecha_final';
            }
        }

        if (!empty($buscar)) {
            $conditions[] = '(fecha_inicio LIKE ? OR fecha_final LIKE ? OR periodo LIKE ?)';
            $params[]     = "%$buscar%";
            $params[]     = "%$buscar%";
            $params[]     = "%$buscar%";
            $types       .= 'sss';
        }

        $where = 'WHERE ' . implode(' AND ', $conditions);

        return [$where, $params, $types];
    }
}