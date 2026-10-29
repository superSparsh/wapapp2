/**
 * Register service worker for PWA installability.
 * Do NOT call preventDefault on beforeinstallprompt — that hides Chrome's
 * address-bar Install icon. Profile page shows install guidance instead.
 */
export function initPwa() {
    if (!('serviceWorker' in navigator)) {
        return;
    }

    const register = () => {
        navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch((error) => {
            console.warn('Service worker registration failed', error);
        });
    };

    if (document.readyState === 'complete') {
        register();
    } else {
        window.addEventListener('load', register, { once: true });
    }

    markInstallUiState();
    window.addEventListener('appinstalled', markInstallUiState);
}

function isStandalone() {
    return window.matchMedia('(display-mode: standalone)').matches
        || window.navigator.standalone === true;
}

function markInstallUiState() {
    const installed = isStandalone();
    document.querySelectorAll('[data-pwa-install-card]').forEach((card) => {
        card.classList.toggle('hidden', installed);
        card.setAttribute('aria-hidden', installed ? 'true' : 'false');
    });
    document.querySelectorAll('[data-pwa-installed-card]').forEach((card) => {
        card.classList.toggle('hidden', !installed);
        card.setAttribute('aria-hidden', installed ? 'false' : 'true');
    });
}
