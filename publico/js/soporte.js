// Toggle FAQ
function toggleFAQ(faqId) {
    const content = document.getElementById("content-" + faqId);
    const icon = document.getElementById("icon-" + faqId);
    
    content.classList.toggle("collapsed");
    icon.classList.toggle("rotated");
}

// Validación del formulario de contacto
document.getElementById('formContacto')?.addEventListener('submit', function(e) {
    const nombre = this.querySelector('input[name="nombre"]').value.trim();
    const correo = this.querySelector('input[name="correo"]').value.trim();
    const mensaje = this.querySelector('textarea[name="mensaje"]').value.trim();
    
    if (!nombre || !correo || !mensaje) {
        e.preventDefault();
        alert('Por favor, completa todos los campos del formulario');
        return false;
    }
    
    // Validar formato de email
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(correo)) {
        e.preventDefault();
        alert('Por favor, ingresa un correo electrónico válido');
        return false;
    }
});