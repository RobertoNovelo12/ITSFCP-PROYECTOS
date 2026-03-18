<?php
require_once __DIR__ . '/../publico/config/conexion.php';

class Tematica
{
    private $con;

    public function __construct($conn)
    {
        $this->con = $conn;
    }

    //DATOS PRINCIPAL
    public function obtenerTematicas($rol, $buscar = null)
    {
        // Normalizar rol para evitar problemas de mayúsculas/minúsculas
        $rol = strtolower($rol);

        // Cantidad totales
        $total = $this->obtenerCantidadTematica(0, $rol, $buscar);

        // Parámetros de paginación
        $por_pagina = 6;
        $pagina = empty($_GET['pagina']) ? 1 : intval($_GET['pagina']);
        $desde = ($pagina - 1) * $por_pagina;

        $total_paginas = ($total > 0) ? ceil($total / $por_pagina) : 1;

        // Inicializar variables
        $sql = "";
        $params = [];
        $types = "";
        $whereAdded = false;

        // Consultas base según rol
        switch ($rol) {
            case 'supervisor':
                $sql = "SELECT DISTINCT tema.id_tematica, tema.nombre_tematica as tematica, tema.descripcion_tematica as descripcion, tema.fecha_creacion AS creacion, tema.fecha_modificacion AS modificacion,
	(SELECT COUNT(*) 
        FROM subtematica as subt
        WHERE tema.id_tematica = subt.id_tematica AND subt.estado=1) AS total,
        (CASE 
            WHEN tema.estado = 1 THEN 'Activo'
            ELSE 'Desactivado'
        END) AS estado
        FROM tematica as tema
INNER JOIN subtematica as subt ON tema.id_tematica = subt.id_tematica";
                // supervisor no añade WHERE por defecto
                $whereAdded = false;
                break;

            default:
                // Si el rol es inesperado devolvemos vacío (evita errores posteriores)
                return json_encode([
                    "tematica" => [],
                    "paginacion" => [
                        "total" => 0,
                        "por_pagina"      => $por_pagina,
                        "pagina"          => $pagina,
                        "total_paginas"   => 1
                    ]
                ]);
                break;
        }

        // Filtro de búsqueda (si aplica)
        if (!empty($buscar)) {
            if ($whereAdded) {
                $sql .= " AND tema.nombre_tematica LIKE ? ";
            } else {
                $sql .= " WHERE tema.nombre_tematica LIKE ? ";
                $whereAdded = true;
            }
            $params[] = "%$buscar%";
            $types .= "s";
        }

        // GROUP BY y LIMIT al final (LIMIT siempre al final de la query)
        $sql .= " GROUP BY tema.id_tematica ORDER BY tema.id_tematica ASC LIMIT ?, ?";

        // Añadir params para paginación (siempre enteros)
        $params[] = $desde;
        $params[] = $por_pagina;
        $types .= "ii";

        // Preparar y ejecutar
        $stmt = $this->con->prepare($sql);
        if (!$stmt) {
            die("Error en prepare(): " . $this->con->error . "<br>SQL: $sql");
        }

        // bind_param requiere tipos y valores; si types está vacío no bindear
        if ($types !== "") {
            // Usar operador splat para pasar los parámetros
            $stmt->bind_param($types, ...$params);
        }

        if (!$stmt->execute()) {
            die("Error en execute(): " . $stmt->error . "<br>SQL: $sql");
        }

        $resultado = [
            "tematica" => $stmt->get_result()->fetch_all(MYSQLI_ASSOC),
            "paginacion" => [
                "total" => $total,
                "por_pagina"      => $por_pagina,
                "pagina"          => $pagina,
                "total_paginas"   => $total_paginas
            ]
        ];

        return json_encode($resultado);
    }

    //DATOS TEMATICA Y SUBTEMATICA PARA EDITAR
    public function obtenerTematicasEditar($id_tematica)
    {

        $sql = "SELECT DISTINCT id_tematica, nombre_tematica as nombre, descripcion_tematica as descripcion, 
        (CASE 
            WHEN estado = 1 THEN 'Activo'
            ELSE 'Desactivado'
        END) AS estado
        FROM tematica WHERE id_tematica = ?;";
        // Preparar y ejecutar
        $stmt = $this->con->prepare($sql);

        if (!$stmt) {
            die("Error en prepare(): " . $this->con->error . "<br>SQL: $sql");
        }

        $stmt->bind_param("i", $id_tematica);

        if (!$stmt->execute()) {
            die("Error en execute(): " . $stmt->error . "<br>SQL: $sql");
        }

        $resultTematica = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $sql2 = "SELECT DISTINCT 
    subt.id_subtematica as id, 
    subt.nombre_subtematica AS nombre, subt.estado
FROM tematica AS tema
INNER JOIN subtematica AS subt 
    ON tema.id_tematica = subt.id_tematica
WHERE tema.id_tematica = ? AND subt.estado=1;";
        // Preparar y ejecutar
        $stmt2 = $this->con->prepare($sql2);
        if (!$stmt2) {
            die("Error en prepare(): " . $this->con->error . "<br>SQL: $sql2");
        }

        $stmt2->bind_param("i", $id_tematica);

        if (!$stmt2->execute()) {
            die("Error en execute(): " . $stmt2->error . "<br>SQL: $sql2");
        }

        $resultSubtematica = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt2->close();

        $resultado = [
            "tematica" => $resultTematica,
            "subtematicas" => $resultSubtematica
        ];

        return $resultado;
    }
    //Detalles de temática con sus subtematicas
    public function obtenerTematicasDetalles($id_tematica)
    {

        $sql = "SELECT DISTINCT tema.id_tematica, tema.nombre_tematica as nombre, tema.descripcion_tematica as descripcion, 
        (CASE 
            WHEN tema.estado = 1 THEN 'Activo'
            ELSE 'Desactivado'
        END) AS estado
        FROM tematica as tema
WHERE tema.id_tematica = ?;";
        // Preparar y ejecutar
        $stmt = $this->con->prepare($sql);

        if (!$stmt) {
            die("Error en prepare(): " . $this->con->error . "<br>SQL: $sql");
        }

        $stmt->bind_param("i", $id_tematica);

        if (!$stmt->execute()) {
            die("Error en execute(): " . $stmt->error . "<br>SQL: $sql");
        }

        $resultTematica = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $sql2 = "SELECT DISTINCT 
    subt.id_subtematica as id, 
    subt.nombre_subtematica AS nombre, subt.estado
FROM tematica AS tema
INNER JOIN subtematica AS subt 
    ON tema.id_tematica = subt.id_tematica
WHERE tema.id_tematica = ?;";
        // Preparar y ejecutar
        $stmt2 = $this->con->prepare($sql2);
        if (!$stmt2) {
            die("Error en prepare(): " . $this->con->error . "<br>SQL: $sql2");
        }

        $stmt2->bind_param("i", $id_tematica);

        if (!$stmt2->execute()) {
            die("Error en execute(): " . $stmt2->error . "<br>SQL: $sql2");
        }

        $resultSubtematica = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt2->close();

        $resultado = [
            "tematica" => $resultTematica,
            "subtematicas" => $resultSubtematica
        ];

        return $resultado;
    }

    //DATOS FILTRADOS SEGUN SELECCION
    public function obtenerTematicasTablaFiltro($filtro, $rol, $buscar = null)
    {
        // --- Paginación ---
        $total = $this->obtenerCantidadTematica($filtro, $rol, $buscar);
        $por_pagina = 6;
        $pagina = empty($_GET['pagina']) ? 1 : intval($_GET['pagina']);
        $desde = ($pagina - 1) * $por_pagina;
        $total_paginas = ($total > 0) ? ceil($total / $por_pagina) : 1;

        // --- SQL BASE POR ROL ---
        $base = "";
        $params = [];
        $types = "";
        $where = [];

        switch ($rol) {
            case 'supervisor':
                $base = "SELECT 
                area.id_tematica,
                area.nombre_tematica AS nombre,
                area.descripcion_tematica AS descripcion,
                area.fecha_creacion AS creacion,
                area.fecha_modificacion AS modificacion,
                (SELECT COUNT(*) FROM subtematicas as subt WHERE subt.id_tematica = area.id.tematica AND subt.estado = 1) AS total,
                (CASE 
            WHEN tema.estado = 1 THEN 'Activo'
            ELSE 'Desactivado'
        END) AS estado
            FROM tematica AS tema
            LEFT JOIN tema subt 
                ON subt.id_tematica = tema.id_tematica";
                break;

            default:
                return json_encode([
                    "tematica" => [],
                    "paginacion" => []
                ]);
        }

        // --- Filtro por estado ---
        if ($filtro == 0 || $filtro == 1) { // 2 significa "Total", no filtrar
            $where[] = "tema.estado = ? ";
            $params[] = $filtro;
            $types   .= "i";
        }

        // --- Filtro de búsqueda ---
        if (!empty($buscar)) {
            $where[] = "tema.nombre_tematica LIKE ?";
            $params[] = "%$buscar%";
            $types   .= "s";
        }

        // --- WHERE dinámico ---
        $sql = $base;
        if (count($where) > 0) {
            $sql .= " WHERE " . implode(" AND ", $where);
        }

        // --- Agrupación, orden y límite ---
        $sql .= "
        GROUP BY tema.id_tematica
        ORDER BY tema.id_tematica ASC
        LIMIT ?, ?
    ";

        $params[] = $desde;
        $params[] = $por_pagina;
        $types   .= "ii";

        // --- Ejecutar consulta ---
        $stmt = $this->con->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $filas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        // --- Respuesta final ---
        return json_encode([
            "tematica" => $filas,
            "paginacion" => [
                "total" => $total,
                "por_pagina"      => $por_pagina,
                "pagina"          => $pagina,
                "total_paginas"   => $total_paginas
            ]
        ]);
    }

    //OBTENER LA CANTIDAD DE PROYECTOS
    public function obtenerCantidadTematica($numerofiltro, $rol, $buscar = null)
    {

        if ($numerofiltro == 0) {
            // Si el filtro es 0 (Total), no aplicamos ninguna condición adicional
            switch ($rol) {
                case 'supervisor':
                    $sql = "SELECT COUNT(*) AS total FROM gestion_proyectos.tematica as tema WHERE 1";
                    $params = [];
                    $types  = "";

                    if (!empty($buscar)) {
                        $sql .= " AND tema.nombre_tematica LIKE ?";
                        $params[] = "%$buscar%";
                        $types   .= "s";
                    }

                    $stmt = $this->con->prepare($sql);

                    break;
                default:
                    break; // Retorna 0 si el rol no es válido
            }

            if (!empty($params)) {
                $stmt->bind_param($types, ...$params);
            } else {
                // No hay parámetros para enlazar
            }
            $stmt->execute();
            $resultado = $stmt->get_result()->fetch_assoc();
            return $resultado['total'];   // OBTENER EL NUMERO TOTAL DE TEMATICAS
        } else {
            switch ($rol) {
                case 'supervisor':
                    $sql = "SELECT COUNT(*) AS total FROM gestion_proyectos.tematica as tema 
WHERE tema.estado = ?";

                    $params = [$numerofiltro];
                    $types  = "i";

                    if (!empty($buscar)) {
                        $sql .= " AND tema.nombre_tematica LIKE ?";
                        $params[] = "%$buscar%";
                        $types   .= "s";
                    }

                    $stmt = $this->con->prepare($sql);
                    $stmt->bind_param($types, ...$params);
                    break;
                default:
                    break; // Retorna 0 si el rol no es válido
            }
            $stmt->execute();
            $resultado = $stmt->get_result()->fetch_assoc();
            return $resultado['total'];   // OBTENER EL NUMERO TOTAL DE TEMATICAS
        }
    }

    //FILTROS DATOS GENERAL
    //DATOS DEL FILTRO
    public function obtenerTematicasDatosFiltro($rol)
    {
        switch ($rol) {
            case 'supervisor':
                $sql = "SELECT DISTINCT 
  COUNT(*) AS Total,
  SUM(CASE WHEN estado= 1 THEN 1 ELSE 0 END) AS Activo,
  SUM(CASE WHEN estado= 0 THEN 1 ELSE 0 END) AS Desactivado
FROM gestion_proyectos.tematica AS tema;";
                $stmt = $this->con->prepare($sql);
                break;
            default:
                return []; // Retorna un array vacío si el rol no es válido
        }
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function registrarTematica($nombre, $descripcion)
    {
        $sql = "INSERT INTO tematica (nombre_tematica, descripcion_tematica, estado) VALUES (?, ?, 1);";
        $stmt = $this->con->prepare($sql);
        $stmt->bind_param("ss", $nombre, $descripcion);
        $stmt->execute();

        return $stmt->insert_id;
    }

    public function registrarsubtematica($id_tematica, $nombre_subtematica)
    {
        $sql_sub = "INSERT INTO subtematica (id_tematica, nombre_subtematica) VALUES (?, ?);";
        $stmt_sub = $this->con->prepare($sql_sub);
        $stmt_sub->bind_param("is", $id_tematica, $nombre_subtematica);
        return $stmt_sub->execute();
    }

    public function obtenerIdsSubtematicas($id_tematica)
    {
        $sql = "SELECT id_subtematica FROM subtematica WHERE id_tematica = ? AND estado = 1";
        $stmt = $this->con->prepare($sql);
        $stmt->bind_param("i", $id_tematica);
        $stmt->execute();

        $result = $stmt->get_result();

        $ids = [];

        while ($row = $result->fetch_assoc()) {
            $ids[] = $row['id_subtematica'];
        }

        return $ids;
    }

    public function editarTematica($nombre, $descripcion, $id_tematica)
    {
        $sql = "UPDATE tematica 
            SET nombre_tematica = ?, descripcion_tematica = ?, fecha_modificacion = NOW() 
            WHERE id_tematica = ?";

        $stmt = $this->con->prepare($sql);
        $stmt->bind_param("ssi", $nombre, $descripcion, $id_tematica);

        if ($stmt->execute()) {
            return true;
        }

        return false;
    }

    public function editarSubtematica($id_subtematica, $nombre_subtematica)
    {
        $sql_sub = "UPDATE subtematica SET nombre_subtematica = ?, fecha_modificacion = NOW()  WHERE id_subtematica = ?;";
        $stmt_sub = $this->con->prepare($sql_sub);
        $stmt_sub->bind_param("si", $nombre_subtematica, $id_subtematica);
        return $stmt_sub->execute();
    }

    public function eliminar_tematica($id_tematica, $estado)
    {
        $this->con->begin_transaction();

        try {

            $sql = "UPDATE tematica 
                SET estado = ?, fecha_modificacion = NOW() 
                WHERE id_tematica = ?";

            $stmt = $this->con->prepare($sql);
            $stmt->bind_param("ii", $estado, $id_tematica);
            $stmt->execute();

            $sql2 = "UPDATE subtematica 
                 SET estado = ?, fecha_modificacion = NOW() 
                 WHERE id_tematica = ?";

            $stmt2 = $this->con->prepare($sql2);
            $stmt2->bind_param("ii", $estado, $id_tematica);
            $stmt2->execute();

            $this->con->commit();

            return true;
        } catch (Exception $e) {

            $this->con->rollback();

            return false;
        }
    }

    public function eliminar_subtematica($id_subtematica, $estado)
    {
        $sql = "UPDATE subtematica SET estado = ?, fecha_modificacion = NOW()  WHERE id_subtematica = ?;";
        $stmt = $this->con->prepare($sql);
        $stmt->bind_param("ii", $estado, $id_subtematica);

        return $stmt->execute();
    }

    public function comparar_Duplicidad_Subtematica($id_tematica, $nombre, $id_excluir = null)
    {

        $sql = "SELECT COUNT(*) as total
FROM subtematica
WHERE id_tematica = ?
AND nombre_subtematica = ?
AND estado = 1";

        if ($id_excluir) {
            $sql .= " AND id_subtematica != ?";
        }

        $stmt = $this->con->prepare($sql);

        if ($id_excluir) {

            $stmt->bind_param("isi", $id_tematica, $nombre, $id_excluir);
        } else {

            $stmt->bind_param("is", $id_tematica, $nombre);
        }

        $stmt->execute();

        $result = $stmt->get_result()->fetch_assoc();

        if ($result['total'] > 0) {

            throw new Exception("La subtemática ya existe en esta temática");
        }
    }
}
