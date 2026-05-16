
<?php
//Controlador de proyectos -> Maneja sobre proyectos y solicitudes de integración a proyectos
require_once __DIR__ . '/../Modelos/proyecto.php';
require_once __DIR__ . '/../publico/config/conexion.php';

class ProyectoControlador
{

    // 
    // VALIDACIONES INTERNAS
    // 

    private function rolValido($rol)
    {
        return in_array($rol, ['investigador', 'estudiante', 'supervisor', 'profesor']);
    }

    private function validarAcceso($rol, $permitidos)
    {
        if (!in_array($rol, $permitidos)) {
            throw new Exception("No tienes permisos para realizar esta acción");
        }
    }

    private function validarMetodo($metodo)
    {
        if ($_SERVER['REQUEST_METHOD'] !== $metodo) {
            throw new Exception("Método no permitido");
        }
    }

    // 
    // MÉTODO BASE REUTILIZABLE (proyectos)
    // 

    private function obtenerDatos($id, $rol, $buscar, $filtro = null, $tipo = 'filtro')
    {
        global $conn;
        try {
            if (!$this->rolValido($rol)) return [];

            $proyecto = new Proyectos($conn);
            $proyecto->actualizarProyectosVencidos();
            $proyecto->actualizarEstadoEstudiantesVencidos();

            switch ($tipo) {
                case 'filtro':
                    return $proyecto->obtenerProyectosDatosFiltro($id, $rol);
                case 'tabla':
                default:
                    return $proyecto->obtenerProyectosTablaFiltro($id, $filtro, $rol, $buscar);
            }
        } catch (Exception $e) {
            error_log($e->getMessage());
            return [];
        }
    }

    // 
    // ACCIONES DE PROYECTOS (tabla principal)
    // 

    public function index($id, $rol, $buscar = null)
    {
        return $this->obtenerDatos((int)$id, $rol, $buscar, 0, 'tabla');
    }

    public function filtros($id, $rol)
    {
        return $this->obtenerDatos((int)$id, $rol, null, null, 'filtro');
    }

    public function Total($id, $rol, $buscar = null)
    {
        return $this->obtenerDatos((int)$id, $rol, $buscar, 0, 'tabla');
    }

    public function Cierre($id, $rol, $buscar = null)
    {
        return $this->obtenerDatos((int)$id, $rol, $buscar, 1, 'tabla');
    }

    public function Activos($id, $rol, $buscar = null)
    {
        return $this->obtenerDatos((int)$id, $rol, $buscar, 2, 'tabla');
    }

    public function PorAprobar($id, $rol, $buscar = null)
    {
        return $this->obtenerDatos((int)$id, $rol, $buscar, 3, 'tabla');
    }

    public function Rechazados($id, $rol, $buscar = null)
    {
        return $this->obtenerDatos((int)$id, $rol, $buscar, 4, 'tabla');
    }

    public function PorCerrar($id, $rol, $buscar = null)
    {
        return $this->obtenerDatos((int)$id, $rol, $buscar, 5, 'tabla');
    }

    public function Vencido($id, $rol, $buscar = null)
    {
        return $this->obtenerDatos((int)$id, $rol, $buscar, 6, 'tabla');
    }

    public function Cierrerechazado($id, $rol, $buscar = null)
    {
        return $this->obtenerDatos((int)$id, $rol, $buscar, 7, 'tabla');
    }

    // 
    // NÚMERO DE FILTRO (conversión action -> id_estadoP)
    // 

    public function numerofiltro($action)
    {
        return match ($action) {
            'Total'                     => 0,
            'Cierre'                    => 1,
            'Activos', 'Activo'         => 2,
            'PorAprobar'                => 3,
            'Rechazados'                => 4,
            'PorCerrar'                 => 5,
            'Vencido'                   => 6,
            'Cierrerechazado',
            'CierreRechazado'           => 7,
            default                     => 0,
        };
    }

    // 
    // ENCABEZADOS — separados por módulo
    // 

    /**
     * Encabezados para la tabla principal de /proyectos/index.php
     */
    public function encabezadosProyectos($rol)
    {
        $base = ['ID', 'Título', 'Inicio', 'Fin', 'Estado', 'Período', 'Pendientes', 'Acciones'];

        $estudiante = [
            'ID',
            'Título',
            'Inicio',
            'Fin',
            'Estado Proyecto',
            'Estado Estudiante',
            'Documentación',
            'Período',
            'Pendientes',
            'Acciones'
        ];

        return ($rol === 'estudiante') ? $estudiante : $base;
    }

    /**
     * Encabezados para la tabla de /solicitudes_integracion_proyecto/index.php
     * (se definen directamente en la vista, pero se expone por consistencia)
     */
    public function encabezadosSolicitudes()
    {
        return ['ID', 'Título', 'Tipo solicitud', 'Investigador', 'Periodo', 'Fecha solicitud', 'Estado', 'Acciones'];
    }

    // 
    // OPCIONES DE FILTRO — separadas por módulo
    // 

    /**
     * Opciones del select de filtro para /proyectos/index.php
     */
    public function opcionesProyectos($rol, $filtros)
    {
        if (empty($filtros)) return [];

        $base = [
            'Total'   => "Total ({$filtros[0]['Total']})",
            'Activos' => "Activos ({$filtros[0]['Activos']})",
        ];

        if ($rol === 'estudiante') {
            return $base + [
                'Cierre'    => "Cierre ({$filtros[0]['Cierre']})",
                'PorCerrar' => "Por Cerrar ({$filtros[0]['PorCerrar']})",
                'Vencido'   => "Vencidos ({$filtros[0]['Vencido']})",
            ];
        }

        if (in_array($rol, ['investigador', 'profesor'])) {
            return $base + [
                'PorAprobar'      => "Por Aprobar ({$filtros[0]['PorAprobar']})",
                'Rechazados'      => "Rechazados ({$filtros[0]['Rechazados']})",
                'Cierre'          => "Cierre ({$filtros[0]['Cierre']})",
                'PorCerrar'       => "Por Cerrar ({$filtros[0]['PorCerrar']})",
                'Cierrerechazado' => "Cierre rechazado ({$filtros[0]['Cierrerechazado']})",
                'Vencido'         => "Vencidos ({$filtros[0]['Vencido']})",
            ];
        }

        // Supervisor: en la vista de proyectos solo ve aprobados/activos
        if ($rol === 'supervisor') {
            return $base + [
                'PorCerrar'       => "Por Cerrar ({$filtros[0]['PorCerrar']})",
                'Cierre'          => "Cierre ({$filtros[0]['Cierre']})",
                'Cierrerechazado' => "Cierre rechazado ({$filtros[0]['Cierrerechazado']})",
                'Vencido'         => "Vencidos ({$filtros[0]['Vencido']})",
            ];
        }

        return [];
    }

    /**
     * Opciones del select de filtro para /solicitudes_integracion_proyecto/index.php
     * Se manejan como tabs en la vista; este método es de apoyo.
     */
    public function opcionesSolicitudes()
    {
        return [
            'Todas'      => 'Todas',
            'Creacion'   => 'Creación',
            'Cierre'     => 'Cierre',
            'Pendientes' => 'Pendientes',
        ];
    }

    // 
    // ESTILO DE ESTADO (badge Bootstrap)
    // 

    public function EstiloEstado($estado)
    {
        return match ($estado) {
            'Cierre rechazado', 'Rechazado', 'Terminado' => 'danger',
            'Por cerrar', 'Por aprobar', 'Pendiente', 'en_proceso', 'en_correccion'   => 'warning',
            'Vencido'                                    => 'dark',
            'Activo', 'carta_subida', 'liberado_supervisor'   => 'success',
            'Cierre'                                     => 'secondary',
            default                                      => 'info',
        };
    }

    // 
    // BOTONES INDIVIDUALES
    // 

    public function obtenerbotones($tipo, $id_proyecto, $id_usuario = null)
    {
        $boton = '';
        switch ($tipo) {

            case 'Detalles':
                $boton = '<a href="detalles.php?id_proyectos=' . $id_proyecto . '" class="btn btn-primary" data-bs-toggle="tooltip" data-bs-title="Ver detalles del proyecto">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-eye-fill" viewBox="0 0 16 16">
                        <path d="M10.5 8a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0"/><path d="M0 8s3-5.5 8-5.5S16 8 16 8s-3 5.5-8 5.5S0 8 0 8m8 3.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7"/>
                    </svg></a>';
                break;

            case 'Tareas':
                $boton = '<a href="../Tareas/index.php?id_proyectos=' . $id_proyecto . '" class="btn btn-info" data-bs-toggle="tooltip" data-bs-title="Tareas">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" class="bi bi-list-task" viewBox="0 0 16 16">
                        <path fill-rule="evenodd" d="M2 2.5a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5V3a.5.5 0 0 0-.5-.5zM3 3H2v1h1z"/>
                        <path d="M5 3.5a.5.5 0 0 1 .5-.5h9a.5.5 0 0 1 0 1h-9a.5.5 0 0 1-.5-.5M5.5 7a.5.5 0 0 0 0 1h9a.5.5 0 0 0 0-1zm0 4a.5.5 0 0 0 0 1h9a.5.5 0 0 0 0-1z"/>
                        <path fill-rule="evenodd" d="M1.5 7a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5H2a.5.5 0 0 1-.5-.5zM2 7h1v1H2zm0 3.5a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5zm1 .5H2v1h1z"/>
                    </svg></a>';
                break;

            case 'Ver Tareas Alumnos':
                $boton = '<a href="../Tareas/tareas_estudiante.php?id_usuario=' . $id_usuario . '&id_proyectos=' . $id_proyecto . '" class="btn btn-info" data-bs-toggle="tooltip" data-bs-title="Ver Tareas">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" class="bi bi-list-task" viewBox="0 0 16 16">
                        <path fill-rule="evenodd" d="M2 2.5a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5V3a.5.5 0 0 0-.5-.5zM3 3H2v1h1z"/>
                        <path d="M5 3.5a.5.5 0 0 1 .5-.5h9a.5.5 0 0 1 0 1h-9a.5.5 0 0 1-.5-.5M5.5 7a.5.5 0 0 0 0 1h9a.5.5 0 0 0 0-1zm0 4a.5.5 0 0 0 0 1h9a.5.5 0 0 0 0-1z"/>
                        <path fill-rule="evenodd" d="M1.5 7a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5H2a.5.5 0 0 1-.5-.5zM2 7h1v1H2zm0 3.5a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5zm1 .5H2v1h1z"/>
                    </svg></a>';
                break;

            case 'Aprobar':
                $boton = '<a href="index.php?action=actualizarestado&id_proyectos=' . $id_proyecto . '&tipo=Activos"
                    class="btn btn-success" data-bs-toggle="tooltip" data-bs-title="Aprobar proyecto">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" class="bi bi-check-circle-fill" viewBox="0 0 16 16">
                        <path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0m-3.97-3.03a.75.75 0 0 0-1.08.022L7.477 9.417 5.384 7.323a.75.75 0 0 0-1.06 1.06L6.97 11.03a.75.75 0 0 0 1.079-.02l3.992-4.99a.75.75 0 0 0-.01-1.05z"/>
                    </svg></a>';
                break;

            case 'Solicitar cerrar':
                $boton = '<a href="index.php?action=actualizarestado&id_proyectos=' . $id_proyecto . '&tipo=PorCerrar"
                    class="btn btn-warning" data-bs-toggle="tooltip" data-bs-title="Solicitar cierre del proyecto">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-x-circle-fill" viewBox="0 0 16 16">
                        <path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0M5.354 4.646a.5.5 0 1 0-.708.708L7.293 8l-2.647 2.646a.5.5 0 0 0 .708.708L8 8.707l2.646 2.647a.5.5 0 0 0 .708-.708L8.707 8l2.647-2.646a.5.5 0 0 0-.708-.708L8 7.293z"/>
                    </svg></a>';
                break;

            case 'Aprobar cierre':
                $boton = '<a href="index.php?action=actualizarestado&id_proyectos=' . $id_proyecto . '&tipo=Cierre"
                    class="btn btn-success" data-bs-toggle="tooltip" data-bs-title="Aprobar cierre de proyecto">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" class="bi bi-check-circle-fill" viewBox="0 0 16 16">
                        <path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0m-3.97-3.03a.75.75 0 0 0-1.08.022L7.477 9.417 5.384 7.323a.75.75 0 0 0-1.06 1.06L6.97 11.03a.75.75 0 0 0 1.079-.02l3.992-4.99a.75.75 0 0 0-.01-1.05z"/>
                    </svg></a>';
                break;

            case 'Rechazar cierre':
                $boton = '<a href="comentarios.php?id_proyectos=' . $id_proyecto . '&motivo=cierre_rechazado"
                    class="btn btn-danger" data-bs-toggle="tooltip" data-bs-title="Rechazar cierre de proyecto">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-x-circle-fill" viewBox="0 0 16 16">
                        <path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0M5.354 4.646a.5.5 0 1 0-.708.708L7.293 8l-2.647 2.646a.5.5 0 0 0 .708.708L8 8.707l2.646 2.647a.5.5 0 0 0 .708-.708L8.707 8l2.647-2.646a.5.5 0 0 0-.708-.708L8 7.293z"/>
                    </svg></a>';
                break;

            case 'Volver a enviar cierre':
                $boton = '<a href="index.php?action=actualizarestado&id_proyectos=' . $id_proyecto . '&tipo=PorCerrar"
                    class="btn btn-warning" data-bs-toggle="tooltip" data-bs-title="Volver a enviar cierre">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-arrow-repeat" viewBox="0 0 16 16">
                        <path d="M11.534 7h3.932a.25.25 0 0 1 .192.41l-1.966 2.36a.25.25 0 0 1-.384 0l-1.966-2.36a.25.25 0 0 1 .192-.41m-11 2h3.932a.25.25 0 0 0 .192-.41L2.692 6.23a.25.25 0 0 0-.384 0L.342 8.59A.25.25 0 0 0 .534 9"/>
                        <path fill-rule="evenodd" d="M8 3c-1.552 0-2.94.707-3.857 1.818a.5.5 0 1 1-.771-.636A6.002 6.002 0 0 1 13.917 7H12.9A5 5 0 0 0 8 3M3.1 9a5.002 5.002 0 0 0 8.757 2.182.5.5 0 1 1 .771.636A6.002 6.002 0 0 1 2.083 9z"/>
                    </svg></a>';
                break;

            case 'Volver a enviar proyecto':
                $boton = '<a href="index.php?action=actualizarestado&id_proyectos=' . $id_proyecto . '&tipo=PorAprobar"
                    class="btn btn-warning" data-bs-toggle="tooltip" data-bs-title="Volver a enviar proyecto">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-arrow-repeat" viewBox="0 0 16 16">
                        <path d="M11.534 7h3.932a.25.25 0 0 1 .192.41l-1.966 2.36a.25.25 0 0 1-.384 0l-1.966-2.36a.25.25 0 0 1 .192-.41m-11 2h3.932a.25.25 0 0 0 .192-.41L2.692 6.23a.25.25 0 0 0-.384 0L.342 8.59A.25.25 0 0 0 .534 9"/>
                        <path fill-rule="evenodd" d="M8 3c-1.552 0-2.94.707-3.857 1.818a.5.5 0 1 1-.771-.636A6.002 6.002 0 0 1 13.917 7H12.9A5 5 0 0 0 8 3M3.1 9a5.002 5.002 0 0 0 8.757 2.182.5.5 0 1 1 .771.636A6.002 6.002 0 0 1 2.083 9z"/>
                    </svg></a>';
                break;

            case 'Editar':
                $boton = '<a href="editar.php?id_proyectos=' . $id_proyecto . '"
                    class="btn btn-warning" data-bs-toggle="tooltip" data-bs-title="Editar proyecto">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-pencil-square" viewBox="0 0 16 16">
                        <path d="M15.502 1.94a.5.5 0 0 1 0 .706L14.459 3.69l-2-2L13.502.646a.5.5 0 0 1 .707 0l1.293 1.293zm-1.75 2.456-2-2L4.939 9.21a.5.5 0 0 0-.121.196l-.805 2.414a.25.25 0 0 0 .316.316l2.414-.805a.5.5 0 0 0 .196-.12l6.813-6.814z"/>
                        <path fill-rule="evenodd" d="M1 13.5A1.5 1.5 0 0 0 2.5 15h11a1.5 1.5 0 0 0 1.5-1.5v-6a.5.5 0 0 0-1 0v6a.5.5 0 0 1-.5.5h-11a.5.5 0 0 1-.5-.5v-11a.5.5 0 0 1 .5-.5H9a.5.5 0 0 0 0-1H2.5A1.5 1.5 0 0 0 1 2.5z"/>
                    </svg></a>';
                break;

            case 'Rechazar creacion':
                $boton = '<a href="comentarios.php?id_proyectos=' . $id_proyecto . '&motivo=creacion_rechazada"
                    class="btn btn-danger" data-bs-toggle="tooltip" data-bs-title="Rechazar creación de proyecto">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-x-circle-fill" viewBox="0 0 16 16">
                        <path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0M5.354 4.646a.5.5 0 1 0-.708.708L7.293 8l-2.647 2.646a.5.5 0 0 0 .708.708L8 8.707l2.646 2.647a.5.5 0 0 0 .708-.708L8.707 8l2.647-2.646a.5.5 0 0 0-.708-.708L8 7.293z"/>
                    </svg></a>';
                break;

            case 'Comentarios':
                $boton = '<a href="ver_comentarios.php?id_proyectos=' . $id_proyecto . '"
                    class="btn btn-info" data-bs-toggle="tooltip" data-bs-title="Ver comentarios">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-chat-dots-fill" viewBox="0 0 16 16">
                        <path d="M16 8c0 3.866-3.582 7-8 7a9 9 0 0 1-2.347-.306c-.584.296-1.925.864-4.181 1.234-.2.032-.352-.176-.273-.362.354-.836.674-1.95.77-2.966C.744 11.37 0 9.76 0 8c0-3.866 3.582-7 8-7s8 3.134 8 7M5 8a1 1 0 1 0-2 0 1 1 0 0 0 2 0m4 0a1 1 0 1 0-2 0 1 1 0 0 0 2 0m3 1a1 1 0 1 0 0-2 1 1 0 0 0 0 2"/>
                    </svg></a>';
                break;

            case 'Dar de baja':
                $boton = '<button class="btn btn-danger btn-sm" data-accion="baja" data-id="' . $id_usuario . '">Dar de baja</button>';
                break;

            case 'Reactivar':
                $boton = '<button class="btn btn-success btn-sm" data-accion="reactivar" data-id="' . $id_usuario . '">Reactivar</button>';
                break;

            // Botón de detalle para solicitudes_integracion_proyecto (apunta a solicitudes_integracion_proyecto/detalle.php)
            case 'DetalleSolicitud':
                $boton = '<a href="../solicitudes_integracion_proyecto/detalle.php?id_proyectos=' . $id_proyecto . '"
                    class="btn btn-primary btn-sm" data-bs-toggle="tooltip" data-bs-title="Ver detalle de solicitud">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-eye-fill" viewBox="0 0 16 16">
                        <path d="M10.5 8a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0"/><path d="M0 8s3-5.5 8-5.5S16 8 16 8s-3 5.5-8 5.5S0 8 0 8m8 3.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7"/>
                    </svg></a>';
                break;

            default:
                break;
        }
        return $boton;
    }

    // 
    // BOTONES DE ACCIÓN — tabla de proyectos (index.php)
    // 

    public function botonesAccion($id, $rol, $estado = null, $extra = null, $estado_completados_estudiantes = 0, $estado_estudiante = 'activo', $estado_editar = false)
    {
        $solicitar = ($estado_completados_estudiantes == 1) ? 'Solicitar cerrar' : '';

         if ($estado_editar == true){
            $editar = "Editar";
         } else{
            $editar = "";
         }
         
        // Bloqueo para estudiante en baja
        if ($rol === 'estudiante' && strtolower($estado_estudiante) === 'baja') {
            return $this->obtenerbotones('Detalles', $id);
        }

        $acciones = [

            'estudiante' => [
                'Activo'    => ['Detalles', 'Ver Tareas Alumnos'],
                'Por cerrar' => ['Detalles', 'Ver Tareas Alumnos'],
                'Vencido'   => ['Detalles', 'Ver Tareas Alumnos'],
                'Cierre'    => ['Detalles', 'Ver Tareas Alumnos'],
            ],

            'investigador' => [
                'Activo'          => ['Detalles', 'Tareas', $editar, 'Comentarios', $solicitar],
                'Por aprobar'     => ['Detalles', 'Comentarios'],
                'Por cerrar'      => ['Detalles', 'Tareas', 'Comentarios'],
                'Cierre'          => ['Detalles', 'Tareas', 'Comentarios'],
                'Cierre rechazado' => ['Volver a enviar cierre', 'Detalles', $editar, 'Tareas', 'Comentarios'],
                'Rechazado'       => ['Volver a enviar proyecto', 'Detalles', $editar, 'Comentarios'],
                'Vencido'         => ['Detalles', 'Tareas', 'Comentarios'],
            ],

            'profesor' => [
                'Activo'          => ['Detalles', 'Tareas', $editar, 'Comentarios', $solicitar],
                'Por aprobar'     => ['Detalles', 'Comentarios'],
                'Por cerrar'      => ['Detalles', 'Tareas', 'Comentarios'],
                'Cierre'          => ['Detalles', 'Tareas', 'Comentarios'],
                'Cierre rechazado' => ['Volver a enviar cierre', 'Detalles', $editar, 'Tareas', 'Comentarios'],
                'Rechazado'       => ['Volver a enviar proyecto', 'Detalles', $editar, 'Comentarios'],
                'Vencido'         => ['Detalles', 'Tareas', 'Comentarios'],
            ],

            // Supervisor en /proyectos/index.php: solo proyectos ya aprobados
            'supervisor' => [
                'Activo'          => ['Detalles', 'Tareas', 'Comentarios'],
                'Por cerrar'      => ['Detalles', 'Tareas', 'Comentarios'],
                'Cierre rechazado' => ['Detalles', 'Tareas', 'Comentarios'],
                'Cierre'          => ['Detalles', 'Tareas', 'Comentarios'],
                'Vencido'         => ['Detalles', 'Tareas', 'Comentarios'],
            ],
        ];

        if (!isset($acciones[$rol][$estado])) return '';

        $botones = '';
        foreach ($acciones[$rol][$estado] as $accion) {
            if (empty($accion)) continue;
            if ($accion === 'Ver Tareas Alumnos') {
                $botones .= $this->obtenerbotones($accion, $id, $extra);
            } else {
                $botones .= $this->obtenerbotones($accion, $id);
            }
        }
        return $botones;
    }

    // 
    // BOTONES DE ACCIÓN — tabla de solicitudes_integracion_proyecto (solicitudes_integracion_proyecto/index.php)
    // 

    /**
     * Botones para la columna Acciones en la tabla de /solicitudes_integracion_proyecto/index.php
     */
    public function botonesAccionSolicitud($id_proyecto, $rol, $tipo_solicitud, $estado_proyecto)
    {
        $botones = '';

        // Botón: ver detalle siempre
        $botones .= $this->obtenerbotones('Detalles', $id_proyecto);

        if ($rol === 'supervisor') {
            if ($tipo_solicitud === 'creacion' && $estado_proyecto === 'Por aprobar') {
                // Aprobar creación
                $botones .= '<a href="../proyectos/index.php?action=actualizarestado&id_proyectos=' . $id_proyecto . '&tipo=Activos"
                    class="btn btn-success btn-sm" data-bs-toggle="tooltip" data-bs-title="Aprobar proyecto"
                    onclick="return confirm(\'¿Aprobar este proyecto?\')">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-check-circle-fill" viewBox="0 0 16 16">
                        <path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0m-3.97-3.03a.75.75 0 0 0-1.08.022L7.477 9.417 5.384 7.323a.75.75 0 0 0-1.06 1.06L6.97 11.03a.75.75 0 0 0 1.079-.02l3.992-4.99a.75.75 0 0 0-.01-1.05z"/>
                    </svg></a>';
                // Rechazar creación
                $botones .= '<a href="../proyectos/comentarios.php?id_proyectos=' . $id_proyecto . '&motivo=creacion_rechazada&desde=solicitudes"
                    class="btn btn-danger btn-sm" data-bs-toggle="tooltip" data-bs-title="Rechazar proyecto">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-x-circle-fill" viewBox="0 0 16 16">
                        <path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0M5.354 4.646a.5.5 0 1 0-.708.708L7.293 8l-2.647 2.646a.5.5 0 0 0 .708.708L8 8.707l2.646 2.647a.5.5 0 0 0 .708-.708L8.707 8l2.647-2.646a.5.5 0 0 0-.708-.708L8 7.293z"/>
                    </svg></a>';
            }

            if ($tipo_solicitud === 'cierre' && $estado_proyecto === 'Por cerrar') {
                // Aprobar cierre
                $botones .= '<a href="../proyectos/index.php?action=actualizarestado&id_proyectos=' . $id_proyecto . '&tipo=Cierre"
                    class="btn btn-success btn-sm" data-bs-toggle="tooltip" data-bs-title="Aprobar cierre"
                    onclick="return confirm(\'¿Aprobar el cierre de este proyecto?\')">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-check-circle-fill" viewBox="0 0 16 16">
                        <path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0m-3.97-3.03a.75.75 0 0 0-1.08.022L7.477 9.417 5.384 7.323a.75.75 0 0 0-1.06 1.06L6.97 11.03a.75.75 0 0 0 1.079-.02l3.992-4.99a.75.75 0 0 0-.01-1.05z"/>
                    </svg></a>';
                // Rechazar cierre
                $botones .= '<a href="../proyectos/comentarios.php?id_proyectos=' . $id_proyecto . '&motivo=cierre_rechazado&desde=solicitudes"
                    class="btn btn-danger btn-sm" data-bs-toggle="tooltip" data-bs-title="Rechazar cierre">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-x-circle-fill" viewBox="0 0 16 16">
                        <path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0M5.354 4.646a.5.5 0 1 0-.708.708L7.293 8l-2.647 2.646a.5.5 0 0 0 .708.708L8 8.707l2.646 2.647a.5.5 0 0 0 .708-.708L8.707 8l2.647-2.646a.5.5 0 0 0-.708-.708L8 7.293z"/>
                    </svg></a>';
            }
        }

        return $botones;
    }

    // 
    // BOTONES EDITAR ESTUDIANTE
    // 

    public function botonesAccionEditarEstudiante($id_estudiante, $rol, $estado, $id_proyecto, $estado_proyecto)
    {
        $boton = '';
        if (in_array($rol, ['investigador', 'profesor'])) {
            if (strtolower($estado_proyecto) === 'vencido') {
                return '<span class="text-muted">Sin acciones</span>';
            }
            if ($estado === 'activo') {
                $boton .= $this->obtenerbotones('Dar de baja', $id_proyecto, $id_estudiante);
            }
            if ($estado === 'baja') {
                $boton .= $this->obtenerbotones('Reactivar', $id_proyecto, $id_estudiante);
            }
        }
        return $boton;
    }

    // 
    // PORCENTAJE DE AVANCE
    // 

    public function obtenerPorcentajeAvance($id_proyecto)
    {
        global $conn;
        try {
            $proyecto   = new Proyectos($conn);
            $porcentaje = $proyecto->obtenerTareasAvance((int)$id_proyecto);
            return ($porcentaje === null || $porcentaje === false) ? 0 : $porcentaje;
        } catch (Exception $e) {
            error_log($e->getMessage());
            return 0;
        }
    }

    // 
    // CATÁLOGOS
    // 

    public function tematica()
    {
        global $conn;
        try {
            return (new Proyectos($conn))->tematica() ?? [];
        } catch (Exception $e) {
            error_log($e->getMessage());
            return [];
        }
    }

    public function subtematicas($id)
    {
        global $conn;
        try {
            return (new Proyectos($conn))->obtenersubtematica((int)$id);
        } catch (Exception $e) {
            error_log($e->getMessage());
            return [];
        }
    }

    public function obtenerperiodo()
    {
        global $conn;
        try {
            return (new Proyectos($conn))->obtenerperiodo() ?? [];
        } catch (Exception $e) {
            error_log($e->getMessage());
            return [];
        }
    }

    public function obtenerInstituto()
    {
        global $conn;
        try {
            return (new Proyectos($conn))->obtenerinstituto() ?? [];
        } catch (Exception $e) {
            error_log($e->getMessage());
            return [];
        }
    }

    /**
     * Todos los periodos para el select de filtro en solicitudes_integracion_proyecto/index.php
     */
    public function obtenerTodosPeriodos()
    {
        global $conn;
        try {
            return (new Proyectos($conn))->obtenerTodosPeriodos() ?? [];
        } catch (Exception $e) {
            error_log($e->getMessage());
            return [];
        }
    }

    // 
    // CRUD DE PROYECTOS
    // 

    public function registrarProyecto($datos, $id, $rol)
    {
        global $conn;
        try {
            $this->validarMetodo('POST');
            $this->validarAcceso($rol, ['investigador', 'profesor']);

            $periodo     = $this->obtenerperiodo();
            $subtematicas = $datos['subtematicas'] ?? [];

            if (empty($subtematicas)) throw new Exception("Datos incompletos");

            $instituto = $this->obtenerInstituto()[0] ?? null;
            if (!$datos['NombreProyecto']) throw new Exception("Falta nombre del proyecto");

            $proyecto = new Proyectos($conn);
            $proyecto->actualizarProyectosVencidos();

            $proyectoId = $proyecto->registrarProyecto(
                $id,
                3,
                $instituto['id_instituto'],
                $periodo['id_periodos'],
                $datos['NombreProyecto'],
                $datos['Descripcion'],
                $datos['Objetivos'],
                $datos['FechaInicio'],
                $datos['FechaFinal'],
                $datos['Presupuesto'],
                $datos['Requisitos'],
                $datos['Pre_requisitos'],
                $datos['Modalidad'],
                $datos['AlumnosCantidad']
            );

            foreach ($subtematicas as $idSub) {
                $proyecto->vincularSubtematica($proyectoId, (int)$idSub);
            }

            header("Location: index.php?mensaje=1");
            exit();
        } catch (Exception $e) {
            die($e->getMessage());
        }
    }

    public function editarProyecto($datos, $id_usuario, $rol)
    {
        global $conn;
        try {
            $this->validarMetodo('POST');
            $this->validarAcceso($rol, ['investigador', 'profesor']);

            $id_proyecto  = (int)($datos['id_proyectos'] ?? 0);
            $subtematicas = $datos['subtematicas'] ?? [];

            if (!$id_proyecto || empty($subtematicas)) throw new Exception("Datos incompletos");

            $proyecto = new Proyectos($conn);
            $proyecto->editarProyecto(
                $id_proyecto,
                $id_usuario,
                $datos['NombreProyecto'],
                $datos['Descripcion'],
                $datos['Objetivos'],
                $datos['FechaInicio'],
                $datos['FechaFinal'],
                $datos['Presupuesto'],
                $datos['Requisitos'],
                $datos['Pre_requisitos'],
                $datos['Modalidad'],
                $datos['AlumnosCantidad']
            );

            foreach ($subtematicas as $idSub) {
                $proyecto->ActualizarvincularSubtematica($id_proyecto, (int)$idSub);
            }

            header("Location: index.php?id_proyectos={$id_proyecto}");
            exit();
        } catch (Exception $e) {
            return $e->getMessage();
        }
    }

    public function subtematicasProyecto($id_proyecto)
    {
        global $conn;
        try {
            return (new Proyectos($conn))->obtenersubtematicasProyecto($id_proyecto);
        } catch (Exception $e) {
            return $e->getMessage();
        }
    }

    // 
    // ACTUALIZAR ESTADO
    // 

    public function actualizarestadoRechazo($data, $id_usuario, $rol)
    {
        global $conn;
        try {
            $this->validarMetodo('POST');
            $this->validarAcceso($rol, ['supervisor']);

            if (empty($data['comentario']) || empty($data['id_proyectos'])) {
                throw new Exception("Datos incompletos");
            }

            $proyecto = new Proyectos($conn);
            $proyecto->actualizarProyectosVencidos();
            $proyecto->actualizarEstadoProyectoRechazo(
                $id_usuario,
                (int)$data['id_proyectos'],
                $data['tipo'],
                $data['comentario']
            );

            // Redirigir al origen correcto
            $desde = $data['desde'] ?? 'proyectos';
            if ($desde === 'solicitudes') {
                header("Location: ../solicitudes_integracion_proyecto/index.php?mensaje=1");
            } else {
                header("Location: index.php?mensaje=1");
            }
            exit();
        } catch (Exception $e) {
            return $e->getMessage();
        }
    }

    public function actualizarestado($id_proyecto, $rol, $tipo)
    {
        global $conn;
        try {
            $this->validarMetodo('GET');
            $this->validarAcceso($rol, ['supervisor', 'investigador', 'profesor']);

            $proyecto  = new Proyectos($conn);
            $proyecto->actualizarProyectosVencidos();

            $estado    = $this->numerofiltro($tipo);
            $porcentaje = $this->obtenerPorcentajeAvance((int)$id_proyecto);

            $proyecto->actualizarestado((int)$id_proyecto, $estado, $porcentaje);

            header("Location: index.php?mensaje=1");
            exit();
        } catch (Exception $e) {
            return $e->getMessage();
        }
    }

    // 
    // DATOS DE DETALLE
    // 

    public function datosproyecto($id_proyecto)
    {
        global $conn;
        return (new Proyectos($conn))->obtenerProyecto($id_proyecto);
    }

    public function datosinvestigador($id_proyecto)
    {
        global $conn;
        $proyecto     = new Proyectos($conn);
        $investigador = $proyecto->obtenerProyectoInvestigador($id_proyecto);
        $id_usuario   = $investigador['id_usuarios'] ?? null;

        return [
            'investigador' => $investigador,
            'area'         => $proyecto->obtenerUsuarioArea($id_usuario),
            'lineas'       => $proyecto->obtenerInvestigadorLinea($id_proyecto),
        ];
    }

    public function datosestudiantes($id_proyecto)
    {
        global $conn;
        $proyecto   = new Proyectos($conn);
        $estudiante = $proyecto->obtenerProyectoEstudiante($id_proyecto);
        $id_usuario = $estudiante['id_usuario'] ?? null;

        return [
            'estudiante' => $estudiante,
            'area'       => $proyecto->obtenerUsuarioArea($id_usuario),
        ];
    }

    public function comentarios($id_proyecto)
    {
        global $conn;
        return (new Proyectos($conn))->obtenerProyectoComentarios($id_proyecto);
    }

    public function estudiantes($id_proyecto)
    {
        global $conn;
        return (new Proyectos($conn))->estudiantes($id_proyecto);
    }

    public function obtenerEstudianteProyecto($id_proyecto, $id_estudiante)
    {
        global $conn;
        return (new Proyectos($conn))->obtenerEstudianteProyecto($id_proyecto, $id_estudiante);
    }

    public function historial_estudiante_proyecto($id_proyecto, $id_usuario)
    {
        global $conn;
        try {
            $pagina = $_GET['pagina'] ?? 1;
            return (new Proyectos($conn))->lineaTiempoProyectoUsuarios($id_proyecto, $id_usuario, $pagina);
        } catch (Exception $e) {
            error_log($e->getMessage());
            header("Location: editar.php?error=1");
            exit;
        }
    }

    // 
    // ACCIONES DE ESTUDIANTE (baja / reactivar)
    // 

    public function accionEstudiante($datos)
    {
        global $conn;
        $action      = $datos['action']      ?? '';
        $id_proyecto = $datos['id_proyecto'];
        $id_estudiante = $datos['id_estudiante'];
        $motivo      = $datos['motivo']      ?? null;

        $modelo = new Proyectos($conn);

        switch ($action) {
            case 'baja':
                $modelo->bajaEstudiante($id_proyecto, $id_estudiante, $motivo, $_SESSION['id_usuario']);
                break;
            case 'reactivar':
                $modelo->reactivarEstudiante($id_proyecto, $id_estudiante, $_SESSION['id_usuario']);
                break;
        }

        header("Location: editar.php?id_proyectos=" . $id_proyecto . "&mensaje=1");
    }

    // 
    // MÓDULO SOLICITUDES — métodos específicos
    // 

    /**
     * Resumen de conteos para el dashboard de solicitudes (supervisor)
     */
    public function resumenSolicitudes($rol, $id_usuario, $id_periodo = 0)
    {
        global $conn;
        try {
            return (new Proyectos($conn))->resumenSolicitudes($rol, $id_usuario, $id_periodo);
        } catch (Exception $e) {
            error_log($e->getMessage());
            return ['total' => 0, 'pendientes_creacion' => 0, 'pendientes_cierre' => 0, 'aprobadas' => 0];
        }
    }

    /**
     * Listar solicitudes con paginación, filtro por tipo y periodo
     */
    public function listarSolicitudes($rol, $id_usuario, $tipo_filtro = 'Todas', $buscar = '', $pagina = 1, $id_periodo = 0)
    {
        global $conn;
        try {
            return (new Proyectos($conn))->listarSolicitudes($rol, $id_usuario, $tipo_filtro, $buscar, $pagina, $id_periodo);
        } catch (Exception $e) {
            error_log($e->getMessage());
            return json_encode(['solicitudes' => [], 'paginacion' => []]);
        }
    }

    public function periodoactual()
    {
        global $conn;
        try {
            return (new Proyectos($conn))->periodoactual();
        } catch (Exception $e) {
            error_log($e->getMessage());
            header("Location: index.php?error=1");
            exit;
        }
    }
}
