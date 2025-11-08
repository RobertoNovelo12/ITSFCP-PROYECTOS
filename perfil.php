<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear Perfil</title>
    <link rel="stylesheet" href="./publico/css/styles.css">
</head>
<body class="body-register">
    <div class="container-perfil">
        <h1 class="title-perfil">¡Vamos a crear un perfil!</h1>

        <div class="avatar-section">
            <div class="avatar-container">
                <div class="avatar" id="avatar-letter"></div>
                <label for="avatar-upload" class="edit-avatar-btn">
                    <img src="./publico/icons/ri_pencil-line.webp" alt="">
                </label>
                <input type="file" id="avatar-upload" accept="image/*,capture=camera">
            </div>
        </div>

        <form class="form-perfil">
            <div class="input-group">
                <input type="text" id="username" class="input-field" placeholder=" " required>
                <label for="username" class="floating-label">Nombre de usuario</label>
            </div>

            <div class="role-section">
                <label class="role-label">¿Qué tipo de usuario eres?</label>
                <select class="role-select" id="user-role" required>
                    <option value="alumno">Alumno</option>
                    <option value="profesor">Profesor</option>
                </select>
            </div>

            <div class="input-group">
                <input type="text" id="institucion" class="input-field" placeholder=" " required>
                <label for="institucion" class="floating-label">Institución</label>
            </div>

            <button type="submit" class="submit-btn">Confirmar</button>
        </form>
    </div>

    <script>
        // Abrir explorador de archivos o cámara al hacer clic en el boton de editar
        const avatarUpload = document.getElementById('avatar-upload');
        
        avatarUpload.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const avatarDiv = document.getElementById('avatar-letter');
                    avatarDiv.style.backgroundImage = `url(${e.target.result})`;
                    avatarDiv.style.backgroundSize = 'cover';
                    avatarDiv.style.backgroundPosition = 'center';
                    avatarDiv.textContent = ''; // Limpiar la letra si había
                };
                reader.readAsDataURL(file);
            }
        });
    </script>
</body>
</html>