/**
 * Register service worker + optional Chrome install prompt (L1/L2 PWA).
 */
export function initPwa() {
    if (!('serviceWorker' in navigator)) {
        return;
    }

    const register = () => {
        navigator.serviceWorker.register('/sw.js').catch((error) => {
            console.warn('Service worker registration failed', error);
        });
    };

    if (document.readyState === 'complete') {
        register();
    } else {
        window.addEventListener('load', register, { once: true });
    }

    initInstallPrompt();
}

function initInstallPrompt() {
    let deferredPrompt = null;
    const buttons = () => document.querySelectorAll('[data-pwa-install]');

    window.addEventListener('beforeinstallprompt', (event) => {
        event.preventDefault();
        deferredPrompt = event;
        buttons().forEach((button) => {
            button.classList.remove('hidden');
            button.setAttribute('aria-hidden', 'false');
        });
    });

    window.addEventListener('appinstalled', () => {
        deferredPrompt = null;
        buttons().forEach((button) => {
            button.classList.add('hidden');
            button.setAttribute('aria-hidden', 'true');
        });
    });

    document.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-pwa-install]');
        if (!button || !deferredPrompt) {
            return;
        }

        deferredPrompt.prompt();
        try {
            await deferredPrompt.userChoice;
        } catch {
            // User dismissed or browser blocked.
        }
        deferredPrompt = null;
        buttons().forEach((el) => {
            el.classList.add('hidden');
            el.setAttribute('aria-hidden', 'true');
        });
    });

    // Already running as installed PWA — never show install CTA.
    if (window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true) {
        buttons().forEach((button) => button.classList.add('hidden'));
    }
}
