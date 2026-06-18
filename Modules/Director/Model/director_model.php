<?php
// Modelos/Director.php

require_once __DIR__ . '/../Repository/director_repository.php';

/**
 * Director (Modelo)
 *
 * Responsabilidad exclusiva: lógica de negocio del módulo de directores.
 * Delega toda ejecución SQL a DirectorRepositorio.
 */
class Director
{
    private DirectorRepositorio $repo;

    public function __construct(mysqli $conn)
    {
        $this->repo = new DirectorRepositorio($conn);
    }


    // 
    // TABLA PRINCIPAL CON PAGINACIÓN
    // 

    public function obtenerTablaFiltro(?string $buscar, int $filtro): array
    {
        $por_pagina    = 6;
        $pagina        = max(1, (int)($_GET['pagina'] ?? 1));
        $desde         = ($pagina - 1) * $por_pagina;
        $total         = $this->repo->contarDirectores($buscar, $filtro);
        $total_paginas = max(1, (int)ceil($total / $por_pagina));

        return [
            'director'   => $this->repo->listarDirectores($buscar, $filtro, $desde, $por_pagina),
            'paginacion' => compact('total', 'por_pagina', 'pagina') + ['total_paginas' => $total_paginas],
        ];
    }

    public function obtenerCantidadDirector(?string $buscar = null, int $filtro = 2): int
    {
        return $this->repo->contarDirectores($buscar, $filtro);
    }


    // 
    // DETALLE / EDICIÓN
    // 

    public function obtenerEditar(int $id_director): array
    {
        $fila = $this->repo->buscarParaEditar($id_director);

        if (!$fila) {
            throw new Exception('Director no encontrado');
        }

        return $fila;
    }

    public function obtenerDetalles(int $id_director): array
    {
        $fila = $this->repo->buscarDetalle($id_director);

        if (!$fila) {
            throw new Exception('Director no encontrado');
        }

        return $fila;
    }

    public function obtenerGradosActivos(): array
    {
        return $this->repo->listarGradosActivos();
    }


    // 
    // CRUD
    // 

    public function registrarDirector(
        int $id_grado,
        string $nombre,
        string $apellido,
        ?string $correo,
        ?string $telefono,
        ?string $fecha_inicio,
        ?string $fecha_final
    ): int {
        $validacion = $this->repo->verificarDirector($correo);

        if ($validacion['activo']) {
            throw new Exception('Conflicto: ya existe un director activo con ese correo.');
        }

        $id = $this->repo->insertarDirector(
            $id_grado, $nombre, $apellido, $correo, $telefono, $fecha_inicio, $fecha_final
        );

        $this->repo->insertarHistorial($id, 'CREACION', "Se registró el director {$nombre} {$apellido}");

        return $id;
    }

    public function editarDirector(
        int $id_grado,
        string $nombre,
        string $apellido,
        ?string $correo,
        ?string $telefono,
        int $id_director,
        ?string $fecha_inicio,
        ?string $fecha_final,
        ?string $motivo_fin
    ): void {
        $this->repo->actualizarDirector(
            $id_grado, $nombre, $apellido, $correo, $telefono,
            $id_director, $fecha_inicio, $fecha_final, $motivo_fin
        );

        $this->repo->insertarHistorial(
            $id_director,
            'ACTUALIZACION',
            "Se actualizaron los datos del director {$nombre} {$apellido}"
        );
    }

    public function reactivar(int $id_director): void
    {
        $datos = $this->repo->buscarCorreoPorId($id_director);

        if (!$datos) {
            throw new Exception('No se pudieron obtener datos del director.');
        }

        if (!empty($datos['correo'])) {
            $validacion = $this->repo->verificarDirector($datos['correo']);
            if ($validacion['activo']) {
                throw new Exception('Conflicto: ya existe un director activo con el mismo correo.');
            }
        }

        $afectadas = $this->repo->reactivarDirector($id_director);

        if ($afectadas === 0) {
            throw new Exception('El director ya estaba activo o no se pudo actualizar.');
        }

        $this->repo->insertarHistorial($id_director, 'ACTUALIZACION', 'El director fue reactivado');
    }

    public function eliminarDirector(int $id_director): int
    {
        $filas = $this->repo->desactivarDirector($id_director);

        if ($filas > 0) {
            $this->repo->insertarHistorial($id_director, 'BAJA', 'El director fue desactivado');
        }

        return $filas;
    }


    // 
    // VERIFICACIONES / UTILIDADES
    // 

    public function verificarDirector(?string $correo): array
    {
        return $this->repo->verificarDirector($correo);
    }

    public function verificarDirectorOtroId(int $id_director, ?string $correo): array
    {
        return $this->repo->verificarDirectorOtroId($id_director, $correo);
    }

    public function obtenerPorId(int $id_director, bool $forUpdate = false): ?array
    {
        return $this->repo->buscarPorId($id_director, $forUpdate);
    }

    public function desactivarDirectoresVencidos(): void
    {
        $this->repo->desactivarDirectoresVencidos();
    }

    public function bloquearTabla(): void
    {
        $this->repo->bloquearTabla();
    }


    // 
    // HISTORIAL / LÍNEA DE TIEMPO
    // 

    public function registrarHistorial(int $id_director, string $accion, string $descripcion): void
    {
        $this->repo->insertarHistorial($id_director, $accion, $descripcion);
    }

    public function lineaTiempoDirector(int $id_director, int $pagina = 1): array
    {
        $pagina     = max(1, $pagina);
        $por_pagina = 5;
        $desde      = ($pagina - 1) * $por_pagina;

        $total         = $this->repo->contarHistorial($id_director);
        $total_paginas = max(1, (int)ceil($total / $por_pagina));

        $historial = $this->repo->listarHistorial($id_director, $desde, $por_pagina);

        $agrupado = [];
        foreach ($historial as $item) {
            $agrupado[date('d/m/Y', strtotime($item['fecha']))][] = $item;
        }

        return [
            'datos'      => $agrupado,
            'paginacion' => compact('total', 'por_pagina', 'pagina') + ['total_paginas' => $total_paginas],
        ];
    }
}
