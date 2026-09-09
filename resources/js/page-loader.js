const LOADER_ATTR = 'data-page-loader';

function getLoader() {
    return document.querySelector(`[${LOADER_ATTR}]`);
}

export function showPageLoader(label = 'Loading…') {
    const el = getLoader();
    if (!el) return;

    const text = el.querySelector('[data-page-loader-label]');
    if (text) text.textContent = label;

    el.classList.remove('hidden');
    el.classList.add('flex');
    el.setAttribute('aria-hidden', 'false');
    document.documentElement.classList.add('fd-page-loading');
}

export function hidePageLoader() {
    const el = getLoader();
    if (!el) return;

    el.classList.add('hidden');
    el.classList.remove('flex');
    el.setAttribute('aria-hidden', 'true');
    document.documentElement.classList.remove('fd-page-loading');
}

function shouldIgnoreLink(anchor) {
    if (!anchor || anchor.target === '_blank' || anchor.hasAttribute('download')) {
        return true;
    }

    const href = anchor.getAttribute('href');
    if (!href || href.startsWith('#') || href.startsWith('javascript:') || href.startsWith('mailto:') || href.startsWith('tel:')) {
        return true;
    }

    if (anchor.dataset.noLoader !== undefined || anchor.closest('[data-no-loader]')) {
        return true;
    }

    try {
        const url = new URL(href, window.location.origin);
        if (url.origin !== window.location.origin) {
            return true;
        }

        // Same-URL hash-only / query-only soft navigations still load; allow loader.
        // Skip only when href resolves to the exact current path+search and is just a hash jump already handled above.
    } catch {
        return true;
    }

    return false;
}

/**
 * Show immediately so full-page navigations paint the loader before unload.
 * If another handler preventDefaults (AJAX/modals), hide on the next microtask.
 */
function showUnlessCancelled(event, label) {
    showPageLoader(label);

    queueMicrotask(() => {
        if (event.defaultPrevented) {
            hidePageLoader();
        }
    });
}

export function initPageLoader() {
    if (!getLoader()) {
        return;
    }

    hidePageLoader();

    document.addEventListener('click', (event) => {
        const anchor = event.target.closest?.('a[href]');
        if (!anchor || shouldIgnoreLink(anchor) || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
            return;
        }

        showUnlessCancelled(event, 'Loading…');
    }, true);

    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement)) return;
        if (form.dataset.noLoader !== undefined || form.closest('[data-no-loader]')) return;

        // Confirm dialog intercepts the first submit; don't cover the modal with a loader.
        if (form.dataset.confirm && form.dataset.confirmBypass !== 'true') return;

        showUnlessCancelled(event, form.dataset.loaderLabel || 'Loading…');
    }, true);

    window.addEventListener('pageshow', () => hidePageLoader());
    window.addEventListener('pagehide', () => hidePageLoader());
}
