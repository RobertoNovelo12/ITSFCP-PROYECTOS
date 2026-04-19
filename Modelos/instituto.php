<?php

class Instituto
{
    private $con;

    public function __construct($conn)
    {
        $this->con = $conn;
    }

    public function obtenerDetalles(): array
    {
        $sql = "SELECT * FROM instituto LIMIT 1";

        $res = $this->con->query($sql);
        return $res->fetch_assoc();
    }

    public function obtenerDirectores()
    {
        $sql = "SELECT id_director, nombre, apellido, estado FROM director";
        $res = $this->con->query($sql);

        $directores = [];

        while ($row = $res->fetch_assoc()) {
            $directores[] = $row;
        }

        return $directores;
    }

    public function editar($id, $nombre, $unidad, $direccion, $estado, $correo, $ciudad, $clave, $telefono, $id_director)
{
    $sql = "UPDATE instituto SET 
            nombre=?,
            unidad_academica=?,
            direccion=?,
            estado=?,
            correo_instituto=?,
            ciudad=?,
            clave_plantel=?,
            telefono=?,
            id_director=?
            WHERE id_instituto=?";

    $stmt = $this->con->prepare($sql);

    $stmt->bind_param(
        "ssssssssii",
        $nombre,
        $unidad,
        $direccion,
        $estado,
        $correo,
        $ciudad,
        $clave,
        $telefono,
        $id_director,
        $id
    );

    $stmt->execute();
}

    public function bloquear_tabla()
    {
        $sql = "SELECT id_instituto FROM instituto FOR UPDATE";
        $this->con->query($sql);
    }

    public function validar($id_director)
    {
        $idDirector = intval($id_director);

        $sql = "SELECT estado FROM director WHERE id_director = ?";
        $stmt = $this->con->prepare($sql);
        $stmt->bind_param("i", $idDirector);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();

        if (!$res || $res['estado'] !== 1) {
            throw new Exception("Director inválido o inactivo");
        }
    }
}
