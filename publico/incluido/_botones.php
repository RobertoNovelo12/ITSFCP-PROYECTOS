<?php
// publico/incluido/_botones.php

class Botones
{
    /**
     * 
     * BOTÓN SOLO CON ICONO
     * 
     * Ideal para tablas y acciones rápidas.
     *
     * Ejemplo:
     * Botones::botonIcono(
     *     'detalles.php?id=1',
     *     'primary',
     *     $iconos['tabla']['ver'],
     *     'Ver detalles'
     * );
     */
    public static function botonIcono(
        string $url,
        string $color,
        string $icono,
        string $tooltip,
        string $tamano = 'sm'
    ): string {
        return '
        <a href="' . htmlspecialchars($url) . '"
           class="btn btn-' . htmlspecialchars($color) . ' btn-' . htmlspecialchars($tamano) . '"
           data-bs-toggle="tooltip"
           data-bs-placement="top"
           data-bs-custom-class="custom-tooltip"
           data-bs-title="' . htmlspecialchars($tooltip) . '">
            <i class="' . htmlspecialchars($icono) . '"></i>
        </a>';
    }

    /**
     * 
     * BOTÓN CON ICONO Y TEXTO
     * 
     * Ideal para formularios y acciones principales.
     *
     * Ejemplo:
     * Botones::botonTexto(
     *     'crear.php',
     *     'success',
     *     $iconos['tabla']['subir'],
     *     'Crear registro'
     * );
     */
    public static function botonTexto(
        string $url,
        string $color,
        string $icono,
        string $texto,
        string $tooltip = '',
        string $tamano = 'sm'
    ): string {

        $tooltipAttr = '';

        if ($tooltip !== '') {
            $tooltipAttr = '
                data-bs-toggle="tooltip"
                data-bs-placement="top"
                data-bs-custom-class="custom-tooltip"
                data-bs-title="' . htmlspecialchars($tooltip) . '"';
        }

        return '
        <a href="' . htmlspecialchars($url) . '"
           class="btn btn-' . htmlspecialchars($color) . ' btn-' . htmlspecialchars($tamano) . '"
           ' . $tooltipAttr . '>
            <i class="' . htmlspecialchars($icono) . ' me-2"></i>'
            . htmlspecialchars($texto) .
            '</a>';
    }

    /**
     * 
     * BOTÓN CON CONFIRMACIÓN
     * 
     * Para eliminar, desactivar, cancelar, etc.
     *
     * Ejemplo:
     * Botones::botonConfirmacion(
     *     'eliminar.php?id=1',
     *     'danger',
     *     $iconos['tabla']['eliminar'],
     *     'Eliminar',
     *     '¿Desea eliminar este registro?'
     * );
     */
    public static function botonConfirmacion(
        string $url,
        string $color,
        string $icono,
        string $tooltip,
        string $mensajeConfirmacion,
        string $tamano = 'sm'
    ): string {

        return '
        <a href="' . htmlspecialchars($url) . '"
           class="btn btn-' . htmlspecialchars($color) . ' btn-' . htmlspecialchars($tamano) . '"
           data-bs-toggle="tooltip"
           data-bs-placement="top"
           data-bs-custom-class="custom-tooltip"
           data-bs-title="' . htmlspecialchars($tooltip) . '"
           onclick="return confirm(\'' . htmlspecialchars($mensajeConfirmacion, ENT_QUOTES) . '\');">
            <i class="' . htmlspecialchars($icono) . '"></i>
        </a>';
    }

    /**
     * 
     * BOTÓN CON ATRIBUTOS DATA-*
     * 
     * Para AJAX, modales, eventos JS, etc.
     *
     * Ejemplo:
     * Botones::botonData(
     *     'danger',
     *     $iconos['tabla']['eliminar'],
     *     'Dar de baja',
     *     [
     *         'accion' => 'baja',
     *         'id' => 15
     *     ]
     * );
     */
    public static function botonData(
        string $color,
        string $icono,
        string $tooltip,
        array $data = [],
        string $tamano = 'sm',
        string $texto = ''
    ): string {

        $atributos = '';

        foreach ($data as $clave => $valor) {
            $atributos .= ' data-' . htmlspecialchars($clave) .
                '="' . htmlspecialchars((string)$valor) . '"';
        }

        return '
    <button type="submit"
            name="action"
            value="' . htmlspecialchars($texto) . '"
            class="btn btn-' . htmlspecialchars($color) . ' btn-' . htmlspecialchars($tamano) . '"
            data-bs-toggle="tooltip"
            data-bs-placement="top"
            data-bs-custom-class="custom-tooltip"
            data-bs-title="' . htmlspecialchars($tooltip) . '"'
            . $atributos . '>
        <i class="' . htmlspecialchars($icono) . '"></i>'
            . ($texto !== '' ? '<span class="ms-2">' . htmlspecialchars($texto) . '</span>' : '') . '
    </button>';
    }
}
