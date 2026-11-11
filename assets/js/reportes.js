// Tooltip para las gráficas de reportes (elementos con data-tip).
document.addEventListener('DOMContentLoaded', () => {
    const tip = document.createElement('div');
    tip.className = 'tooltip';
    tip.hidden = true;
    document.body.appendChild(tip);
    const mostrar = (el, x, y) => {
        tip.textContent = el.dataset.tip;
        tip.hidden = false;
        const w = tip.offsetWidth;
        tip.style.left = Math.min(Math.max(x - w / 2, 8), window.innerWidth - w - 8) + 'px';
        tip.style.top = (y - tip.offsetHeight - 12 + window.scrollY) + 'px';
    };
    document.querySelectorAll('[data-tip]').forEach(el => {
        el.addEventListener('mousemove', e => { mostrar(el, e.clientX, e.clientY); el.classList.add('activa'); });
        el.addEventListener('mouseleave', () => { tip.hidden = true; el.classList.remove('activa'); });
    });
});
