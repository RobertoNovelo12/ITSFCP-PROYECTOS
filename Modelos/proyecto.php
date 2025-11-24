<?php
require_once '../../publico/config/conexion.php';

class Proyectos
{

    private $con;

    public function __construct($conn)
    {
        $this->con = $conn;
    }

    //DATOS GENERALES SIN FILTRO
    public function obtenerProyectos($id, $rol, $buscar = null)
    {
        switch ($rol) {
            case 'alumno':
                $sql = "SELECT proy.id_proyectos, proy.titulo, proy.fecha_inicio, proy.fecha_fin, espr.nombre, peri.periodo, COUNT(CASE WHEN tbse.id_estadoT = 1 THEN 1 END) AS total_pendiente FROM gestion_proyectos.proyectos as proy 
JOIN proyectos_usuarios as prus ON proy.id_proyectos = prus.id_proyectos
JOIN estudiantes as estu ON estu.id_usuario = prus.id_usuarios
JOIN estados_proyectos as espr ON proy.id_estadoP = espr.id_estadoP 
JOIN periodos as peri ON proy.id_periodos = peri.id_periodos
LEFT JOIN tbl_seguimiento AS tbse 
ON tbse.id_proyectos = proy.id_proyectos
AND tbse.id_estadoT = 1
WHERE estu.id_usuario = ? 
GROUP BY proy.id_proyectos;";
                $params = [$id];
                $types  = "i";
                break;
            case 'investigador':
                $sql = "SELECT proy.id_proyectos, proy.titulo, proy.fecha_inicio, proy.fecha_fin, espr.nombre, peri.periodo, COUNT(CASE WHEN tbse.id_estadoT = 2 THEN 1 END) AS total FROM gestion_proyectos.proyectos as proy 
JOIN investigadores as inv ON inv.id_usuario = proy.id_investigador
JOIN estados_proyectos as espr ON proy.id_estadoP = espr.id_estadoP 
JOIN periodos as peri ON proy.id_periodos = peri.id_periodos
LEFT JOIN tbl_seguimiento AS tbse 
ON tbse.id_proyectos = proy.id_proyectos
AND tbse.id_estadoT = 2
WHERE proy.id_investigador = ?";
                $params = [$id];
                $types  = "i";
                break;
            case 'supervisor':
                $sql = "SELECT proy.id_proyectos, proy.titulo, proy.fecha_inicio, proy.fecha_fin, espr.nombre, peri.periodo FROM gestion_proyectos.proyectos as proy 
JOIN estados_proyectos as espr ON proy.id_estadoP = espr.id_estadoP 
JOIN periodos as peri ON proy.id_periodos = peri.id_periodos";
                $types  = "";
                $params = [];
                break;
            default:
                break;
        }

        if (!empty($buscar)) {
            $sql .= " AND proy.titulo LIKE ?";
            $params[] = "%$buscar%";
            $types   .= "s";
        }

        $sql .= " GROUP BY proy.id_proyectos";

        $stmt = $this->con->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        } else {
            // No hay parámetros para enlazar
        }
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    //DATOS DEL FILTRO
    public function obtenerProyectosDatosFiltro($id, $rol)
    {

        switch ($rol) {
            case 'alumno':
                $sql = "SELECT   COUNT(*) AS Total,
  SUM(espr.nombre='Activo') AS Activos,
  SUM(espr.nombre='Por aprobar') AS PorAprobar,
  SUM(espr.nombre='Cierre') AS Cierre,
  SUM(espr.nombre='Por cerrar') AS PorCerrar,
  SUM(espr.nombre='Rechazado') AS Rechazados FROM gestion_proyectos.proyectos as proy 
JOIN proyectos_usuarios as prus ON proy.id_proyectos = prus.id_proyectos
JOIN estudiantes as estu ON prus.id_usuarios = estu.id_usuario
JOIN estados_proyectos as espr ON proy.id_estadoP = espr.id_estadoP
WHERE estu.id_usuario = ? 
ORDER BY proy.id_proyectos ASC;";

                $stmt = $this->con->prepare($sql);
                $stmt->bind_param("i", $id);
                break;
            case 'investigador':
            case 'profesor':
                $sql = "SELECT   COUNT(*) AS Total,
  SUM(espr.nombre='Activo') AS Activos,
  SUM(espr.nombre='Por aprobar') AS PorAprobar,
  SUM(espr.nombre='Cierre') AS Cierre,
  SUM(espr.nombre='Por cerrar') AS PorCerrar,
  SUM(espr.nombre='Rechazado') AS Rechazados FROM gestion_proyectos.proyectos as proy 
JOIN investigadores as inv ON inv.id_usuario = proy.id_investigador
JOIN estados_proyectos as espr ON proy.id_estadoP = espr.id_estadoP 
WHERE proy.id_investigador = ?
ORDER BY proy.id_proyectos ASC;";

                $stmt = $this->con->prepare($sql);
                $stmt->bind_param("i", $id);
                break;
            case 'supervisor':
                $sql = "SELECT   COUNT(*) AS Total,
  SUM(espr.nombre='Activo') AS Activos,
  SUM(espr.nombre='Por aprobar') AS PorAprobar,
  SUM(espr.nombre='Cierre') AS Cierre,
  SUM(espr.nombre='Por cerrar') AS PorCerrar,
  SUM(espr.nombre='Rechazado') AS Rechazados FROM gestion_proyectos.proyectos as proy 
JOIN estados_proyectos as espr ON proy.id_estadoP = espr.id_estadoP 
ORDER BY proy.id_proyectos ASC;";

                $stmt = $this->con->prepare($sql);
                break;
            default:
                return []; // Retorna un array vacío si el rol no es válido
        }


        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    //DATOS FILTRADOS SEGUN SELECCION
    public function obtenerProyectosTablaFiltro($id, $filtro, $rol, $buscar = null)
    {
        if ($filtro == 0) {
            // Si el filtro es 0 (Total), no aplicamos ninguna condición adicional

            switch ($rol) {
                case 'alumno':
                    $sql = "SELECT proy.id_proyectos, proy.titulo, proy.fecha_inicio, proy.fecha_fin, espr.nombre, peri.periodo, COUNT(CASE WHEN tbse.id_estadoT = 1 THEN 1 END) AS total FROM gestion_proyectos.proyectos as proy 
JOIN proyectos_usuarios as prus ON proy.id_proyectos = prus.id_proyectos
JOIN estudiantes as estu ON prus.id_usuarios = estu.id_usuario
JOIN estados_proyectos as espr ON proy.id_estadoP = espr.id_estadoP 
JOIN periodos as peri ON proy.id_periodos = peri.id_periodos
LEFT JOIN tbl_seguimiento AS tbse 
ON tbse.id_proyectos = proy.id_proyectos
AND tbse.id_estadoT = 1
WHERE estu.id_usuario = ?";

                    $params = [$id];
                    $types  = "i";

                    if (!empty($buscar)) {
                        $sql .= " AND proy.titulo LIKE ?";
                        $params[] = "%$buscar%";
                        $types   .= "s";
                    }

                    $sql .= " GROUP BY proy.id_proyectos";

                    $stmt = $this->con->prepare($sql);
                    $stmt->bind_param($types, ...$params);
                    break;
                case 'investigador':
                case 'profesor':
                    $sql = "SELECT proy.id_proyectos, proy.titulo, proy.fecha_inicio, proy.fecha_fin, espr.nombre, peri.periodo, COUNT(CASE WHEN tbse.id_estadoT = 2 THEN 1 END) AS total FROM gestion_proyectos.proyectos as proy 
JOIN investigadores as inv ON inv.id_usuario = proy.id_investigador
JOIN estados_proyectos as espr ON proy.id_estadoP = espr.id_estadoP 
JOIN periodos as peri ON proy.id_periodos = peri.id_periodos
LEFT JOIN tbl_seguimiento AS tbse 
ON tbse.id_proyectos = proy.id_proyectos
AND tbse.id_estadoT = 2
WHERE proy.id_investigador = ?";

                    $params = [$id];
                    $types  = "i";

                    if (!empty($buscar)) {
                        $sql .= " AND proy.titulo LIKE ?";
                        $params[] = "%$buscar%";
                        $types   .= "s";
                    }

                    $sql .= " GROUP BY proy.id_proyectos";

                    $stmt = $this->con->prepare($sql);
                    $stmt->bind_param($types, ...$params);
                    break;
                case 'supervisor':
                    $sql = "SELECT proy.id_proyectos, proy.titulo, proy.fecha_inicio, proy.fecha_fin, espr.nombre, peri.periodo FROM gestion_proyectos.proyectos as proy 
JOIN estados_proyectos as espr ON proy.id_estadoP = espr.id_estadoP 
JOIN periodos as peri ON proy.id_periodos = peri.id_periodos";

                    $types  = "";

                    if (!empty($buscar)) {
                        $sql .= " AND proy.titulo LIKE ?";
                        $params[] = "%$buscar%";
                        $types   .= "s";
                    }

                    $sql .= " GROUP BY proy.id_proyectos";

                    $stmt = $this->con->prepare($sql);
                    if (!empty($params)) {
                        $stmt->bind_param($types, ...$params);
                    } else {
                        // No hay parámetros para enlazar
                    }
                    break;
                default:
                    return []; // Retorna un array vacío si el rol no es válido
            }

            $stmt->execute();
            return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        } else {

            switch ($rol) {
                case 'alumno':
                    $sql = "SELECT proy.id_proyectos, proy.titulo, proy.fecha_inicio, proy.fecha_fin, espr.nombre, peri.periodo, SUM(CASE WHEN tbse.id_estadoT = 1 THEN 1 ELSE 0 END) AS total FROM gestion_proyectos.proyectos as proy 
JOIN proyectos_usuarios as prus ON proy.id_proyectos = prus.id_proyectos
JOIN estudiantes as estu ON prus.id_usuarios = estu.id_usuario
JOIN estados_proyectos as espr ON proy.id_estadoP = espr.id_estadoP 
JOIN periodos as peri ON proy.id_periodos = peri.id_periodos
LEFT JOIN tbl_seguimiento AS tbse 
    ON tbse.id_proyectos = proy.id_proyectos 
WHERE estu.id_usuario = ? AND proy.id_estadoP = ?";

                    $params = [$id, $filtro];
                    $types  = "is";

                    if (!empty($buscar)) {
                        $sql .= " AND proy.titulo LIKE ?";
                        $params[] = "%$buscar%";
                        $types   .= "s";
                    }

                    $sql .= " GROUP BY proy.id_proyectos";

                    $stmt = $this->con->prepare($sql);
                    $stmt->bind_param($types, ...$params);
                    break;
                case 'investigador':
                case 'profesor':
                    $sql = "SELECT proy.id_proyectos, proy.titulo, proy.fecha_inicio, proy.fecha_fin, espr.nombre, peri.periodo, COUNT(CASE WHEN tbse.id_estadoT = 2 THEN 1 END) AS total
 FROM gestion_proyectos.proyectos as proy 
JOIN investigadores as inv ON inv.id_usuario = proy.id_investigador
JOIN estados_proyectos as espr ON proy.id_estadoP = espr.id_estadoP 
JOIN periodos as peri ON proy.id_periodos = peri.id_periodos
LEFT JOIN tbl_seguimiento AS tbse 
ON tbse.id_proyectos = proy.id_proyectos
AND tbse.id_estadoT = 2
WHERE proy.id_investigador = ? AND espr.id_estadoP = ?";

                    $params = [$id, $filtro];
                    $types  = "is";

                    if (!empty($buscar)) {
                        $sql .= " AND proy.titulo LIKE ?";
                        $params[] = "%$buscar%";
                        $types   .= "s";
                    }

                    $sql .= " GROUP BY proy.id_proyectos";

                    $stmt = $this->con->prepare($sql);
                    $stmt->bind_param($types, ...$params);
                    break;
                case 'supervisor':
                    $sql = "SELECT proy.id_proyectos, proy.titulo, proy.fecha_inicio, proy.fecha_fin, espr.nombre, peri.periodo FROM gestion_proyectos.proyectos as proy 
JOIN estados_proyectos as espr ON proy.id_estadoP = espr.id_estadoP 
JOIN periodos as peri ON proy.id_periodos = peri.id_periodos
WHERE proy.id_estadoP = ?";

                    $params = [$filtro];
                    $types  = "i";

                    if (!empty($buscar)) {
                        $sql .= " AND proy.titulo LIKE ?";
                        $params[] = "%$buscar%";
                        $types   .= "s";
                    }

                    $sql .= " GROUP BY proy.id_proyectos";

                    $stmt = $this->con->prepare($sql);
                    $stmt->bind_param($types, ...$params);
                    break;
                default:
                    return []; // Retorna un array vacío si el rol no es válido
            }


            $stmt->execute();
            return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        }
    }
    //OBTENER LA CANTIDAD DE PROYECTOS
    public function obtenerCantidadProyectos($id, $numerofiltro, $rol, $buscar = null)
    {

        if ($numerofiltro == 0) {
            // Si el filtro es 0 (Total), no aplicamos ninguna condición adicional
            switch ($rol) {
                case 'alumno':
                    $sql = "SELECT COUNT(*) AS total_proyectos FROM gestion_proyectos.proyectos as proy 
JOIN proyectos_usuarios as prus ON proy.id_proyectos = prus.id_proyectos
JOIN estudiantes as estu ON prus.id_usuarios = estu.id_usuario
WHERE estu.id_usuario = ?";

                    $params = [$id];
                    $types  = "i";

                    if (!empty($buscar)) {
                        $sql .= " AND proy.titulo LIKE ?";
                        $params[] = "%$buscar%";
                        $types   .= "s";
                    }

                    $stmt = $this->con->prepare($sql);
                    break;
                case 'investigador':
                case 'profesor':
                    $sql = "SELECT COUNT(*) AS total_proyectos FROM gestion_proyectos.proyectos as proy 
JOIN investigadores as inv ON inv.id_usuario = proy.id_investigador
WHERE proy.id_investigador = ?";

                    $params = [$id];
                    $types  = "i";

                    if (!empty($buscar)) {
                        $sql .= " AND proy.titulo LIKE ?";
                        $params[] = "%$buscar%";
                        $types   .= "s";
                    }

                    $stmt = $this->con->prepare($sql);
                    break;
                case 'supervisor':
                    $sql = "SELECT COUNT(*) AS total_proyectos FROM gestion_proyectos.proyectos as proy WHERE 1";
                    $params = [];
                    $types  = "";

                    if (!empty($buscar)) {
                        $sql .= " AND proy.titulo LIKE ?";
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
            return $resultado['total_proyectos'];   // OBTENER EL NUMERO TOTAL DE PROYECTOS
        } else {
            switch ($rol) {
                case 'alumno':
                    $sql = "SELECT COUNT(*) AS total_proyectos FROM gestion_proyectos.proyectos as proy 
JOIN proyectos_usuarios as prus ON proy.id_proyectos = prus.id_proyectos
JOIN estudiantes as estu ON prus.id_usuarios = estu.id_usuario
WHERE estu.id_usuario = ? AND proy.id_estadoP = ?";

                    $params = [$id, $numerofiltro];
                    $types  = "ii";

                    if (!empty($buscar)) {
                        $sql .= " AND proy.titulo LIKE ?";
                        $params[] = "%$buscar%";
                        $types   .= "s";
                    }

                    $stmt = $this->con->prepare($sql);
                    $stmt->bind_param($types, ...$params);
                    break;
                case 'investigador':
                case 'profesor':
                    $sql = "SELECT COUNT(*) AS total_proyectos FROM gestion_proyectos.proyectos as proy 
JOIN investigadores as inv ON inv.id_usuario = proy.id_investigador
WHERE proy.id_investigador = ? AND proy.id_estadoP = ?";

                    $params = [$id, $numerofiltro];
                    $types  = "ii";

                    if (!empty($buscar)) {
                        $sql .= " AND proy.titulo LIKE ?";
                        $params[] = "%$buscar%";
                        $types   .= "s";
                    }

                    $stmt = $this->con->prepare($sql);
                    $stmt->bind_param($types, ...$params);
                    break;
                case 'supervisor':
                    $sql = "SELECT COUNT(*) AS total_proyectos FROM gestion_proyectos.proyectos as proy 
WHERE proy.id_estadoP = ?";

                    $params = [$numerofiltro];
                    $types  = "i";

                    if (!empty($buscar)) {
                        $sql .= " AND proy.titulo LIKE ?";
                        $params[] = "%$buscar%";
                        $types   .= "s";
                    }

                    $stmt = $this->con->prepare($sql);
                    $stmt->bind_param($types, ...$params);
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
            return $resultado['total_proyectos'];   // OBTENER EL NUMERO TOTAL DE PROYECTOS
        }
    }

    //INSERCION DE PROYECTOS
    public function insertarProyecto($titulo, $descripcion, $responsable)
    {
        $sql = "INSERT INTO proyectos (titulo, descripcion, responsable) VALUES (?, ?, ?)";
        $stmt = $this->con->prepare($sql);
        $stmt->bind_param("sss", $titulo, $descripcion, $responsable);
        return $stmt->execute();
    }
}
