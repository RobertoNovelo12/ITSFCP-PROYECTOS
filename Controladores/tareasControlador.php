<?php

require_once __DIR__ . '/../Modelos/tareas.php';
require_once __DIR__ . '/../publico/config/conexion.php';



class TareaControlador
{

    public function index_Principal($id_proyecto, $id_usuario, $rol)
    {
        global $conn;

        $tareas = new Tarea($conn);

        if ($rol == "investigador" || $rol == "estudiante" || $rol == "supervisor") {
            //Revisión de estados de tarea
            $tareas->actualizarTareasVencidos();
            $tarea = $tareas->obtenerTareas($id_proyecto, $id_usuario, $rol);
            return $tarea;
        } else {
            $tarea = []; // evita undefined variable
            return $tarea;
        }
    }
    public function index_Lista($id_tarea, $rol)
    {
        global $conn;

        $tarea = new Tarea($conn);

        if ($rol == "investigador" || $rol == "estudiante" || $rol == "supervisor") {
            //Revisión de estados de tarea
            $tarea->actualizarTareasVencidos();
            $tarea = $tarea->obtenerTareasLista($id_tarea, $rol);
            return $tarea;
        } else {
            $tarea = []; // evita undefined variable
            return $tarea;
        }
    }

    public function tareas($descripcion, $rol, $datos)
    {
        switch ($rol) {
            case 'estudiante':
                if ($descripcion == "hidden") {
                    $campo = '<input type="hidden" name="action" value="editarTarea">
    <input type="hidden" name="id_tareas" value="<?= '. $datos['id_tarea'] .' ?>">';
                } else if ($descripcion == "Resumen") {
                    $campo = '<h4>1. Resumen / Abstract</h4>';

                    $campo .= '<div class="mb-3">
        <label>Resumen / Abstract:</label>
        <textarea class="form-control" name="Resumen" rows="4" required>' . (($datos['contenido']['Resumen'] ?? '') ?? '') . '</textarea>
    </div>';

                    $campo .= '<div class="mb-3">
        <label>Archivo actual:</label>
        <?php if (' . $datos['archivo_nombre'] . '): ?>
            <a href="descargar.php?id='. $datos['id_tarea'] .'">
                Descargar archivo (' . $datos['archivo_nombre'] . ')
            </a>
        <?php else: ?>
            <p>No hay archivo cargado.</p>
        <?php endif; ?>

        <label class="mt-2">Subir archivo nuevo:</label>
        <input type="file" name="archivo" class="form-control">
    </div>';

                    $campo .= ' <div class="mb-3">
        <label>Comentarios:</label>
        <textarea class="form-control" name="Comentarios" rows="3" disabled>' . ($datos['comentarios'] ?? '') . '</textarea>
    </div>';
                } else if ($descripcion == "Introducción") {
                    $campo = '<h4>2. Introducción</h4>';

                    $campo .= '<div class="mb-3">
        <label>Introducción:</label>
        <textarea class="form-control" name="Introducción" rows="4" required>' . ($datos['contenido']['Introduccion'] ?? '') . '</textarea>
    </div>';

                    $campo .= '<div class="mb-3">
        <label>Archivo actual:</label>
        <?php if (' . $datos['archivo_nombre'] . '): ?>
            <a href="descargar.php?id='. $datos['id_tarea'] .'">
                Descargar archivo (' . $datos['archivo_nombre'] . ')
            </a>
        <?php else: ?>
            <p>No hay archivo cargado.</p>
        <?php endif; ?>

        <label class="mt-2">Subir archivo nuevo:</label>
        <input type="file" name="archivo" class="form-control">
    </div>';

                    $campo .= '<div class="mb-3">
        <label>Comentarios:</label>
        <textarea class="form-control" name="Comentarios" rows="3" disabled>' . ($datos['comentarios'] ?? '') . '</textarea>
    </div>';
                } else if ($descripcion == "PlanteamientoProblema") {

                    $campo = '<h4>3. Planteamiento del Problema</h4>';

                    $campo .= '<div class="mb-3">
        <label>Planteamiento del Problema:</label>
        <textarea class="form-control" name="PlanteamientoProblema" rows="4" required>' . ($datos['contenido']['PlanteamientoProblema'] ?? '') . '</textarea>
    </div>';

                    $campo .= '<div class="mb-3">
        <label>Archivo actual:</label>
        <?php if (' . $datos['archivo_nombre'] . '): ?>
            <a href="descargar.php?id='. $datos['id_tarea'] .'">
                Descargar archivo (' . $datos['archivo_nombre'] . ')
            </a>
        <?php else: ?>
            <p>No hay archivo cargado.</p>
        <?php endif; ?>

        <label class="mt-2">Subir archivo nuevo:</label>
        <input type="file" name="archivo" class="form-control">
    </div>';

                    $campo .= '<div class="mb-3">
        <label>Comentarios recibidos:</label>
        <textarea class="form-control" name="Comentarios" rows="3" disabled>' . ($datos['comentarios'] ?? '') . '</textarea>
    </div>';
                } else if ($descripcion == "Justificacion") {

                    $campo = '<h4>4. Justificación</h4>';

                    $campo .= '<div class="mb-3">
        <label>Justificación:</label>
        <textarea class="form-control" name="Justificacion" rows="4" required>' . ($datos['contenido']['Justificacion'] ?? '') . '</textarea>
    </div>';

                    $campo .= ' <div class="mb-3">
        <label>Archivo actual:</label>
        <?php if (' . $datos['archivo_nombre'] . '): ?>
            <a href="descargar.php?id='. $datos['id_tarea'] .'">
                Descargar archivo (' . $datos['archivo_nombre'] . ')
            </a>
        <?php else: ?>
            <p>No hay archivo cargado.</p>
        <?php endif; ?>

        <label class="mt-2">Subir archivo nuevo:</label>
        <input type="file" name="archivo" class="form-control">
    </div>';

                    $campo .= ' <div class="mb-3">
        <label>Comentarios recibidos:</label>
        <textarea class="form-control" name="Comentarios" rows="3" disabled>' . ($datos['comentarios'] ?? '') . '</textarea>
    </div>';
                } else if ($descripcion == "Objetivos") {

                    $campo = '<h4>5. Objetivos</h4>';

                    // OBJETIVO GENERAL
                    $campo .= '<div class="mb-3">
        <label>Objetivo general:</label>
        <textarea class="form-control" name="ObjetivoGeneral" rows="4">' . ($datos['contenido']['ObjetivoGeneral'] ?? '') . '</textarea>
    </div>';

                    // OBJETIVOS ESPECÍFICOS
                    $campo .= '<div class="mb-3">
        <label>Objetivos específicos:</label>
        <textarea class="form-control" name="ObjetivosEspecificos" rows="4">' . ($datos['contenido']['ObjetivosEspecificos'] ?? '') . '</textarea>
    </div>';

                    // ARCHIVO
                    $campo .= '<div class="mb-3">
        <label>Archivo actual:</label>
        <?php if (' . $datos['archivo_nombre'] . '): ?>
            <a href="descargar.php?id='. $datos['id_tarea'] .'">
                Descargar archivo (' . $datos['archivo_nombre'] . ')
            </a>
        <?php else: ?>
            <p>No hay archivo cargado.</p>
        <?php endif; ?>

        <label class="mt-2">Subir archivo nuevo:</label>
        <input type="file" name="archivo" class="form-control">
    </div>';

                    // COMENTARIOS (solo lectura para estudiante)
                    $campo .= '
    <div class="mb-3">
        <label>Comentarios:</label>
        <textarea class="form-control" name="Comentarios" rows="3" disabled>' . ($datos['comentarios'] ?? '') . '</textarea>
    </div>';
                } else if ($descripcion == "MarcoTeorico") {

                    $campo = '<h4>6. Marco teórico y/o de referencia</h4>';

                    /* ===================== ANTECEDENTES ===================== */
                    $campo .= '
    <div class="mb-3">
        <label>Antecedentes:</label>
        <textarea class="form-control" name="Antecedentes" rows="4">' . ($datos['contenido']['Antecedentes'] ?? '') . '</textarea>
    </div>';

                    /* ===================== FUNDAMENTACIÓN TEÓRICA ===================== */
                    $campo .= '
    <div class="mb-3">
        <label>Fundamentación teórica:</label>
        <textarea class="form-control" name="Fundamentacion" rows="4">' . ($datos['contenido']['Fundamentacion'] ?? '') . '</textarea>
    </div>';

                    /* ===================== MARCO CONCEPTUAL ===================== */
                    $campo .= '
    <div class="mb-3">
        <label>Marco conceptual:</label>
        <textarea class="form-control" name="MarcoConceptual" rows="4">' . ($datos['contenido']['MarcoConceptual'] ?? '') . '</textarea>
    </div>';

                    /* ===================== ARCHIVO ===================== */
                    $campo .= '
    <div class="mb-3">
        <label>Archivo actual:</label>
        <?php if (' . $datos['archivo_nombre'] . '): ?>
            <a href="descargar.php?id='. $datos['id_tarea'] .'">
                Descargar archivo (' . $datos['archivo_nombre'] . ')
            </a>
        <?php else: ?>
            <p>No hay archivo cargado.</p>
        <?php endif; ?>

        <label class="mt-2">Subir archivo nuevo:</label>
        <input type="file" name="archivo" class="form-control">
    </div>';

                    /* ===================== COMENTARIOS SOLO LECTURA ===================== */
                    $campo .= '
    <div class="mb-3">
        <label>Comentarios:</label>
        <textarea class="form-control" name="Comentarios" rows="3" disabled>' . ($datos['comentarios'] ?? '') . '</textarea>
    </div>';
                } else if ($descripcion == "Metodologia") {

                    $campo = '<h4>7. Metodología</h4>';

                    /* === Tipo de estudio === */
                    $campo .= '
        <div class="mb-3">
            <label>6.1 Tipo de estudio:</label>
            <textarea class="form-control" name="TipoEstudio" rows="4">'
                        . ($datos['contenido']['MetodosTecnicas'] ?? '') .
                        '</textarea>
        </div>';

                    $campo .= '
        <div class="mb-3">
            <label>Archivo actual:</label>';
                    if (!empty($datos["archivo"])) {
                        $campo .= '<a href="descargar.php?id=' . $datos["id_tarea"] . '">
                Descargar archivo (' . $datos["archivo_nombre"] . ')
            </a>';
                    } else {
                        $campo .= '<p>No hay archivo cargado.</p>';
                    }
                    $campo .= '
            <label class="mt-2">Subir archivo nuevo:</label>
            <input type="file" name="archivo" class="form-control">
        </div>';


                    /* === Métodos y técnicas === */
                    $campo .= '
        <div class="mb-3">
            <label>6.2 Métodos y técnicas:</label>
            <textarea class="form-control" name="MetodosTecnicas" rows="4">'
                        . ($datos['contenido']['MetodosTecnicas'] ?? '') .
                        '</textarea>
        </div>';

                    $campo .= '
        <div class="mb-3">
            <label>Archivo actual:</label>';
                    if (!empty($datos["archivo_nombre"])) {
                        $campo .= '<a href="descargar.php?id=' . $datos["id_tarea"] . '">
                Descargar archivo (' . $datos["archivo_nombre"] . ')
            </a>';
                    } else {
                        $campo .= '<p>No hay archivo cargado.</p>';
                    }
                    $campo .= '
            <label class="mt-2">Subir archivo nuevo:</label>
            <input type="file" name="archivo" class="form-control">
        </div>';


                    /* === Población y muestra === */
                    $campo .= '
        <div class="mb-3">
            <label>6.3 Población y muestra:</label>
            <textarea class="form-control" name="PoblacionMuestra" rows="4">'
                        . ($datos["contenido"]["PoblacionMuestra"]) .
                        '</textarea>
        </div>';

                    $campo .= '
        <div class="mb-3">
            <label>Archivo actual:</label>';
                    if (!empty($datos["archivo_nombre"])) {
                        $campo .= '<a href="descargar.php?id=' . $datos["id_tarea"] . '">
                Descargar archivo (' . $datos["archivo_nombre"] . ')
            </a>';
                    } else {
                        $campo .= '<p>No hay archivo cargado.</p>';
                    }
                    $campo .= '
            <label class="mt-2">Subir archivo nuevo:</label>
            <input type="file" name="archivo" class="form-control">
        </div>';


                    /* === Instrumentos === */
                    $campo .= '
        <div class="mb-3">
            <label>6.4 Instrumentos:</label>
            <textarea class="form-control" name="Instrumentos" rows="4">'
                        . ($datos["contenido"]["Instrumentos"]) .
                        '</textarea>
        </div>';

                    $campo .= '
        <div class="mb-3">
            <label>Archivo actual:</label>';
                    if (!empty($datos["archivo_nombre"])) {
                        $campo .= '<a href="descargar.php?id=' . $datos["id_tarea"] . '">
                Descargar archivo (' . $datos["archivo_nombre"] . ')
            </a>';
                    } else {
                        $campo .= '<p>No hay archivo cargado.</p>';
                    }
                    $campo .= '
            <label class="mt-2">Subir archivo nuevo:</label>
            <input type="file" name="archivo" class="form-control">
        </div>';

                    /* === Comentarios del investigador visibles === */
                    $campo .= '
        <div class="mb-3">
            <label>Comentarios del investigador:</label>
            <textarea class="form-control" rows="3" disabled>'
                        . ($datos["contenido"]["Comentarios"]) .
                        '</textarea>
        </div>';
                } else if ($descripcion == "MetasProductosImpacto") {

                    $campo = '<h4>8. Metas, productos esperados e impacto</h4>';

                    /* --- 8.1 Metas --- */
                    $campo .= '
    <div class="mb-3">
        <label>8.1 Metas:</label>
        <textarea class="form-control" name="Metas" rows="4">' . ($datos['contenido']['Metas'] ?? '') . '</textarea>
    </div>';

                    /* --- 8.2 Productos esperados --- */
                    $campo .= '
    <div class="mb-3">
        <label>8.2 Productos esperados:</label>
        <textarea class="form-control" name="ProductosEsperados" rows="4">' . ($datos['contenido']['ProductosEsperados'] ?? '') . '</textarea>
    </div>';

                    /* --- 8.3 Impacto --- */
                    $campo .= '
    <div class="mb-3">
        <label>8.3 Impacto:</label>
        <textarea class="form-control" name="Impacto" rows="4">' . ($datos['contenido']['Impacto'] ?? '') . '</textarea>
    </div>';

                    /* --- ARCHIVO GENERAL --- */
                    $campo .= '
    <div class="mb-3">
        <label>Archivo actual:</label>
        <?php if (' . $datos['archivo_nombre'] . '): ?>
            <a href="descargar.php?id='. $datos['id_tarea'] .'">
                Descargar archivo (' . $datos['archivo_nombre'] . ')
            </a>
        <?php else: ?>
            <p>No hay archivo cargado.</p>
        <?php endif; ?>

        <label class="mt-2">Subir archivo nuevo:</label>
        <input type="file" name="archivo" class="form-control">
    </div>';

                    /* --- COMENTARIOS (solo los ve el estudiante) --- */
                    $campo .= '
    <div class="mb-3">
        <label>Comentarios del investigador/supervisor:</label>
        <textarea class="form-control" name="Comentarios" rows="3" disabled>' . ($datos['comentarios'] ?? '') . '</textarea>
    </div>';
                } else if ($descripcion == "Cronograma") {

                    $campo = '<h4>9. Cronograma</h4>';

                    /* --- CRONOGRAMA --- */
                    $campo .= '
    <div class="mb-3">
        <label>Cronograma:</label>
        <textarea class="form-control" name="Cronograma" rows="6">' . ($datos['contenido']['Cronograma'] ?? '') . '</textarea>
    </div>';

                    /* --- ARCHIVO GENERAL --- */
                    $campo .= '
    <div class="mb-3">
        <label>Archivo actual:</label>
        <?php if (' . $datos['archivo_nombre'] . '): ?>
            <a href="descargar.php?id='. $datos['id_tarea'] .'">
                Descargar archivo (' . $datos['archivo_nombre'] . ')
            </a>
        <?php else: ?>
            <p>No hay archivo cargado.</p>
        <?php endif; ?>

        <label class="mt-2">Subir archivo nuevo:</label>
        <input type="file" name="archivo" class="form-control">
    </div>';

                    /* --- COMENTARIOS PARA EL ESTUDIANTE --- */
                    $campo .= '
    <div class="mb-3">
        <label>Comentarios del investigador/supervisor:</label>
        <textarea class="form-control" name="Comentarios" rows="3" disabled>' . ($datos['comentarios'] ?? '') . '</textarea>
    </div>';
                } else if ($descripcion == "Bibliografia") {

                    $campo = '<h4>10. Bibliografía</h4>';

                    /* --- BIBLIOGRAFÍA --- */
                    $campo .= '
    <div class="mb-3">
        <label>Bibliografía:</label>
        <textarea class="form-control" name="Bibliografia" rows="6">' . ($datos['contenido']['Bibliografia'] ?? '') . '</textarea>
    </div>';

                    /* --- ARCHIVO GENERAL --- */
                    $campo .= '
    <div class="mb-3">

        <label>Archivo actual:</label>
        <?php if (' . $datos['archivo_nombre'] . '): ?>
            <a href="descargar.php?id='. $datos['id_tarea'] .'">
                Descargar archivo (' . $datos['archivo_nombre'] . ')
            </a>
        <?php else: ?>
            <p>No hay archivo cargado.</p>
        <?php endif; ?>

        <label class="mt-2">Subir archivo nuevo:</label>
        <input type="file" name="archivo" class="form-control">

    </div>';

                    /* --- COMENTARIOS (el estudiante los puede ver y editar) --- */
                    $campo .= '
    <div class="mb-3">
        <label>Comentarios del investigador/supervisor:</label>
        <textarea class="form-control" name="Comentarios" rows="3" disabled>' . ($datos['comentarios'] ?? '') . '</textarea>
    </div>';
                } else if ($descripcion == "Anexos") {

                    $campo = '<h4>11. Anexos (Opcional)</h4>';

                    /* --- 11.1 Instrumentos de recolección --- */
                    $campo .= '
    <div class="mb-3">
        <label>Instrumentos de recolección (Explicación):</label>
        <textarea class="form-control" name="Instrumentos" rows="4">' . ($datos['contenido']['Instrumentos'] ?? '') . '</textarea>
    </div>';

                    /* --- 11.2 Formularios de consentimiento --- */
                    $campo .= '
    <div class="mb-3">
        <label>Formularios de consentimiento (Explicación):</label>
        <textarea class="form-control" name="Consentimiento" rows="4">' . (($datos['contenido']['Consentimiento'] ?? '') ?? '') . '</textarea>
    </div>';

                    /* --- 11.3 Mapas, tablas, gráficos adicionales --- */
                    $campo .= '
    <div class="mb-3">
        <label>Mapas, tablas, gráficos adicionales (Explicación):</label>
        <textarea class="form-control" name="Mapas" rows="4">' . ($datos['contenido']['Mapas'] ?? '') . '</textarea>
    </div>';

                    /* --- 11.4 Otra documentación complementaria --- */
                    $campo .= '
    <div class="mb-3">
        <label>Otra documentación complementaria (Explicación):</label>
        <textarea class="form-control" name="Documentacion" rows="4">' . ($datos['contenido']['Documentacion'] ?? '') . '</textarea>
    </div>';

                    /* --- ARCHIVO GENERAL --- */
                    $campo .= '
    <div class="mb-3">

        <label>Archivo actual:</label>
        <?php if (' . $datos['archivo_nombre'] . '): ?>
            <a href="descargar.php?id='. $datos['id_tarea'] .'">
                Descargar archivo (' . $datos['archivo_nombre'] . ')
            </a>
        <?php else: ?>
            <p>No hay archivo cargado.</p>
        <?php endif; ?>

        <label class="mt-2">Subir archivo nuevo:</label>
        <input type="file" name="archivo" class="form-control">

    </div>';

                    /* --- COMENTARIOS PARA EL ESTUDIANTE --- */
                    $campo .= '
    <div class="mb-3">
        <label>Comentarios del investigador/supervisor:</label>
        <textarea class="form-control" name="Comentarios" rows="3" disabled>' . ($datos['comentarios'] ?? '') . '</textarea>
    </div>';
                }
                break;
            case 'investigador':
                if ($descripcion == "hidden") {
                    $campo = '<input type="hidden" name="action" value="editarTareaRevisar">
    <input type="hidden" name="id_tareas" value="<?= '. $datos['id_tarea'] .' ?>">';
                } else if ($descripcion == "Resumen") {
                    $campo = '<h4>1. Resumen / Abstract</h4>';

                    $campo .= '<div class="mb-3">
        <label>Resumen / Abstract:</label>
        <textarea class="form-control" name="Resumen" rows="4" disabled>' . (($datos['contenido']['Resumen'] ?? '') ?? '') . '</textarea>
    </div>';

                    $campo .= '<div class="mb-3">
        <label>Archivo actual:</label>
        <?php if (' . $datos['archivo_nombre'] . '): ?>
            <a href="descargar.php?id='. $datos['id_tarea'] .'">
                Descargar archivo (' . $datos['archivo_nombre'] . ')
            </a>
        <?php else: ?>
            <p>No hay archivo cargado.</p>
        <?php endif; ?>

        <!-- investigador no sube archivos al contenido; input se muestra deshabilitado -->
        <label class="mt-2">Subir archivo nuevo:</label>
        <input type="file" name="archivo_Resumen" class="form-control" disabled>
    </div>';

                    $campo .= '<div class="mb-3">
        <label>Comentarios del investigador:</label>
        <textarea class="form-control" name="Comentarios" rows="3">' . ($datos['comentarios'] ?? '') . '</textarea>
    </div>';
                } else if ($descripcion == "Introducción") {
                    $campo = '<h4>2. Introducción</h4>';

                    $campo .= '<div class="mb-3">        <label>Introducción:</label>
        <textarea class="form-control" name="Introducción" rows="4" disabled>' . ($datos['contenido']['Introduccion'] ?? '') . '</textarea>
    </div>';

                    $campo .= ' <div class="mb-3">
        <label>Archivo actual:</label>
        <?php if (' . $datos['archivo_nombre'] . '): ?>
            <a href="descargar.php?id='. $datos['id_tarea'] .'">
                Descargar archivo (' . $datos['archivo_nombre'] . ')
            </a>
        <?php else: ?>
            <p>No hay archivo cargado.</p>
        <?php endif; ?>

        <!-- investigador no sube archivos al contenido; input se muestra deshabilitado -->
        <label class="mt-2">Subir archivo nuevo:</label>
        <input type="file" name="archivo" class="form-control" disabled>
    </div>';

                    $campo .= ' <div class="mb-3">
        <label>Comentarios del investigador:</label>
        <textarea class="form-control" name="Comentarios" rows="3">' . ($datos['comentarios'] ?? '') . '</textarea>
    </div>';
                } else if ($descripcion == "PlanteamientoProblema") {

                    $campo = '<h4>3. Planteamiento del Problema</h4>';

                    $campo .= ' <div class="mb-3">
        <label>Planteamiento del Problema:</label>
        <textarea class="form-control" name="PlanteamientoProblema" rows="4" disabled>' . ($datos['contenido']['PlanteamientoProblema'] ?? '') . '</textarea>
    </div>';

                    $campo .= ' <div class="mb-3">
        <label>Archivo actual:</label>
        <?php if (' . $datos['archivo_nombre'] . '): ?>
            <a href="descargar.php?id='. $datos['id_tarea'] .'">
                Descargar archivo (' . $datos['archivo_nombre'] . ')
            </a>
        <?php else: ?>
            <p>No hay archivo cargado.</p>
        <?php endif; ?>

        <label class="mt-2">Subir archivo nuevo:</label>
        <input type="file" name="archivo_Planteamiento" class="form-control" disabled>
    </div>';

                    $campo .= ' <div class="mb-3">
        <label>Comentarios del investigador:</label>
        <textarea class="form-control" name="Comentarios" rows="3">' . ($datos['comentarios'] ?? '') . '</textarea>
    </div>';
                } else if ($descripcion == "Justificacion") {

                    $campo = '<h4>4. Justificación</h4>';

                    $campo .= '<div class="mb-3">
        <label>Justificación:</label>
        <textarea class="form-control" name="Justificacion" rows="4" disabled>' . ($datos['contenido']['Justificacion'] ?? '') . '</textarea>
    </div>';

                    $campo .= ' <div class="mb-3">
        <label>Archivo actual:</label>
        <?php if (' . $datos['archivo_nombre'] . '): ?>
            <a href="descargar.php?id='. $datos['id_tarea'] .'">
                Descargar archivo (' . $datos['archivo_nombre'] . ')
            </a>
        <?php else: ?>
            <p>No hay archivo cargado.</p>
        <?php endif; ?>

        <label class="mt-2">Subir archivo nuevo:</label>
        <input type="file" name="archivo" class="form-control" disabled>
    </div>';

                    $campo .= ' <div class="mb-3">
        <label>Comentarios del investigador:</label>
        <textarea class="form-control" name="Comentarios" rows="3">' . ($datos['comentarios'] ?? '') . '</textarea>
    </div>';
                } else if ($descripcion == "Objetivos") {

                    $campo = '<h4>5. Objetivos</h4>';

                    // OBJETIVO GENERAL (bloqueado)
                    $campo .= ' <div class="mb-3">
        <label>Objetivo general:</label>
        <textarea class="form-control" name="ObjetivoGeneral" rows="4" disabled>' . ($datos['contenido']['ObjetivoGeneral'] ?? '') . '</textarea>
    </div>';

                    // OBJETIVOS ESPECÍFICOS (bloqueado)
                    $campo .= ' <div class="mb-3">
        <label>Objetivos específicos:</label>
        <textarea class="form-control" name="ObjetivosEspecificos" rows="4" disabled>' . ($datos['contenido']['ObjetivosEspecificos'] ?? '') . '</textarea>
    </div>';

                    // ARCHIVO (bloqueado)
                    $campo .= ' <div class="mb-3">
        <label>Archivo actual:</label>
        <?php if (' . $datos['archivo_nombre'] . '): ?>
            <a href="descargar.php?id='. $datos['id_tarea'] .'">
                Descargar archivo (' . $datos['archivo_nombre'] . ')
            </a>
        <?php else: ?>
            <p>No hay archivo cargado.</p>
        <?php endif; ?>

        <label class="mt-2">Subir archivo nuevo:</label>
        <input type="file" name="archivo" class="form-control" disabled>
    </div>';

                    // COMENTARIOS (solo investigador puede editar)
                    $campo .= ' <div class="mb-3">
        <label>Comentarios:</label>
        <textarea class="form-control" name="Comentarios" rows="3">' . ($datos['comentarios'] ?? '') . '</textarea>
    </div>';
                } else if ($descripcion == "MarcoTeorico") {

                    $campo = '<h4>6. Marco teórico y/o de referencia</h4>';

                    /* ===================== ANTECEDENTES ===================== */
                    $campo .= '
    <div class="mb-3">
        <label>Antecedentes:</label>
        <textarea class="form-control" name="Antecedentes" rows="4" disabled>' . ($datos['contenido']['Antecedentes'] ?? '') . '</textarea>
    </div>';

                    /* ===================== FUNDAMENTACIÓN TEÓRICA ===================== */
                    $campo .= '
    <div class="mb-3">
        <label>Fundamentación teórica:</label>
        <textarea class="form-control" name="Fundamentacion" rows="4" disabled>' . ($datos['contenido']['Fundamentacion'] ?? '') . '</textarea>
    </div>';

                    /* ===================== MARCO CONCEPTUAL ===================== */
                    $campo .= '
    <div class="mb-3">
        <label>Marco conceptual:</label>
        <textarea class="form-control" name="MarcoConceptual" rows="4" disabled>' . ($datos['contenido']['MarcoConceptual'] ?? '') . '</textarea>
    </div>';

                    /* ===================== ARCHIVO ===================== */
                    $campo .= '
    <div class="mb-3">
        <label>Archivo actual:</label>
        <?php if (' . $datos['archivo_nombre'] . '): ?>
            <a href="descargar.php?id='. $datos['id_tarea'] .'">
                Descargar archivo (' . $datos['archivo_nombre'] . ')
            </a>
        <?php else: ?>
            <p>No hay archivo cargado.</p>
        <?php endif; ?>

        <label class="mt-2">Subir archivo nuevo:</label>
        <input type="file" name="archivo" class="form-control" disabled>
    </div>';

                    /* ===================== COMENTARIOS EDITABLE ===================== */
                    $campo .= '
    <div class="mb-3">
        <label>Comentarios:</label>
        <textarea class="form-control" name="Comentarios" rows="3">' . ($datos['comentarios'] ?? '') . '</textarea>
    </div>';
                } else if ($descripcion == "Metodologia") {
                    $campo = '<h4>7. Metodología</h4>';

                    /* === Tipo de estudio === */
                    $campo .= '
        <div class="mb-3">
            <label>6.1 Tipo de estudio:</label>
            <textarea class="form-control" name="TipoEstudio" rows="4" disabled>'
                        . ($datos['contenido']['MetodosTecnicas'] ?? '') .
                        '</textarea>
        </div>';

                    /* === Métodos y técnicas === */
                    $campo .= '
        <div class="mb-3">
            <label>6.2 Métodos y técnicas:</label>
            <textarea class="form-control" name="MetodosTecnicas" rows="4" disabled>'
                        . ($datos['contenido']['MetodosTecnicas'] ?? '') .
                        '</textarea>
        </div>';

                    /* === Población y muestra === */
                    $campo .= '
        <div class="mb-3">
            <label>6.3 Población y muestra:</label>
            <textarea class="form-control" name="PoblacionMuestra" rows="4" disabled>'
                        . ($datos["contenido"]["PoblacionMuestra"]) .
                        '</textarea>
        </div>';

                    /* === Instrumentos === */
                    $campo .= '
        <div class="mb-3">
            <label>6.4 Instrumentos:</label>
            <textarea class="form-control" name="Instrumentos" rows="4" disabled>'
                        . ($datos["contenido"]["Instrumentos"]) .
                        '</textarea>
        </div>';

                    $campo .= '
        <div class="mb-3">
            <label>Archivo actual:</label>';
                    if (!empty($datos["archivo_nombre_"])) {
                        $campo .= '<a href="descargar.php?id=' . $datos["id_tarea"] . '">
                Descargar archivo (' . $datos["archivo_nombre"] . ')
            </a>';
                    } else {
                        $campo .= '<p>No hay archivo cargado.</p>';
                    }
                    $campo .= '
            <label class="mt-2">Subir archivo nuevo:</label>
            <input type="file" name="archivo" class="form-control">
        </div>';

                    /* === Comentarios del investigador visibles === */
                    $campo .= '
        <div class="mb-3">
            <label>Comentarios del investigador:</label>
            <textarea class="form-control" rows="3" name="Comentarios"> '
                        . ($datos["contenido"]["Comentarios"]) .
                        '</textarea>
        </div>';
                } else if ($descripcion == "MetasProductosImpacto") {
                    $campo = '<h4>8. Metas, productos esperados e impacto</h4>';

                    /* --- 8.1 Metas --- */
                    $campo .= '
    <div class="mb-3">
        <label>8.1 Metas:</label>
        <textarea class="form-control" name="Metas" rows="4" disabled>' . ($datos['contenido']['Metas'] ?? '') . '</textarea>
    </div>';

                    /* --- 8.2 Productos esperados --- */
                    $campo .= '
    <div class="mb-3">
        <label>8.2 Productos esperados:</label>
        <textarea class="form-control" name="ProductosEsperados" rows="4" disabled>' . ($datos['contenido']['ProductosEsperados'] ?? '') . '</textarea>
    </div>';

                    /* --- 8.3 Impacto --- */
                    $campo .= '
    <div class="mb-3">
        <label>8.3 Impacto:</label>
        <textarea class="form-control" name="Impacto" rows="4" disabled>' . ($datos['contenido']['Impacto'] ?? '') . '</textarea>
    </div>';

                    /* --- ARCHIVO GENERAL --- */
                    $campo .= '
    <div class="mb-3">
        <label>Archivo actual:</label>
        <?php if (' . $datos['archivo_nombre'] . '): ?>
            <a href="descargar.php?id='. $datos['id_tarea'] .'">
                Descargar archivo (' . $datos['archivo_nombre'] . ')
            </a>
        <?php else: ?>
            <p>No hay archivo cargado.</p>
        <?php endif; ?>

        <label class="mt-2">Subir archivo nuevo:</label>
        <input type="file" class="form-control" disabled>
    </div>';

                    /* --- COMENTARIOS (investigador puede escribir) --- */
                    $campo .= '
    <div class="mb-3">
        <label>Comentarios al estudiante:</label>
        <textarea class="form-control" name="Comentarios" rows="3">' . ($datos['comentarios'] ?? '') . '</textarea>
    </div>';
                } else if ($descripcion == "Cronograma") {

                    $campo = '<h4>9. Cronograma</h4>';

                    /* --- CRONOGRAMA (solo lectura) --- */
                    $campo .= '
    <div class="mb-3">
        <label>Cronograma:</label>
        <textarea class="form-control" name="Cronograma" rows="6" disabled>' . ($datos['contenido']['Cronograma'] ?? '') . '</textarea>
    </div>';

                    /* --- ARCHIVO GENERAL --- */
                    $campo .= '
    <div class="mb-3">
        <label>Archivo actual:</label>
        <?php if (' . $datos['archivo_nombre'] . '): ?>
            <a href="descargar.php?id='. $datos['id_tarea'] .'">
                Descargar archivo (' . $datos['archivo_nombre'] . ')
            </a>
        <?php else: ?>
            <p>No hay archivo cargado.</p>
        <?php endif; ?>

        <label class="mt-2">Subir archivo nuevo:</label>
        <input type="file" class="form-control" disabled>
    </div>';

                    /* --- COMENTARIOS (investigador puede escribir) --- */
                    $campo .= '
    <div class="mb-3">
        <label>Comentarios al estudiante:</label>
        <textarea class="form-control" name="Comentarios" rows="3">' . ($datos['comentarios'] ?? '') . '</textarea>
    </div>';
                } else if ($descripcion == "Bibliografia") {

                    $campo = '<h4>10. Bibliografía</h4>';

                    /* --- BIBLIOGRAFÍA (solo lectura) --- */
                    $campo .= '
    <div class="mb-3">
        <label>Bibliografía:</label>
        <textarea class="form-control" name="Bibliografia" rows="6" disabled>' . ($datos['contenido']['Bibliografia'] ?? '') . '</textarea>
    </div>';

                    /* --- ARCHIVO GENERAL --- */
                    $campo .= '
    <div class="mb-3">

        <label>Archivo actual:</label>
        <?php if (' . $datos['archivo_nombre'] . '): ?>
            <a href="descargar.php?id='. $datos['id_tarea'] .'">
                Descargar archivo (' . $datos['archivo_nombre'] . ')
            </a>
        <?php else: ?>
            <p>No hay archivo cargado.</p>
        <?php endif; ?>

        <label class="mt-2">Subir archivo nuevo:</label>
        <input class="form-control" type="file" disabled>

    </div>';

                    /* --- COMENTARIOS (investigador puede escribir) --- */
                    $campo .= '
    <div class="mb-3">
        <label>Comentarios al estudiante:</label>
        <textarea class="form-control" name="Comentarios" rows="3">' . ($datos['comentarios'] ?? '') . '</textarea>
    </div>';
                } else if ($descripcion == "Anexos") {

                    $campo = '<h4>11. Anexos (Opcional)</h4>';

                    /* --- 11.1 Instrumentos de recolección --- */
                    $campo .= '
    <div class="mb-3">
        <label>Instrumentos de recolección (Explicación):</label>
        <textarea class="form-control" rows="4" disabled>' . ($datos['contenido']['Instrumentos'] ?? '') . '</textarea>
    </div>';

                    /* --- 11.2 Formularios de consentimiento --- */
                    $campo .= '
    <div class="mb-3">
        <label>Formularios de consentimiento (Explicación):</label>
        <textarea class="form-control" rows="4" disabled>' . (($datos['contenido']['Consentimiento'] ?? '') ?? '') . '</textarea>
    </div>';

                    /* --- 11.3 Mapas, tablas, gráficos adicionales --- */
                    $campo .= '
    <div class="mb-3">
        <label>Mapas, tablas, gráficos adicionales (Explicación):</label>
        <textarea class="form-control" rows="4" disabled>' . ($datos['contenido']['Mapas'] ?? '') . '</textarea>
    </div>';

                    /* --- 11.4 Otra documentación complementaria --- */
                    $campo .= '
    <div class="mb-3">
        <label>Otra documentación complementaria (Explicación):</label>
        <textarea class="form-control" rows="4" disabled>' . ($datos['contenido']['Documentacion'] ?? '') . '</textarea>
    </div>';

                    /* --- ARCHIVO GENERAL (solo descargar) --- */
                    $campo .= '
    <div class="mb-3">

        <label>Archivo actual:</label>
        <?php if (' . $datos['archivo_nombre'] . '): ?>
            <a href="descargar.php?id='. $datos['id_tarea'] .'">
                Descargar archivo (' . $datos['archivo_nombre'] . ')
            </a>
        <?php else: ?>
            <p>No hay archivo cargado.</p>
        <?php endif; ?>

        <input type="file" class="form-control mt-2" disabled>

    </div>';

                    /* --- COMENTARIOS QUE SÍ PUEDE ESCRIBIR EL INVESTIGADOR --- */
                    $campo .= '
    <div class="mb-3">
        <label>Comentarios al estudiante:</label>
        <textarea class="form-control" name="Comentarios" rows="3">' . ($datos['comentarios'] ?? '') . '</textarea>
    </div>';
                }
                break;
            case 'supervisor':
                if ($descripcion == "Resumen") {
                    $campo = '<h4>1. Resumen / Abstract</h4>';

                    $campo .= '<div class="mb-3">
        <label>Resumen / Abstract:</label>
        <textarea class="form-control" name="Resumen" rows="4" disabled>' . (($datos['contenido']['Resumen'] ?? '') ?? '') . '</textarea>
    </div>';

                    $campo .= '<div class="mb-3">
        <label>Archivo actual:</label>
        <?php if (' . $datos['archivo_nombre'] . '): ?>
            <a href="descargar.php?id='. $datos['id_tarea'] .'">
                Descargar archivo (' . $datos['archivo_nombre'] . ')
            </a>
        <?php else: ?>
            <p>No hay archivo cargado.</p>
        <?php endif; ?>

        <!-- supervisor no puede subir -->
        <label class="mt-2">Subir archivo nuevo:</label>
        <input type="file" name="archivo_Resumen" class="form-control" disabled>
    </div>';

                    $campo .= '<div class="mb-3">
        <label>Comentarios:</label>
        <textarea class="form-control" name="Comentarios" rows="3" disabled>' . ($datos['comentarios'] ?? '') . '</textarea>
    </div>';
                } else if ($descripcion == "Introducción") {
                    $campo = '<h4>2. Introducción</h4>';

                    $campo .= '<div class="mb-3">
        <label>Introducción:</label>
        <textarea class="form-control" name="Introducción" rows="4" disabled>' . ($datos['contenido']['Introduccion'] ?? '') . '</textarea>
    </div>';

                    $campo .= '<div class="mb-3">
        <label>Archivo actual:</label>
        <?php if (' . $datos['archivo_nombre'] . '): ?>
            <a href="descargar.php?id='. $datos['id_tarea'] .'">
                Descargar archivo (' . $datos['archivo_nombre'] . ')
            </a>
        <?php else: ?>
            <p>No hay archivo cargado.</p>
        <?php endif; ?>

        <!-- supervisor no puede subir -->
        <label class="mt-2">Subir archivo nuevo:</label>
        <input type="file" name="archivo_Introduccion" class="form-control" disabled>
    </div>';

                    $campo .= ' <div class="mb-3">
        <label>Comentarios:</label>
        <textarea class="form-control" name="Comentarios" rows="3" disabled>' . ($datos['comentarios'] ?? '') . '</textarea>
    </div>';
                } else if ($descripcion == "PlanteamientoProblema") {

                    $campo = '<h4>3. Planteamiento del Problema</h4>';

                    $campo .= '<div class="mb-3">
        <label>Planteamiento del Problema:</label>
        <textarea class="form-control" name="PlanteamientoProblema" rows="4" disabled>' . ($datos['contenido']['PlanteamientoProblema'] ?? '') . '</textarea>
    </div>';

                    $campo .= ' <label>Archivo actual:</label>
        <?php if (' . $datos['archivo_nombre'] . '): ?>
            <a href="descargar.php?id='. $datos['id_tarea'] .'">
                Descargar archivo (' . $datos['archivo_nombre'] . ')
            </a>
        <?php else: ?>
            <p>No hay archivo cargado.</p>
        <?php endif; ?>

        <label class="mt-2">Subir archivo nuevo:</label>
        <input type="file" name="archivo_Planteamiento" class="form-control" disabled>
    </div>';

                    $campo .= '<div class="mb-3">
        <label>Comentarios:</label>
        <textarea class="form-control" name="Comentarios" rows="3" disabled>' . ($datos['comentarios'] ?? '') . '</textarea>
    </div>';
                } else if ($descripcion == "Justificacion") {

                    $campo = '<h4>4. Justificación</h4>';

                    $campo .= '<div class="mb-3">
        <label>Justificación:</label>
        <textarea class="form-control" name="Justificacion" rows="4" disabled>' . ($datos['contenido']['Justificacion'] ?? '') . '</textarea>
    </div>';

                    $campo .= '<div class="mb-3">
        <label>Archivo actual:</label>
        <?php if (' . $datos['archivo_nombre'] . '): ?>
            <a href="descargar.php?id='. $datos['id_tarea'] .'">
                Descargar archivo (' . $datos['archivo_nombre'] . ')
            </a>
        <?php else: ?>
            <p>No hay archivo cargado.</p>
        <?php endif; ?>

        <label class="mt-2">Subir archivo nuevo:</label>
        <input type="file" name="archivo_Justificacion" class="form-control" disabled>
    </div>';

                    $campo .= '<div class="mb-3">
        <label>Comentarios:</label>
        <textarea class="form-control" name="Comentarios" rows="3" disabled>' . ($datos['comentarios'] ?? '') . '</textarea>
    </div>';
                } else if ($descripcion == "Objetivos") {

                    $campo = '<h4>5. Objetivos</h4>';

                    // OBJETIVO GENERAL
                    $campo .= '<div class="mb-3">
        <label>Objetivo general:</label>
        <textarea class="form-control" name="ObjetivoGeneral" rows="4" disabled>' . ($datos['contenido']['ObjetivoGeneral'] ?? '') . '</textarea>
    </div>';

                    // OBJETIVOS ESPECÍFICOS
                    $campo .= '<div class="mb-3">
        <label>Objetivos específicos:</label>
        <textarea class="form-control" name="ObjetivosEspecificos" rows="4" disabled>' . ($datos['contenido']['ObjetivosEspecificos'] ?? '') . '</textarea>
    </div>';

                    // ARCHIVO
                    $campo .= '<div class="mb-3">
        <label>Archivo actual:</label>
        <?php if (' . $datos['archivo_nombre'] . '): ?>
            <a href="descargar.php?id='. $datos['id_tarea'] .'">
                Descargar archivo (' . $datos['archivo_nombre'] . ')
            </a>
        <?php else: ?>
            <p>No hay archivo cargado.</p>
        <?php endif; ?>

        <label class="mt-2">Subir archivo nuevo:</label>
        <input type="file" name="archivo_Objetivos" class="form-control" disabled>
    </div>';

                    // COMENTARIOS (solo lectura)
                    $campo .= '<div class="mb-3">
        <label>Comentarios:</label>
        <textarea class="form-control" name="Comentarios" rows="3" disabled>' . ($datos['comentarios'] ?? '') . '</textarea>
    </div>';
                } else if ($descripcion == "MarcoTeorico") {

                    $campo = '<h4>6. Marco teórico y/o de referencia</h4>';

                    /* ===================== ANTECEDENTES ===================== */
                    $campo .= '
    <div class="mb-3">
        <label>Antecedentes:</label>
        <textarea class="form-control" name="Antecedentes" rows="4" disabled>' . ($datos['contenido']['Antecedentes'] ?? '') . '</textarea>
    </div>';

                    /* ===================== FUNDAMENTACIÓN TEÓRICA ===================== */
                    $campo .= '
    <div class="mb-3">
        <label>Fundamentación teórica:</label>
        <textarea class="form-control" name="Fundamentacion" rows="4" disabled>' . ($datos['contenido']['Fundamentacion'] ?? '') . '</textarea>
    </div>';

                    /* ===================== MARCO CONCEPTUAL ===================== */
                    $campo .= '
    <div class="mb-3">
        <label>Marco conceptual:</label>
        <textarea class="form-control" name="MarcoConceptual" rows="4" disabled>' . ($datos['contenido']['MarcoConceptual'] ?? '') . '</textarea>
    </div>';

                    /* ===================== ARCHIVO ===================== */
                    $campo .= '
    <div class="mb-3">
        <label>Archivo actual:</label>
        <?php if (' . $datos['archivo_nombre'] . '): ?>
            <a href="descargar.php?id='. $datos['id_tarea'] .'">
                Descargar archivo (' . $datos['archivo_nombre'] . ')
            </a>
        <?php else: ?>
            <p>No hay archivo cargado.</p>
        <?php endif; ?>

        <label class="mt-2">Subir archivo nuevo:</label>
        <input type="file" name="archivo_MarcoTeorico" class="form-control" disabled>
    </div>';

                    /* ===================== COMENTARIOS ===================== */
                    $campo .= '
    <div class="mb-3">
        <label>Comentarios:</label>
        <textarea class="form-control" name="Comentarios" rows="3" disabled>' . ($datos['comentarios'] ?? '') . '></textarea>
    </div>';
                } else if ($descripcion == "Metodologia") {

                    $campo = '<h4>7. Metodología</h4>';

                    /* ===========================================================
       5.1 Diseño de investigación
       =========================================================== */
                    $campo .= '
    <h5 class="mt-4">5.1 Diseño de investigación</h5>
    <div class="mb-3">
        <label>Diseño de investigación:</label>
        <textarea class="form-control" name="Metodologia_Diseno" rows="4" disabled>
            ' . $datos['contenido']['Metodologia_Diseno'] . '
        </textarea>
    </div>';

                    /* ===========================================================
       5.2 Enfoque
       =========================================================== */
                    $campo .= '
    <h5 class="mt-4">5.2 Enfoque</h5>
    <div class="mb-3">
        <label>Enfoque:</label>
        <textarea class="form-control" name="Metodologia_Enfoque" rows="4" disabled>
            ' . $datos['contenido']['Metodologia_Enfoque'] . '
        </textarea>
    </div>';

                    /* ===========================================================
       5.3 Técnicas e instrumentos
       =========================================================== */
                    $campo .= '
    <h5 class="mt-4">5.3 Técnicas e instrumentos de recolección de datos</h5>
    <div class="mb-3">
        <label>Técnicas e instrumentos:</label>
        <textarea class="form-control" name="Metodologia_Tecnicas" rows="4" disabled>
            ' . $datos['contenido']['Metodologia_Tecnicas'] . '
        </textarea>
    </div>';

                    /* ===========================================================
       5.4 Población y muestra
       =========================================================== */
                    $campo .= '
    <h5 class="mt-4">5.4 Población y muestra</h5>
    <div class="mb-3">
        <label>Población y muestra:</label>
        <textarea class="form-control" name="Metodologia_Poblacion" rows="4" disabled>
            ' . $datos['contenido']['Metodologia_Poblacion'] . '
        </textarea>
    </div>';

                    /* ===========================================================
       5.5 Procedimiento
       =========================================================== */
                    $campo .= '
    <h5 class="mt-4">5.5 Procedimiento para la recolección de datos</h5>
    <div class="mb-3">
        <label>Procedimiento:</label>
        <textarea class="form-control" name="Metodologia_Procedimiento" rows="4" disabled>
            ' . $datos['contenido']['Metodologia_Procedimiento'] . '
        </textarea>
    </div>';

                    /* ===========================================================
       ARCHIVO GENERAL (solo se muestra, no editable)
       =========================================================== */
                    $campo .= '
    <div class="mb-3 mt-4">
        <label>Archivo actual:</label>
        <?php if (' . $datos['archivo_nombre'] . '): ?>
            <a href="descargar.php?id='. $datos['id_tarea'] .'">
                Descargar archivo (' . $datos['archivo_nombre'] . ')
            </a>
        <?php else: ?>
            <p>No hay archivo cargado.</p>
        <?php endif; ?>

        <label class="mt-2">Subir archivo nuevo:</label>
        <input type="file" name="archivo_Metodologia" class="form-control" disabled>
    </div>';
                } else if ($descripcion == "MetasProductosImpacto") {

                    $campo = '<h4>8. Metas, productos esperados e impacto</h4>';

                    /* --- 8.1 Metas --- */
                    $campo .= '
    <div class="mb-3">
        <label>8.1 Metas:</label>
        <textarea class="form-control" name="Metas" rows="4" disabled>' . ($datos['contenido']['Metas'] ?? '') . '</textarea>
    </div>';

                    /* --- 8.2 Productos esperados --- */
                    $campo .= '
    <div class="mb-3">
        <label>8.2 Productos esperados:</label>
        <textarea class="form-control" name="ProductosEsperados" rows="4" disabled>' . ($datos['contenido']['ProductosEsperados'] ?? '') . '</textarea>
    </div>';

                    /* --- 8.3 Impacto --- */
                    $campo .= '
    <div class="mb-3">
        <label>8.3 Impacto:</label>
        <textarea class="form-control" name="Impacto" rows="4" disabled>' . ($datos['contenido']['Impacto'] ?? '') . '</textarea>
    </div>';

                    /* --- ARCHIVO GENERAL --- */
                    $campo .= '
    <div class="mb-3">
        <label>Archivo actual:</label>
        <?php if (' . $datos['archivo_nombre'] . '): ?>
            <a href="descargar.php?id='. $datos['id_tarea'] .'">
                Descargar archivo (' . $datos['archivo_nombre'] . ')
            </a>
        <?php else: ?>
            <p>No hay archivo cargado.</p>
        <?php endif; ?>

        <label class="mt-2">Subir archivo nuevo:</label>
        <input type="file" class="form-control" disabled>
    </div>';

                    /* --- COMENTARIOS (solo visualiza) --- */
                    $campo .= '
    <div class="mb-3">
        <label>Comentarios del investigador:</label>
        <textarea class="form-control" name="Comentarios" rows="3" disabled>' . ($datos['comentarios'] ?? '') . '</textarea>
    </div>';
                } else if ($descripcion == "Cronograma") {

                    $campo = '<h4>9. Cronograma</h4>';

                    /* --- CRONOGRAMA (solo lectura) --- */
                    $campo .= '
    <div class="mb-3">
        <label>Cronograma:</label>
        <textarea class="form-control" name="Cronograma" rows="6" disabled>' . ($datos['contenido']['Cronograma'] ?? '') . '</textarea>
    </div>';

                    /* --- ARCHIVO GENERAL --- */
                    $campo .= '
    <div class="mb-3">
        <label>Archivo actual:</label>
        <?php if (' . $datos['archivo_nombre'] . '): ?>
            <a href="descargar.php?id='. $datos['id_tarea'] .'">
                Descargar archivo (' . $datos['archivo_nombre'] . ')
            </a>
        <?php else: ?>
            <p>No hay archivo cargado.</p>
        <?php endif; ?>

        <label class="mt-2">Subir archivo nuevo:</label>
        <input type="file" class="form-control" disabled>
    </div>';

                    /* --- COMENTARIOS (solo lectura) --- */
                    $campo .= '
    <div class="mb-3">
        <label>Comentarios del investigador:</label>
        <textarea class="form-control" name="Comentarios" rows="3" disabled>' . ($datos['comentarios'] ?? '') . '</textarea>
    </div>';
                } else if ($descripcion == "Bibliografia") {

                    $campo = '<h4>10. Bibliografía</h4>';

                    /* --- BIBLIOGRAFÍA (solo lectura) --- */
                    $campo .= '
    <div class="mb-3">
        <label>Bibliografía:</label>
        <textarea class="form-control" name="Bibliografia" rows="6" disabled>' . ($datos['contenido']['Bibliografia'] ?? '') . '</textarea>
    </div>';

                    /* --- ARCHIVO GENERAL --- */
                    $campo .= '
    <div class="mb-3">

        <label>Archivo actual:</label>
        <?php if (' . $datos['archivo_nombre'] . '): ?>
            <a href="descargar.php?id='. $datos['id_tarea'] .'">
                Descargar archivo (' . $datos['archivo_nombre'] . ')
            </a>
        <?php else: ?>
            <p>No hay archivo cargado.</p>
        <?php endif; ?>

        <label class="mt-2">Subir archivo nuevo:</label>
        <input class="form-control" type="file" disabled>

    </div>';

                    /* --- COMENTARIOS (solo lectura) --- */
                    $campo .= '
    <div class="mb-3">
        <label>Comentarios del investigador:</label>
        <textarea class="form-control" name="Comentarios" rows="3" disabled>' . ($datos['comentarios'] ?? '') . '</textarea>
    </div>';
                } else if ($descripcion == "Anexos") {

                    $campo = '<h4>11. Anexos (Opcional)</h4>';

                    /* --- 11.1 Instrumentos de recolección --- */
                    $campo .= '
    <div class="mb-3">
        <label>Instrumentos de recolección (Explicación):</label>
        <textarea class="form-control" rows="4" disabled>' . ($datos['contenido']['Instrumentos'] ?? '') . '</textarea>
    </div>';

                    /* --- 11.2 Formularios de consentimiento --- */
                    $campo .= '
    <div class="mb-3">
        <label>Formularios de consentimiento (Explicación):</label>
        <textarea class="form-control" rows="4" disabled>' . (($datos['contenido']['Consentimiento'] ?? '') ?? '') . '</textarea>
    </div>';

                    /* --- 11.3 Mapas, tablas, gráficos adicionales --- */
                    $campo .= '
    <div class="mb-3">
        <label>Mapas, tablas, gráficos adicionales (Explicación):</label>
        <textarea class="form-control" rows="4" disabled>' . ($datos['contenido']['Mapas'] ?? '') . '</textarea>
    </div>';

                    /* --- 11.4 Otra documentación complementaria --- */
                    $campo .= '
    <div class="mb-3">
        <label>Otra documentación complementaria (Explicación):</label>
        <textarea class="form-control" rows="4" disabled>' . ($datos['contenido']['Documentacion'] ?? '') . '</textarea>
    </div>';

                    /* --- ARCHIVO GENERAL (solo lectura) --- */
                    $campo .= '
    <div class="mb-3">

        <label>Archivo actual:</label>
        <?php if (' . $datos['archivo_nombre'] . '): ?>
            <a href="descargar.php?id='. $datos['id_tarea'] .'">
                Descargar archivo (' . $datos['archivo_nombre'] . ')
            </a>
        <?php else: ?>
            <p>No hay archivo cargado.</p>
        <?php endif; ?>

        <input type="file" class="form-control mt-2" disabled>

    </div>';

                    /* --- COMENTARIOS (solo ver) --- */
                    $campo .= '
    <div class="mb-3">
        <label>Comentarios del investigador:</label>
        <textarea class="form-control" rows="3" disabled>' . ($datos['comentarios'] ?? '') . '</textarea>
    </div>';
                }
                break;
            default:
                break;
        }
        return $campo;
    }



    //Para obtener el número del filtro de la tabla
    public function numerofiltro($action)
    {

        $numerofiltro = 0;
        switch ($action) {
            case 'Total':
                $numerofiltro = 0;
                break;
            case 'Pendiente':
                $numerofiltro = 1;
                break;
            case 'Revisar':
                $numerofiltro = 2;
                break;
            case 'Corregir':
                $numerofiltro = 3;
                break;
            case 'SinActivar':
                $numerofiltro = 4;
                break;
            case 'Aprobado':
                $numerofiltro = 5;
                break;
            case 'Vencido':
                $numerofiltro = 6;
                break;
            default:
                break;
        }
        return $numerofiltro;
    }
    // Encabezados según rol
    //Para obtener los encabezados de las tablas
    public function encabezadosPrincipal($rol)
    {
        switch ($rol) {
            case 'estudiante':
                $encabezados = [
                    'Tarea',
                    'Estado',
                    'Guía',
                    'FechaEntrega',
                    'Acciones'
                ];
                break;
            case 'investigador':
            case 'profesor':
            case 'supervisor':
                $encabezados = [
                    'Tarea',
                    'Entregado',
                    'Estado',
                    'Guía',
                    'FechaEntrega',
                    'Acciones'
                ];
                break;
            default:
                $encabezados = [];
                break;
        }
        return $encabezados;
    }

    //Para obtener los encabezados de las tablas
    public function encabezadosLista($rol)
    {
        switch ($rol) {
            case 'investigador':
            case 'profesor':
            case 'supervisor':
                $encabezados = [
                    'ID',
                    'estudiante',
                    'Estado',
                    'Fecha Entrega',
                    'Acciones'
                ];
                break;
            default:
                $encabezados = [];
                break;
        }
        return $encabezados;
    }

    public function obtenerbotones($tipo, $id1 = null, $id2 = null, $id3= null)
    {
        $boton = "";
        switch ($tipo) {
            case 'Ver Tarea':
                $boton = '<a href="tarea.php?id_asignacion=' . $id1 . '&tipo=' . $id2 . '&id_proyectos='. $id3 .'"><button type="button" class="btn btn-primary" data-bs-toggle="tooltip" data-bs-placement="top"
        data-bs-custom-class="custom-tooltip" data-bs-title="Ver detalles de la tarea"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-eye-fill" style="padding:0px;margin:auto;" viewBox="0 0 16 16">
  <path d="M10.5 8a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0"/><path d="M0 8s3-5.5 8-5.5S16 8 16 8s-3 5.5-8 5.5S0 8 0 8m8 3.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7"/></svg></button></a>';
                break;
            case 'Ver lista':
                $boton = '<a href="lista_tareas.php?id_tarea=' . $id1 . '&id_proyectos=' . $id2 . '"><button type="button" class="btn btn-primary" data-bs-toggle="tooltip" data-bs-placement="top"
        data-bs-custom-class="custom-tooltip" data-bs-title="Ver lista de tareas"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-eye-fill" style="padding:0px;margin:auto;" viewBox="0 0 16 16">
  <path d="M10.5 8a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0"/><path d="M0 8s3-5.5 8-5.5S16 8 16 8s-3 5.5-8 5.5S0 8 0 8m8 3.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7"/></svg></button></a>';
                break;
            case 'Aprobar':
                $boton = '<a href="lista_tareas.php?action=actualizarestado&id_tarea=' . $id1 . '&tipo=Activos"><button type="button" class="btn btn-success" data-bs-toggle="tooltip" data-bs-placement="top"
        data-bs-custom-class="custom-tooltip" data-bs-title="Aprobar tarea"> <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" class="bi bi-check-circle-fill" viewBox="0 0 16 16">
  <path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0m-3.97-3.03a.75.75 0 0 0-1.08.022L7.477 9.417 5.384 7.323a.75.75 0 0 0-1.06 1.06L6.97 11.03a.75.75 0 0 0 1.079-.02l3.992-4.99a.75.75 0 0 0-.01-1.05z"/></svg></button></a>';
                break;
            case 'Activar':
                $boton = '<a href="tabla.php?action=actualizarestado&id_tarea=' . $id1 . '&tipo=Pendiete"><button type="button" class="btn btn-success" data-bs-toggle="tooltip" data-bs-placement="top"
        data-bs-custom-class="custom-tooltip" data-bs-title="Aprobar cierre de proyecto"> <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" class="bi bi-check-circle-fill" viewBox="0 0 16 16">
  <path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0m-3.97-3.03a.75.75 0 0 0-1.08.022L7.477 9.417 5.384 7.323a.75.75 0 0 0-1.06 1.06L6.97 11.03a.75.75 0 0 0 1.079-.02l3.992-4.99a.75.75 0 0 0-.01-1.05z"/></svg></button></a>';
                break;
            case 'Editar Tarea':
                $boton = '<a href="editar.php?id_tarea=' . $id1 . '"> <button type="button" class="btn btn-info" data-bs-toggle="tooltip" data-bs-placement="top"
        data-bs-custom-class="custom-tooltip" data-bs-title="Editar proyecto"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-pencil-square" viewBox="0 0 16 16">
  <path d="M15.502 1.94a.5.5 0 0 1 0 .706L14.459 3.69l-2-2L13.502.646a.5.5 0 0 1 .707 0l1.293 1.293zm-1.75 2.456-2-2L4.939 9.21a.5.5 0 0 0-.121.196l-.805 2.414a.25.25 0 0 0 .316.316l2.414-.805a.5.5 0 0 0 .196-.12l6.813-6.814z"/>
  <path fill-rule="evenodd" d="M1 13.5A1.5 1.5 0 0 0 2.5 15h11a1.5 1.5 0 0 0 1.5-1.5v-6a.5.5 0 0 0-1 0v6a.5.5 0 0 1-.5.5h-11a.5.5 0 0 1-.5-.5v-11a.5.5 0 0 1 .5-.5H9a.5.5 0 0 0 0-1H2.5A1.5 1.5 0 0 0 1 2.5z"/>
</svg></button></a>';
                break;
            case 'Detalles':
                $boton = '<a href="detalles.php?id_tarea=' . $id1 . '"> <button type="button" class="btn btn-info" data-bs-toggle="tooltip" data-bs-placement="top"
        data-bs-custom-class="custom-tooltip" data-bs-title="Editar proyecto"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-eye-fill" style="padding:0px;margin:auto;" viewBox="0 0 16 16">
  <path d="M10.5 8a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0"/><path d="M0 8s3-5.5 8-5.5S16 8 16 8s-3 5.5-8 5.5S0 8 0 8m8 3.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7"/></svg></button></a>';
                break;
            case 'EnviarTarea':
                $boton = '<a href="tarea.php?id_tarea=' . $id1 . '&action=editarTareaEstudiante&tipo=Revisar"> <button type="button" class="btn btn-info" data-bs-toggle="tooltip" data-bs-placement="top"
        data-bs-custom-class="custom-tooltip" data-bs-title="Editar proyecto"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-eye-fill" style="padding:0px;margin:auto;" viewBox="0 0 16 16">
  <path d="M10.5 8a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0"/><path d="M0 8s3-5.5 8-5.5S16 8 16 8s-3 5.5-8 5.5S0 8 0 8m8 3.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7"/></svg></button></a>';
                break;
            case 'Solicitar Corregir':
                $boton = '<a href="editar.php?id_tarea=' . $id1 . '&action=editarTareaRevisar&tipo=Corregir"> <button type="button" class="btn btn-info" data-bs-toggle="tooltip" data-bs-placement="top"
        data-bs-custom-class="custom-tooltip" data-bs-title="Editar proyecto"><<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-arrow-counterclockwise" viewBox="0 0 16 16">
  <path fill-rule="evenodd" d="M8 3a5 5 0 1 1-4.546 2.914.5.5 0 0 0-.908-.417A6 6 0 1 0 8 2z"/>
  <path d="M8 4.466V.534a.25.25 0 0 0-.41-.192L5.23 2.308a.25.25 0 0 0 0 .384l2.36 1.966A.25.25 0 0 0 8 4.466"/>
</svg></button></a>';
                break;
            default:
                break;
        }
        return $boton;
    }

    //Botones de acción en la tabla 
    public function botonesAccionPrincipal($id, $rol, $estado = null, $id_proyectos = null)
    {
        $boton = "";

        switch ($rol) {

            case 'estudiante':
                if ($estado != "") {
                    $boton = $this->obtenerbotones("Ver Tarea", $id);
                }
                break;

            case 'investigador':
            case 'profesor':
                if (in_array($estado, ["Pendiente", "Revisar", "Corregir", "Aprobado", "Vencido"])) {
                    $boton = $this->obtenerbotones("Ver lista", $id, $id_proyectos);
                } elseif ($estado == "SinActivar") {
                    $boton  = $this->obtenerbotones("Activar", $id);
                    $boton .= $this->obtenerbotones("Editar Tarea", $id);
                }
                break;

            case 'supervisor':
                if (in_array($estado, ["Pendiente", "Revisar", "Corregir", "Aprobado", "Vencido"])) {
                    $boton  = $this->obtenerbotones("Ver lista", $id, $id_proyectos);
                } elseif ($estado == "SinActivar") {
                    $boton = $this->obtenerbotones("Detalles", $id);
                }
                break;
        }

        return $boton;
    }

    public function botonesAccionLista($id1, $rol, $estado = null, $id2 = null, $id3= null)
    {
        $boton = "";

        switch ($rol) {

            case 'investigador':
            case 'profesor':
                if (in_array($estado, ["Revisar", "Corregir"])) {
                    $boton  = $this->obtenerbotones("Ver Tarea", $id1, $id2, $id3);
                    $boton .= $this->obtenerbotones("Aprobar", $id1);
                    $boton .= $this->obtenerbotones("Solicitar Corregir", $id1);
                } elseif (in_array($estado, ["Aprobado", "Vencido", "Pendiente"])) {
                    $boton = $this->obtenerbotones("Ver Tarea", $id1, $id2, $id3);
                }
                break;

            case 'supervisor':
                if (in_array($estado, ["Revisar", "Corregir", "Aprobado", "Vencido", "Pendiente"])) {
                    $boton = $this->obtenerbotones("Ver Tarea", $id1, $id2, $id3);
                }
                break;
        }

        return $boton;
    }

    /* EDITAR TAREA - investigador */
    public function editarTarea($datos, $id, $rol)
    {

        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            if ($rol == "investigador" || $rol == "profesor") {

                $action = $datos['action'] ?? '';

                $id_tareas = $datos['id_tareas'];
                $descripcion = $datos['descripcion'];

                $instrucciones = $datos['Instrucciones'];
                //Tabla seguimiento
                $fecha_entrega = $datos['fecha_entrega'];
                $archivo = null;
                $archivo_nombre = null;
                $archivo_tipo = null;

                if (!empty($_FILES['archivo']['tmp_name'])) {
                    $archivo = file_get_contents($_FILES['archivo']['tmp_name']);
                    $archivo_nombre = $_FILES['archivo']['name'];
                    $archivo_tipo = $_FILES['archivo']['type'];
                }

                if ($action == 'editarTarea') {
                    global $conn;
                    $tarea = new Tarea($conn);
                    $tarea->actualizarTareasVencidos();
                    $tarea->editarTareaGeneral($id_tareas, $descripcion, $instrucciones, $fecha_entrega, $archivo, $archivo_nombre, $archivo_tipo);
                }
            } else {
                die("El usuario no tiene permiso para crear el tarea");
            }
        } else {
            die("Los datos no fueron enviados ha acabado para registrar tareas");
        }
    }

    public function editarTareaEstudiante($datos, $id_usuario, $rol)
    {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            if ($rol == "estudiante") {

                $action = $datos['action'] ?? '';

                $id_asignacion = $datos['id_asignacion'];
                $id_tarea = $datos['id_Tareas'];
                $id_estudiante = $id_usuario;
                // Contenido anterior de la tarea (desde la BD)
                $contenidoActual = json_decode($datos['contenido'], true) ?? [];

                // Aquí guardaremos solo lo nuevo
                $contenidoNuevo = [];

                // Lista de TODOS los posibles campos que pueden existir en cualquier tarea
                $mapaCampos = [
                    // Resumen
                    "Resumen",

                    // Introducción
                    "Introducción",

                    // Planteamiento del problema
                    "PlanteamientoProblema",

                    // Justificación
                    "Justificacion",

                    // Objetivos
                    "ObjetivoGeneral",
                    "ObjetivosEspecificos",

                    // Marco teórico
                    "Antecedentes",
                    "Fundamentacion",
                    "MarcoConceptual",
                    "ComentariosMarcoTeorico",

                    // Metodología
                    "TipoEstudio",
                    "MetodosTecnicas",
                    "PoblacionMuestra",
                    "Instrumentos",

                    // Metas, productos e impacto
                    "Metas",
                    "ProductosEsperados",
                    "Impacto",

                    // Cronograma
                    "Cronograma",

                    // Bibliografía
                    "Bibliografia",

                    // Anexos
                    "Consentimiento",
                    "Mapas",
                    "Documentacion",
                ];

                // Recorrer cada posible campo y ver si vino en el POST
                foreach ($mapaCampos as $campo) {
                    if (isset($_POST[$campo])) {
                        $contenidoNuevo[$campo] = $_POST[$campo];
                    }
                }

                // Combinar: se mantienen los anteriores y solo sobrescriben los nuevos
                $contenidoFinal = array_merge($contenidoActual, $contenidoNuevo);

                // Convertir a JSON para guardar en BD
                $contenidoJson = json_encode($contenidoFinal, JSON_UNESCAPED_UNICODE);

                $archivo = null;

                if (!empty($_FILES['archivo']['tmp_name'])) {
                    $archivo = [
                        'data' => file_get_contents($_FILES['archivo']['tmp_name']),
                        'name' => $_FILES['archivo']['name'],
                        'type' => $_FILES['archivo']['type']
                    ];
                }

                if ($action == 'editarTareaEstudiante') {
                    global $conn;
                    $tarea = new Tarea($conn);
                    $tarea->actualizarTareasVencidos();
                    $tarea->editarTareaEstudiante($id_asignacion, $id_tarea, $id_estudiante, $contenidoJson, $archivo);
                }
            } else {
                die("El usuario no tiene permiso para crear el tarea");
            }
        } else {
            die("Los datos no fueron enviados ha acabado para registrar tarea");
        }
    }
    //investigador 
    public function editarTareaRevisar($datos, $id_usuario, $rol)
    {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            if ($rol == "investigador" || $rol == "profesor") {

                $action = $datos['action'] ?? '';

                $id_asignacion = $datos['id_asignacion'];
                $id_tareas = $datos['id_tareas'];
                $id_estudiante = $datos['id_usuario'];

                $comentarios = ($datos['comentarios'] ?? '');


                if ($action == 'RevisarTarea') {
                    global $conn;
                    $tarea = new Tarea($conn);
                    $tarea->actualizarTareasVencidos();
                    $tarea->editarTareaRevisar($id_asignacion, $id_tareas, $id_estudiante, $comentarios);
                }
            } else {
                die("El usuario no tiene permiso para crear el tarea");
            }
        } else {
            die("Los datos no fueron enviados ha acabado para registrar tareas");
        }
    }

    //Actualizar estado de proyectos sin comentarios
    public function actualizarestado($id_tarea, $rol, $tipo)
    {
        if ($_SERVER['REQUEST_METHOD'] == 'GET') {
            if ($rol == "supervisor" || $rol == "investigador" || $rol == "profesor") {
                global $conn;
                $proyecto = new Proyectos($conn);
                $proyecto->actualizarProyectosVencidos();
                $numeroEstado = $this->numerofiltro($tipo);
                $proyecto->actualizarestado($id_tarea, $numeroEstado);
            } else {
                die("El usuario no tiene permiso para crear el tarea");
            }
        } else {
            die("Los datos no fueron enviados");
        }
    }

    public function mostrarEditarTarea($id_tarea, $rol)
    {

        global $conn;

        $tareas = new Tarea($conn);

        if ($rol == "investigador" || $rol == "estudiante" || $rol == "supervisor") {
            //Revisión de estados de tarea
            $tareas->actualizarTareasVencidos();
            $json = $tareas->obtenerTareaGeneral($id_tarea);
            // Convertir a array
            $datos = json_decode($json, true);
            return $datos;
        } else {
            $datos = []; // evita undefined variable
            return $datos;
        }
    }

    public function mostrarTarea($id_asignacion, $rol)
    {

        global $conn;

        $tareas = new Tarea($conn);

        if ($rol == "investigador" || $rol == "estudiante" || $rol == "supervisor") {
            //Revisión de estados de tarea
            $tareas->actualizarTareasVencidos();
            $json = $tareas->obtenerTareaAlumno($id_asignacion);
            // Convertir a array
            $datos = json_decode($json, true);

            return $datos;
        } else {
            $datos = []; // evita undefined variable
            return $datos;
        }
    }
}
