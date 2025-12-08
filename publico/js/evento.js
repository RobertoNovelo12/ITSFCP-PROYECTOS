document.addEventListener("DOMContentLoaded", function() {

    // =====================================================
    // ABRIR / CERRAR MODAL
    // =====================================================
    const modal = document.getElementById("modalCrearEvento");
    const btnOpen = document.getElementById("btnAbrirCrearEvento");
    const btnClose = document.getElementById("cerrarCrearEvento");

    btnOpen.addEventListener("click", () => {
        modal.style.display = "flex";
        cargarProyectos();
    });

    btnClose.addEventListener("click", () => {
        modal.style.display = "none";
    });

    window.addEventListener("click", e => {
        if (e.target === modal) modal.style.display = "none";
    });


    // =====================================================
    // QUILL
    // =====================================================
    let quillModal = new Quill("#editorDescripcionModal", {
        theme: "snow",
        placeholder: "Escribe la descripción del evento...",
        modules: {
            toolbar: [
                ["bold", "italic", "underline"],
                [{ list: "ordered" }, { list: "bullet" }],
                ["link"]
            ]
        }
    });


    // =====================================================
    // CARGAR PROYECTOS (AJAX)
    // =====================================================
    function cargarProyectos() {
        fetch("/ITSFCP-PROYECTOS/Vistas/Calendario/obtener_proyectos.php")
            .then(res => res.json())
            .then(lista => {
                console.log(lista);
                let select = document.getElementById("proyectoModal");
                select.innerHTML = '<option value="">Seleccionar proyecto</option>';

                lista.forEach(p => {
                    select.innerHTML += `<option value="${p.id}">${p.titulo}</option>`;
                });
            });
    }


    // =====================================================
    // CARGAR ESTUDIANTES SEGÚN PROYECTO
    // =====================================================
    const proyectoModal = document.getElementById("proyectoModal");
    const listaEstudiantes = document.getElementById("listaEstudiantesModal");

    proyectoModal.addEventListener("change", function() {
        const id = this.value;

        if (!id) {
            listaEstudiantes.innerHTML =
                "<p class='small text-muted'>Seleccione un proyecto...</p>";
            return;
        }

        fetch(`/ITSFCP-PROYECTOS/Vistas/Eventos/crear.php?getEstudiantes=1&id_proyecto=${id}`)
            .then(res => res.json())
            .then(estudiantes => {
                console.log(estudiantes);
                if (!estudiantes.length) {
                    listaEstudiantes.innerHTML =
                        "<p class='text-muted small'>No hay estudiantes en este proyecto.</p>";
                    return;
                }

                let html = "";
                estudiantes.forEach(e => {
                    html += `
                        <label class="inv-item">
                            <input type="checkbox" name="invitados[]" value="${e.id_usuarios}">
                            ${e.nombre} ${e.apellido}
                        </label>
                    `;
                });

                listaEstudiantes.innerHTML = html;
            });
    });


    // =====================================================
    // SUBMIT DEL FORMULARIO (ENVÍA A crear.php)
    // =====================================================
    const formModal = document.getElementById("formCrearEvento");

formModal.addEventListener("submit", function(e) {
    e.preventDefault();

    document.getElementById("descripcionModal").value = quillModal.root.innerHTML;

    const formData = new FormData(formModal);

    // Depurar qué se envía
    for (let pair of formData.entries()) {
        console.log(pair[0]+ ': ' + pair[1]);
    }

    fetch("/ITSFCP-PROYECTOS/Vistas/Eventos/crear.php", {
        method: "POST",
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        console.log(data); // ver qué responde el servidor
        document.getElementById("alertaEvento").innerHTML = data.msg;
        document.getElementById("alertaEvento").style.display = "block";

        if (data.status === "ok") {
            setTimeout(() => {
                window.location.reload();
            }, 1200);
        }
    });
});

});