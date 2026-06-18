<?php
// Repositorios/InstitutoRepositorio.php

require_once __DIR__ . '/../../../public/incluido/BaseModelo.php';

/**
 * InstitutoRepositorio
 *
 * Responsabilidad exclusiva: ejecutar consultas SQL sobre la tabla `instituto`
 * y tablas relacionadas.
 * No contiene lógica de negocio.
 */
class InstitutoRepositorio extends BaseModelo
{
    public function __construct(mysqli $conn)
    {
        parent::__construct($conn);
    }


    // 
    // CONSULTAS
    // 

    public function obtenerDetalles(): ?array
    {
        return $this->ejecutar(
            'SELECT * FROM instituto LIMIT 1',
            '',
            [],
            false
        ) ?: null;
    }

    public function listarDirectores(): array
    {
        return $this->ejecutar(
            'SELECT id_director, nombre, apellido, estado FROM director ORDER BY nombre ASC'
        );
    }


    // 
    // VALIDACIÓN
    // 

    public function buscarEstadoDirector(int $id_director): ?array
    {
        $fila = $this->ejecutar(
            'SELECT estado FROM director WHERE id_director = ?',
            'i',
            [$id_director],
            false
        );

        return $fila ?: null;
    }


    // 
    // CRUD
    // 

    public function actualizarInstituto(
        int $id_instituto,
        string $nombre,
        string $unidad_academica,
        string $direccion,
        string $estado,
        string $correo_instituto,
        string $ciudad,
        string $clave_plantel,
        string $telefono,
        int $id_director
    ): void {
        $this->ejecutar(
            'UPDATE instituto SET
                nombre           = ?,
                unidad_academica = ?,
                direccion        = ?,
                estado           = ?,
                correo_instituto = ?,
                ciudad           = ?,
                clave_plantel    = ?,
                telefono         = ?,
                id_director      = ?
             WHERE id_instituto  = ?',
            'ssssssssii',
            [
                $nombre, $unidad_academica, $direccion, $estado,
                $correo_instituto, $ciudad, $clave_plantel, $telefono,
                $id_director, $id_instituto,
            ]
        );
    }


    // 
    // CONCURRENCIA
    // 

    public function bloquearTabla(): void
    {
        $this->ejecutar('SELECT id_instituto FROM instituto FOR UPDATE');
    }
}
