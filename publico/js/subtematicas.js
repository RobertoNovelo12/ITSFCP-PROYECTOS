let contadorSub = 0;
const maxSubtematicas = 5; //Cantidad máxima de subtematicas

/* ACTUALIZAR CONTADOR */

function actualizarContador(){

const total = document.querySelectorAll('.subtematica-input').length;

const contador = document.getElementById("contadorSubtematicas");

if(contador){

contador.textContent = `${total} / ${maxSubtematicas}`;

}

const boton = document.querySelector(".btn-agregar-sub");

if(total >= maxSubtematicas){

boton.disabled = true;

}else{

boton.disabled = false;

}

}


/* AGREGAR SUBTEMATICA */

function agregarSubtematica(){

const lista = document.getElementById("listaSubtematicas");

const total = document.querySelectorAll('.subtematica-input').length;

if(total >= maxSubtematicas){

alert("Se alcanzó el límite de subtemáticas");
return;

}

const html = `
<div class="subtematica-item mb-3">

<input type="hidden" name="subtematicas[${contadorSub}][id]" value="nuevo">

<div class="subtematica-contenido d-flex gap-2">

<input
class="form-control subtematica-input"
name="subtematicas[${contadorSub}][nombre]"
placeholder="Nueva subtemática"
required>

<button
type="button"
class="btn btn-eliminar-sub"
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


/*ELIMINAR SUBTEMATICA*/

function eliminarSub(btn){

const item = btn.closest(".subtematica-item");

if(item){
item.remove();
}

actualizarContador();

}


/* EVITAR DUPLICADOS */

function hayDuplicados(){

const valores = [];

let duplicado = false;

document.querySelectorAll(".subtematica-input").forEach(input => {

const valor = input.value.trim().toLowerCase();

if(valor === "") return;

if(valores.includes(valor)){

duplicado = true;

}

valores.push(valor);

});

return duplicado;

}


/* INICIALIZAR */

document.addEventListener("DOMContentLoaded", () => {

const form = document.getElementById("formCrearTematica");

if(!form) return;

/* Crear primera subtemática automáticamente */

if(document.querySelectorAll('.subtematica-input').length === 0){

agregarSubtematica();

}

/* Validación al enviar */

form.addEventListener("submit", function(e){

const subs = document.querySelectorAll('.subtematica-input');

if(subs.length === 0){

alert("Debes agregar al menos una subtemática");
e.preventDefault();
return;

}

/* Verificar duplicados */

if(hayDuplicados()){

alert("No se permiten subtemáticas duplicadas");
e.preventDefault();
return;

}

/* Limpiar inputs vacíos */

subs.forEach(input => {

if(input.value.trim() === ""){

input.removeAttribute("name");

}

});

});

actualizarContador();

});