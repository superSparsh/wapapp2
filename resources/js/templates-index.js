import { showAppConfirm } from './confirm-dialog.js';
import { showToast } from './toast.js';

function getCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content || '';
}

export function initTemplatesIndex() {
    const root = document.querySelector('[data-templates-index]');
    if (!root) {
        return;
    }

    const bulkUrl = root.dataset.bulkDestroyUrl;
    const bulkBar = document.getElementById('templates-bulk-actions');
    const bulkCount = bulkBar?.querySelector('[data-bulk-count]');
    const selectAll = document.getElementById('templates-select-all');
    const checkboxes = () => [...document.querySelectorAll('.template-row-checkbox')];

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
            box.checked = selectAll.checked;
        });
        updateBulkBar();
    });

    root.addEventListener('change', (event) => {
        if (event.target.matches('.template-row-checkbox')) {
            updateBulkBar();
        }
    });

    document.getElementById('templates-bulk-delete')?.addEventListener('click', async () => {
        const uuids = checkboxes().filter((box) => box.checked).map((box) => box.value);
        if (!uuids.length || !bulkUrl) {
            return;
        }

        const confirmed = await showAppConfirm({
            title: 'Delete templates',
            message: `Delete ${uuids.length} selected template(s)? This cannot be undone.`,
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
                },
                body: JSON.stringify({ uuids }),
            });

            const data = await response.json();
            if (!response.ok) {
                throw new Error(data.message || 'Bulk delete failed.');
            }

            showToast({ message: data.message || 'Templates deleted.', type: 'success' });
            window.location.reload();
        } catch (error) {
            showToast({ message: error.message || 'Bulk delete failed.', type: 'error' });
        }
    });
}
