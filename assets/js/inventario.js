// Formulario de entradas, salidas y ajustes de inventario con varias líneas.
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('form-inv');
    if (!form) return;
    const tipo = form.dataset.tipo, url = form.dataset.buscar;
    const buscar = document.getElementById('buscar'), resultados = document.getElementById('resultados');
    const tbody = document.getElementById('lineas');
    const lineas = new Map(); // id_producto -> producto
    const cant = v => Number(v).toLocaleString('es-CO', { maximumFractionDigits: 3 });
    const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

    function agregar(p, cantidad = '', costo = '') {
        if (lineas.has(String(p.id_producto))) {
            const input = tbody.querySelector(`tr[data-id="${p.id_producto}"] .cant`);
            input.focus(); input.select();
            return;
        }
        lineas.set(String(p.id_producto), p);
        const paso = p.decimal ? 'any' : '1';
        const tr = document.createElement('tr');
        tr.dataset.id = p.id_producto;
        tr.innerHTML = `
            <td><strong>${esc(p.nombre)}</strong><br><small>${esc(p.codigo)} · ${esc(p.unidad_medida)}</small></td>
            <td class="num">${cant(p.stock)}</td>
            <td class="num"><input type="number" class="cant" name="items[${p.id_producto}][cantidad]" min="${tipo === 'ajuste' ? 0 : (p.decimal ? 0.001 : 1)}" step="${paso}"
                ${tipo === 'salida' ? `max="${Number(p.stock)}"` : ''} value="${esc(cantidad)}" required></td>
            ${tipo === 'entrada' ? `<td class="num"><input type="number" class="costo" name="items[${p.id_producto}][costo]" min="0" step="any" value="${esc(costo !== '' ? costo : Number(p.precio_compra))}"></td>` : ''}
            <td class="num resultado-linea">—</td>
            <td class="num"><button type="button" class="btn btn-danger btn-sm quitar" aria-label="Quitar">✕</button></td>`;
        tbody.appendChild(tr);
        calcular(tr);
        actualizarVacio();
        const input = tr.querySelector('.cant');
        if (cantidad === '') input.focus();
    }
    function calcular(tr) {
        const p = lineas.get(tr.dataset.id), v = tr.querySelector('.cant').value, celda = tr.querySelector('.resultado-linea');
        if (v === '') { celda.textContent = '—'; celda.className = 'num resultado-linea'; return; }
        const n = parseFloat(v), stock = Number(p.stock);
        let texto, clase = '';
        if (tipo === 'entrada') { texto = cant(stock + n); clase = 'positivo'; }
        else if (tipo === 'salida') { texto = stock - n < 0 ? 'Insuficiente' : cant(stock - n); clase = 'negativo'; }
        else { const d = n - stock; texto = d === 0 ? 'Sin cambio' : (d > 0 ? '+' : '−') + cant(Math.abs(d)); clase = d > 0 ? 'positivo' : d < 0 ? 'negativo' : 'muted'; }
        celda.textContent = texto; celda.className = 'num resultado-linea ' + clase;
    }
    function actualizarVacio() {
        document.getElementById('sin-lineas').hidden = lineas.size > 0;
        document.getElementById('guardar').disabled = lineas.size === 0;
    }

    let espera;
    async function buscarProductos(q) {
        const r = await fetch(`${url}?q=${encodeURIComponent(q)}`, { headers: { Accept: 'application/json' } });
        const res = await r.json();
        return res.success ? res.data.productos : [];
    }
    buscar.addEventListener('input', () => {
        clearTimeout(espera);
        const q = buscar.value.trim();
        if (!q) { resultados.hidden = true; return; }
        espera = setTimeout(async () => {
            const productos = await buscarProductos(q);
            resultados.innerHTML = productos.length ? productos.map((p, i) => `
                <button type="button" class="resultado" data-i="${i}">
                    <span><strong>${esc(p.nombre)}</strong><small>${esc(p.codigo)} · Stock: ${cant(p.stock)} ${esc(p.unidad_medida)}</small></span>
                </button>`).join('') : '<p class="empty">No se encontraron productos activos.</p>';
            resultados._productos = productos;
            resultados.hidden = false;
        }, 250);
    });
    buscar.addEventListener('keydown', async e => {
        if (e.key !== 'Enter') return;
        e.preventDefault(); clearTimeout(espera);
        const q = buscar.value.trim();
        if (!q) return;
        const [p] = await buscarProductos(q);
        if (p) { agregar(p); buscar.value = ''; resultados.hidden = true; }
    });
    resultados.addEventListener('click', e => {
        const btn = e.target.closest('.resultado');
        if (!btn) return;
        agregar(resultados._productos[btn.dataset.i]);
        buscar.value = ''; resultados.hidden = true;
    });
    document.addEventListener('click', e => { if (!e.target.closest('.pos-search, .resultados')) resultados.hidden = true; });
    tbody.addEventListener('input', e => { if (e.target.matches('.cant')) calcular(e.target.closest('tr')); });
    tbody.addEventListener('click', e => {
        if (!e.target.closest('.quitar')) return;
        const tr = e.target.closest('tr');
        lineas.delete(tr.dataset.id); tr.remove(); actualizarVacio();
    });
    // Enter dentro de una cantidad no envía el formulario: vuelve al buscador para seguir agregando.
    tbody.addEventListener('keydown', e => { if (e.key === 'Enter' && e.target.matches('input')) { e.preventDefault(); buscar.focus(); } });
    form.addEventListener('submit', e => {
        if (!lineas.size) { e.preventDefault(); return; }
        if (!confirm(`¿Confirmas ${tipo === 'ajuste' ? 'el ajuste' : 'la ' + tipo} de ${lineas.size} producto(s)?`)) e.preventDefault();
    });
    (window.INV_LINEAS || []).forEach(p => agregar(p, p.cantidad ?? '', p.costo ?? ''));
    actualizarVacio();
});
