<?php
// Modelos/SolicitudSni.php

require_once __DIR__ . '/../Repositorios/SolicitudSniRepositorio.php';

/**
 * SolicitudSni (Modelo)
 *
 * Responsabilidad exclusiva: lógica de negocio del módulo de
 * solicitudes de actualización de nivel SNI.
 * Delega toda ejecución SQL a SolicitudSniRepositorio.
 *
 * No extiende BaseModelo porque no ejecuta SQL directamente.
 */
class SolicitudSni
{
    private SolicitudSniRepositorio $repo;

    public function __construct(mysqli $conn)
    {
        $this->repo = new SolicitudSniRepositorio($conn);
    }


    // 
    // CATÁLOGO
    // 

    /**
     * Devuelve todos los niveles SNI activos.
     *
     * @return array[]
     */
    public function obtenerNivelesSni(): array
    {
        return $this->repo->obtenerNivelesSni();
    }


    // 
    // DATOS ACTUALES DEL INVESTIGADOR
    // 

    /**
     * Devuelve los datos del investigador con su nivel SNI actual.
     *
     * @return array|null
     */
    public function obtenerDatosInvestigador(int $id_usuario): ?array
    {
        return $this->repo->obtenerDatosInvestigador($id_usuario);
    }


    // 
    // CREAR SOLICITUD SNI (investigador)
    // 

    /**
     * Valida y registra una nueva solicitud de cambio de nivel SNI.
     *
     * @param  array  $archivo  Entrada de $_FILES correspondiente al PDF.
     * @return array{ok: bool, msg: string}
     */
    public function crearSolicitud(int $id_usuario, int $valor_actual_id, int $valor_nuevo_id, array $archivo): array
    {
        // 1. Validar que el valor cambió
        if ($valor_actual_id === $valor_nuevo_id) {
            return ['ok' => false, 'msg' => 'El nivel SNI nuevo es igual al actual.'];
        }

        // 2. Verificar que no hay solicitud pendiente
        if ($this->repo->tieneSolicitudPendiente($id_usuario)) {
            return ['ok' => false, 'msg' => 'Ya tienes una solicitud de nivel SNI en proceso.'];
        }

        // 3. Validar PDF
        if (empty($archivo['tmp_name'])) {
            return ['ok' => false, 'msg' => 'El documento PDF es obligatorio.'];
        }

        $finfo     = finfo_open(FILEINFO_MIME_TYPE);
        $mime_real = finfo_file($finfo, $archivo['tmp_name']);
        finfo_close($finfo);

        if ($mime_real !== 'application/pdf' || $archivo['type'] !== 'application/pdf') {
            return ['ok' => false, 'msg' => 'Solo se aceptan archivos PDF.'];
        }
        if ($archivo['size'] > 2 * 1024 * 1024) {
            return ['ok' => false, 'msg' => 'El archivo no debe superar 2 MB.'];
        }

        // 4. Guardar PDF en storage
        $dir = __DIR__ . '/../storage/academico/usuario_' . $id_usuario . '/';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $nombre_archivo = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $archivo['name']);
        $ruta_relativa  = '/storage/academico/usuario_' . $id_usuario . '/' . $nombre_archivo;

        if (!move_uploaded_file($archivo['tmp_name'], $dir . $nombre_archivo)) {
            return ['ok' => false, 'msg' => 'Error al guardar el documento.'];
        }

        // 5 – 7. Insertar documento, solicitud e historial (con manejo de errores)
        try {
            $nombre_display = 'Evidencia SNI - ' . date('d/m/Y');
            $id_documento   = $this->repo->insertarDocumento(
                $nombre_display,
                $nombre_archivo,
                $ruta_relativa,
                $archivo['size'],
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
                'Solicitud de nivel SNI creada por el investigador.'
            );
        } catch (Exception $e) {
            return ['ok' => false, 'msg' => $e->getMessage()];
        }

        return ['ok' => true, 'msg' => 'Solicitud de nivel SNI enviada correctamente. Queda pendiente de revisión.'];
    }

       // 
    // DATOS ACTUALES DEL INVESTIGADOR
    // 

    public function tieneSolicitudPendiente(int $id_usuario)
    {
        return $this->repo->tieneSolicitudPendiente($id_usuario);
    }


    // 
    // HISTORIAL DEL INVESTIGADOR (línea de tiempo)
    // 

    /**
     * Devuelve el historial paginado de solicitudes SNI del investigador,
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
     * Devuelve los conteos por estado de todas las solicitudes SNI.
     *
     * @return array
     */
    public function conteosFiltros(): array
    {
        return $this->repo->conteosFiltros();
    }

    /**
     * Devuelve el listado paginado de solicitudes SNI junto con
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
     * Devuelve todos los datos de una solicitud SNI.
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
     * Devuelve el historial completo de una solicitud SNI.
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
     * Aprueba una solicitud SNI: actualiza el investigador,
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
            $this->repo->actualizarNivelSniInvestigador($id_usuario, $valor_nuevo);
            $this->repo->aprobarSolicitudDB($id_solicitud, $id_supervisor);
            $this->repo->insertarHistorial(
                $id_solicitud,
                $id_supervisor,
                'pendiente',
                'aprobado',
                'Nivel SNI validado y aprobado por supervisor.'
            );
            $conn->commit();
            return ['ok' => true, 'msg' => 'Solicitud de nivel SNI aprobada correctamente.'];
        } catch (Exception $e) {
            $conn->rollback();
            return ['ok' => false, 'msg' => $e->getMessage()];
        }
    }


    // 
    // RECHAZAR SOLICITUD (supervisor) — TRANSACCIONAL
    // 

    /**
     * Rechaza una solicitud SNI y registra el historial en una transacción.
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
            return ['ok' => true, 'msg' => 'Solicitud de nivel SNI rechazada.', 'detalle' => $detalle];
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