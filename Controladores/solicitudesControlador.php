<?php

/**
 * solicitudesControlador.php
 * Solo accesible para el rol 'investigador'.
 */

require_once __DIR__ . '/../Modelos/solicitudes.php';
require_once __DIR__ . '/../publico/config/conexion.php';

class solicitudesControlador
{

    // ----------------------------------------------------------------
    // Helpers privados
    // ----------------------------------------------------------------

    private function soloInvestigador(string $rol): void
    {
        if (!in_array($rol, ['investigador', 'profesor'], true)) {
            http_response_code(403);
            die('Acceso denegado.');
        }
    }

    // Encabezados de tabla  
    public function encabezados(): array
    {
        return ['#', 'Estudiante', 'Matrícula', 'Carrera', 'Proyecto', 'Semestre', 'Promedio', 'Fecha', 'Estado', 'Acciones'];
    }

    private function json(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function botonesAccion(int $id_solicitud, string $estado, int $id_proyecto): string
    {
        $estado = strtolower(trim($estado));
        $btn = ''; // Siempre: Ver detalle 
        $btn .= $this->boton('detalle', $id_solicitud, $id_proyecto);
        if (in_array($estado, ['pendiente', 'en_revision', 'correcciones'], true)) {
            $btn .= ' ' . $this->boton('aceptar', $id_solicitud);
            $btn .= ' ' . $this->boton('correcciones', $id_solicitud);
            $btn .= ' ' . $this->boton('rechazar', $id_solicitud);
        }
        return $btn;
    }
    private function boton(string $tipo, int $id_solicitud, int $id_proyecto = 0): string
    {
        return match ($tipo) {
            'detalle' => "<a href='detalles_solicitud.php?id={$id_solicitud}' class='btn btn-info btn-sm' title='Ver solicitud' > <i class='bi bi-file-text-fill'></i> </a>",
            'aceptar' => " <button type='button' class='btn btn-success btn-sm' data-bs-toggle='tooltip' title='Aceptar solicitud' onclick='confirmarAceptar({$id_solicitud})'> <i class='bi bi-check-circle-fill'></i> </button>",
            'correcciones' => " <button type='button' class='btn btn-warning btn-sm' data-bs-toggle='tooltip' title='Pedir correcciones' onclick='abrirModalAccion({$id_solicitud},\"correcciones\")'> <i class='bi bi-pencil-fill'></i> </button>",
            'rechazar' => " <button type='button' class='btn btn-danger btn-sm' data-bs-toggle='tooltip' title='Rechazar solicitud' onclick='abrirModalAccion({$id_solicitud},\"rechazar\")'> <i class='bi bi-ban'></i> </button>",
            default => '',
        };
    }

    public function badgeEstado(string $estado): string
    {
        return match (strtolower($estado)) {
            'pendiente' => "<span class='badge bg-secondary'>Pendiente</span>",
            'en_revision' => "<span class='badge bg-info text-dark'>En revisión</span>",
            'correcciones' => "<span class='badge bg-warning text-dark'>Correcciones</span>",
            'aceptado' => "<span class='badge bg-success'>Aceptado</span>",
            'rechazado' => "<span class='badge bg-danger'>Rechazado</span>",
            default => "<span class='badge bg-light text-dark'>" . htmlspecialchars($estado) . "</span>",
        };
    }

    private function idUsuario(): int
    {
        return intval($_SESSION['id_usuario'] ?? 0);
    }

    private function rol(): string
    {
        return strtolower($_SESSION['rol'] ?? '');
    }

    // ----------------------------------------------------------------
    // index — vista principal con tabla + resumen
    // ----------------------------------------------------------------

    public function index(int $id_usuario, string $rol): array
    {

        $this->soloInvestigador($rol);

        global $conn;

        $Solicitudes = new Solicitud($conn);

        $por_pagina = 8;
        $pagina     = max(1, intval($_GET['pagina'] ?? 1));
        $desde      = ($pagina - 1) * $por_pagina;

        $filtros = [
            'estado'      => $_GET['estado']      ?? '',
            'buscar'      => $_GET['buscar']      ?? '',
            'proyecto'    => $_GET['proyecto']    ?? '',
            'semestre'    => $_GET['semestre']    ?? '',
            'fecha_desde' => $_GET['fecha_desde'] ?? '',
            'fecha_hasta' => $_GET['fecha_hasta'] ?? '',
        ];

        $total        = $Solicitudes->contarSolicitudes($id_usuario, $filtros);
        $solicitudes  = $Solicitudes->obtenerSolicitudes($id_usuario, $filtros, $desde, $por_pagina);
        $resumen      = $Solicitudes->resumen($id_usuario);
        $proyectos    = $Solicitudes->proyectosDelInvestigador($id_usuario);

        return [
            'solicitudes' => $solicitudes,
            'resumen'     => $resumen,
            'proyectos'   => $proyectos,
            'filtros'     => $filtros,
            'paginacion'  => [
                'total'         => $total,
                'por_pagina'    => $por_pagina,
                'pagina'        => $pagina,
                'total_paginas' => max(1, (int) ceil($total / $por_pagina)),
            ],
        ];
    }

    // ----------------------------------------------------------------
    // detalle — JSON para el modal (AJAX GET)
    // ----------------------------------------------------------------

    public function detalle(): void
    {

        $this->soloInvestigador($this->rol());

        global $conn;

        $Solicitudes = new Solicitud($conn);

        $id = intval($_GET['id'] ?? 0);
        if (!$id) $this->json(['error' => 'ID inválido.'], 422);

        if (!$Solicitudes->verificarPermiso($id, $this->idUsuario())) {
            $this->json(['error' => 'Sin permiso.'], 403);
        }

        $Solicitudes->marcarEnRevision($id);

        $data        = $Solicitudes->obtenerDetalle($id);
        $comentarios = $Solicitudes->obtenerComentarios($id);

        if (!$data) $this->json(['error' => 'Solicitud no encontrada.'], 404);

        $this->json([
            'solicitud'  => $data,
            'comentarios' => $comentarios
        ]);
    }

    // ----------------------------------------------------------------
    // aceptar — POST AJAX
    // ----------------------------------------------------------------

    public function aceptar(): void
    {

        $this->soloInvestigador($this->rol());

        global $conn;

        $Solicitudes = new Solicitud($conn);

        $id = intval($_POST['id_solicitud'] ?? 0);
        if (!$id) $this->json(['ok' => false, 'msg' => 'ID inválido.'], 422);

        if (!$Solicitudes->verificarPermiso($id, $this->idUsuario())) {
            $this->json(['ok' => false, 'msg' => 'Sin permiso.'], 403);
        }

        $ok = $Solicitudes->aceptar($id, $this->idUsuario());

        $this->json([
            'ok'  => $ok,
            'msg' => $ok ? 'Solicitud aceptada.' : 'Error al aceptar.'
        ]);
    }

    // ----------------------------------------------------------------
    // pedir correcciones
    // ----------------------------------------------------------------

    public function pedirCorrecciones(): void
    {
        $this->soloInvestigador($this->rol());
        global $conn;

        $Solicitudes = new Solicitud($conn);
        $id         = intval($_POST['id_solicitud'] ?? 0);
        $comentario = trim($_POST['comentario'] ?? '');

        if (!$id || $comentario === '') {
            $this->json(['ok' => false, 'msg' => 'Datos incompletos.'], 422);
        }

        if (!$Solicitudes->verificarPermiso($id, $this->idUsuario())) {
            $this->json(['ok' => false, 'msg' => 'Sin permiso.'], 403);
        }

        [$ruta, $nombre] = $this->procesarArchivo($id);

        $ok = $Solicitudes->pedirCorrecciones(
            $id,
            $this->idUsuario(),
            $comentario,
            $ruta,
            $nombre
        );

        $this->json([
            'ok'  => $ok,
            'msg' => $ok ? 'Correcciones solicitadas.' : 'Error.'
        ]);
    }

    // ----------------------------------------------------------------
    // rechazar
    // ----------------------------------------------------------------

    public function rechazar(): void
    {
        $this->soloInvestigador($this->rol());
        global $conn;

        $Solicitudes = new Solicitud($conn);
        $id         = intval($_POST['id_solicitud'] ?? 0);
        $comentario = trim($_POST['comentario'] ?? '');

        if (!$id || $comentario === '') {
            $this->json(['ok' => false, 'msg' => 'Motivo obligatorio.'], 422);
        }

        if (!$Solicitudes->verificarPermiso($id, $this->idUsuario())) {
            $this->json(['ok' => false, 'msg' => 'Sin permiso.'], 403);
        }

        [$ruta, $nombre] = $this->procesarArchivo($id);

        $ok = $Solicitudes->rechazar(
            $id,
            $this->idUsuario(),
            $comentario,
            $ruta,
            $nombre
        );

        $this->json([
            'ok'  => $ok,
            'msg' => $ok ? 'Rechazada.' : 'Error.'
        ]);
    }

    // ----------------------------------------------------------------
    // enviar correcciones (estudiante)
    // ----------------------------------------------------------------

    public function enviarCorrecciones(): void
    {
        if ($this->rol() !== 'estudiante') {
            $this->json(['ok' => false, 'msg' => 'Sin permiso.'], 403);
        }

        global $conn;

        $Solicitudes = new Solicitud($conn);

        $id         = intval($_POST['id_solicitud'] ?? 0);
        $comentario = trim($_POST['comentario'] ?? '');

        if (!$id) $this->json(['ok' => false, 'msg' => 'ID inválido.'], 422);

        [$ruta, $nombre] = $this->procesarArchivo($id);

        $ok = $Solicitudes->enviarCorrecciones(
            $id,
            $this->idUsuario(),
            $comentario,
            null,
            $ruta,
            $nombre
        );

        $this->json([
            'ok'  => $ok,
            'msg' => $ok ? 'Enviado.' : 'Error.'
        ]);
    }

    // ----------------------------------------------------------------
    // archivo
    // ----------------------------------------------------------------

    private function procesarArchivo(int $id): array
    {
        if (empty($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
            return [null, null];
        }

        $file = $_FILES['archivo'];
        $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, ['pdf', 'docx', 'png', 'jpg'])) return [null, null];
        if ($file['size'] > 8 * 1024 * 1024) return [null, null];

        $dir = __DIR__ . '/../publico/docs/solicitudes/';
        if (!is_dir($dir)) mkdir($dir, 0755, true);

        $nombre = 'sol_' . $id . '_' . date('YmdHis') . '.' . $ext;
        $ruta   = 'docs/solicitudes/' . $nombre;

        move_uploaded_file($file['tmp_name'], $dir . $nombre);

        return [$ruta, $file['name']];
    }

    // ----------------------------------------------------------------
    // detallePagina — vista completa 
    // ----------------------------------------------------------------
    public function detallePagina(int $id_solicitud, int $id_usuario, string $rol): array
    {
        $this->soloInvestigador($rol);

        global $conn;

        $Solicitudes = new Solicitud($conn);

        if (!$id_solicitud) {
            die('ID inválido.');
        }

        if (!$Solicitudes->verificarPermiso($id_solicitud, $id_usuario)) {
            die('Sin permiso.');
        }

        $Solicitudes->marcarEnRevision($id_solicitud);

        $solicitud   = $Solicitudes->obtenerDetalle($id_solicitud);
        $comentarios = $Solicitudes->obtenerComentarios($id_solicitud);

        if (!$solicitud) {
            die('Solicitud no encontrada.');
        }

        // AQUÍ ESTÁ EL SEGUIMIENTO
        $etapas = [];

        require_once __DIR__ . '/../Modelos/solicitudes.php';

        $segModelo = new Solicitud($conn);

        $etapas = $segModelo->getEtapasPorProyecto(
            (int)$solicitud['id_proyectos'],
            (int)$solicitud['id_estudiante']
        );

        return [
            'solicitud'   => $solicitud,
            'comentarios' => $comentarios,
            'etapas'      => $etapas
        ];
    }
}
