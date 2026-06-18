<?php
// Modelos/NivelSNI.php

require_once __DIR__ . '/../Repository/nivel_sni_repository.php';

/**
 * NivelSNI (Modelo)
 *
 * Responsabilidad exclusiva: lógica de negocio del módulo de niveles SNI.
 * Delega toda ejecución SQL a NivelSNIRepositorio.
 */
class NivelSNI
{
    private NivelSNIRepositorio $repo;

    public function __construct(mysqli $conn)
    {
        $this->repo = new NivelSNIRepositorio($conn);
    }


    // 
    // TABLA PRINCIPAL CON PAGINACIÓN
    // 

    public function obtenerTablaFiltro(?string $buscar, int $filtro): array
    {
        $por_pagina    = 6;
        $pagina        = max(1, (int)($_GET['pagina'] ?? 1));
        $desde         = ($pagina - 1) * $por_pagina;
        $total         = $this->repo->contarNiveles($buscar, $filtro);
        $total_paginas = max(1, (int)ceil($total / $por_pagina));

        return [
            'niveles_sni' => $this->repo->listarNiveles($buscar, $filtro, $desde, $por_pagina),
            'paginacion'  => [
                'total'         => $total,
                'por_pagina'    => $por_pagina,
                'pagina'        => $pagina,
                'total_paginas' => $total_paginas,
            ],
        ];
    }

    public function obtenerCantidadNivelSNI(?string $buscar = null, int $filtro = 2): int
    {
        return $this->repo->contarNiveles($buscar, $filtro);
    }


    // 
    // OBTENER REGISTRO
    // 

    public function obtenerEditar(int $id_nivel): array
    {
        $fila = $this->repo->buscarParaEditar($id_nivel);

        if (!$fila) {
            throw new Exception('Nivel SNI no encontrado.');
        }

        return $fila;
    }

    public function obtenerDetalles(int $id_nivel): array
    {
        $fila = $this->repo->buscarDetalle($id_nivel);

        if (!$fila) {
            throw new Exception('Nivel SNI no encontrado.');
        }

        return $fila;
    }

    public function obtenerPorId(int $id_nivel, bool $forUpdate = false): ?array
    {
        return $this->repo->buscarPorId($id_nivel, $forUpdate);
    }


    // 
    // CRUD
    // 

    /**
     * @return int  ID insertado.
     * @throws Exception
     */
    public function registrarNivelSNI(string $nombre): int
    {
        $validacion = $this->repo->verificarNivel($nombre);

        if ($validacion['activo']) {
            throw new Exception('Ya existe un Nivel SNI activo con ese nombre.');
        }

        return $this->repo->insertarNivel($nombre);
    }

    /**
     * @return int  El mismo $id_nivel recibido.
     */
    public function editarNivelSNI(string $nombre, int $id_nivel): int
    {
        $this->repo->actualizarNivel($nombre, $id_nivel);

        return $id_nivel;
    }

    /**
     * @throws Exception
     */
    public function reactivar(int $id_nivel): void
    {
        $registro = $this->repo->buscarPorId($id_nivel, true);

        if (!$registro) {
            throw new Exception('Nivel SNI no encontrado.');
        }

        $datos = $this->repo->buscarNombrePorId($id_nivel);

        if (!$datos) {
            throw new Exception('No se pudieron obtener datos del Nivel SNI.');
        }

        $validacion = $this->repo->verificarNivel($datos['nombre']);

        if ($validacion['activo']) {
            throw new Exception('Ya existe un Nivel SNI activo con el mismo nombre.');
        }

        $afectadas = $this->repo->reactivarNivel($id_nivel);

        if ($afectadas === 0) {
            throw new Exception('El registro ya estaba activo o no se pudo actualizar.');
        }
    }

    /**
     * @return int  Filas afectadas.
     */
    public function eliminar_niveles_sni(int $id_nivel): int
    {
        return $this->repo->desactivarNivel($id_nivel);
    }


    // 
    // VERIFICACIONES DE DUPLICIDAD
    // 

    public function verificarNivelSNI(string $nombre): array
    {
        return $this->repo->verificarNivel($nombre);
    }

    public function obtenerPorIdDiferente(int $id_nivel, string $nombre): array
    {
        return $this->repo->verificarNivelOtroId($id_nivel, $nombre);
    }


    // 
    // CONCURRENCIA
    // 

    public function bloquear_tabla(): void
    {
        $this->repo->bloquearTabla();
    }
}