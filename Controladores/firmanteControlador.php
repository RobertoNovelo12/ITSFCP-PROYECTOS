<?php

require_once __DIR__ . '/../Modelos/firmante.php';
require_once __DIR__ . '/../publico/config/conexion.php';

/**
 *  CLASE: firmanteControlador
 * Controlador principal del módulo de Firmantes.
 * Gestiona las operaciones CRUD, validaciones de acceso,
 * carga/descarga de imágenes de firma y generación de vistas.
 *
 * FLUJO DE FIRMA DIGITAL:
 *   Subida  → validar PNG → redimensionar (300×100 px) → encriptar → guardar .enc
 *   Descarga → leer .enc → desencriptar → enviar bytes PNG al navegador
 *   Vista   → desencriptar → base64 → <img src="data:image/png;base64,...">
 */
class firmanteControlador
{
    // SECCIÓN: Utilidades internas

    /**
     * Verifica si el usuario tiene rol de supervisor.
     *
     * @param string $rol
     * @return bool
     */
    private function esSupervisor($rol): bool
    {
        return isset($rol) && $rol === 'supervisor';
    }

    /**
     * Sanitiza datos de entrada para prevenir XSS.
     *
     * @param string|null $dato
     * @return string|null
     */
    private function limpiar($dato): ?string
    {
        return isset($dato)
            ? htmlspecialchars(trim($dato), ENT_QUOTES, 'UTF-8')
            : null;
    }

    // SECCIÓN: Listado de firmantes

    /**
     * Obtiene el listado de firmantes con filtro y paginación.
     *
     * @param string      $rol
     * @param string|null $buscar
     * @return array
     */
    public function index($rol, $buscar = null): array
    {
        global $conn;

        try {
            if (!$this->esSupervisor($rol)) {
                return [];
            }

            $buscar = $this->limpiar($buscar);

            $Firmante = new Firmante($conn);

            return $Firmante->obtenerTablaFiltro($buscar, 2);
        } catch (Throwable $e) {
            error_log("Error en index() [firmanteControlador]: " . $e->getMessage());
            return [];
        }
    }

    // Obtener datos por ID

    /**
     * Obtiene los datos de un firmante para el formulario de edición.
     *
     * @param string $rol
     * @param int    $id_firmantes
     * @return array
     */
    public function indexEditar($rol, $id_firmantes): array
    {
        global $conn;

        try {
            if (!$this->esSupervisor($rol)) {
                return [];
            }

            $id = filter_var($id_firmantes, FILTER_VALIDATE_INT);

            if (!$id) {
                return [];
            }

            $Firmante = new Firmante($conn);

            return $Firmante->obtenerEditar($id);
        } catch (Throwable $e) {
            error_log("Error en indexEditar() [firmanteControlador]: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtiene los datos completos de un firmante para la vista de detalles.
     *
     * @param string $rol
     * @param int    $id_firmantes
     * @return array
     */
    public function indexDetalles($rol, $id_firmantes): array
    {
        global $conn;

        try {
            if (!$this->esSupervisor($rol)) {
                return [];
            }

            $id = filter_var($id_firmantes, FILTER_VALIDATE_INT);

            if (!$id) {
                return [];
            }

            $Firmante = new Firmante($conn);

            return $Firmante->obtenerDetalles($id);
        } catch (Throwable $e) {
            error_log("Error en indexDetalles() [firmanteControlador]: " . $e->getMessage());
            return [];
        }
    }

    // Desactivar firmante

    /**
     * Desactiva lógicamente un firmante (soft delete, estado = 0).
     * Implementa transacción y control de concurrencia.
     *
     * @param string $rol
     * @param int    $id_firmantes
     * @throws Exception
     */
    public function eliminar($rol, $id_firmantes)
    {
        if (!$this->esSupervisor($rol)) {
            throw new Exception("No tienes permiso para desactivar firmantes.");
        }
        if (!$id_firmantes) {
            throw new Exception("ID de firmante inválido.");
        }

        global $conn;
        $conn->begin_transaction();

        try {
            $Firmante = new Firmante($conn);
            $Firmante->obtenerPorId((int)$id_firmantes); // Verificar existencia

            $filas = $Firmante->eliminar_firmante((int)$id_firmantes);

            if ($filas < 0) {
                throw new Exception("Error al desactivar el firmante.");
            }

            $conn->commit();
            header("Location: tabla.php?mensaje=1");
            exit;
        } catch (Throwable $e) {
            if ($conn->errno === 0) {
                $conn->rollback();
            }

            error_log("Error en eliminar() [firmanteControlador]: " . $e->getMessage());
            header("Location: tabla.php?error=10");
            exit;
        }
    }

    // Encabezados y opciones de tabla

    /**
     * Retorna los encabezados de la tabla principal de firmantes.
     *
     * @param string $rol
     * @return array
     */
    public function encabezadosPrincipal($rol): array
    {
        if (!$this->esSupervisor($rol)) {
            return [];
        }

        return [
            'Nombre',
            'Cargo',
            'Firma Digital',
            'Estado',
            'Acciones'
        ];
    }

    /**
     * Genera las opciones del selector de filtro con conteo de registros.
     *
     * @param string $rol
     * @param array  $filtros
     * @return array
     */
    public function opciones($rol, $filtros): array
    {
        if (!$this->esSupervisor($rol) || empty($filtros) || !isset($filtros[0])) {
            return [];
        }

        $data = $filtros[0];

        return [
            'Total'       => "Total ("       . ($data['Total']       ?? 0) . " en total)",
            'Activo'      => "Activos ("     . ($data['Activo']      ?? 0) . " en total)",
            'Desactivado' => "Desactivados (" . ($data['Desactivado'] ?? 0) . " en total)"
        ];
    }

    /**
     * Convierte la opción de filtro seleccionada a su número interno.
     *
     * @param string $action
     * @return int
     */
    public function numerofiltro($action): int
    {
        return match ($action) {
            'Total'       => 2,
            'Activo'      => 1,
            'Desactivado' => 0,
            default       => 2
        };
    }

    /**
     * Obtiene los datos para el panel de filtros (conteos).
     *
     * @param string $rol
     * @return array
     */
    public function filtros($rol): array
    {
        global $conn;

        try {
            if (!$this->esSupervisor($rol)) {
                return [];
            }

            $Firmante = new Firmante($conn);

            return $Firmante->obtenerDatosFiltro($rol);
        } catch (Throwable $e) {
            error_log("Error en filtros() [firmanteControlador]: " . $e->getMessage());
            return [];
        }
    }

    // Filtros de listado

    /**
     * Método interno base para filtrar firmantes por estado.
     *
     * @param string      $rol
     * @param int         $tipoFiltro
     * @param string|null $buscar
     * @return array
     */
    private function obtenerPorFiltro($rol, int $tipoFiltro, $buscar = null): array
    {
        global $conn;

        try {
            if (!$this->esSupervisor($rol)) {
                return [];
            }

            $buscar   = $this->limpiar($buscar);
            $Firmante = new Firmante($conn);

            return $Firmante->obtenerTablaFiltro($buscar, $tipoFiltro);
        } catch (Throwable $e) {
            error_log("Error en obtenerPorFiltro() [firmanteControlador]: " . $e->getMessage());
            return [];
        }
    }

    /** Obtiene todos los firmantes (activos + desactivados). */
    public function Total($rol, $buscar = null): array
    {
        return $this->obtenerPorFiltro($rol, 2, $buscar);
    }

    /** Obtiene solo firmantes activos. */
    public function Activo($rol, $buscar = null): array
    {
        return $this->obtenerPorFiltro($rol, 1, $buscar);
    }

    /** Obtiene solo firmantes desactivados. */
    public function Desactivado($rol, $buscar = null): array
    {
        return $this->obtenerPorFiltro($rol, 0, $buscar);
    }

    // Estilos visuales

    /**
     * Retorna la clase Bootstrap de badge según el estado del firmante.
     *
     * @param string $estado
     * @return string  'success' | 'danger' | 'info'
     */
    public function EstiloEstadoLista($estado): string
    {
        $estado = strtolower(trim($estado));

        return match ($estado) {
            'activo'      => "success",
            'desactivado' => "danger",
            default       => "info"
        };
    }

    // Botones de acción

    /**
     * Genera el HTML de un botón de acción específico para la tabla principal.
     *
     * @param string   $tipo
     * @param int|null $id1
     * @return string  HTML del botón
     */
    private function obtenerbotones($tipo, $id1 = null)
    {
        $boton = "";
        switch ($tipo) {
            case 'Editar Firmante':
                $boton = '<a href="editar.php?id_firmantes=' . $id1 . '" type="button" class="btn btn-warning"
                    data-bs-toggle="tooltip" data-bs-placement="top"
                    data-bs-custom-class="custom-tooltip" data-bs-title="Editar firmante">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-pencil-square" viewBox="0 0 16 16">
                        <path d="M15.502 1.94a.5.5 0 0 1 0 .706L14.459 3.69l-2-2L13.502.646a.5.5 0 0 1 .707 0l1.293 1.293zm-1.75 2.456-2-2L4.939 9.21a.5.5 0 0 0-.121.196l-.805 2.414a.25.25 0 0 0 .316.316l2.414-.805a.5.5 0 0 0 .196-.12l6.813-6.814z"/>
                        <path fill-rule="evenodd" d="M1 13.5A1.5 1.5 0 0 0 2.5 15h11a1.5 1.5 0 0 0 1.5-1.5v-6a.5.5 0 0 0-1 0v6a.5.5 0 0 1-.5.5h-11a.5.5 0 0 1-.5-.5v-11a.5.5 0 0 1 .5-.5H9a.5.5 0 0 0 0-1H2.5A1.5 1.5 0 0 0 1 2.5z"/>
                    </svg></a>';
                break;
            case 'Detalles':
                $boton = '<a href="detalles.php?id_firmantes=' . $id1 . '" type="button" class="btn btn-info"
                    data-bs-toggle="tooltip" data-bs-placement="top"
                    data-bs-custom-class="custom-tooltip" data-bs-title="Ver detalles del firmante">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-eye-fill" style="padding:0px;margin:auto;" viewBox="0 0 16 16">
                        <path d="M10.5 8a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0"/>
                        <path d="M0 8s3-5.5 8-5.5S16 8 16 8s-3 5.5-8 5.5S0 8 0 8m8 3.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7"/>
                    </svg></a>';
                break;
            case 'Desactivar':
                $boton = '<a href="tabla.php?id_firmantes=' . $id1 . '&action=desactivar_firmante" type="button" class="btn btn-danger"
                    data-bs-toggle="tooltip" data-bs-placement="top"
                    data-bs-custom-class="custom-tooltip" data-bs-title="Desactivar firmante">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-x-circle-fill" viewBox="0 0 16 16">
                        <path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0M5.354 4.646a.5.5 0 1 0-.708.708L7.293 8l-2.647 2.646a.5.5 0 0 0 .708.708L8 8.707l2.646 2.647a.5.5 0 0 0 .708-.708L8.707 8l2.647-2.646a.5.5 0 0 0-.708-.708L8 7.293z"/>
                    </svg></a>';
                break;
            default:
                break;
        }
        return $boton;
    }

    /**
     * Genera los botones de acción para una fila de la tabla principal.
     * Los botones disponibles dependen del estado del firmante.
     *
     * @param int    $id
     * @param string $rol
     * @param string $estado
     * @return string  HTML de botones
     */
    public function botonesAccionPrincipal($id, $rol, $estado = null)
    {
        if (!$this->esSupervisor($rol)) return "";

        $boton = "";

        if (in_array($estado, ["Activo"])) {
            $boton .= $this->obtenerbotones("Editar Firmante", $id);
            $boton .= $this->obtenerbotones("Detalles", $id);
            $boton .= $this->obtenerbotones("Desactivar", $id);
        } elseif ($estado === "Desactivado") {
            $boton .= $this->obtenerbotones("Editar Firmante", $id);
            $boton .= $this->obtenerbotones("Detalles", $id);
        }

        return $boton;
    }

    /**
     * Genera el HTML de un botón de acción para el formulario de edición.
     *
     * @param string $tipo  'Desactivar' | 'Reactivar' | 'Guardar'
     * @return string
     */
    public function obtenerbotonesEditar($tipo)
    {
        $boton = "";
        switch ($tipo) {
            case 'Desactivar':
                $boton = '<button type="submit" name="action" value="Desactivar" class="btn btn-danger">Desactivar</button>';
                break;
            case 'Reactivar':
                $boton = '<button type="submit" name="action" value="Reactivar" class="btn btn-warning">Reactivar</button>';
                break;
            case 'Guardar':
                $boton = '<button type="submit" name="action" value="Guardar" class="btn btn-guardar">Guardar cambios</button>';
                break;
            default:
                break;
        }
        return $boton;
    }

    /**
     * Genera el conjunto de botones para el formulario de edición
     * según el rol del usuario y el estado actual del firmante.
     *
     * @param string $rol
     * @param string $estado
     * @return string  HTML de botones
     */
    public function botonesAccionEditar($rol, $estado = null)
    {
        $boton = "";

        switch ($rol) {
            case 'supervisor':
                if (in_array($estado, ["Activo"])) {
                    $boton  = $this->obtenerbotonesEditar("Desactivar");
                    $boton .= $this->obtenerbotonesEditar("Guardar");
                } elseif (in_array($estado, ["Desactivado"])) {
                    $boton  = $this->obtenerbotonesEditar("Reactivar");
                    $boton .= $this->obtenerbotonesEditar("Guardar");
                }
                break;
            default:
                break;
        }

        return $boton;
    }

    // Operaciones CRUD completas

    /**
     * Registra un nuevo firmante con su imagen de firma digital.
     *
     * FLUJO:
     *   1. Validar acceso y datos recibidos.
     *   2. Redimensionar imagen PNG a 300×100 px.
     *   3. Encriptar y guardar como .enc en publico/img/firmas/.
     *   4. Insertar en BD dentro de transacción.
     *   5. Redirigir según resultado.
     *
     * @param string $rol
     * @param int    $id_instituto
     * @param string $nombre
     * @param string $cargo
     * @param array  $archivoFirma  Elemento de $_FILES['firma_digital']
     */
    public function registrarFirmante($rol, $id_instituto, $nombre, $cargo, $archivoFirma)
    {
        if (!$this->esSupervisor($rol)) return "";

        global $conn;

        $rutaRedim    = null;
        $nombreArchivo = null;

        $conn->begin_transaction();
        try {
            $Firmante = new Firmante($conn);

            // Bloqueo de concurrencia para evitar duplicados simultáneos
            $Firmante->bloquear_tabla();

            // Validar duplicado activo
            $verificacion = $Firmante->verificarFirmante($nombre, $cargo, (int)$id_instituto);

            if ($verificacion['activo'] > 0) {
                throw new Exception("Ya existe un firmante activo con ese nombre y cargo.");
            }

            // Procesar imagen de firma (redimensionar 300×100 px y encriptar)
            if (empty($archivoFirma['tmp_name'])) {
                throw new Exception("Debe subir una imagen de firma digital.");
            }

            $rutaRedim = $Firmante->redimensionarFirma($archivoFirma['tmp_name']);
            $nombreArchivo = Firmante::generarNombreFirma();
            $nombreEncriptado = $Firmante->encriptarYGuardarFirma($rutaRedim, $nombreArchivo);

            // Insertar en base de datos
            $id_nuevo = $Firmante->registrarFirmante(
                (int)$id_instituto,
                $nombre,
                $cargo,
                $nombreEncriptado
            );

            if (!$id_nuevo) {
                header("Location: tabla.php?error=1");
                exit;
            }

            $conn->commit();
            header("Location: tabla.php?mensaje=1");
            exit;
        } catch (mysqli_sql_exception $e) {
            $conn->rollback();

            // Eliminar archivo .enc si se creó antes del fallo en BD
            if ($nombreArchivo) {
                (new Firmante($conn))->eliminarArchiveFirma($nombreArchivo . '.enc');
            }

            if ($e->getCode() == 1062) {
                header("Location: tabla.php?error=duplicado");
            } else {
                header("Location: tabla.php?error=2");
            }
            exit;
        } catch (Exception $e) {
            $conn->rollback();

            if ($nombreArchivo) {
                (new Firmante($conn))->eliminarArchiveFirma($nombreArchivo . '.enc');
            }

            error_log("Error en registrarFirmante(): " . $e->getMessage());
            header("Location: crear.php?error=" . urlencode($e->getMessage()));
            exit;
        } finally {
            // Limpiar archivo temporal redimensionado si existe
            if ($rutaRedim && file_exists($rutaRedim)) {
                unlink($rutaRedim);
            }
        }
    }

    /**
     * Edita los datos de un firmante existente.
     * Si se sube una nueva imagen, reemplaza la anterior (elimina el .enc viejo).
     *
     * FLUJO:
     *   1. Validar acceso y duplicados (excluyendo el propio ID).
     *   2. Si hay nueva imagen: redimensionar → encriptar → guardar .enc nuevo.
     *   3. Actualizar BD dentro de transacción.
     *   4. Si se subió nueva imagen: eliminar el .enc anterior.
     *
     * @param string $rol
     * @param int    $id_firmantes
     * @param int    $id_instituto
     * @param string $nombre
     * @param string $cargo
     * @param array  $archivoFirma  Elemento de $_FILES['firma_digital'] (puede estar vacío)
     * @param string $firmaActual   Nombre del archivo .enc actual en BD
     */
    public function editarFirmante($rol, $id_firmantes, $id_instituto, $nombre, $cargo, $archivoFirma, $firmaActual)
    {
        if (!$this->esSupervisor($rol)) return "";

        global $conn;

        $rutaRedim         = null;
        $nombreEncriptado  = null;

        $conn->begin_transaction();
        try {
            $Firmante = new Firmante($conn);

            // Verificar duplicado excluyendo el propio registro
            $verificacion = $Firmante->verificarFirmanteExcluyendo(
                (int)$id_firmantes,
                $nombre,
                $cargo,
                (int)$id_instituto
            );

            if ($verificacion['activo'] > 0 || $verificacion['desactivado'] > 0) {
                throw new Exception("Ya existe otro firmante con ese nombre y cargo en el instituto.");
            }

            // Procesar nueva imagen de firma si se subió
            $hayNuevaFirma = !empty($archivoFirma['tmp_name']);

            if ($hayNuevaFirma) {
                $rutaRedim = $Firmante->redimensionarFirma($archivoFirma['tmp_name']);
                $nombreNuevo = Firmante::generarNombreFirma();
                $nombreEncriptado = $Firmante->encriptarYGuardarFirma($rutaRedim, $nombreNuevo);
            }

            // Actualizar en BD
            $Firmante->editarFirmante(
                (int)$id_firmantes,
                (int)$id_instituto,
                $nombre,
                $cargo,
                $hayNuevaFirma ? $nombreEncriptado : null
            );

            $conn->commit();

            // Eliminar firma anterior si se reemplazó correctamente
            if ($hayNuevaFirma && !empty($firmaActual)) {
                $Firmante->eliminarArchiveFirma($firmaActual);
            }

            header("Location: tabla.php?mensaje=1");
            exit;
        } catch (mysqli_sql_exception $e) {
            $conn->rollback();

            // Eliminar .enc nuevo si falló la BD
            if ($nombreEncriptado) {
                (new Firmante($conn))->eliminarArchiveFirma($nombreEncriptado);
            }

            if ($e->getCode() == 1062) {
                header("Location: tabla.php?error=duplicado");
            } else {
                header("Location: tabla.php?error=2");
            }
            exit;
        } catch (Exception $e) {
            $conn->rollback();

            if ($nombreEncriptado) {
                (new Firmante($conn))->eliminarArchiveFirma($nombreEncriptado);
            }

            error_log("Error en editarFirmante(): " . $e->getMessage());
            header("Location: editar.php?id_firmantes=" . (int)$id_firmantes . "&error=" . urlencode($e->getMessage()));
            exit;
        } finally {
            if ($rutaRedim && file_exists($rutaRedim)) {
                unlink($rutaRedim);
            }
        }
    }

    /**
     * Reactiva un firmante previamente desactivado.
     *
     * @param string $rol
     * @param int    $id_firmantes
     */
    public function reactivar($rol, $id_firmantes)
    {
        if (!$this->esSupervisor($rol)) return "";

        global $conn;

        $conn->begin_transaction();
        try {
            $Firmante = new Firmante($conn);
            $Firmante->bloquear_tabla();
            $Firmante->obtenerPorId((int)$id_firmantes, true);
            $Firmante->reactivar((int)$id_firmantes);

            $conn->commit();
            header("Location: tabla.php?mensaje=1");
            exit;
        } catch (mysqli_sql_exception $e) {
            $conn->rollback();

            if ($e->getCode() == 1062) {
                header("Location: tabla.php?error=duplicado");
            } else {
                header("Location: tabla.php?error=2");
            }
            exit;
        }
    }

    // Imagen de firma — descarga y visualización

    /**
     * Descarga la imagen de firma desencriptada como archivo PNG.
     * Envía headers de Content-Disposition: attachment para forzar descarga.
     *
     * @param string $rol
     * @param int    $id_firmantes
     */
    public function descargarFirma($rol, $id_firmantes)
    {
        if (!$this->esSupervisor($rol)) {
            header("Location: tabla.php?error=permiso");
            exit;
        }

        global $conn;

        try {
            $Firmante = new Firmante($conn);
            $datos    = $Firmante->obtenerDetalles((int)$id_firmantes);

            if (empty($datos['firma_digital'])) {
                header("Location: tabla.php?error=sin_firma");
                exit;
            }

            $bytesPng = $Firmante->desencriptarFirma($datos['firma_digital']);

            // Construir nombre de descarga amigable
            $nombreDescarga = 'firma_' . preg_replace('/[^a-zA-Z0-9_]/', '_', $datos['nombre']) . '.png';

            // Enviar imagen como descarga
            header('Content-Type: image/png');
            header('Content-Disposition: attachment; filename="' . $nombreDescarga . '"');
            header('Content-Length: ' . strlen($bytesPng));
            header('Cache-Control: no-cache, no-store, must-revalidate');
            echo $bytesPng;
            exit;
        } catch (Throwable $e) {
            error_log("Error en descargarFirma(): " . $e->getMessage());
            header("Location: tabla.php?error=descarga");
            exit;
        }
    }

    /**
     * Obtiene la imagen de firma desencriptada en formato base64
     * para incrustarla directamente en un <img> de la vista.
     *
     * @param string|null $firma_digital  Nombre del archivo .enc
     * @return string|null                Data URI (data:image/png;base64,...) o null si no existe
     */
    public function obtenerFirmaBase64(?string $firma_digital): ?string
    {
        if (empty($firma_digital)) return null;

        global $conn;

        try {
            $Firmante = new Firmante($conn);
            $bytesPng = $Firmante->desencriptarFirma($firma_digital);
            return 'data:image/png;base64,' . base64_encode($bytesPng);
        } catch (Throwable $e) {
            error_log("Error en obtenerFirmaBase64(): " . $e->getMessage());
            return null;
        }
    }

    // Validaciones públicas

    /**
     * Verifica si existe un firmante con el mismo nombre y cargo en el instituto.
     *
     * @param string $nombre
     * @param string $cargo
     * @param int    $id_instituto
     * @return array  ['activo' => int, 'desactivado' => int]
     */
    public function verificarFirmante($nombre, $cargo, $id_instituto): array
    {
        global $conn;

        try {
            if (empty($nombre) || empty($cargo)) {
                return ["activo" => 0, "desactivado" => 0];
            }

            $Firmante = new Firmante($conn);
            return $Firmante->verificarFirmante($nombre, $cargo, (int)$id_instituto);
        } catch (Throwable $e) {
            error_log("Error en verificarFirmante(): " . $e->getMessage());
            return ["activo" => 0, "desactivado" => 0];
        }
    }

    /**
     * Verifica duplicados excluyendo el propio firmante (para edición).
     *
     * @param int    $id_firmantes
     * @param string $nombre
     * @param string $cargo
     * @param int    $id_instituto
     * @return array  ['activo' => int, 'desactivado' => int]
     */
    public function verificarFirmanteExcluyendo($id_firmantes, $nombre, $cargo, $id_instituto): array
    {
        global $conn;

        try {
            if (empty($nombre) || empty($cargo)) {
                return ["activo" => 0, "desactivado" => 0];
            }

            $Firmante = new Firmante($conn);
            return $Firmante->verificarFirmanteExcluyendo(
                (int)$id_firmantes,
                $nombre,
                $cargo,
                (int)$id_instituto
            );
        } catch (Throwable $e) {
            error_log("Error en verificarFirmanteExcluyendo(): " . $e->getMessage());
            return ["activo" => 0, "desactivado" => 0];
        }
    }
}
