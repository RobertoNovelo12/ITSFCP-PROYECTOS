document.addEventListener("DOMContentLoaded", function() {
    const editorContainer = document.getElementById("editorDescripcion");
    
    if (editorContainer && typeof Quill !== 'undefined') {
        const quill = new Quill("#editorDescripcion", {
            theme: "snow",
            placeholder: "Escribe la descripción del evento...",
            modules: {
                toolbar: [
                    ["bold", "italic", "underline"],
                    [{ "list": "ordered"}, { "list": "bullet" }],
                    ["link"]
                ]
            }
        });

        // Sincronizar contenido con el campo oculto antes de enviar
        const form = document.getElementById("formEvento");
        if (form) {
            form.addEventListener("submit", function(e) {
                document.getElementById("descripcion").value = quill.root.innerHTML;
            });
        }
    }
});