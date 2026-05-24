<?php
/**
 * _paginacion.php — Partial universal de paginación
 * 
 * Variables requeridas:
 *   array  $paginacion     → claves: pagina, total_paginas, por_pagina, total
 *   string $qBase          → query string base SIN 'pagina' (ej: "action=lista&buscar=foo")
 *
 * Variables opcionales:
 *   string $entidad        → texto del contador. Default: 'registros'
 *   string $clave_pagina   → nombre del parámetro de página. Default: 'pagina'
 *   bool   $sm             → true para pagination-sm. Default: false
 *
 * Uso mínimo:
 *   <?php include __DIR__ . '../../../publico/incluido/_paginacion.php'?>
 *
 * Uso con opciones:
 *   <?php
 *   $entidad      = 'proyectos';
 *   $clave_pagina = 'pagina_proy';
 *   $sm           = true;
 *   include __DIR__ . '../../../publico/incluido/_paginacion.php';
 *   ?>
 */

// Salida temprana si no hay suficientes páginas
if (empty($paginacion) || ($paginacion['total_paginas'] ?? 1) <= 1) return;

// Parámetros con defaults
$_p_pagina  = (int) ($paginacion['pagina']        ?? 1);
$_p_total   = (int) ($paginacion['total_paginas'] ?? 1);
$_p_por_pag = (int) ($paginacion['por_pagina']    ?? 10);
$_p_total_r = (int) ($paginacion['total']         ?? 0);
$_p_entidad = isset($entidad)      ? htmlspecialchars($entidad)      : 'registros';
$_p_clave   = isset($clave_pagina) ? htmlspecialchars($clave_pagina) : 'pagina';
$_p_sm      = !empty($sm) ? ' pagination-sm' : '';
$_p_qBase   = isset($qBase) ? rtrim($qBase, '&') : '';
$_p_sep     = $_p_qBase !== '' ? $_p_qBase . '&' : '';

// Ventana de páginas: siempre 1ª, última y ±2 alrededor de la actual
$_p_rango   = 2;
$_p_paginas = [];
for ($i = 1; $i <= $_p_total; $i++) {
    if (
        $i === 1 || $i === $_p_total
        || ($i >= $_p_pagina - $_p_rango && $i <= $_p_pagina + $_p_rango)
    ) {
        $_p_paginas[] = $i;
    }
}

// Contador de registros
$_p_desde = ($_p_pagina - 1) * $_p_por_pag + 1;
$_p_hasta = min($_p_pagina * $_p_por_pag, $_p_total_r);
?>

<div class="paginacion-wrap">
    <ul class="pagination justify-content-center mb-1<?= $_p_sm ?>">

        <!-- Anterior -->
        <li class="page-item <?= $_p_pagina <= 1 ? 'disabled' : '' ?>">
            <a class="page-link"
               href="?<?= $_p_sep . $_p_clave ?>=<?= $_p_pagina - 1 ?>"
               <?= $_p_pagina <= 1 ? 'tabindex="-1" aria-disabled="true"' : '' ?>>
                &laquo;
            </a>
        </li>

        <?php
        $_p_prev = null;
        foreach ($_p_paginas as $_p_i):
            // Elipsis cuando hay salto entre páginas
            if ($_p_prev !== null && $_p_i - $_p_prev > 1): ?>
                <li class="page-item disabled" aria-hidden="true">
                    <span class="page-link">…</span>
                </li>
            <?php endif; ?>

            <li class="page-item <?= $_p_i === $_p_pagina ? 'active' : '' ?>"
                <?= $_p_i === $_p_pagina ? 'aria-current="page"' : '' ?>>
                <a class="page-link"
                   href="?<?= $_p_sep . $_p_clave ?>=<?= $_p_i ?>">
                    <?= $_p_i ?>
                </a>
            </li>

        <?php $_p_prev = $_p_i; endforeach; ?>

        <!-- Siguiente -->
        <li class="page-item <?= $_p_pagina >= $_p_total ? 'disabled' : '' ?>">
            <a class="page-link"
               href="?<?= $_p_sep . $_p_clave ?>=<?= $_p_pagina + 1 ?>"
               <?= $_p_pagina >= $_p_total ? 'tabindex="-1" aria-disabled="true"' : '' ?>>
                &raquo;
            </a>
        </li>

    </ul>

    <p class="paginacion-info">
        Mostrando <?= number_format($_p_desde) ?>–<?= number_format($_p_hasta) ?>
        de <?= number_format($_p_total_r) ?> <?= $_p_entidad ?>
    </p>
</div>