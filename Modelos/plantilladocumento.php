<?php
require_once __DIR__ . '/../publico/config/conexion.php';

class plantilladocumento
{
    private $con;

    public function __construct($conn)
    {
        $this->con = $conn;
    }

    /**
     * Obtiene datos para filtros (totales, activos, desactivados)
     */
    public function obtenerDatosFiltro($rol): array
    {
        if ($rol !== 'supervisor') {
            return [];
        }

        $sql = "SELECT 
                    COUNT(*) AS Total,
                    COALESCE(SUM(CASE WHEN estado = 1 THEN 1 ELSE 0 END), 0) AS Activo,
                    COALESCE(SUM(CASE WHEN estado = 0 THEN 1 ELSE 0 END), 0) AS Desactivado
                FROM plantillas_documentos";

        $stmt = $this->con->prepare($sql);
        if (!$stmt) throw new Exception("Error en prepare (obtenerDatosFiltro): " . $this->con->error);
        if (!$stmt->execute()) throw new Exception("Error en execute (obtenerDatosFiltro): " . $stmt->error);
        $resultado = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $resultado;
    }


    /**
     * Método base para construir WHERE dinámico (REUTILIZABLE)
     */
    private function construirWhere(&$params, &$types, $buscar, $filtro): string
    {
        $where = [];

        if ($filtro == 0) $where[] = "estado = 0";
        if ($filtro == 1) $where[] = "estado = 1";
        elseif ($filtro == 2) $where[] = "estado IN (0,1)";

        if (!empty($buscar)) {
            $where[] = "(nombre LIKE ? OR categoria LIKE ?)";
            $params[] = "%$buscar%";
            $params[] = "%$buscar%";
            $types .= "ss";
        }

        return " WHERE " . implode(" AND ", $where);
    }

    /**
     * Obtiene tabla principal con paginación
     */
    public function obtenerTablaFiltro($buscar, $filtro): array
    {
        $pagina = isset($_GET['pagina']) ? max(1, (int)$_GET['pagina']) : 1;
        $por_pagina = 6;
        $desde = ($pagina - 1) * $por_pagina;

        $params = [];
        $types = "";

        $total = $this->obtenerCantidadGradoAcademico($buscar, $filtro);
        $total_paginas = ($total > 0) ? ceil($total / $por_pagina) : 1;

        $sql = "SELECT 
                    id_plantilla,
                    nombre,
                    fecha_modificacion AS modificar,
                    fecha_creacion AS crear,
                    CASE 
                        WHEN estado = 1 THEN 'Activo'        
                        WHEN estado = 0 THEN 'Desactivado'
                        ELSE 'Desconocido'
                    END AS estados
                FROM plantillas_documentos";

        $sql .= $this->construirWhere($params, $types, $buscar, $filtro);
        $sql .= " ORDER BY id_plantilla ASC LIMIT ?, ?";

        $stmt = $this->con->prepare($sql);
        if (!$stmt) throw new Exception("Error en prepare (obtenerTablaFiltro): " . $this->con->error);

        $params[] = $desde;
        $params[] = $por_pagina;
        $types .= "ii";

        $stmt->bind_param($types, ...$params);
        if (!$stmt->execute()) throw new Exception("Error en execute (obtenerTablaFiltro): " . $stmt->error);

        $data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        return [
            "plantillas_documentos" => $data,
            "paginacion" => [
                "total" => $total,
                "por_pagina" => $por_pagina,
                "pagina" => $pagina,
                "total_paginas" => $total_paginas
            ]
        ];
    }

    /**
     * Obtiene total de registros con filtros
     */
    public function obtenerCantidad($buscar = null, $filtro = 2): int
    {
        $params = [];
        $types = "";

        $sql = "SELECT COUNT(*) AS total FROM plantillas_documentos";
        $sql .= $this->construirWhere($params, $types, $buscar, $filtro);

        $stmt = $this->con->prepare($sql);
        if (!$stmt) throw new Exception("Error en prepare (obtenerCantidad): " . $this->con->error);

        if (!empty($params)) $stmt->bind_param($types, ...$params);
        if (!$stmt->execute()) throw new Exception("Error en execute (obtenerCantidad): " . $stmt->error);

        $resultado = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return (int)($resultado['total'] ?? 0);
    }


    /**
     * Obtiene datos para vista de detalles
     */
    public function obtenerTipos_documentos(): array
    {
        $sql = "SELECT 
                    id_plantilla, 
                    nombre
                FROM plantillas_documentos
                WHERE activo = 1";

        $stmt = $this->con->prepare($sql);
        if (!$stmt) throw new Exception("Error en prepare (obtenerTipos_documentos): " . $this->con->error);

        if (!$stmt->execute()) throw new Exception("Error en execute (obtenerTipos_documentos): " . $stmt->error);

        $registro = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$registro) throw new Exception("Tipo de documento no encontrado");

        return $registro;
    }

    /**
     * Obtiene datos para vista de detalles
     */
    public function obtenerInfoTipos($id_plantilla): array
    {
        $sql = "SELECT 
                    p.id_plantilla, 
                    p.nombre,
                    p.version,
                FROM plantillas_documentos AS p
                WHERE p.id_plantilla = ?";

        $stmt = $this->con->prepare($sql);
        if (!$stmt) throw new Exception("Error en prepare (obtenerTipos): " . $this->con->error);

        $stmt->bind_param("i", $id_plantilla);
        if (!$stmt->execute()) throw new Exception("Error en execute (obtenerTipos): " . $stmt->error);

        $registro = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$registro) throw new Exception("Plantillas de documentos no encontrado");

        return $registro;
    }

    /**
     * Registra un nuevo Grado Académico.
     * IMPORTANTE: Ejecutar dentro de una transacción.
     *
     * @param string $nombre
     * @param int $version
     * @throws Exception
     */
    public function registrar(string $nombre, int $version, $archivo)
    {

        $sql = "INSERT INTO plantillas_documentos 
            (nombre, version, estado, fecha_creacion) 
            VALUES (?, 1, NOW())";

        $stmt = $this->con->prepare($sql);
        if (!$stmt) throw new Exception("Error en prepare (registrarGradoAcademico): " . $this->con->error);

        $stmt->bind_param("s", $nombre);
        if (!$stmt->execute()) throw new Exception("Error en execute (registrarGradoAcademico): " . $stmt->error);

        $id = $stmt->insert_id;
        $stmt->close();

        return $id;
    }

    /**
     * Reactiva un Grado Académico previamente desactivado.
     * IMPORTANTE: Ejecutar dentro de transacción.
     *
     * @param int $id_grado
     * @return void
     * @throws Exception
     */
    public function reactivar(int $id_grado): void
    {

        $sqlDatos = "SELECT nombre FROM plantillas_documentos WHERE id_grado = ?";
        $stmtDatos = $this->con->prepare($sqlDatos);
        if (!$stmtDatos) throw new Exception("Error en prepare (reactivar datos): " . $this->con->error);

        $stmtDatos->bind_param("i", $id_grado);
        $stmtDatos->execute();
        $datos = $stmtDatos->get_result()->fetch_assoc();
        $stmtDatos->close();

        if (!$datos) throw new Exception("No se pudieron obtener datos de Grado Académico.");

        $sql = "UPDATE plantillas_documentos 
            SET estado = 1, 
                fecha_modificacion = NOW() 
            WHERE id_grado = ? 
              AND estado = 0";

        $stmt = $this->con->prepare($sql);
        if (!$stmt) throw new Exception("Error en prepare (reactivarGradoAcademico): " . $this->con->error);

        $stmt->bind_param("i", $id_grado);
        if (!$stmt->execute()) throw new Exception("Error en execute (reactivarGradoAcademico): " . $stmt->error);

        if ($stmt->affected_rows === 0) {
            throw new Exception("El registro ya estaba activo o no se pudo actualizar.");
        }

        $stmt->close();
    }

    /**
     * Bloquea únicamente los registros activos.
     * IMPORTANTE: Debe ejecutarse dentro de una transacción.
     *
     * @return void
     * @throws Exception
     */
    public function bloquear_tabla(): void
    {
        $sql = "SELECT id_grado FROM plantillas_documentos WHERE estado = 1 FOR UPDATE";
        $stmt = $this->con->prepare($sql);
        if (!$stmt) throw new Exception("Error en prepare (bloquear_tabla): " . $this->con->error);
        if (!$stmt->execute()) throw new Exception("Error en execute (bloquear_tabla): " . $stmt->error);
        $stmt->free_result();
        $stmt->close();
    }

    /**
     * Eliminación lógica (soft delete) de un Grado Académico.
     *
     * @param int $id_grado
     * @return int Número de filas afectadas
     * @throws Exception
     */
    public function eliminar_plantillas_documentos(int $id_grado): int
    {
        $sql = "UPDATE plantillas_documentos 
                SET estado = 0, 
                    fecha_modificacion = NOW() 
                WHERE id_grado = ? 
                  AND estado <> 0";

        $stmt = $this->con->prepare($sql);
        if (!$stmt) throw new Exception("Error en prepare (eliminar_plantillas_documentos): " . $this->con->error);

        $stmt->bind_param("i", $id_grado);
        if (!$stmt->execute()) throw new Exception("Error en execute (eliminar_plantillas_documentos): " . $stmt->error);

        $filas = $stmt->affected_rows;
        $stmt->close();

        return $filas;
    }
}
