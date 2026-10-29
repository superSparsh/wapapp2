import { showAppAlert } from './confirm-dialog.js';

/**
 * PWA install + app-icon inbox badge (Badging API where the OS allows it).
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
    initAppBadgeLifecycle();
}

/**
 * Mirror inbox unread onto the installed app icon when the Badging API exists.
 * Android Chrome / Windows / ChromeOS: usually works.
 * iOS Home Screen web apps (newer iOS): may work when installed via Safari.
 * macOS Safari / older iOS: often unsupported — tab title badge still updates.
 */
export function setPwaAppBadge(count) {
    const next = Math.max(0, Math.floor(Number(count) || 0));

    const apply = (target) => {
        if (!target) {
            return;
        }
        try {
            if (next > 0 && typeof target.setAppBadge === 'function') {
                target.setAppBadge(next).catch(() => {});
            } else if (next <= 0 && typeof target.clearAppBadge === 'function') {
                target.clearAppBadge().catch(() => {});
            }
        } catch {
            // OS / permission edge cases.
        }
    };

    apply(navigator);

    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.ready
            .then((registration) => apply(registration))
            .catch(() => {});
    }
}

function initAppBadgeLifecycle() {
    const syncFromDom = () => {
        const total = Number(
            document.querySelector('[data-inbox-root]')?.dataset.unreadTotal
            || document.body?.dataset.inboxUnreadTotal
            || 0,
        );
        setPwaAppBadge(total);
    };

    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') {
            syncFromDom();
        }
    });

    window.addEventListener('focus', syncFromDom);
}

function isStandalone() {
    return window.matchMedia('(display-mode: standalone)').matches
        || window.navigator.standalone === true;
}

function isIosDevice() {
    const ua = window.navigator.userAgent || '';
    return /iPhone|iPad|iPod/i.test(ua)
        || (window.navigator.platform === 'MacIntel' && window.navigator.maxTouchPoints > 1);
}

function isSafariBrowser() {
    const ua = window.navigator.userAgent || '';
    return /Safari/i.test(ua) && !/Chrome|CriOS|EdgiOS|FxiOS|OPiOS|Android/i.test(ua);
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
        if (!btn) {
            return;
        }

        // iOS never fires beforeinstallprompt — keep button enabled with Add to Home Screen flow.
        const iosFlow = isIosDevice();
        btn.disabled = false;
        btn.classList.remove('opacity-50');
        btn.title = iosFlow
            ? 'iPhone/iPad: Share → Add to Home Screen'
            : deferredPrompt
                ? 'Install WapApp'
                : 'Chrome/Edge: Install when browser is ready, or use browser menu → Install app';
        btn.dataset.pwaInstallMode = iosFlow ? 'ios' : (deferredPrompt ? 'prompt' : 'menu');
    };

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

        if (isIosDevice()) {
            showAppAlert(
                isSafariBrowser()
                    ? 'iPhone/iPad pe: Safari Share button (□↑) → “Add to Home Screen” → Add. Chrome iOS pe bhi Share → Add to Home Screen use karo.'
                    : 'iPhone/iPad pe WapApp install ke liye Safari kholo → Share (□↑) → “Add to Home Screen” → Add.',
                'Install on iPhone / iPad',
            );
            return;
        }

        if (deferredPrompt) {
            deferredPrompt.prompt();
            try {
                await deferredPrompt.userChoice;
            } catch {
                // dismissed
            }
            deferredPrompt = null;
            showInstall();
            return;
        }

        showAppAlert(
            'Chrome / Edge: address bar ya ⋮ menu → “Install WapApp…” / “Install app”.\n\n'
            + 'Agar option na dikhe: HTTPS pe hard refresh karo, DevTools → Application → Service Workers → Unregister → refresh.',
            'Install WapApp',
        );
    });
}
