<?php
// Modelos/AreaConocimiento.php

require_once __DIR__ . '/../Repositorios/AreaConocimientoRepositorio.php';

/**
 * AreaConocimiento (Modelo)
 *
 * Responsabilidad exclusiva: lógica de negocio del módulo de áreas de conocimiento.
 * Delega toda ejecución SQL a AreaConocimientoRepositorio.
 */
class AreaConocimiento
{
    private AreaConocimientoRepositorio $repo;

    public function __construct(mysqli $conn)
    {
        $this->repo = new AreaConocimientoRepositorio($conn);
    }


    // 
    // TABLA PRINCIPAL
    // 

    public function obtenerAreasTablaFiltro(?string $buscar, int $filtro): array
    {
        $por_pagina    = 6;
        $pagina        = max(1, (int)($_GET['pagina'] ?? 1));
        $desde         = ($pagina - 1) * $por_pagina;
        $total         = $this->repo->contarAreas($buscar, $filtro);
        $total_paginas = max(1, (int)ceil($total / $por_pagina));

        return [
            'area'       => $this->repo->listarAreas($buscar, $filtro, $desde, $por_pagina),
            'paginacion' => compact('total', 'por_pagina', 'pagina') + ['total_paginas' => $total_paginas],
        ];
    }


    // 
    // DATOS PARA EL FORMULARIO DE EDICIÓN
    // 

    public function obtenerAreaEditar(int $id_area): array
    {
        $area = $this->repo->buscarAreaPorId($id_area);

        if (!$area) {
            throw new Exception('Área no encontrada');
        }

        return [
            'area'     => $area,
            'subareas' => $this->repo->listarSubareasPorArea($id_area, true),
        ];
    }


    // 
    // DATOS PARA DETALLES
    // 

    public function obtenerAreasDetalles(int $id_area): array
    {
        return [
            'area'     => $this->repo->buscarAreaDetalle($id_area),
            'subareas' => $this->repo->listarSubareasPorArea($id_area),
        ];
    }


    // 
    // CREAR
    // 

    /**
     * Inserta el área y sus subareas.
     *
     * @return int  ID del área creada.
     * @throws Exception
     */
    public function crearAreaCompleta(string $nombre, string $descripcion, array $subareas): int
    {
        $id_area = $this->repo->insertarArea($nombre, $descripcion);

        foreach ($subareas as $sub) {
            $this->repo->insertarSubarea($id_area, $sub);
        }

        return $id_area;
    }

    public function registrarsubarea(int $id_area, string $nombre_subarea): void
    {
        $this->repo->registrarSubareaSimple($id_area, $nombre_subarea);
    }


    // 
    // ACTUALIZAR
    // 

    public function editarArea(string $nombre, string $descripcion, int $id_area): void
    {
        $this->repo->actualizarArea($id_area, $nombre, $descripcion);
    }

    public function editarSubarea(int $id_subarea, string $nombre): void
    {
        $this->repo->actualizarSubarea($id_subarea, $nombre);
    }


    // 
    // SOFT DELETE
    // 

    /**
     * Desactiva (o reactiva) el área y todas sus subareas en cascada.
     *
     * @param int $estado  0 = desactivar, 1 = reactivar
     */
    public function eliminar_area(int $id_area, int $estado): void
    {
        $this->repo->cambiarEstadoArea($id_area, $estado);
    }

    public function eliminar_subarea(int $id_subarea, int $estado): void
    {
        $this->repo->cambiarEstadoSubarea($id_subarea, $estado);
    }


    // 
    // VALIDACIÓN DE DUPLICIDAD
    // 

    /**
     * @throws Exception
     */
    public function comparar_Duplicidad_Subareas(int $id_area, string $nombre, mixed $id_excluir = null): void
    {
        $this->repo->verificarDuplicidadSubarea($id_area, $nombre, $id_excluir);
    }


    // 
    // OBTENER IDS DE SUBAREAS
    // 

    /**
     * @return int[]
     */
    public function obtenerIdsSubareas(int $id_area): array
    {
        return $this->repo->obtenerIdsSubareas($id_area);
    }
}
