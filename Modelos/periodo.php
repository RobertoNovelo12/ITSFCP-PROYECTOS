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
FROM periodos ORDER BY periodo DESC
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
                per.id_periodos,
                per.periodo,
                per.fecha_inicio AS inicio,
                area.fecha_final AS final,
                (CASE WHEN CURDATE() BETWEEN fecha_inicio AND fecha_final THEN 'Activo'
                CASE WHEN CURDATE() < fecha_inicio THEN 'Pendiente'
                CASE WHEN CURDATE() > fecha_final THEN 'Terminado' END) AS estado
            FROM periodo";

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
            $where[] = "fecha_inicio LIKE ?";
            $params[] = "%$buscar%";
            $types .= "s";
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
        if ($filtro == 2) {
            // Si el filtro es 2 (Total), no aplicamos ninguna condición adicional
            $sql = "SELECT COUNT(*) AS total FROM periodos as per WHERE 1";
            $params = [];
            $types  = "";
            // MEJORAR: SI buscar es fecha inicio, fecha final o periodo
            if (!empty($buscar)) {
                $sql .= " AND per.fecha_inicio LIKE ?";
                $params[] = "%$buscar%";
                $types   .= "s";
            }

            $stmt = $this->con->prepare($sql);


            if (!empty($params)) {
                $stmt->bind_param($types, ...$params);
            } else {
                // No hay parámetros para enlazar
            }
            $stmt->execute();
            $resultado = $stmt->get_result()->fetch_assoc();
            return $resultado['total'];   // OBTENER EL NUMERO TOTAL DE AREAS
        } else {

            $sql = "SELECT COUNT(*) AS total FROM periodos as per ";
            $params = [];
            $types = "";
            $where = [];

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

            if (!empty($buscar)) {
                $where[] = "fecha_inicio LIKE ?";
                $params[] = "%$buscar%";
                $types .= "s";
            }

            if (!empty($where)) {
                $sql .= " WHERE " . implode(" AND ", $where);
            }

            $stmt = $this->con->prepare($sql);
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $resultado = $stmt->get_result()->fetch_assoc();
        return $resultado['total'];   // OBTENER EL NUMERO TOTAL DE AREAS
    }


    // EDICIÓN
    public function obtenerPeriodoEditar($id_periodos)
    {
        $sql = "SELECT id_periodos, periodo, fecha_inicio, fecha_final,
        (CASE WHEN CURDATE() BETWEEN fecha_inicio AND fecha_final THEN 'Activo'
                CASE WHEN CURDATE() < fecha_inicio THEN 'Pendiente'
                CASE WHEN CURDATE() > fecha_final THEN 'Terminado' END) AS estado
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
        $sql = "SELECT id_periodos, periodo, fecha_inicio, fecha_final,
        (CASE WHEN CURDATE() BETWEEN fecha_inicio AND fecha_final THEN 'Activo'
                CASE WHEN CURDATE() < fecha_inicio THEN 'Pendiente'
                CASE WHEN CURDATE() > fecha_final THEN 'Terminado' END) AS estado
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

    // CREAR


    //Crea subareas
    public function registrarPeriodo($periodo, $fecha_inicio, $fecha_final)
    {
        $sql_sub = "INSERT INTO periodos (periodo, fecha_inicio, fecha_final) VALUES (?, ?, ?);";
        $stmt_sub = $this->con->prepare($sql_sub);
        $stmt_sub->bind_param("sss", $periodo, $fecha_inicio, $fecha_final);
        return $stmt_sub->execute();
    }

    // ACTUALIZAR
    public function editarArea($periodo, $fecha_inicio, $fecha_final, $id_periodos)
    {
        $sql = "UPDATE periodos
                SET periodo = ?, fecha_inicio = ?, fecha_final = ?
                WHERE id_periodos = ?";

        $stmt = $this->con->prepare($sql);
        $stmt->bind_param("issi", $periodo, $fecha_inicio, $fecha_final, $id_periodos);
        return $stmt->execute();
    }

    // ELIMINAR (SOFT DELETE)
    public function eliminar_area($id_area, $estado)
    {

        $sql = "UPDATE areas_conocimiento SET estado = ? WHERE id_area = ?";
        $stmt = $this->con->prepare($sql);
        $stmt->bind_param("ii", $estado, $id_area);
        $stmt->execute();

        $sql2 = "UPDATE subareas_conocimiento SET estado = ? WHERE id_area = ?";
        $stmt2 = $this->con->prepare($sql2);
        $stmt2->bind_param("ii", $estado, $id_area);
        $stmt2->execute();
        return 0;
    }
    //Busca duplicidad de subareas
    public function comparar_Duplicidad_Subperiodo($id_area, $nombre)
    {
        $sql = "SELECT COUNT(*) as total
            FROM periodos
            WHERE id_periodos = ?
            AND LOWER(periodo) = LOWER(?)
            AND estado = 1";

        if (!empty($id_excluir)) {
            $sql .= " AND id_subarea != ?";
        }

        $stmt = $this->con->prepare($sql);

        if (!empty($id_excluir)) {
            $stmt->bind_param("isi", $id_area, $nombre, $id_excluir);
        } else {
            $stmt->bind_param("is", $id_area, $nombre);
        }

        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();

        if ($result['total'] > 0) {
            throw new Exception("La Subarea ya existe en esta Área de conocimiento");
        }
    }
    //Obtener las ID de subáreas
    public function obtenerIdsSubareas($id_area)
    {
        $sql = "SELECT id_subarea FROM subareas_conocimiento WHERE id_area = ? AND estado = 1";
        $stmt = $this->con->prepare($sql);
        $stmt->bind_param("i", $id_area);
        $stmt->execute();
        $result = $stmt->get_result();
        $ids = [];
        while ($row = $result->fetch_assoc()) {
            $ids[] = $row['id_subarea'];
        }
        return $ids;
    }
}
