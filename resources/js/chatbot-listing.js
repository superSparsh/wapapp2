import { showAppAlert, showAppConfirm } from './confirm-dialog.js';

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
}

function setToggleSwitchActive(toggle, active) {
    if (!toggle) {
        return;
    }

    toggle.setAttribute('aria-checked', active ? 'true' : 'false');
    toggle.classList.toggle('bg-green-500', active);
    toggle.classList.toggle('bg-green-50', !active);

    const knob = toggle.querySelector('span');

    if (knob) {
        knob.classList.toggle('left-[24px]', active);
        knob.classList.toggle('left-[2px]', !active);
    }
}

function updateStatusBadge(row, active) {
    const badge = row?.querySelector('[data-chatbot-status]');

    if (!badge) {
        return;
    }

    if (active) {
        badge.textContent = 'Active';
        badge.className = 'inline-flex items-center justify-center rounded bg-[rgba(0,128,0,0.1)] px-2 py-1 text-[10px] font-medium leading-[1.2] whitespace-nowrap text-[green]';

        return;
    }

    badge.textContent = 'Inactive';
    badge.className = 'inline-flex items-center justify-center rounded bg-[rgba(0,0,0,0.1)] px-2 py-1 text-[10px] font-medium leading-[1.2] whitespace-nowrap text-text-muted';
}

export function initChatbotListingToggle() {
    document.querySelectorAll('[data-chatbot-toggle]').forEach((form) => {
        if (form.dataset.chatbotToggleBound) {
            return;
        }

        form.dataset.chatbotToggleBound = '1';

        form.addEventListener('submit', async (event) => {
            event.preventDefault();

            const button = form.querySelector('button[type="submit"]');
            const toggle = form.querySelector('[role="switch"]');
            const previous = toggle?.getAttribute('aria-checked') === 'true';
            const nextActive = !previous;

            const confirmed = await showAppConfirm({
                title: nextActive ? 'Enable chatbot?' : 'Disable chatbot?',
                message: nextActive
                    ? 'This chatbot will start responding to trigger keywords.'
                    : 'This chatbot will stop responding to messages.',
                variant: nextActive ? 'default' : 'danger',
                confirmLabel: nextActive ? 'Enable' : 'Disable',
            });

            if (!confirmed) {
                return;
            }

            if (button) {
                button.disabled = true;
            }

            try {
                const response = await fetch(form.action, {
                    method: 'PATCH',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': csrfToken(),
                    },
                    credentials: 'same-origin',
                });

                const payload = await response.json().catch(() => ({}));

                if (!response.ok) {
                    const message = payload.errors?.flow?.[0]
                        || payload.message
                        || 'Unable to update chatbot status.';

                    setToggleSwitchActive(toggle, previous);
                    await showAppAlert(message, 'Chatbot status');

                    return;
                }

                const active = Boolean(payload.active);
                setToggleSwitchActive(toggle, active);
                updateStatusBadge(form.closest('tr'), active);
            } catch {
                window.location.reload();
            } finally {
                if (button) {
                    button.disabled = false;
                }
            }
        });
    });
}
