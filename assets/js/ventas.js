// Punto de venta: búsqueda de productos, carrito (guardado en la BD) y cálculo de totales.
document.addEventListener('DOMContentLoaded', () => {
    const pos = document.getElementById('pos');
    if (!pos) return;
    const url = pos.dataset.url;
    const csrf = pos.dataset.csrf;
    const impuestoPct = parseFloat(pos.dataset.impuesto) || 0;
    const $ = id => document.getElementById(id);
    const buscar = $('buscar'), resultados = $('resultados'), tbody = $('items');
    const descuento = $('descuento'), metodo = $('metodo'), recibido = $('recibido');
    let carrito = window.CARRITO || { items: [] };
    let total = 0;

    const dinero = v => '$' + Math.round(v).toLocaleString('es-CO');
    const cant = v => Number(v).toLocaleString('es-CO', { maximumFractionDigits: 3 });
    const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

    function aviso(mensaje, tipo = 'error') {
        let el = document.querySelector('.pos-aviso');
        if (!el) { el = document.createElement('div'); pos.before(el); }
        el.className = `alert ${tipo} pos-aviso`;
        el.textContent = mensaje;
        clearTimeout(aviso.t);
        aviso.t = setTimeout(() => el.remove(), 5000);
    }

    async function enviar(accion, datos = {}) {
        const body = new FormData();
        body.append('accion', accion);
        body.append('csrf', csrf);
        Object.entries(datos).forEach(([k, v]) => body.append(k, v));
        try {
            const r = await fetch(url, { method: 'POST', body, headers: { Accept: 'application/json' } });
            const res = await r.json();
            if (res.data && res.data.items) { carrito = res.data; pintar(); }
            if (!res.success) aviso(res.message);
            return res.success;
        } catch (e) {
            aviso('No se pudo conectar con el servidor.');
            return false;
        }
    }

    function pintar() {
        const items = carrito.items || [];
        tbody.innerHTML = items.map(i => {
            const paso = i.decimal ? 0.001 : 1;
            return `<tr data-id="${i.id_producto}">
                <td><strong>${esc(i.nombre)}</strong><br><small>${esc(i.codigo)} · Stock: ${cant(i.stock)}</small></td>
                <td class="num">${dinero(i.precio_unitario)}</td>
                <td class="num"><div class="qty">
                    <button type="button" class="btn btn-light btn-sm" data-accion="menos">−</button>
                    <input type="number" value="${Number(i.cantidad)}" min="${paso}" step="${paso}" max="${Number(i.stock)}" aria-label="Cantidad">
                    <button type="button" class="btn btn-light btn-sm" data-accion="mas">+</button>
                </div></td>
                <td class="num">${dinero(i.cantidad * i.precio_unitario)}</td>
                <td class="num"><button type="button" class="btn btn-danger btn-sm" data-accion="quitar" aria-label="Quitar">✕</button></td>
            </tr>`;
        }).join('');
        $('vacio').hidden = items.length > 0;
        $('vaciar').hidden = items.length === 0;
        recalcular();
    }

    function recalcular() {
        const subtotal = (carrito.items || []).reduce((s, i) => s + Math.round(i.cantidad * i.precio_unitario * 100) / 100, 0);
        const desc = Math.min(Math.max(parseFloat(descuento.value) || 0, 0), subtotal);
        const impuesto = Math.round((subtotal - desc) * impuestoPct) / 100;
        total = subtotal - desc + impuesto;
        $('t-subtotal').textContent = dinero(subtotal);
        if ($('t-impuesto')) $('t-impuesto').textContent = dinero(impuesto);
        $('t-total').textContent = dinero(total);
        const efectivo = metodo.selectedOptions[0]?.dataset.efectivo === '1';
        $('campo-efectivo').hidden = !efectivo;
        $('campo-referencia').hidden = efectivo;
        recibido.required = efectivo;
        recibido.min = efectivo ? total.toFixed(2) : 0;
        const cambio = (parseFloat(recibido.value) || 0) - total;
        $('cambio').textContent = recibido.value === '' ? '$0' : (cambio >= 0 ? dinero(cambio) : 'Falta ' + dinero(-cambio));
        $('cambio').classList.toggle('falta', recibido.value !== '' && cambio < 0);
        $('finalizar').disabled = !(carrito.items || []).length;
    }

    // Búsqueda con espera corta mientras se escribe.
    let espera, ultimaBusqueda = [];
    buscar.addEventListener('input', () => {
        clearTimeout(espera);
        const q = buscar.value.trim();
        if (!q) { resultados.hidden = true; return; }
        espera = setTimeout(async () => {
            try {
                const r = await fetch(`${url}?accion=buscar&q=${encodeURIComponent(q)}`, { headers: { Accept: 'application/json' } });
                const res = await r.json();
                if (!res.success) { aviso(res.message); return; }
                ultimaBusqueda = res.data.productos;
                resultados.innerHTML = ultimaBusqueda.length ? ultimaBusqueda.map(p => `
                    <button type="button" class="resultado" data-id="${p.id_producto}" ${Number(p.stock) <= 0 ? 'disabled' : ''}>
                        <span><strong>${esc(p.nombre)}</strong><small>${esc(p.codigo)} · Stock: ${cant(p.stock)}</small></span>
                        <span class="precio">${dinero(p.precio_venta)}</span>
                    </button>`).join('') : '<p class="empty">No se encontraron productos.</p>';
                resultados.hidden = false;
            } catch (e) { aviso('No se pudo buscar productos.'); }
        }, 250);
    });
    // Enter: agrega el primer resultado (útil con lector de código de barras).
    buscar.addEventListener('keydown', async e => {
        if (e.key !== 'Enter') return;
        e.preventDefault();
        clearTimeout(espera);
        const q = buscar.value.trim();
        if (!q) return;
        const r = await fetch(`${url}?accion=buscar&q=${encodeURIComponent(q)}`, { headers: { Accept: 'application/json' } });
        const res = await r.json();
        const p = res.data?.productos?.[0];
        if (!p) { aviso('No se encontró el producto "' + q + '".'); return; }
        if (await enviar('agregar', { id_producto: p.id_producto, cantidad: 1 })) { buscar.value = ''; resultados.hidden = true; }
    });
    resultados.addEventListener('click', async e => {
        const btn = e.target.closest('.resultado');
        if (!btn) return;
        if (await enviar('agregar', { id_producto: btn.dataset.id, cantidad: 1 })) {
            buscar.value = ''; resultados.hidden = true; buscar.focus();
        }
    });
    document.addEventListener('click', e => { if (!e.target.closest('.pos-search, .resultados')) resultados.hidden = true; });

    tbody.addEventListener('click', e => {
        const btn = e.target.closest('button[data-accion]');
        if (!btn) return;
        const fila = btn.closest('tr'), id = fila.dataset.id;
        const input = fila.querySelector('input'), paso = parseFloat(input.step) || 1;
        if (btn.dataset.accion === 'quitar') enviar('quitar', { id_producto: id });
        if (btn.dataset.accion === 'mas') enviar('actualizar', { id_producto: id, cantidad: parseFloat(input.value) + paso });
        if (btn.dataset.accion === 'menos') enviar('actualizar', { id_producto: id, cantidad: Math.max(parseFloat(input.value) - paso, 0) });
    });
    tbody.addEventListener('change', e => {
        if (e.target.matches('input')) enviar('actualizar', { id_producto: e.target.closest('tr').dataset.id, cantidad: e.target.value || 0 });
    });
    $('vaciar').addEventListener('click', () => { if (confirm('¿Vaciar el carrito?')) enviar('vaciar'); });

    [descuento, metodo, recibido].forEach(el => el.addEventListener('input', recalcular));
    $('form-venta').addEventListener('submit', e => {
        if (!(carrito.items || []).length) { e.preventDefault(); aviso('El carrito está vacío.'); return; }
        if (!confirm('¿Registrar la venta por ' + dinero(total) + '?')) { e.preventDefault(); return; }
        $('finalizar').disabled = true;
    });
    pintar();
});
