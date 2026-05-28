<?php
// Modelos/lineaInvestigacion.php

require_once __DIR__ . '/../publico/config/conexion.php';
require_once __DIR__ . '/BaseModelo.php';

class Linea extends BaseModelo
{

    // ─
    // FILTROS / CONTEOS
    // ─

    public function obtenerDatosFiltro(string $rol): array
    {
        if ($rol !== 'supervisor') return [];

        return $this->ejecutar(
            "SELECT
                COUNT(*) AS Total,
                COALESCE(SUM(CASE WHEN estado = 1 THEN 1 ELSE 0 END), 0) AS Activo,
                COALESCE(SUM(CASE WHEN estado = 0 THEN 1 ELSE 0 END), 0) AS Desactivado
             FROM lineas_investigacion"
        );
    }

    /** Construye la cláusula WHERE dinámica reutilizable. */
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

    // ─
    // TABLA PRINCIPAL CON PAGINACIÓN
    // ─

    public function obtenerTablaFiltro(?string $buscar, int $filtro): array
    {
        $por_pagina = 6;
        $pagina     = max(1, (int)($_GET['pagina'] ?? 1));
        $desde      = ($pagina - 1) * $por_pagina;

        $total         = $this->obtenerCantidadLinea($buscar, $filtro);
        $total_paginas = max(1, (int)ceil($total / $por_pagina));

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

        $data = $this->ejecutar($sql, $types, $params);

        return [
            'linea'      => $data,
            'paginacion' => [
                'total'         => $total,
                'por_pagina'    => $por_pagina,
                'pagina'        => $pagina,
                'total_paginas' => $total_paginas,
            ],
        ];
    }

    public function obtenerCantidadLinea(?string $buscar = null, int $filtro = 2): int
    {
        $params = [];
        $types  = '';
        $sql    = 'SELECT COUNT(*) AS total FROM lineas_investigacion';
        $sql   .= $this->construirWhere($params, $types, $buscar, $filtro);

        $resultado = $this->ejecutar($sql, $types, $params, false);
        return (int)($resultado['total'] ?? 0);
    }

    // ─
    // OBTENER REGISTRO
    // ─

    public function obtenerEditar(int $id_linea): array
    {
        $resultado = $this->ejecutar(
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

        if (!$resultado) throw new Exception('Línea de investigación no encontrada.');
        return $resultado;
    }

    public function obtenerDetalles(int $id_linea): array
    {
        $resultado = $this->ejecutar(
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

        if (!$resultado) throw new Exception('Línea de investigación no encontrada.');
        return $resultado;
    }

    public function obtenerPorId(int $id_linea, bool $forUpdate = false): ?array
    {
        $sql = 'SELECT estado FROM lineas_investigacion WHERE id_linea = ?';
        if ($forUpdate) $sql .= ' FOR UPDATE';

        $resultado = $this->ejecutar($sql, 'i', [$id_linea], false);
        return $resultado ?: null;
    }

    public function obtenerPorNombre(string $nombre): ?array
    {
        $resultado = $this->ejecutar(
            'SELECT id_linea FROM lineas_investigacion WHERE nombre = ? LIMIT 1',
            's',
            [$nombre],
            false
        );
        return $resultado ?: null;
    }

    // ─
    // CRUD
    // ─

    /**
     * Registra una nueva línea de investigación (activo = 1).
     * DEBE ejecutarse dentro de una transacción.
     */
    public function registrarLinea(string $nombre, string $descripcion): int
    {
        $validacion = $this->verificarLinea($nombre);
        if ($validacion['activo']) {
            throw new Exception('Ya existe una línea de investigación activa con ese nombre.');
        }

        $this->ejecutar(
            "INSERT INTO lineas_investigacion (nombre, descripcion, estado, fecha_creacion)
             VALUES (?, ?, 1, NOW())",
            'ss',
            [$nombre, $descripcion]
        );
        return (int)$this->conn->insert_id;
    }

    /**
     * Edita una línea de investigación existente.
     * DEBE ejecutarse dentro de una transacción.
     */
    public function editarLinea(string $nombre, string $descripcion, int $id_linea): int
    {
        $this->ejecutar(
            "UPDATE lineas_investigacion
             SET nombre = ?, descripcion = ?, fecha_modificacion = NOW()
             WHERE id_linea = ?",
            'ssi',
            [$nombre, $descripcion, $id_linea]
        );
        return $id_linea;
    }

    /**
     * Reactiva una línea de investigación desactivada.
     * DEBE ejecutarse dentro de una transacción.
     */
    public function reactivar(int $id_linea): void
    {
        $registro = $this->obtenerPorId($id_linea, true);
        if (!$registro) throw new Exception('Línea de investigación no encontrada.');

        $datos = $this->ejecutar(
            'SELECT nombre FROM lineas_investigacion WHERE id_linea = ?',
            'i',
            [$id_linea],
            false
        );
        if (!$datos) throw new Exception('No se pudieron obtener datos de la línea de investigación.');

        $validacion = $this->verificarLinea($datos['nombre']);
        if ($validacion['activo']) {
            throw new Exception('Ya existe una línea de investigación activa con el mismo nombre.');
        }

        $this->ejecutar(
            "UPDATE lineas_investigacion
             SET estado = 1, fecha_modificacion = NOW()
             WHERE id_linea = ? AND estado = 0",
            'i',
            [$id_linea]
        );

        if ($this->conn->affected_rows === 0) {
            throw new Exception('La línea ya estaba activa o no se pudo actualizar.');
        }
    }

    /**
     * Desactivación lógica (soft delete).
     * Devuelve las filas afectadas.
     */
    public function eliminar_linea(int $id_linea): int
    {
        $this->ejecutar(
            "UPDATE lineas_investigacion
             SET estado = 0, fecha_modificacion = NOW()
             WHERE id_linea = ? AND estado <> 0",
            'i',
            [$id_linea]
        );
        return $this->conn->affected_rows;
    }

    // ─
    // VERIFICACIONES DE DUPLICIDAD
    // ─

    public function verificarLinea(string $nombre): array
    {
        $resultado = $this->ejecutar(
            "SELECT
                EXISTS(SELECT 1 FROM lineas_investigacion WHERE estado = 1 AND nombre = ?) AS activo,
                EXISTS(SELECT 1 FROM lineas_investigacion WHERE estado = 0 AND nombre = ?) AS desactivado",
            'ss',
            [$nombre, $nombre],
            false
        );
        return [
            'activo'      => (int)($resultado['activo']      ?? 0),
            'desactivado' => (int)($resultado['desactivado'] ?? 0),
        ];
    }

    /**
     * Verifica si existe otro registro con el mismo nombre, excluyendo el ID actual.
     */
    public function obtenerPorIdDiferente(int $id_linea, string $nombre): array
    {
        $resultado = $this->ejecutar(
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
            'activo'      => (int)($resultado['activo']      ?? 0),
            'desactivado' => (int)($resultado['desactivado'] ?? 0),
        ];
    }

    // ─
    // CONCURRENCIA
    // ─

    /**
     * Bloquea los registros activos.
     * DEBE ejecutarse dentro de una transacción (InnoDB).
     */
    public function bloquear_tabla(): void
    {
        $this->ejecutar(
            'SELECT id_linea FROM lineas_investigacion WHERE estado = 1 FOR UPDATE'
        );
    }
}
