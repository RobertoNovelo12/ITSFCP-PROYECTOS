<?php
// Modelos/director.php

require_once __DIR__ . '/../publico/config/conexion.php';
require_once __DIR__ . '/BaseModelo.php';

class Director extends BaseModelo
{

    // ─
    //  FILTROS / CONTEOS
    // ─

    public function obtenerDatosFiltro(): array
    {
        $this->desactivarDirectoresVencidos();

        return $this->ejecutar(
            "SELECT
                COUNT(*) AS Total,
                COALESCE(SUM(CASE WHEN d.estado = 1 THEN 1 ELSE 0 END), 0) AS Activo,
                COALESCE(SUM(CASE WHEN d.estado = 0 THEN 1 ELSE 0 END), 0) AS Desactivado
            FROM director d"
        );
    }

    // ─
    //  TABLA PRINCIPAL CON PAGINACIÓN
    // ─

    private function construirWhere(array &$params, string &$types, ?string $buscar, int $filtro): string
    {
        $where = [];

        if ($filtro === 0)      $where[] = "d.estado = 0";
        elseif ($filtro === 1)  $where[] = "d.estado = 1";
        else                    $where[] = "d.estado IN (0,1)";

        if (!empty($buscar)) {
            $where[]  = "(d.nombre LIKE ? OR d.apellido LIKE ? OR d.correo LIKE ? OR d.fecha_creacion LIKE ?)";
            $params[] = "%$buscar%";
            $params[] = "%$buscar%";
            $params[] = "%$buscar%";
            $params[] = "%$buscar%";
            $types   .= "ssss";
        }

        return " WHERE " . implode(" AND ", $where);
    }

    public function obtenerTablaFiltro(?string $buscar, int $filtro): array
    {
        $por_pagina = 6;
        $pagina     = max(1, (int)($_GET['pagina'] ?? 1));
        $desde      = ($pagina - 1) * $por_pagina;

        $total         = $this->obtenerCantidadDirector($buscar, $filtro);
        $total_paginas = max(1, (int)ceil($total / $por_pagina));

        $params = [];
        $types  = "";

        $sql  = "SELECT
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
        $sql     .= " ORDER BY d.id_director ASC LIMIT ?, ?";
        $params[] = $desde;
        $params[] = $por_pagina;
        $types   .= "ii";

        return [
            "director"   => $this->ejecutar($sql, $types, $params),
            "paginacion" => compact("total", "por_pagina", "pagina") + ["total_paginas" => $total_paginas],
        ];
    }

    public function obtenerCantidadDirector(?string $buscar = null, int $filtro = 2): int
    {
        $params = [];
        $types  = "";
        $sql    = "SELECT COUNT(*) AS total FROM director d";
        $sql   .= $this->construirWhere($params, $types, $buscar, $filtro);

        return (int)($this->ejecutar($sql, $types, $params, false)['total'] ?? 0);
    }

    // ─
    //  DETALLE / EDICIÓN
    // ─

    public function obtenerEditar(int $id_director): array
    {
        $row = $this->ejecutar(
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
            "i",
            [$id_director],
            false
        );

        if (!$row) throw new Exception("Director no encontrado");
        return $row;
    }

    public function obtenerDetalles(int $id_director): array
    {
        $row = $this->ejecutar(
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
            "i",
            [$id_director],
            false
        );

        if (!$row) throw new Exception("Director no encontrado");
        return $row;
    }

    public function obtenerGradosActivos(): array
    {
        return $this->ejecutar(
            "SELECT id_grado, nombre FROM grados_academicos WHERE estado = 1 ORDER BY nombre ASC"
        );
    }

    // ─
    //  CRUD
    // ─

    public function registrarDirector(
        int $id_grado,
        string $nombre,
        string $apellido,
        ?string $correo,
        ?string $telefono,
        ?string $fecha_inicio,
        ?string $fecha_final
    ): int {
        $validacion = $this->verificarDirector($correo);
        if ($validacion['activo']) {
            throw new Exception("Conflicto: ya existe un director activo con ese correo.");
        }

        $this->ejecutar(
            "INSERT INTO director
                (id_grado, nombre, apellido, correo, telefono, estado, fecha_creacion, fecha_inicio, fecha_final)
             VALUES (?, ?, ?, ?, ?, 1, NOW(), ?, ?)",
            "issssss",
            [$id_grado, $nombre, $apellido, $correo, $telefono, $fecha_inicio, $fecha_final]
        );

        $id = (int)$this->conn->insert_id;

        $this->registrarHistorial($id, 'CREACION', "Se registró el director {$nombre} {$apellido}");

        return $id;
    }

    public function editarDirector(
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
            "UPDATE director
             SET id_grado = ?, nombre = ?, apellido = ?, correo = ?, telefono = ?,
                 fecha_modificacion = NOW(), fecha_inicio = ?, fecha_final = ?, motivo_fin = ?
             WHERE id_director = ?",
            "issssssi",  // corregido: era "isssssssi"
            [$id_grado, $nombre, $apellido, $correo, $telefono,
             $fecha_inicio, $fecha_final, $motivo_fin, $id_director]
        );

        $this->registrarHistorial(
            $id_director,
            'ACTUALIZACION',
            "Se actualizaron los datos del director {$nombre} {$apellido}"
        );
    }

    public function reactivar(int $id_director): void
    {
        $datos = $this->ejecutar(
            "SELECT correo FROM director WHERE id_director = ?",
            "i",
            [$id_director],
            false
        );

        if (!$datos) throw new Exception("No se pudieron obtener datos del director.");

        if (!empty($datos['correo'])) {
            $validacion = $this->verificarDirector($datos['correo']);
            if ($validacion['activo']) {
                throw new Exception("Conflicto: ya existe un director activo con el mismo correo.");
            }
        }

        $this->ejecutar(
            "UPDATE director
             SET estado = 1, fecha_modificacion = NOW()
             WHERE id_director = ? AND estado = 0",
            "i",
            [$id_director]
        );

        if ($this->conn->affected_rows === 0) {
            throw new Exception("El director ya estaba activo o no se pudo actualizar.");
        }

        $this->registrarHistorial($id_director, 'ACTUALIZACION', "El director fue reactivado");
    }

    public function eliminarDirector(int $id_director): int
    {
        $this->ejecutar(
            "UPDATE director
             SET estado = 0, fecha_modificacion = NOW()
             WHERE id_director = ? AND estado <> 0",
            "i",
            [$id_director]
        );

        $filas = $this->conn->affected_rows;

        if ($filas > 0) {
            $this->registrarHistorial($id_director, 'BAJA', "El director fue desactivado");
        }

        return $filas;
    }

    // ─
    //  VERIFICACIONES / UTILIDADES
    // ─

    public function verificarDirector(?string $correo): array
    {
        if (empty($correo)) return ["activo" => 0, "desactivado" => 0];

        $row = $this->ejecutar(
            "SELECT
                EXISTS(SELECT 1 FROM director WHERE estado = 1 AND correo = ?) AS activo,
                EXISTS(SELECT 1 FROM director WHERE estado = 0 AND correo = ?) AS desactivado",
            "ss",
            [$correo, $correo],
            false
        );

        return [
            "activo"      => (int)($row['activo']      ?? 0),
            "desactivado" => (int)($row['desactivado'] ?? 0),
        ];
    }

    public function verificarDirectorOtroId(int $id_director, ?string $correo): array
    {
        if (empty($correo)) return ["activo" => 0, "desactivado" => 0];

        $row = $this->ejecutar(
            "SELECT
                EXISTS(SELECT 1 FROM director WHERE estado = 1 AND correo = ? AND id_director != ?) AS activo,
                EXISTS(SELECT 1 FROM director WHERE estado = 0 AND correo = ? AND id_director != ?) AS desactivado",
            "sisi",
            [$correo, $id_director, $correo, $id_director],
            false
        );

        return [
            "activo"      => (int)($row['activo']      ?? 0),
            "desactivado" => (int)($row['desactivado'] ?? 0),
        ];
    }

    public function obtenerPorId(int $id_director, bool $forUpdate = false): ?array
    {
        $sql = "SELECT estado FROM director WHERE id_director = ?";
        if ($forUpdate) $sql .= " FOR UPDATE";

        return $this->ejecutar($sql, "i", [$id_director], false) ?: null;
    }

    public function desactivarDirectoresVencidos(): void
    {
        $this->ejecutar(
            "UPDATE director
             SET estado = 0
             WHERE estado = 1 AND fecha_final IS NOT NULL AND CURDATE() > fecha_final"
        );
    }

    public function bloquearTabla(): void
    {
        $this->ejecutar("SELECT id_director FROM director WHERE estado = 1 FOR UPDATE");
    }

    // ─
    //  HISTORIAL / LÍNEA DE TIEMPO
    // ─

    public function registrarHistorial(int $id_director, string $accion, string $descripcion): void
    {
        $this->ejecutar(
            "INSERT INTO historial_director (id_director, accion, descripcion, fecha) VALUES (?, ?, ?, NOW())",
            "iss",
            [$id_director, $accion, $descripcion]
        );
    }

    public function lineaTiempoDirector(int $id_director, int $pagina = 1): array
    {
        $pagina    = max(1, $pagina);
        $por_pagina = 5;
        $desde     = ($pagina - 1) * $por_pagina;

        $total = (int)($this->ejecutar(
            "SELECT COUNT(*) AS total FROM historial_director WHERE id_director = ?",
            "i",
            [$id_director],
            false
        )['total'] ?? 0);

        $total_paginas = max(1, (int)ceil($total / $por_pagina));

        $historial = $this->ejecutar(
            "SELECT h.accion AS tipo_evento, h.descripcion, h.fecha
             FROM historial_director h
             WHERE h.id_director = ?
             ORDER BY h.fecha DESC
             LIMIT ?, ?",
            "iii",
            [$id_director, $desde, $por_pagina]
        );

        $agrupado = [];
        foreach ($historial as $item) {
            $agrupado[date("d/m/Y", strtotime($item['fecha']))][] = $item;
        }

        return [
            "datos"      => $agrupado,
            "paginacion" => compact("total", "por_pagina", "pagina") + ["total_paginas" => $total_paginas],
        ];
    }
}
