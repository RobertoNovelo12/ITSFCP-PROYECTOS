<?php
// Modelos/GradoAcademico.php

require_once __DIR__ . '/../Repositorios/GradoAcademicoRepositorio.php';

/**
 * GradoAcademico (Modelo)
 *
 * Responsabilidad exclusiva: lógica de negocio del módulo de grados académicos.
 * Delega toda ejecución SQL a GradoAcademicoRepositorio.
 */
class GradoAcademico
{
    private GradoAcademicoRepositorio $repo;

    public function __construct(mysqli $conn)
    {
        $this->repo = new GradoAcademicoRepositorio($conn);
    }


    // ·············································
    // FILTROS / CONTEOS
    // ·············································

    public function obtenerDatosFiltro(): array
    {
        return $this->repo->obtenerDatosFiltro();
    }


    // ·············································
    // TABLA PRINCIPAL CON PAGINACIÓN
    // ·············································

    public function obtenerTablaFiltro(?string $buscar, int $filtro): array
    {
        $por_pagina    = 6;
        $pagina        = max(1, (int)($_GET['pagina'] ?? 1));
        $desde         = ($pagina - 1) * $por_pagina;
        $total         = $this->repo->contarGrados($buscar, $filtro);
        $total_paginas = max(1, (int)ceil($total / $por_pagina));

        return [
            'grados_academicos' => $this->repo->listarGrados($buscar, $filtro, $desde, $por_pagina),
            'paginacion'        => compact('total', 'por_pagina', 'pagina') + ['total_paginas' => $total_paginas],
        ];
    }

    public function obtenerCantidadGradoAcademico(?string $buscar = null, int $filtro = 2): int
    {
        return $this->repo->contarGrados($buscar, $filtro);
    }


    // ·············································
    // DETALLE / EDICIÓN
    // ·············································

    public function obtenerEditar(int $id_grado): array
    {
        $fila = $this->repo->buscarParaEditar($id_grado);

        if (!$fila) {
            throw new Exception('Grado Académico no encontrado');
        }

        return $fila;
    }

    public function obtenerDetalles(int $id_grado): array
    {
        $fila = $this->repo->buscarDetalle($id_grado);

        if (!$fila) {
            throw new Exception('Grado Académico no encontrado');
        }

        return $fila;
    }


    // ·············································
    // CRUD
    // ·············································

    public function registrarGradoAcademico(string $nombre): int
    {
        $validacion = $this->repo->verificarGrado($nombre);

        if ($validacion['activo']) {
            throw new Exception('Conflicto: ya existe un Grado Académico activo con ese nombre.');
        }

        return $this->repo->insertarGrado($nombre);
    }

    public function editarGradoAcademico(string $nombre, int $id_grado): void
    {
        $this->repo->actualizarGrado($nombre, $id_grado);
    }

    public function reactivar(int $id_grado): void
    {
        $datos = $this->repo->buscarNombrePorId($id_grado);

        if (!$datos) {
            throw new Exception('No se pudieron obtener datos de Grado Académico.');
        }

        $validacion = $this->repo->verificarGrado($datos['nombre']);

        if ($validacion['activo']) {
            throw new Exception('Conflicto: ya existe un Grado Académico activo con el mismo nombre.');
        }

        $afectadas = $this->repo->reactivarGrado($id_grado);

        if ($afectadas === 0) {
            throw new Exception('El registro ya estaba activo o no se pudo actualizar.');
        }
    }

    public function eliminarGradoAcademico(int $id_grado): int
    {
        return $this->repo->desactivarGrado($id_grado);
    }


    // ·············································
    // VERIFICACIONES / UTILIDADES
    // ·············································

    public function verificarGradoAcademico(string $nombre): array
    {
        return $this->repo->verificarGrado($nombre);
    }

    public function verificarGradoOtroId(int $id_grado, string $nombre): array
    {
        return $this->repo->verificarGradoOtroId($id_grado, $nombre);
    }

    public function obtenerPorId(int $id_grado, bool $forUpdate = false): ?array
    {
        return $this->repo->buscarPorId($id_grado, $forUpdate);
    }

    public function bloquearTabla(): void
    {
        $this->repo->bloquearTabla();
    }
}
