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
                $sql = "SELECT DISTINCT 
  COUNT(*) AS Total,
  SUM(CASE WHEN CURDATE() BETWEEN fecha_inicio AND fecha_final THEN 1 ELSE 0 END) AS Activo,
  SUM(CASE WHEN CURDATE() < fecha_inicio THEN 1 ELSE 0 END) AS Pendiente,
  SUM(CASE WHEN CURDATE() > fecha_final THEN 1 ELSE 0 END) AS Terminado
FROM periodos WHERE estado = 1 ORDER BY periodo DESC
LIMIT 1;";
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
    fecha_modificacion AS modificar,
    CASE 
        WHEN CURDATE() BETWEEN fecha_inicio AND fecha_final THEN 'Activo'
        WHEN CURDATE() < fecha_inicio THEN 'Pendiente'
        WHEN CURDATE() > fecha_final THEN 'Terminado'
    END AS estado
FROM periodos ";

        // Filtros

        switch ($filtro) {
            case 0: //Terminado
                $where[] = " CURDATE() > fecha_final";
                break;
            case 1: //Pendiente
                $where[] = " CURDATE() < fecha_inicio";
            case 2:
                $where[] = " CURDATE() BETWEEN fecha_inicio AND fecha_final";
                break;
            case 3:
                break;
            default:
                break;
        }

        // MEJORAR: SI buscar es fecha inicio, fecha final o periodo

        if (!empty($buscar)) {
        $where[] = "(fecha_inicio LIKE ? OR fecha_final LIKE ? OR periodo LIKE ?)";
        $params[] = "%$buscar%";
        $params[] = "%$buscar%";
        $params[] = "%$buscar%";
        $types .= "sss";
        }

        if (!empty($where)) {
            $sql .= " WHERE " . implode(" AND ", $where);
        }

        // AGRUPACIÓN NECESARIA
        $sql .= " GROUP BY id_periodos";

        // IMPORTANTE: LIMIT SIN PLACEHOLDER
        $sql .= " ORDER BY id_periodos ASC LIMIT $desde, $por_pagina";

        // DEBUG (por si vuelve a fallar)
        $stmt = $this->con->prepare($sql);

        if (!$stmt) {
            die("Error en prepare(): " . $this->con->error . "<br>SQL: $sql");
        }

        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }

        if (!$stmt->execute()) {
            die("Error en execute(): " . $stmt->error);
        }

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

    // Filtros
    switch ($filtro) {
        case 0: // Terminado
            $where[] = "CURDATE() > fecha_final";
            break;
        case 1: // Pendiente
            $where[] = "CURDATE() < fecha_inicio";
            break;
        case 2: // Activo
            $where[] = "CURDATE() BETWEEN fecha_inicio AND fecha_final";
            break;
        case 3: // Total
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
        $sql = "SELECT id_periodos, periodo, fecha_inicio, fecha_final,
        CASE 
        WHEN CURDATE() BETWEEN fecha_inicio AND fecha_final THEN 'Activo'
        WHEN CURDATE() < fecha_inicio THEN 'Pendiente'
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
        WHEN CURDATE() < fecha_inicio THEN 'Pendiente'
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
        $sql_sub = "INSERT INTO periodos (periodo, fecha_inicio, fecha_final, estado) VALUES (?, ?, ?, 1);";
        $stmt_sub = $this->con->prepare($sql_sub);
        $stmt_sub->bind_param("sss", $periodo, $fecha_inicio, $fecha_final);
        $stmt_sub->execute();
        return $stmt_sub->insert_id;
    }

    // ACTUALIZAR
    public function editarPeriodo($periodo, $fecha_inicio, $fecha_final, $id_periodos)
    {
        $sql = "UPDATE periodos
                SET periodo = ?, fecha_inicio = ?, fecha_final = ?
                WHERE id_periodos = ?";

        $stmt = $this->con->prepare($sql);
        $stmt->bind_param("issi", $periodo, $fecha_inicio, $fecha_final, $id_periodos);
        return $stmt->execute();
    }

    // ELIMINAR (SOFT DELETE)
    public function eliminar_periodo($id_periodo, $estado)
    {

        $sql = "UPDATE periodos SET estado = 0 WHERE id_periodos = ?";
        $stmt = $this->con->prepare($sql);
        $stmt->bind_param("ii", $estado, $id_periodo);
        $stmt->execute();
        return 0;
    }
    //Busca duplicidad de periodos
    public function comparar_Duplicidad_Subperiodo($id_peridos, $periodo)
    {
        $sql = "SELECT COUNT(*) as total
            FROM periodos
            WHERE id_periodos = ?
            AND LOWER(periodo) = LOWER(?)
            AND estado = 1";

        if (!empty($id_excluir)) {
            $sql .= " AND id_periodoss != ?";
        }

        $stmt = $this->con->prepare($sql);

        if (!empty($id_excluir)) {
            $stmt->bind_param("isi", $id_peridos, $periodo, $id_excluir);
        } else {
            $stmt->bind_param("is", $id_peridos, $periodo);
        }

        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();

        if ($result['total'] > 0) {
            throw new Exception("El periodo ya existe");
        }
    }
}
