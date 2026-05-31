<?php
// Modelos/ajustestiposdocumentos.php

require_once __DIR__ . '/../Repositorios/AjustesDocumentosRepositorio.php';

/**
 * ajustesdocumentos (Modelo)
 *
 * Responsabilidad exclusiva: lógica de negocio del módulo de tipos de documentos.
 * Delega toda ejecución SQL a AjustesDocumentosRepositorio.
 *
 * No extiende BaseModelo porque no ejecuta SQL directamente.
 */
class ajustesdocumentos
{
    private AjustesDocumentosRepositorio $repo;

    public function __construct(mysqli $conn)
    {
        $this->repo = new AjustesDocumentosRepositorio($conn);
    }


    // 
    // TABLA PRINCIPAL
    // 

    /**
     * Devuelve los tipos de documento filtrados por categoría.
     *
     * @param string[] $filtros  p.ej. ['proceso', 'final']
     * @return array[]
     */
    public function obtenerTablaFiltro(array $filtros): array
    {
        return $this->repo->listarPorCategorias($filtros);
    }


    // 
    // DATOS PARA EL FORMULARIO DE EDICIÓN
    // 

    /**
     * Devuelve un tipo de documento por su ID para cargar el formulario.
     *
     * @return array  Fila del documento o [] si no existe.
     */
    public function obtenerEditar(int $id_tipo_documento): array
    {
        return $this->repo->buscarPorId($id_tipo_documento) ?? [];
    }


    // 
    // OBTENER POR ID
    // 

    /**
     * Devuelve el registro completo de un tipo de documento.
     * Con $forUpdate = true usa FOR UPDATE (requiere transacción activa).
     *
     * @return array|null
     */
    public function obtenerPorId(int $id_tipo_documento, bool $forUpdate = false): ?array
    {
        return $forUpdate
            ? $this->repo->buscarPorIdParaActualizar($id_tipo_documento)
            : $this->repo->buscarPorId($id_tipo_documento);
    }


    // 
    // EDITAR
    // 

    /**
     * Actualiza descripción y orden de un tipo de documento.
     * Debe ejecutarse dentro de una transacción abierta en el controlador.
     *
     * @return int  El mismo $id_tipo_documento recibido.
     */
    public function editar(string $descripcion, int $orden, int $id_tipo_documento): int
    {
        $this->repo->actualizarDescripcionOrden($descripcion, $orden, $id_tipo_documento);
        return $id_tipo_documento;
    }


    // 
    // DESACTIVAR
    // 

    /**
     * Desactivación lógica de un tipo de documento.
     *
     * @return int  Filas afectadas (>= 1 éxito | 0 ya estaba desactivado).
     */
    public function desactivar(int $id_tipo_documento): int
    {
        return $this->repo->desactivar($id_tipo_documento);
    }


    // 
    // REACTIVAR
    // 

    /**
     * Reactiva un tipo de documento previamente desactivado.
     * Valida que el registro exista y que realmente se actualizó.
     * Debe ejecutarse dentro de una transacción con bloqueo previo.
     *
     * @throws Exception Si el registro no existe o ya estaba activo.
     */
    public function reactivar(int $id_tipo_documento): void
    {
        $registro = $this->repo->buscarPorIdParaActualizar($id_tipo_documento);

        if (!$registro) {
            throw new Exception("Tipo de documento no encontrado.");
        }

        $filas = $this->repo->reactivar($id_tipo_documento);

        if ($filas === 0) {
            throw new Exception("El tipo de documento ya estaba activo o no se pudo actualizar.");
        }
    }


    // 
    // BLOQUEO DE FILAS (concurrencia)
    // 

    /**
     * Bloquea las filas activas para evitar condiciones de carrera.
     * Debe ejecutarse dentro de una transacción activa (InnoDB).
     */
    public function bloquear_tabla(): void
    {
        $this->repo->bloquearFilasActivas();
    }
}
