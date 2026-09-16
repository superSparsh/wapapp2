import { showAppConfirm } from './confirm-dialog.js';
import { showToast } from './toast.js';

function getCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content || '';
}

const STATUS_VARIANT_CLASSES = {
    'fd-approved': 'bg-green-50 text-green-700',
    'fd-draft': 'bg-blue-50 text-primary-2',
    'fd-error': 'bg-danger/10 text-danger',
    'fd-type': 'bg-indigo-50 text-indigo-700',
    'fd-category': 'bg-violet-50 text-violet-700',
    'fd-category-marketing': 'bg-pink-50 text-pink-700',
    'fd-category-utility': 'bg-sky-50 text-sky-700',
    'fd-category-auth': 'bg-amber-50 text-amber-800',
    'fd-category-carousel': 'bg-fuchsia-50 text-fuchsia-700',
    'fd-category-lto': 'bg-orange-50 text-orange-700',
    approved: 'bg-stat-emerald/15 text-stat-emerald',
    pending: 'bg-stat-orange/15 text-stat-orange',
    rejected: 'bg-danger/10 text-danger',
    default: 'bg-muted-surface text-text-body',
};

function applyStatusChip(chip, label, variant) {
    if (!chip) {
        return;
    }

    chip.textContent = label || '—';
    chip.className = `fd-status-chip inline-flex items-center rounded px-2 py-1 ${STATUS_VARIANT_CLASSES[variant] || STATUS_VARIANT_CLASSES.default}`;
}

function initStatusPolling(root) {
    const statusesUrl = root.dataset.statusesUrl;
    if (!statusesUrl) {
        return;
    }

    const pollMs = Math.max(5000, Number(root.dataset.statusPollMs || 15000));

    const poll = async () => {
        if (document.hidden) {
            return;
        }

        const rows = [...root.querySelectorAll('[data-template-row][data-template-uuid]')];
        const uuids = rows.map((row) => row.dataset.templateUuid).filter(Boolean);
        if (!uuids.length) {
            return;
        }

        try {
            const params = new URLSearchParams();
            uuids.forEach((uuid) => params.append('uuids[]', uuid));
            const response = await fetch(`${statusesUrl}?${params.toString()}`, {
                headers: { Accept: 'application/json' },
            });

            if (!response.ok) {
                return;
            }

            const data = await response.json();
            const byUuid = new Map((data.items || []).map((item) => [item.uuid, item]));

            rows.forEach((row) => {
                const item = byUuid.get(row.dataset.templateUuid);
                if (!item) {
                    return;
                }

                const chip = row.querySelector('[data-template-status-chip]');
                applyStatusChip(chip, item.status, item.status_variant);

                const rejectionWrap = row.querySelector('[data-template-rejection-wrap]');
                const rejectionTitle = row.querySelector('[data-template-rejection-title]');
                const rejectionText = row.querySelector('[data-template-rejection-text]');
                const rejectionHint = row.querySelector('[data-template-rejection-hint]');
                if (rejectionWrap) {
                    const show = Boolean(item.error);
                    rejectionWrap.classList.toggle('hidden', !show);
                    if (rejectionTitle) {
                        rejectionTitle.textContent = item.rejection_title || 'Submission failed';
                    }
                    if (rejectionText) {
                        rejectionText.textContent = item.rejection_reason
                            || 'No error details were returned by WhatsApp.';
                    }
                    if (rejectionHint) {
                        const hint = item.rejection_hint || '';
                        rejectionHint.textContent = hint;
                        rejectionHint.classList.toggle('hidden', hint === '');
                    }
                }
            });
        } catch {
            // Ignore transient poll errors.
        }
    };

    poll();
    window.setInterval(poll, pollMs);
}

export function initTemplatesIndex() {
    const root = document.querySelector('[data-templates-index]');
    if (!root) {
        return;
    }

    initStatusPolling(root);

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
