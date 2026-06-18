<?php
// Modelos/SolictudGrado.php

require_once __DIR__ . '/../Repository/solicitud_grado_repository.php';
require_once __DIR__ . '/../../../public/incluido/FileUploader.php';

/**
 * SolicitudGrado (Modelo)
 *
 * Responsabilidad exclusiva: lógica de negocio del módulo de
 * solicitudes de actualización de grado académico.
 * Delega toda ejecución SQL a SolicitudGradoRepositorio.
 *
 * No extiende BaseModelo porque no ejecuta SQL directamente.
 */
class SolicitudGrado
{
    private SolicitudGradoRepositorio $repo;

    public function __construct(mysqli $conn)
    {
        $this->repo = new SolicitudGradoRepositorio($conn);
    }


    // 
    // CATÁLOGO
    // 

    /**
     * Devuelve todos los grados académicos activos.
     *
     * @return array[]
     */
    public function obtenerGrados(): array
    {
        return $this->repo->obtenerGrados();
    }


    // 
    // DATOS ACTUALES DEL INVESTIGADOR
    // 

    /**
     * Devuelve los datos del investigador con su grado académico actual.
     *
     * @return array|null
     */
    public function obtenerDatosInvestigador(int $id_usuario): ?array
    {
        return $this->repo->obtenerDatosInvestigador($id_usuario);
    }


    // 
    // CREAR SOLICITUD GRADO (investigador)
    // 

    /**
     * Valida y registra una nueva solicitud de cambio de grado académico.
     *
     * @param  array  $archivo  Entrada de $_FILES correspondiente al PDF.
     * @return array{ok: bool, msg: string}
     */
    public function crearSolicitud(int $id_usuario, int $valor_actual_id, int $valor_nuevo_id, array $archivo): array
    {
        // 1. Validar que el valor cambió
        if ($valor_actual_id === $valor_nuevo_id) {
            return ['ok' => false, 'msg' => 'El grado académico nuevo es igual al actual.'];
        }

        // 2. Verificar que no hay solicitud pendiente
        if ($this->repo->tieneSolicitudPendiente($id_usuario)) {
            return ['ok' => false, 'msg' => 'Ya tienes una solicitud de grado académico en proceso.'];
        }

        // 3. Validar y subir PDF usando FileUploader
        $uploader = new FileUploader('storage/academico/usuario_' . $id_usuario);
        $allowedMimes = ['application/pdf'];
        $maxSize = 2 * 1024 * 1024; // 2 MB
        $prefix = 'grado_evidencia';

        try {
            $fileInfo = $uploader->upload($archivo, $allowedMimes, $maxSize, $prefix);
        } catch (RuntimeException $e) {
            return ['ok' => false, 'msg' => $e->getMessage()];
        }

        // 4 – 6. Insertar documento, solicitud e historial (con manejo de errores)
        try {
            $nombre_display = 'Evidencia Grado - ' . date('d/m/Y');
            $id_documento   = $this->repo->insertarDocumento(
                $fileInfo['nombre_display'],
                $fileInfo['nombre_archivo'],
                $fileInfo['ruta_bd'],
                $fileInfo['tamano'],
                $id_usuario
            );

            $id_solicitud = $this->repo->insertarSolicitud(
                $id_usuario,
                $id_documento,
                $valor_actual_id,
                $valor_nuevo_id
            );

            $this->repo->insertarHistorial(
                $id_solicitud,
                $id_usuario,
                null,
                'pendiente',
                'Solicitud de grado académico creada por el investigador.'
            );
        } catch (Exception $e) {
            return ['ok' => false, 'msg' => $e->getMessage()];
        }

        return ['ok' => true, 'msg' => 'Solicitud de grado académico enviada correctamente. Queda pendiente de revisión.'];
    }

    // 
    //  VERIFICAR SOLICITUD PENDIENTE ACTIVA
    // 

    public function tieneSolicitudPendiente($id_usuario)
    {
                return $this->repo->tieneSolicitudPendiente($id_usuario);
    }


    // 
    // HISTORIAL DEL INVESTIGADOR (línea de tiempo)
    // 

    /**
     * Devuelve el historial paginado de solicitudes de grado del investigador,
     * agrupado por fecha.
     *
     * @return array{datos: array, paginacion: array}
     */
    public function historialInvestigador(int $id_usuario, int $pagina = 1, int $por_pagina = 8): array
    {
        $pagina        = max(1, $pagina);
        $desde         = ($pagina - 1) * $por_pagina;
        $total         = $this->repo->contarHistorialInvestigador($id_usuario);
        $total_paginas = max(1, (int)ceil($total / $por_pagina));

        $filas    = $this->repo->listarHistorialInvestigador($id_usuario, $desde, $por_pagina);
        $agrupado = [];
        foreach ($filas as $f) {
            $fecha = date('d/m/Y', strtotime($f['fecha']));
            $agrupado[$fecha][] = $f;
        }

        return [
            'datos'      => $agrupado,
            'paginacion' => [
                'total'         => $total,
                'por_pagina'    => $por_pagina,
                'pagina'        => $pagina,
                'total_paginas' => $total_paginas,
            ],
        ];
    }


    // 
    // SOLICITUDES PARA SUPERVISOR
    // 

    /**
     * Devuelve los conteos por estado de todas las solicitudes de grado.
     *
     * @return array
     */
    public function conteosFiltros(): array
    {
        return $this->repo->conteosFiltros();
    }

    /**
     * Devuelve el listado paginado de solicitudes de grado junto con
     * los datos de paginación, serializado en JSON.
     *
     * @return string  JSON con claves 'solicitudes' y 'paginacion'.
     */
    public function obtenerSolicitudes(?string $estado = null, ?string $buscar = null): string
    {
        $por_pagina    = 8;
        $pagina        = max(1, intval($_GET['pagina'] ?? 1));
        $desde         = ($pagina - 1) * $por_pagina;
        $total         = $this->repo->contarSolicitudes($estado, $buscar);
        $total_paginas = max(1, (int)ceil($total / $por_pagina));

        $filas = $this->repo->listarSolicitudes($estado, $buscar, $desde, $por_pagina);

        return json_encode([
            'solicitudes' => $filas,
            'paginacion'  => [
                'total'         => $total,
                'por_pagina'    => $por_pagina,
                'pagina'        => $pagina,
                'total_paginas' => $total_paginas,
            ],
        ]);
    }


    // 
    // DETALLE DE UNA SOLICITUD (supervisor)
    // 

    /**
     * Devuelve todos los datos de una solicitud de grado.
     *
     * @return array|null
     */
    public function obtenerDetalle(int $id_solicitud): ?array
    {
        return $this->repo->obtenerDetalle($id_solicitud);
    }


    // 
    // HISTORIAL DE UNA SOLICITUD (supervisor)
    // 

    /**
     * Devuelve el historial completo de una solicitud de grado.
     *
     * @return array[]
     */
    public function historialDeSolicitud(int $id_solicitud): array
    {
        return $this->repo->historialDeSolicitud($id_solicitud);
    }


    // 
    // APROBAR SOLICITUD (supervisor) — TRANSACCIONAL
    // 

    /**
     * Aprueba una solicitud de grado: actualiza el investigador,
     * cambia el estado y registra el historial, todo en una transacción.
     *
     * @return array{ok: bool, msg: string}
     */
    public function aprobarSolicitud(int $id_solicitud, int $id_supervisor): array
    {
        $detalle = $this->repo->obtenerDetalle($id_solicitud);
        if (empty($detalle) || $detalle['estado'] !== 'pendiente') {
            return ['ok' => false, 'msg' => 'La solicitud ya no está pendiente.'];
        }

        $id_usuario  = (int)$detalle['id_usuarios'];
        $valor_nuevo = (int)$detalle['valor_nuevo_id'];
        $conn        = $this->repo->getConn();

        $conn->begin_transaction();
        try {
            $this->repo->actualizarGradoInvestigador($id_usuario, $valor_nuevo);
            $this->repo->aprobarSolicitudDB($id_solicitud, $id_supervisor);
            $this->repo->insertarHistorial(
                $id_solicitud,
                $id_supervisor,
                'pendiente',
                'aprobado',
                'Grado académico validado y aprobado por supervisor.'
            );
            $conn->commit();
            return ['ok' => true, 'msg' => 'Solicitud de grado académico aprobada correctamente.'];
        } catch (Exception $e) {
            $conn->rollback();
            return ['ok' => false, 'msg' => $e->getMessage()];
        }
    }


    // 
    // RECHAZAR SOLICITUD (supervisor) — TRANSACCIONAL
    // 

    /**
     * Rechaza una solicitud de grado y registra el historial en una transacción.
     *
     * @return array{ok: bool, msg: string}
     */
    public function rechazarSolicitud(int $id_solicitud, int $id_supervisor, string $comentario): array
    {
        $comentario = trim($comentario);
        if (empty($comentario)) {
            return ['ok' => false, 'msg' => 'El comentario es obligatorio para rechazar.'];
        }

        $detalle = $this->repo->obtenerDetalle($id_solicitud);
        if (empty($detalle) || $detalle['estado'] !== 'pendiente') {
            return ['ok' => false, 'msg' => 'La solicitud ya no está pendiente.'];
        }

        $conn = $this->repo->getConn();
        $conn->begin_transaction();
        try {
            $this->repo->rechazarSolicitudDB($id_solicitud, $id_supervisor);
            $this->repo->insertarHistorial(
                $id_solicitud,
                $id_supervisor,
                'pendiente',
                'rechazado',
                $comentario
            );
            $conn->commit();
            return ['ok' => true, 'msg' => 'Solicitud de grado académico rechazada.', 'detalle' => $detalle];
        } catch (Exception $e) {
            $conn->rollback();
            return ['ok' => false, 'msg' => $e->getMessage()];
        }
    }


    // 
    // DATOS DE CORREO
    // 

    /**
     * Devuelve correo y nombre del investigador para enviar notificación.
     *
     * @return array|null
     */
    public function obtenerCorreoInvestigador(int $id_usuario): ?array
    {
        return $this->repo->obtenerCorreoInvestigador($id_usuario);
    }
}
