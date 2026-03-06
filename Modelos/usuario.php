
<?php
require_once __DIR__ . '/../publico/config/conexion.php';

class Usuarios
{

    private $con;

    public function __construct($conn)
    {
        $this->con = $conn;
    }

    //DATOS GENERALES SIN FILTRO
    public function obtenerUsuarios($id, $rol, $buscar = null)
    {
        // Normalizar rol para evitar problemas de mayúsculas/minúsculas
        $rol = strtolower($rol);

        // Cantidad totales
        $total_usuarios = $this->obtenerCantidadUsuarios($id, 0, $rol, $buscar);

        // Parámetros de paginación
        $por_pagina = 6;
        $pagina = empty($_GET['pagina']) ? 1 : intval($_GET['pagina']);
        $desde = ($pagina - 1) * $por_pagina;

        $total_paginas = ($total_usuarios > 0) ? ceil($total_usuarios / $por_pagina) : 1;

        // Inicializar variables
        $sql = "";
        $params = [];
        $types = "";
        $whereAdded = false;

        // Consultas base según rol
        switch ($rol) {
            case 'supervisor':
                $sql = "SELECT 
    usua.id_usuarios,
    usua.CONCAT(nombre, ' ', apellido_paterno, ' ', apellido_materno) AS nombre_completo,
    usua.estado_usuario,
    usua.fecha_registro,
    CASE
        WHEN e.id_estudiante IS NOT NULL THEN 'estudiante'
        WHEN i.id_investigador IS NOT NULL THEN 'investigador'
        WHEN s.id_supervisor IS NOT NULL THEN 'supervisor'
        ELSE 'desconocido'
    END AS tipo_usuario
FROM usuarios u
LEFT JOIN estudiantes e ON u.id_usuarios = e.id_usuario
LEFT JOIN investigadores i ON u.id_usuarios = i.id_usuario
LEFT JOIN supervisores s ON u.id_usuarios = s.id_usuario;";
                // supervisor no añade WHERE por defecto
                $whereAdded = false;
                break;

            default:
                // Si el rol es inesperado devolvemos vacío (evita errores posteriores)
                return json_encode([
                    "usuarios" => [],
                    "paginacion" => [
                        "total_usuarios" => 0,
                        "por_pagina"      => $por_pagina,
                        "pagina"          => $pagina,
                        "total_paginas"   => 1
                    ]
                ]);
        }

        // Filtro de búsqueda (si aplica)
        if (!empty($buscar)) {
            if ($whereAdded) {
                $sql .= " AND (usua.nombre LIKE ? 
                        OR usua.apellido_paterno LIKE ?
                        OR usua.apellido_materno LIKE ?) ";
            } else {
                $sql .= " WHERE (usua.nombre LIKE ? 
                        OR usua.apellido_paterno LIKE ?
                        OR usua.apellido_materno LIKE ?) ";
                $whereAdded = true;
            }

            $params[] = "%$buscar%";
            $params[] = "%$buscar%";
            $params[] = "%$buscar%";

            $types .= "sss";
        }


        // GROUP BY y LIMIT al final (LIMIT siempre al final de la query)
        $sql .= " GROUP BY usua.id_usuarios ORDER BY usua.id_usuarios ASC LIMIT ?, ?";

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
            "usuarios" => $stmt->get_result()->fetch_all(MYSQLI_ASSOC),
            "paginacion" => [
                "total_usuarios" => $total_usuarios,
                "por_pagina"      => $por_pagina,
                "pagina"          => $pagina,
                "total_paginas"   => $total_paginas
            ]
        ];

        return json_encode($resultado);
    }


    //DATOS DEL FILTRO
    public function obtenerUsuariosDatosFiltro($id, $rol)
    {
        switch ($rol) {

            case 'supervisor':
                $sql = "SELECT 
  COUNT(*) AS Total,
  SUM(CASE WHEN estado_usuario='espera' THEN 1 ELSE 0 END) AS Espera,
  SUM(CASE WHEN estado_usuario=a'aprobado' THEN 1 ELSE 0 END) AS Aprobado,
  SUM(CASE WHEN estado_usuario='activo' THEN 1 ELSE 0 END) AS Activo,
  SUM(CASE WHEN estado_usuario='rechazado' THEN 1 ELSE 0 END) AS Rechazado,
FROM gestion_proyectos.usuarios";

                $stmt = $this->con->prepare($sql);
                break;
            default:
                return []; // Retorna un array vacío si el rol no es válido
        }
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    //DATOS FILTRADOS SEGUN SELECCION
    public function obtenerUsuariosTablaFiltro($id, $filtro, $rol, $buscar = null)
    {
        // --- Paginación ---
        $total_usuarios = $this->obtenerCantidadUsuarios($id, $filtro, $rol, $buscar);
        $por_pagina = 6;
        $pagina = empty($_GET['pagina']) ? 1 : intval($_GET['pagina']);
        $desde = ($pagina - 1) * $por_pagina;
        $total_paginas = ($total_usuarios > 0) ? ceil($total_usuarios / $por_pagina) : 1;

        // --- SQL BASE POR ROL ---
        $base = "";
        $params = [];
        $types = "";
        $where = [];

        switch ($rol) {
            case 'supervisor':
                $base = "
                SELECT 
                    proy.id_proyectos, proy.titulo, proy.fecha_inicio, proy.fecha_fin,
                    espr.nombre AS estado, peri.periodo
                FROM proyectos proy
                JOIN estados_proyectos espr ON proy.id_estadoP = espr.id_estadoP
                JOIN periodos peri ON proy.id_periodos = peri.id_periodos
                ";
                break;

            default:
                return json_encode([
                    "usuarios" => [],
                    "paginacion" => []
                ]);
        }

        // --- Filtro por estado ---
        if ($filtro != 0) {
            $where[] = "usua.estado_usuario = ?";
            $params[] = $filtro;
            $types   .= "s";
        }

        // --- Filtro de búsqueda ---
        if (!empty($buscar)) {
            $where[] = "usua.nombre LIKE ?";
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
        GROUP BY proy.id_usuarios
        ORDER BY proy.id_usuarios ASC
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
            "usuarios" => $filas,
            "paginacion" => [
                "total_usuarios" => $total_usuarios,
                "por_pagina"      => $por_pagina,
                "pagina"          => $pagina,
                "total_paginas"   => $total_paginas
            ]
        ]);
    }

    //OBTENER LA CANTIDAD DE PROYECTOS
    public function obtenerCantidadUsuarios($id, $numerofiltro, $rol, $buscar = null)
    {

        if ($numerofiltro == 0) {
            // Si el filtro es 0 (Total), no aplicamos ninguna condición adicional
            switch ($rol) {
                case 'supervisor':
                    $sql = "SELECT COUNT(*) AS total_usuarios FROM gestion_proyectos.usuarios as usua WHERE 1";
                    $params = [];
                    $types  = "";

                    if (!empty($buscar)) {
                        $sql .= " AND proy.nombre LIKE ?";
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
            return $resultado['total_usuario'];   // OBTENER EL NUMERO TOTAL DE PROYECTOS
        } else {
            switch ($rol) {
                case 'supervisor':
                    $sql = "SELECT COUNT(*) AS total_usuarios FROM gestion_proyectos.usuarios as usua 
WHERE proy.estado_usuario = ?";

                    $params = [$numerofiltro];
                    $types  = "i";

                    if (!empty($buscar)) {
                        $sql .= " AND proy.nombre LIKE ?";
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
            return $resultado['total_usuarios'];   // OBTENER EL NUMERO TOTAL DE USUARIOS
        }
    }

    public function actualizarestado($id_usuarios, $Estado)
    {

        // 1. Actualizar estado
        $sql = "UPDATE usuarios 
            SET estado_usuario = ?, actualizado_en = NOW() 
            WHERE id_proyectos = ?";

        $stmt = $this->con->prepare($sql);

        if (!$stmt) {
            die("Error en prepare(): " . $this->con->error);
        }


        $stmt->bind_param("si", $Estado, $id_usuarios);

        if (!$stmt->execute()) {
            die("Error en execute(): " . $stmt->error);
        }
        header("Location: tabla.php");
        exit();
    }

    //DETALLES DEL USUARIO
    function obtenerUsuario($id_usuario)
    {
        $sql = "SELECT proy.id_usuario, espr.nombre as estado_proyecto, tema.nombre_tematica as tematica, subt.nombre_subtematica as subtematica, peri.periodo, CASE WHEN CURDATE() BETWEEN peri.fecha_inicio AND peri.fecha_final THEN 'Activo' WHEN CURDATE() < peri.fecha_inicio THEN 'Terminado' ELSE 'Terminado'  END AS estado_periodo, proy.titulo, proy.descripcion, proy.objetivo, proy.fecha_inicio, proy.fecha_fin, proy.presupuesto, proy.creado_en, proy.requisitos, proy.pre_requisitos, proy.modalidad, proy.cantidad_estudiante FROM gestion_proyectos.proyectos as proy
JOIN estados_proyectos as espr ON proy.id_estadoP = espr.id_estadoP
JOIN tematica as tema ON tema.id_tematica = proy.id_tematica
JOIN subtematica as subt ON tema.id_tematica = subt.id_tematica
JOIN periodos as peri ON peri.id_periodos = proy.id_periodos
WHERE proy.id_usuario = ?;";

        $params = [$id_usuario];
        $types  = "i";

        $stmt = $this->con->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}

