<?php
// Controladores/plantilladocumentoControlador.php

require_once __DIR__ . '/../Modelos/plantilladocumento.php';
require_once __DIR__ . '/../publico/config/conexion.php';
require_once __DIR__ . '/BaseControlador.php';
include __DIR__ . '/../publico/incluido/_botones.php';

class plantilladocumentoControlador extends BaseControlador
{

    // 
    //  HELPER PRIVADO
    // 

    private function modelo(): plantilladocumento
    {
        global $conn;
        return new plantilladocumento($conn);
    }

    private function vacio(): array
    {
        return [
            'plantillas' => [],
            'paginacion' => [
                'total'         => 0,
                'por_pagina'    => 6,
                'pagina'        => 1,
                'total_paginas' => 1,
            ],
        ];
    }

    // 
    //  LISTADOS / FILTROS
    // 

    public function index(string $rol, ?string $buscar = null): array
    {
        if (!$this->esSupervisor($rol)) return $this->vacio();
        return $this->obtenerPorFiltro($rol, 2, $buscar);
    }

    public function Total(string $rol, ?string $buscar = null): array
    {
        return $this->obtenerPorFiltro($rol, 2, $buscar);
    }

    public function Activo(string $rol, ?string $buscar = null): array
    {
        return $this->obtenerPorFiltro($rol, 1, $buscar);
    }

    public function Desactivado(string $rol, ?string $buscar = null): array
    {
        return $this->obtenerPorFiltro($rol, 0, $buscar);
    }

    private function obtenerPorFiltro(string $rol, int $estado, ?string $buscar): array
    {
        if (!$this->esSupervisor($rol)) return $this->vacio();
        try {
            return $this->modelo()->obtenerTablaFiltro($this->limpiar($buscar), $estado);
        } catch (Throwable $e) {
            error_log($e->getMessage());
            return $this->vacio();
        }
    }

    public function indexCrear(string $rol): array
    {
        if (!$this->esSupervisor($rol)) return [];
        try {
            return $this->modelo()->obtenerTipos_documentos();
        } catch (Throwable $e) {
            error_log($e->getMessage());
            return [];
        }
    }

    // 
    //  PRESENTACIÓN / UI
    // 

    public function encabezadosPrincipal(string $rol): array
    {
        if (!$this->esSupervisor($rol)) return [];
        return ['Plantilla', 'Versión', 'Creación', 'Modificación', 'Archivo', 'Estado', 'Acciones'];
    }

    public function opciones(): array
    {
        return [
            'Total'       => "Total",
            'Activo'      => "Activos",
            'Desactivado' => "Desactivados",
        ];
    }

    public function numerofiltro(string $action): int
    {
        return match ($action) {
            'Activo'      => 1,
            'Desactivado' => 0,
            default       => 2,
        };
    }

    public function EstiloEstado(string $estado): string
    {
        return match (strtolower(trim($estado))) {
            'activo'      => 'success',
            'desactivado' => 'danger',
            default       => 'info',
        };
    }

    public function EstiloTimeLine(string $evento): string
    {
        return match (trim($evento)) {
            'CREACION'      => 'success',
            'NUEVA_VERSION' => 'primary',
            'REACTIVACION'  => 'info',
            'DESACTIVACION' => 'danger',
            default         => 'secondary',
        };
    }

    // 
    //  BOTONES DE TABLA
    // 

    private function botonHistorial(int $id_tipo_documento): string
    {
        include __DIR__ . '../../publico/incluido/_iconos.php';

        return Botones::botonIcono(
            'historial.php?id_tipo_documento=' . $id_tipo_documento,
            'primary',
            $iconos['tabla']['ver'],
            'Ver historial de plantilla'
        );
    }

    private function botonDesactivar(int $id_plantilla, int $id_tipo_documento): string
    {
        include __DIR__ . '../../publico/incluido/_iconos.php';

        return Botones::botonConfirmacion(
            'index.php?id_plantilla=' . $id_plantilla
                . '&id_tipo_documento=' . $id_tipo_documento
                . '&action=desactivar',
            'danger',
            $iconos['tabla']['solicitar_cierre'],
            'Desactivar plantilla',
            '¿Desactivar esta plantilla?'
        );
    }

    private function botonReactivar(int $id_plantilla, int $id_tipo_documento): string
    {
        include __DIR__ . '../../publico/incluido/_iconos.php';

        return Botones::botonIcono(
            'index.php?id_plantilla=' . $id_plantilla
                . '&id_tipo_documento=' . $id_tipo_documento
                . '&action=reactivar',
            'success',
            $iconos['tabla']['reactivar'],
            'Reactivar plantilla'
        );
    }


    public function botonesAccionPrincipal(
        int    $id_plantilla,
        string $rol,
        string $estado,
        int    $id_tipo_documento
    ): string {
        if (!$this->esSupervisor($rol)) return '';

        $botones = $this->botonHistorial($id_tipo_documento);

        if ($estado === 'Activo') {
            $botones .= $this->botonDesactivar($id_plantilla, $id_tipo_documento);
        } elseif ($estado === 'Desactivado') {
            $botones .= $this->botonReactivar($id_plantilla, $id_tipo_documento);
        }

        return '<div class="d-flex gap-1">' . $botones . '</div>';
    }

    // 
    //  DATOS PARA CREAR (Ajax)
    // 

    public function obtenerPlantillas(int $id_tipo_documento): array
    {
        try {
            $modelo     = $this->modelo();
            $info    = $modelo->obtenerInfoTipos($id_tipo_documento);
            $version = ($info['ultima_version'] !== null)
                ? (int)$info['ultima_version'] + 1
                : 1;

            return [
                'nombre'  => $info['nombre'] . ' v' . $version,
                'version' => $version,
            ];
        } catch (Throwable $e) {
            error_log($e->getMessage());
            return [];
        }
    }

    // 
    //  SUBCARPETA SEGÚN TIPO DE DOCUMENTO
    // 

    public function carpetaPorTipo(string $nombre, string $categoria = ''): string
    {
        $nombre_lc    = strtolower(trim($nombre));
        $categoria_lc = strtolower(trim($categoria));

        $mapaNombre = [
            'carta compromiso'     => 'carta',
            'carta de terminacion' => 'carta',
            'reporte final'        => 'reporte',
            'informe'              => 'informe',
        ];

        foreach ($mapaNombre as $clave => $carpeta) {
            if (str_contains($nombre_lc, $clave)) {
                return $carpeta;
            }
        }

        if ($categoria_lc === 'final')   return 'final';
        if ($categoria_lc === 'proceso') return 'proceso';

        return 'general';
    }

    // 
    //  REGISTRAR — POST → redirige con msg
    // 

    public function registrar(
        string $rol,
        string $nombre,
        string $nombre_archivo,
        string $ruta,
        string $extension,
        string $tipo_mime,
        int    $tamano_bytes,
        int    $id_tipo_documento,
        int    $id_usuario
    ): void {
        global $conn;
        try {
            $this->validarAcceso($rol, ['supervisor']);

            $conn->begin_transaction();
            $modelo = $this->modelo();
            $modelo->bloquearTabla($id_tipo_documento);

            $info    = $modelo->obtenerInfoTipos($id_tipo_documento);
            $modelo->desactivarPorTipo($id_tipo_documento);
            $version = $modelo->obtenerSiguienteVersion($id_tipo_documento);

            $id_documento = $modelo->registrarDocumento(
                nombre: $nombre,
                nombre_archivo: $nombre_archivo,
                ruta: $ruta,
                tipo_mime: $tipo_mime,
                extension: $extension,
                tamano_bytes: $tamano_bytes,
                tipo: 'plantilla',
                visibilidad: 'privado',
                id_usuario: $id_usuario,
                version: $version
            );

            $id_plantilla = $modelo->registrar($id_tipo_documento, $nombre, $version, $id_documento);

            $accion      = $info['ultima_version'] === null ? 'CREACION' : 'NUEVA_VERSION';
            $descripcion = $this->generarDescripcion($accion, $version, $info['nombre']);
            $modelo->registrarHistorial($id_plantilla, $id_usuario, $accion, $descripcion);

            $conn->commit();
            $this->redirigir('exito_crear');
        } catch (mysqli_sql_exception $e) {
            $conn->rollback();
            error_log($e->getMessage());
            $msg = ($e->getCode() === 1062) ? 'error_duplicado' : 'error_crear';
            $this->redirigir($msg);
        } catch (Exception $e) {
            // Sólo hacer rollback si la transacción fue iniciada correctamente
            if (isset($conn) && $conn->errno === 0) {
                $conn->rollback();
            }
            error_log($e->getMessage());
            $msg = match ($e->getMessage()) {
                'accion_no_permitida' => 'accion_no_permitida',
                default               => 'error_crear',
            };
            $this->redirigir($msg);
        }
    }

    // 
    //  DESACTIVAR — GET → redirige con msg
    // 

    public function desactivar(string $rol, int $id_plantilla, int $id_usuario): void
    {
        global $conn;
        try {
            $this->validarMetodo('GET');
            $this->validarAcceso($rol, ['supervisor']);

            if ($id_plantilla <= 0) {
                throw new Exception('error_desactivar');
            }

            $conn->begin_transaction();
            $modelo      = $this->modelo();
            $registro = $modelo->obtenerPorId($id_plantilla);

            if (!$registro) {
                throw new Exception("Plantilla no existe (ID: {$id_plantilla})");
            }
            if ((int)$registro['activo'] === 0) {
                throw new Exception("La plantilla ya está desactivada");
            }

            $datos = $modelo->obtenerInfoPlantilla($id_plantilla);
            $modelo->bloquearTabla($datos['id_tipo_documento']);

            $filas = $modelo->desactivarPorTipo($datos['id_tipo_documento']);
            if ($filas === 0) {
                throw new Exception("No había plantillas activas para desactivar");
            }

            $descripcion = $this->generarDescripcion('DESACTIVACION', null, $datos['nombre']);
            $modelo->registrarHistorial($id_plantilla, $id_usuario, 'DESACTIVACION', $descripcion);

            $conn->commit();
            $this->redirigir('exito_desactivar');
        } catch (Exception $e) {
            if (isset($conn) && $conn->errno === 0) {
                $conn->rollback();
            }
            error_log($e->getMessage());
            $msg = match ($e->getMessage()) {
                'accion_no_permitida' => 'accion_no_permitida',
                'error_desactivar'    => 'error_desactivar',
                default               => 'error_desactivar',
            };
            $this->redirigir($msg);
        }
    }

    // 
    //  REACTIVAR — GET → redirige con msg
    // 

    public function reactivar(string $rol, int $id_plantilla, int $id_usuario): void
    {
        global $conn;
        try {
            $this->validarMetodo('GET');
            $this->validarAcceso($rol, ['supervisor']);

            $conn->begin_transaction();
            $modelo      = $this->modelo();
            $registro = $modelo->obtenerPorId($id_plantilla);

            if (!$registro) {
                throw new Exception("Plantilla no existe (ID: {$id_plantilla})");
            }
            if ((int)$registro['activo'] === 1) {
                throw new Exception("La plantilla ya está activa");
            }

            $modelo->activarVersion($id_plantilla);

            $datos       = $modelo->obtenerInfoPlantilla($id_plantilla);
            $descripcion = $this->generarDescripcion('REACTIVACION', $datos['version'], null);
            $modelo->registrarHistorial($id_plantilla, $id_usuario, 'REACTIVACION', $descripcion);

            $conn->commit();
            $this->redirigir('exito_reactivar');
        } catch (Exception $e) {
            if (isset($conn) && $conn->errno === 0) {
                $conn->rollback();
            }
            error_log($e->getMessage());
            $msg = match ($e->getMessage()) {
                'accion_no_permitida' => 'accion_no_permitida',
                'error_reactivar'     => 'error_reactivar',
                default               => 'error_reactivar',
            };
            $this->redirigir($msg);
        }
    }

    // 
    //  LÍNEA DE TIEMPO
    // 

    public function info_linea_tiempo(int $id_tipo_documento)
    {
        try {
            $pagina = max(1, (int)($_GET['pagina'] ?? 1));
            return $this->modelo()->linea_tiempo($id_tipo_documento, $pagina);
        } catch (Throwable $e) {
            error_log($e->getMessage());
            $this->redirigir('error_cargar');
        }
    }

    // 
    //  HELPER: DESCRIPCIONES DE HISTORIAL
    // 

    private function generarDescripcion(string $accion, ?int $version, ?string $nombre): string
    {
        return match ($accion) {
            'CREACION'      => "Se creó la plantilla versión {$version} para el tipo de documento \"{$nombre}\".",
            'NUEVA_VERSION' => "Se registró una nueva versión {$version}. La versión anterior fue desactivada automáticamente.",
            'REACTIVACION'  => "Se reactivó la versión {$version} y se desactivaron las demás versiones.",
            'DESACTIVACION' => "Se desactivaron las plantillas activas del tipo de documento \"{$nombre}\".",
            default         => "Acción desconocida: {$accion}.",
        };
    }
}
