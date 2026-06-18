<?php

/**
 * view_helpers.php
 *
 * Funciones de ayuda reutilizables para la capa de la Vista.
 * Este archivo puede ser incluido en layout.php para que las funciones
 * estén disponibles globalmente en todas las vistas.
 */

/**
 * Genera un badge de Bootstrap 5 según el estado proporcionado.
 */
function badgeEstado(string $estado): string
{
    $map = [
        'pendiente'              => ['secondary',         'Pendiente'],
        'proceso'                => ['primary',           'En proceso'],
        'completado'             => ['success',           'Completado'],
        'rechazado'              => ['danger',            'Rechazado'],
        'correcciones'           => ['warning text-dark', 'Correcciones'],
        'en_revision'            => ['info text-dark',    'En revisión'],
        'aceptado'               => ['success',           'Aceptado'],
        'finalizacion_pendiente' => ['warning text-dark', 'Terminación pendiente de validación'],
    ];
    [$color, $texto] = $map[$estado] ?? ['secondary', ucfirst($estado)];
    return "<span class='badge bg-{$color}'>{$texto}</span>";
}