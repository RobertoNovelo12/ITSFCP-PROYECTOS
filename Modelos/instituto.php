<?php
// Modelos/instituto.php

require_once __DIR__ . '/../publico/config/conexion.php';
require_once __DIR__ . '/BaseModelo.php';

class Instituto extends BaseModelo
{

    // 
    //  CONSULTAS
    // 

    public function obtenerDetalles(): ?array
    {
        return $this->ejecutar(
            "SELECT * FROM instituto LIMIT 1",
            "",
            [],
            false
        );
    }

    public function obtenerDirectores(): array
    {
        return $this->ejecutar(
            "SELECT id_director, nombre, apellido, estado FROM director ORDER BY nombre ASC"
        );
    }

    // 
    //  VALIDACIÓN
    // 

    /**
     * Verifica que el director exista y esté activo.
     * Lanza excepción si no es válido.
     */
    public function validarDirectorActivo(int $id_director): void
    {
        $row = $this->ejecutar(
            "SELECT estado FROM director WHERE id_director = ?",
            "i",
            [$id_director],
            false
        );

        if (!$row || (int)$row['estado'] !== 1) {
            throw new Exception("director_inactivo");
        }
    }

    // 
    //  CRUD
    // 

    public function editar(
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
            "UPDATE instituto SET
                nombre           = ?,
                unidad_academica = ?,
                direccion        = ?,
                estado           = ?,
                correo_instituto = ?,
                ciudad           = ?,
                clave_plantel    = ?,
                telefono         = ?,
                id_director      = ?
             WHERE id_instituto  = ?",
            "ssssssssii",
            [
                $nombre, $unidad_academica, $direccion, $estado,
                $correo_instituto, $ciudad, $clave_plantel, $telefono,
                $id_director, $id_instituto,
            ]
        );
    }

    // 
    //  UTILIDADES
    // 

    public function bloquearTabla(): void
    {
        $this->ejecutar("SELECT id_instituto FROM instituto FOR UPDATE");
    }
}