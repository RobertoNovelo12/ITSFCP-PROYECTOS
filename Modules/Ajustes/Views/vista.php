<?php
$usuario         = $datos['usuario'];
$config          = $datos['config'];
$inicial         = $datos['inicial'];
$nombre_completo = $datos['nombre_completo'];
$fecha_formateada= $datos['fecha_formateada'];
?>

<div class="container-ajustes py-4">
    <div id="alerta-ajustes" class="alert d-none mb-3"></div>

    <div class="row">
        <!-- Columna izquierda: Perfil -->
        <div class="col-lg-5">
            <div class="perfil-card">
                <div class="perfil-header">
                    <div class="avatar-container">
                        <div class="avatar"><?= $inicial ?></div>
                    </div>
                    <div class="perfil-info">
                        <h2 class="perfil-nombre"><?= htmlspecialchars($nombre_completo) ?></h2>
                        <p class="perfil-email"><?= htmlspecialchars($usuario['correo_institucional']) ?></p>
                    </div>
                </div>

                <div class="perfil-detalles">
                    <div class="detalle-item">
                        <i class="bi bi-calendar"></i>
                        <span>Nací el <?= $fecha_formateada ?></span>
                    </div>
                    <div class="detalle-item">
                        <i class="bi bi-mortarboard"></i>
                        <span><?= htmlspecialchars($config['institucion_academica']) ?></span>
                    </div>
                </div>

                <div class="perfil-section">
                    <h3 class="section-title"><i class="bi bi-person"></i> Perfil</h3>

                    <div class="perfil-opciones">

                        <!-- Cambiar contraseña -->
                        <div class="opcion-grupo">
                            <button class="opcion-btn toggle-campo" data-target="campo-password">
                                <i class="bi bi-key"></i><span>Cambiar contraseña</span>
                            </button>
                            <div class="campo-editable d-none" id="campo-password">
                                <input type="password" class="form-control mb-1" name="password" placeholder="Nueva contraseña">
                                <input type="password" class="form-control" name="password_confirm" placeholder="Confirmar contraseña">
                            </div>
                        </div>

                        <!-- Cambiar nombre -->
                        <div class="opcion-grupo">
                            <button class="opcion-btn toggle-campo" data-target="campo-nombre">
                                <i class="bi bi-person"></i><span>Cambiar nombre</span>
                            </button>
                            <div class="campo-editable d-none" id="campo-nombre">
                                <input type="text" class="form-control" name="nombre"
                                       placeholder="Nuevo nombre"
                                       value="<?= htmlspecialchars($usuario['nombre']) ?>">
                            </div>
                        </div>

                        <!-- Cambiar localidad -->
                        <div class="opcion-grupo">
                            <button class="opcion-btn toggle-campo" data-target="campo-localidad">
                                <i class="bi bi-geo-alt"></i><span>Cambiar localidad</span>
                            </button>
                            <div class="campo-editable d-none" id="campo-localidad">
                                <input type="text" class="form-control" name="localidad"
                                       placeholder="Localidad"
                                       value="<?= htmlspecialchars($config['localidad']) ?>">
                            </div>
                        </div>

                        <!-- Cambiar fecha de nacimiento -->
                        <div class="opcion-grupo">
                            <button class="opcion-btn toggle-campo" data-target="campo-fecha">
                                <i class="bi bi-calendar"></i><span>Cambiar fecha de nacimiento</span>
                            </button>
                            <div class="campo-editable d-none" id="campo-fecha">
                                <input type="date" class="form-control" name="fecha_nacimiento"
                                       value="<?= htmlspecialchars($usuario['fecha_nacimiento'] ?? '') ?>">
                            </div>
                        </div>

                        <!-- Institución académica -->
                        <div class="opcion-grupo">
                            <button class="opcion-btn toggle-campo" data-target="campo-institucion">
                                <i class="bi bi-mortarboard"></i><span>Agregar institución académica</span>
                            </button>
                            <div class="campo-editable d-none" id="campo-institucion">
                                <input type="text" class="form-control" name="institucion_academica"
                                       placeholder="Institución"
                                       value="<?= htmlspecialchars($config['institucion_academica']) ?>">
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="config-card">

                <div class="config-section">
                    <h3 class="section-title"><i class="bi bi-bell"></i> Notificaciones</h3>
                    <div class="config-opciones">
                        <label class="config-label">
                            <input type="checkbox" class="config-checkbox" name="notif_todas" <?= $config['notif_todas'] ? 'checked' : '' ?>>
                            Recibir todas las notificaciones
                        </label>
                        <label class="config-label">
                            <input type="checkbox" class="config-checkbox" name="notif_tareas_nuevas" <?= $config['notif_tareas_nuevas'] ? 'checked' : '' ?>>
                            Recibir notificaciones de tareas nuevas
                        </label>
                        <label class="config-label">
                            <input type="checkbox" class="config-checkbox" name="notif_tareas_atrasadas" <?= $config['notif_tareas_atrasadas'] ? 'checked' : '' ?>>
                            Recibir notificaciones de tareas atrasadas
                        </label>
                        <label class="config-label">
                            <input type="checkbox" class="config-checkbox" name="notif_modificaciones_proyecto" <?= $config['notif_modificaciones_proyecto'] ? 'checked' : '' ?>>
                            Recibir notificaciones de modificaciones al proyecto
                        </label>
                        <label class="config-label">
                            <input type="checkbox" class="config-checkbox" name="notif_admin_proyecto" <?= $config['notif_admin_proyecto'] ? 'checked' : '' ?>>
                            Recibir notificaciones del administrador del proyecto
                        </label>
                    </div>
                </div>

                <div class="config-section">
                    <h3 class="section-title"><i class="bi bi-shield-check"></i> Privacidad</h3>
                    <div class="config-opciones">
                        <label class="config-label">
                            <input type="checkbox" class="config-checkbox" name="priv_ver_tareas" <?= $config['priv_ver_tareas'] ? 'checked' : '' ?>>
                            Los demás usuarios pueden ver mis tareas
                        </label>
                        <label class="config-label">
                            <input type="checkbox" class="config-checkbox" name="priv_ver_proyectos" <?= $config['priv_ver_proyectos'] ? 'checked' : '' ?>>
                            Los demás usuarios pueden ver mis proyectos
                        </label>
                        <label class="config-label">
                            <input type="checkbox" class="config-checkbox" name="priv_ver_datos" <?= $config['priv_ver_datos'] ? 'checked' : '' ?>>
                            Los demás usuarios pueden ver mis datos
                        </label>
                    </div>
                </div>

            </div>
        </div>
    </div>
    <div class="row mt-4">
        <div class="col-12 text-end">
            <button class="btn-confirmar" id="btn-guardar-todo">
                <i class="bi bi-check-lg"></i> Guardar cambios
            </button>
        </div>
    </div>

</div>

<!-- Modal de confirmación -->
<div class="modal fade" id="modalConfirmar" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirmar cambios</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                ¿Estás seguro de que deseas guardar todos los cambios?
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btn-confirmar-ok">Sí, guardar</button>
            </div>
        </div>
    </div>
</div>

<script>
// Toggle campos de perfil
document.querySelectorAll('.toggle-campo').forEach(btn => {
    btn.addEventListener('click', () => {
        const target = document.getElementById(btn.dataset.target);
        target.classList.toggle('d-none');
    });
});

const modalConfirmar = new bootstrap.Modal(document.getElementById('modalConfirmar'));

// Botón único → abre modal
document.getElementById('btn-guardar-todo').addEventListener('click', () => {
    modalConfirmar.show();
});

// Al confirmar → guarda perfil y config en paralelo
document.getElementById('btn-confirmar-ok').addEventListener('click', () => {
    modalConfirmar.hide();

    // FormData perfil (campos visibles)
    const formPerfil = new FormData();
    document.querySelectorAll('.campo-editable:not(.d-none) input').forEach(input => {
        formPerfil.append(input.name, input.value);
    });

    // FormData config (checkboxes)
    const formConfig = new FormData();
    document.querySelectorAll('.config-checkbox').forEach(cb => {
        if (cb.checked) formConfig.append(cb.name, '1');
    });

    // Enviar ambos en paralelo
    Promise.all([
        fetch('/Ajax/ajustes_perfil.php', { method: 'POST', body: formPerfil }).then(r => r.json()),
        fetch('/Ajax/ajustes_config.php', { method: 'POST', body: formConfig }).then(r => r.json())
    ]).then(([resPerfil, resConfig]) => {
        // Si ambos son ok
        if (resPerfil.ok && resConfig.ok) {
            mostrarAlerta('Cambios guardados correctamente.', true);
        } else {
            // Mostrar el error que haya
            const msg = (!resPerfil.ok ? resPerfil.msg : '') + ' ' + (!resConfig.ok ? resConfig.msg : '');
            mostrarAlerta(msg.trim(), false);
        }
    });
});

function mostrarAlerta(msg, ok) {
    const el = document.getElementById('alerta-ajustes');
    el.textContent = msg;
    el.className = 'alert ' + (ok ? 'alert-success' : 'alert-danger');
    el.classList.remove('d-none');
    setTimeout(() => el.classList.add('d-none'), 3000);
}
</script>