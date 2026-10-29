import { showAppAlert } from './confirm-dialog.js';

/**
 * PWA install: register SW + Profile "Install now" via beforeinstallprompt.
 *
 * Note: calling preventDefault() is required for a reliable custom Install button.
 * Chrome may then hide the omnibox Install icon; use Profile → Install now instead.
 */
export function initPwa() {
    if ('serviceWorker' in navigator) {
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
    }

    initInstallButton();
}

function isStandalone() {
    return window.matchMedia('(display-mode: standalone)').matches
        || window.navigator.standalone === true;
}

function initInstallButton() {
    let deferredPrompt = null;

    const cards = () => document.querySelectorAll('[data-pwa-install-card]');
    const installedCards = () => document.querySelectorAll('[data-pwa-installed-card]');
    const buttons = () => document.querySelectorAll('[data-pwa-install]');

    const showInstalled = () => {
        cards().forEach((el) => {
            el.classList.add('hidden');
            el.setAttribute('aria-hidden', 'true');
        });
        installedCards().forEach((el) => {
            el.classList.remove('hidden');
            el.setAttribute('aria-hidden', 'false');
        });
    };

    const showInstall = () => {
        if (isStandalone()) {
            showInstalled();
            return;
        }
        cards().forEach((el) => {
            el.classList.remove('hidden');
            el.setAttribute('aria-hidden', 'false');
        });
        installedCards().forEach((el) => {
            el.classList.add('hidden');
            el.setAttribute('aria-hidden', 'true');
        });
        buttons().forEach((btn) => {
            btn.disabled = !deferredPrompt;
            btn.classList.toggle('opacity-50', !deferredPrompt);
            btn.title = deferredPrompt
                ? 'Install WapApp'
                : 'Chrome install ready hone ka wait… HTTPS/localhost pe soft-refresh try karo';
        });
    };

    if (isStandalone()) {
        showInstalled();
        return;
    }

    showInstall();

    window.addEventListener('beforeinstallprompt', (event) => {
        event.preventDefault();
        deferredPrompt = event;
        showInstall();
    });

    window.addEventListener('appinstalled', () => {
        deferredPrompt = null;
        showInstalled();
    });

    document.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-pwa-install]');
        if (!button) {
            return;
        }

        if (!deferredPrompt) {
            showAppAlert(
                'Install abhi ready nahi hai. Chrome ⋮ menu → “Install WapApp…” check karo, ya HTTPS/localhost pe hard refresh (Cmd+Shift+R) → Application → Service Workers → Unregister → refresh.',
                'Install WapApp',
            );
            return;
        }

        deferredPrompt.prompt();
        try {
            await deferredPrompt.userChoice;
        } catch {
            // dismissed
        }
        deferredPrompt = null;
        showInstall();
    });
}
