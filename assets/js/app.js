document.addEventListener('DOMContentLoaded', () => {
    const html = document.documentElement;
    const toggle = document.querySelector('.sidebar-toggle');
    const backdrop = document.querySelector('.sidebar-backdrop');
    if (!toggle) return;
    const esMovil = () => window.matchMedia('(max-width: 900px)').matches;
    const actualizarAria = () => toggle.setAttribute('aria-expanded', String(
        esMovil() ? html.classList.contains('sidebar-open') : !html.classList.contains('sidebar-collapsed')));

    toggle.addEventListener('click', () => {
        if (esMovil()) {
            html.classList.toggle('sidebar-open');
        } else {
            const oculto = html.classList.toggle('sidebar-collapsed');
            try { localStorage.setItem('sidebar', oculto ? 'oculto' : 'visible'); } catch (e) {}
        }
        actualizarAria();
    });
    backdrop?.addEventListener('click', () => { html.classList.remove('sidebar-open'); actualizarAria(); });
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape' && html.classList.contains('sidebar-open')) { html.classList.remove('sidebar-open'); actualizarAria(); }
    });
    window.addEventListener('resize', actualizarAria);
    actualizarAria();
});
