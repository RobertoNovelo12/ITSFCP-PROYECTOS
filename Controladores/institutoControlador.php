<?php

require_once __DIR__ . '/../Modelos/instituto.php';
require_once __DIR__ . '/../publico/config/conexion.php';

class institutoControlador
{
    private function esSupervisor($rol): bool
    {
        return $rol === 'supervisor';
    }

    public function indexDetalles($rol): array
    {
        global $conn;

        if (!$this->esSupervisor($rol)) return [];

        $modelo = new Instituto($conn);
        return $modelo->obtenerDetalles();
    }

    public function directores()
    {
        global $conn;

        $modelo = new Instituto($conn);
        return $modelo->obtenerDirectores();
    }

    public function editar($datos)
    {
        global $conn;

        $conn->begin_transaction();

        try {
            $modelo = new Instituto($conn);


            // VALIDAR DIRECTOR ACTIVO
            if (!empty($datos['id_director'])) {

                $validacion = $modelo->validar(['id_director']);
                if ($validacion == 0) {
                    throw new Exception("Director inactivo.");
                }
            }

            $modelo->bloquear_tabla();

            $modelo->editar(
                $datos['id_instituto'],
                $datos['nombre'],
                $datos['unidad_academica'],
                $datos['direccion'],
                $datos['estado'],
                $datos['correo_instituto'],
                $datos['ciudad'],
                $datos['clave_plantel'],
                $datos['telefono'],
                $datos['id_director']
            );

            $conn->commit();

            header("Location: index.php?mensaje=1");
            exit;
        } catch (Exception $e) {

            $conn->rollback();
            header("Location: index.php?error=director");
            exit;
        }
    }
    public function listaDirectores()
    {
        global $conn;

        $modelo = new Instituto($conn);
        return $modelo->obtenerDirectores();
    }
}
