// JAVASCRIPT DE LA MODAL DE INICIO DE SESIÓN Y DE REGISTRO
function abrirModal(modalId) {
    const modal = document.getElementById(modalId);
    const container = modal.querySelector('.modal-container');
    
    // Calcula el ancho de la barra de scroll
    const scrollbarWidth = window.innerWidth - document.documentElement.clientWidth;
    
    // Resetea los estilos inline antes de abrir
    container.style.opacity = '';
    container.style.transform = '';
    
    // Agrega padding para compensar la barra de scroll
    if (scrollbarWidth > 0) {
        document.body.style.paddingRight = scrollbarWidth + 'px';
        document.querySelector('.header').style.paddingRight = (40 + scrollbarWidth) + 'px';
    }
    
    modal.classList.add('active');
    document.body.classList.add('modal-open');
}

function cerrarModal(modalId) {
    const modal = document.getElementById(modalId);
    const container = modal.querySelector('.modal-container');
    
    // Resetea los estilos inline al cerrar
    container.style.opacity = '';
    container.style.transform = '';
    
    modal.classList.remove('active');
    document.body.classList.remove('modal-open');
    
    // Remueve el padding después de la transición
    setTimeout(() => {
        document.body.style.paddingRight = '';
        document.querySelector('.header').style.paddingRight = '';
    }, 300);
}

function cerrarModalFondo(event, modalId) {
    // Solo cierra si se hace clic en el fondo oscuro, no en el contenido
    if (event.target.classList.contains('modal-overlay')) {
        event.preventDefault();
        cerrarModal(modalId);
    }
}

function cambiarModal(modalActual, modalNuevo) {
    const modalActualElement = document.getElementById(modalActual);
    const modalNuevoElement = document.getElementById(modalNuevo);
    
    // Oculta el contenedor actual
    modalActualElement.querySelector('.modal-container').style.opacity = '0';
    modalActualElement.querySelector('.modal-container').style.transform = 'translateY(30px)';
    
    setTimeout(() => {
        // Cierra el modal actual sin quitar el padding
        modalActualElement.classList.remove('active');
        
        // Abre el nuevo modal inmediatamente
        modalNuevoElement.classList.add('active');
        
        // Resetea la animación del nuevo modal
        setTimeout(() => {
            modalNuevoElement.querySelector('.modal-container').style.opacity = '1';
            modalNuevoElement.querySelector('.modal-container').style.transform = 'translateY(0)';
        }, 50);
    }, 200);
}

// Cerrar modal con tecla ESC
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        cerrarModal('loginModal');
        cerrarModal('registroModal');
    }
});