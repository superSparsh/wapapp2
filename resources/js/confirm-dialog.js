let pendingAction = null;
let pendingResolve = null;

function getDialog() {
    return document.getElementById('app-confirm-dialog');
}

function closeDialog() {
    const dialog = getDialog();
    if (!dialog) {
        return;
    }

    dialog.classList.add('hidden');
    dialog.classList.remove('flex');
    dialog.setAttribute('aria-hidden', 'true');
    pendingAction = null;
    pendingResolve = null;
}

function openDialog({ title, message, variant = 'default', mode = 'confirm', confirmLabel = 'Confirm' }) {
    const dialog = getDialog();
    if (!dialog) {
        return false;
    }

    const titleEl = dialog.querySelector('[data-confirm-title]');
    const messageEl = dialog.querySelector('[data-confirm-message]');
    const confirmBtn = dialog.querySelector('[data-confirm-submit]');
    const confirmActions = dialog.querySelector('[data-confirm-actions]');
    const alertActions = dialog.querySelector('[data-alert-actions]');

    if (titleEl) {
        titleEl.textContent = title;
    }

    if (messageEl) {
        messageEl.textContent = message;
    }

    if (confirmBtn) {
        confirmBtn.textContent = confirmLabel;
        confirmBtn.classList.remove('bg-green-500', 'text-primary-2', 'bg-[var(--color-danger)]', 'text-white');
        if (variant === 'danger') {
            confirmBtn.classList.add('bg-[var(--color-danger)]', 'text-white');
        } else {
            confirmBtn.classList.add('bg-green-500', 'text-primary-2');
        }
    }

    confirmActions?.classList.toggle('hidden', mode === 'alert');
    confirmActions?.classList.toggle('flex', mode !== 'alert');
    alertActions?.classList.toggle('hidden', mode !== 'alert');
    alertActions?.classList.toggle('flex', mode === 'alert');

    dialog.classList.remove('hidden');
    dialog.classList.add('flex');
    dialog.setAttribute('aria-hidden', 'false');

    return true;
}

function dismissDialog(confirmed = false) {
    if (confirmed) {
        pendingAction?.();
    } else {
        pendingResolve?.(false);
    }
    closeDialog();
}

export function showAppAlert(message, title = 'Notice') {
    return new Promise((resolve) => {
        pendingResolve = resolve;
        pendingAction = () => resolve();

        if (!openDialog({ title, message, mode: 'alert' })) {
            window.alert(message);
            resolve();
        }
    });
}

export function showAppConfirm({
    message,
    title = 'Are you sure?',
    variant = 'danger',
    confirmLabel = 'Confirm',
}) {
    return new Promise((resolve) => {
        pendingResolve = resolve;
        pendingAction = () => resolve(true);

        if (!openDialog({ title, message, variant, confirmLabel })) {
            resolve(window.confirm(message));
        }
    });
}

export function initConfirmDialog() {
    const dialog = getDialog();
    if (!dialog) {
        return;
    }

    if (typeof window !== 'undefined') {
        window.showAppAlert = showAppAlert;
        window.showAppConfirm = showAppConfirm;
    }

    dialog.querySelector('[data-confirm-cancel]')?.addEventListener('click', () => dismissDialog(false));
    dialog.querySelector('[data-confirm-backdrop]')?.addEventListener('click', () => dismissDialog(false));
    dialog.querySelector('[data-alert-ok]')?.addEventListener('click', () => dismissDialog(true));
    dialog.querySelector('[data-confirm-submit]')?.addEventListener('click', () => dismissDialog(true));

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && dialog.classList.contains('flex')) {
            dismissDialog(false);
        }
    });

    document.addEventListener(
        'submit',
        (event) => {
            const form = event.target instanceof HTMLFormElement ? event.target : null;
            if (!form?.dataset.confirm || form.dataset.confirmBypass === 'true') {
                return;
            }

            event.preventDefault();
            event.stopPropagation();

            showAppConfirm({
                title: form.dataset.confirmTitle || 'Confirm action',
                message: form.dataset.confirm,
                variant: form.dataset.confirmVariant || 'danger',
                confirmLabel: form.dataset.confirmLabel || 'Delete',
            }).then((confirmed) => {
                if (!confirmed) {
                    return;
                }

                form.dataset.confirmBypass = 'true';
                form.requestSubmit();
                delete form.dataset.confirmBypass;
            });
        },
        true,
    );

    document.addEventListener('click', (event) => {
        const target = event.target instanceof Element ? event.target.closest('[data-confirm]') : null;
        if (!target || target instanceof HTMLFormElement) {
            return;
        }

        if (target instanceof HTMLAnchorElement && target.href) {
            event.preventDefault();

            showAppConfirm({
                title: target.dataset.confirmTitle || 'Confirm action',
                message: target.dataset.confirm,
                variant: target.dataset.confirmVariant || 'danger',
                confirmLabel: target.dataset.confirmLabel || 'Continue',
            }).then((confirmed) => {
                if (confirmed) {
                    window.location.href = target.href;
                }
            });
        }
    });
}
