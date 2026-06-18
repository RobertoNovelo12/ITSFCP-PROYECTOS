<?php
// Modelos/Carrera.php

require_once __DIR__ . '/../Repository/carrera_repository.php';

/**
 * Carrera (Modelo)
 *
 * Responsabilidad exclusiva: lógica de negocio del módulo de carreras.
 * Delega toda ejecución SQL a CarreraRepositorio.
 */
class Carrera
{
    private CarreraRepositorio $repo;

    public function __construct(mysqli $conn)
    {
        $this->repo = new CarreraRepositorio($conn);
    }


    // 
    // TABLA PRINCIPAL
    // 

    public function obtenerTablaFiltro(?string $buscar, int $filtro): array
    {
        $por_pagina    = 6;
        $pagina        = max(1, (int)($_GET['pagina'] ?? 1));
        $desde         = ($pagina - 1) * $por_pagina;
        $total         = $this->repo->contarCarreras($buscar, $filtro);
        $total_paginas = max(1, (int)ceil($total / $por_pagina));

        return [
            'carrera'    => $this->repo->listarCarreras($buscar, $filtro, $desde, $por_pagina),
            'paginacion' => compact('total', 'por_pagina', 'pagina') + ['total_paginas' => $total_paginas],
        ];
    }

    public function obtenerCantidadCarrera(?string $buscar, int $filtro): int
    {
        return $this->repo->contarCarreras($buscar, $filtro);
    }


    // 
    // DATOS PARA FORMULARIOS
    // 

    public function obtenerEditar(int $id_carrera): array
    {
        $fila = $this->repo->buscarParaEditar($id_carrera);

        if (!$fila) {
            throw new Exception('Carrera no encontrada.');
        }

        return $fila;
    }

    public function obtenerDetalles(int $id_carrera): array
    {
        $fila = $this->repo->buscarDetalle($id_carrera);

        if (!$fila) {
            throw new Exception('Carrera no encontrada.');
        }

        return $fila;
    }


    // 
    // CREAR
    // 

    /**
     * Inserta una nueva carrera activa.
     *
     * @return int  ID insertado.
     * @throws Exception
     */
    public function registrarCarrera(string $nombre_carrera): int
    {
        $validacion = $this->repo->verificarCarrera($nombre_carrera);

        if ($validacion['activo'] > 0) {
            throw new Exception('error_duplicado');
        }

        return $this->repo->insertarCarrera($nombre_carrera);
    }


    // 
    // EDITAR
    // 

    /**
     * @return int  El mismo $id_carrera recibido.
     */
    public function editarCarrera(string $nombre_carrera, int $id_carrera): int
    {
        $this->repo->actualizarCarrera($nombre_carrera, $id_carrera);

        return $id_carrera;
    }


    // 
    // REACTIVAR
    // 

    /**
     * @throws Exception
     */
    public function reactivar(int $id_carrera): void
    {
        $datos = $this->repo->buscarNombrePorId($id_carrera);

        if (!$datos) {
            throw new Exception('Carrera no encontrada.');
        }

        $validacion = $this->repo->verificarCarrera($datos['nombre_carrera']);

        if ($validacion['activo'] > 0) {
            throw new Exception('error_duplicado');
        }

        $afectadas = $this->repo->reactivarCarrera($id_carrera);

        if ($afectadas === 0) {
            throw new Exception('La carrera ya estaba activa o no se pudo actualizar.');
        }
    }


    // 
    // DESACTIVAR (soft delete)
    // 

    /**
     * @return int  Filas afectadas.
     */
    public function eliminar_carrera(int $id_carrera): int
    {
        return $this->repo->desactivarCarrera($id_carrera);
    }


    // 
    // BLOQUEO OPTIMISTA PARA CONCURRENCIA
    // 

    public function bloquear_tabla(): void
    {
        $this->repo->bloquearTabla();
    }


    // 
    // OBTENER POR ID
    // 

    public function obtenerPorId(int $id_carrera, bool $forUpdate = false): ?array
    {
        return $this->repo->buscarPorId($id_carrera, $forUpdate);
    }


    // 
    // VERIFICACIÓN DE DUPLICIDAD
    // 

    /**
     * @return array{activo: int, desactivado: int}
     */
    public function verificarCarrera(string $nombre_carrera): array
    {
        return $this->repo->verificarCarrera($nombre_carrera);
    }

    /**
     * @return array{activo: int, desactivado: int}
     */
    public function obtenerPorIdDiferente(int $id_carrera, string $nombre_carrera): array
    {
        return $this->repo->verificarCarreraOtroId($id_carrera, $nombre_carrera);
    }
}
