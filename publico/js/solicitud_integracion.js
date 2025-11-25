// Manejo del nombre de archivo
document.getElementById('documento')?.addEventListener('change', function(e) {
    const fileName = e.target.files[0]?.name || 'No file selected';
    const fileLabel = this.parentElement.querySelector('.file-name');
    const fileInfo = this.parentElement.querySelector('small');
    
    if (e.target.files[0]) {
        fileLabel.textContent = fileName;
        fileInfo.textContent = `Archivo seleccionado: ${fileName}`;
        fileInfo.classList.remove('text-muted');
        fileInfo.classList.add('text-success');
    } else {
        fileLabel.textContent = 'Choose file';
        fileInfo.textContent = 'No file selected';
        fileInfo.classList.remove('text-success');
        fileInfo.classList.add('text-muted');
    }
});

// Validación del formulario
document.getElementById('formSolicitud')?.addEventListener('submit', function(e) {
    e.preventDefault();
    
    const confirmacion = document.getElementById('confirmacion');
    
    if (!confirmacion.checked) {
        alert('Debes confirmar que la información proporcionada es verídica');
        return false;
    }
    
    // AFALTA AGRAGEAR VALIDACIONES ADICIONALES
    
    // Enviar formulario
    this.submit();
});