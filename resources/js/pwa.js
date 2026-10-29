import { showAppAlert } from './confirm-dialog.js';

/**
 * PWA install: register SW + Profile "Install now" via beforeinstallprompt.
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

function setVisible(el, visible, displayClass = 'inline-flex') {
    if (!el) {
        return;
    }

    if (visible) {
        el.hidden = false;
        el.classList.remove('hidden');
        el.classList.add(displayClass);
        el.setAttribute('aria-hidden', 'false');
    } else {
        el.hidden = true;
        el.classList.add('hidden');
        el.classList.remove(displayClass);
        el.setAttribute('aria-hidden', 'true');
    }
}

function initInstallButton() {
    let deferredPrompt = null;

    const installCard = () => document.querySelector('[data-pwa-install-card]');
    const installedCard = () => document.querySelector('[data-pwa-installed-card]');
    const installButton = () => document.querySelector('[data-pwa-install]');

    const showInstalled = () => {
        setVisible(installCard(), false);
        setVisible(installedCard(), true);
    };

    const showInstall = () => {
        if (isStandalone()) {
            showInstalled();
            return;
        }

        setVisible(installCard(), true);
        setVisible(installedCard(), false);

        const btn = installButton();
        if (btn) {
            btn.disabled = !deferredPrompt;
            btn.classList.toggle('opacity-50', !deferredPrompt);
            btn.title = deferredPrompt
                ? 'Install WapApp'
                : 'Chrome install ready hone ka wait… soft-refresh try karo';
        }
    };

    // Initial mutual exclusivity (before any events).
    if (isStandalone()) {
        showInstalled();
    } else {
        showInstall();
    }

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
                'Install abhi ready nahi hai. Chrome ⋮ menu → “Install WapApp…” check karo, ya HTTPS/localhost pe hard refresh → Application → Service Workers → Unregister → refresh.',
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
