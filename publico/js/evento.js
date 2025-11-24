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

document.addEventListener("DOMContentLoaded", () => {

    const proyectoSelect = document.getElementById("proyecto");
    const listaEstudiantes = document.getElementById("listaEstudiantes");

    if (!proyectoSelect || !listaEstudiantes) return;

    proyectoSelect.addEventListener("change", function () {
        const idProyecto = this.value;

        if (!idProyecto) {
            listaEstudiantes.innerHTML = "<p class='small text-muted'>Seleccione un proyecto...</p>";
            return;
        }

        fetch(`?getEstudiantes=1&id_proyecto=${idProyecto}`)
            .then(res => res.json())
            .then(estudiantes => {

                if (!estudiantes.length) {
                    listaEstudiantes.innerHTML = "<p class='text-muted small'>No hay estudiantes en este proyecto.</p>";
                    return;
                }

                let html = "";

                estudiantes.forEach(est => {
                    html += `
                        <label class="inv-item">
                            <input type="checkbox" name="invitados[]" value="${est.id_usuarios}">
                            ${est.nombre} ${est.apellido}
                        </label>
                    `;
                });

                listaEstudiantes.innerHTML = html;
            });
    });
});
