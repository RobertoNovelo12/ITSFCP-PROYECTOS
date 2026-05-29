<?php
// Modelos/periodo.php

require_once __DIR__ . '/../publico/config/conexion.php';
require_once __DIR__ . '/BaseModelo.php';

class Periodo extends BaseModelo
{
    public function __construct(mysqli $conn)
    {
        parent::__construct($conn);
    }


    /**
     * Construye WHERE dinámico.
     *
     * Filtros:
     *   2 = Total    → estado = 1 (activos + terminados)
     *   1 = Activo   → estado = 1 AND hoy BETWEEN fecha_inicio AND fecha_final
     *   0 = Terminado→ estado = 1 AND hoy > fecha_final
     *   3 = Desactivado → estado = 0
     *
     * Devuelve [string $where, array $params, string $types]
     */
    private function construirWhere(?string $buscar, int $filtro): array
    {
        $conditions = [];
        $params     = [];
        $types      = '';

        if ($filtro === 3) {
            $conditions[] = "estado = 0";
        } else {
            $conditions[] = "estado = 1";
            if ($filtro === 0) {
                $conditions[] = "CURDATE() > fecha_final";
            } elseif ($filtro === 1) {
                $conditions[] = "CURDATE() BETWEEN fecha_inicio AND fecha_final";
            }
            // filtro === 2 → Total (sin restricción de fecha)
        }

        if (!empty($buscar)) {
            $conditions[] = "(fecha_inicio LIKE ? OR fecha_final LIKE ? OR periodo LIKE ?)";
            $params[]     = "%$buscar%";
            $params[]     = "%$buscar%";
            $params[]     = "%$buscar%";
            $types       .= 'sss';
        }

        $where = 'WHERE ' . implode(' AND ', $conditions);

        return [$where, $params, $types];
    }


    // ─
    // TABLA PRINCIPAL CON PAGINACIÓN
    // ─

    public function obtenerPeriodoTablaFiltro(?string $buscar, int $filtro): array
    {
        $pagina        = max(1, (int)($_GET['pagina'] ?? 1));
        $por_pagina    = 6;
        $desde         = ($pagina - 1) * $por_pagina;
        $total         = $this->obtenerCantidadPeriodo($buscar, $filtro);
        $total_paginas = max(1, (int)ceil($total / $por_pagina));

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

        return [
            "periodo"    => $this->ejecutar($sql, $types, $params),
            "paginacion" => compact("total", "por_pagina", "pagina") + ["total_paginas" => $total_paginas],
        ];
    }

    public function obtenerCantidadPeriodo(?string $buscar = null, int $filtro = 2): int
    {
        [$where, $params, $types] = $this->construirWhere($buscar, $filtro);

        $sql = "SELECT COUNT(*) AS total FROM periodos $where";

        return (int)($this->ejecutar($sql, $types, $params, false)['total'] ?? 0);
    }


    // ─
    // EDITAR / DETALLES
    // ─

    public function obtenerPeriodoEditar(int $id_periodos): array
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
            "i",
            [$id_periodos],
            false
        );

        if (!$fila) {
            throw new Exception("Periodo no encontrado.");
        }

        return $fila;
    }

    public function obtenerPeriodoDetalles(int $id_periodos): array
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
            "i",
            [$id_periodos],
            false
        );

        if (!$fila) {
            throw new Exception("Periodo no encontrado.");
        }

        return $fila;
    }


    // ─
    // CREAR / REACTIVAR
    // ─

    /**
     * Registra un nuevo periodo.
     * Debe ejecutarse dentro de una transacción.
     *
     * @return int  ID insertado.
     * @throws Exception
     */
    public function registrarPeriodo(
        string  $periodo,
        string  $fecha_inicio,
        string  $fecha_final,
        ?string $fecha_inicio_proyectos  = null,
        ?string $fecha_fin_proyectos     = null,
        ?string $fecha_inicio_solicitud  = null,
        ?string $fecha_fin_solicitud     = null
    ): int {
        $validacion = $this->verificarPeriodo($periodo, $fecha_inicio, $fecha_final);

        if ($validacion['activo']) {
            throw new Exception("Conflicto: ya existe un periodo activo con ese nombre o fechas.");
        }

        $this->ejecutar(
            "INSERT INTO periodos
                (periodo, fecha_inicio, fecha_final,
                 fecha_inicio_proyectos, fecha_fin_proyectos,
                 fecha_inicio_solicitud, fecha_fin_solicitud,
                 estado, fecha_creacion)
             VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW())",
            "sssssss",
            [
                $periodo, $fecha_inicio, $fecha_final,
                $fecha_inicio_proyectos, $fecha_fin_proyectos,
                $fecha_inicio_solicitud, $fecha_fin_solicitud,
            ]
        );

        return (int)$this->conn->insert_id;
    }

    /**
     * Actualiza únicamente las fechas de proyectos e integración.
     * Debe ejecutarse dentro de una transacción.
     *
     * @return int  Filas afectadas.
     */
    public function actualizarFechasSubperiodos(
        int     $id_periodos,
        ?string $fecha_inicio_proyectos,
        ?string $fecha_fin_proyectos,
        ?string $fecha_inicio_solicitud,
        ?string $fecha_fin_solicitud
    ): int {
        $this->ejecutar(
            "UPDATE periodos
             SET fecha_inicio_proyectos  = ?,
                 fecha_fin_proyectos     = ?,
                 fecha_inicio_solicitud  = ?,
                 fecha_fin_solicitud     = ?,
                 fecha_modificacion      = NOW()
             WHERE id_periodos = ?
               AND estado <> 0",
            "ssssi",
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

    /**
     * Reactiva un periodo desactivado.
     * Debe ejecutarse dentro de una transacción.
     *
     * @throws Exception
     */
    public function reactivarPeriodo(int $id): void
    {
        $periodo = $this->obtenerPorId($id, true);

        if (!$periodo) {
            throw new Exception("Periodo no encontrado.");
        }

        $datos = $this->ejecutar(
            "SELECT periodo, fecha_inicio, fecha_final FROM periodos WHERE id_periodos = ?",
            "i",
            [$id],
            false
        );

        if (!$datos) {
            throw new Exception("No se pudieron obtener datos del periodo.");
        }

        $validacion = $this->verificarPeriodo(
            $datos['periodo'],
            $datos['fecha_inicio'],
            $datos['fecha_final']
        );

        if ($validacion['activo']) {
            throw new Exception("Conflicto: ya existe un periodo activo con mismo nombre o fechas.");
        }

        $this->ejecutar(
            "UPDATE periodos
             SET estado = 1, fecha_modificacion = NOW()
             WHERE id_periodos = ? AND estado = 0",
            "i",
            [$id]
        );

        if ($this->conn->affected_rows === 0) {
            throw new Exception("El periodo ya estaba activo o no se pudo actualizar.");
        }
    }


    // ─
    // AUXILIARES
    // ─

    public function obtenerPorNombre(string $nombre): ?array
    {
        $fila = $this->ejecutar(
            "SELECT id_periodos FROM periodos WHERE periodo = ? LIMIT 1",
            "s",
            [$nombre],
            false
        );

        return $fila ?: null;
    }

    public function bloquear_tabla(): void
    {
        $this->ejecutar(
            "SELECT id_periodos FROM periodos WHERE estado = 1 FOR UPDATE"
        );
    }

    public function eliminar_periodo(int $id_periodo): int
    {
        $this->ejecutar(
            "UPDATE periodos
             SET estado = 0, fecha_modificacion = NOW()
             WHERE id_periodos = ? AND estado <> 0",
            "i",
            [$id_periodo]
        );

        return $this->conn->affected_rows;
    }

    public function desactivarActivos(): void
    {
        $this->ejecutar(
            "UPDATE periodos SET estado = 0, fecha_modificacion = NOW() WHERE estado = 1"
        );
    }

    /**
     * Verifica duplicidad de periodos por solapamiento de fechas y nombre.
     *
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
            "sssssssss",
            [
                $fecha_inicio, $fecha_fin,    // activo_fecha
                $nombre,                       // activo_nombre
                $fecha_inicio, $fecha_fin,    // desactivado_vigente_fecha
                $nombre,                       // desactivado_vigente_nombre
                $fecha_inicio, $fecha_fin,    // desactivado_pasado_fecha
                $nombre,                       // desactivado_pasado_nombre
            ],
            false
        );

        return [
            'activo'             => (int)(($fila['activo_fecha']             ?? 0) || ($fila['activo_nombre']             ?? 0)),
            'desactivado'        => (int)(($fila['desactivado_vigente_fecha'] ?? 0) || ($fila['desactivado_vigente_nombre'] ?? 0)),
            'desactivado_pasado' => (int)(($fila['desactivado_pasado_fecha']  ?? 0) || ($fila['desactivado_pasado_nombre']  ?? 0)),
        ];
    }

    public function obtenerPorId(int $id, bool $forUpdate = false): ?array
    {
        $sql = "SELECT estado FROM periodos WHERE id_periodos = ?";
        if ($forUpdate) {
            $sql .= " FOR UPDATE";
        }

        $fila = $this->ejecutar($sql, "i", [$id], false);

        return $fila ?: null;
    }
}