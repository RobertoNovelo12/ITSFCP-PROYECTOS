<?php
// Modelos/Instituto.php

require_once __DIR__ . '/../Repositorios/InstitutoRepositorio.php';

/**
 * Instituto (Modelo)
 *
 * Responsabilidad exclusiva: lógica de negocio del módulo de instituto.
 * Delega toda ejecución SQL a InstitutoRepositorio.
 */
class Instituto
{
    private InstitutoRepositorio $repo;

    public function __construct(mysqli $conn)
    {
        $this->repo = new InstitutoRepositorio($conn);
    }


    // ·············································
    // CONSULTAS
    // ·············································

    public function obtenerDetalles(): ?array
    {
        return $this->repo->obtenerDetalles();
    }

    public function obtenerDirectores(): array
    {
        return $this->repo->listarDirectores();
    }


    // ·············································
    // VALIDACIÓN
    // ·············································

    /**
     * Verifica que el director exista y esté activo.
     *
     * @throws Exception
     */
    public function validarDirectorActivo(int $id_director): void
    {
        $fila = $this->repo->buscarEstadoDirector($id_director);

        if (!$fila || (int)$fila['estado'] !== 1) {
            throw new Exception('director_inactivo');
        }
    }


    // ·············································
    // CRUD
    // ·············································

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
        $this->repo->actualizarInstituto(
            $id_instituto, $nombre, $unidad_academica, $direccion, $estado,
            $correo_instituto, $ciudad, $clave_plantel, $telefono, $id_director
        );
    }


    // ·············································
    // UTILIDADES
    // ·············································

    public function bloquearTabla(): void
    {
        $this->repo->bloquearTabla();
    }
}
