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
 * Uses a clickable list (not native <select>) so options never open behind the modal.
 *
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

        let currentUuid =
            selectedUuid && lines.some((line) => line.uuid === selectedUuid)
                ? selectedUuid
                : lines[0].uuid;

        const modal = document.createElement('div');
        modal.id = 'chatbot-line-picker-modal';
        // Inline z-index: Tailwind may not emit arbitrary classes from JS strings.
        Object.assign(modal.style, {
            position: 'fixed',
            inset: '0',
            zIndex: '2147483646',
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
            background: 'rgba(0, 0, 0, 0.45)',
            padding: '16px',
        });

        const panel = document.createElement('div');
        Object.assign(panel.style, {
            width: '100%',
            maxWidth: '28rem',
            borderRadius: '12px',
            background: '#fff',
            padding: '20px',
            boxShadow: '0 20px 40px rgba(0,0,0,0.2)',
            position: 'relative',
            zIndex: '2147483647',
        });
        panel.setAttribute('role', 'dialog');
        panel.setAttribute('aria-modal', 'true');

        const heading = document.createElement('h3');
        heading.textContent = title;
        Object.assign(heading.style, {
            margin: '0',
            fontSize: '18px',
            fontWeight: '600',
            color: '#111827',
        });

        const description = document.createElement('p');
        description.textContent = message;
        Object.assign(description.style, {
            margin: '8px 0 0',
            fontSize: '14px',
            color: '#4b5563',
        });

        const listLabel = document.createElement('div');
        listLabel.textContent = 'WhatsApp number';
        Object.assign(listLabel.style, {
            marginTop: '16px',
            marginBottom: '8px',
            fontSize: '13px',
            fontWeight: '600',
            color: '#374151',
        });

        const list = document.createElement('div');
        Object.assign(list.style, {
            display: 'flex',
            flexDirection: 'column',
            gap: '8px',
            maxHeight: '240px',
            overflowY: 'auto',
        });

        const paintList = () => {
            list.innerHTML = '';
            lines.forEach((line) => {
                const selected = line.uuid === currentUuid;
                const item = document.createElement('button');
                item.type = 'button';
                item.dataset.lineUuid = line.uuid;
                Object.assign(item.style, {
                    display: 'flex',
                    alignItems: 'center',
                    gap: '10px',
                    width: '100%',
                    textAlign: 'left',
                    borderRadius: '8px',
                    border: selected ? '2px solid #22c55e' : '1px solid #d1d5db',
                    background: selected ? '#f0fdf4' : '#fff',
                    padding: '10px 12px',
                    cursor: 'pointer',
                    fontSize: '14px',
                    fontWeight: selected ? '600' : '500',
                    color: '#111827',
                });

                const radio = document.createElement('span');
                Object.assign(radio.style, {
                    width: '16px',
                    height: '16px',
                    borderRadius: '999px',
                    border: selected ? '5px solid #22c55e' : '2px solid #9ca3af',
                    flexShrink: '0',
                    boxSizing: 'border-box',
                });

                const label = document.createElement('span');
                label.textContent = line.label || line.uuid;

                item.appendChild(radio);
                item.appendChild(label);
                item.addEventListener('click', () => {
                    currentUuid = line.uuid;
                    paintList();
                });
                list.appendChild(item);
            });
        };

        paintList();

        const actions = document.createElement('div');
        Object.assign(actions.style, {
            marginTop: '20px',
            display: 'flex',
            justifyContent: 'flex-end',
            gap: '8px',
        });

        const cancelBtn = document.createElement('button');
        cancelBtn.type = 'button';
        cancelBtn.textContent = 'Cancel';
        Object.assign(cancelBtn.style, {
            borderRadius: '8px',
            border: '1px solid #d1d5db',
            background: '#fff',
            padding: '8px 16px',
            fontSize: '14px',
            fontWeight: '600',
            color: '#374151',
            cursor: 'pointer',
        });

        const confirmBtn = document.createElement('button');
        confirmBtn.type = 'button';
        confirmBtn.textContent = 'Save on this number';
        Object.assign(confirmBtn.style, {
            borderRadius: '8px',
            border: 'none',
            background: '#22c55e',
            padding: '8px 16px',
            fontSize: '14px',
            fontWeight: '600',
            color: '#fff',
            cursor: 'pointer',
        });

        const cleanup = (value) => {
            modal.remove();
            document.removeEventListener('keydown', onKeyDown);
            resolve(value);
        };

        const onKeyDown = (event) => {
            if (event.key === 'Escape') {
                cleanup(null);
            }
        };

        cancelBtn.addEventListener('click', () => cleanup(null));
        confirmBtn.addEventListener('click', () => cleanup(currentUuid));
        modal.addEventListener('click', (event) => {
            if (event.target === modal) {
                cleanup(null);
            }
        });
        document.addEventListener('keydown', onKeyDown);

        actions.appendChild(cancelBtn);
        actions.appendChild(confirmBtn);
        panel.appendChild(heading);
        panel.appendChild(description);
        panel.appendChild(listLabel);
        panel.appendChild(list);
        panel.appendChild(actions);
        modal.appendChild(panel);
        document.body.appendChild(modal);
        confirmBtn.focus();
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
