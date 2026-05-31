<?php
// Modelos/LineaInvestigacion.php

require_once __DIR__ . '/../Repositorios/LineaInvestigacionRepositorio.php';

/**
 * Linea (Modelo)
 *
 * Responsabilidad exclusiva: lógica de negocio del módulo de líneas de investigación.
 * Delega toda ejecución SQL a LineaInvestigacionRepositorio.
 */
class Linea
{
    private LineaInvestigacionRepositorio $repo;

    public function __construct(mysqli $conn)
    {
        $this->repo = new LineaInvestigacionRepositorio($conn);
    }


    // 
    // TABLA PRINCIPAL CON PAGINACIÓN
    // 

    public function obtenerTablaFiltro(?string $buscar, int $filtro): array
    {
        $por_pagina    = 6;
        $pagina        = max(1, (int)($_GET['pagina'] ?? 1));
        $desde         = ($pagina - 1) * $por_pagina;
        $total         = $this->repo->contarLineas($buscar, $filtro);
        $total_paginas = max(1, (int)ceil($total / $por_pagina));

        return [
            'linea'      => $this->repo->listarLineas($buscar, $filtro, $desde, $por_pagina),
            'paginacion' => [
                'total'         => $total,
                'por_pagina'    => $por_pagina,
                'pagina'        => $pagina,
                'total_paginas' => $total_paginas,
            ],
        ];
    }

    public function obtenerCantidadLinea(?string $buscar = null, int $filtro = 2): int
    {
        return $this->repo->contarLineas($buscar, $filtro);
    }


    // 
    // OBTENER REGISTRO
    // 

    public function obtenerEditar(int $id_linea): array
    {
        $fila = $this->repo->buscarParaEditar($id_linea);

        if (!$fila) {
            throw new Exception('Línea de investigación no encontrada.');
        }

        return $fila;
    }

    public function obtenerDetalles(int $id_linea): array
    {
        $fila = $this->repo->buscarDetalle($id_linea);

        if (!$fila) {
            throw new Exception('Línea de investigación no encontrada.');
        }

        return $fila;
    }

    public function obtenerPorId(int $id_linea, bool $forUpdate = false): ?array
    {
        return $this->repo->buscarPorId($id_linea, $forUpdate);
    }

    public function obtenerPorNombre(string $nombre): ?array
    {
        return $this->repo->buscarPorNombre($nombre);
    }


    // 
    // CRUD
    // 

    /**
     * @return int  ID insertado.
     * @throws Exception
     */
    public function registrarLinea(string $nombre, string $descripcion): int
    {
        $validacion = $this->repo->verificarLinea($nombre);

        if ($validacion['activo']) {
            throw new Exception('Ya existe una línea de investigación activa con ese nombre.');
        }

        return $this->repo->insertarLinea($nombre, $descripcion);
    }

    /**
     * @return int  El mismo $id_linea recibido.
     */
    public function editarLinea(string $nombre, string $descripcion, int $id_linea): int
    {
        $this->repo->actualizarLinea($nombre, $descripcion, $id_linea);

        return $id_linea;
    }

    /**
     * @throws Exception
     */
    public function reactivar(int $id_linea): void
    {
        $registro = $this->repo->buscarPorId($id_linea, true);

        if (!$registro) {
            throw new Exception('Línea de investigación no encontrada.');
        }

        $datos = $this->repo->buscarNombrePorId($id_linea);

        if (!$datos) {
            throw new Exception('No se pudieron obtener datos de la línea de investigación.');
        }

        $validacion = $this->repo->verificarLinea($datos['nombre']);

        if ($validacion['activo']) {
            throw new Exception('Ya existe una línea de investigación activa con el mismo nombre.');
        }

        $afectadas = $this->repo->reactivarLinea($id_linea);

        if ($afectadas === 0) {
            throw new Exception('La línea ya estaba activa o no se pudo actualizar.');
        }
    }

    /**
     * @return int  Filas afectadas.
     */
    public function eliminar_linea(int $id_linea): int
    {
        return $this->repo->desactivarLinea($id_linea);
    }


    // 
    // VERIFICACIONES DE DUPLICIDAD
    // 

    public function verificarLinea(string $nombre): array
    {
        return $this->repo->verificarLinea($nombre);
    }

    public function obtenerPorIdDiferente(int $id_linea, string $nombre): array
    {
        return $this->repo->verificarLineaOtroId($id_linea, $nombre);
    }


    // 
    // CONCURRENCIA
    // 

    public function bloquear_tabla(): void
    {
        $this->repo->bloquearTabla();
    }
}
