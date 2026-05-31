<?php
// Modelos/gradoacademico.php

require_once __DIR__ . '/../publico/config/conexion.php';
require_once __DIR__ . '/BaseModelo.php';

class GradoAcademico extends BaseModelo
{

    // index
    //  FILTROS / CONTEOS
    // index

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

    // index
    //  TABLA PRINCIPAL CON PAGINACIÓN
    // index

    private function construirWhere(array &$params, string &$types, ?string $buscar, int $filtro): string
    {
        $where = [];

        if ($filtro === 0)      $where[] = "estado = 0";
        elseif ($filtro === 1)  $where[] = "estado = 1";
        else                    $where[] = "estado IN (0,1)";

        if (!empty($buscar)) {
            $where[]  = "(nombre LIKE ? OR fecha_creacion LIKE ?)";
            $params[] = "%$buscar%";
            $params[] = "%$buscar%";
            $types   .= "ss";
        }

        return " WHERE " . implode(" AND ", $where);
    }

    public function obtenerTablaFiltro(?string $buscar, int $filtro): array
    {
        $por_pagina = 6;
        $pagina     = max(1, (int)($_GET['pagina'] ?? 1));
        $desde      = ($pagina - 1) * $por_pagina;

        $total         = $this->obtenerCantidadGradoAcademico($buscar, $filtro);
        $total_paginas = max(1, (int)ceil($total / $por_pagina));

        $params = [];
        $types  = "";

        $sql  = "SELECT
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
        $sql     .= " ORDER BY id_grado ASC LIMIT ?, ?";
        $params[] = $desde;
        $params[] = $por_pagina;
        $types   .= "ii";

        return [
            "grados_academicos" => $this->ejecutar($sql, $types, $params),
            "paginacion"        => compact("total", "por_pagina", "pagina") + ["total_paginas" => $total_paginas],
        ];
    }

    public function obtenerCantidadGradoAcademico(?string $buscar = null, int $filtro = 2): int
    {
        $params = [];
        $types  = "";
        $sql    = "SELECT COUNT(*) AS total FROM grados_academicos";
        $sql   .= $this->construirWhere($params, $types, $buscar, $filtro);

        return (int)($this->ejecutar($sql, $types, $params, false)['total'] ?? 0);
    }

    // index
    //  DETALLE / EDICIÓN
    // index

    public function obtenerEditar(int $id_grado): array
    {
        $row = $this->ejecutar(
            "SELECT
                id_grado, nombre,
                CASE
                    WHEN estado = 1 THEN 'Activo'
                    WHEN estado = 0 THEN 'Desactivado'
                    ELSE 'Desconocido'
                END AS estado
            FROM grados_academicos
            WHERE id_grado = ?",
            "i",
            [$id_grado],
            false
        );

        if (!$row) throw new Exception("Grado Académico no encontrado");
        return $row;
    }

    public function obtenerDetalles(int $id_grado): array
    {
        $row = $this->ejecutar(
            "SELECT
                id_grado, nombre, fecha_creacion, fecha_modificacion,
                CASE
                    WHEN estado = 1 THEN 'Activo'
                    WHEN estado = 0 THEN 'Desactivado'
                    ELSE 'Desconocido'
                END AS estado
            FROM grados_academicos
            WHERE id_grado = ?",
            "i",
            [$id_grado],
            false
        );

        if (!$row) throw new Exception("Grado Académico no encontrado");
        return $row;
    }

    // index
    //  CRUD
    // index

    public function registrarGradoAcademico(string $nombre): int
    {
        $validacion = $this->verificarGradoAcademico($nombre);
        if ($validacion['activo']) {
            throw new Exception("Conflicto: ya existe un Grado Académico activo con ese nombre.");
        }

        $this->ejecutar(
            "INSERT INTO grados_academicos (nombre, estado, fecha_creacion) VALUES (?, 1, NOW())",
            "s",
            [$nombre]
        );

        return (int)$this->conn->insert_id;
    }

    public function editarGradoAcademico(string $nombre, int $id_grado): void
    {
        $this->ejecutar(
            "UPDATE grados_academicos SET nombre = ?, fecha_modificacion = NOW() WHERE id_grado = ?",
            "si",
            [$nombre, $id_grado]
        );
    }

    public function reactivar(int $id_grado): void
    {
        $datos = $this->ejecutar(
            "SELECT nombre FROM grados_academicos WHERE id_grado = ?",
            "i",
            [$id_grado],
            false
        );

        if (!$datos) throw new Exception("No se pudieron obtener datos de Grado Académico.");

        $validacion = $this->verificarGradoAcademico($datos['nombre']);
        if ($validacion['activo']) {
            throw new Exception("Conflicto: ya existe un Grado Académico activo con el mismo nombre.");
        }

        $this->ejecutar(
            "UPDATE grados_academicos
             SET estado = 1, fecha_modificacion = NOW()
             WHERE id_grado = ? AND estado = 0",
            "i",
            [$id_grado]
        );

        if ($this->conn->affected_rows === 0) {
            throw new Exception("El registro ya estaba activo o no se pudo actualizar.");
        }
    }

    public function eliminarGradoAcademico(int $id_grado): int
    {
        $this->ejecutar(
            "UPDATE grados_academicos
             SET estado = 0, fecha_modificacion = NOW()
             WHERE id_grado = ? AND estado <> 0",
            "i",
            [$id_grado]
        );

        return $this->conn->affected_rows;
    }

    // index
    //  VERIFICACIONES / UTILIDADES
    // index

    public function verificarGradoAcademico(string $nombre): array
    {
        $row = $this->ejecutar(
            "SELECT
                EXISTS(SELECT 1 FROM grados_academicos WHERE estado = 1 AND nombre = ?) AS activo,
                EXISTS(SELECT 1 FROM grados_academicos WHERE estado = 0 AND nombre = ?) AS desactivado",
            "ss",
            [$nombre, $nombre],
            false
        );

        return [
            "activo"      => (int)($row['activo']      ?? 0),
            "desactivado" => (int)($row['desactivado'] ?? 0),
        ];
    }

    public function verificarGradoOtroId(int $id_grado, string $nombre): array
    {
        $row = $this->ejecutar(
            "SELECT
                EXISTS(SELECT 1 FROM grados_academicos WHERE estado = 1 AND nombre = ? AND id_grado != ?) AS activo,
                EXISTS(SELECT 1 FROM grados_academicos WHERE estado = 0 AND nombre = ? AND id_grado != ?) AS desactivado",
            "sisi",
            [$nombre, $id_grado, $nombre, $id_grado],
            false
        );

        return [
            "activo"      => (int)($row['activo']      ?? 0),
            "desactivado" => (int)($row['desactivado'] ?? 0),
        ];
    }

    public function obtenerPorId(int $id_grado, bool $forUpdate = false): ?array
    {
        $sql = "SELECT estado FROM grados_academicos WHERE id_grado = ?";
        if ($forUpdate) $sql .= " FOR UPDATE";

        return $this->ejecutar($sql, "i", [$id_grado], false) ?: null;
    }

    public function bloquearTabla(): void
    {
        $this->ejecutar("SELECT id_grado FROM grados_academicos WHERE estado = 1 FOR UPDATE");
    }
}
