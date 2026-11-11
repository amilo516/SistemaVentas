// Panel flotante del Asesor IA: abre/cierra el panel y pide las recomendaciones por AJAX.
document.addEventListener('DOMContentLoaded', () => {
    const fab = document.querySelector('.asesor-fab');
    const panel = document.getElementById('asesor-panel');
    if (!fab || !panel) return;
    const form = panel.querySelector('.ap-form');
    const cuerpo = panel.querySelector('.ap-cuerpo');
    const boton = form.querySelector('button');
    const tipos = {reabastecer: 'Reabastecer', promocion: 'Promoción', precio: 'Precio', devoluciones: 'Devoluciones', ventas: 'Ventas'};

    const abrir = (si) => {
        panel.hidden = !si;
        fab.setAttribute('aria-expanded', String(si));
        fab.classList.toggle('activo', si);
        if (si) (form.querySelector('input') || boton).focus();
    };
    fab.addEventListener('click', () => abrir(panel.hidden));
    panel.querySelector('.ap-cerrar').addEventListener('click', () => { abrir(false); fab.focus(); });
    document.addEventListener('keydown', e => { if (e.key === 'Escape' && !panel.hidden) { abrir(false); fab.focus(); } });

    const el = (etiqueta, clase, texto) => {
        const n = document.createElement(etiqueta);
        if (clase) n.className = clase;
        if (texto !== undefined) n.textContent = texto;
        return n;
    };
    const mensaje = (texto, clase = 'ap-ayuda') => cuerpo.replaceChildren(el('p', clase, texto));

    form.addEventListener('submit', async e => {
        e.preventDefault();
        const datos = new FormData(form);
        datos.append('csrf', panel.dataset.csrf);
        boton.disabled = true;
        mensaje('Consultando los datos y analizando… puede tardar unos segundos.', 'ap-ayuda ap-cargando');
        try {
            const r = await fetch(panel.dataset.url, {method: 'POST', body: datos, credentials: 'same-origin'});
            const json = await r.json();
            if (!json.success) throw new Error(json.message);
            const d = json.data;
            const nodos = [];
            if (d.aviso) nodos.push(el('p', 'ap-aviso', d.aviso));
            nodos.push(el('p', 'ap-resumen', d.resumen));
            const lista = el('ol', 'ap-lista');
            d.recomendaciones.forEach(rec => {
                const li = el('li', 'rec rec-' + rec.prioridad);
                const cab = el('div', 'rec-cabecera');
                cab.append(el('span', 'badge rec-prioridad', 'Prioridad ' + rec.prioridad), el('span', 'rec-tipo', tipos[rec.tipo] || rec.tipo));
                li.append(cab, el('strong', '', rec.titulo), el('p', '', rec.detalle), el('p', 'rec-accion', '→ ' + rec.accion));
                lista.append(li);
            });
            if (d.recomendaciones.length) nodos.push(lista);
            cuerpo.replaceChildren(...nodos);
        } catch (err) {
            mensaje('No se pudo generar el análisis. Recarga la página e inténtalo de nuevo.', 'ap-aviso');
        } finally {
            boton.disabled = false;
        }
    });
});
