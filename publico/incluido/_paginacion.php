<?php
/**
 * incluido/_paginacion.php — Código universal de paginación
 *
 * Variables requeridas:
 *   array  $paginacion     → claves: pagina, total_paginas, por_pagina, total
 *   string $qBase          → query string base SIN 'pagina' (ej: "action=lista&buscar=foo")
 *
 * Variables opcionales:
 *   string $entidad        → texto del contador. Default: 'registros'
 *   string $clave_pagina   → nombre del parámetro de página. Default: 'pagina'
 *   bool   $sm             → true para pagination-sm. Default: false
 */

//  Salida temprana 
// Si $paginacion está vacío o solo hay 1 página, no hay nada que paginar:
// el código no imprime nada y devuelve el control al archivo que lo incluyó.
if (empty($paginacion) || ($paginacion['total_paginas'] ?? 1) <= 1) return;


//  Lectura de $paginacion 
// Se copian los valores del array a variables locales con prefijo $_p_ para
// no pisar variables del scope del archivo que llama a este código.
// El ?? es un fallback por si alguna clave no viene definida; en condiciones
// normales $paginacion siempre las trae todas desde el controlador.
$_p_pagina  = (int) ($paginacion['pagina']        ?? 1);   // página actual
$_p_total   = (int) ($paginacion['total_paginas'] ?? 1);   // total de páginas
$_p_por_pag = (int) ($paginacion['por_pagina']    ?? 15);  // registros por página
                                                            // ↑ SOLO afecta el contador
                                                            //   "Mostrando X–Y", NO la
                                                            //   consulta SQL
$_p_total_r = (int) ($paginacion['total']         ?? 0);   // total de registros


//  Variables opcionales ─
// Se respetan si el archivo llamador las definió; si no, se usan defaults.
$_p_entidad = isset($entidad)      ? htmlspecialchars($entidad)      : 'registros';
$_p_clave   = isset($clave_pagina) ? htmlspecialchars($clave_pagina) : 'pagina';
$_p_sm      = !empty($sm) ? ' pagination-sm' : '';  // clase Bootstrap para tamaño pequeño


//  Query string base ─
// $qBase viene del archivo llamador con los filtros activos, ej:
//   "action=lista&buscar=foo&estado=1"
// Se limpia el & final por si acaso, y se arma $_p_sep que es lo que va
// antes del parámetro de página en cada href.
//   Con qBase:    "action=lista&buscar=foo&pagina=3"
//   Sin qBase:    "pagina=3"
$_p_qBase = isset($qBase) ? rtrim($qBase, '&') : '';
$_p_sep   = $_p_qBase !== '' ? $_p_qBase . '&' : '';


//  Ventana de páginas 
// En lugar de mostrar TODOS los botones (puede haber cientos de páginas),
// se construye un subconjunto inteligente que siempre incluye:
//   • La primera página (1)
//   • La última página ($_p_total)
//   • Las $_p_rango páginas anteriores y posteriores a la actual
// Las páginas que queden fuera de esos grupos aparecerán como "…" (elipsis).
$_p_rango   = 2;
$_p_paginas = [];

for ($i = 1; $i <= $_p_total; $i++) {
    if (
        $i === 1             // siempre incluir la primera
        || $i === $_p_total  // siempre incluir la última
        || ($i >= $_p_pagina - $_p_rango && $i <= $_p_pagina + $_p_rango) // ventana central
    ) {
        $_p_paginas[] = $i;
    }
}
// Ejemplo en página 7 de 20: $_p_paginas = [1, 5, 6, 7, 8, 9, 20]
// Se renderizará:  1 … 5 6 [7] 8 9 … 20


//  Contador "Mostrando X–Y de Z" 
// Cálculo puramente aritmético basado en $paginacion, no toca la BD.
// Si estoy en página 3 con 15 por página: desde=31, hasta=45 (o el total si es la última).
$_p_desde = ($_p_pagina - 1) * $_p_por_pag + 1;
$_p_hasta = min($_p_pagina * $_p_por_pag, $_p_total_r);
?>


<div class="paginacion-wrap">
    <ul class="pagination justify-content-center mb-1<?= $_p_sm ?>">

        <!-- Botón Anterior: deshabilitado si ya estamos en la página 1 -->
        <li class="page-item <?= $_p_pagina <= 1 ? 'disabled' : '' ?>">
            <a class="page-link"
               href="?<?= $_p_sep . $_p_clave ?>=<?= $_p_pagina - 1 ?>"
               <?= $_p_pagina <= 1 ? 'tabindex="-1" aria-disabled="true"' : '' ?>>
                &laquo;
            </a>
        </li>

        <?php
        $_p_prev = null; // guarda el número de la página anterior del loop para detectar saltos
        foreach ($_p_paginas as $_p_i):

            // Si hay un salto entre la página anterior del loop y la actual
            // (ej: pasamos de 1 a 5), se inserta el "…"
            if ($_p_prev !== null && $_p_i - $_p_prev > 1): ?>
                <li class="page-item disabled" aria-hidden="true">
                    <span class="page-link">…</span>
                </li>
            <?php endif; ?>

            <!-- Botón de página: marcado como active si es la página actual -->
            <li class="page-item <?= $_p_i === $_p_pagina ? 'active' : '' ?>"
                <?= $_p_i === $_p_pagina ? 'aria-current="page"' : '' ?>>
                <a class="page-link"
                   href="?<?= $_p_sep . $_p_clave ?>=<?= $_p_i ?>">
                    <?= $_p_i ?>
                </a>
            </li>

        <?php $_p_prev = $_p_i; endforeach; ?>

        <!-- Botón Siguiente: deshabilitado si ya estamos en la última página -->
        <li class="page-item <?= $_p_pagina >= $_p_total ? 'disabled' : '' ?>">
            <a class="page-link"
               href="?<?= $_p_sep . $_p_clave ?>=<?= $_p_pagina + 1 ?>"
               <?= $_p_pagina >= $_p_total ? 'tabindex="-1" aria-disabled="true"' : '' ?>>
                &raquo;
            </a>
        </li>

    </ul>

    <!-- Contador de registros: solo visual, calculado arriba -->
    <p class="paginacion-info">
        Mostrando <?= number_format($_p_desde) ?>–<?= number_format($_p_hasta) ?>
        de <?= number_format($_p_total_r) ?> <?= $_p_entidad ?>
    </p>
</div>