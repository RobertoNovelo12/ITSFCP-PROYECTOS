<?php
require_once __DIR__ . '/../publico/config/conexion.php';

class Usuarios
{
    private $con;

    public function __construct($conn)
    {
        $this->con = $conn;
    }

    // 
    //  CONTAR USUARIOS (para paginación)
    // 
    public function obtenerCantidadUsuarios($estado = null, $buscar = null, $tipo = null)
    {
        $params = [];
        $types  = "";
        $where  = [];

        $sql = "SELECT COUNT(DISTINCT u.id_usuarios) AS total
                FROM usuarios u
                LEFT JOIN usuarios_roles   ur ON ur.id_usuarios = u.id_usuarios
                LEFT JOIN roles            r  ON r.id_roles     = ur.id_rol
                LEFT JOIN estudiantes      es ON es.id_usuarios = u.id_usuarios
                LEFT JOIN investigadores   inv ON inv.id_usuarios = u.id_usuarios
                LEFT JOIN supervisores     su ON su.id_usuarios = u.id_usuarios";

        if (!empty($estado)) {
            $where[] = "u.estado_usuario = ?";
            $params[] = $estado;
            $types   .= "s";
        }

        if (!empty($buscar)) {
            $where[] = "(u.nombre LIKE ? OR u.apellido_paterno LIKE ? OR u.apellido_materno LIKE ?)";
            $params[] = "%$buscar%";
            $params[] = "%$buscar%";
            $params[] = "%$buscar%";
            $types   .= "sss";
        }

        if (!empty($tipo)) {
            switch ($tipo) {
                case 'estudiante':
                    $where[] = "es.id_usuarios IS NOT NULL";
                    break;
                case 'investigador':
                    $where[] = "inv.id_usuarios IS NOT NULL";
                    break;
                case 'supervisor':
                    $where[] = "su.id_usuarios IS NOT NULL";
                    break;
            }
        }

        if (!empty($where)) {
            $sql .= " WHERE " . implode(" AND ", $where);
        }

        $stmt = $this->con->prepare($sql);
        if (!$stmt) die("Error prepare count: " . $this->con->error);
        if (!empty($types)) $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        return (int)($row['total'] ?? 0);
    }

    // 
    //  LISTADO DE USUARIOS CON FILTROS Y PAGINACIÓN
    // 
    public function obtenerUsuarios($estado = null, $buscar = null, $tipo = null)
    {
        $por_pagina = 6;
        $pagina     = max(1, intval($_GET['pagina'] ?? 1));
        $desde      = ($pagina - 1) * $por_pagina;

        $total         = $this->obtenerCantidadUsuarios($estado, $buscar, $tipo);
        $total_paginas = max(1, ceil($total / $por_pagina));

        $params = [];
        $types  = "";
        $where  = [];

        $sql = "SELECT
                    u.id_usuarios,
                    u.nombre,
                    u.apellido_paterno,
                    u.apellido_materno,
                    CONCAT(u.nombre, ' ', u.apellido_paterno, ' ', u.apellido_materno) AS nombre_completo,
                    u.correo_institucional,
                    u.telefono,
                    u.fecha_registro,
                    u.estado_usuario,
                    COALESCE(ANY_VALUE(r.nombre), 'Sin rol') AS tipo_usuario
                FROM usuarios u
                LEFT JOIN usuarios_roles ur  ON ur.id_usuarios = u.id_usuarios
                LEFT JOIN roles          r   ON r.id_roles     = ur.id_rol
                LEFT JOIN estudiantes    es  ON es.id_usuarios = u.id_usuarios
                LEFT JOIN investigadores inv ON inv.id_usuarios = u.id_usuarios
                LEFT JOIN supervisores   su  ON su.id_usuarios  = u.id_usuarios
                ";

        if (!empty($estado)) {
            $where[] = "u.estado_usuario = ?";
            $params[] = $estado;
            $types   .= "s";
        }

        if (!empty($buscar)) {
            $where[] = "(u.nombre LIKE ? OR u.apellido_paterno LIKE ? OR u.apellido_materno LIKE ?)";
            $params[] = "%$buscar%";
            $params[] = "%$buscar%";
            $params[] = "%$buscar%";
            $types   .= "sss";
        }

        if (!empty($tipo)) {
            switch ($tipo) {
                case 'estudiante':
                    $where[] = "es.id_usuarios IS NOT NULL";
                    break;
                case 'investigador':
                    $where[] = "inv.id_usuarios IS NOT NULL";
                    break;
                case 'supervisor':
                    $where[] = "su.id_usuarios IS NOT NULL";
                    break;
            }
        }

        if (!empty($where)) {
            $sql .= " WHERE " . implode(" AND ", $where);
        }

        $sql .= " GROUP BY u.id_usuarios ORDER BY u.fecha_registro DESC LIMIT ?, ?";
        $params[] = $desde;
        $params[] = $por_pagina;
        $types   .= "ii";

        $stmt = $this->con->prepare($sql);
        if (!$stmt) die("Error prepare listado: " . $this->con->error);
        $stmt->bind_param($types, ...$params);
        if (!$stmt->execute()) die("Error execute listado: " . $stmt->error);

        return json_encode([
            "usuarios"   => $stmt->get_result()->fetch_all(MYSQLI_ASSOC),
            "paginacion" => [
                "total"        => $total,
                "por_pagina"   => $por_pagina,
                "pagina"       => $pagina,
                "total_paginas" => $total_paginas
            ]
        ]);
    }

    // 
    //  DETALLE DE UN USUARIO
    // 
    public function obtenerUsuario($id_usuario)
    {
        $sql = "SELECT
                    u.*,
                    g.genero,
                    COALESCE(ANY_VALUE(r.nombre), 'Sin rol') AS tipo_usuario,
                    es.matricula,
                    c.nombre_carrera,
                    inv.rfc,
                    ga.nombre  AS grado_academico,
                    ns.nombre   AS nivel_sni
                FROM usuarios u
                LEFT JOIN genero_usuario  g   ON g.id_genero    = u.id_genero
                LEFT JOIN usuarios_roles  ur  ON ur.id_usuarios = u.id_usuarios
                LEFT JOIN roles           r   ON r.id_roles     = ur.id_rol
                LEFT JOIN estudiantes     es  ON es.id_usuarios = u.id_usuarios
                LEFT JOIN carreras        c   ON c.id_carrera   = es.id_carrera
                LEFT JOIN investigadores  inv ON inv.id_usuarios = u.id_usuarios
                LEFT JOIN grados_academicos ga ON ga.id_grado   = inv.id_grado
                LEFT JOIN niveles_sni     ns  ON ns.id_nivel    = inv.id_nivel_sni
                LEFT JOIN supervisores    su  ON su.id_usuarios = u.id_usuarios
                WHERE u.id_usuarios = ?";

        $stmt = $this->con->prepare($sql);
        if (!$stmt) die("Error prepare detalle: " . $this->con->error);
        $stmt->bind_param("i", $id_usuario);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    // 
    //  ACTUALIZAR ESTADO (aprobar / cancelar)
    // 
    public function actualizarEstado($id_usuario, $estado)
    {
        $sql = "UPDATE usuarios SET estado_usuario = ? WHERE id_usuarios = ?";
        $stmt = $this->con->prepare($sql);
        if (!$stmt) die("Error prepare update: " . $this->con->error);
        $stmt->bind_param("si", $estado, $id_usuario);
        if (!$stmt->execute()) die("Error execute update: " . $stmt->error);
        return $stmt->affected_rows > 0;
    }

    // 
    //  RECHAZAR CON COMENTARIO
    // 
    public function rechazarUsuario($id_usuario, $comentario)
    {
        // 1. Cambiar estado a 'cancelado'
        $ok = $this->actualizarEstado($id_usuario, 'cancelado');
        if (!$ok) return false;

        // 2. Guardar el comentario de rechazo (tabla notificaciones si existe, aquí lo dejamos para el correo)
        // El correo se envía desde el controlador con PHPMailer
        return true;
    }

    // 
    //  OBTENER CORREO DE USUARIO (para notificación)
    // 
    public function obtenerCorreo($id_usuario)
    {
        $sql = "SELECT correo_institucional, nombre, apellido_paterno FROM usuarios WHERE id_usuarios = ?";
        $stmt = $this->con->prepare($sql);
        $stmt->bind_param("i", $id_usuario);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }
}
