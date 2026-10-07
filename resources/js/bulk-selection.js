import { showAppConfirm } from './confirm-dialog.js';
import { showToast } from './toast.js';

function getCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content || '';
}

/**
 * Templates-style bulk row selection + delete.
 *
 * Root attributes:
 * - data-bulk-selection
 * - data-bulk-destroy-url
 * - data-bulk-confirm-title (optional)
 * - data-bulk-confirm-message (optional, use {count})
 * - data-bulk-success-message (optional)
 * - data-bulk-body-key (optional, default "uuids")
 */
export function initBulkSelection(root = document) {
    root.querySelectorAll('[data-bulk-selection]').forEach((scope) => {
        if (scope.dataset.bulkSelectionBound === '1') {
            return;
        }
        scope.dataset.bulkSelectionBound = '1';
        bindBulkSelection(scope);
    });
}

function bindBulkSelection(scope) {
    const bulkUrl = scope.dataset.bulkDestroyUrl;
    const bodyKey = scope.dataset.bulkBodyKey || 'uuids';
    const confirmTitle = scope.dataset.bulkConfirmTitle || 'Delete selected';
    const confirmMessage =
        scope.dataset.bulkConfirmMessage || 'Delete {count} selected item(s)? This cannot be undone.';
    const successMessage = scope.dataset.bulkSuccessMessage || 'Selected items deleted.';

    const bulkBar = scope.querySelector('[data-bulk-actions]');
    const bulkCount = scope.querySelector('[data-bulk-count]');
    const selectAll = scope.querySelector('[data-bulk-select-all]');
    const deleteBtn = scope.querySelector('[data-bulk-delete]');

    const checkboxes = () => [...scope.querySelectorAll('[data-bulk-row-checkbox]')];

    const updateBulkBar = () => {
        const selected = checkboxes().filter((box) => box.checked);
        if (bulkBar) {
            bulkBar.classList.toggle('hidden', selected.length === 0);
            bulkBar.classList.toggle('flex', selected.length > 0);
        }
        if (bulkCount) {
            bulkCount.textContent = String(selected.length);
        }
        if (selectAll) {
            const all = checkboxes();
            selectAll.checked = all.length > 0 && selected.length === all.length;
            selectAll.indeterminate = selected.length > 0 && selected.length < all.length;
        }
    };

    selectAll?.addEventListener('change', () => {
        checkboxes().forEach((box) => {
            box.checked = Boolean(selectAll.checked);
        });
        updateBulkBar();
    });

    scope.addEventListener('change', (event) => {
        if (event.target instanceof HTMLInputElement && event.target.matches('[data-bulk-row-checkbox]')) {
            updateBulkBar();
        }
    });

    deleteBtn?.addEventListener('click', async () => {
        const ids = checkboxes()
            .filter((box) => box.checked)
            .map((box) => box.value)
            .filter(Boolean);

        if (!ids.length || !bulkUrl) {
            return;
        }

        const confirmed = await showAppConfirm({
            title: confirmTitle,
            message: confirmMessage.replace('{count}', String(ids.length)),
            confirmLabel: 'Delete',
            variant: 'danger',
        });

        if (!confirmed) {
            return;
        }

        try {
            const response = await fetch(bulkUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                body: JSON.stringify({ [bodyKey]: ids }),
            });

            const data = await response.json().catch(() => ({}));
            if (!response.ok) {
                throw new Error(data.message || 'Bulk delete failed.');
            }

            showToast({ message: data.message || successMessage, type: 'success' });
            window.location.reload();
        } catch (error) {
            showToast({
                message: error instanceof Error ? error.message : 'Bulk delete failed.',
                type: 'error',
            });
        }
    });

    updateBulkBar();
}
