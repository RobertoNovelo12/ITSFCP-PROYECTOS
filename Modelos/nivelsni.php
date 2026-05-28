<?php
// Modelos/nivelsni.php

require_once __DIR__ . '/../publico/config/conexion.php';
require_once __DIR__ . '/BaseModelo.php';

class NivelSNI extends BaseModelo
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
             FROM niveles_sni"
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
            $where[]  = '(nombre LIKE ? OR fecha_creacion LIKE ?)';
            $params[] = "%$buscar%";
            $params[] = "%$buscar%";
            $types   .= 'ss';
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

        $total         = $this->obtenerCantidadNivelSNI($buscar, $filtro);
        $total_paginas = max(1, (int)ceil($total / $por_pagina));

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
        $sql     .= ' ORDER BY id_nivel ASC LIMIT ?, ?';
        $params[] = $desde;
        $params[] = $por_pagina;
        $types   .= 'ii';

        $data = $this->ejecutar($sql, $types, $params);

        return [
            'niveles_sni' => $data,
            'paginacion'  => [
                'total'         => $total,
                'por_pagina'    => $por_pagina,
                'pagina'        => $pagina,
                'total_paginas' => $total_paginas,
            ],
        ];
    }

    public function obtenerCantidadNivelSNI(?string $buscar = null, int $filtro = 2): int
    {
        $params = [];
        $types  = '';
        $sql    = 'SELECT COUNT(*) AS total FROM niveles_sni';
        $sql   .= $this->construirWhere($params, $types, $buscar, $filtro);

        $resultado = $this->ejecutar($sql, $types, $params, false);
        return (int)($resultado['total'] ?? 0);
    }

    // ─
    // OBTENER REGISTRO
    // ─

    public function obtenerEditar(int $id_nivel): array
    {
        $resultado = $this->ejecutar(
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

        if (!$resultado) throw new Exception('Nivel SNI no encontrado.');
        return $resultado;
    }

    public function obtenerDetalles(int $id_nivel): array
    {
        $resultado = $this->ejecutar(
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

        if (!$resultado) throw new Exception('Nivel SNI no encontrado.');
        return $resultado;
    }

    public function obtenerPorId(int $id_nivel, bool $forUpdate = false): ?array
    {
        $sql = 'SELECT estado FROM niveles_sni WHERE id_nivel = ?';
        if ($forUpdate) $sql .= ' FOR UPDATE';

        $resultado = $this->ejecutar($sql, 'i', [$id_nivel], false);
        return $resultado ?: null;
    }

    // ─
    // CRUD
    // ─

    /**
     * Registra un nuevo Nivel SNI (activo = 1).
     * DEBE ejecutarse dentro de una transacción.
     */
    public function registrarNivelSNI(string $nombre): int
    {
        $validacion = $this->verificarNivelSNI($nombre);
        if ($validacion['activo']) {
            throw new Exception('Ya existe un Nivel SNI activo con ese nombre.');
        }

        $this->ejecutar(
            "INSERT INTO niveles_sni (nombre, estado, fecha_creacion) VALUES (?, 1, NOW())",
            's',
            [$nombre]
        );
        return (int)$this->conn->insert_id;
    }

    /**
     * Edita un Nivel SNI existente.
     * DEBE ejecutarse dentro de una transacción.
     */
    public function editarNivelSNI(string $nombre, int $id_nivel): int
    {
        $this->ejecutar(
            "UPDATE niveles_sni SET nombre = ?, fecha_modificacion = NOW() WHERE id_nivel = ?",
            'si',
            [$nombre, $id_nivel]
        );
        return $id_nivel;
    }

    /**
     * Reactiva un Nivel SNI desactivado.
     * DEBE ejecutarse dentro de una transacción.
     */
    public function reactivar(int $id_nivel): void
    {
        $registro = $this->obtenerPorId($id_nivel, true);
        if (!$registro) throw new Exception('Nivel SNI no encontrado.');

        $datos = $this->ejecutar(
            'SELECT nombre FROM niveles_sni WHERE id_nivel = ?',
            'i',
            [$id_nivel],
            false
        );
        if (!$datos) throw new Exception('No se pudieron obtener datos del Nivel SNI.');

        $validacion = $this->verificarNivelSNI($datos['nombre']);
        if ($validacion['activo']) {
            throw new Exception('Ya existe un Nivel SNI activo con el mismo nombre.');
        }

        $this->ejecutar(
            "UPDATE niveles_sni SET estado = 1, fecha_modificacion = NOW()
             WHERE id_nivel = ? AND estado = 0",
            'i',
            [$id_nivel]
        );

        if ($this->conn->affected_rows === 0) {
            throw new Exception('El registro ya estaba activo o no se pudo actualizar.');
        }
    }

    /**
     * Desactivación lógica (soft delete).
     * Devuelve las filas afectadas.
     */
    public function eliminar_niveles_sni(int $id_nivel): int
    {
        $this->ejecutar(
            "UPDATE niveles_sni SET estado = 0, fecha_modificacion = NOW()
             WHERE id_nivel = ? AND estado <> 0",
            'i',
            [$id_nivel]
        );
        return $this->conn->affected_rows;
    }

    // ─
    // VERIFICACIONES DE DUPLICIDAD
    // ─

    public function verificarNivelSNI(string $nombre): array
    {
        $resultado = $this->ejecutar(
            "SELECT
                EXISTS(SELECT 1 FROM niveles_sni WHERE estado = 1 AND nombre = ?) AS activo,
                EXISTS(SELECT 1 FROM niveles_sni WHERE estado = 0 AND nombre = ?) AS desactivado",
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
    public function obtenerPorIdDiferente(int $id_nivel, string $nombre): array
    {
        $resultado = $this->ejecutar(
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
            'SELECT id_nivel FROM niveles_sni WHERE estado = 1 FOR UPDATE'
        );
    }
}
