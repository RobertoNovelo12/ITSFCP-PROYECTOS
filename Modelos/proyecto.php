<?php
require_once __DIR__ . '/../publico/config/conexion.php';

class Proyectos
{

    private $con;

    public function __construct($conn)
    {
        $this->con = $conn;
    }

    //ACTUALIZAR A VENCIDO LOS PROYECTOS
    public function actualizarProyectosVencidos()
    {
        $hoy = date("Y-m-d");

        $sql = "UPDATE proyectos 
            SET id_estadoP = 6
            WHERE id_estadoP IN (2, 5, 7)
              AND fecha_fin < ?";

        $stmt = $this->con->prepare($sql);
        $stmt->bind_param("s", $hoy);
        $stmt->execute();
    }

    //DATOS GENERALES SIN FILTRO
    public function obtenerProyectos($id, $rol, $numerofiltro, $buscar = null)
    {
        // Normalizar rol para evitar problemas de mayúsculas/minúsculas
        $rol = strtolower($rol);

        // Cantidad totales
        $total_proyectos = $this->obtenerCantidadProyectos($id, $numerofiltro, $rol, $buscar);

        // Parámetros de paginación
        $por_pagina = 6;
        $pagina = empty($_GET['pagina']) ? 1 : intval($_GET['pagina']);
        $desde = ($pagina - 1) * $por_pagina;

        $total_paginas = ($total_proyectos > 0) ? ceil($total_proyectos / $por_pagina) : 1;

        // Inicializar variables
        $sql = "";
        $params = [];
        $types = "";
        $whereAdded = false;

        // Consultas base según rol
        switch ($rol) {
            case 'estudiante':
            case 'alumno':
                $sql = "SELECT proy.id_proyectos, proy.titulo, proy.fecha_inicio, proy.fecha_fin, 
                               espr.nombre, peri.periodo, 
                               COUNT(CASE WHEN taus.id_estadoT = 1 THEN 1 END) AS total
                        FROM gestion_proyectos.proyectos AS proy
                        JOIN proyectos_usuarios AS prus ON proy.id_proyectos = prus.id_proyectos
                        JOIN estudiantes AS estu ON estu.id_usuario = prus.id_usuarios
                        JOIN estados_proyectos AS espr ON proy.id_estadoP = espr.id_estadoP
                        JOIN periodos AS peri ON proy.id_periodos = peri.id_periodos
                        LEFT JOIN tbl_seguimiento AS tbse ON tbse.id_proyectos = proy.id_proyectos
                        LEFT JOIN tareas AS tare ON tare.id_avances = tbse.id_avances
                        LEFT JOIN tareas_usuarios AS taus ON taus.id_tarea = tare.id_tarea 
                            AND taus.id_usuario = estu.id_usuario 
                            AND taus.id_estadoT = 1
                        WHERE estu.id_usuario = ? ";
                $params[] = $id;
                $types .= "i";
                $whereAdded = true;
                break;

            case 'investigador':
            case 'profesor':
                $sql = "SELECT proy.id_proyectos, proy.titulo, proy.fecha_inicio, proy.fecha_fin, 
                               espr.nombre, peri.periodo,
                               COUNT(CASE WHEN taus.id_estadoT = 2 THEN 1 END) AS total
                        FROM gestion_proyectos.proyectos AS proy
                        JOIN investigadores AS inv ON inv.id_usuario = proy.id_investigador
                        JOIN estados_proyectos AS espr ON proy.id_estadoP = espr.id_estadoP
                        JOIN periodos AS peri ON proy.id_periodos = peri.id_periodos
                        LEFT JOIN tbl_seguimiento AS tbse ON tbse.id_proyectos = proy.id_proyectos
                        LEFT JOIN tareas AS tare ON tare.id_avances = tbse.id_avances
                        LEFT JOIN tareas_usuarios AS taus ON taus.id_tarea = tare.id_tarea 
                            AND taus.id_estadoT = 2
                        WHERE proy.id_investigador = ? ";
                $params[] = $id;
                $types .= "i";
                $whereAdded = true;
                break;

            case 'supervisor':
                $sql = "SELECT proy.id_proyectos, proy.titulo, proy.fecha_inicio, proy.fecha_fin, 
                               espr.nombre, peri.periodo
                        FROM gestion_proyectos.proyectos AS proy
                        JOIN estados_proyectos AS espr ON proy.id_estadoP = espr.id_estadoP
                        JOIN periodos AS peri ON proy.id_periodos = peri.id_periodos ";
                $whereAdded = false;
                break;

            default:
                return json_encode([
                    "proyectos" => [],
                    "paginacion" => [
                        "total_proyectos" => 0,
                        "por_pagina" => $por_pagina,
                        "pagina" => $pagina,
                        "total_paginas" => 1
                    ]
                ]);
        }

        // Filtro de búsqueda (si aplica)
        if (!empty($buscar)) {
            if ($whereAdded) {
                $sql .= " AND proy.titulo LIKE ? ";
            } else {
                $sql .= " WHERE proy.titulo LIKE ? ";
                $whereAdded = true;
            }
            $params[] = "%$buscar%";
            $types .= "s";
        }

        // GROUP BY y LIMIT al final
        $sql .= " GROUP BY proy.id_proyectos ORDER BY proy.id_proyectos ASC LIMIT ?, ?";

        $params[] = $desde;
        $params[] = $por_pagina;
        $types .= "ii";

        // Preparar y ejecutar
        $stmt = $this->con->prepare($sql);
        if (!$stmt) {
            die("Error en prepare(): " . $this->con->error . "<br>SQL: $sql");
        }

        if ($types !== "") {
            $stmt->bind_param($types, ...$params);
        }

        if (!$stmt->execute()) {
            die("Error en execute(): " . $stmt->error . "<br>SQL: $sql");
        }

        $resultado = [
            "proyectos" => $stmt->get_result()->fetch_all(MYSQLI_ASSOC),
            "paginacion" => [
                "total_proyectos" => $total_proyectos,
                "por_pagina" => $por_pagina,
                "pagina" => $pagina,
                "total_paginas" => $total_paginas
            ]
        ];

        return json_encode($resultado);
    }


    //DATOS DEL FILTRO
    public function obtenerProyectosDatosFiltro($id, $rol)
    {
        switch ($rol) {
            case 'estudiante':
                $sql = "SELECT 
  COUNT(*) AS Total,
  SUM(CASE WHEN espr.nombre='Activo' THEN 1 ELSE 0 END) AS Activos,
  SUM(CASE WHEN espr.nombre='Por aprobar' THEN 1 ELSE 0 END) AS PorAprobar,
  SUM(CASE WHEN espr.nombre='Cierre' THEN 1 ELSE 0 END) AS Cierre,
  SUM(CASE WHEN espr.nombre='Por cerrar' THEN 1 ELSE 0 END) AS PorCerrar,
  SUM(CASE WHEN espr.nombre='Vencido' THEN 1 ELSE 0 END) AS Vencido
FROM gestion_proyectos.proyectos AS proy
JOIN proyectos_usuarios AS prus ON proy.id_proyectos = prus.id_proyectos
JOIN estudiantes AS estu ON prus.id_usuarios = estu.id_usuario
JOIN estados_proyectos AS espr ON proy.id_estadoP = espr.id_estadoP
WHERE estu.id_usuario = ?;";

                $stmt = $this->con->prepare($sql);
                $stmt->bind_param("i", $id);
                break;
            case 'investigador':
            case 'profesor':
                $sql = "SELECT 
  COUNT(*) AS Total,
  SUM(CASE WHEN espr.nombre='Activo' THEN 1 ELSE 0 END) AS Activos,
  SUM(CASE WHEN espr.nombre='Por aprobar' THEN 1 ELSE 0 END) AS PorAprobar,
  SUM(CASE WHEN espr.nombre='Cierre' THEN 1 ELSE 0 END) AS Cierre,
  SUM(CASE WHEN espr.nombre='Por cerrar' THEN 1 ELSE 0 END) AS PorCerrar,
  SUM(CASE WHEN espr.nombre='Rechazado' THEN 1 ELSE 0 END) AS Rechazados,
  SUM(CASE WHEN espr.nombre='Vencido' THEN 1 ELSE 0 END) AS Vencido,
  SUM(CASE WHEN espr.nombre='Cierre rechazado' THEN 1 ELSE 0 END) AS Cierrerechazado
FROM gestion_proyectos.proyectos AS proy
JOIN investigadores AS inv ON inv.id_usuario = proy.id_investigador
JOIN estados_proyectos AS espr ON proy.id_estadoP = espr.id_estadoP
WHERE proy.id_investigador = ?;";

                $stmt = $this->con->prepare($sql);
                $stmt->bind_param("i", $id);
                break;
            case 'supervisor':
                $sql = "SELECT 
  COUNT(*) AS Total,
  SUM(CASE WHEN espr.nombre='Activo' THEN 1 ELSE 0 END) AS Activos,
  SUM(CASE WHEN espr.nombre='Por aprobar' THEN 1 ELSE 0 END) AS PorAprobar,
  SUM(CASE WHEN espr.nombre='Cierre' THEN 1 ELSE 0 END) AS Cierre,
  SUM(CASE WHEN espr.nombre='Por cerrar' THEN 1 ELSE 0 END) AS PorCerrar,
  SUM(CASE WHEN espr.nombre='Rechazado' THEN 1 ELSE 0 END) AS Rechazados, 
  SUM(CASE WHEN espr.nombre='Vencido' THEN 1 ELSE 0 END) AS Vencido,
  SUM(CASE WHEN espr.nombre='Cierre rechazado' THEN 1 ELSE 0 END) AS Cierrerechazado
FROM gestion_proyectos.proyectos AS proy
JOIN estados_proyectos AS espr ON proy.id_estadoP = espr.id_estadoP;";

                $stmt = $this->con->prepare($sql);
                break;
            default:
                return [];
        }
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    //DATOS FILTRADOS SEGUN SELECCION
    public function obtenerProyectosTablaFiltro($id, $filtro, $rol, $buscar = null)
    {
        $total_proyectos = $this->obtenerCantidadProyectos($id, $filtro, $rol, $buscar);

        $por_pagina = 6;
        $pagina = empty($_GET['pagina']) ? 1 : intval($_GET['pagina']);
        $desde = ($pagina - 1) * $por_pagina;

        $total_paginas = ($total_proyectos > 0) ? ceil($total_proyectos / $por_pagina) : 1;

        if ($filtro == 0) {
            switch ($rol) {
                case 'estudiante':
                    $sql = "SELECT proy.id_proyectos, proy.titulo, proy.fecha_inicio, proy.fecha_fin, 
                                   espr.nombre, peri.periodo, 
                                   COUNT(CASE WHEN taus.id_estadoT = 1 THEN 1 END) AS total 
                            FROM gestion_proyectos.proyectos as proy 
                            JOIN proyectos_usuarios as prus ON proy.id_proyectos = prus.id_proyectos
                            JOIN estudiantes as estu ON prus.id_usuarios = estu.id_usuario
                            JOIN estados_proyectos as espr ON proy.id_estadoP = espr.id_estadoP 
                            JOIN periodos as peri ON proy.id_periodos = peri.id_periodos
                            LEFT JOIN tbl_seguimiento AS tbse ON tbse.id_proyectos = proy.id_proyectos
                            LEFT JOIN tareas AS tare ON tare.id_avances = tbse.id_avances
                            LEFT JOIN tareas_usuarios AS taus ON taus.id_tarea = tare.id_tarea 
                                AND taus.id_usuario = estu.id_usuario 
                                AND taus.id_estadoT = 1
                            WHERE estu.id_usuario = ?";

                    $params = [$id];
                    $types = "i";

                    if (!empty($buscar)) {
                        $sql .= " AND proy.titulo LIKE ?";
                        $params[] = "%$buscar%";
                        $types .= "s";
                    }

                    $sql .= " GROUP BY proy.id_proyectos ORDER BY proy.id_proyectos ASC LIMIT ?, ?";
                    $params[] = $desde;
                    $params[] = $por_pagina;
                    $types .= "ii";

                    $stmt = $this->con->prepare($sql);
                    $stmt->bind_param($types, ...$params);
                    break;

                case 'investigador':
                case 'profesor':
                    $sql = "SELECT proy.id_proyectos, proy.titulo, proy.fecha_inicio, proy.fecha_fin, 
                                   espr.nombre, peri.periodo, 
                                   COUNT(CASE WHEN taus.id_estadoT = 2 THEN 1 END) AS total 
                            FROM gestion_proyectos.proyectos as proy 
                            JOIN investigadores as inv ON inv.id_usuario = proy.id_investigador
                            JOIN estados_proyectos as espr ON proy.id_estadoP = espr.id_estadoP 
                            JOIN periodos as peri ON proy.id_periodos = peri.id_periodos
                            LEFT JOIN tbl_seguimiento AS tbse ON tbse.id_proyectos = proy.id_proyectos
                            LEFT JOIN tareas AS tare ON tare.id_avances = tbse.id_avances
                            LEFT JOIN tareas_usuarios AS taus ON taus.id_tarea = tare.id_tarea 
                                AND taus.id_estadoT = 2
                            WHERE proy.id_investigador = ?";

                    $params = [$id];
                    $types = "i";

                    if (!empty($buscar)) {
                        $sql .= " AND proy.titulo LIKE ?";
                        $params[] = "%$buscar%";
                        $types .= "s";
                    }

                    $sql .= " GROUP BY proy.id_proyectos ORDER BY proy.id_proyectos ASC LIMIT ?, ?";
                    $params[] = $desde;
                    $params[] = $por_pagina;
                    $types .= "ii";

                    $stmt = $this->con->prepare($sql);
                    $stmt->bind_param($types, ...$params);
                    break;

                case 'supervisor':
                    $sql = "SELECT proy.id_proyectos, proy.titulo, proy.fecha_inicio, proy.fecha_fin, 
                                   espr.nombre, peri.periodo 
                            FROM gestion_proyectos.proyectos as proy 
                            JOIN estados_proyectos as espr ON proy.id_estadoP = espr.id_estadoP 
                            JOIN periodos as peri ON proy.id_periodos = peri.id_periodos
                            WHERE 1";

                    $params = [];
                    $types = "";

                    if (!empty($buscar)) {
                        $sql .= " AND proy.titulo LIKE ?";
                        $params[] = "%$buscar%";
                        $types .= "s";
                    }

                    $sql .= " GROUP BY proy.id_proyectos ORDER BY proy.id_proyectos ASC LIMIT ?, ?";
                    $params[] = $desde;
                    $params[] = $por_pagina;
                    $types .= "ii";

                    $stmt = $this->con->prepare($sql);
                    if (!empty($params)) {
                        $stmt->bind_param($types, ...$params);
                    }
                    break;

                default:
                    return json_encode([
                        "proyectos" => [],
                        "paginacion" => [
                            "total_proyectos" => 0,
                            "por_pagina" => $por_pagina,
                            "pagina" => $pagina,
                            "total_paginas" => 1
                        ]
                    ]);
            }

            $stmt->execute();
            $resultado = [
                "proyectos" => $stmt->get_result()->fetch_all(MYSQLI_ASSOC),
                "paginacion" => [
                    "total_proyectos" => $total_proyectos,
                    "por_pagina" => $por_pagina,
                    "pagina" => $pagina,
                    "total_paginas" => $total_paginas
                ]
            ];
            return json_encode($resultado);

        } else {
            switch ($rol) {
                case 'estudiante':
                    $sql = "SELECT proy.id_proyectos, proy.titulo, proy.fecha_inicio, proy.fecha_fin, 
                                   espr.nombre, peri.periodo, 
                                   COUNT(CASE WHEN taus.id_estadoT = 1 THEN 1 END) AS total 
                            FROM gestion_proyectos.proyectos as proy 
                            JOIN proyectos_usuarios as prus ON proy.id_proyectos = prus.id_proyectos
                            JOIN estudiantes as estu ON prus.id_usuarios = estu.id_usuario
                            JOIN estados_proyectos as espr ON proy.id_estadoP = espr.id_estadoP 
                            JOIN periodos as peri ON proy.id_periodos = peri.id_periodos
                            LEFT JOIN tbl_seguimiento AS tbse ON tbse.id_proyectos = proy.id_proyectos
                            LEFT JOIN tareas AS tare ON tare.id_avances = tbse.id_avances
                            LEFT JOIN tareas_usuarios AS taus ON taus.id_tarea = tare.id_tarea 
                                AND taus.id_usuario = estu.id_usuario
                            WHERE estu.id_usuario = ? AND proy.id_estadoP = ?";

                    $params = [$id, $filtro];
                    $types = "ii";

                    if (!empty($buscar)) {
                        $sql .= " AND proy.titulo LIKE ?";
                        $params[] = "%$buscar%";
                        $types .= "s";
                    }

                    $sql .= " GROUP BY proy.id_proyectos ORDER BY proy.id_proyectos ASC LIMIT ?, ?";
                    $params[] = $desde;
                    $params[] = $por_pagina;
                    $types .= "ii";

                    $stmt = $this->con->prepare($sql);
                    $stmt->bind_param($types, ...$params);
                    break;

                case 'investigador':
                case 'profesor':
                    $sql = "SELECT proy.id_proyectos, proy.titulo, proy.fecha_inicio, proy.fecha_fin, 
                                   espr.nombre, peri.periodo, 
                                   COUNT(CASE WHEN taus.id_estadoT = 2 THEN 1 END) AS total
                            FROM gestion_proyectos.proyectos as proy 
                            JOIN investigadores as inv ON inv.id_usuario = proy.id_investigador
                            JOIN estados_proyectos as espr ON proy.id_estadoP = espr.id_estadoP 
                            JOIN periodos as peri ON proy.id_periodos = peri.id_periodos
                            LEFT JOIN tbl_seguimiento AS tbse ON tbse.id_proyectos = proy.id_proyectos
                            LEFT JOIN tareas AS tare ON tare.id_avances = tbse.id_avances
                            LEFT JOIN tareas_usuarios AS taus ON taus.id_tarea = tare.id_tarea 
                                AND taus.id_estadoT = 2
                            WHERE proy.id_investigador = ? AND espr.id_estadoP = ?";

                    $params = [$id, $filtro];
                    $types = "ii";

                    if (!empty($buscar)) {
                        $sql .= " AND proy.titulo LIKE ?";
                        $params[] = "%$buscar%";
                        $types .= "s";
                    }

                    $sql .= " GROUP BY proy.id_proyectos ORDER BY proy.id_proyectos ASC LIMIT ?, ?";
                    $params[] = $desde;
                    $params[] = $por_pagina;
                    $types .= "ii";

                    $stmt = $this->con->prepare($sql);
                    $stmt->bind_param($types, ...$params);
                    break;

                case 'supervisor':
                    $sql = "SELECT proy.id_proyectos, proy.titulo, proy.fecha_inicio, proy.fecha_fin, 
                                   espr.nombre, peri.periodo 
                            FROM gestion_proyectos.proyectos as proy 
                            JOIN estados_proyectos as espr ON proy.id_estadoP = espr.id_estadoP 
                            JOIN periodos as peri ON proy.id_periodos = peri.id_periodos
                            WHERE proy.id_estadoP = ?";

                    $params = [$filtro];
                    $types = "i";

                    if (!empty($buscar)) {
                        $sql .= " AND proy.titulo LIKE ?";
                        $params[] = "%$buscar%";
                        $types .= "s";
                    }

                    $sql .= " GROUP BY proy.id_proyectos ORDER BY proy.id_proyectos ASC LIMIT ?, ?";
                    $params[] = $desde;
                    $params[] = $por_pagina;
                    $types .= "ii";

                    $stmt = $this->con->prepare($sql);
                    $stmt->bind_param($types, ...$params);
                    break;

                default:
                    return json_encode([
                        "proyectos" => [],
                        "paginacion" => [
                            "total_proyectos" => 0,
                            "por_pagina" => $por_pagina,
                            "pagina" => $pagina,
                            "total_paginas" => 1
                        ]
                    ]);
            }

            $stmt->execute();
            $filas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $resultado = [
                "proyectos" => $filas,
                "paginacion" => [
                    "total_proyectos" => $total_proyectos,
                    "por_pagina" => $por_pagina,
                    "pagina" => $pagina,
                    "total_paginas" => $total_paginas
                ]
            ];
            return json_encode($resultado);
        }
    }

    //OBTENER LA CANTIDAD DE PROYECTOS
    public function obtenerCantidadProyectos($id, $numerofiltro, $rol, $buscar = null)
    {
        if ($numerofiltro == 0) {
            switch ($rol) {
                case 'estudiante':
                    $sql = "SELECT COUNT(*) AS total_proyectos 
                            FROM gestion_proyectos.proyectos as proy 
                            JOIN proyectos_usuarios as prus ON proy.id_proyectos = prus.id_proyectos
                            JOIN estudiantes as estu ON prus.id_usuarios = estu.id_usuario
                            WHERE estu.id_usuario = ?";

                    $params = [$id];
                    $types = "i";

                    if (!empty($buscar)) {
                        $sql .= " AND proy.titulo LIKE ?";
                        $params[] = "%$buscar%";
                        $types .= "s";
                    }

                    $stmt = $this->con->prepare($sql);
                    break;

                case 'investigador':
                case 'profesor':
                    $sql = "SELECT COUNT(*) AS total_proyectos 
                            FROM gestion_proyectos.proyectos as proy 
                            JOIN investigadores as inv ON inv.id_usuario = proy.id_investigador
                            WHERE proy.id_investigador = ?";

                    $params = [$id];
                    $types = "i";

                    if (!empty($buscar)) {
                        $sql .= " AND proy.titulo LIKE ?";
                        $params[] = "%$buscar%";
                        $types .= "s";
                    }

                    $stmt = $this->con->prepare($sql);
                    break;

                case 'supervisor':
                    $sql = "SELECT COUNT(*) AS total_proyectos 
                            FROM gestion_proyectos.proyectos as proy 
                            WHERE 1";
                    $params = [];
                    $types = "";

                    if (!empty($buscar)) {
                        $sql .= " AND proy.titulo LIKE ?";
                        $params[] = "%$buscar%";
                        $types .= "s";
                    }

                    $stmt = $this->con->prepare($sql);
                    break;

                default:
                    return 0;
            }

            if (!empty($params)) {
                $stmt->bind_param($types, ...$params);
            }
            $stmt->execute();
            $resultado = $stmt->get_result()->fetch_assoc();
            return $resultado['total_proyectos'];

        } else {
            switch ($rol) {
                case 'estudiante':
                    $sql = "SELECT COUNT(*) AS total_proyectos 
                            FROM gestion_proyectos.proyectos as proy 
                            JOIN proyectos_usuarios as prus ON proy.id_proyectos = prus.id_proyectos
                            JOIN estudiantes as estu ON prus.id_usuarios = estu.id_usuario
                            WHERE estu.id_usuario = ? AND proy.id_estadoP = ?";

                    $params = [$id, $numerofiltro];
                    $types = "ii";

                    if (!empty($buscar)) {
                        $sql .= " AND proy.titulo LIKE ?";
                        $params[] = "%$buscar%";
                        $types .= "s";
                    }

                    $stmt = $this->con->prepare($sql);
                    $stmt->bind_param($types, ...$params);
                    break;

                case 'investigador':
                case 'profesor':
                    $sql = "SELECT COUNT(*) AS total_proyectos 
                            FROM gestion_proyectos.proyectos as proy 
                            JOIN investigadores as inv ON inv.id_usuario = proy.id_investigador
                            WHERE proy.id_investigador = ? AND proy.id_estadoP = ?";

                    $params = [$id, $numerofiltro];
                    $types = "ii";

                    if (!empty($buscar)) {
                        $sql .= " AND proy.titulo LIKE ?";
                        $params[] = "%$buscar%";
                        $types .= "s";
                    }

                    $stmt = $this->con->prepare($sql);
                    $stmt->bind_param($types, ...$params);
                    break;

                case 'supervisor':
                    $sql = "SELECT COUNT(*) AS total_proyectos 
                            FROM gestion_proyectos.proyectos as proy 
                            WHERE proy.id_estadoP = ?";

                    $params = [$numerofiltro];
                    $types = "i";

                    if (!empty($buscar)) {
                        $sql .= " AND proy.titulo LIKE ?";
                        $params[] = "%$buscar%";
                        $types .= "s";
                    }

                    $stmt = $this->con->prepare($sql);
                    $stmt->bind_param($types, ...$params);
                    break;

                default:
                    return 0;
            }
            $stmt->execute();
            $resultado = $stmt->get_result()->fetch_assoc();
            return $resultado['total_proyectos'];
        }
    }

    // Las demás funciones permanecen igual...
    public function tematica()
    {
        $sql = "SELECT id_tematica, nombre_tematica FROM gestion_proyectos.tematica;";
        $stmt = $this->con->prepare($sql);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function obtenersubtematica($id_tematica)
    {
        $sql = "SELECT sub.id_subtematica, sub.nombre_subtematica 
                FROM gestion_proyectos.subtematica as sub
                JOIN tematica as te ON sub.id_tematica = te.id_tematica
                WHERE te.id_tematica = ?";

        $stmt = $this->con->prepare($sql);
        $stmt->bind_param("i", $id_tematica);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function obtenerperiodo()
    {
        $sql = "SELECT 
                id_periodos,
                periodo,
                fecha_inicio AS FechaInicio,
fecha_final AS FechaFinal,
CASE
WHEN CURDATE() BETWEEN fecha_inicio AND fecha_final THEN 'Activo'
WHEN CURDATE() < fecha_inicio THEN 'Pendiente'
ELSE 'Terminado'
END AS estado
FROM periodos
ORDER BY periodo DESC
LIMIT 1;";
        $stmt = $this->con->prepare($sql);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function obtenerinstituto()
    {
        $sql = "SELECT id_instituto 
            FROM gestion_proyectos.instituto 
            ORDER BY id_instituto DESC 
            LIMIT 1;";

        $stmt = $this->con->prepare($sql);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function registrarProyecto($id_investigador, $id_estadoP, $id_tematica, $id_instituto, $id_periodos, $titulo, $descripcion, $objetivo, $fecha_inicio, $fecha_final, $presupuesto, $requisitos, $Pre_requisitos, $modalidad, $AlumnosCantidad)
    {
        $sql = "INSERT INTO proyectos 
            (id_investigador, id_estadoP, id_tematica, id_instituto, id_periodos, titulo, descripcion, objetivo, fecha_inicio, fecha_fin, presupuesto, actualizado_en, requisitos, pre_requisitos, modalidad, cantidad_estudiante)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?, ?)";

        $stmt = $this->con->prepare($sql);
        if (!$stmt) {
            die("Error en prepare(): " . $this->con->error);
        }

        $stmt->bind_param(
            "iiiiisssssssssi",
            $id_investigador,
            $id_estadoP,
            $id_tematica,
            $id_instituto,
            $id_periodos,
            $titulo,
            $descripcion,
            $objetivo,
            $fecha_inicio,
            $fecha_final,
            $presupuesto,
            $requisitos,
            $Pre_requisitos,
            $modalidad,
            $AlumnosCantidad
        );

        if (!$stmt->execute()) {
            die("Error en execute(): " . $stmt->error);
        }

        header("Location: crear.php?msg=creado");
        exit();
    }

    public function editarProyecto($id_proyecto, $id_investigador, $id_tematica, $titulo, $descripcion, $objetivo, $fecha_inicio, $fecha_final, $presupuesto, $requisitos, $Pre_requisitos, $modalidad, $AlumnosCantidad)
    {
        $sql = "UPDATE proyectos SET 
            titulo = ?,
            descripcion = ?,
            objetivo = ?,
            pre_requisitos = ?,
            requisitos = ?,
            cantidad_estudiante = ?,
            id_tematica = ?,
            modalidad = ?,
            actualizado_en = NOW(),
            presupuesto = ?,
            fecha_inicio = ?,
            fecha_fin = ?
            WHERE id_proyectos = ? AND id_investigador = ?";

        $stmt = $this->con->prepare($sql);
        if (!$stmt) {
            die("Error en prepare(): " . $this->con->error);
        }

        $stmt->bind_param(
            "sssssiisissii",
            $titulo,
            $descripcion,
            $objetivo,
            $Pre_requisitos,
            $requisitos,
            $AlumnosCantidad,
            $id_tematica,
            $modalidad,
            $presupuesto,
            $fecha_inicio,
            $fecha_final,
            $id_proyecto,
            $id_investigador
        );

        if (!$stmt->execute()) {
            die("Error en execute(): " . $stmt->error);
        }

        header("Location: editar.php?msg=mensaje&id_proyectos=" . $id_proyecto);
        exit();
    }

    public function actualizarEstadoProyectoRechazo($id_usuario, $id_proyectos, $tipo, $comentario)
    {
        if ($tipo == "cierre_rechazado") {
            $num_motivo = 7;
        } else if ($tipo == "creacion_rechazada") {
            $num_motivo = 4;
        }

        $sql = "UPDATE proyectos SET id_estadoP = ?, actualizado_en = NOW() WHERE id_proyectos = ?";
        $stmt = $this->con->prepare($sql);
        $stmt->bind_param("ii", $num_motivo, $id_proyectos);

        if (!$stmt->execute()) {
            die("Error en execute(): " . $stmt->error);
        }

        $sql = "INSERT INTO proyectos_comentarios 
            (id_proyectos, id_usuario, tipo, comentario, fecha)
            VALUES (?, ?, ?, ?, CURDATE())";

        $stmt = $this->con->prepare($sql);
        $stmt->bind_param("iiss", $id_proyectos, $id_usuario, $tipo, $comentario);

        if (!$stmt->execute()) {
            die("Error en execute(): " . $stmt->error);
        }

        header("Location: tabla.php?msg=mensaje");
        exit;
    }

    public function actualizarestado($id_proyectos, $numeroEstado)
    {
        $sql = "UPDATE proyectos 
            SET id_estadoP = ?, actualizado_en = NOW() 
            WHERE id_proyectos = ?";

        $stmt = $this->con->prepare($sql);
        if (!$stmt) {
            die("Error en prepare(): " . $this->con->error);
        }

        $stmt->bind_param("ii", $numeroEstado, $id_proyectos);

        if (!$stmt->execute()) {
            die("Error en execute(): " . $stmt->error);
        }

        if ($numeroEstado == 2) {
            $sqlTipos = "SELECT id_tareatipo FROM tipo_tarea ORDER BY id_tareatipo ASC";
            $result = $this->con->query($sqlTipos);

            if ($result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    $id_tipo = $row['id_tareatipo'];

                    $sqlSeg = "INSERT INTO tbl_seguimiento 
                          (id_proyectos, fecha_activacion) 
                          VALUES (?, NOW())";

                    $stmtSeg = $this->con->prepare($sqlSeg);
                    $stmtSeg->bind_param("i", $id_proyectos);
                    $stmtSeg->execute();

                    $id_avances = $stmtSeg->insert_id;

                    $sqlTarea = "INSERT INTO tareas 
                            (id_avances, id_tipotarea, descripcion, instrucciones)
                            VALUES (?, ?, NULL, NULL)";

                    $stmtTarea = $this->con->prepare($sqlTarea);
                    $stmtTarea->bind_param("ii", $id_avances, $id_tipo);
                    $stmtTarea->execute();
                }
            }
        }

        header("Location: tabla.php?msg=mensaje");
        exit;
    }

    function obtenerProyecto($id_proyecto)
    {
        $sql = "SELECT proy.id_proyectos, espr.nombre as estado_proyecto, tema.nombre_tematica as tematica, 
                   subt.nombre_subtematica as subtematica, peri.periodo, 
                   CASE 
                       WHEN CURDATE() BETWEEN peri.fecha_inicio AND peri.fecha_final THEN 'Activo' 
                       WHEN CURDATE() < peri.fecha_inicio THEN 'Terminado' 
                       ELSE 'Terminado'  
                   END AS estado_periodo, 
                   proy.titulo, proy.descripcion, proy.objetivo, proy.fecha_inicio, proy.fecha_fin, 
                   proy.presupuesto, proy.creado_en, proy.requisitos, proy.pre_requisitos, proy.modalidad, 
                   proy.cantidad_estudiante 
            FROM gestion_proyectos.proyectos as proy
            JOIN estados_proyectos as espr ON proy.id_estadoP = espr.id_estadoP
            JOIN tematica as tema ON tema.id_tematica = proy.id_tematica
            JOIN subtematica as subt ON tema.id_tematica = subt.id_tematica
            JOIN periodos as peri ON peri.id_periodos = proy.id_periodos
            WHERE proy.id_proyectos = ?;";

        $stmt = $this->con->prepare($sql);
        $stmt->bind_param("i", $id_proyecto);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    function obtenerProyectoInvestigador($id_proyecto)
    {
        $sql = "SELECT usua.nombre, usua.apellido_paterno, usua.apellido_materno, 
                   arco.nombre_area as area_conocimiento, subco.nombre_subarea as subarea, 
                   nisn.nombre as nivel_sni, grac.nombre as grado_academico, 
                   liin.nombre as linea_investigacion  
            FROM gestion_proyectos.investigadores as inve
            JOIN usuarios as usua ON usua.id_usuarios = inve.id_usuario
            JOIN areas_conocimiento as arco ON arco.id_area = inve.id_area
            JOIN subareas_conocimiento as subco ON arco.id_area = subco.id_area
            JOIN niveles_sni as nisn ON nisn.id_nivel = inve.id_nivel_sni
            JOIN grados_academicos as grac ON grac.id_grado = inve.id_grado
            JOIN lineas_investigacion as liin ON liin.id_linea = inve.id_linea
            JOIN proyectos as proy ON proy.id_investigador = inve.id_usuario
            WHERE proy.id_proyectos = ?;";

        $stmt = $this->con->prepare($sql);
        $stmt->bind_param("i", $id_proyecto);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    function obtenerProyectoEstudiante($id_proyecto)
    {
        $sql = "SELECT usua.id_usuarios, usua.nombre, usua.apellido_paterno, usua.apellido_materno, 
                   carr.nombre_carrera as carrera, arco.nombre_area as area, 
                   subco.nombre_subarea as subarea 
            FROM gestion_proyectos.estudiantes as estu 
            JOIN usuarios AS usua ON usua.id_usuarios = estu.id_usuario
            JOIN areas_conocimiento as arco ON arco.id_area = estu.id_area
            JOIN subareas_conocimiento as subco ON arco.id_area = subco.id_area
            JOIN carreras as carr ON carr.id_carrera = estu.id_carrera
            JOIN proyectos_usuarios as prus ON prus.id_usuarios = estu.id_usuario
            JOIN proyectos as proy ON proy.id_proyectos = prus.id_proyectos
            WHERE proy.id_proyectos = ?;";

        $stmt = $this->con->prepare($sql);
        $stmt->bind_param("i", $id_proyecto);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function obtenerProyectoComentarios($id_proyecto)
    {
        $sql = "SELECT CASE 
                WHEN prco.tipo = 'creacion_rechazada' THEN 'Creación rechazada'
                WHEN prco.tipo = 'cierre_rechazado' THEN 'Cierre rechazada'
                ELSE 'Rechazo'
            END AS tipo, 
            CONCAT(usua.nombre, ' ', usua.apellido_paterno, ' ', usua.apellido_materno) as nombre_completo, 
            prco.comentario, prco.fecha 
            FROM gestion_proyectos.proyectos_comentarios as prco
            JOIN proyectos as proy ON proy.id_proyectos = prco.id_proyectos
            JOIN usuarios as usua ON usua.id_usuarios = prco.id_usuario
            WHERE proy.id_proyectos = ? 
            ORDER BY fecha DESC;";

        $stmt = $this->con->prepare($sql);
        $stmt->bind_param("i", $id_proyecto);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}