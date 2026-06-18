<?php
require_once __DIR__ . '/../Model/calendario_model.php';

class CalendarioControlador
{
    private $modelo;
    private string $rol;
    private int $id_usuario;

    public function __construct($conn, string $rol, int $id_usuario)
    {
        $this->modelo     = new CalendarioModelo($conn);
        $this->rol        = strtolower($rol);
        $this->id_usuario = $id_usuario;
    }

    public function getEventos(): array
    {
        // Tareas según rol
        if ($this->rol === 'supervisor') {
            $tareas = $this->modelo->getEventosSupervisor();
        } elseif (in_array($this->rol, ['investigador', 'profesor'])) {
            $tareas = $this->modelo->getEventosInvestigador($this->id_usuario);
        } else {
            $tareas = $this->modelo->getEventosEstudiante($this->id_usuario);
        }

        // Eventos personales (todos los roles)
        $eventos = $this->modelo->getEventosPersonales($this->id_usuario);

        return array_merge($tareas, $eventos);
    }

    public function getProyectos(): array
    {
        if ($this->rol === 'supervisor') {
            return $this->modelo->getProyectosSupervisor();
        }
        return $this->modelo->getProyectosUsuario($this->id_usuario);
    }
}