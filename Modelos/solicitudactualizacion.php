<?php
// Modelos/SolicitudActualizacion.php

require_once __DIR__ . '/../Repositorios/SolicitudActualizacionRepositorio.php';

/**
 * SolicitudActualizacion (Modelo)
 *
 * Responsabilidad exclusiva: lógica de negocio del módulo de solicitudes de
 * actualización de SNI / grado académico.
 * Delega toda ejecución SQL a SolicitudActualizacionRepositorio.
 *
 * La construcción del WHERE dinámico permanece aquí como lógica de dominio.
 */
class SolicitudActualizacion
{
    private SolicitudActualizacionRepositorio $repo;

    public function __construct(mysqli $conn)
    {
        $this->repo = new SolicitudActualizacionRepositorio($conn);
    }


    // 
    // LISTADO PAGINADO
    // 

    public function obtenerSolicitudes(
        ?string $estado = null,
        ?string $buscar = null,
        ?string $tipo   = null
    ): array {
        $por_pagina    = 8;
        $pagina        = max(1, (int)($_GET['pagina'] ?? 1));
        $desde         = ($pagina - 1) * $por_pagina;

        [$where, $params, $types] = $this->construirWhere($estado, $buscar, $tipo);

        $total         = $this->repo->contarSolicitudes($where, $params, $types);
        $total_paginas = max(1, (int)ceil($total / $por_pagina));

        $data = $this->repo->listarSolicitudes($where, $params, $types, $desde, $por_pagina);

        return [
            'solicitudes' => $data,
            'paginacion'  => compact('total', 'por_pagina', 'pagina', 'total_paginas'),
        ];
    }


    // 
    // DETALLE
    // 

    public function obtenerDetalle(int $id_solicitud): ?array
    {
        return $this->repo->buscarDetalle($id_solicitud);
    }


    // 
    // HISTORIAL
    // 

    public function historialDeSolicitud(int $id_solicitud): array
    {
        return $this->repo->listarHistorial($id_solicitud);
    }


    // 
    // APROBAR / RECHAZAR
    // 

    public function aprobarSolicitud(int $id_solicitud, int $id_supervisor): array
    {
        return $this->repo->aprobarSolicitud($id_solicitud, $id_supervisor);
    }

    public function rechazarSolicitud(int $id_solicitud, int $id_supervisor, string $comentario): array
    {
        return $this->repo->rechazarSolicitud($id_solicitud, $id_supervisor, $comentario);
    }


    // 
    // DATOS DE CORREO
    // 

    public function obtenerCorreoInvestigador(int $id_usuario): ?array
    {
        return $this->repo->buscarCorreoInvestigador($id_usuario);
    }


    // 
    // WHERE DINÁMICO (lógica de dominio)
    // 

    private function construirWhere(?string $estado, ?string $buscar, ?string $tipo): array
    {
        $cond   = [];
        $params = [];
        $types  = '';

        if (!empty($estado)) {
            $cond[]   = 's.estado = ?';
            $params[] = $estado;
            $types   .= 's';
        }

        if (!empty($buscar)) {
            $cond[]   = '(u.nombre LIKE ? OR u.apellido_paterno LIKE ? OR u.apellido_materno LIKE ?)';
            $like     = "%$buscar%";
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $types   .= 'sss';
        }

        if (!empty($tipo)) {
            $cond[]   = 's.tipo = ?';
            $params[] = $tipo;
            $types   .= 's';
        }

        $where = !empty($cond) ? 'WHERE ' . implode(' AND ', $cond) : '';

        return [$where, $params, $types];
    }
}
