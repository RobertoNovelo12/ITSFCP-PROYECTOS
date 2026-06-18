<?php
// Modelos/SolicitudesCartaTerminacion.php

require_once __DIR__ . '/../Repository/solicitudes_carta_terminacion_repository.php';

/**
 * SolicitudesCartaTerminacion (Modelo)
 *
 * Responsabilidad exclusiva: lógica de negocio del módulo de cartas de terminación.
 * Delega toda ejecución SQL a SolicitudesCartaTerminacionRepositorio.
 *
 * La construcción de los fragmentos WHERE dinámicos permanece aquí como lógica
 * de dominio: el repositorio recibe el fragmento ya construido junto con sus
 * parámetros y tipos correspondientes.
 *
 * Firma pública idéntica al modelo original (solicitudes_carta_terminacion),
 * con separación estricta de responsabilidades.
 */
class solicitudes_carta_terminacion
{
    private SolicitudesCartaTerminacionRepositorio $repo;

    public function __construct(mysqli $conn)
    {
        $this->repo = new SolicitudesCartaTerminacionRepositorio($conn);
    }


    // 
    // CATÁLOGOS
    // 

    public function obtenerTodosPeriodos(): array
    {
        return $this->repo->obtenerTodosPeriodos();
    }


    // 
    // RESUMEN (tarjetas dashboard)
    // 

    public function resumenCartas(int $id_periodo = 0): array
    {
        $where_periodo = '';
        $params        = [];
        $types         = '';

        if ($id_periodo) {
            $where_periodo = 'AND p.id_periodos = ?';
            $params[]      = $id_periodo;
            $types        .= 'i';
        }

        return $this->repo->resumenCartas($where_periodo, $params, $types);
    }


    // 
    // LISTADO PAGINADO
    // 

    public function listarCartas(
        string $tipo_filtro = 'Todas',
        string $buscar      = '',
        int    $pagina      = 1,
        int    $id_periodo  = 0
    ): array {
        $por_pagina    = 6;
        $pagina        = max(1, $pagina);
        $desde         = ($pagina - 1) * $por_pagina;

        [$base_where, $params, $types] = $this->construirWhereFiltro(
            $tipo_filtro, $buscar, $id_periodo
        );

        $total         = $this->repo->contarCartas($base_where, $params, $types);
        $total_paginas = max(1, (int)ceil($total / $por_pagina));

        $data = $this->repo->listarCartas($base_where, $params, $types, $desde, $por_pagina);

        return [
            'solicitudes' => $data,
            'paginacion'  => compact('total', 'por_pagina', 'pagina', 'total_paginas'),
        ];
    }


    // 
    // DETALLE
    // 

    public function detalleCarta(int $id_cierre_est): ?array
    {
        return $this->repo->detalleCarta($id_cierre_est);
    }


    // 
    // HISTORIAL
    // 

    public function historialProceso(int $id_proyectos, int $id_usuarios): array
    {
        return $this->repo->historialProceso($id_proyectos, $id_usuarios);
    }


    // 
    // APROBAR / RECHAZAR
    // 

    public function aprobarCarta(int $id_cierre_est, int $id_supervisor): array
    {
        return $this->repo->aprobarCarta($id_cierre_est, $id_supervisor);
    }

    public function rechazarCarta(int $id_cierre_est, int $id_supervisor, string $comentario): array
    {
        return $this->repo->rechazarCarta($id_cierre_est, $id_supervisor, $comentario);
    }


    // 
    // WHERE DINÁMICO (lógica de dominio)
    // 

    /**
     * Construye el fragmento WHERE compartido por contarCartas() y listarCartas().
     *
     * El filtro de estado proviene de un match controlado, por lo que el literal
     * SQL resultante es seguro para interpolar. Periodo y búsqueda siempre
     * viajan como parámetros bind.
     *
     * @return array{0: string, 1: array, 2: string}
     */
    private function construirWhereFiltro(string $tipo_filtro, string $buscar, int $id_periodo): array
    {
        $where_estado = match ($tipo_filtro) {
            'Pendientes' => "AND ce.estado = 'pendiente'",
            'Aprobadas'  => "AND ce.estado = 'aprobado'",
            'Rechazadas' => "AND ce.estado = 'rechazado'",
            default      => '',
        };

        $params = [];
        $types  = '';

        $where_periodo = '';
        if ($id_periodo) {
            $where_periodo = 'AND p.id_periodos = ?';
            $params[]      = $id_periodo;
            $types        .= 'i';
        }

        $where_buscar = '';
        if (!empty($buscar)) {
            $where_buscar = 'AND p.titulo LIKE ?';
            $params[]     = "%$buscar%";
            $types       .= 's';
        }

        $base_where = "WHERE 1 {$where_estado} {$where_periodo} {$where_buscar}";

        return [$base_where, $params, $types];
    }
}
