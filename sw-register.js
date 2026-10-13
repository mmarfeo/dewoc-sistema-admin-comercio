if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        const swPath = (window.APP_BASE || '') + '/sw.js';
        navigator.serviceWorker.register(swPath)
            .then(reg => console.log('[PWA] SW registrado:', reg.scope))
            .catch(err => console.warn('[PWA] Error SW:', err));
    });

    // Banner de instalación
    let deferredPrompt;
    window.addEventListener('beforeinstallprompt', e => {
        e.preventDefault();
        if (localStorage.getItem('dewoc-pwa-banner-oculto') === '1') return;
        deferredPrompt = e;
        const banner = document.getElementById('pwa-install-banner');
        if (banner) banner.classList.remove('d-none');
    });

    document.addEventListener('click', e => {
        if (e.target && e.target.id === 'btn-install-pwa' && deferredPrompt) {
            deferredPrompt.prompt();
            deferredPrompt.userChoice.then(() => { deferredPrompt = null; });
            document.getElementById('pwa-install-banner')?.classList.add('d-none');
        }
        if (e.target && e.target.id === 'btn-dismiss-pwa') {
            document.getElementById('pwa-install-banner')?.classList.add('d-none');
            localStorage.setItem('dewoc-pwa-banner-oculto', '1');
        }
    });

    window.addEventListener('appinstalled', () => {
        localStorage.setItem('dewoc-pwa-banner-oculto', '1');
    });
}
