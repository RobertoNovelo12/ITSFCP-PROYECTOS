<?php

require_once __DIR__ . '/../Modelos/solicitudes.php';
require_once __DIR__ . '/../publico/config/conexion.php';

class solicitudesControlador
{

    public function index($id, $rol, $buscar = null)
    {
        global $conn;

        $solicitudes = new Solicitud($conn);

        if ($rol == "investigador" || $rol == "estudiante" || $rol == "supervisor") {
            //General
            $solicitud = $solicitudes->obtenerSolicitudes($id, $rol);
            return $solicitud;
        } else {
            $solicitud = []; // evita undefined variable
            return $solicitud;
        }
    }

    //Para obtener los encabezados de las tablas
    public function encabezados()
    {
        $encabezados = [
            'ID',
            'Estudiante',
            'Carrera',
            'Matricula',
            'Proyecto',
            'Fecha solicitud',
            'Comentarios',
            'Estado',
            'Acciones'
        ];
        return $encabezados;
    }


    public function obtenerbotones($tipo, $id_solicitud_proyectos, $id_proyectos = null)
    {
        $boton = "";
        switch ($tipo) {
            case 'Detalles':
                $boton = '<a href="/ITSFCP-PROYECTOS/Vistas/Proyectos/detalles_proyecto.php?id=' . $id_proyectos . '">
                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="tooltip" data-bs-placement="top"
                    data-bs-custom-class="custom-tooltip" data-bs-title="Ver detalles del proyecto">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-eye-fill" style="padding:0px;margin:auto;" viewBox="0 0 16 16">
                            <path d="M10.5 8a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0"/>
                            <path d="M0 8s3-5.5 8-5.5S16 8 16 8s-3 5.5-8 5.5S0 8 0 8m8 3.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7"/>
                        </svg>
                    </button>
                </a>';
                break;
            case 'VerDatos':
                $boton = '<button type="button" class="btn btn-info btn-sm" data-bs-toggle="tooltip" data-bs-placement="top"
                    data-bs-custom-class="custom-tooltip" data-bs-title="Ver datos de la solicitud" onclick="verDatosSolicitud(' . $id_solicitud_proyectos . ')">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-file-text-fill" viewBox="0 0 16 16">
                            <path d="M12 0H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V2a2 2 0 0 0-2-2M5 4h6a.5.5 0 0 1 0 1H5a.5.5 0 0 1 0-1m-.5 2.5A.5.5 0 0 1 5 6h6a.5.5 0 0 1 0 1H5a.5.5 0 0 1-.5-.5M5 8h6a.5.5 0 0 1 0 1H5a.5.5 0 0 1 0-1m0 2h3a.5.5 0 0 1 0 1H5a.5.5 0 0 1 0-1"/>
                        </svg>
                    </button>';
                break;
            case 'Aprobar':
                $boton = '<button type="button" class="btn btn-success btn-sm" data-bs-toggle="tooltip" data-bs-placement="top"
                    data-bs-custom-class="custom-tooltip" data-bs-title="Aprobar solicitud" onclick="confirmarAprobacion(' . $id_solicitud_proyectos . ')">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" class="bi bi-check-circle-fill" viewBox="0 0 16 16">
                            <path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0m-3.97-3.03a.75.75 0 0 0-1.08.022L7.477 9.417 5.384 7.323a.75.75 0 0 0-1.06 1.06L6.97 11.03a.75.75 0 0 0 1.079-.02l3.992-4.99a.75.75 0 0 0-.01-1.05z"/>
                        </svg>
                    </button>';
                break;
            case 'Rechazar':
                $boton = '<button type="button" class="btn btn-danger btn-sm" data-bs-toggle="tooltip" data-bs-placement="top"
                    data-bs-custom-class="custom-tooltip" data-bs-title="Rechazar solicitud" onclick="abrirRechazoSolicitud(' . $id_solicitud_proyectos . ')">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-ban" style="padding:0;margin:auto;" viewBox="0 0 16 16">
                            <path d="M15 8a6.97 6.97 0 0 0-1.71-4.584l-9.874 9.875A7 7 0 0 0 15 8M2.71 12.584l9.874-9.875a7 7 0 0 0-9.874 9.874ZM16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0"/>
                        </svg>
                    </button>';
                break;
            default:
                break;
        }
        return $boton;
    }

    //Botones de acción en la tabla 
    public function botonesAccion($id_solicitud, $rol, $id_proyectos, $estado = null)
    {
        $boton = "";

        // Normalizar el estado a minúsculas para comparación
        $estado_lower = strtolower(trim($estado ?? ''));

        switch ($rol) {
            case 'estudiante':
                $boton = $this->obtenerbotones("Detalles", $id_solicitud, $id_proyectos);
                break;

            case 'investigador':
            case 'profesor':
                if ($estado_lower == "pendiente") {
                    $boton = $this->obtenerbotones("VerDatos", $id_solicitud);
                    $boton .= ' ';
                    $boton .= $this->obtenerbotones("Detalles", $id_solicitud, $id_proyectos);
                    $boton .= ' ';
                    $boton .= $this->obtenerbotones("Aprobar", $id_solicitud);
                    $boton .= ' ';
                    $boton .= $this->obtenerbotones("Rechazar", $id_solicitud);
                } else if ($estado_lower == "aceptado") {
                    $boton = $this->obtenerbotones("VerDatos", $id_solicitud);
                    $boton .= ' ';
                    $boton .= $this->obtenerbotones("Detalles", $id_solicitud, $id_proyectos);
                } else if ($estado_lower == "rechazado") {
                    $boton = $this->obtenerbotones("VerDatos", $id_solicitud);
                    $boton .= ' ';
                    $boton .= $this->obtenerbotones("Detalles", $id_solicitud, $id_proyectos);
                } else {
                    // Por defecto, si el estado no coincide, mostrar al menos ver datos y detalles
                    $boton = $this->obtenerbotones("VerDatos", $id_solicitud);
                    $boton .= ' ';
                    $boton .= $this->obtenerbotones("Detalles", $id_solicitud, $id_proyectos);
                }
                break;

            case 'supervisor':
                $boton = $this->obtenerbotones("VerDatos", $id_solicitud);
                $boton .= ' ';
                $boton .= $this->obtenerbotones("Detalles", $id_solicitud, $id_proyectos);
                break;

            default:
                $boton = '';
                break;
        }
        return $boton;
    }

    /* ACCIÓN DE RECHAZAR CIERRE */
    public function actualizarestadoRechazo($data, $id_usuario, $rol)
    {
        $action = $data['action'] ?? '';
        if (!empty($comentario = $data['comentario']) && !empty($id_solicitud_proyecto = $data['id_solicitud_proyecto'])) {
            if ($_SERVER['REQUEST_METHOD'] == 'POST') {
                if ($rol == "supervisor" || $rol == "investigador" || $rol == "profesor") {
                    if ($action == 'actualizarestadoRechazo') {
                        $id_solicitud_proyecto = $data['id_solicitud_proyecto'];
                        $tipo = $data['tipo'];
                        $comentario = $data['comentario'];

                        global $conn;
                        $Solicitud = new Solicitud($conn);
                        $Solicitud->actualizarEstadoSolicitudRechazo($id_usuario, $id_solicitud_proyecto, $tipo, $comentario);
                    } else {
                        die("No es la acción correspondiente");
                    }
                } else {
                    die("El usuario no tiene permiso para rechazar la solicitud");
                }
            } else {
                die("Los datos no fueron enviados correctamente");
            }
        }
    }

    //Actualizar estado de proyectos sin comentarios
    public function actualizarestado($id_solicitud_proyecto, $estado)
    {
        if ($_SERVER['REQUEST_METHOD'] == 'GET') {
            global $conn;
            $Solicitud = new Solicitud($conn);
            $Solicitud->actualizarestado($id_solicitud_proyecto, $estado);
        } else {
            die("Los datos no fueron enviados");
        }
    }



    public function comentarios($id_solicitud_proyecto)
    {
        global $conn;
        $Solicitud = new Solicitud($conn);
        return $Solicitud->obtenerSolicitudComentarios($id_solicitud_proyecto);
    }

    // NUEVO: Obtener datos completos de la solicitud
    public function obtenerDatosSolicitud($id_solicitud)
    {
        global $conn;
        $Solicitud = new Solicitud($conn);
        return $Solicitud->obtenerDatosCompletosolicitud($id_solicitud);
    }
}