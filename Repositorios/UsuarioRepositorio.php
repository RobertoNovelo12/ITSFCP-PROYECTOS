<?php
// Modelos/UsuarioRepositorio.php

require_once __DIR__ . '/../Modelos/BaseModelo.php';

/**
 * UsuarioRepositorio
 *
 * Responsabilidad exclusiva: ejecutar consultas SQL sobre la tabla `usuarios`
 * y sus tablas relacionadas. No contiene lógica de negocio.
 *
 * Es instanciado por Usuarios (el modelo) mediante inyección por constructor.
 */
class UsuarioRepositorio extends BaseModelo
{
    public function __construct(mysqli $conn)
    {
        parent::__construct($conn);
    }


    // ../../publico/incluido/_iconos.php
    // CONTEO PARA PAGINACIÓN
    // ../../publico/incluido/_iconos.php

    /**
     * Devuelve el total de usuarios que coinciden con los filtros dados.
     *
     * @param string|null $estado  'espera' | 'activo' | 'cancelado' | null (todos)
     * @param string|null $buscar  Texto libre sobre nombre / apellidos
     * @param string|null $tipo    'estudiante' | 'investigador' | 'supervisor' | null
     */
    public function contarUsuarios(?string $estado, ?string $buscar, ?string $tipo): int
    {
        $sql = "SELECT COUNT(DISTINCT u.id_usuarios) AS total
                FROM usuarios u
                LEFT JOIN usuarios_roles   ur  ON ur.id_usuarios  = u.id_usuarios
                LEFT JOIN roles            r   ON r.id_roles      = ur.id_rol
                LEFT JOIN estudiantes      es  ON es.id_usuarios  = u.id_usuarios
                LEFT JOIN investigadores   inv ON inv.id_usuarios  = u.id_usuarios
                LEFT JOIN supervisores     su  ON su.id_usuarios   = u.id_usuarios";

        [$where, $params, $types] = $this->construirFiltros($estado, $buscar, $tipo);

        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $fila = $this->ejecutar($sql, $types, $params, false);
        return (int)($fila['total'] ?? 0);
    }


    // ../../publico/incluido/_iconos.php
    // LISTADO CON FILTROS Y PAGINACIÓN
    // ../../publico/incluido/_iconos.php

    /**
     * Devuelve la página de usuarios que coinciden con los filtros dados.
     *
     * @return array[]  Filas de la consulta.
     */
    public function listarUsuarios(
        ?string $estado,
        ?string $buscar,
        ?string $tipo,
        int $desde,
        int $por_pagina
    ): array {
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
            COALESCE(MAX(r.nombre), 'Sin rol') AS tipo_usuario
        FROM usuarios u
        LEFT JOIN usuarios_roles ur  ON ur.id_usuarios  = u.id_usuarios
        LEFT JOIN roles          r   ON r.id_roles      = ur.id_rol
        LEFT JOIN estudiantes    es  ON es.id_usuarios  = u.id_usuarios
        LEFT JOIN investigadores inv ON inv.id_usuarios  = u.id_usuarios
        LEFT JOIN supervisores   su  ON su.id_usuarios   = u.id_usuarios";

        [$where, $params, $types] = $this->construirFiltros($estado, $buscar, $tipo);

        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql     .= ' GROUP BY u.id_usuarios ORDER BY u.fecha_registro DESC LIMIT ?, ?';
        $params[] = $desde;
        $params[] = $por_pagina;
        $types   .= 'ii';

        return $this->ejecutar($sql, $types, $params);
    }


    // ../../publico/incluido/_iconos.php
    // DETALLE DE UN USUARIO
    // ../../publico/incluido/_iconos.php

    /**
     * Devuelve todos los datos de un usuario por su ID, incluidos
     * los campos específicos de estudiante e investigador.
     *
     * @return array|null  Fila o null si no existe.
     */
    public function buscarPorId(int $id_usuario): ?array
    {
        $fila = $this->ejecutar(
            "SELECT
                u.*,
                g.genero,
                COALESCE(r.nombre, 'Sin rol') AS tipo_usuario,
                es.matricula,
                c.nombre_carrera,
                inv.rfc,
                ga.nombre AS grado_academico,
                ns.nombre AS nivel_sni
                FROM usuarios u
                LEFT JOIN genero_usuario    g   ON g.id_genero    = u.id_genero
                LEFT JOIN usuarios_roles    ur  ON ur.id_usuarios = u.id_usuarios
                LEFT JOIN roles             r   ON r.id_roles     = ur.id_rol
                LEFT JOIN estudiantes       es  ON es.id_usuarios = u.id_usuarios
                LEFT JOIN carreras          c   ON c.id_carrera   = es.id_carrera
                LEFT JOIN investigadores    inv ON inv.id_usuarios = u.id_usuarios
                LEFT JOIN grados_academicos ga  ON ga.id_grado    = inv.id_grado
                LEFT JOIN niveles_sni       ns  ON ns.id_nivel    = inv.id_nivel_sni
                LEFT JOIN supervisores      su  ON su.id_usuarios = u.id_usuarios
                WHERE u.id_usuarios = ?",
            'i',
            [$id_usuario],
            false
        );

        return $fila ?: null;
    }


    // ../../publico/incluido/_iconos.php
    // ACTUALIZAR ESTADO
    // ../../publico/incluido/_iconos.php

    /**
     * Cambia el estado de un usuario ('activo' | 'cancelado' | 'espera').
     *
     */
    public function actualizarEstado(int $id_usuario, string $estado): void
    {
        $this->ejecutar(
            "UPDATE usuarios SET estado_usuario = ? WHERE id_usuarios = ?",
            'si',
            [$estado, $id_usuario]
        );
    }


    // ../../publico/incluido/_iconos.php
    // OBTENER CORREO PARA NOTIFICACIÓN
    // ../../publico/incluido/_iconos.php

    /**
     * Devuelve el correo y nombre del usuario para enviarle una notificación.
     *
     * @return array|null  ['correo_institucional', 'nombre', 'apellido_paterno'] o null.
     */
    public function obtenerCorreo(int $id_usuario): ?array
    {
        $fila = $this->ejecutar(
            "SELECT correo_institucional, nombre, apellido_paterno
             FROM usuarios
             WHERE id_usuarios = ?",
            'i',
            [$id_usuario],
            false
        );
        return $fila ?: null;
    }


    // ../../publico/incluido/_iconos.php
    // HELPER PRIVADO: construcción de filtros WHERE
    // ../../publico/incluido/_iconos.php

    /**
     * Construye el array de cláusulas WHERE, parámetros y tipos
     * compartido entre contarUsuarios() y listarUsuarios().
     *
     * @return array{0: string[], 1: array, 2: string}
     */
    private function construirFiltros(?string $estado, ?string $buscar, ?string $tipo): array
    {
        $where  = [];
        $params = [];
        $types  = '';

        if (!empty($estado)) {
            $where[]  = 'u.estado_usuario = ?';
            $params[] = $estado;
            $types   .= 's';
        }

        if (!empty($buscar)) {
            $where[]  = '(u.nombre LIKE ? OR u.apellido_paterno LIKE ? OR u.apellido_materno LIKE ?)';
            $params[] = "%$buscar%";
            $params[] = "%$buscar%";
            $params[] = "%$buscar%";
            $types   .= 'sss';
        }

        if (!empty($tipo)) {
            $where[] = match ($tipo) {
                'estudiante'   => 'es.id_usuarios IS NOT NULL',
                'investigador' => 'inv.id_usuarios IS NOT NULL',
                'supervisor'   => 'su.id_usuarios IS NOT NULL',
                default        => '1',
            };
        }

        return [$where, $params, $types];
    }
}
