<?php
// Modelos/PlantillaDocumento.php

require_once __DIR__ . '/../Repositorios/PlantillaDocumentoRepositorio.php';

/**
 * PlantillaDocumento (Modelo)
 *
 * Responsabilidad exclusiva: lógica de negocio del módulo de plantillas de documento.
 * Delega toda ejecución SQL a PlantillaDocumentoRepositorio.
 */
class PlantillaDocumento
{
    private PlantillaDocumentoRepositorio $repo;

    public function __construct(mysqli $conn)
    {
        $this->repo = new PlantillaDocumentoRepositorio($conn);
    }


    // 
    // CONSULTAS PRINCIPALES
    // 

    public function obtenerTablaFiltro(?string $buscar, int $filtro): array
    {
        $pagina    = max(1, (int)($_GET['pagina'] ?? 1));
        $porPagina = 6;
        $desde     = ($pagina - 1) * $porPagina;

        $total        = $this->repo->contarPlantillas($buscar, $filtro);
        $totalPaginas = $total > 0 ? (int)ceil($total / $porPagina) : 1;

        return [
            'plantillas' => $this->repo->listarPlantillas($buscar, $filtro, $desde, $porPagina),
            'paginacion' => [
                'total'         => $total,
                'por_pagina'    => $porPagina,
                'pagina'        => $pagina,
                'total_paginas' => $totalPaginas,
            ],
        ];
    }

    public function obtenerTipos_documentos(): array
    {
        return $this->repo->listarTiposDocumentos();
    }

    public function obtenerInfoTipos(int $id_tipo_documento): array
    {
        $resultado = $this->repo->obtenerInfoTipo($id_tipo_documento);

        if (!$resultado) {
            throw new Exception("Tipo de documento no encontrado (ID: {$id_tipo_documento})");
        }

        return $resultado;
    }

    public function obtenerInfoPlantilla(int $id_plantilla): array
    {
        $resultado = $this->repo->obtenerInfoPlantilla($id_plantilla);

        if (!$resultado) {
            throw new Exception("Plantilla no encontrada (ID: {$id_plantilla})");
        }

        return $resultado;
    }

    public function obtenerPorId(int $id_plantilla): ?array
    {
        return $this->repo->buscarPorId($id_plantilla);
    }

    public function obtenerPlantillaPorId(int $id_plantilla): ?array
    {
        return $this->repo->buscarArchivoPlantilla($id_plantilla);
    }

    public function obtenerSiguienteVersion(int $id_tipo_documento): int
    {
        return $this->repo->obtenerSiguienteVersion($id_tipo_documento);
    }


    // 
    // OPERACIONES DE ESCRITURA
    // 

    /**
     * @return int  id_documento generado.
     * @throws Exception
     */
    public function registrarDocumento(
        string $nombre,
        string $nombre_archivo,
        string $ruta,
        string $tipo_mime,
        string $extension,
        int    $tamano_bytes,
        string $tipo,
        string $visibilidad,
        int    $id_usuario,
        int    $version
    ): int {
        $id = $this->repo->insertarDocumento(
            $nombre, $nombre_archivo, $ruta, $tipo_mime, $extension,
            $tamano_bytes, $tipo, $visibilidad, $id_usuario, $version
        );

        if ($id === 0) {
            throw new Exception('No se pudo registrar el documento en documentos_subidos');
        }

        return $id;
    }

    /**
     * @return int  id_plantilla generado.
     * @throws Exception
     */
    public function registrar(
        int    $id_tipo_documento,
        string $nombre,
        int    $version,
        int    $id_documento
    ): int {
        $id = $this->repo->insertarPlantilla($id_tipo_documento, $nombre, $version, $id_documento);

        if ($id === 0) {
            throw new Exception('No se pudo registrar la plantilla');
        }

        return $id;
    }

    /**
     * @return int  Filas afectadas.
     */
    public function desactivarPorTipo(int $id_tipo_documento): int
    {
        return $this->repo->desactivarPorTipo($id_tipo_documento);
    }

    /**
     * @throws Exception
     */
    public function activarVersion(int $id_plantilla): void
    {
        $this->repo->activarVersion($id_plantilla);
    }

    public function bloquearTabla(int $id_tipo_documento): void
    {
        $this->repo->bloquearTabla($id_tipo_documento);
    }


    // 
    // HISTORIAL / LÍNEA DE TIEMPO
    // 

    public function registrarHistorial(
        int    $id_plantilla,
        int    $id_usuario,
        string $accion,
        string $descripcion
    ): void {
        $this->repo->insertarHistorial($id_plantilla, $id_usuario, $accion, $descripcion);
    }

    public function linea_tiempo(int $id_tipo_documento, int $pagina = 1, int $porPagina = 5): array
    {
        $pagina = max(1, $pagina);
        $desde  = ($pagina - 1) * $porPagina;

        $total        = $this->repo->contarHistorial($id_tipo_documento);
        $totalPaginas = $total > 0 ? (int)ceil($total / $porPagina) : 1;

        $historial = $this->repo->listarHistorial($id_tipo_documento, $desde, $porPagina);

        $agrupado = [];
        foreach ($historial as $item) {
            $agrupado['Versión ' . $item['version']][] = $item;
        }

        return [
            'datos'      => $agrupado,
            'paginacion' => [
                'total'         => $total,
                'por_pagina'    => $porPagina,
                'pagina'        => $pagina,
                'total_paginas' => $totalPaginas,
            ],
        ];
    }
}