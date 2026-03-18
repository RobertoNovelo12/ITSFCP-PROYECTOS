let contadorSub = 0;
const maxSubareas = 5; //Cantidad máxima de subareas

/* ACTUALIZAR CONTADOR */

function actualizarContador() {
  const total = document.querySelectorAll(".subarea-input").length;

  const contador = document.getElementById("contadorSubarea");

  if (contador) {
    contador.textContent = `${total} / ${maxSubareas}`;
  }

  const boton = document.querySelector(".btn-agregar-sub");

  if (total >= maxSubareas) {
    boton.disabled = true;
  } else {
    boton.disabled = false;
  }
}

/* AGREGAR AREA */

function agregarSubarea() {
  const lista = document.getElementById("listaSubarea");

  const total = document.querySelectorAll(".subarea-input").length;

  if (total >= maxSubareas) {
    alert("Se alcanzó el límite de subareas");
    return;
  }

  const html = `
<div class="subarea row mb-3 align-items-center g-2">

<input type="hidden" name="subarea[${contadorSub}][id_subarea]" value="nuevo">

<div class="col-12 col-md-8">

<input
class="form-control subarea-input"
name="subarea[${contadorSub}][nombre]"
placeholder="Nueva subarea"
required>

</div>

<div class="col-12 col-md-4">

<button
type="button"
class="btn btn-eliminar-sub w-100"
onclick="eliminarSub(this)">
Eliminar
</button>

</div>

</div>
`;

  lista.insertAdjacentHTML("beforeend", html);

  contadorSub++;

  actualizarContador();
}

/*ELIMINAR SUBAREA*/

function eliminarSub(btn) {
  const item = btn.closest(".subarea");

  if (item) {
    item.remove();
  }

  actualizarContador();
}

/* EVITAR DUPLICADOS */

function hayDuplicados() {
  const valores = [];

  let duplicado = false;

  document.querySelectorAll(".subarea-input").forEach((input) => {
    const valor = input.value.trim().toLowerCase();

    if (valor === "") return;

    if (valores.includes(valor)) {
      duplicado = true;
    }

    valores.push(valor);
  });

  return duplicado;
}

/* INICIALIZAR */

document.addEventListener("DOMContentLoaded", () => {
  const form = document.getElementById("formCrearArea");

  if (!form) return;

  contadorSub = document.querySelectorAll(".subarea-input").length;

  /* Crear primera subarea automáticamente */

  if (contadorSub === 0) {
    agregarSubarea();
  }

  /* Validación al enviar */

  form.addEventListener("submit", function (e) {
    const subs = document.querySelectorAll(".subarea-input");

    if (subs.length === 0) {
      alert("Debes agregar al menos una subarea");
      e.preventDefault();
      return;
    }

    /* Verificar duplicados */

    if (hayDuplicados()) {
      alert("No se permiten subareas duplicadas");
      e.preventDefault();
      return;
    }

    /* Limpiar inputs vacíos */

    subs.forEach((input) => {
      if (input.value.trim() === "") {
        input.removeAttribute("name");
      }
    });
  });

  actualizarContador();
});
