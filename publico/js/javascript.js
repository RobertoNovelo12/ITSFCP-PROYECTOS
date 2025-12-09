document.addEventListener("DOMContentLoaded", () => {
  const darkModeToggle = document.getElementById("darkModeToggle");
  const htmlElement = document.documentElement;
  const storageKey = "darkModeEnabled";

  // Si no hay botón, solo no se configura el toggle, pero el modo oscuro sí puede activarse
  const isDarkMode = localStorage.getItem(storageKey) === "true";

  // Aplicar modo oscuro al cargar la página
  if (isDarkMode) {
    htmlElement.classList.add("dark-mode");
    if (darkModeToggle) {
      darkModeToggle.classList.add("active");
      const icon = darkModeToggle.querySelector("i");
      if (icon) icon.className = "bi bi-moon";
    }
  }

  // Si existe el botón, configurar evento
  if (darkModeToggle) {
    darkModeToggle.addEventListener("click", () => {
      const isCurrentlyDark = htmlElement.classList.toggle("dark-mode");
      localStorage.setItem(storageKey, isCurrentlyDark);

      const icon = darkModeToggle.querySelector("i");
      if (icon) icon.className = isCurrentlyDark ? "bi bi-moon" : "bi bi-sun";
    });
  }

  // ========================================
  // DROPDOWN DEL PERFIL
  // ========================================
  const profileBtn = document.getElementById("userProfileBtn");
  const profileDropdown = document.getElementById("profileDropdown");

  if (profileBtn && profileDropdown) {
    profileBtn.addEventListener("click", (e) => {
      e.stopPropagation();
      profileDropdown.classList.toggle("show");
    });

    document.addEventListener("click", (e) => {
      if (
        !profileBtn.contains(e.target) &&
        !profileDropdown.contains(e.target)
      ) {
        profileDropdown.classList.remove("show");
      }
    });
  }

  // ========================================
  // MOSTRAR/OCULTAR CONTRASEÑA
  // ========================================
  const eyeOpen = "./publico/icons/iconoir_eye-solid.webp";
  const eyeClosed = "./publico/icons/solar_eye-closed-broken.webp";

  function setupPasswordToggle(inputId, iconId) {
    const input = document.getElementById(inputId);
    const icon = document.getElementById(iconId);
    const wrapper = icon?.parentElement;
    if (!input || !icon || !wrapper) return;

    wrapper.addEventListener("click", (e) => {
      e.preventDefault();
      const isPassword = input.type === "password";
      input.type = isPassword ? "text" : "password";
      icon.src = isPassword ? eyeOpen : eyeClosed;
      icon.alt = isPassword ? "Ocultar contraseña" : "Mostrar contraseña";
      input.focus();

      wrapper.classList.add("ripple");
      setTimeout(() => wrapper.classList.remove("ripple"), 500);
    });
  }

  setupPasswordToggle("password", "togglePassword");
  setupPasswordToggle("confirmar", "toggleConfirm");

  // ========================================
  // MODAL SOLICITUD Y CIERRE DE SESIÓN
  // ========================================
  const form = document.getElementById("formSolicitud");
  const modal = document.getElementById("modal-solicitud");
  const confirmarBtn = document.getElementById("confirmar-btn");

  if (form && modal && confirmarBtn) {
    form.addEventListener("submit", async (e) => {
      e.preventDefault();
      const formData = new FormData(form);

      try {
        const response = await fetch(form.action, {
          method: "POST",
          body: formData,
        });

        if (response.ok) {
          modal.style.display = "flex";
        } else {
          alert("Hubo un problema al enviar la solicitud.");
        }
      } catch (error) {
        console.error(error);
        alert("Error de conexión.");
      }
    });

    confirmarBtn.addEventListener("click", async () => {
      try {
        await fetch("../../publico/config/logout.php");
      } catch (e) {
        console.error("Error al cerrar sesión:", e);
      }
      window.location.href = "../../index.php";
    });

    modal.addEventListener("click", (e) => {
      if (e.target === modal) modal.style.display = "none";
    });
  }

  // ========================================
  // AVATAR DINÁMICO
  // ========================================
  const usernameInput = document.getElementById("username");
  const avatarLetter = document.getElementById("avatar-letter");
  const avatarUpload = document.getElementById("avatar-upload");
  const avatarImg = document.getElementById("avatar-img");

  if (usernameInput && avatarLetter && !avatarImg) {
    usernameInput.addEventListener("input", () => {
      const first = usernameInput.value.trim().charAt(0).toUpperCase();
      avatarLetter.textContent = first || "U";
    });
  }

  if (avatarUpload) {
    avatarUpload.addEventListener("change", (event) => {
      const file = event.target.files[0];
      if (!file) return;

      const reader = new FileReader();
      reader.onload = (e) => {
        const src = e.target.result;

        if (avatarLetter) {
          const img = document.createElement("img");
          img.src = src;
          img.alt = "Avatar";
          img.className = "avatar";
          img.id = "avatar-img";
          avatarLetter.replaceWith(img);
        } else if (avatarImg) {
          avatarImg.src = src;
        }
      };

      reader.readAsDataURL(file);
    });
  }
});

// ========================================
// VALIDACIÓN DE CURP
// ========================================
const curpInput = document.getElementById("curp");
const diaInput = document.getElementById("day");
const mesInput = document.getElementById("month");
const anioInput = document.getElementById("year");
const generoSelect = document.getElementById("id_genero");

const curpRegex = /^[A-Z][AEIOU][A-Z]{2}\d{2}(0[1-9]|1[0-2])(0[1-9]|[12]\d|3[01])[HM](AS|BC|BS|CC|CL|CM|CS|CH|DF|DG|GT|GR|HG|JC|MC|MN|MS|NT|NL|OC|PL|QT|QR|SP|SL|SR|TC|TS|TL|VZ|YN|ZS|NE)[B-DF-HJ-NP-TV-Z]{3}[0-9A-Z]\d$/;

if (curpInput) {
  curpInput.addEventListener("input", function () {
    let curp = curpInput.value.toUpperCase();
    curpInput.value = curp;

    if (curp.length === 18 && curpRegex.test(curp)) {
      // Extraer datos
      const year = curp.substring(4, 6);
      const month = curp.substring(6, 8);
      const day = curp.substring(8, 10);
      const gender = curp.substring(10, 11);

      // Convertir año YY → YYYY
      const fullYear = parseInt(year) <= 24 ? "20" + year : "19" + year;

      // Autollenar fecha
      if (diaInput) diaInput.value = day;
      if (mesInput) mesInput.value = month;
      if (anioInput) anioInput.value = fullYear;

      // Autollenar género
      if (generoSelect) {
        if (gender === "H") generoSelect.value = "2"; // Masculino
        if (gender === "M") generoSelect.value = "1"; // Femenino
      }
    }
  });
}

// ========================================
// VALIDACIÓN DE TELÉFONO
// ========================================
const phone = document.getElementById("phone-register");

if (phone) {
  phone.addEventListener("input", function () {
    this.value = this.value.replace(/\D/g, ""); // solo números

    if (this.value.length > 10) {
      this.value = this.value.slice(0, 10);
    }
  });
}

//MODAL

function abrirmodal() {
  const myModal = new bootstrap.Modal(
    document.getElementById("mensaje")
  );
  myModal.show();
}
//VERIFICAR LA CANTIDAD DE ALUMNOS
const inputLimpiar = document.getElementById("InputFormLimpiar6");
if (inputLimpiar) {
document
  .getElementById("InputFormLimpiar6")
  .addEventListener("input", function () {
    const max = 3;

    // Convertimos a número
    let valor = parseInt(this.value);

    // Si no es número, no hacemos nada
    if (isNaN(valor)) {
      this.value = "";
      return;
    }

    // Si es mayor que el máximo → corregir automáticamente
    if (valor > max) {
      this.value = max;
    }

    // Evita ingresar más de 1 dígito
    if (this.value.length > 1) {
      this.value = this.value.slice(0, 1);
    }
  });
}
//ABRIR MODAL DE RECHAZO CIERRE
function abrirRechazo(id) {
  // Insertar ID dentro del input hidden del modal
  document.getElementById("idProyectoRechazoCierre").value = id;

  // Abrir el modal
  var modal = new bootstrap.Modal(
    document.getElementById("modalRechazoCierre")
  );
  modal.show();
}
function abrirRechazoSolicitud(id) {
  // Insertar ID dentro del input hidden del modal
  document.getElementById("id_solicitud_proyectos").value = id;

  // Abrir el modal
  var modal = new bootstrap.Modal(
    document.getElementById("modalRechazoSolicitud")
  );
  modal.show();
}
//ABRIR COMENTARIOS

document.addEventListener("DOMContentLoaded", async function () {
  let id = document.getElementById("idProyectoComentarios").value;

  // Si no hay ID, no hacemos nada
  if (!id) return;

  // Petición AJAX
  let res = await fetch("/ITSFCP-PROYECTOS/Ajax/comentarios.php", {
    method: "POST",
    headers: {
      "Content-Type": "application/x-www-form-urlencoded",
    },
    body: "id=" + id,
  });

  let datos = await res.json();

  let accordion = document.getElementById("comentariosAccordion");
  accordion.innerHTML = "";

  if (datos.length === 0) {
    accordion.innerHTML = "<p>No hay comentarios.</p>";
  } else {
    datos.forEach((c, index) => {
      accordion.innerHTML += `
                <div class="accordion-item">
                    <h2 class="accordion-header" id="heading${index}">
                        <button class="accordion-button collapsed"
                                type="button"
                                data-bs-toggle="collapse"
                                data-bs-target="#collapse${index}">
                            <strong>${c.tipo} — ${c.fecha}</strong>
                        </button>
                    </h2>
                    <div id="collapse${index}" class="accordion-collapse collapse">
                        <div class="accordion-body">
                            <p><strong>Supervisor:</strong> ${c.nombre_completo}</p>
                            <p><strong>Comentario:</strong> ${c.comentario}</p>
                        </div>
                    </div>
                </div>`;
    });
  }
});

//MOSTRAR TOOLTIP, QUE ES UN TEXTO AL SOBREPONER MOUSE EN BOTÓN
document.addEventListener("DOMContentLoaded", function () {
  const tooltipTriggerList = document.querySelectorAll(
    '[data-bs-toggle="tooltip"]'
  );
  const tooltipList = [...tooltipTriggerList].map(
    (t) => new bootstrap.Tooltip(t)
  );
});

  // Abrir el modal
  let modal = new bootstrap.Modal(document.getElementById("modalComentarios"));
  modal.show();
});

//ABRIR MODAL DE MENSAJE
function abrirMensaje() {
  document.addEventListener("DOMContentLoaded", function () {
    const myModal = new bootstrap.Modal(document.getElementById("mensaje"));
    myModal.show();
  });
}

