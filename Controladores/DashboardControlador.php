<?php
require_once __DIR__ . '/../Modelos/DashboardModelo.php';

class DashboardControlador
{
    private $modelo;
    private $rol;
    private $id_usuario;

    public function __construct($conn, string $rol, int $id_usuario)
    {
        $this->modelo     = new DashboardModelo($conn);
        $this->rol        = strtolower($rol);
        $this->id_usuario = $id_usuario;
    }

    public function getDatos(): array
    {
        $proyectos = $this->modelo->getProyectosPorRol($this->rol, $this->id_usuario);

        // Progreso del primer proyecto
        $primer_proyecto = $proyectos[0] ?? null;
        $porcentaje = $primer_proyecto
            ? $this->modelo->getProgresoProyecto($primer_proyecto['id_proyectos'])
            : 0;

        // Tareas (no aplica a supervisor)
        $tareas = [];
        if ($this->rol !== 'supervisor') {
            $tareas = $this->modelo->getTareasUsuario($this->id_usuario);
        }

        // Modificaciones
        $proyectos_ids  = $this->modelo->getProyectosIds($this->id_usuario);
        $modificaciones = $this->modelo->getModificaciones($this->rol, $proyectos_ids);

        return [
            'proyectos'       => $proyectos,
            'primer_proyecto' => $primer_proyecto,
            'porcentaje'      => $porcentaje,
            'tareas'          => $tareas,
            'modificaciones'  => $modificaciones,
            'rol'             => $this->rol,
            'mostrar_btn'     => in_array($this->rol, ['profesor', 'supervisor']),
        ];
    }
}