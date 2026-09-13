document.addEventListener('DOMContentLoaded', function () {
    var btn = document.getElementById('btnToggleSidebar');
    var sidebar = document.getElementById('sidebar');
    var overlay = document.getElementById('sidebarOverlay');

    if (btn && sidebar) {
        btn.addEventListener('click', function () {
            sidebar.classList.toggle('show');
            overlay.classList.toggle('show');
        });
    }
    if (overlay) {
        overlay.addEventListener('click', function () {
            sidebar.classList.remove('show');
            overlay.classList.remove('show');
        });
    }

    document.querySelectorAll('.alert').forEach(function (alerta) {
        setTimeout(function () {
            var bsAlert = bootstrap.Alert.getOrCreateInstance(alerta);
            if (bsAlert) bsAlert.close();
        }, 5000);
    });
});

function confirmarAccion(mensaje) {
    return confirm(mensaje || '¿Está seguro de realizar esta acción?');
}

/**
 * Muestra un aviso flotante (toast de Bootstrap) en vez del alert() nativo del navegador.
 * Pensado como reemplazo directo de alert() para mensajes de validación en formularios.
 *
 * @param {string} mensaje Texto del aviso
 * @param {string} tipo     'error' (por defecto), 'success' o 'warning'
 */
function mostrarAviso(mensaje, tipo) {
    const estilos = {
        error:   { clase: 'text-bg-danger',  icono: 'bi-exclamation-triangle-fill' },
        success: { clase: 'text-bg-success', icono: 'bi-check-circle-fill' },
        warning: { clase: 'text-bg-warning', icono: 'bi-exclamation-circle-fill' },
    };
    const estilo = estilos[tipo] || estilos.error;

    let contenedor = document.getElementById('avisosContenedor');
    if (!contenedor) {
        contenedor = document.createElement('div');
        contenedor.id = 'avisosContenedor';
        contenedor.className = 'toast-container position-fixed top-0 start-50 translate-middle-x p-3';
        contenedor.style.zIndex = '1090';
        document.body.appendChild(contenedor);
    }

    const toastEl = document.createElement('div');
    toastEl.className = 'toast align-items-center ' + estilo.clase + ' border-0';
    toastEl.setAttribute('role', 'alert');
    toastEl.setAttribute('aria-live', 'assertive');
    toastEl.setAttribute('aria-atomic', 'true');

    const flex = document.createElement('div');
    flex.className = 'd-flex';

    const body = document.createElement('div');
    body.className = 'toast-body';
    const icono = document.createElement('i');
    icono.className = 'bi ' + estilo.icono + ' me-2';
    body.appendChild(icono);
    body.appendChild(document.createTextNode(mensaje));

    const btnCerrar = document.createElement('button');
    btnCerrar.type = 'button';
    btnCerrar.className = 'btn-close btn-close-white me-2 m-auto';
    btnCerrar.setAttribute('data-bs-dismiss', 'toast');
    btnCerrar.setAttribute('aria-label', 'Cerrar');

    flex.appendChild(body);
    flex.appendChild(btnCerrar);
    toastEl.appendChild(flex);
    contenedor.appendChild(toastEl);

    const toast = new bootstrap.Toast(toastEl, { delay: 6000 });
    toastEl.addEventListener('hidden.bs.toast', () => toastEl.remove());
    toast.show();
}

/**
 * Inicializa un selector de íconos de Bootstrap Icons con vista previa y buscador.
 * Requiere que assets/js/bootstrap-icons-list.js esté cargado (variable BOOTSTRAP_ICONS_LISTA).
 *
 * @param {string} searchInputId  Input de texto donde el usuario busca/escribe
 * @param {string} hiddenInputId  Input oculto que guarda el valor real (ej: "bi-gear")
 * @param {string} previewId      Elemento <i> o <span> donde se muestra el ícono elegido
 * @param {string} dropdownId     Contenedor donde se listan las coincidencias
 * @param {string} valorInicial   Valor inicial (ej: "bi-gear"), opcional
 */
function inicializarSelectorIcono(searchInputId, hiddenInputId, previewId, dropdownId, valorInicial) {
    const input = document.getElementById(searchInputId);
    const oculto = document.getElementById(hiddenInputId);
    const preview = document.getElementById(previewId);
    const dropdown = document.getElementById(dropdownId);
    if (!input || !oculto || !preview || !dropdown) return;

    function actualizarPreview(nombreCompleto) {
        preview.className = 'bi ' + (nombreCompleto || 'bi-question-circle');
    }

    function establecerValor(nombreSinPrefijo) {
        const completo = 'bi-' + nombreSinPrefijo;
        oculto.value = completo;
        input.value = nombreSinPrefijo;
        actualizarPreview(completo);
        dropdown.classList.add('d-none');
    }

    function renderizarLista(filtro) {
        const lista = (typeof BOOTSTRAP_ICONS_LISTA !== 'undefined' ? BOOTSTRAP_ICONS_LISTA : [])
            .filter(nombre => nombre.toLowerCase().includes(filtro.toLowerCase()))
            .slice(0, 80);
        if (!lista.length) {
            dropdown.innerHTML = '<div class="p-2 text-muted small">Sin coincidencias.</div>';
        } else {
            dropdown.innerHTML = lista.map(nombre => `
                <div class="icon-picker-item" data-icono="${nombre}">
                    <i class="bi bi-${nombre}"></i> <span>${nombre}</span>
                </div>
            `).join('');
        }
        dropdown.classList.remove('d-none');
    }

    dropdown.addEventListener('click', function (e) {
        const item = e.target.closest('.icon-picker-item');
        if (item) establecerValor(item.dataset.icono);
    });
    input.addEventListener('focus', function () { renderizarLista(input.value); });
    input.addEventListener('input', function () { renderizarLista(input.value); });
    document.addEventListener('click', function (e) {
        if (!e.target.closest('#' + searchInputId) && !e.target.closest('#' + dropdownId)) {
            dropdown.classList.add('d-none');
        }
    });

    if (valorInicial) {
        establecerValor(valorInicial.replace(/^bi-/, ''));
    }

    // Expuesto para poder fijar el valor programáticamente (ej. al editar un registro)
    input.establecerValorIcono = establecerValor;
}

/**
 * Inicializa una lista (tbody) cuyas filas se pueden arrastrar para reordenar.
 * Cada fila debe tener class="fila-arrastrable" draggable="true" y data-id="N".
 * Al soltar, envía por POST (fetch) la nueva secuencia de ids a la URL actual.
 *
 * En pantallas táctiles no hay "dragstart" (la API HTML5 Drag and Drop no la
 * disparan los navegadores móviles), así que además se maneja el reordenamiento
 * a mano con touchstart/touchmove/touchend sobre la primera celda de la fila
 * (el "grip" bi-grip-vertical) para no interferir con el scroll vertical de la tabla.
 *
 * @param {string} idLista     Id del <tbody> contenedor de las filas
 * @param {string} accion      Valor del campo "accion" a enviar (ej: 'reordenar')
 * @param {object} datosExtra  Pares clave/valor adicionales a enviar (ej: {padre: 5})
 */
function inicializarListaOrdenable(idLista, accion, datosExtra) {
    const lista = document.getElementById(idLista);
    if (!lista) return;
    let filaArrastrada = null;

    function moverSiCorresponde(filaSobre, y) {
        if (!filaSobre || filaSobre === filaArrastrada || !lista.contains(filaSobre)) return;
        const rect = filaSobre.getBoundingClientRect();
        const despuesDeCentro = (y - rect.top) > rect.height / 2;
        lista.insertBefore(filaArrastrada, despuesDeCentro ? filaSobre.nextSibling : filaSobre);
    }

    function activar() {
        lista.querySelectorAll('.fila-arrastrable').forEach(fila => {
            fila.addEventListener('dragstart', () => {
                filaArrastrada = fila;
                fila.classList.add('opacity-50');
            });
            fila.addEventListener('dragend', () => {
                fila.classList.remove('opacity-50');
                guardarNuevoOrden();
            });
            fila.addEventListener('dragover', e => {
                e.preventDefault();
                moverSiCorresponde(e.target.closest('.fila-arrastrable'), e.clientY);
            });

            const handle = fila.querySelector('td:first-child') || fila;
            handle.addEventListener('touchstart', () => {
                filaArrastrada = fila;
                fila.classList.add('opacity-50');
                document.addEventListener('touchmove', alMoverToque, { passive: false });
                document.addEventListener('touchend', alSoltarToque);
                document.addEventListener('touchcancel', alSoltarToque);
            }, { passive: true });
        });
    }

    function alMoverToque(e) {
        if (!filaArrastrada) return;
        e.preventDefault();
        const toque = e.touches[0];
        const elemento = document.elementFromPoint(toque.clientX, toque.clientY);
        moverSiCorresponde(elemento && elemento.closest('.fila-arrastrable'), toque.clientY);
    }

    function alSoltarToque() {
        document.removeEventListener('touchmove', alMoverToque);
        document.removeEventListener('touchend', alSoltarToque);
        document.removeEventListener('touchcancel', alSoltarToque);
        if (!filaArrastrada) return;
        filaArrastrada.classList.remove('opacity-50');
        filaArrastrada = null;
        guardarNuevoOrden();
    }

    function guardarNuevoOrden() {
        const ids = Array.from(lista.querySelectorAll('.fila-arrastrable')).map(f => f.dataset.id);
        const datos = new FormData();
        datos.append('accion', accion);
        ids.forEach(id => datos.append('ids[]', id));
        if (datosExtra) {
            Object.keys(datosExtra).forEach(clave => datos.append(clave, datosExtra[clave]));
        }
        fetch(window.location.pathname, { method: 'POST', body: datos });
    }

    activar();
}

/**
 * Inicializa una zona de arrastrar y soltar (drag & drop) para adjuntar múltiples
 * archivos, validando extensiones permitidas y mostrando una lista previa con
 * opción de quitar archivos antes de enviar el formulario.
 *
 * @param {string} zonaId        Contenedor visual de la zona de arrastre
 * @param {string} inputId       Input type="file" (multiple) real que se envía con el formulario
 * @param {string} listaId       Contenedor donde se listan los archivos seleccionados
 * @param {string[]} extensiones Extensiones permitidas, ej: ['png','jpg','mp4','pdf']
 */
function inicializarDropzone(zonaId, inputId, listaId, extensiones) {
    const zona = document.getElementById(zonaId);
    const input = document.getElementById(inputId);
    const lista = document.getElementById(listaId);
    if (!zona || !input || !lista) return;

    let archivosSeleccionados = [];

    function extensionValida(nombre) {
        const ext = nombre.split('.').pop().toLowerCase();
        return extensiones.includes(ext);
    }

    function iconoPara(nombre) {
        const ext = nombre.split('.').pop().toLowerCase();
        if (ext === 'pdf') return 'bi-file-earmark-pdf text-danger';
        if (ext === 'mp4') return 'bi-file-earmark-play text-primary';
        return 'bi-file-earmark-image text-success';
    }

    function sincronizarInput() {
        const dt = new DataTransfer();
        archivosSeleccionados.forEach(f => dt.items.add(f));
        input.files = dt.files;
    }

    function renderizarLista() {
        if (!archivosSeleccionados.length) {
            lista.innerHTML = '';
            return;
        }
        lista.innerHTML = archivosSeleccionados.map((f, i) => `
            <div class="d-flex align-items-center justify-content-between border rounded px-2 py-1 mb-1">
                <span><i class="bi ${iconoPara(f.name)} me-2"></i>${f.name} <small class="text-muted">(${(f.size/1024).toFixed(0)} KB)</small></span>
                <button type="button" class="btn btn-sm btn-outline-danger" data-quitar="${i}"><i class="bi bi-x"></i></button>
            </div>
        `).join('');
    }

    function agregarArchivos(fileList) {
        const rechazados = [];
        Array.from(fileList).forEach(f => {
            if (!extensionValida(f.name)) { rechazados.push(f.name); return; }
            const yaExiste = archivosSeleccionados.some(x => x.name === f.name && x.size === f.size);
            if (!yaExiste) archivosSeleccionados.push(f);
        });
        if (rechazados.length) {
            mostrarAviso('Estos archivos no tienen una extensión permitida (' + extensiones.join(', ') + '): ' + rechazados.join(', '));
        }
        sincronizarInput();
        renderizarLista();
    }

    zona.addEventListener('click', () => input.click());
    zona.addEventListener('dragover', e => { e.preventDefault(); zona.classList.add('dropzone-active'); });
    zona.addEventListener('dragleave', () => zona.classList.remove('dropzone-active'));
    zona.addEventListener('drop', e => {
        e.preventDefault();
        zona.classList.remove('dropzone-active');
        if (e.dataTransfer.files.length) agregarArchivos(e.dataTransfer.files);
    });
    input.addEventListener('change', () => { if (input.files.length) agregarArchivos(input.files); });
    lista.addEventListener('click', e => {
        const btn = e.target.closest('[data-quitar]');
        if (btn) {
            archivosSeleccionados.splice(parseInt(btn.dataset.quitar), 1);
            sincronizarInput();
            renderizarLista();
        }
    });
}
