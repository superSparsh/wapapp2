/**
 * Shared maximize/minimize for flow builders (chatbot, WhatsApp flows, drip canvas).
 */
export function initBuilderSectionMaximize() {
    document.querySelectorAll('[data-builder-maximize-section]').forEach((section) => {
        const workspace = section.closest('[data-builder-workspace]') ?? document;
        const maxBtn = workspace.querySelector('[data-action="maximize"]');
        const maxLabel = workspace.querySelector('[data-maximize-label]') ?? document.getElementById('maximize-label');
        const overlayToolbar = section.querySelector('[data-builder-maximize-toolbar]');

        if (!maxBtn) {
            return;
        }

        const setMaximized = (isMaximized) => {
            section.classList.toggle('builder-maximized', isMaximized);
            document.body.classList.toggle('builder-fullscreen', isMaximized);
            maxBtn.setAttribute('aria-pressed', String(isMaximized));

            if (maxLabel) {
                maxLabel.textContent = isMaximized ? 'Minimize' : 'Maximize';
            }

            if (overlayToolbar) {
                overlayToolbar.classList.toggle('hidden', !isMaximized);
                overlayToolbar.hidden = !isMaximized;
            }
        };

        maxBtn.addEventListener('click', () => {
            setMaximized(!section.classList.contains('builder-maximized'));
        });

        section.querySelectorAll('[data-builder-minimize]').forEach((button) => {
            button.addEventListener('click', () => {
                setMaximized(false);
            });
        });
    });
}

export function initDripCanvasMaximize() {
    document.querySelectorAll('[data-drip-workspace]').forEach((workspace) => {
        const button = workspace.querySelector('[data-drip-maximize]');
        const minimizeBtn = workspace.querySelector('[data-drip-minimize]');
        const sidePanel = workspace.querySelector('[data-drip-side-panel]');

        if (!button || !sidePanel) {
            return;
        }

        const setMaximized = (next) => {
            workspace.setAttribute('data-drip-maximized', String(next));
            button.setAttribute('aria-pressed', String(next));
            button.setAttribute('aria-label', next ? 'Restore canvas' : 'Maximize canvas');

            if (minimizeBtn) {
                minimizeBtn.classList.toggle('hidden', !next);
                minimizeBtn.hidden = !next;
            }
        };

        button.addEventListener('click', () => {
            const maximized = workspace.getAttribute('data-drip-maximized') === 'true';
            setMaximized(!maximized);
        });

        minimizeBtn?.addEventListener('click', () => {
            setMaximized(false);
        });
    });
}

export function initInboxMaximize() {
    const workspace = document.querySelector('[data-inbox-workspace]');
    const button = workspace?.querySelector('[data-inbox-maximize]');
    const minimizeBtn = workspace?.querySelector('[data-inbox-minimize]');

    if (!workspace || !button) {
        return;
    }

    const setMaximized = (next) => {
        workspace.setAttribute('data-inbox-maximized', String(next));
        button.setAttribute('aria-pressed', String(next));
        button.setAttribute('aria-label', next ? 'Restore conversation' : 'Maximize conversation');

        if (minimizeBtn) {
            minimizeBtn.classList.toggle('hidden', !next);
            minimizeBtn.hidden = !next;
        }
    };

    button.addEventListener('click', () => {
        const maximized = workspace.getAttribute('data-inbox-maximized') === 'true';
        setMaximized(!maximized);
    });

    minimizeBtn?.addEventListener('click', () => {
        setMaximized(false);
    });
}
