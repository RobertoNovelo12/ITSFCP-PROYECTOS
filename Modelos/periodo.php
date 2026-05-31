<?php
// Modelos/Periodo.php

require_once __DIR__ . '/../Repositorios/PeriodoRepositorio.php';

/**
 * Periodo (Modelo)
 *
 * Responsabilidad exclusiva: lógica de negocio del módulo de periodos.
 * Delega toda ejecución SQL a PeriodoRepositorio.
 */
class Periodo
{
    private PeriodoRepositorio $repo;

    public function __construct(mysqli $conn)
    {
        $this->repo = new PeriodoRepositorio($conn);
    }


    // 
    // TABLA PRINCIPAL CON PAGINACIÓN
    // 

    public function obtenerPeriodoTablaFiltro(?string $buscar, int $filtro): array
    {
        $pagina        = max(1, (int)($_GET['pagina'] ?? 1));
        $por_pagina    = 6;
        $desde         = ($pagina - 1) * $por_pagina;
        $total         = $this->repo->contarPeriodos($buscar, $filtro);
        $total_paginas = max(1, (int)ceil($total / $por_pagina));

        return [
            'periodo'    => $this->repo->listarPeriodos($buscar, $filtro, $desde, $por_pagina),
            'paginacion' => compact('total', 'por_pagina', 'pagina') + ['total_paginas' => $total_paginas],
        ];
    }

    public function obtenerCantidadPeriodo(?string $buscar = null, int $filtro = 2): int
    {
        return $this->repo->contarPeriodos($buscar, $filtro);
    }


    // 
    // EDITAR / DETALLES
    // 

    public function obtenerPeriodoEditar(int $id_periodos): array
    {
        $fila = $this->repo->buscarParaEditar($id_periodos);

        if (!$fila) {
            throw new Exception('Periodo no encontrado.');
        }

        return $fila;
    }

    public function obtenerPeriodoDetalles(int $id_periodos): array
    {
        $fila = $this->repo->buscarDetalle($id_periodos);

        if (!$fila) {
            throw new Exception('Periodo no encontrado.');
        }

        return $fila;
    }


    // 
    // CREAR / REACTIVAR
    // 

    /**
     * @return int  ID insertado.
     * @throws Exception
     */
    public function registrarPeriodo(
        string  $periodo,
        string  $fecha_inicio,
        string  $fecha_final,
        ?string $fecha_inicio_proyectos = null,
        ?string $fecha_fin_proyectos    = null,
        ?string $fecha_inicio_solicitud = null,
        ?string $fecha_fin_solicitud    = null
    ): int {
        $validacion = $this->repo->verificarPeriodo($periodo, $fecha_inicio, $fecha_final);

        if ($validacion['activo']) {
            throw new Exception('Conflicto: ya existe un periodo activo con ese nombre o fechas.');
        }

        return $this->repo->insertarPeriodo(
            $periodo, $fecha_inicio, $fecha_final,
            $fecha_inicio_proyectos, $fecha_fin_proyectos,
            $fecha_inicio_solicitud, $fecha_fin_solicitud
        );
    }

    /**
     * @return int  Filas afectadas.
     */
    public function actualizarFechasSubperiodos(
        int     $id_periodos,
        ?string $fecha_inicio_proyectos,
        ?string $fecha_fin_proyectos,
        ?string $fecha_inicio_solicitud,
        ?string $fecha_fin_solicitud
    ): int {
        return $this->repo->actualizarFechasSubperiodos(
            $id_periodos,
            $fecha_inicio_proyectos,
            $fecha_fin_proyectos,
            $fecha_inicio_solicitud,
            $fecha_fin_solicitud
        );
    }

    /**
     * @throws Exception
     */
    public function reactivarPeriodo(int $id): void
    {
        $periodo = $this->repo->buscarPorId($id, true);

        if (!$periodo) {
            throw new Exception('Periodo no encontrado.');
        }

        $datos = $this->repo->buscarDatosPorId($id);

        if (!$datos) {
            throw new Exception('No se pudieron obtener datos del periodo.');
        }

        $validacion = $this->repo->verificarPeriodo(
            $datos['periodo'],
            $datos['fecha_inicio'],
            $datos['fecha_final']
        );

        if ($validacion['activo']) {
            throw new Exception('Conflicto: ya existe un periodo activo con mismo nombre o fechas.');
        }

        $afectadas = $this->repo->reactivarPeriodo($id);

        if ($afectadas === 0) {
            throw new Exception('El periodo ya estaba activo o no se pudo actualizar.');
        }
    }


    // 
    // AUXILIARES
    // 

    public function obtenerPorNombre(string $nombre): ?array
    {
        return $this->repo->buscarPorNombre($nombre);
    }

    public function bloquear_tabla(): void
    {
        $this->repo->bloquearTabla();
    }

    /**
     * @return int  Filas afectadas.
     */
    public function eliminar_periodo(int $id_periodo): int
    {
        return $this->repo->desactivarPeriodo($id_periodo);
    }

    public function desactivarActivos(): void
    {
        $this->repo->desactivarTodosActivos();
    }

    public function verificarPeriodo(string $nombre, string $fecha_inicio, string $fecha_fin): array
    {
        return $this->repo->verificarPeriodo($nombre, $fecha_inicio, $fecha_fin);
    }

    public function obtenerPorId(int $id, bool $forUpdate = false): ?array
    {
        return $this->repo->buscarPorId($id, $forUpdate);
    }
}