<?php

require_once __DIR__ . '/../Repository/tematica_repository.php';

class Tematica
{
    private TematicaRepositorio $repositorio;

    public function __construct(mysqli $conn)
    {
        $this->repositorio = new TematicaRepositorio($conn);
    }

    /*
    
    | LISTADO PRINCIPAL
    
    */

    public function obtenerTematicas(string $rol, ?string $buscar = null): string
    {
        if (strtolower($rol) !== 'supervisor') {
            return json_encode([
                'tematica' => [],
                'paginacion' => [
                    'total' => 0,
                    'por_pagina' => 6,
                    'pagina' => 1,
                    'total_paginas' => 1
                ]
            ]);
        }

        $total = $this->repositorio->contarTematicas(2, $buscar);

        $por_pagina = 6;
        $pagina = empty($_GET['pagina']) ? 1 : max(1, intval($_GET['pagina']));
        $desde = ($pagina - 1) * $por_pagina;

        $tematicas = $this->repositorio->listarTematicas(
            $buscar,
            $desde,
            $por_pagina
        );

        return json_encode([
            'tematica' => $tematicas,
            'paginacion' => [
                'total' => $total,
                'por_pagina' => $por_pagina,
                'pagina' => $pagina,
                'total_paginas' => max(1, ceil($total / $por_pagina))
            ]
        ]);
    }

    /*
    
    | EDITAR
    
    */

    public function obtenerTematicasEditar(int $id_tematica): array
    {
        return [
            'tematica' => $this->repositorio->buscarTematicaPorId($id_tematica),
            'subtematicas' => $this->repositorio->buscarSubtematicas($id_tematica, true)
        ];
    }

    /*
    
    | DETALLES
    
    */

    public function obtenerTematicasDetalles(int $id_tematica): array
    {
        return [
            'tematica' => $this->repositorio->buscarTematicaPorId($id_tematica),
            'subtematicas' => $this->repositorio->buscarSubtematicas($id_tematica, false)
        ];
    }

    /*
    
    | FILTROS
    
    */

    public function obtenerTematicasTablaFiltro(
        int $filtro,
        string $rol,
        ?string $buscar = null
    ): string {

        if (strtolower($rol) !== 'supervisor') {
            return json_encode([
                'tematica' => [],
                'paginacion' => []
            ]);
        }

        $total = $this->repositorio->contarTematicas($filtro, $buscar);

        $por_pagina = 6;
        $pagina = empty($_GET['pagina']) ? 1 : max(1, intval($_GET['pagina']));
        $desde = ($pagina - 1) * $por_pagina;

        $tematicas = $this->repositorio->listarTematicasFiltro(
            $filtro,
            $buscar,
            $desde,
            $por_pagina
        );

        return json_encode([
            'tematica' => $tematicas,
            'paginacion' => [
                'total' => $total,
                'por_pagina' => $por_pagina,
                'pagina' => $pagina,
                'total_paginas' => max(1, ceil($total / $por_pagina))
            ]
        ]);
    }

    /*
    
    | CONTEO
    
    */

    public function obtenerCantidadTematica(
        int $numerofiltro,
        string $rol,
        ?string $buscar = null
    ): int {

        if (strtolower($rol) !== 'supervisor') {
            return 0;
        }

        return $this->repositorio->contarTematicas(
            $numerofiltro,
            $buscar
        );
    }

    /*
    
    | CRUD TEMÁTICA
    
    */

    public function registrarTematica(
        string $nombre,
        string $descripcion
    ): int {

        return $this->repositorio->insertarTematica(
            $nombre,
            $descripcion
        );
    }

    public function editarTematica(
        string $nombre,
        string $descripcion,
        int $id_tematica
    ): bool {

        $this->repositorio->actualizarTematica(
            $nombre,
            $descripcion,
            $id_tematica
        );

        return true;
    }

    public function eliminar_tematica(
        int $id_tematica,
        int $estado
    ): bool {

        $this->repositorio->cambiarEstadoTematica(
            $id_tematica,
            $estado
        );

        return true;
    }

    /*
    
    | CRUD SUBTEMÁTICAS
    
    */

    public function registrarsubtematica(
        int $id_tematica,
        string $nombre_subtematica
    ): bool {

        $this->repositorio->insertarSubtematica(
            $id_tematica,
            $nombre_subtematica
        );

        return true;
    }

    public function editarSubtematica(
        int $id_subtematica,
        string $nombre_subtematica
    ): bool {

        $this->repositorio->actualizarSubtematica(
            $id_subtematica,
            $nombre_subtematica
        );

        return true;
    }

    public function eliminar_subtematica(
        int $id_subtematica,
        int $estado
    ): bool {

        $this->repositorio->cambiarEstadoSubtematica(
            $id_subtematica,
            $estado
        );

        return true;
    }

    public function obtenerIdsSubtematicas(
        int $id_tematica
    ): array {

        return $this->repositorio->obtenerIdsSubtematicas(
            $id_tematica
        );
    }

    /*
    
    | VALIDACIÓN
    
    */

    public function comparar_Duplicidad_Subtematica(
        int $id_tematica,
        string $nombre,
        mixed $id_excluir = null
    ): void {

        $idExcluir = (
            !empty($id_excluir)
            && $id_excluir !== 'nuevo'
        )
            ? (int)$id_excluir
            : null;

        if (
            $this->repositorio->existeSubtematicaDuplicada(
                $id_tematica,
                $nombre,
                $idExcluir
            )
        ) {
            throw new Exception(
                "La subtemática '$nombre' ya existe en esta temática."
            );
        }
    }
}