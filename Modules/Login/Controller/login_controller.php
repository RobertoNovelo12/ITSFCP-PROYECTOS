<?php
require_once __DIR__ . '/../Model/login_model.php';

class LoginControlador
{
    private $modelo;
    private string $base_url = "/";

    public function __construct($conn)
    {
        $this->modelo = new LoginModelo($conn);
    }

    public function manejarSesionActiva(): void
    {
        if (isset($_SESSION['rol'])) {
            header("Location: {$this->base_url}Modules/Principal/Views/index.php");
            exit;
        }
    }

    public function procesarLogin(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['login'])) {
            return; // No hay nada que procesar
        }

        $correo    = trim($_POST['correo'] ?? '');
        $password  = $_POST['contraseña'] ?? '';

        if (empty($correo) || empty($password)) {
            $this->redirigirError("Completa todos los campos.");
        }

        $usuario = $this->modelo->buscarPorCorreo($correo);

        if (!$usuario) {
            $this->redirigirError("Correo o contraseña incorrectos.");
        }

        if (!password_verify($password, $usuario['password'])) {
            $this->redirigirError("Correo o contraseña incorrectos.");
        }

        // Todo correcto: iniciar sesión
        $_SESSION['id_usuario'] = $usuario['id_usuarios'];
        $_SESSION['nombre']     = $usuario['nombre'];
        $_SESSION['rol']        = $usuario['rol'];

        header("Location: {$this->base_url}Modules/Principal/Views/index.php");
        exit;
    }

    private function redirigirError(string $mensaje): void
    {
        header("Location: {$this->base_url}Modules/Login/Views/index.php?error=" . urlencode($mensaje));
        exit;
    }
}