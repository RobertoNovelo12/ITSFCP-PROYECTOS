<?php
require_once __DIR__ . '/../publico/config/conexion.php';

class AreaConocimiento
{
    private $con;

    public function __construct($conn)
    {
        $this->con = $conn;
    }

    public function obtenerAreasDatosFiltro($rol)
    {
        switch ($rol) {
            case 'supervisor':
                $sql = "SELECT DISTINCT 
  COUNT(*) AS Total,
  SUM(CASE WHEN estado= 1 THEN 1 ELSE 0 END) AS Activo,
  SUM(CASE WHEN estado= 0 THEN 1 ELSE 0 END) AS Desactivado
FROM areas_conocimiento AS area;";
                $stmt = $this->con->prepare($sql);
                break;
            default:
                return []; // Retorna un array vacío si el rol no es válido
        }
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    // TABLA PRINCIPAL
    public function obtenerAreasTablaFiltro($buscar, $filtro)
    {
        $total = $this->obtenerCantidadArea($buscar, $filtro);

        $por_pagina = 6;
        $pagina = empty($_GET['pagina']) ? 1 : intval($_GET['pagina']);
        $desde = ($pagina - 1) * $por_pagina;
        $total_paginas = ($total > 0) ? ceil($total / $por_pagina) : 1;

        $params = [];
        $types = "";
        $where = [];

        $sql = "SELECT 
                area.id_area,
                area.nombre_area AS nombre,
                area.descripcion_area AS descripcion,
                area.fecha_creacion AS creacion,
                area.fecha_modificacion AS modificacion,
                (SELECT COUNT(*) FROM subareas_conocimiento as suba WHERE area.id_area = suba.id_area AND suba.estado = 1) AS total,
                (CASE 
            WHEN area.estado = 1 THEN 'Activo'
            ELSE 'Desactivado'
        END) AS estado
            FROM areas_conocimiento area
            LEFT JOIN subareas_conocimiento suba 
                ON suba.id_area = area.id_area";

        // Filtros
        if ($filtro == 0 || $filtro == 1) {
            $where[] = "area.estado = ?";
            $params[] = $filtro;
            $types .= "i";
        }

        if (!empty($buscar)) {
            $where[] = "area.nombre_area LIKE ?";
            $params[] = "%$buscar%";
            $types .= "s";
        }

        if (!empty($where)) {
            $sql .= " WHERE area.estodo = 1 " . implode(" AND ", $where);
        }

        // AGRUPACIÓN NECESARIA
        $sql .= " GROUP BY area.id_area";

        // IMPORTANTE: LIMIT SIN PLACEHOLDER
        $sql .= " ORDER BY area.id_area ASC LIMIT $desde, $por_pagina";

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
            "area" => $stmt->get_result()->fetch_all(MYSQLI_ASSOC),
            "paginacion" => [
                "total" => $total,
                "por_pagina" => $por_pagina,
                "pagina" => $pagina,
                "total_paginas" => $total_paginas
            ]
        ];
    }

    public function obtenerCantidadArea($buscar = null, $filtro = 2)
    {
        if ($filtro == 2) {
            // Si el filtro es 2 (Total), no aplicamos ninguna condición adicional
            $sql = "SELECT COUNT(*) AS total FROM areas_conocimiento as area WHERE 1";
            $params = [];
            $types  = "";

            if (!empty($buscar)) {
                $sql .= " AND area.nombre_area LIKE ?";
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

            $sql = "SELECT COUNT(*) AS total FROM areas_conocimiento as area 
WHERE area.estado = ?";

            $params = [$filtro];
            $types  = "i";

            if (!empty($buscar)) {
                $sql .= " AND area.nombre_area LIKE ?";
                $params[] = "%$buscar%";
                $types   .= "s";
            }

            $stmt = $this->con->prepare($sql);
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $resultado = $stmt->get_result()->fetch_assoc();
        return $resultado['total'];   // OBTENER EL NUMERO TOTAL DE AREAS
    }


    // EDICIÓN
    public function obtenerAreaEditar($id_area)
    {
        $sql = "SELECT id_area, nombre_area AS nombre, descripcion_area AS descripcion, 
        (CASE 
            WHEN estado = 1 THEN 'Activo'
            ELSE 'Desactivado'
        END) AS estado
                FROM areas_conocimiento
                WHERE id_area = ?";

        $stmt = $this->con->prepare($sql);
        $stmt->bind_param("i", $id_area);
        $stmt->execute();

        $area = $stmt->get_result()->fetch_assoc();

        if (!$area) {
            throw new Exception("Área no encontrada");
        }

        $sql2 = "SELECT id_subarea, nombre_subarea AS nombre, estado
                 FROM subareas_conocimiento
                 WHERE id_area = ? AND estado = 1";

        $stmt2 = $this->con->prepare($sql2);
        $stmt2->bind_param("i", $id_area);
        $stmt2->execute();

        return [
            "area" => $area,
            "subareas" => $stmt2->get_result()->fetch_all(MYSQLI_ASSOC)
        ];
    }

    //Obtener datos para detalles
    public function obtenerAreasDetalles($id_area)
    {
        // AREA
        $sql = "SELECT 
                id_area, 
                nombre_area AS nombre,
                descripcion_area AS descripcion,
                (CASE 
                    WHEN estado = 1 THEN 'Activo'
                    ELSE 'Desactivado'
                END) AS estado
            FROM areas_conocimiento
            WHERE id_area = ?";

        $stmt = $this->con->prepare($sql);

        if (!$stmt) {
            die("Error SQL: " . $this->con->error);
        }

        $stmt->bind_param("i", $id_area);
        $stmt->execute();

        $result = $stmt->get_result();
        $area = $result->fetch_assoc();

        $stmt->close();

        // SUBAREAS
        $sql2 = "SELECT 
                suba.id_subarea,
                suba.nombre_subarea AS nombre,
                suba.estado
            FROM subareas_conocimiento AS suba
            WHERE suba.id_area = ?";

        $stmt2 = $this->con->prepare($sql2);

        if (!$stmt2) {
            die("Error SQL (subareas): " . $this->con->error);
        }

        $stmt2->bind_param("i", $id_area);
        $stmt2->execute();

        $result2 = $stmt2->get_result();
        $subareas = $result2->fetch_all(MYSQLI_ASSOC);

        $stmt2->close();

        return [
            "area" => $area,
            "subareas" => $subareas
        ];
    }

    // CREAR
    public function crearAreaCompleta($nombre, $descripcion, $subareas)
    {
        $this->con->begin_transaction();

        try {
            $sql = "INSERT INTO areas_conocimiento (nombre_area, descripcion_area, estado)
                    VALUES (?, ?, 1)";
            $stmt = $this->con->prepare($sql);
            $stmt->bind_param("ss", $nombre, $descripcion);
            $stmt->execute();

            $id_area = $stmt->insert_id;

            foreach ($subareas as $sub) {
                $sql2 = "INSERT INTO subareas_conocimiento (id_area, nombre_subarea, estado)
                         VALUES (?, ?, 1)";
                $stmt2 = $this->con->prepare($sql2);
                $stmt2->bind_param("is", $id_area, $sub);
                $stmt2->execute();
            }

            $this->con->commit();
            return $id_area;
        } catch (Exception $e) {
            $this->con->rollback();
            return false;
        }
    }

    //Crea subareas
    public function registrarsubarea($id_area, $nombre_subarea)
    {
        $sql_sub = "INSERT INTO subareas_conocimiento (id_area, nombre_subarea) VALUES (?, ?);";
        $stmt_sub = $this->con->prepare($sql_sub);
        $stmt_sub->bind_param("is", $id_area, $nombre_subarea);
        return $stmt_sub->execute();
    }

    // ACTUALIZAR
    public function editarArea($nombre, $descripcion, $id_area)
    {
        $sql = "UPDATE areas_conocimiento
                SET nombre_area = ?, descripcion_area = ?, fecha_modificacion = NOW()
                WHERE id_area = ?";

        $stmt = $this->con->prepare($sql);
        $stmt->bind_param("ssi", $nombre, $descripcion, $id_area);
        return $stmt->execute();
    }

    public function editarSubarea($id, $nombre)
    {
        $sql = "UPDATE subareas_conocimiento
                SET nombre_subarea = ?, fecha_modificacion = NOW()
                WHERE id_subarea = ?";

        $stmt = $this->con->prepare($sql);
        $stmt->bind_param("si", $nombre, $id);
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

    public function eliminar_subarea($id_subarea, $estado)
    {
        $sql = "UPDATE subareas_conocimiento 
                SET estado = ?, fecha_modificacion = NOW()
                WHERE id_subarea = ?";

        $stmt = $this->con->prepare($sql);
        $stmt->bind_param("ii", $estado, $id_subarea);
        return $stmt->execute();
    }
    //Busca duplicidad de subareas
        public function comparar_Duplicidad_Subareas($id_area, $nombre, $id_excluir = null)
    {
        $sql = "SELECT COUNT(*) as total
            FROM subareas_conocimiento
            WHERE id_area = ?
            AND LOWER(nombre_subarea) = LOWER(?)
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
