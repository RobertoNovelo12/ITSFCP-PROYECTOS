<?php
// Modelos/carrera.php

require_once __DIR__ . '/../publico/config/conexion.php';
require_once __DIR__ . '/BaseModelo.php';

class Carrera extends BaseModelo
{
    public function __construct(mysqli $conn)
    {
        parent::__construct($conn);
    }


    // ─
    // FILTROS / CONTEOS
    // ─

    public function obtenerDatosFiltro(string $rol): array
    {
        if ($rol !== 'supervisor') {
            return [];
        }

        return $this->ejecutar(
            "SELECT
                COUNT(*) AS Total,
                COALESCE(SUM(CASE WHEN estado = 1 THEN 1 ELSE 0 END), 0) AS Activo,
                COALESCE(SUM(CASE WHEN estado = 0 THEN 1 ELSE 0 END), 0) AS Desactivado
             FROM carreras"
        );
    }


    // ─
    // TABLA PRINCIPAL
    // ─

    public function obtenerTablaFiltro(?string $buscar, int $filtro): array
    {
        $total         = $this->obtenerCantidadCarrera($buscar, $filtro);
        $por_pagina    = 6;
        $pagina        = max(1, (int)($_GET['pagina'] ?? 1));
        $desde         = ($pagina - 1) * $por_pagina;
        $total_paginas = max(1, (int)ceil($total / $por_pagina));

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
                ORDER BY id_carrera ASC
                LIMIT ?, ?";

        $params[] = $desde;
        $params[] = $por_pagina;
        $types   .= 'ii';

        return [
            "carrera"    => $this->ejecutar($sql, $types, $params),
            "paginacion" => compact("total", "por_pagina", "pagina") + ["total_paginas" => $total_paginas],
        ];
    }

    public function obtenerCantidadCarrera(?string $buscar, int $filtro): int
    {
        [$where, $params, $types] = $this->construirWhere($buscar, $filtro);

        $sql = "SELECT COUNT(*) AS total FROM carreras $where";

        return (int)($this->ejecutar($sql, $types, $params, false)['total'] ?? 0);
    }

    /**
     * Construye la cláusula WHERE con sus parámetros.
     * Devuelve [string $where, array $params, string $types].
     *
     * Filtro: 0 = Desactivado | 1 = Activo | 2 = Total (ambos)
     */
    private function construirWhere(?string $buscar, int $filtro): array
    {
        $conditions = [];
        $params     = [];
        $types      = '';

        // Estado
        if ($filtro === 0 || $filtro === 1) {
            $conditions[] = "estado = ?";
            $params[]     = $filtro;
            $types       .= 'i';
        } else {
            // filtro === 2: ambos estados
            $conditions[] = "estado IN (0, 1)";
        }

        // Búsqueda por nombre y fecha
        if (!empty($buscar)) {
            $conditions[] = "(nombre_carrera LIKE ? OR fecha_creacion LIKE ?)";
            $params[]     = "%$buscar%";
            $params[]     = "%$buscar%";
            $types       .= 'ss';
        }

        $where = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

        return [$where, $params, $types];
    }


    // ─
    // DATOS PARA FORMULARIOS
    // ─

    public function obtenerEditar(int $id_carrera): array
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
            "i",
            [$id_carrera],
            false
        );

        if (!$fila) {
            throw new Exception("Carrera no encontrada.");
        }

        return $fila;
    }

    public function obtenerDetalles(int $id_carrera): array
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
            "i",
            [$id_carrera],
            false
        );

        if (!$fila) {
            throw new Exception("Carrera no encontrada.");
        }

        return $fila;
    }


    // ─
    // CREAR
    // ─

    /**
     * Inserta una nueva carrera activa.
     * Lanza Exception si ya existe una carrera activa con el mismo nombre.
     * Debe ejecutarse dentro de una transacción.
     *
     * @return int  ID insertado.
     * @throws Exception
     */
    public function registrarCarrera(string $nombre_carrera): int
    {
        $validacion = $this->verificarCarrera($nombre_carrera);

        if ($validacion['activo'] > 0) {
            throw new Exception('error_duplicado');
        }

        $this->ejecutar(
            "INSERT INTO carreras (nombre_carrera, estado, fecha_creacion) VALUES (?, 1, NOW())",
            "s",
            [$nombre_carrera]
        );

        return (int)$this->conn->insert_id;
    }


    // ─
    // EDITAR
    // ─

    /**
     * Actualiza el nombre de una carrera.
     * Debe ejecutarse dentro de una transacción.
     *
     * @return int  El mismo $id_carrera recibido.
     */
    public function editarCarrera(string $nombre_carrera, int $id_carrera): int
    {
        $this->ejecutar(
            "UPDATE carreras SET nombre_carrera = ?, fecha_modificacion = NOW() WHERE id_carrera = ?",
            "si",
            [$nombre_carrera, $id_carrera]
        );

        return $id_carrera;
    }


    // ─
    // REACTIVAR
    // ─

    /**
     * Reactiva una carrera desactivada.
     * Valida que no exista otra activa con el mismo nombre.
     * Debe ejecutarse dentro de una transacción con bloqueo previo.
     *
     * @throws Exception
     */
    public function reactivar(int $id_carrera): void
    {
        // Obtener nombre para validar duplicidad
        $datos = $this->ejecutar(
            "SELECT nombre_carrera FROM carreras WHERE id_carrera = ?",
            "i",
            [$id_carrera],
            false
        );

        if (!$datos) {
            throw new Exception("Carrera no encontrada.");
        }

        $validacion = $this->verificarCarrera($datos['nombre_carrera']);

        if ($validacion['activo'] > 0) {
            throw new Exception('error_duplicado');
        }

        $this->ejecutar(
            "UPDATE carreras
             SET estado = 1, fecha_modificacion = NOW()
             WHERE id_carrera = ? AND estado = 0",
            "i",
            [$id_carrera]
        );

        if ($this->conn->affected_rows === 0) {
            throw new Exception("La carrera ya estaba activa o no se pudo actualizar.");
        }
    }


    // ─
    // DESACTIVAR (soft delete)
    // ─

    /**
     * @return int  Filas afectadas (≥ 1 éxito, 0 ya estaba desactivada).
     */
    public function eliminar_carrera(int $id_carrera): int
    {
        $this->ejecutar(
            "UPDATE carreras
             SET estado = 0, fecha_modificacion = NOW()
             WHERE id_carrera = ? AND estado <> 0",
            "i",
            [$id_carrera]
        );

        return $this->conn->affected_rows;
    }


    // ─
    // BLOQUEO OPTIMISTA PARA CONCURRENCIA
    // ─

    public function bloquear_tabla(): void
    {
        $this->ejecutar(
            "SELECT id_carrera FROM carreras WHERE estado = 1 FOR UPDATE"
        );
    }


    // ─
    // OBTENER POR ID
    // ─

    public function obtenerPorId(int $id_carrera, bool $forUpdate = false): ?array
    {
        $sql = "SELECT estado FROM carreras WHERE id_carrera = ?";
        if ($forUpdate) {
            $sql .= " FOR UPDATE";
        }

        $fila = $this->ejecutar($sql, "i", [$id_carrera], false);

        return $fila ?: null;
    }


    // ─
    // VERIFICACIÓN DE DUPLICIDAD
    // ─

    /**
     * Verifica si ya existe una carrera con el nombre dado (cualquier estado).
     *
     * @return array{activo: int, desactivado: int}
     */
    public function verificarCarrera(string $nombre_carrera): array
    {
        $fila = $this->ejecutar(
            "SELECT
                EXISTS(SELECT 1 FROM carreras WHERE estado = 1 AND nombre_carrera = ?) AS activo,
                EXISTS(SELECT 1 FROM carreras WHERE estado = 0 AND nombre_carrera = ?) AS desactivado",
            "ss",
            [$nombre_carrera, $nombre_carrera],
            false
        );

        return [
            'activo'      => (int)($fila['activo']      ?? 0),
            'desactivado' => (int)($fila['desactivado'] ?? 0),
        ];
    }

    /**
     * Verifica si existe otra carrera con el mismo nombre, excluyendo el ID actual.
     * Usado al editar para detectar conflictos con otros registros.
     *
     * @return array{activo: int, desactivado: int}
     */
    public function obtenerPorIdDiferente(int $id_carrera, string $nombre_carrera): array
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
            "sisi",
            [$nombre_carrera, $id_carrera, $nombre_carrera, $id_carrera],
            false
        );

        return [
            'activo'      => (int)($fila['activo']      ?? 0),
            'desactivado' => (int)($fila['desactivado'] ?? 0),
        ];
    }
}