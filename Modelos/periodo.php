<?php
require_once __DIR__ . '/../publico/config/conexion.php';

class Periodo
{
    private $con;

    public function __construct($conn)
    {
        $this->con = $conn;
    }

    public function obtenerPeriodoDatosFiltro($rol)
    {

        switch ($rol) {
            case 'supervisor':
                $sql = "SELECT 
  COUNT(*) AS Total,
  COALESCE(SUM(CASE WHEN CURDATE() BETWEEN fecha_inicio AND fecha_final THEN 1 ELSE 0 END), 0) AS Activo,
  COALESCE(SUM(CASE WHEN CURDATE() > fecha_final THEN 1 ELSE 0 END), 0) AS Terminado
FROM periodos 
WHERE estado = 1;";
                $stmt = $this->con->prepare($sql);
                break;
            default:
                return []; // Retorna un array vacío si el rol no es válido
        }
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    // TABLA PRINCIPAL
    public function obtenerPeriodoTablaFiltro($buscar, $filtro)
    {
        $total = $this->obtenerCantidadPeriodo($buscar, $filtro);

        $por_pagina = 6;
        $pagina = empty($_GET['pagina']) ? 1 : intval($_GET['pagina']);
        $desde = ($pagina - 1) * $por_pagina;
        $total_paginas = ($total > 0) ? ceil($total / $por_pagina) : 1;

        $params = [];
        $types = "";
        $where = [];

        $sql = "SELECT 
        id_periodos,
        periodo,
        fecha_inicio AS inicio,
        fecha_final AS final,
        fecha_creacion AS crear,
        CASE 
            WHEN CURDATE() BETWEEN fecha_inicio AND fecha_final THEN 'Activo'        
            WHEN CURDATE() > fecha_final THEN 'Terminado'
            ELSE 'Desconocido'
        END AS estados
    FROM periodos";

        // Siempre excluir eliminados (soft delete)
        $where[] = "estado = 1";

        // Filtro por estado lógico (fecha)
        switch ($filtro) {
            case 0: // Terminados
                $where[] = "CURDATE() > fecha_final";
                break;

            case 1: // Activo
                $where[] = "CURDATE() BETWEEN fecha_inicio AND fecha_final";
                break;

            case 2: // Todos
                // No se agrega nada
                break;
        }

        // Búsqueda
        if (!empty($buscar)) {
            $where[] = "(fecha_inicio LIKE ? OR fecha_final LIKE ? OR periodo LIKE ?)";
            $params[] = "%$buscar%";
            $params[] = "%$buscar%";
            $params[] = "%$buscar%";
            $types .= "sss";
        }

        // Construcción correcta del WHERE
        if (!empty($where)) {
            $sql .= " WHERE " . implode(" AND ", $where);
        }

        // Orden + paginación
        $sql .= " ORDER BY id_periodos ASC LIMIT $desde, $por_pagina";

        // Preparar
        $stmt = $this->con->prepare($sql);

        if (!$stmt) {
            die("Error en prepare(): " . $this->con->error . "<br>SQL: $sql");
        }

        // 🔗 Bind dinámico
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }

        // Ejecutar
        if (!$stmt->execute()) {
            die("Error en execute(): " . $stmt->error);
        }

        // Resultado
        return [
            "periodo" => $stmt->get_result()->fetch_all(MYSQLI_ASSOC),
            "paginacion" => [
                "total" => $total,
                "por_pagina" => $por_pagina,
                "pagina" => $pagina,
                "total_paginas" => $total_paginas
            ]
        ];
    }

    public function obtenerCantidadPeriodo($buscar = null, $filtro = 2)
    {
        $sql = "SELECT COUNT(*) AS total FROM periodos";
        $params = [];
        $types = "";
        $where = [];

        // Siempre excluir eliminados (soft delete)
        $where[] = "estado = 1";

        // Filtros
        switch ($filtro) {
            case 0: // Terminado
                $where[] = "CURDATE() > fecha_final";
                break;
            case 1: // Activo
                $where[] = "CURDATE() BETWEEN fecha_inicio AND fecha_final";
                break;
            case 2: // Total
                // No filtro
                break;
        }

        // Búsqueda
        if (!empty($buscar)) {
            $where[] = "(fecha_inicio LIKE ? OR fecha_final LIKE ? OR periodo LIKE ?)";
            $params[] = "%$buscar%";
            $params[] = "%$buscar%";
            $params[] = "%$buscar%";
            $types .= "sss";
        }

        // Construcción del WHERE
        if (!empty($where)) {
            $sql .= " WHERE " . implode(" AND ", $where);
        }

        $stmt = $this->con->prepare($sql);

        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }

        $stmt->execute();
        $resultado = $stmt->get_result()->fetch_assoc();

        return $resultado['total'];
    }


    // EDICIÓN
    public function obtenerPeriodoEditar($id_periodos)
    {
        $sql = "SELECT id_periodos, periodo AS nombre, fecha_inicio AS inicio, fecha_final AS fin,
        CASE 
        WHEN CURDATE() BETWEEN fecha_inicio AND fecha_final THEN 'Activo'
        WHEN CURDATE() > fecha_final THEN 'Terminado'
    END AS estado
                FROM periodos
                WHERE id_periodos = ?";

        $stmt = $this->con->prepare($sql);
        $stmt->bind_param("i", $id_periodos);
        $stmt->execute();

        $periodo = $stmt->get_result()->fetch_assoc();

        if (!$periodo) {
            throw new Exception("Periodo no encontrada");
        }

        return $periodo;
    }

    //Obtener datos para detalles
    public function obtenerPeriodoDetalles($id_periodos)
    {
        $sql = "SELECT id_periodos, periodo, fecha_inicio, fecha_final, fecha_creacion, fecha_modificacion,
        CASE 
        WHEN CURDATE() BETWEEN fecha_inicio AND fecha_final THEN 'Activo'
        WHEN CURDATE() > fecha_final THEN 'Terminado'
    END AS estado
                FROM periodos
                WHERE id_periodos = ?";

        $stmt = $this->con->prepare($sql);
        $stmt->bind_param("i", $id_periodos);
        $stmt->execute();

        $periodo = $stmt->get_result()->fetch_assoc();

        if (!$periodo) {
            throw new Exception("Periodo no encontrado");
        }

        return $periodo;
    }

    //Crea Periodo
    public function registrarPeriodo($periodo, $fecha_inicio, $fecha_final)
    {
        $sql = "INSERT INTO periodos (periodo, fecha_inicio, fecha_final, estado, fecha_creacion) 
            VALUES (?, ?, ?, 1, NOW())";

        $stmt = $this->con->prepare($sql);

        if (!$stmt) {
            throw new Exception("Error en prepare: " . $this->con->error);
        }

        $stmt->bind_param("sss", $periodo, $fecha_inicio, $fecha_final);

        if (!$stmt->execute()) {
            throw new Exception("Error en execute: " . $stmt->error);
        }

        return $stmt->insert_id;
    }

    // REACTIVAR
    public function reactivarPeriodo($id)
    {
        $sql = "UPDATE periodos 
            SET estado = 1, fecha_modificacion = NOW() 
            WHERE id_periodos = ?";

        $stmt = $this->con->prepare($sql);
        $stmt->bind_param("i", $id);
        $stmt->execute();
    }

    public function obtenerPorNombre($nombre)
    {
        $sql = "SELECT id_periodos FROM periodos WHERE periodo = ? LIMIT 1";
        $stmt = $this->con->prepare($sql);
        $stmt->bind_param("s", $nombre);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    public function bloquear_tabla()
    {
        // BLOQUEO DE CONCURRENCIA
        $sql = "SELECT id_periodos FROM periodos WHERE estado = 1 FOR UPDATE";
        $stmt = $this->con->prepare($sql);
        $stmt->execute();
        $res = $stmt->get_result();

        return $res;
    }

    // ELIMINAR (SOFT DELETE)
    public function eliminar_periodo($id_periodo)
    {
        $sql = "UPDATE periodos 
            SET estado = 0, fecha_modificacion = NOW() 
            WHERE id_periodos = ?";

        $stmt = $this->con->prepare($sql);

        if (!$stmt) {
            throw new Exception("Error en prepare: " . $this->con->error);
        }

        $stmt->bind_param("i", $id_periodo);

        if (!$stmt->execute()) {
            throw new Exception("Error en execute: " . $stmt->error);
        }

        return $stmt->affected_rows;
    }

    public function desactivarActivos()
    {
        $sql = "UPDATE periodos 
            SET estado = 0, fecha_modificacion = NOW() 
            WHERE estado = 1";

        $stmt = $this->con->prepare($sql);
        $stmt->execute();
    }
    //Busca duplicidad de periodos
    public function verificarPeriodo($nombre, $fecha_inicio, $fecha_fin)
    {
        $sql = "SELECT 
                MAX(CASE 
                    WHEN estado = 1 
                    AND (? <= fecha_final AND ? >= fecha_inicio) 
                    THEN 1 ELSE 0 END) AS activo,

                MAX(CASE 
                    WHEN estado = 0 
                    AND (? <= fecha_final AND ? >= fecha_inicio) 
                    THEN 1 ELSE 0 END) AS desactivado,

                MAX(CASE 
                    WHEN estado = 1 AND periodo = ? 
                    THEN 1 ELSE 0 END) AS activo_nombre,

                MAX(CASE 
                    WHEN estado = 0 AND periodo = ? 
                    THEN 1 ELSE 0 END) AS desactivado_nombre

            FROM periodos";

        $stmt = $this->con->prepare($sql);

        if (!$stmt) {
            throw new Exception("Error en prepare: " . $this->con->error);
        }

        $stmt->bind_param(
            "ssssss",
            $fecha_inicio,
            $fecha_fin,
            $fecha_inicio,
            $fecha_fin,
            $nombre,
            $nombre
        );

        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();

        return [
            "activo" => ($res['activo'] || $res['activo_nombre']) ? 1 : 0,
            "desactivado" => ($res['desactivado'] || $res['desactivado_nombre']) ? 1 : 0
        ];
    }
    //Para el caso de desactivar periodo
    public function obtenerPorId($id)
    {
        $sql = "SELECT estado FROM periodos WHERE id_periodos = ?";
        $stmt = $this->con->prepare($sql);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }
}
