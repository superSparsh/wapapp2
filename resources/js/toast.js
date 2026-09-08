const DEFAULT_DURATION = 4500;
const MAX_VISIBLE = 5;

let container = null;
const queue = [];

const TYPE_META = {
    success: {
        title: 'Success',
        border: 'var(--color-green-500)',
        iconBg: 'rgba(109, 187, 72, 0.14)',
        iconColor: 'var(--color-green-500)',
        progress: 'var(--color-green-500)',
    },
    error: {
        title: 'Error',
        border: 'var(--color-danger)',
        iconBg: 'rgba(234, 84, 85, 0.14)',
        iconColor: 'var(--color-danger)',
        progress: 'var(--color-danger)',
    },
    warning: {
        title: 'Warning',
        border: '#eb9e51',
        iconBg: 'rgba(235, 158, 81, 0.16)',
        iconColor: '#eb9e51',
        progress: '#eb9e51',
    },
    info: {
        title: 'Notice',
        border: '#4282b6',
        iconBg: 'rgba(66, 130, 182, 0.14)',
        iconColor: '#4282b6',
        progress: '#4282b6',
    },
};

function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function ensureContainer() {
    const existing = document.getElementById('app-toast-container');

    if (existing) {
        container = existing;

        return container;
    }

    if (container) {
        return container;
    }

    container = document.createElement('div');
    container.id = 'app-toast-container';
    container.className = 'app-toast-container';
    container.setAttribute('aria-live', 'polite');
    container.setAttribute('aria-relevant', 'additions');
    document.body.appendChild(container);

    return container;
}

function iconSvg(type) {
    if (type === 'success') {
        return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>';
    }

    if (type === 'error') {
        return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>';
    }

    if (type === 'warning') {
        return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 9v4m0 4h.01M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/></svg>';
    }

    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 8v4m0 4h.01"/></svg>';
}

function dismissToast(toastEl) {
    if (!toastEl || toastEl.dataset.dismissed === 'true') {
        return;
    }

    toastEl.dataset.dismissed = 'true';
    toastEl.classList.add('app-toast--leave');

    window.setTimeout(() => {
        toastEl.remove();
        const index = queue.indexOf(toastEl);
        if (index >= 0) {
            queue.splice(index, 1);
        }
    }, 220);
}

function createToastElement({ type, message, title, duration }) {
    const meta = TYPE_META[type] ?? TYPE_META.info;
    const toast = document.createElement('div');
    toast.className = 'app-toast';
    toast.style.setProperty('--toast-accent', meta.border);
    toast.style.setProperty('--toast-icon-bg', meta.iconBg);
    toast.style.setProperty('--toast-icon-color', meta.iconColor);
    toast.style.setProperty('--toast-progress', meta.progress);
    toast.style.setProperty('--toast-duration', `${duration}ms`);

    toast.innerHTML = `
      <div class="app-toast__icon">${iconSvg(type)}</div>
      <div class="app-toast__content">
        <p class="app-toast__title">${escapeHtml(title || meta.title)}</p>
        <p class="app-toast__message">${escapeHtml(message)}</p>
      </div>
      <button type="button" class="app-toast__close" aria-label="Dismiss notification">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>
      </button>
      <span class="app-toast__progress" aria-hidden="true"></span>
    `;

    toast.querySelector('.app-toast__close')?.addEventListener('click', () => dismissToast(toast));

    return toast;
}

export function showToast({
    type = 'info',
    message,
    title,
    duration = DEFAULT_DURATION,
} = {}) {
    const text = String(message ?? '').trim();

    if (!text) {
        return null;
    }

    const root = ensureContainer();

    while (queue.length >= MAX_VISIBLE) {
        dismissToast(queue[0]);
    }

    const toast = createToastElement({
        type: TYPE_META[type] ? type : 'info',
        message: text,
        title,
        duration: duration > 0 ? duration : DEFAULT_DURATION,
    });

    root.appendChild(toast);
    queue.push(toast);

    requestAnimationFrame(() => {
        toast.classList.add('app-toast--enter');
    });

    if (duration > 0) {
        window.setTimeout(() => dismissToast(toast), duration);
    }

    return () => dismissToast(toast);
}

export function showSuccessToast(message, title) {
    return showToast({ type: 'success', message, title });
}

export function showErrorToast(message, title) {
    return showToast({ type: 'error', message, title });
}

export function showWarningToast(message, title) {
    return showToast({ type: 'warning', message, title });
}

export function showInfoToast(message, title) {
    return showToast({ type: 'info', message, title });
}

export function initFlashToasts() {
    const flashes = window.__FLASH_TOASTS__;

    if (!Array.isArray(flashes)) {
        return;
    }

    flashes.forEach((flash, index) => {
        if (!flash?.message) {
            return;
        }

        window.setTimeout(() => {
            showToast({
                type: flash.type || 'info',
                message: flash.message,
                title: flash.title,
            });
        }, index * 120);
    });

    delete window.__FLASH_TOASTS__;
}

export function initToast() {
    ensureContainer();
    initFlashToasts();

    window.showAppToast = showToast;
    window.showSuccessToast = showSuccessToast;
    window.showErrorToast = showErrorToast;
    window.showWarningToast = showWarningToast;
    window.showInfoToast = showInfoToast;
}
