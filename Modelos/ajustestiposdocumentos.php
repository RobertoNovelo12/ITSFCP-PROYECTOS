<?php
// Modelos/ajustestiposdocumentos.php

require_once __DIR__ . '/../publico/config/conexion.php';
require_once __DIR__ . '/BaseModelo.php';

class ajustesdocumentos extends BaseModelo
{
    public function __construct(mysqli $conn)
    {
        parent::__construct($conn);
    }



    // 
    // TABLA PRINCIPAL
    // 

    /**
     * Devuelve las filas de la tabla filtradas por categoría.
     *
     * @param string[] $filtros  p.ej. ['proceso', 'final']
     */
    public function obtenerTablaFiltro(array $filtros): array
    {
        $placeholders = implode(',', array_fill(0, count($filtros), '?'));
        $types        = str_repeat('s', count($filtros));

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
             ORDER BY categoria",
            $types,
            $filtros
        );
    }


    // 
    // DATOS PARA EL FORMULARIO DE EDICIÓN
    // 

    public function obtenerEditar(int $id_tipo_documento): array
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
            "i",
            [$id_tipo_documento],
            false          // fetch_assoc
        );

        return $fila ?? [];
    }


    // 
    // EDITAR
    // 

    /**
     * Actualiza descripción y orden de un tipo de documento.
     * Debe ejecutarse dentro de una transacción.
     *
     * @return int  El mismo $id_tipo_documento recibido.
     */
    public function editar(string $descripcion, int $orden, int $id_tipo_documento): int
    {
        $this->ejecutar(
            "UPDATE tipo_documento
             SET descripcion = ?, orden = ?, fecha_modificacion = NOW()
             WHERE id_tipo_documento = ?",
            "sii",
            [$descripcion, $orden, $id_tipo_documento]
        );

        return $id_tipo_documento;
    }


    // 
    // REACTIVAR
    // 

    /**
     * Reactiva un tipo de documento previamente desactivado.
     * Debe ejecutarse dentro de una transacción con bloqueo previo.
     *
     * @throws Exception Si el registro no existe o ya estaba activo.
     */
    public function reactivar(int $id_tipo_documento): void
    {
        // Confirmar existencia (con bloqueo si viene de bloquear_tabla)
        $registro = $this->obtenerPorId($id_tipo_documento, true);

        if (!$registro) {
            throw new Exception("Tipo de documento no encontrado.");
        }

        $this->ejecutar(
            "UPDATE tipo_documento
             SET estado = 1, fecha_modificacion = NOW()
             WHERE id_tipo_documento = ?
               AND estado = 0",
            "i",
            [$id_tipo_documento]
        );

        // Verificar que realmente se actualizó una fila
        if ($this->conn->affected_rows === 0) {
            throw new Exception("El tipo de documento ya estaba activo o no se pudo actualizar.");
        }
    }


    // 
    // DESACTIVAR  (soft delete)
    // 

    /**
     * Desactivación lógica de un tipo de documento.
     *
     * @return int  Filas afectadas (≥ 1 éxito, 0 ya estaba desactivado).
     */
    public function desactivar(int $id_tipo_documento): int
    {
        $this->ejecutar(
            "UPDATE tipo_documento
             SET estado = 0, fecha_modificacion = NOW()
             WHERE id_tipo_documento = ?
               AND estado <> 0",
            "i",
            [$id_tipo_documento]
        );

        return $this->conn->affected_rows;
    }


    // 
    // BLOQUEO OPTIMISTA PARA CONCURRENCIA
    // 

    /**
     * Bloquea las filas activas para evitar condiciones de carrera.
     * Debe ejecutarse dentro de una transacción (InnoDB).
     */
    public function bloquear_tabla(): void
    {
        $this->ejecutar(
            "SELECT id_tipo_documento
             FROM tipo_documento
             WHERE estado = 1
             FOR UPDATE"
        );
    }


    // 
    // OBTENER POR ID
    // 

    /**
     * Devuelve el estado de un tipo de documento por su ID.
     * Con $forUpdate = true agrega FOR UPDATE (requiere transacción activa).
     *
     * @return array|null  ['estado' => 0|1] o null si no existe.
     */
    public function obtenerPorId(int $id_tipo_documento, bool $forUpdate = false): ?array
    {
        $sql = "SELECT estado
                FROM tipo_documento
                WHERE id_tipo_documento = ?";

        if ($forUpdate) {
            $sql .= " FOR UPDATE";
        }

        $fila = $this->ejecutar($sql, "i", [$id_tipo_documento], false);

        return $fila ?: null;
    }
}