const Tabla = {

  init({ contenedor, url, paramsBuscar, paramsFiltro }) {
    this.contenedor   = document.querySelector(contenedor);
    this.url          = url;
    this.inputBuscar  = document.querySelector(paramsBuscar);
    this.selectFiltro = paramsFiltro ? document.querySelector(paramsFiltro) : null;

    const qActual = new URLSearchParams(location.search);
    this._params = {
      action: qActual.get('action') ?? 'index',
      buscar: qActual.get('buscar') ?? '',
      pagina: parseInt(qActual.get('pagina') ?? '1'),
    };

    this._bindEventos();
  },

  _bindEventos() {
    if (this.selectFiltro) {
      this.selectFiltro.addEventListener('change', () => {
        this._actualizar({ action: this.selectFiltro.value, pagina: 1 });
      });
    }

    const form = this.inputBuscar?.closest('form');
    if (form) {
      form.addEventListener('submit', e => {
        e.preventDefault();
        this._actualizar({ buscar: this.inputBuscar.value, pagina: 1 });
      });
    }

    // Botón limpiar búsqueda
    document.addEventListener('click', e => {
      const linkLimpiar = e.target.closest('a.btn-secondary[href*="action="]');
      if (!linkLimpiar) return;
      e.preventDefault();
      this._actualizar({ buscar: '', pagina: 1 });
    });

    // Paginación — delegación en document porque la tabla se reemplaza
    document.addEventListener('click', e => {
      const link = e.target.closest('[data-pagina]');
      if (!link) return;
      e.preventDefault();
      this._actualizar({ pagina: parseInt(link.dataset.pagina) });
    });
  },

  _params: {},

  _actualizar(nuevos = {}) {
    Object.assign(this._params, nuevos);

    const qs = new URLSearchParams({
      action:     this._params.action ?? 'index',
      buscar:     this._params.buscar ?? '',
      pagina:     this._params.pagina ?? 1,
      solo_tabla: 1,
    });

    this._setLoading(true);

    fetch(`${this.url}?${qs}`)
      .then(r => {
        if (!r.ok) throw new Error(r.status);
        return r.text();
      })
      .then(html => {
        this.contenedor.outerHTML = html;
        // Reasignar la referencia porque outerHTML reemplaza el nodo
        this.contenedor = document.querySelector('#tabla-wrap');
        this._setLoading(false);

        if (this.selectFiltro) {
          this.selectFiltro.value = this._params.action;
        }
        if (this.inputBuscar) {
          this.inputBuscar.value = this._params.buscar;
        }

        const qsVisible = new URLSearchParams({
          action: this._params.action ?? 'index',
        });
        if (this._params.buscar) qsVisible.set('buscar', this._params.buscar);
        if (this._params.pagina > 1) qsVisible.set('pagina', this._params.pagina);
        history.replaceState(null, '', `?${qsVisible}`);
      })
      .catch(() => {
        this._setLoading(false);
        if (!this.contenedor.querySelector('.alert-danger')) {
          this.contenedor.insertAdjacentHTML('afterbegin',
            '<div class="alert alert-danger">Error al cargar los datos.</div>');
        }
      });
  },

  _setLoading(estado) {
    if (!this.contenedor) return;
    this.contenedor.style.opacity       = estado ? '0.4' : '1';
    this.contenedor.style.pointerEvents = estado ? 'none' : '';
  }
};