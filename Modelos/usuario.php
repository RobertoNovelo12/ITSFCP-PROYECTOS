<?php
// Modelos/usuario.php

require_once __DIR__ . '/../Repositorios/UsuarioRepositorio.php';

/**
 * Usuarios (Modelo)
 *
 * Responsabilidad exclusiva: lógica de negocio del módulo de usuarios.
 * Delega toda ejecución SQL a UsuarioRepositorio.
 *
 * No extiende BaseModelo porque no ejecuta SQL directamente.
 */
class Usuarios
{
    private UsuarioRepositorio $repo;

    public function __construct(mysqli $conn)
    {
        $this->repo = new UsuarioRepositorio($conn);
    }


    // 
    // LISTADO CON FILTROS Y PAGINACIÓN
    // 

    /**
     * Devuelve el listado paginado de usuarios junto con datos de paginación.
     *
     * @return array  array con claves 'usuarios' y 'paginacion'.
     */
    public function obtenerUsuarios(?string $estado = null, ?string $buscar = null, ?string $tipo = null): array
    {
        $por_pagina    = 6;
        $pagina        = max(1, intval($_GET['pagina'] ?? 1));
        $desde         = ($pagina - 1) * $por_pagina;

        $total         = $this->repo->contarUsuarios($estado, $buscar, $tipo);
        $total_paginas = max(1, (int)ceil($total / $por_pagina));

        $filas = $this->repo->listarUsuarios($estado, $buscar, $tipo, $desde, $por_pagina);

        return [
            'usuarios'   => $filas,
            'paginacion' => [
                'total'        => $total,
                'por_pagina'   => $por_pagina,
                'pagina'       => $pagina,
                'total_paginas'=> $total_paginas,
            ],
        ];
    }


    // 
    // DETALLE DE UN USUARIO
    // 

    /**
     * Devuelve todos los datos de un usuario por su ID.
     *
     * @return array|null
     */
    public function obtenerUsuario(int $id_usuario): ?array
    {
        return $this->repo->buscarPorId($id_usuario);
    }


    // 
    // APROBAR USUARIO
    // 

    /**
     * Cambia el estado del usuario a 'activo'.
     *
     * @return bool  true si se actualizó correctamente.
     */
    public function actualizarEstado(int $id_usuario, string $estado): bool
    {
        return $this->repo->actualizarEstado($id_usuario, $estado);
    }


    // 
    // RECHAZAR USUARIO
    // 

    /**
     * Cambia el estado del usuario a 'cancelado'.
     * El comentario de rechazo se envía por correo desde el controlador.
     *
     * @return bool
     */
    public function rechazarUsuario(int $id_usuario, string $comentario): bool
    {
        return $this->repo->actualizarEstado($id_usuario, 'cancelado');
    }


    // 
    // DATOS PARA NOTIFICACIÓN POR CORREO
    // 

    /**
     * Devuelve correo y nombre del usuario para enviar notificación.
     *
     * @return array|null
     */
    public function obtenerCorreo(int $id_usuario): ?array
    {
        return $this->repo->obtenerCorreo($id_usuario);
    }
}
