<?php
// Modelos/AjustesDocumentosRepositorio.php

require_once __DIR__ . '/../Modelos/BaseModelo.php';

/**
 * AjustesDocumentosRepositorio
 *
 * Responsabilidad exclusiva: ejecutar consultas SQL sobre la tabla
 * `tipo_documento`. No contiene lógica de negocio.
 *
 * Es instanciado por ajustesdocumentos (el modelo) mediante inyección
 * por constructor.
 */
class AjustesDocumentosRepositorio extends BaseModelo
{
    public function __construct(mysqli $conn)
    {
        parent::__construct($conn);
    }


    // 
    // TABLA PRINCIPAL
    // 

    /**
     * Devuelve los tipos de documento filtrados por categoría.
     *
     * @param string[] $categorias  p.ej. ['proceso', 'final'] | ['proceso'] | ['final']
     * @return array[]
     */
    public function listarPorCategorias(array $categorias): array
    {
        $placeholders = implode(',', array_fill(0, count($categorias), '?'));
        $types        = str_repeat('s', count($categorias));

        return $this->ejecutar(
            "SELECT
                 id_tipo_documento,
                 nombre,
                 descripcion,
                 categoria,
                 orden,
                 fecha_modificacion AS modificar,
                 CASE
                     WHEN estado = 1 THEN 'Activo'
                     WHEN estado = 0 THEN 'Desactivado'
                     ELSE 'Desconocido'
                 END AS estados
             FROM tipo_documento
             WHERE categoria IN ($placeholders)
             ORDER BY categoria, orden ASC",
            $types,
            $categorias
        );
    }


    // 
    // DATOS PARA EL FORMULARIO DE EDICIÓN
    // 

    /**
     * Devuelve un tipo de documento por su ID.
     *
     * @return array|null  Fila o null si no existe.
     */
    public function buscarPorId(int $id_tipo_documento): ?array
    {
        $fila = $this->ejecutar(
            "SELECT
                 id_tipo_documento,
                 nombre,
                 descripcion,
                 categoria,
                 orden,
                 fecha_modificacion AS modificar,
                 CASE
                     WHEN estado = 1 THEN 'Activo'
                     WHEN estado = 0 THEN 'Desactivado'
                     ELSE 'Desconocido'
                 END AS estados
             FROM tipo_documento
             WHERE id_tipo_documento = ?
             ORDER BY id_tipo_documento",
            'i',
            [$id_tipo_documento],
            false
        );

        return $fila ?: null;
    }

    /**
     * Igual que buscarPorId pero con FOR UPDATE (requiere transacción activa).
     * Usado para bloqueo optimista en reactivar().
     *
     * @return array|null
     */
    public function buscarPorIdParaActualizar(int $id_tipo_documento): ?array
    {
        $fila = $this->ejecutar(
            "SELECT estado FROM tipo_documento WHERE id_tipo_documento = ? FOR UPDATE",
            'i',
            [$id_tipo_documento],
            false
        );

        return $fila ?: null;
    }


    // 
    // EDITAR
    // 

    /**
     * Actualiza descripción y orden de un tipo de documento.
     * Debe ejecutarse dentro de una transacción abierta externamente.
     */
    public function actualizarDescripcionOrden(string $descripcion, int $orden, int $id_tipo_documento): void
    {
        $this->ejecutar(
            "UPDATE tipo_documento
             SET descripcion = ?, orden = ?, fecha_modificacion = NOW()
             WHERE id_tipo_documento = ?",
            'sii',
            [$descripcion, $orden, $id_tipo_documento]
        );
    }


    // 
    // DESACTIVAR  (soft-delete o no eliminar por completo, solo desactivar administrativamente)
    // 

    /**
     * Desactivación lógica. Solo actúa si el registro está activo (estado <> 0).
     *
     * @return int  Filas afectadas (>= 1 éxito | 0 ya estaba desactivado).
     */
    public function desactivar(int $id_tipo_documento): int
    {
        $this->ejecutar(
            "UPDATE tipo_documento
             SET estado = 0, fecha_modificacion = NOW()
             WHERE id_tipo_documento = ?
               AND estado <> 0",
            'i',
            [$id_tipo_documento]
        );

        return $this->conn->affected_rows;
    }


    // 
    // REACTIVAR
    // 

    /**
     * Reactiva un tipo de documento desactivado.
     * Debe ejecutarse dentro de una transacción con bloqueo previo
     * (llamar a buscarPorIdParaActualizar primero).
     *
     * @return int  Filas afectadas (>= 1 éxito | 0 ya estaba activo).
     */
    public function reactivar(int $id_tipo_documento): int
    {
        $this->ejecutar(
            "UPDATE tipo_documento
             SET estado = 1, fecha_modificacion = NOW()
             WHERE id_tipo_documento = ?
               AND estado = 0",
            'i',
            [$id_tipo_documento]
        );

        return $this->conn->affected_rows;
    }


    // 
    // BLOQUEO DE FILAS (concurrencia InnoDB)
    // 

    /**
     * Bloquea las filas activas de tipo_documento para evitar condiciones
     * de carrera. Debe ejecutarse dentro de una transacción activa.
     */
    public function bloquearFilasActivas(): void
    {
        $this->ejecutar(
            "SELECT id_tipo_documento FROM tipo_documento WHERE estado = 1 FOR UPDATE"
        );
    }
}
