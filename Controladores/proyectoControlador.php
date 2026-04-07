<?php

require_once __DIR__ . '/../Modelos/proyecto.php';
require_once __DIR__ . '/../publico/config/conexion.php';

// Encabezados según rol

class ProyectoControlador
{


    //Validar roles permitidos
    private function rolValido($rol)
    {
        return in_array($rol, ['investigador', 'estudiante', 'supervisor']);
    }

    //Método base reutilizable
    private function obtenerDatos($id, $rol, $buscar, $filtro = null, $tipo = 'general')
    {
        global $conn;
        try {
            if (!$this->rolValido($rol)) return [];

            $proyecto = new Proyectos($conn);

            //Actualizar vencidos
            $proyecto->actualizarProyectosVencidos();

            switch ($tipo) {
                case 'filtro':
                    return $proyecto->obtenerProyectosDatosFiltro($id, $rol);

                case 'tabla':
                    return $proyecto->obtenerProyectosTablaFiltro($id, $filtro, $rol, $buscar);

                default:
                    return $proyecto->obtenerProyectos($id, $rol, $buscar);
            }
        } catch (Exception $e) {
            error_log($e->getMessage());
            return [];
        }
    }

    //General
    public function index($id, $rol, $buscar = null)
    {
        return $this->obtenerDatos((int)$id, $rol, $buscar, 0, 'tabla');
    }

    //Filtros generales
    public function filtros($id, $rol)
    {
        return $this->obtenerDatos((int)$id, $rol, null, null, 'filtro');
    }

    //Total
    public function Total($id, $rol, $buscar = null)
    {
        return $this->obtenerDatos((int)$id, $rol, $buscar, 0, 'tabla');
    }

    //Cierre
    public function Cierre($id, $rol, $buscar = null)
    {
        return $this->obtenerDatos((int)$id, $rol, $buscar, 1, 'tabla');
    }

    //Activos
    public function Activos($id, $rol, $buscar = null)
    {
        return $this->obtenerDatos((int)$id, $rol, $buscar, 2, 'tabla');
    }

    //Por aprobar
    public function PorAprobar($id, $rol, $buscar = null)
    {
        return $this->obtenerDatos((int)$id, $rol, $buscar, 3, 'tabla');
    }

    //Rechazados
    public function Rechazados($id, $rol, $buscar = null)
    {
        return $this->obtenerDatos((int)$id, $rol, $buscar, 4, 'tabla');
    }

    //Por cerrar
    public function PorCerrar($id, $rol, $buscar = null)
    {
        return $this->obtenerDatos((int)$id, $rol, $buscar, 5, 'tabla');
    }

    //Vencidos
    public function Vencido($id, $rol, $buscar = null)
    {
        return $this->obtenerDatos((int)$id, $rol, $buscar, 6, 'tabla');
    }

    //Cierre rechazado
    public function Cierrerechazado($id, $rol, $buscar = null)
    {
        return $this->obtenerDatos((int)$id, $rol, $buscar, 7, 'tabla');
    }

    //Para obtener el número del filtro de la tabla
    public function numerofiltro($action)
    {
        return match ($action) {
            'Total' => 0,
            'Cierre' => 1,
            'Activos', 'Activo' => 2,
            'PorAprobar' => 3,
            'Rechazados' => 4,
            'PorCerrar' => 5,
            'Vencido' => 6,
            'CierreRechazado' => 7,
            default => 0,
        };
    }

    //Encabezados
    public function encabezados($rol)
    {
        //Todos los roles usan los mismos encabezados
        $encabezadosBase = [
            'ID',
            'Título',
            'Inicio',
            'Fin',
            'Estado',
            'Período',
            'Pendientes',
            'Acciones'
        ];

        return in_array($rol, ['estudiante', 'investigador', 'profesor', 'supervisor'])
            ? $encabezadosBase
            : [];
    }

    //Opciones
    public function datosopciones($rol, $filtros)
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
                'Vencido'   => "Vencidos ({$filtros[0]['Vencido']})"
            ];
        }

        if (in_array($rol, ['investigador', 'profesor', 'supervisor'])) {
            return $base + [
                'PorAprobar'        => "Por Aprobar ({$filtros[0]['PorAprobar']})",
                'Rechazados'        => "Rechazados ({$filtros[0]['Rechazados']})",
                'Cierre'            => "Cierre ({$filtros[0]['Cierre']})",
                'PorCerrar'         => "Por Cerrar ({$filtros[0]['PorCerrar']})",
                'Cierrerechazado'   => "Cierre rechazado ({$filtros[0]['Cierrerechazado']})",
                'Vencido'           => "Vencidos ({$filtros[0]['Vencido']})"
            ];
        }

        return [];
    }

    //Estilo estado
    public function EstiloEstado($estado)
    {
        return match ($estado) {
            'Cierre rechazado', 'Rechazado', 'Terminado' => "danger",
            'Por cerrar', 'Por aprobar', 'Pendiente' => "warning",
            'Vencido' => "secondary",
            'Activo' => "success",
            'Cierre' => "dark",
            default => "info",
        };
    }

    //Porcentaje de avance
    public function obtenerPorcentajeAvance($id_proyecto)
    {
        global $conn;
        try {
            $proyecto = new Proyectos($conn);

            $porcentaje = $proyecto->obtenerTareasAvance((int)$id_proyecto);

            return ($porcentaje === null || $porcentaje === false) ? 0 : $porcentaje;
        } catch (Exception $e) {
            error_log($e->getMessage());
            return 0;
        }
    }


    public function obtenerbotones($tipo, $id_proyecto, $id_usuario = null)
    {
        $boton = "";
        switch ($tipo) {
            case 'Detalles':
                $boton = '<a href="detalles.php?id_proyectos=' . $id_proyecto . '" type="button" class="btn btn-primary" data-bs-toggle="tooltip" data-bs-placement="top"
        data-bs-custom-class="custom-tooltip" data-bs-title="Ver detalles del proyecto"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-eye-fill" style="padding:0px;margin:auto;" viewBox="0 0 16 16">
  <path d="M10.5 8a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0"/><path d="M0 8s3-5.5 8-5.5S16 8 16 8s-3 5.5-8 5.5S0 8 0 8m8 3.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7"/></svg></a>';
                break;
            case 'Tareas':
                $boton = '<a href="../Tareas/tabla.php?id_proyectos=' . $id_proyecto . '" type="button" class="btn btn-info" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-custom-class="custom-tooltip"
        data-bs-title="Tareas"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" class="bi bi-list-task" viewBox="0 0 16 16"><path fill-rule="evenodd" d="M2 2.5a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5V3a.5.5 0 0 0-.5-.5zM3 3H2v1h1z"/>
  <path d="M5 3.5a.5.5 0 0 1 .5-.5h9a.5.5 0 0 1 0 1h-9a.5.5 0 0 1-.5-.5M5.5 7a.5.5 0 0 0 0 1h9a.5.5 0 0 0 0-1zm0 4a.5.5 0 0 0 0 1h9a.5.5 0 0 0 0-1z"/><path fill-rule="evenodd" d="M1.5 7a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5H2a.5.5 0 0 1-.5-.5zM2 7h1v1H2zm0 3.5a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5zm1 .5H2v1h1z"/></svg></a>';
                break;
            case 'Ver Tareas Alumnos':
                $boton = '<a href="../Tareas/tareas_estudiante.php?id_usuario=' . $id_usuario . '" type="button" class="btn btn-info" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-custom-class="custom-tooltip"
        data-bs-title="Ver Tareas"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" class="bi bi-list-task" viewBox="0 0 16 16"><path fill-rule="evenodd" d="M2 2.5a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5V3a.5.5 0 0 0-.5-.5zM3 3H2v1h1z"/>
  <path d="M5 3.5a.5.5 0 0 1 .5-.5h9a.5.5 0 0 1 0 1h-9a.5.5 0 0 1-.5-.5M5.5 7a.5.5 0 0 0 0 1h9a.5.5 0 0 0 0-1zm0 4a.5.5 0 0 0 0 1h9a.5.5 0 0 0 0-1z"/><path fill-rule="evenodd" d="M1.5 7a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5H2a.5.5 0 0 1-.5-.5zM2 7h1v1H2zm0 3.5a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5zm1 .5H2v1h1z"/></svg></a>';
                break;
            case 'Aprobar':
                $boton = '<a href="tabla.php?action=actualizarestado&id_proyectos=' . $id_proyecto . '&tipo=Activos" button type="button" class="btn btn-success" data-bs-toggle="tooltip" data-bs-placement="top"
        data-bs-custom-class="custom-tooltip" data-bs-title="Aprobar proyecto"> <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" class="bi bi-check-circle-fill" viewBox="0 0 16 16">
  <path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0m-3.97-3.03a.75.75 0 0 0-1.08.022L7.477 9.417 5.384 7.323a.75.75 0 0 0-1.06 1.06L6.97 11.03a.75.75 0 0 0 1.079-.02l3.992-4.99a.75.75 0 0 0-.01-1.05z"/></svg></a>';
                break;
            case 'Solicitar cerrar':
                $boton = '<a href="tabla.php?action=actualizarestado&id_proyectos=' . $id_proyecto . '&tipo=PorCerrar" type="button" class="btn btn-danger" data-bs-toggle="tooltip" data-bs-placement="top"
        data-bs-custom-class="custom-tooltip" data-bs-title="Solicitar cerrar proyecto"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-x-circle-fill" viewBox="0 0 16 16">
  <path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0M5.354 4.646a.5.5 0 1 0-.708.708L7.293 8l-2.647 2.646a.5.5 0 0 0 .708.708L8 8.707l2.646 2.647a.5.5 0 0 0 .708-.708L8.707 8l2.647-2.646a.5.5 0 0 0-.708-.708L8 7.293z"/>
</svg></a>';
                break;
            case 'Aprobar cierre':
                $boton = '<a href="tabla.php?action=actualizarestado&id_proyectos=' . $id_proyecto . '&tipo=Cierre" type="button" class="btn btn-success" data-bs-toggle="tooltip" data-bs-placement="top"
        data-bs-custom-class="custom-tooltip" data-bs-title="Aprobar cierre de proyecto"> <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" class="bi bi-check-circle-fill" viewBox="0 0 16 16">
  <path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0m-3.97-3.03a.75.75 0 0 0-1.08.022L7.477 9.417 5.384 7.323a.75.75 0 0 0-1.06 1.06L6.97 11.03a.75.75 0 0 0 1.079-.02l3.992-4.99a.75.75 0 0 0-.01-1.05z"/></svg></a>';
                break;
            case 'Rechazar cierre':
                $boton = '<a href="comentarios.php?id_proyectos=' . $id_proyecto . '&motivo=cierre_rechazado" type="button" class="btn btn-danger" data-bs-toggle="tooltip" data-bs-placement="top"
        data-bs-custom-class="custom-tooltip" data-bs-title="Rechazar cierre de proyecto"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-x-circle-fill" viewBox="0 0 16 16">
  <path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0M5.354 4.646a.5.5 0 1 0-.708.708L7.293 8l-2.647 2.646a.5.5 0 0 0 .708.708L8 8.707l2.646 2.647a.5.5 0 0 0 .708-.708L8.707 8l2.647-2.646a.5.5 0 0 0-.708-.708L8 7.293z"/>
</svg></a>';
                break;
            case 'Volver a enviar cierre':
                $boton = '<a href="tabla.php?action=actualizarestado&id_proyectos=' . $id_proyecto . '&tipo=PorCerrar" type="button" class="btn btn-warning" data-bs-toggle="tooltip" data-bs-placement="top"
        data-bs-custom-class="custom-tooltip" data-bs-title="Volver a enviar cierre"><svg xmlns=\"http://www.w3.org/2000/svg\" width="18" height="18" fill="currentColor" class="bi bi-arrow-repeat" viewBox="0 0 16 16">
  <path d="M11.534 7h3.932a.25.25 0 0 1 .192.41l-1.966 2.36a.25.25 0 0 1-.384 0l-1.966-2.36a.25.25 0 0 1 .192-.41m-11 2h3.932a.25.25 0 0 0 .192-.41L2.692 6.23a.25.25 0 0 0-.384 0L.342 8.59A.25.25 0 0 0 .534 9"/>
  <path fill-rule="evenodd" d="M8 3c-1.552 0-2.94.707-3.857 1.818a.5.5 0 1 1-.771-.636A6.002 6.002 0 0 1 13.917 7H12.9A5 5 0 0 0 8 3M3.1 9a5.002 5.002 0 0 0 8.757 2.182.5.5 0 1 1 .771.636A6.002 6.002 0 0 1 2.083 9z"/>
</svg></a>';
                break;
            case 'Volver a enviar proyecto':
                $boton = '<a href="tabla.php?action=actualizarestado&id_proyectos=' . $id_proyecto . '&tipo=PorAprobar" type="button" class="btn btn-warning" data-bs-toggle="tooltip" data-bs-placement="top"
    data-bs-custom-class="custom-tooltip" data-bs-title="Volver a enviar proyecto">
    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" 
    class="bi bi-arrow-repeat" viewBox="0 0 16 16">
      <path d="M11.534 7h3.932a.25.25 0 0 1 .192.41l-1.966 2.36a.25.25 0 0 1-.384 0l-1.966-2.36a.25.25 0 0 1 .192-.41m-11 2h3.932a.25.25 0 0 0 .192-.41L2.692 6.23a.25.25 0 0 0-.384 0L.342 8.59A.25.25 0 0 0 .534 9"/>
      <path fill-rule="evenodd" d="M8 3c-1.552 0-2.94.707-3.857 1.818a.5.5 0 1 1-.771-.636A6.002 6.002 0 0 1 13.917 7H12.9A5 5 0 0 0 8 3M3.1 9a5.002 5.002 0 0 0 8.757 2.182.5.5 0 1 1 .771.636A6.002 6.002 0 0 1 2.083 9z"/>
    </svg></a>';
                break;
            case 'GenerarConstancia':
                $boton = '<a href="constancias.php?action=generar&id_proyectos=' . $id_proyecto . '" type="button" class="btn btn-primary" data-bs-toggle="tooltip" data-bs-placement="top"
        data-bs-custom-class="custom-tooltip" data-bs-title="Descargar constancia de terminación"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-file-earmark-pdf-fill" viewBox="0 0 16 16">
  <path d="M5.523 12.424q.21-.124.459-.238a8 8 0 0 1-.45.606c-.28.337-.498.516-.635.572l-.035.012a.3.3 0 0 1-.026-.044c-.056-.11-.054-.216.04-.36.106-.165.319-.354.647-.548m2.455-1.647q-.178.037-.356.078a21 21 0 0 0 .5-1.05 12 12 0 0 0 .51.858q-.326.048-.654.114m2.525.939a4 4 0 0 1-.435-.41q.344.007.612.054c.317.057.466.147.518.209a.1.1 0 0 1 .026.064.44.44 0 0 1-.06.2.3.3 0 0 1-.094.124.1.1 0 0 1-.069.015c-.09-.003-.258-.066-.498-.256M8.278 6.97c-.04.244-.108.524-.2.829a5 5 0 0 1-.089-.346c-.076-.353-.087-.63-.046-.822.038-.177.11-.248.196-.283a.5.5 0 0 1 .145-.04c.013.03.028.092.032.198q.008.183-.038.465z"/>
  <path fill-rule="evenodd" d="M4 0h5.293A1 1 0 0 1 10 .293L13.707 4a1 1 0 0 1 .293.707V14a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V2a2 2 0 0 1 2-2m5.5 1.5v2a1 1 0 0 0 1 1h2zM4.165 13.668c.09.18.23.343.438.419.207.075.412.04.58-.03.318-.13.635-.436.926-.786.333-.401.683-.927 1.021-1.51a11.7 11.7 0 0 1 1.997-.406c.3.383.61.713.91.95.28.22.603.403.934.417a.86.86 0 0 0 .51-.138c.155-.101.27-.247.354-.416.09-.181.145-.37.138-.563a.84.84 0 0 0-.2-.518c-.226-.27-.596-.4-.96-.465a5.8 5.8 0 0 0-1.335-.05 11 11 0 0 1-.98-1.686c.25-.66.437-1.284.52-1.794.036-.218.055-.426.048-.614a1.24 1.24 0 0 0-.127-.538.7.7 0 0 0-.477-.365c-.202-.043-.41 0-.601.077-.377.15-.576.47-.651.823-.073.34-.04.736.046 1.136.088.406.238.848.43 1.295a20 20 0 0 1-1.062 2.227 7.7 7.7 0 0 0-1.482.645c-.37.22-.699.48-.897.787-.21.326-.275.714-.08 1.103"/>
</svg></a>';
                break;
            case 'Editar':
                $boton = '<a href="editar.php?id_proyectos=' . $id_proyecto . '" type="button" class="btn btn-info" data-bs-toggle="tooltip" data-bs-placement="top"
        data-bs-custom-class="custom-tooltip" data-bs-title="Editar proyecto"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-pencil-square" viewBox="0 0 16 16">
  <path d="M15.502 1.94a.5.5 0 0 1 0 .706L14.459 3.69l-2-2L13.502.646a.5.5 0 0 1 .707 0l1.293 1.293zm-1.75 2.456-2-2L4.939 9.21a.5.5 0 0 0-.121.196l-.805 2.414a.25.25 0 0 0 .316.316l2.414-.805a.5.5 0 0 0 .196-.12l6.813-6.814z"/>
  <path fill-rule="evenodd" d="M1 13.5A1.5 1.5 0 0 0 2.5 15h11a1.5 1.5 0 0 0 1.5-1.5v-6a.5.5 0 0 0-1 0v6a.5.5 0 0 1-.5.5h-11a.5.5 0 0 1-.5-.5v-11a.5.5 0 0 1 .5-.5H9a.5.5 0 0 0 0-1H2.5A1.5 1.5 0 0 0 1 2.5z"/>
</svg></a>';
                break;
            case 'Rechazar creacion':
                $boton = '<a href="comentarios.php?id_proyectos=' . $id_proyecto . '&motivo=creacion_rechazada" type="button" class="btn btn-danger" data-bs-toggle="tooltip" data-bs-placement="top"
        data-bs-custom-class="custom-tooltip" data-bs-title="Rechazar creación de proyecto"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-x-circle-fill" viewBox="0 0 16 16">
  <path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0M5.354 4.646a.5.5 0 1 0-.708.708L7.293 8l-2.647 2.646a.5.5 0 0 0 .708.708L8 8.707l2.646 2.647a.5.5 0 0 0 .708-.708L8.707 8l2.647-2.646a.5.5 0 0 0-.708-.708L8 7.293z"/>
</svg></a>';
                break;
            case 'Comentarios':
                $boton = '<a href="ver_comentarios.php?id_proyectos=' . $id_proyecto . '" type="button" class="btn btn-info" data-bs-toggle="tooltip" data-bs-placement="top"
        data-bs-custom-class="custom-tooltip" data-bs-title="Ver comentarios"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-chat-dots-fill" viewBox="0 0 16 16">
  <path d="M16 8c0 3.866-3.582 7-8 7a9 9 0 0 1-2.347-.306c-.584.296-1.925.864-4.181 1.234-.2.032-.352-.176-.273-.362.354-.836.674-1.95.77-2.966C.744 11.37 0 9.76 0 8c0-3.866 3.582-7 8-7s8 3.134 8 7M5 8a1 1 0 1 0-2 0 1 1 0 0 0 2 0m4 0a1 1 0 1 0-2 0 1 1 0 0 0 2 0m3 1a1 1 0 1 0 0-2 1 1 0 0 0 0 2"/>
</svg></a>';
                break;
            case 'Dar de baja':
                $boton = '<button class="btn btn-danger btn-sm" data-accion="baja"data-id="' . $id_usuario . '">Dar de baja</button>';
                break;

            case 'Reactivar':
                $boton = '<button class="btn btn-success btn-sm" data-accion="reactivar" data-id="' . $id_usuario . '">Reactivar</button>';
                break;
            default:
                break;
        }
        return $boton;
    }

    //Botones de acción en la tabla 
    public function botonesAccion($id, $rol, $estado = null, $extra = null, $estado_completados_estudiantes = 0)
    {
        $solicitar = '';
        if ($estado_completados_estudiantes == 1) {
            $solicitar = 'Solicitar cerrar';
        } elseif ($estado_completados_estudiantes == 0) {
            $solicitar = '';
        }
        //Mapa de acciones por rol y estado
        $acciones = [

            'estudiante' => [
                'Activo' => ['Detalles', 'Ver Tareas Alumnos'],
                'Por cerrar' => ['Detalles', 'Ver Tareas Alumnos'],
                'Vencido' => ['Detalles', 'Ver Tareas Alumnos'],
                'Cierre' => ['Detalles', 'Ver Tareas Alumnos', 'GenerarConstancia'],
            ],

            'investigador' => [
                'Activo' => ['Detalles', 'Tareas', 'Editar', 'Comentarios', $solicitar],
                'Por aprobar' => ['Detalles', 'Comentarios'],
                'Por cerrar' => ['Detalles', 'Tareas', 'Comentarios'],
                'Cierre' => ['Detalles', 'Tareas', 'Comentarios'],
                'Cierre rechazado' => ['Volver a enviar cierre', 'Detalles', 'Editar', 'Tareas', 'Comentarios'],
                'Rechazado' => ['Volver a enviar proyecto', 'Detalles', 'Editar', 'Comentarios'],
                'Vencido' => ['Detalles', 'Tareas', 'Comentarios'],
            ],

            'profesor' => [
                'Activo' => ['Detalles', 'Tareas', 'Editar', 'Comentarios', $solicitar],
                'Por aprobar' => ['Detalles', 'Comentarios'],
                'Por cerrar' => ['Detalles', 'Tareas', 'Comentarios'],
                'Cierre' => ['Detalles', 'Tareas', 'Comentarios'],
                'Cierre rechazado' => ['Volver a enviar cierre', 'Detalles', 'Editar', 'Tareas', 'Comentarios'],
                'Rechazado' => ['Volver a enviar proyecto', 'Detalles', 'Editar', 'Comentarios'],
                'Vencido' => ['Detalles', 'Tareas', 'Comentarios'],
            ],

            'supervisor' => [
                'Activo' => ['Detalles', 'Tareas', 'Comentarios'],
                'Por aprobar' => ['Aprobar', 'Detalles', 'Rechazar creacion', 'Comentarios'],
                'Por cerrar' => ['Aprobar cierre', 'Detalles', 'Tareas', 'Rechazar cierre', 'Comentarios'],
                'Cierre rechazado' => ['Detalles', 'Tareas', 'Comentarios'],
                'Cierre' => ['Detalles', 'Tareas', 'Comentarios'],
                'Vencido' => ['Detalles', 'Tareas', 'Comentarios'],
                'Rechazado' => ['Detalles', 'Comentarios'],
            ]
        ];

        if (!isset($acciones[$rol][$estado])) {
            return "";
        }

        $botones = "";

        foreach ($acciones[$rol][$estado] as $accion) {
            //Caso especial para pasar $extra
            if ($accion === 'Ver Tareas Alumnos') {
                $botones .= $this->obtenerbotones($accion, null, $extra);
            } else {
                $botones .= $this->obtenerbotones($accion, $id);
            }
        }

        return $botones;
    }


    public function accionEstudiante($datos)
    {
        global $conn;
        $action = $datos['action'] ?? '';
        $id_proyecto = $datos['id_proyecto'];
        $id_estudiante = $datos['id_estudiante'];
        $motivo = $datos['motivo'] ?? null;

        $modelo = new Proyectos($conn);

        switch ($action) {

            case 'baja':
                $res = $modelo->bajaEstudiante(
                    $id_proyecto,
                    $id_estudiante,
                    $motivo,
                    $_SESSION['id_usuario']
                );
                break;

            case 'reactivar':
                $res = $modelo->reactivarEstudiante(
                    $id_proyecto,
                    $id_estudiante,
                    $_SESSION['id_usuario']
                );
                break;
        }

        header("Location: editar.php?id_proyectos". $id_proyecto."&mensaje=1");
    }

    public function botonesAccionEditarEstudiante($id_estudiante, $rol, $estado, $id_proyecto)
    {
        $boton = "";

        if ($rol == 'investigador' || $rol == 'profesor') {

            if ($estado == "activo") {
                $boton .= $this->obtenerbotones("Dar de baja", $id_proyecto, $id_estudiante);
            }

            if ($estado == "baja") {
                $boton .= $this->obtenerbotones("Reactivar", $id_proyecto, $id_estudiante);
            }
        }

        return $boton;
    }

    //TEMATICA
    public function tematica()
    {
        global $conn;
        try {
            $proyecto = new Proyectos($conn);
            $proyecto->actualizarProyectosVencidos();

            return $proyecto->tematica() ?? [];
        } catch (Exception $e) {
            error_log($e->getMessage());
            return [];
        }
    }

    //SUBTEMATICAS
    public function subtematicas($id)
    {
        global $conn;
        try {
            $proyecto = new Proyectos($conn);
            $proyecto->actualizarProyectosVencidos();

            return $proyecto->obtenersubtematica((int)$id);
        } catch (Exception $e) {
            error_log($e->getMessage());
            return [];
        }
    }

    //PERIODO
    public function obtenerperiodo()
    {
        global $conn;
        try {
            $proyecto = new Proyectos($conn);
            $proyecto->actualizarProyectosVencidos();

            return $proyecto->obtenerperiodo() ?? [];
        } catch (Exception $e) {
            error_log($e->getMessage());
            return [];
        }
    }

    //INSTITUTO
    public function obtenerInstituto()
    {
        global $conn;
        try {
            $proyecto = new Proyectos($conn);
            $proyecto->actualizarProyectosVencidos();

            return $proyecto->obtenerinstituto() ?? [];
        } catch (Exception $e) {
            error_log($e->getMessage());
            return [];
        }
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



    public function registrarProyecto($datos, $id, $rol)
    {
        global $conn;
        try {
            $this->validarMetodo('POST');
            $this->validarAcceso($rol, ['investigador', 'profesor']);

            $periodo = $this->obtenerPeriodo();

            //$id_tematica = $datos['Tematica'] ?? null;
            $subtematicas = $datos['subtematicas'] ?? [];

            if (empty($subtematicas)) {
                throw new Exception("Datos incompletos");
            }

            $instituto = $this->obtenerInstituto()[0] ?? null;
            if (!$datos['NombreProyecto']) {
                throw new Exception("Falta nombre del proyecto");
            }

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

            header("Location: tabla.php?mensaje=1");
            exit();
        } catch (Exception $e) {
            die($e->getMessage());
        }
    }

    /* EDITAR PROYECTO */
    public function editarProyecto($datos, $id_usuario, $rol)
    {
        global $conn;
        try {
            $this->validarMetodo('POST');
            $this->validarAcceso($rol, ['investigador', 'profesor']);

            $this->obtenerPeriodo();

            $id_proyecto = (int)($datos['id_proyectos'] ?? 0);
            $subtematicas = $datos['subtematicas'] ?? [];

            if (!$id_proyecto || empty($subtematicas)) {
                throw new Exception("Datos incompletos");
            }

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

            header("Location: tabla.php?id_proyectos={$id_proyecto}");
            exit();
        } catch (Exception $e) {
            return $e->getMessage();
        }
    }

    public function subtematicasProyecto($id_proyecto)
    {
        global $conn;
        try {
            $proyecto = new Proyectos($conn);
            $proyecto->actualizarProyectosVencidos();
            return $proyecto->obtenersubtematicasProyecto($id_proyecto);
        } catch (Exception $e) {
            return $e->getMessage();
        }
    }

    /* ACCIÓN DE RECHAZAR CIERRE */
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

            header("Location: tabla.php?mensaje=1");
            exit();
        } catch (Exception $e) {
            return $e->getMessage();
        }
    }
    //Actualizar estado de proyectos sin comentarios
    public function actualizarestado($id_proyecto, $rol, $tipo)
    {
        global $conn;
        try {
            $this->validarMetodo('GET');
            $this->validarAcceso($rol, ['supervisor', 'investigador', 'profesor']);

            $proyecto = new Proyectos($conn);
            $proyecto->actualizarProyectosVencidos();

            $estado = $this->numerofiltro($tipo);
            $porcentaje = $this->obtenerPorcentajeAvance((int)$id_proyecto);

            $proyecto->actualizarestado((int)$id_proyecto, $estado, $porcentaje);

            header("Location: tabla.php?mensaje=1");
            exit();
        } catch (Exception $e) {
            return $e->getMessage();
        }
    }

    public function datosproyecto($id_proyecto)
    {
        global $conn;
        $proyecto = new Proyectos($conn);
        $proy = $proyecto->obtenerProyecto($id_proyecto);
        return $proy;
    }
    public function datosinvestigador($id_proyecto)
    {
        global $conn;
        $proyecto = new Proyectos($conn);
        $investigador = $proyecto->obtenerProyectoInvestigador($id_proyecto);

        // Obtener id_usuario del investigador
        $id_usuario = $investigador['id_usuarios'] ?? null;

        $area = $proyecto->obtenerUsuarioArea($id_usuario);
        $lineas = $proyecto->obtenerInvestigadorLinea($id_proyecto);

        return [
            "investigador" => $investigador,
            "area" => $area,
            "lineas" => $lineas
        ];
    }
    public function datosestudiantes($id_proyecto)
    {
        global $conn;
        $proyecto = new Proyectos($conn);
        $estudiante = $proyecto->obtenerProyectoEstudiante($id_proyecto);

        // Obtener id_usuario del investigador
        $id_usuario = $estudiante['id_usuario'] ?? null;

        $area = $proyecto->obtenerUsuarioArea($id_usuario);

        return [
            "estudiante" => $estudiante,
            "area" => $area
        ];
    }

    public function comentarios($id_proyecto)
    {
        global $conn;
        $proyecto = new Proyectos($conn);
        return $proyecto->obtenerProyectoComentarios($id_proyecto);
    }

    public function estudiantes($id_proyecto)
    {
        global $conn;
        $proyecto = new Proyectos($conn);
        $estudiante = $proyecto->estudiantes($id_proyecto);

        return $estudiante;
    }

    public function obtenerEstudianteProyecto($id_proyecto, $id_estudiante)
    {
        global $conn;
        $proyecto = new Proyectos($conn);
        $datos = $proyecto->obtenerEstudianteProyecto($id_proyecto, $id_estudiante);

        return $datos;
    }

    public function historial_estudiante_proyecto($id_proyecto, $id_usuario)
    {
        global $conn;

        try {
            $pagina = $_GET['pagina'] ?? 1;

            $proyecto = new Proyectos($conn);

            return $proyecto->lineaTiempoProyectoUsuarios($id_proyecto, $id_usuario, $pagina);
        } catch (Exception $e) {
            error_log($e->getMessage());
            header("Location: editar.php?error=1");
            exit;
        }
    }
}
