<?php
// Controladores/tareasControlador.php

require_once __DIR__ . '/../Modelos/tareas.php';
require_once __DIR__ . '/../publico/config/conexion.php';
require_once __DIR__ . '/BaseControlador.php';

class TareaControlador extends BaseControlador
{
    // 
    // ACCIONES PRINCIPALES (index)
    // 

    public function index_Principal(int $id_proyecto, int $id_usuario, string $rol): array
    {
        global $conn;
        try {
            if (!in_array($rol, ['investigador', 'estudiante', 'supervisor', 'profesor'], true)) return [];
            $tareas = new Tarea($conn);
            $tareas->actualizarTareasVencidos();
            $tareas->actualizarTareasConcluidas();
            $resultado = $tareas->obtenerTareas($id_proyecto, $id_usuario, $rol);
            if (!$resultado) {
                $this->redirigir('sin_permiso_tarea', '/ITSFCP-PROYECTOS/Vistas/Proyectos/index.php');
            }
            return $resultado;
        } catch (\Exception $e) {
            error_log('TareaControlador::index_Principal() — ' . $e->getMessage());
            return [];
        }
    }

    public function index_Lista(int $id_tarea, string $rol): array
    {
        global $conn;
        $id_usuario = (int)$_SESSION['id_usuario'];
        try {
            if (!in_array($rol, ['investigador', 'estudiante', 'supervisor', 'profesor'], true)) return [];
            $tarea = new Tarea($conn);
            $tarea->actualizarTareasVencidos();
            $tarea->actualizarTareasConcluidas($id_tarea);
            return $tarea->obtenerTareasLista($id_tarea, $rol, $id_usuario);
        } catch (\Exception $e) {
            error_log('TareaControlador::index_Lista() — ' . $e->getMessage());
            return [];
        }
    }

    // 
    // CONTENIDO DE LA TAREA (textarea, archivo, comentario)
    // 

    public function tareas(string $descripcion, string $rol, array $datos): string
    {
        $soloLectura = ($rol !== 'estudiante');

        $map = [
            'Resumen'                               => 'Resumen',
            'Introducción'                          => 'Introduccion',
            'Introduccion'                          => 'Introduccion',
            'Planteamiento del Problema'            => 'PlanteamientoProblema',
            'Planteamiento del problema'            => 'PlanteamientoProblema',
            'Justificación'                         => 'Justificacion',
            'Justificacion'                         => 'Justificacion',
            'Objetivos'                             => 'Objetivos',
            'Marco Teórico'                         => 'MarcoTeorico',
            'Marco teórico y/o de referencia'       => 'MarcoTeorico',
            'MarcoTeorico'                          => 'MarcoTeorico',
            'Metodología'                           => 'Metodologia',
            'Metodologia'                           => 'Metodologia',
            'Metas, productos esperados e impacto'  => 'MetasProductosImpacto',
            'Metas, productos esperados e impactos' => 'MetasProductosImpacto',
            'Cronograma y recursos'                 => 'Cronograma',
            'Cronograma'                            => 'Cronograma',
            'Referencias bibliograficas'            => 'Bibliografia',
            'Bibliografía'                          => 'Bibliografia',
            'Anexos'                                => 'Anexos',
            'Reporte Final'                         => 'ReporteFinal',
        ];

        $descripcionNorm = $map[$descripcion] ?? $descripcion;
        $campo  = "<h5 class='fw-semibold mb-2'>{$this->titulo($descripcionNorm)}</h5>";
        $valor  = $datos['contenido'] ?? '';
        $campo .= $this->textarea($descripcion, $descripcionNorm, $valor, $soloLectura);
        $campo .= $this->archivo($datos, $soloLectura);
        $campo .= $this->comentario($datos);
        return $campo;
    }

    private function textarea(string $label, string $name, string $value, bool $disabled = false, int $rows = 7): string
    {
        $dis = $disabled ? 'disabled' : '';
        return "
    <div class='mb-3'>
        <textarea class='form-control editor' name='contenido' rows='{$rows}' {$dis}>" . htmlspecialchars($value) . "</textarea>
    </div>";
    }

    private function archivo(array $datos, bool $disabled = false): string
    {
        $dis  = $disabled ? 'disabled' : '';
        $file = !empty($datos['archivo_nombre'])
            ? "<a href='descargar.php?id={$datos['id_tarea']}' class='d-block mb-2 small'>
                <svg xmlns='http://www.w3.org/2000/svg' width='14' height='14' fill='currentColor' class='bi bi-paperclip me-1' viewBox='0 0 16 16'>
                  <path d='M4.5 3a2.5 2.5 0 0 1 5 0v9a1.5 1.5 0 0 1-3 0V5a.5.5 0 0 1 1 0v7a.5.5 0 0 0 1 0V3a1.5 1.5 0 1 0-3 0v9a2.5 2.5 0 0 0 5 0V5a.5.5 0 0 1 1 0v7a3.5 3.5 0 1 1-7 0z'/>
                </svg>" . htmlspecialchars($datos['archivo_nombre']) . "</a>"
            : "<p class='text-muted small mb-2'>Sin archivo adjunto.</p>";

        $inputFile = !$disabled
            ? "<div class='mt-1'><label class='form-label text-muted small'>Adjuntar archivo:</label>
               <input type='file' class='form-control form-control-sm' name='archivo'></div>"
            : '';

        return "
        <div class='mb-3'>
            <label class='form-label text-muted small'>Archivo de guía:</label>
            {$file}
            {$inputFile}
        </div>";
    }

    private function comentario(array $datos): string
    {
        return "
    <div class='mb-3'>
        <label class='form-label text-muted small'>Comentarios:</label>
        <textarea class='form-control' name='comentarios' rows='3'>" . htmlspecialchars($datos['comentarios'] ?? '') . "</textarea>
    </div>";
    }

    private function titulo(string $desc): string
    {
        $titulos = [
            'Resumen'               => '1. Resumen / Abstract',
            'Introduccion'          => '2. Introducción',
            'PlanteamientoProblema' => '3. Planteamiento del Problema',
            'Justificacion'         => '4. Justificación',
            'Objetivos'             => '5. Objetivos',
            'MarcoTeorico'          => '6. Marco teórico y/o de referencia',
            'Metodologia'           => '7. Metodología',
            'MetasProductosImpacto' => '8. Metas, productos esperados e impacto',
            'Cronograma'            => '9. Cronograma',
            'Bibliografia'          => '10. Bibliografía',
            'Anexos'                => '11. Anexos',
            'ReporteFinal'          => '12. Reporte Final',
        ];
        return $titulos[$desc] ?? $desc;
    }

    private function numerofiltro(string $action): int
    {
        return match ($action) {
            'Total'      => 0,
            'Pendiente'  => 1,
            'Revisar'    => 2,
            'Corregir'   => 3,
            'SinActivar' => 4,
            'Aprobado'   => 5,
            'Vencido'    => 6,
            'Entregado'  => 7,
            'Borrador'   => 8,
            'Concluido'  => 9,
            default      => 0,
        };
    }

    // 
    // ENCABEZADOS
    // 

    public function encabezadosPrincipal(string $rol): array
    {
        return match ($rol) {
            'estudiante'                                      => ['Actividad', 'Estado', 'Guía', 'Fecha Entrega', 'Acciones'],
            'investigador', 'profesor', 'supervisor'          => ['Actividad', 'Entregas', 'Estado', 'Guía', 'Fecha Entrega', 'Acciones'],
            default => [],
        };
    }

    public function encabezadosLista(string $rol): array
    {
        return match ($rol) {
            'investigador', 'profesor', 'supervisor' => ['ID', 'Estudiante', 'Estado', 'Entrega', 'Acciones'],
            default => [],
        };
    }

    // 
    // BOTONES
    // 

    public function obtenerbotones(string $tipo, $id1 = null, $id2 = null, $id3 = null, $id4 = null, $estado = null): string
    {
        return match ($tipo) {
            'Ver Tarea' =>
            '<a href="tarea.php?id_asignacion=' . $id1 . '&tipo=' . $id2 . '&id_proyectos=' . $id3 . '&id_tarea=' . $id4 . '&estado=' . $estado . '"
                    class="btn btn-sm btn-primary" title="Ver tarea">
                    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" fill="currentColor" class="bi bi-eye" viewBox="0 0 16 16">
                      <path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8M1.173 8a13 13 0 0 1 1.66-2.043C4.12 4.668 5.88 3.5 8 3.5s3.879 1.168 5.168 2.457A13 13 0 0 1 14.828 8q-.086.13-.195.288c-.335.48-.83 1.12-1.465 1.755C11.879 11.332 10.119 12.5 8 12.5s-3.879-1.168-5.168-2.457A13 13 0 0 1 1.172 8z"/>
                      <path d="M8 5.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5M4.5 8a3.5 3.5 0 1 1 7 0 3.5 3.5 0 0 1-7 0"/>
                    </svg> Ver</a>',

            'Ver lista' =>
            '<a href="lista_tareas.php?id_tarea=' . $id1 . '&id_proyectos=' . $id2 . '"
                    class="btn btn-sm btn-secondary" title="Ver lista de estudiantes"
                    data-bs-toggle="tooltip" data-bs-placement="top"
                    data-bs-custom-class="custom-tooltip" data-bs-title="Ver lista de alumnos">
                    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" fill="currentColor" class="bi bi-people" viewBox="0 0 16 16">
                      <path d="M15 14s1 0 1-1-1-4-5-4-5 3-5 4 1 1 1 1zm-7.978-1L7 12.996c.001-.264.167-1.03.76-1.72C8.312 10.629 9.282 10 11 10c1.717 0 2.687.63 3.24 1.276.593.69.758 1.457.76 1.72l-.008.002-.014.002zM11 7a2 2 0 1 0 0-4 2 2 0 0 0 0 4m3-2a3 3 0 1 1-6 0 3 3 0 0 1 6 0M6.936 9.28a6 6 0 0 0-1.23-.247A7 7 0 0 0 5 9c-4 0-5 3-5 4q0 1 1 1h4.216A2.24 2.24 0 0 1 5 13c0-1.01.377-2.042 1.09-2.904.243-.294.526-.569.846-.816M4.92 10A5.5 5.5 0 0 0 4 13H1c0-.26.164-1.03.76-1.724.545-.636 1.492-1.256 3.16-1.275ZM1.5 5.5a3 3 0 1 1 6 0 3 3 0 0 1-6 0m3-2a2 2 0 1 0 0 4 2 2 0 0 0 0-4"/>
                    </svg></a>',

            'Editar Tarea' =>
            '<a href="editar.php?id_tarea=' . $id1 . '&id_proyectos=' . $id2 . '"
                    class="btn btn-sm btn-warning" title="Editar tarea"
                    data-bs-toggle="tooltip" data-bs-placement="top"
                    data-bs-custom-class="custom-tooltip" data-bs-title="Editar tarea">
                    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" fill="currentColor" class="bi bi-pencil" viewBox="0 0 16 16">
                      <path d="M12.146.146a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1 0 .708l-10 10a.5.5 0 0 1-.168.11l-5 2a.5.5 0 0 1-.65-.65l2-5a.5.5 0 0 1 .11-.168zM11.207 2.5 13.5 4.793 14.793 3.5 12.5 1.207zm1.586 3L10.5 3.207 4 9.707V10h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.5h.293zm-9.761 5.175-.106.106-1.528 3.821 3.821-1.528.106-.106A.5.5 0 0 1 5 12.5V12h-.5a.5.5 0 0 1-.5-.5V11h-.5a.5.5 0 0 1-.468-.325z"/>
                    </svg></a>',

            'Detalles' =>
            '<a href="detalles.php?id_tarea=' . $id1 . '&id_proyectos=' . $id2 . '"
                    class="btn btn-sm btn-primary" title="Ver detalles"
                    data-bs-toggle="tooltip" data-bs-placement="top"
                    data-bs-custom-class="custom-tooltip" data-bs-title="Ver detalles">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-eye-fill" viewBox="0 0 16 16">
                      <path d="M10.5 8a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0"/>
                      <path d="M0 8s3-5.5 8-5.5S16 8 16 8s-3 5.5-8 5.5S0 8 0 8m8 3.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7"/>
                    </svg></a>',

            default => '',
        };
    }

    public function obtenerbotonesTarea(string $tipo, $id1 = null, $id2 = null): string
    {
        return match ($tipo) {
            'Aprobar'            => '<button type="submit" name="tipo" value="Aprobado"  class="btn btn-success btn-sm">Aprobar</button>',
            'EnviarTarea'        => '<button type="submit" name="tipo" value="Revisar"   class="btn btn-primary btn-sm">Enviar tarea</button>',
            'ReenviarTarea'      => '<button type="submit" name="tipo" value="Revisar"   class="btn btn-primary btn-sm">Volver a enviar</button>',
            'Solicitar Corregir' => '<button type="submit" name="tipo" value="Corregir"  class="btn btn-warning btn-sm">Solicitar corrección</button>',
            'Guardar'            =>
            '<button type="submit" form="form-borrador" class="btn btn-outline-secondary btn-sm">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" class="bi bi-floppy me-1" viewBox="0 0 16 16">
                      <path d="M11 2H9v3h2z"/><path d="M1.5 0h11.586a1.5 1.5 0 0 1 1.06.44l1.415 1.414A1.5 1.5 0 0 1 16 2.914V14.5a1.5 1.5 0 0 1-1.5 1.5h-13A1.5 1.5 0 0 1 0 14.5v-13A1.5 1.5 0 0 1 1.5 0M1 1.5v13a.5.5 0 0 0 .5.5H2v-4.5A1.5 1.5 0 0 1 3.5 9h9a1.5 1.5 0 0 1 1.5 1.5V15h.5a.5.5 0 0 0 .5-.5V2.914a.5.5 0 0 0-.146-.353l-1.415-1.415A.5.5 0 0 0 13.086 1H13v4.5A1.5 1.5 0 0 1 11.5 7h-7A1.5 1.5 0 0 1 3 5.5V1H1.5a.5.5 0 0 0-.5.5m3 4a.5.5 0 0 0 .5.5h7a.5.5 0 0 0 .5-.5V1H4zm6 6.5v3h-4v-3h4a.5.5 0 0 1 0 0"/>
                    </svg> Guardar borrador</button>',
            'Activar' =>
            '<a href="editar.php?action=actualizarestado&id_tarea=' . $id1 . '&id_proyectos=' . $id2 . '&tipo=Pendiente"
                    class="btn btn-success btn-sm">Activar tarea</a>',
            default => '',
        };
    }

    /**
     * Botones de acción en la tabla principal (index.php).
     *
     *
     * @param int         $id           id_tarea (investigador/supervisor) o id_asignacion (estudiante)
     * @param string      $rol
     * @param string|null $estado       Estado de la tarea (texto)
     * @param mixed       $id_proyectos id del proyecto
     * @param mixed       $id_asignacion id_asignacion del estudiante (solo rol estudiante)
     * @param mixed       $tipo         tipo de tarea (solo rol estudiante)
     * @param mixed       $id_tarea     id_tarea real (solo rol estudiante)
     */
    public function botonesAccionPrincipal(
        $id,
        string $rol,
        ?string $estado    = null,
        $id_proyectos      = null,
        $id_asignacion     = null,
        $tipo              = null,
        $id_tarea          = null
    ): string {
        $boton = '';
        switch ($rol) {
            case 'estudiante':
                // Necesita: id_asignacion, tipo, id_proyectos, id_tarea, estado
                if ($estado !== '') {
                    $boton = $this->obtenerbotones(
                        'Ver Tarea',
                        $id_asignacion ?? $id,   // id_asignacion
                        $tipo,                   // tipo
                        $id_proyectos,           // id_proyectos
                        $id_tarea ?? $id,        // id_tarea
                        $estado
                    );
                }
                break;

            case 'investigador':
            case 'profesor':
                if (in_array($estado, ['Pendiente', 'Revisar', 'Corregir', 'Aprobado', 'Vencido', 'Sin activar'], true)) {
                    $boton  = $this->obtenerbotones('Ver lista',    $id, $id_proyectos);
                    $boton .= ' ' . $this->obtenerbotones('Editar Tarea', $id, $id_proyectos);
                } elseif ($estado === 'Concluido') {
                    $boton = $this->obtenerbotones('Ver lista', $id, $id_proyectos);
                }
                break;

            case 'supervisor':
                if (in_array($estado, ['Pendiente', 'Revisar', 'Corregir', 'Aprobado', 'Vencido', 'Sin activar', 'Concluido'], true)) {
                    $boton  = $this->obtenerbotones('Ver lista', $id, $id_proyectos);
                    $boton .= ' ' . $this->obtenerbotones('Detalles', $id, $id_proyectos);
                }
                break;
        }
        return $boton;
    }

    public function botonesAccionLista($id1, string $rol, ?string $estado = null, $id2 = null, $id3 = null, $id4 = null): string
    {
        switch ($rol) {
            case 'investigador':
            case 'profesor':
            case 'supervisor':
                if (in_array($estado, ['Revisar', 'Corregir', 'Aprobado', 'Vencido', 'Pendiente', 'Borrador'], true)) {
                    return $this->obtenerbotones('Ver Tarea', $id1, $id2, $id3, $id4, $estado);
                }
        }
        return '';
    }

    public function botonesAccionTarea($id_tarea, string $rol, string $estado, $id2 = null): string
    {
        $boton = '';
        switch ($rol) {
            case 'estudiante':
                if (in_array($estado, ['Pendiente', 'Borrador'], true)) {
                    $boton  = $this->obtenerbotonesTarea('EnviarTarea');
                    $boton .= ' ' . $this->obtenerbotonesTarea('Guardar');
                } elseif ($estado === 'Corregir') {
                    $boton  = $this->obtenerbotonesTarea('ReenviarTarea');
                    $boton .= ' ' . $this->obtenerbotonesTarea('Guardar');
                } elseif ($estado === 'Revisar') {
                    $boton = $this->obtenerbotonesTarea('Guardar');
                }
                break;
            case 'investigador':
            case 'profesor':
                if (in_array($estado, ['Revisar', 'Corregir', 'Concluido'], true)) {
                    $boton  = $this->obtenerbotonesTarea('Aprobar');
                    $boton .= ' ' . $this->obtenerbotonesTarea('Solicitar Corregir');
                } elseif ($estado === 'Sin activar') {
                    $boton = $this->obtenerbotonesTarea('Activar', $id_tarea, $id2);
                }
                break;
        }
        return $boton;
    }

    // 
    // NOTA CONTEXTUAL
    // 

    public function notaAccionTarea(string $rol, string $estado, string $tipo_tarea = ''): string
    {
        if ($rol === 'investigador' && $estado === 'Sin activar') {
            return $this->_nota(
                'advertencia',
                'Importante',
                'Al activar la tarea, se asignará automáticamente a <strong>todos los estudiantes activos</strong> del proyecto. Una vez activada, no es posible revertirla al estado <em>Sin activar</em>.'
            );
        }
        if ($rol === 'investigador' && in_array($estado, ['Revisar', 'Corregir'], true)) {
            return $this->_nota(
                'info',
                'Nota',
                'Puedes <strong>aprobar</strong> la entrega o solicitar <strong>corrección</strong> al estudiante. La decisión quedará registrada en el historial.'
            );
        }
        if ($estado === 'Concluido') {
            return $this->_nota(
                'exito',
                'Tarea concluida',
                'Todos los estudiantes activos tienen esta actividad <strong>aprobada</strong>. No admite más cambios de estado.'
            );
        }
        if ($rol === 'estudiante' && in_array($estado, ['Pendiente', 'Borrador'], true)) {
            return $this->_nota(
                'info',
                'Nota',
                'Puedes <strong>guardar un borrador</strong> y continuar después, o <strong>enviar tu tarea</strong> cuando esté lista para revisión.'
            );
        }
        if ($rol === 'estudiante' && $estado === 'Corregir') {
            return $this->_nota(
                'advertencia',
                'Corrección solicitada',
                'El investigador ha solicitado cambios en tu entrega. Revisa los comentarios del historial y vuelve a enviar.'
            );
        }
        if ($rol === 'estudiante' && $estado === 'Revisar') {
            return $this->_nota(
                'info',
                'En revisión',
                'Tu entrega está siendo revisada por el investigador. Puedes guardar cambios pero <strong>no reenviar</strong> hasta recibir retroalimentación.'
            );
        }
        if ($rol === 'estudiante' && $estado === 'Aprobado') {
            return $this->_nota(
                'exito',
                'Aprobado',
                'El investigador ha aprobado tu entrega. Esta actividad está <strong>finalizada</strong>.'
            );
        }
        if ($estado === 'Vencido') {
            return $this->_nota(
                'advertencia',
                'Tarea vencida',
                'La fecha de entrega ha pasado. Contacta al investigador si tienes dudas sobre esta actividad.'
            );
        }
        return '';
    }

    private function _nota(string $variante, string $etiqueta, string $mensaje): string
    {
        $clase = match ($variante) {
            'advertencia' => 'nota-tecnm nota-advertencia',
            'exito'       => 'nota-tecnm nota-exito',
            default       => 'nota-tecnm',
        };
        $icono = match ($variante) {
            'advertencia' => '
            <path d="M9.13 3.4L2.2 15.1A1 1 0 0 0 3.07 16.6h13.86a1 1 0 0 0 .87-1.5L10.87 3.4a1 1 0 0 0-1.74 0z"
                  stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/>
            <path d="M10 8.5v3.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
            <circle cx="10" cy="13.8" r="0.75" fill="currentColor"/>',
            'exito' => '
            <circle cx="10" cy="10" r="8.5" stroke="currentColor" stroke-width="1.4"/>
            <path d="M6.5 10.2l2.3 2.3 4.7-4.7" stroke="currentColor"
                  stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>',
            default => '
            <circle cx="10" cy="10" r="8.5" stroke="currentColor" stroke-width="1.4"/>
            <path d="M10 9v5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
            <circle cx="10" cy="6.5" r="0.8" fill="currentColor"/>',
        };
        return "
    <div class='{$clase}'>
        <svg class='nota-icon' viewBox='0 0 20 20' fill='none' xmlns='http://www.w3.org/2000/svg' aria-hidden='true'>
            {$icono}
        </svg>
        <div>
            <p class='nota-label'>{$etiqueta}</p>
            <p class='nota-texto'>{$mensaje}</p>
        </div>
    </div>";
    }

    // 
    // EDITAR TAREA GENERAL (investigador — plantilla)
    // 

    public function editarTarea(array $datos, string $rol, $id_proyectos = null): void
    {
        global $conn;
        $conn->begin_transaction();
        try {
            $this->validarMetodo('POST');
            $this->validarAcceso($rol, ['investigador', 'profesor']);

            $id_tarea      = (int)$datos['id_tarea'];
            $id_proyectos  = (int)($datos['id_proyectos'] ?? ($_GET['id_proyectos'] ?? 0));
            $descripcion   = $datos['descripcion']   ?? '';
            $instrucciones = $datos['instrucciones'] ?? '';
            $fecha_entrega = $datos['fecha_entrega'] ?? '';
            $id_usuario    = (int)($datos['id_usuario'] ?? $_SESSION['id_usuario'] ?? 0);

            $tarea = new Tarea($conn);
            $tarea->actualizarTareasVencidos();

            $id_documento_recurso = null;
            if (!empty($_FILES['archivo']['tmp_name']) && $_FILES['archivo']['error'] === UPLOAD_ERR_OK) {
                $id_documento_recurso = $this->_subirArchivoRecurso($_FILES['archivo'], $id_usuario, $id_proyectos, $tarea);
            }

            $tarea->editarTareaGeneral($id_tarea, $descripcion, $instrucciones, $fecha_entrega, $id_documento_recurso, $id_usuario);
            $conn->commit();

            $this->redirigir('exito_editar', 'editar.php', "&id_tarea={$id_tarea}&id_proyectos={$id_proyectos}");
        } catch (\Exception $e) {
            $conn->rollback();
            error_log('TareaControlador::editarTarea() — ' . $e->getMessage());
            $msg = ($e->getMessage() === 'accion_no_permitida') ? 'accion_no_permitida' : 'error_editar';
            $this->redirigir($msg, 'editar.php');
        }
    }

    // 
    // EDITAR — enrutador estudiante / investigador
    // 

    public function editar(array $datos, string $rol, int $id_proyecto, int $id_asignacion, int $id_usuario): void
    {
        global $conn;
        $this->validarMetodo('POST');
        $this->validarAcceso($rol, ['estudiante', 'investigador', 'profesor']);

        $tipo = trim($datos['tipo'] ?? '');

        if (!in_array($tipo, ['Revisar', 'Corregir', 'Aprobado'], true)) {
            error_log("editar(): tipo inválido: '{$tipo}'");
            $this->redirigir(
                'error_tipo_invalido',
                'tarea.php',
                "&id_tarea={$datos['id_tarea']}&id_proyectos={$id_proyecto}&id_asignacion={$id_asignacion}"
            );
        }

        $conn->begin_transaction();
        try {
            if ($rol === 'estudiante') {
                $this->_guardarContenidoEstudiante($datos, $id_proyecto, $id_asignacion);
            }

            $this->actualizarestado(
                (int)$datos['id_tarea'],
                $rol,
                $tipo,
                $id_proyecto,
                $id_asignacion,
                $id_usuario,
                $datos['comentarios'] ?? ''
            );

            $conn->commit();

            $this->redirigir(
                'exito_estado',
                'tarea.php',
                "&id_tarea={$datos['id_tarea']}&id_proyectos={$id_proyecto}&id_asignacion={$id_asignacion}"
            );
        } catch (\Exception $e) {
            $conn->rollback();
            error_log('TareaControlador::editar() — ' . $e->getMessage());
            $this->redirigir(
                'error_estado',
                'tarea.php',
                "&id_tarea={$datos['id_tarea']}&id_proyectos={$id_proyecto}&id_asignacion={$id_asignacion}"
            );
        }
    }

    // 
    // GUARDAR CONTENIDO DEL ESTUDIANTE (privado)
    // 

    private function _guardarContenidoEstudiante(array $datos, $id_proyecto, $id_asignacion): ?int
    {
        $id_asignacion = (int)($datos['id_asignacion'] ?? 0);
        $id_tarea      = (int)($datos['id_tarea']      ?? 0);
        if ($id_asignacion <= 0 || $id_tarea <= 0) throw new \Exception('Datos incompletos en _guardarContenidoEstudiante.');

        require_once __DIR__ . './../vendor/autoload.php';
        $config   = \HTMLPurifier_Config::createDefault();
        $purifier = new \HTMLPurifier($config);

        $contenido   = $purifier->purify($datos['contenido']   ?? '');
        $comentarios = $purifier->purify($datos['comentarios'] ?? '');
        $id_usuario  = (int)($_SESSION['id_usuario'] ?? 0);

        global $conn;
        $tarea = new Tarea($conn);
        $tarea->actualizarTareasVencidos();

        $id_documento_entrega = null;
        if (!empty($_FILES['archivo']['tmp_name']) && $_FILES['archivo']['error'] === UPLOAD_ERR_OK) {
            $id_documento_entrega = $this->_subirArchivoEntrega($_FILES['archivo'], $id_usuario, $id_proyecto, $datos, $tarea);
        }

        $tarea->editarTareaEstudiante($id_asignacion, $id_tarea, $contenido, $comentarios, $id_documento_entrega);
        return $id_documento_entrega;
    }

    // 
    // GUARDAR BORRADOR (estudiante)
    // 

    public function guardar_borrador(
        int    $id_tarea,
        int    $id_proyectos,
        int    $id_asignacion,
        int    $id_usuarios,
        string $contenido,
        string $comentarios = ''
    ): void {
        global $conn;
        $conn->begin_transaction();
        try {
            $tarea = new Tarea($conn);
            $tarea->actualizarTareasVencidos();

            require_once __DIR__ . './../vendor/autoload.php';
            $config        = \HTMLPurifier_Config::createDefault();
            $purifier      = new \HTMLPurifier($config);
            $contenido_p   = $purifier->purify($contenido);
            $comentarios_p = $purifier->purify($comentarios);

            $id_documento_entrega = null;
            if (!empty($_FILES['archivo']['tmp_name']) && $_FILES['archivo']['error'] === UPLOAD_ERR_OK) {
                $base = "/ITSFCP-PROYECTOS/storage/entregas/alumno_{$id_usuarios}/proyecto_{$id_proyectos}/actividad/";
                $id_documento_entrega = $this->_subirArchivoGenerico($_FILES['archivo'], $base, $id_usuarios, $id_proyectos, $tarea, 'entrega');
            }

            $tarea->guardar_borrador($id_tarea, $id_asignacion, $id_usuarios, $contenido_p, $comentarios_p, $id_documento_entrega);
            $conn->commit();

            $this->redirigir(
                'exito_borrador',
                'tarea.php',
                "&id_tarea={$id_tarea}&id_proyectos={$id_proyectos}&id_asignacion={$id_asignacion}"
            );
        } catch (\Exception $e) {
            $conn->rollback();
            error_log('TareaControlador::guardar_borrador() — ' . $e->getMessage());
            $this->redirigir(
                'error_borrador',
                'tarea.php',
                "&id_tarea={$id_tarea}&id_proyectos={$id_proyectos}&id_asignacion={$id_asignacion}"
            );
        }
    }

    // 
    // ACTUALIZAR ESTADO
    // 

    public function actualizarestado(
        int    $id_tarea,
        string $rol,
        string $tipo,
        int    $id_proyectos,
        int    $id_asignacion = 0,
        int    $id_usuarios   = 0,
        string $comentarios   = ''
    ): void {
        global $conn;

        if (!$id_usuarios) {
            $id_usuarios = (int)($_SESSION['id_usuario'] ?? 0);
        }

        $tipo = trim($tipo);

        if (!in_array($tipo, ['Revisar', 'Corregir', 'Aprobado', 'Pendiente'], true)) {
            error_log("actualizarestado(): tipo no válido: '{$tipo}'");
            $this->redirigir(
                'error_tipo_invalido',
                'tarea.php',
                "&id_tarea={$id_tarea}&id_proyectos={$id_proyectos}&id_asignacion={$id_asignacion}"
            );
        }

        $tarea = new Tarea($conn);
        $tarea->actualizarTareasVencidos();

        $numeroEstado = $this->numerofiltro($tipo);

        require_once __DIR__ . '/../vendor/autoload.php';
        static $purifier = null;
        if ($purifier === null) {
            $config   = \HTMLPurifier_Config::createDefault();
            $purifier = new \HTMLPurifier($config);
        }
        $comentarios_p = $purifier->purify($comentarios);

        $tarea->actualizarestado($id_tarea, $numeroEstado, $id_proyectos, $id_asignacion, $id_usuarios, $comentarios_p);
        $tarea->actualizarTareasConcluidas($id_tarea);

        if (in_array($tipo, ['Aprobado', 'Corregir', 'Revisar'], true)) {
            $this->redirigir(
                'exito_estado',
                'tarea.php',
                "&id_tarea={$id_tarea}&id_proyectos={$id_proyectos}&id_asignacion={$id_asignacion}"
            );
        } else {
            $this->redirigir('exito_estado', 'editar.php', "&id_tarea={$id_tarea}&id_proyectos={$id_proyectos}");
        }
    }

    // 
    // LÍNEA DE TIEMPO
    // 

    public function info_linea_tiempo(int $id_asignacion): array
    {
        global $conn;
        $vacio = ['datos' => [], 'paginacion' => ['total' => 0, 'por_pagina' => 6, 'pagina' => 1, 'total_paginas' => 1]];
        try {
            $pagina = max(1, (int)($_GET['pagina'] ?? 1));
            $tarea  = new Tarea($conn);
            $tarea->actualizarTareasVencidos();
            return $id_asignacion ? $tarea->linea_tiempo_tarea($id_asignacion, $pagina) : $vacio;
        } catch (\Exception $e) {
            error_log('TareaControlador::info_linea_tiempo() — ' . $e->getMessage());
            return $vacio;
        }
    }

    // 
    // MOSTRAR TAREA GENERAL (editar / detalles)
    // 

    public function mostrarEditarTarea(int $id_tarea, string $rol, int $id_usuario): array
    {
        global $conn;
        try {
            if (!in_array($rol, ['investigador', 'profesor', 'supervisor'], true)) return [];
            $tareas = new Tarea($conn);
            $tareas->actualizarTareasVencidos();
            return $tareas->obtenerTareaGeneral($id_tarea, $rol, $id_usuario) ?? [];
        } catch (\Exception $e) {
            error_log('TareaControlador::mostrarEditarTarea() — ' . $e->getMessage());
            return [];
        }
    }

    public function mostrarTarea(int $id_asignacion, string $rol, int $id_usuario, int $id_proyecto): array
    {
        global $conn;
        $defaults = [
            'id_tarea'           => null,
            'id_asignacion'      => $id_asignacion,
            'id_proyectos'       => '',
            'descripcion'        => '',
            'instrucciones'      => '',
            'estado'             => '',
            'id_estadoT'         => 0,
            'tipo_tarea'         => '',
            'contenido'          => '',
            'comentarios'        => '',
            'fecha_modificacion' => null,
            'guia_nombre'        => null,
            'guia_ruta'          => null,
        ];
        try {
            if (!in_array($rol, ['investigador', 'estudiante', 'supervisor', 'profesor'], true)) return $defaults;
            $tareas = new Tarea($conn);
            $tareas->actualizarTareasVencidos();
            $datos = $tareas->obtenerTareaAlumno($id_asignacion, $id_usuario, $id_proyecto);
            return array_merge($defaults, is_array($datos) && !empty($datos) ? $datos : []);
        } catch (\Exception $e) {
            error_log('TareaControlador::mostrarTarea() — ' . $e->getMessage());
            return $defaults;
        }
    }

    // 
    // LISTAR TAREAS ESTUDIANTE
    // 

    public function listarTareasEstudiante(int $id_usuarios, int $id_proyectos): array
    {
        global $conn;
        try {
            return (new Tarea($conn))->obtenerTareasEstudiante($id_usuarios, $id_proyectos);
        } catch (\Exception $e) {
            error_log('TareaControlador::listarTareasEstudiante() — ' . $e->getMessage());
            return [];
        }
    }

    // 
    // ESTILOS DE ESTADO
    // 

    public function estiloEstado($estado): string
    {
        return match ((int)$estado) {
            1       => 'primary',
            2       => 'warning',
            3       => 'danger',
            5       => 'success',
            6       => 'secondary',
            7, 9    => 'info',
            8       => 'light',
            default => 'light',
        };
    }

    public function EstiloEstadoLista(string $estado): string
    {
        return match ($estado) {
            'Pendiente'   => 'primary',
            'Revisar'     => 'warning',
            'Corregir'    => 'danger',
            'Vencido'     => 'dark',
            'Aprobado'    => 'success',
            'Sin activar' => 'secondary',
            'Borrador'    => 'light',
            'Concluido'   => 'info',
            default       => 'light',
        };
    }

    // 
    // OBTENER EDICIONES RECIENTES
    // 

    public function obtenerEdicionesRecientes(int $id_tarea, int $limite = 5): array
    {
        global $conn;
        return (new Tarea($conn))->obtenerEdicionesRecientes($id_tarea, $limite);
    }

    // 
    // HELPERS PRIVADOS: SUBIDA DE ARCHIVOS
    // 

    private function _subirArchivoRecurso(array $file, int $id_usuario, int $id_proyecto, Tarea $tarea): int
    {
        $extension   = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $nombreFinal = uniqid() . '_' . basename($file['name']);
        $base        = "/ITSFCP-PROYECTOS/storage/recursos/investigador_{$id_usuario}/proyecto_{$id_proyecto}/";
        return $this->_moverYRegistrar($file, $base, $nombreFinal, $extension, $id_usuario, $id_proyecto, 'recurso', $tarea);
    }

    private function _subirArchivoEntrega(array $file, int $id_usuario, int $id_proyecto, array $datos, Tarea $tarea): int
    {
        $extension   = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $nombreFinal = uniqid() . '_' . basename($file['name']);
        $etapa_num   = (int)($datos['etapa'] ?? 0);
        $subcarpeta  = $etapa_num > 0 ? "actividad_{$etapa_num}" : 'actividad';
        $base        = "/ITSFCP-PROYECTOS/storage/entregas/alumno_{$id_usuario}/proyecto_{$id_proyecto}/{$subcarpeta}/";
        return $this->_moverYRegistrar($file, $base, $nombreFinal, $extension, $id_usuario, $id_proyecto, 'entrega', $tarea, $etapa_num ?: null);
    }

    private function _subirArchivoGenerico(array $file, string $base, int $id_usuario, int $id_proyecto, Tarea $tarea, string $tipo): int
    {
        $extension   = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $nombreFinal = uniqid() . '_' . basename($file['name']);
        return $this->_moverYRegistrar($file, $base, $nombreFinal, $extension, $id_usuario, $id_proyecto, $tipo, $tarea);
    }

    private function _moverYRegistrar(array $file, string $base, string $nombreFinal, string $extension, int $id_usuario, int $id_proyecto, string $tipo, Tarea $tarea, ?int $etapa = null): int
    {
        $rutaFisica = $_SERVER['DOCUMENT_ROOT'] . $base . $nombreFinal;

        if (!is_dir(dirname($rutaFisica))) {
            mkdir(dirname($rutaFisica), 0755, true);
        }
        if (!move_uploaded_file($file['tmp_name'], $rutaFisica)) {
            throw new \Exception("Error al guardar archivo '{$nombreFinal}'.");
        }

        return $tarea->registrarDocumento(
            nombre: basename($file['name']),
            nombre_archivo: $nombreFinal,
            ruta: $base . $nombreFinal,
            tipo_mime: $file['type'],
            extension: $extension,
            tamano_bytes: $file['size'],
            tipo: $tipo,
            visibilidad: 'privado',
            id_usuario: $id_usuario,
            id_proyecto: $id_proyecto,
            etapa: $etapa
        );
    }
}
