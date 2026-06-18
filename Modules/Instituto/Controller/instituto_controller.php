<?php
// Controladores/institutoControlador.php

require_once __DIR__ . '/../Model/instituto_model.php';
require_once __DIR__ . '/../../../public/config/conexion.php';
require_once __DIR__ . '/../../../public/incluido/BaseControlador.php';

class institutoControlador extends BaseControlador
{

    // 
    //  CONSULTAS
    // 

    public function indexDetalles(string $rol): ?array
    {
        global $conn;
        try {
            $this->validarAcceso($rol, ['supervisor']);
            return (new Instituto($conn))->obtenerDetalles();
        } catch (Exception $e) {
            error_log($e->getMessage());
            return null;
        }
    }

    public function directores(): array
    {
        global $conn;
        try {
            return (new Instituto($conn))->obtenerDirectores();
        } catch (Exception $e) {
            error_log($e->getMessage());
            return [];
        }
    }

    // 
    //  CRUD — redirige con msg
    // 

    public function editar(string $rol, array $datos): void
    {
        global $conn;
        try {
            $this->validarMetodo('POST');
            $this->validarAcceso($rol, ['supervisor']);

            $id_director = (int)($datos['id_director'] ?? 0);

            $conn->begin_transaction();
            $modelo = new Instituto($conn);

            // Validar que el director seleccionado esté activo
            $modelo->validarDirectorActivo($id_director);

            $modelo->bloquearTabla();

            $modelo->editar(
                (int)($datos['id_instituto']      ?? 0),
                $datos['nombre']                  ?? '',
                $datos['unidad_academica']         ?? '',
                $datos['direccion']                ?? '',
                $datos['estado']                   ?? '',
                $datos['correo_instituto']         ?? '',
                $datos['ciudad']                   ?? '',
                $datos['clave_plantel']            ?? '',
                $datos['telefono']                 ?? '',
                $id_director
            );

            $conn->commit();
            $this->redirigir('exito_editar');

        } catch (Exception $e) {
            if (isset($conn) && $conn->errno !== 0) $conn->rollback();
            error_log($e->getMessage());

            $msg = match ($e->getMessage()) {
                'accion_no_permitida' => 'accion_no_permitida',
                'director_inactivo'   => 'error_director',
                default               => 'error_editar',
            };
            $this->redirigir($msg, 'editar.php');
        }
    }
}