export function confirmChatbotAction({
    title,
    message,
    confirmLabel = 'Confirm',
    variant = 'danger',
}) {
    if (typeof window.showAppConfirm === 'function') {
        return window.showAppConfirm({ title, message, confirmLabel, variant });
    }

    return Promise.resolve(window.confirm(message));
}

export function alertChatbotAction(message, title = 'Notice') {
    if (typeof window.showAppAlert === 'function') {
        return window.showAppAlert(message, title);
    }

    window.alert(message);

    return Promise.resolve();
}

/**
 * Ask which WhatsApp number this chatbot should use (multiple lines only).
 * @param {{ lines: Array<{uuid: string, label: string}>, selectedUuid?: string|null, title?: string, message?: string }} options
 * @returns {Promise<string|null>} selected line uuid, or null if cancelled
 */
export function promptChatbotLineChoice({
    lines,
    selectedUuid = null,
    title = 'Choose WhatsApp number',
    message = 'Which WhatsApp number should this chatbot reply on?',
}) {
    if (!Array.isArray(lines) || lines.length === 0) {
        return Promise.resolve(null);
    }

    if (lines.length === 1) {
        return Promise.resolve(lines[0].uuid);
    }

    return new Promise((resolve) => {
        const existing = document.getElementById('chatbot-line-picker-modal');
        if (existing) {
            existing.remove();
        }

        const modal = document.createElement('div');
        modal.id = 'chatbot-line-picker-modal';
        modal.className = 'fixed inset-0 z-[10000] flex items-center justify-center bg-black/40 p-4';
        modal.innerHTML = `
          <div class="w-full max-w-md rounded-xl bg-white p-5 shadow-lg" role="dialog" aria-modal="true">
            <h3 class="text-lg font-semibold text-gray-900"></h3>
            <p class="mt-1 text-sm text-gray-600"></p>
            <label class="mt-4 block text-sm font-medium text-gray-700" for="chatbot-line-picker-select">WhatsApp number</label>
            <select id="chatbot-line-picker-select" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"></select>
            <div class="mt-5 flex justify-end gap-2">
              <button type="button" data-line-cancel class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700">Cancel</button>
              <button type="button" data-line-confirm class="rounded-lg bg-green-500 px-4 py-2 text-sm font-semibold text-white">Save on this number</button>
            </div>
          </div>
        `;

        modal.querySelector('h3').textContent = title;
        modal.querySelector('p').textContent = message;

        const select = modal.querySelector('#chatbot-line-picker-select');
        lines.forEach((line) => {
            const option = document.createElement('option');
            option.value = line.uuid;
            option.textContent = line.label || line.uuid;
            if (line.uuid === selectedUuid) {
                option.selected = true;
            }
            select.appendChild(option);
        });

        const cleanup = (value) => {
            modal.remove();
            resolve(value);
        };

        modal.querySelector('[data-line-cancel]')?.addEventListener('click', () => cleanup(null));
        modal.addEventListener('click', (event) => {
            if (event.target === modal) {
                cleanup(null);
            }
        });
        modal.querySelector('[data-line-confirm]')?.addEventListener('click', () => {
            cleanup(select?.value || null);
        });

        document.body.appendChild(modal);
        select?.focus();
    });
}

export function requestNodeDelete(nodeId) {
    confirmChatbotAction({
        title: 'Delete node?',
        message: 'Are you sure you want to delete this node? This cannot be undone.',
        confirmLabel: 'Delete',
        variant: 'danger',
    }).then((confirmed) => {
        if (!confirmed) {
            return;
        }

        window.dispatchEvent(new CustomEvent('deleteNode', { detail: { nodeId } }));
    });
}

export function requestConnectionDelete(onConfirm) {
    return confirmChatbotAction({
        title: 'Delete connection?',
        message: 'Are you sure you want to delete this connection?',
        confirmLabel: 'Delete',
        variant: 'danger',
    }).then((confirmed) => {
        if (confirmed && typeof onConfirm === 'function') {
            onConfirm();
        }

        return confirmed;
    });
}
