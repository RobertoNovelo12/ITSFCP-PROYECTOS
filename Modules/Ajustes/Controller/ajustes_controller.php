<?php
require_once __DIR__ . '/../Model/ajustes_model.php';

class AjustesControlador
{
    private $modelo;
    private int $id_usuario;

    private array $defaults = [
        'localidad'                      => '',
        'fecha_nacimiento'               => null,
        'institucion_academica'          => '',
        'notif_todas'                    => 0,
        'notif_tareas_nuevas'            => 0,
        'notif_tareas_atrasadas'         => 0,
        'notif_modificaciones_proyecto'  => 0,
        'notif_admin_proyecto'           => 0,
        'priv_ver_tareas'                => 0,
        'priv_ver_proyectos'             => 0,
        'priv_ver_datos'                 => 0,
    ];

    public function __construct($conn, int $id_usuario)
    {
        $this->modelo     = new AjustesModelo($conn);
        $this->id_usuario = $id_usuario;
    }

    public function getDatos(): array
    {
        $usuario = $this->modelo->getUsuario($this->id_usuario);
        $config  = array_merge($this->defaults, $this->modelo->getConfig($this->id_usuario));

        $meses = ['','enero','febrero','marzo','abril','mayo','junio',
                  'julio','agosto','septiembre','octubre','noviembre','diciembre'];

        $fecha_raw = $config['fecha_nacimiento'] ?? $usuario['fecha_nacimiento'];
        $fecha_obj = $fecha_raw ? new DateTime($fecha_raw) : null;
        $fecha_formateada = $fecha_obj
            ? $fecha_obj->format('d') . ' de ' . $meses[(int)$fecha_obj->format('m')] . ' de ' . $fecha_obj->format('Y')
            : 'No registrada';

        return [
            'usuario'          => $usuario,
            'config'           => $config,
            'inicial'          => strtoupper(substr($usuario['nombre'], 0, 1)),
            'nombre_completo'  => trim($usuario['nombre'] . ' ' . $usuario['apellido_paterno'] . ' ' . $usuario['apellido_materno']),
            'fecha_formateada' => $fecha_formateada,
        ];
    }

    public function actualizarPerfil(array $post): array
    {
        $campos = [];

        if (!empty($post['nombre']))
            $campos['nombre'] = trim($post['nombre']);

        if (!empty($post['password']) && !empty($post['password_confirm'])) {
            if ($post['password'] !== $post['password_confirm'])
                return ['ok' => false, 'msg' => 'Las contraseñas no coinciden.'];
            if (strlen($post['password']) < 6)
                return ['ok' => false, 'msg' => 'La contraseña debe tener al menos 6 caracteres.'];
            $campos['password'] = $post['password'];
        }

        if (!empty($post['fecha_nacimiento']))
            $campos['fecha_nacimiento'] = $post['fecha_nacimiento'];

        if (empty($campos))
            return ['ok' => false, 'msg' => 'No hay cambios que guardar.'];

        $ok = $this->modelo->actualizarPerfil($this->id_usuario, $campos);
        return $ok
            ? ['ok' => true,  'msg' => 'Perfil actualizado correctamente.']
            : ['ok' => false, 'msg' => 'Error al actualizar el perfil.'];
    }

    public function guardarConfig(array $post): array
    {
        $datos = [
            'localidad'                     => trim($post['localidad'] ?? ''),
            'institucion_academica'         => trim($post['institucion_academica'] ?? ''),
            'notif_todas'                   => isset($post['notif_todas']) ? 1 : 0,
            'notif_tareas_nuevas'           => isset($post['notif_tareas_nuevas']) ? 1 : 0,
            'notif_tareas_atrasadas'        => isset($post['notif_tareas_atrasadas']) ? 1 : 0,
            'notif_modificaciones_proyecto' => isset($post['notif_modificaciones_proyecto']) ? 1 : 0,
            'notif_admin_proyecto'          => isset($post['notif_admin_proyecto']) ? 1 : 0,
            'priv_ver_tareas'               => isset($post['priv_ver_tareas']) ? 1 : 0,
            'priv_ver_proyectos'            => isset($post['priv_ver_proyectos']) ? 1 : 0,
            'priv_ver_datos'                => isset($post['priv_ver_datos']) ? 1 : 0,
        ];

        $ok = $this->modelo->guardarConfig($this->id_usuario, $datos);
        return $ok
            ? ['ok' => true,  'msg' => 'Configuración guardada correctamente.']
            : ['ok' => false, 'msg' => 'Error al guardar la configuración.'];
    }
}