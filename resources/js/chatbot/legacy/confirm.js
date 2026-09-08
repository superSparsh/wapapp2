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
