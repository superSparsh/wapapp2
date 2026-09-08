import './echo.js';
import './form-validation.js';
import { initThemedSelects, initThemedSelectObserver } from './themed-select.js';
import { initConfirmDialog, showAppAlert } from './confirm-dialog.js';
import { initToast } from './toast.js';
import { initTemplateBuilder } from './template-builder.js';
import { initTemplatesIndex } from './templates-index.js';
import { initFreeTemplateBuilder } from './free-template-builder.js';
import { initListingFilters, applyServerFieldErrors } from './listing-filters.js';
import { initBuilderSectionMaximize, initDripCanvasMaximize, initInboxMaximize } from './builder-maximize.js';
import { initChatbotListingToggle } from './chatbot-listing.js';
import { initCampaignWizard } from './campaign-wizard.js';
import { initDashboard } from './dashboard.js';

document.addEventListener('DOMContentLoaded', () => {
    initToast();
    initThemeToggle();
    initTabToggle();
    initPasswordToggle();
    initOtpInputs();
    initLoginOtp();
    initAuthFeaturesCarousel();
    initThemedSelects();
    initThemedSelectObserver();
    initCampaignWizard();
    initDashboard();
    initInboxModals();
    initInboxMessageMenu();
    initInboxChat();
    initInboxOutboundModals();
    initInboxTeamFeatures();
    initInboxAddContact();
    initInboxSearch();
    initInboxMaximize();
    initGlobalSearch();
    initInboxFilters();
    initDripCanvasMaximize();
    initBuilderSectionMaximize();
    initTeamPage();
    initTemplateBuilder();
    initTemplatesIndex();
    initFreeTemplateBuilder();
    initConfirmDialog();
    initListingFilters();
    initChatbotListingToggle();

    if (window.__SERVER_VALIDATION_ERRORS__) {
        applyServerFieldErrors(window.__SERVER_VALIDATION_ERRORS__);
    }
});

function getPreferredTheme() {
    const stored = window.localStorage.getItem('theme');

    if (stored === 'light' || stored === 'dark') {
        return stored;
    }

    return 'light';
}

function applyTheme(theme) {
    const isDark = theme === 'dark';
    document.documentElement.classList.toggle('dark', isDark);

    document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
        button.setAttribute('aria-pressed', String(isDark));
        button.setAttribute('aria-label', isDark ? 'Switch to light mode' : 'Switch to dark mode');
    });
}

function animateThemeChange(theme) {
    const root = document.documentElement;
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    if (reduceMotion) {
        applyTheme(theme);
        return;
    }

    root.classList.add('theme-animate');
    // Force style flush so the transition class applies before the theme flip
    void root.offsetWidth;
    applyTheme(theme);

    window.setTimeout(() => {
        root.classList.remove('theme-animate');
    }, 400);
}

function initThemeToggle() {
    applyTheme(getPreferredTheme());

    document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
        button.addEventListener('click', () => {
            const next = document.documentElement.classList.contains('dark') ? 'light' : 'dark';
            window.localStorage.setItem('theme', next);
            animateThemeChange(next);
        });
    });
}

function initTabToggle() {
    const container = document.querySelector('[data-tab-toggle]');
    if (!container) return;

    const buttons = container.querySelectorAll('[data-tab-button]');
    const panels = document.querySelectorAll('[data-tab-panel]');

    buttons.forEach((button) => {
        button.addEventListener('click', () => {
            const targetId = button.dataset.tabTarget;
            const index = Number(button.dataset.tabIndex);

            buttons.forEach((btn, i) => {
                const isActive = i === index;
                btn.classList.toggle('border-green-500', isActive);
                btn.classList.toggle('bg-green-50', isActive);
                btn.classList.toggle('text-green-500', isActive);
                btn.classList.toggle('shadow-[0px_1px_2px_0px_rgba(35,39,46,0.08)]', isActive);
                btn.classList.toggle('border-blue-50', !isActive);
                btn.classList.toggle('bg-elevated', !isActive);
                btn.classList.toggle('text-blue-200', !isActive);
            });

            panels.forEach((panel) => {
                const isTarget = panel.id === targetId;
                panel.classList.toggle('hidden', !isTarget);
                panel.classList.toggle('flex', isTarget);
            });
        });
    });
}

function initPasswordToggle() {
    document.querySelectorAll('[data-password-toggle]').forEach((button) => {
        button.addEventListener('click', () => {
            const input = button.closest('.relative')?.querySelector('input');
            if (!input) return;

            const isPassword = input.type === 'password';
            input.type = isPassword ? 'text' : 'password';
            button.setAttribute('aria-label', isPassword ? 'Hide password' : 'Show password');
        });
    });
}

function initOtpInputs() {
    document.querySelectorAll('[data-otp-inputs]').forEach((container) => {
        const inputs = [...container.querySelectorAll('input')];

        inputs.forEach((input, index) => {
            input.addEventListener('input', () => {
                input.value = input.value.replace(/\D/g, '').slice(0, 1);

                if (input.value && index < inputs.length - 1) {
                    inputs[index + 1].focus();
                }
            });

            input.addEventListener('keydown', (event) => {
                if (event.key === 'Backspace' && !input.value && index > 0) {
                    inputs[index - 1].focus();
                }
            });

            input.addEventListener('paste', (event) => {
                event.preventDefault();
                const digits = event.clipboardData.getData('text').replace(/\D/g, '').slice(0, inputs.length);

                digits.split('').forEach((digit, i) => {
                    if (inputs[i]) {
                        inputs[i].value = digit;
                    }
                });

                const nextIndex = Math.min(digits.length, inputs.length - 1);
                inputs[nextIndex]?.focus();
            });
        });
    });
}

function initLoginOtp() {
    const form = document.querySelector('[data-login-otp-form]');
    if (!form) return;

    const phoneInput = form.querySelector('[data-login-otp-phone]');
    const sendButton = form.querySelector('[data-login-otp-send]');
    const hiddenOtp = form.querySelector('[data-login-otp-hidden]');
    const digitInputs = [...form.querySelectorAll('[data-login-otp-digit]')];
    const successMessage = form.querySelector('[data-login-otp-success]');
    const errorMessage = form.querySelector('[data-login-otp-error]');
    const timerMessage = form.querySelector('[data-login-otp-timer]');
    const csrf = form.querySelector('input[name="_token"]')?.value;
    let timerId = null;

    const syncOtp = () => {
        hiddenOtp.value = digitInputs.map((input) => input.value).join('');
    };

    digitInputs.forEach((input) => {
        input.addEventListener('input', syncOtp);
    });

    const showError = (message) => {
        errorMessage.textContent = message;
        errorMessage.classList.remove('hidden');
        successMessage.classList.add('hidden');
    };

    const showSuccess = (message) => {
        successMessage.textContent = message;
        successMessage.classList.remove('hidden');
        errorMessage.classList.add('hidden');
    };

    const startCooldown = (seconds) => {
        let remaining = seconds;
        sendButton.disabled = true;
        timerMessage.classList.remove('hidden');

        const tick = () => {
            timerMessage.textContent = `Resend available in ${remaining}s`;
            remaining -= 1;

            if (remaining < 0) {
                clearInterval(timerId);
                timerId = null;
                sendButton.disabled = false;
                sendButton.textContent = 'Resend OTP';
                timerMessage.classList.add('hidden');
                return;
            }

            timerId = window.setTimeout(tick, 1000);
        };

        tick();
    };

    sendButton?.addEventListener('click', async () => {
        const phone = phoneInput?.value?.trim() ?? '';

        if (!/^[6-9]\d{9}$/.test(phone.replace(/\D/g, '').slice(-10))) {
            showError('Please enter a valid 10-digit mobile number.');
            return;
        }

        sendButton.disabled = true;
        sendButton.textContent = 'Sending...';
        errorMessage.classList.add('hidden');

        try {
            const response = await fetch(sendButton.dataset.sendUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrf ?? '',
                },
                body: JSON.stringify({ phone }),
            });

            const payload = await response.json();

            if (!response.ok || !payload.success) {
                showError(payload.message ?? 'Failed to send OTP.');
                sendButton.disabled = false;
                sendButton.textContent = 'Send OTP';
                return;
            }

            showSuccess(payload.message ?? 'OTP sent successfully.');
            sendButton.textContent = 'OTP Sent';
            startCooldown(60);
            digitInputs[0]?.focus();
        } catch {
            showError('Something went wrong. Please try again.');
            sendButton.disabled = false;
            sendButton.textContent = 'Send OTP';
        }
    });

    form.addEventListener('submit', () => {
        syncOtp();
    });
}

function initAuthFeaturesCarousel() {
    const root = document.querySelector('[data-auth-features-carousel]');
    if (!root) return;

    let slides = [];

    try {
        slides = JSON.parse(root.dataset.slides ?? '[]');
    } catch {
        return;
    }

    if (!slides.length) return;

    const intervalMs = Number(root.dataset.interval ?? 6000);
    const titleEl = root.querySelector('[data-auth-feature-title]');
    const labelEl = root.querySelector('[data-auth-feature-label]');
    const descriptionEl = root.querySelector('[data-auth-feature-description]');
    const progressEl = root.querySelector('[data-auth-feature-progress]');
    const counterEl = root.querySelector('[data-auth-feature-counter]');
    const imageEls = [...root.querySelectorAll('[data-auth-feature-image]')];

    let index = 0;
    let timerId = null;
    let isPaused = false;

    const preloadImages = () => {
        slides.forEach((slide) => {
            const img = new Image();
            img.src = slide.image;
        });
    };

    const updateProgress = (activeIndex) => {
        const progress = ((activeIndex + 1) / slides.length) * 100;
        progressEl.style.width = `${progress}%`;
        counterEl.textContent = `${activeIndex + 1} / ${slides.length}`;
    };

    const showSlide = (nextIndex) => {
        index = (nextIndex + slides.length) % slides.length;
        const slide = slides[index];

        if (labelEl) {
            labelEl.textContent = slide.label ?? '';
        }

        titleEl.textContent = slide.title;
        descriptionEl.textContent = slide.description;

        imageEls.forEach((image) => {
            const isActive = Number(image.dataset.index) === index;
            image.classList.toggle('opacity-100', isActive);
            image.classList.toggle('translate-y-0', isActive);
            image.classList.toggle('scale-100', isActive);
            image.classList.toggle('opacity-0', !isActive);
            image.classList.toggle('translate-y-3', !isActive);
            image.classList.toggle('scale-[1.02]', !isActive);
            image.classList.toggle('pointer-events-none', !isActive);
        });

        updateProgress(index);
    };

    const startAutoRotate = () => {
        if (timerId || slides.length < 2) return;

        timerId = window.setInterval(() => {
            if (!isPaused) {
                showSlide(index + 1);
            }
        }, intervalMs);
    };

    const stopAutoRotate = () => {
        if (!timerId) return;
        clearInterval(timerId);
        timerId = null;
    };

    const pause = () => {
        isPaused = true;
    };

    const resume = () => {
        isPaused = false;
    };

    root.addEventListener('mouseenter', pause);
    root.addEventListener('mouseleave', resume);

    root.addEventListener('keydown', (event) => {
        if (event.key === 'ArrowRight') {
            pause();
            showSlide(index + 1);
            stopAutoRotate();
            startAutoRotate();
        }

        if (event.key === 'ArrowLeft') {
            pause();
            showSlide(index - 1);
            stopAutoRotate();
            startAutoRotate();
        }
    });

    let touchStartX = null;

    root.addEventListener('touchstart', (event) => {
        pause();
        touchStartX = event.touches[0]?.clientX ?? null;
    }, { passive: true });

    root.addEventListener('touchend', (event) => {
        const touchEndX = event.changedTouches[0]?.clientX ?? null;

        if (touchStartX !== null && touchEndX !== null) {
            const delta = touchEndX - touchStartX;

            if (Math.abs(delta) > 40) {
                showSlide(delta < 0 ? index + 1 : index - 1);
                stopAutoRotate();
                startAutoRotate();
            }
        }

        touchStartX = null;
        resume();
    }, { passive: true });

    preloadImages();
    showSlide(0);
    startAutoRotate();
}

function initInboxModals() {
    const modals = document.querySelectorAll('[data-modal]');
    if (!modals.length) return;

    const openModal = (id) => {
        const modal = document.getElementById(`modal-${id}`);
        if (!modal) return;
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        document.body.style.overflow = 'hidden';
    };

    const closeModal = (modal) => {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        if (!document.querySelector('[data-modal].flex')) {
            document.body.style.overflow = '';
        }
    };

    const closeAllModals = () => {
        modals.forEach((modal) => closeModal(modal));
        document.body.style.overflow = '';
    };

    document.querySelectorAll('[data-open-modal]').forEach((trigger) => {
        trigger.addEventListener('click', () => {
            if (trigger.hasAttribute('disabled') || trigger.getAttribute('aria-disabled') === 'true') {
                return;
            }

            const closeId = trigger.dataset.closeModal;
            if (closeId) {
                const current = document.getElementById(`modal-${closeId}`);
                if (current) closeModal(current);
            }
            openModal(trigger.dataset.openModal);
        });
    });

    modals.forEach((modal) => {
        modal.querySelectorAll('[data-modal-close]').forEach((btn) => {
            btn.addEventListener('click', () => closeModal(modal));
        });

        modal.addEventListener('click', (event) => {
            if (event.target === modal) {
                closeModal(modal);
            }
        });
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeAllModals();
        }
    });

    const initialModal = document.querySelector('[data-modal].flex');
    if (initialModal) {
        document.body.style.overflow = 'hidden';
    }
}

function initInboxMessageMenu() {
    const toggle = document.querySelector('[data-inbox-menu-toggle]');
    const menu = document.getElementById('inbox-message-menu');
    if (!toggle || !menu) return;

    const positionMenu = () => {
        const rect = toggle.getBoundingClientRect();
        const menuWidth = menu.offsetWidth || 240;
        const viewportPadding = 12;
        let left = rect.left;

        if (left + menuWidth > window.innerWidth - viewportPadding) {
            left = window.innerWidth - menuWidth - viewportPadding;
        }

        left = Math.max(viewportPadding, left);

        menu.style.left = `${left}px`;
        menu.style.top = `${Math.max(viewportPadding, rect.top - menu.offsetHeight - 8)}px`;
    };

    const closeMenu = () => {
        menu.classList.add('hidden');
        toggle.setAttribute('aria-expanded', 'false');
    };

    const openMenu = () => {
        menu.classList.remove('hidden');
        positionMenu();
        toggle.setAttribute('aria-expanded', 'true');
    };

    toggle.addEventListener('click', (event) => {
        event.stopPropagation();

        if (menu.classList.contains('hidden')) {
            openMenu();
        } else {
            closeMenu();
        }
    });

    menu.querySelectorAll('[data-open-modal]').forEach((item) => {
        item.addEventListener('click', () => closeMenu());
    });

    document.addEventListener('click', (event) => {
        if (!menu.contains(event.target) && !toggle.contains(event.target)) {
            closeMenu();
        }
    });

    window.addEventListener('resize', () => {
        if (!menu.classList.contains('hidden')) {
            positionMenu();
        }
    });

    window.addEventListener('scroll', () => {
        if (!menu.classList.contains('hidden')) {
            positionMenu();
        }
    }, true);

    if (!menu.classList.contains('hidden')) {
        positionMenu();
    }
}

function formatWindowExpiry(isoString) {
    if (!isoString) {
        return '';
    }

    const expiresAt = new Date(isoString);

    if (Number.isNaN(expiresAt.getTime())) {
        return '';
    }

    const diffMs = expiresAt.getTime() - Date.now();

    if (diffMs <= 0) {
        return 'expired';
    }

    const hours = Math.floor(diffMs / (1000 * 60 * 60));
    const minutes = Math.floor((diffMs % (1000 * 60 * 60)) / (1000 * 60));

    if (hours > 0) {
        return `${hours}h ${minutes}m`;
    }

    return `${minutes}m`;
}

function initInboxServiceWindow(chat) {
    const windowUrl = chat.dataset.windowUrl;

    if (!windowUrl) {
        return {
            refresh: async () => {},
            isWithinWindow: () => true,
        };
    }

    const banner = chat.querySelector('[data-inbox-window-banner]');
    const bannerText = chat.querySelector('[data-inbox-window-banner-text]');
    const input = chat.querySelector('[data-inbox-message-input]');
    const sendButton = chat.querySelector('[data-inbox-send-button]');
    const sessionActions = chat.querySelectorAll('[data-inbox-session-action]');
    const windowRequiredItems = chat.querySelectorAll('[data-inbox-requires-window]');
    const windowHours = Number(chat.dataset.windowHours || 24);

    let withinWindow = true;

    const applyWindowState = (status) => {
        withinWindow = Boolean(status?.within_window);
        chat.dataset.withinWindow = withinWindow ? '1' : '0';

        if (banner && bannerText) {
            banner.classList.remove('border-green-300', 'bg-green-50', 'text-green-900', 'border-amber-300', 'bg-amber-50', 'text-amber-900');

            if (withinWindow && status?.expires_at) {
                const remaining = formatWindowExpiry(status.expires_at);
                banner.classList.remove('hidden');
                banner.classList.add('border-green-300', 'bg-green-50', 'text-green-900');
                bannerText.textContent = `Session active for ${remaining} more (resets on customer reply, max ${status.window_hours ?? windowHours}h).`;
            } else if (!withinWindow) {
                banner.classList.remove('hidden');
                banner.classList.add('border-amber-300', 'bg-amber-50', 'text-amber-900');
                bannerText.textContent = '24-hour session expired. Send an approved template to re-open the conversation.';
            } else {
                banner.classList.add('hidden');
            }
        }

        input?.toggleAttribute('disabled', !withinWindow);
        sendButton?.toggleAttribute('disabled', !withinWindow);

        sessionActions.forEach((element) => {
            element.toggleAttribute('disabled', !withinWindow);
            element.setAttribute('aria-disabled', withinWindow ? 'false' : 'true');
            element.classList.toggle('pointer-events-none', !withinWindow);
            element.classList.toggle('opacity-50', !withinWindow);
        });

        windowRequiredItems.forEach((element) => {
            element.toggleAttribute('disabled', !withinWindow);
            element.setAttribute('aria-disabled', withinWindow ? 'false' : 'true');
            element.classList.toggle('pointer-events-none', !withinWindow);
            element.classList.toggle('opacity-50', !withinWindow);
        });
    };

    const refresh = async () => {
        try {
            const response = await fetch(windowUrl, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });

            if (!response.ok) {
                return;
            }

            const status = await response.json();
            applyWindowState(status);
        } catch {
            // Ignore transient window status errors.
        }
    };

    refresh();

    return {
        refresh,
        isWithinWindow: () => withinWindow,
    };
}

function updateThreadRow(thread) {
    if (!thread?.uuid) return;

    const row = document.querySelector(`[data-thread-uuid="${thread.uuid}"]`);
    if (!row) return;

    const preview = row.querySelector('[data-thread-preview]');
    const time = row.querySelector('[data-thread-time]');
    const unread = row.querySelector('[data-thread-unread]');

    if (preview && thread.preview !== undefined) {
        preview.textContent = thread.preview;
    }

    if (time && thread.time) {
        time.textContent = thread.time;
    }

    if (unread) {
        const count = Number(thread.unread || 0);

        if (count > 0) {
            unread.textContent = String(count);
            unread.classList.remove('hidden');
            unread.classList.add('flex');
        } else {
            unread.textContent = '';
            unread.classList.add('hidden');
            unread.classList.remove('flex');
        }
    }
}

function initInboxChat() {
    const chat = document.querySelector('[data-inbox-chat]');
    if (!chat) return;

    const form = chat.querySelector('[data-inbox-send-form]');
    const input = chat.querySelector('[data-inbox-message-input]');
    const messagesEl = chat.querySelector('[data-inbox-messages]');
    const sendUrl = chat.dataset.sendUrl;
    const messagesUrl = chat.dataset.messagesUrl;
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;

    if (!form || !input || !messagesUrl || !sendUrl || !csrf) return;

    const serviceWindow = initInboxServiceWindow(chat);
    const seenMessageUuids = new Set();

    const appendMessage = (message) => {
        if (!messagesEl || !message?.body) return;

        if (message.uuid) {
            if (seenMessageUuids.has(message.uuid)) {
                return;
            }

            seenMessageUuids.add(message.uuid);
        }

        const row = document.createElement('div');
        row.className = message.is_outbound ? 'flex justify-end' : 'flex justify-start';

        const bubble = document.createElement('div');
        bubble.className = message.is_outbound
            ? 'max-w-[640px] rounded-bl-[12px] rounded-tl-[12px] rounded-tr-[12px] bg-green-100 p-4 text-xs leading-[1.8] text-text-body'
            : 'max-w-[640px] rounded-bl-[12px] rounded-br-[12px] rounded-tr-[12px] bg-muted-surface p-4 text-xs leading-[1.8] text-text-body';
        bubble.style.fontFamily = "'Poppins', var(--font-sans)";
        bubble.textContent = message.body;

        row.appendChild(bubble);
        messagesEl.appendChild(row);
        messagesEl.scrollTop = messagesEl.scrollHeight;
    };

    const refreshMessages = async () => {
        try {
            const response = await fetch(messagesUrl, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });

            if (!response.ok) return;

            const data = await response.json();
            if (!Array.isArray(data.items) || !messagesEl) return;

            messagesEl.innerHTML = '';
            data.items.forEach((message) => appendMessage({
                body: message.body,
                is_outbound: message.is_outbound ?? message.direction === 'outbound',
            }));
        } catch {
            // Ignore transient poll errors.
        }
    };

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        const body = input.value.trim();
        if (!body) return;

        if (!serviceWindow.isWithinWindow()) {
            showAppAlert('Outside the 24-hour messaging window. Send an approved template instead.', 'Session expired');

            return;
        }

        const button = form.querySelector('[data-inbox-send-button]');
        button?.setAttribute('disabled', 'disabled');

        try {
            const response = await fetch(sendUrl, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                },
                credentials: 'same-origin',
                body: JSON.stringify({ body }),
            });

            if (!response.ok) {
                const error = await response.json().catch(() => ({}));
                const message = error.message || 'Unable to send message.';
                showAppAlert(message, 'Unable to send');

                return;
            }

            const data = await response.json();
            appendMessage({
                uuid: data.message?.uuid,
                body: data.message?.body ?? body,
                is_outbound: true,
            });
            input.value = '';
            if (!realtimeEnabled) {
                await refreshMessages();
            }
        } finally {
            button?.removeAttribute('disabled');
        }
    });

    const root = document.querySelector('[data-inbox-root]');
    const realtimeEnabled = root?.dataset.realtimeEnabled === '1';
    const tenantId = root?.dataset.tenantId;
    const conversationUuid = chat.dataset.conversationUuid;
    const pollInterval = realtimeEnabled
        ? Number(root?.dataset.realtimeFallbackPoll || 120000)
        : Number(root?.dataset.pollInterval || 30000);

    if (realtimeEnabled && tenantId && window.Echo) {
        window.Echo.private(`inbox.${tenantId}`)
            .listen('.thread.updated', (payload) => {
                updateThreadRow(payload.thread);
            })
            .listen('.message.created', (payload) => {
                updateThreadRow(payload.thread);
            });

        if (conversationUuid) {
            window.Echo.private(`inbox.${tenantId}.conversation.${conversationUuid}`)
                .listen('.message.created', (payload) => {
                    if (payload.conversation_uuid === conversationUuid) {
                        appendMessage(payload.message);
                        serviceWindow.refresh();
                    }
                });
        }
    }

    if (pollInterval > 0) {
        window.setInterval(async () => {
            await refreshMessages();
            await serviceWindow.refresh();
        }, pollInterval);
    }

    const assigneeSelect = chat.querySelector('[data-inbox-assignee]');
    const assignUrl = chat.dataset.assignUrl;

    if (assigneeSelect && assignUrl) {
        assigneeSelect.addEventListener('change', async () => {
            try {
                await fetch(assignUrl, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({ assignee: assigneeSelect.value }),
                });
            } catch {
                // Ignore transient errors.
            }
        });
    }
}

function initInboxOutboundModals() {
    const chat = document.querySelector('[data-inbox-chat]');
    if (!chat) return;

    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    const mediaUrl = chat.dataset.mediaUrl;
    const templateUrl = chat.dataset.templateUrl;
    const locationUrl = chat.dataset.locationUrl;
    const stickerUrl = chat.dataset.stickerUrl;
    const sendUrl = chat.dataset.sendUrl;
    const templatesUrl = chat.dataset.templatesUrl;
    const messagesEl = chat.querySelector('[data-inbox-messages]');

    if (!csrf) return;

    const assertWithinWindow = () => {
        if (chat.dataset.withinWindow === '0') {
            showAppAlert('Outside the 24-hour messaging window. Send an approved template instead.', 'Session expired');

            return false;
        }

        return true;
    };

    const postJsonMessage = async (url, payload) => {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,
            },
            credentials: 'same-origin',
            body: JSON.stringify(payload),
        });

        if (!response.ok) {
            const error = await response.json().catch(() => ({}));
            throw new Error(error.message || 'Unable to send message.');
        }

        return response.json();
    };

    const closeModal = (form) => {
        const modal = form.closest('[data-modal]');
        if (!modal) return;

        modal.classList.add('hidden');
        modal.classList.remove('flex');
        document.body.style.overflow = '';
    };

    const appendMessage = (message) => {
        if (!messagesEl || !message?.body) return;

        const row = document.createElement('div');
        row.className = 'flex justify-end';

        const bubble = document.createElement('div');
        bubble.className = 'max-w-[640px] rounded-bl-[12px] rounded-tl-[12px] rounded-tr-[12px] bg-green-100 p-4 text-xs leading-[1.8] text-text-body';
        bubble.style.fontFamily = "'Poppins', var(--font-sans)";
        bubble.textContent = message.body;

        row.appendChild(bubble);
        messagesEl.appendChild(row);
        messagesEl.scrollTop = messagesEl.scrollHeight;
    };

    const showFormError = (form, message) => {
        const errorEl = form.querySelector('[data-inbox-media-error], [data-inbox-template-error], [data-inbox-location-error], [data-inbox-sticker-error], [data-inbox-payment-error], [data-inbox-reaction-error]');
        if (!errorEl) return;

        errorEl.textContent = message;
        errorEl.classList.toggle('hidden', !message);
    };

    const mediaForm = document.querySelector('[data-inbox-media-form]');
    if (mediaForm && mediaUrl) {
        mediaForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            showFormError(mediaForm, '');

            if (!assertWithinWindow()) {
                return;
            }

            const formData = new FormData(mediaForm);

            try {
                const response = await fetch(mediaUrl, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': csrf,
                    },
                    credentials: 'same-origin',
                    body: formData,
                });

                if (!response.ok) {
                    const error = await response.json().catch(() => ({}));
                    showFormError(mediaForm, error.message || 'Unable to send media.');

                    return;
                }

                const data = await response.json();
                appendMessage(data.message);
                mediaForm.reset();
                closeModal(mediaForm);
            } catch {
                showFormError(mediaForm, 'Unable to send media.');
            }
        });
    }

    const templateForm = document.querySelector('[data-inbox-template-form]');
    const templateSelect = document.querySelector('[data-inbox-template-select]');

    if (templateSelect && templatesUrl) {
        fetch(templatesUrl, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        })
            .then((response) => (response.ok ? response.json() : { items: [] }))
            .then((data) => {
                const items = Array.isArray(data.items) ? data.items : [];
                templateSelect.innerHTML = '';

                if (items.length === 0) {
                    const option = document.createElement('option');
                    option.value = '';
                    option.textContent = 'No templates available';
                    templateSelect.appendChild(option);

                    return;
                }

                items.forEach((template) => {
                    const option = document.createElement('option');
                    option.value = template.code;
                    option.textContent = template.name || template.code;
                    templateSelect.appendChild(option);
                });
            })
            .catch(() => {
                templateSelect.innerHTML = '<option value="">No templates available</option>';
            });
    }

    if (templateForm && templateUrl) {
        templateForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            showFormError(templateForm, '');

            const templateCode = templateSelect?.value;
            if (!templateCode) {
                showFormError(templateForm, 'Select a template.');

                return;
            }

            try {
                const response = await fetch(templateUrl, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({ template_code: templateCode }),
                });

                if (!response.ok) {
                    const error = await response.json().catch(() => ({}));
                    showFormError(templateForm, error.message || 'Unable to send template.');

                    return;
                }

                const data = await response.json();
                appendMessage(data.message);
                closeModal(templateForm);
            } catch {
                showFormError(templateForm, 'Unable to send template.');
            }
        });
    }

    const locationForm = document.querySelector('[data-inbox-location-form]');
    if (locationForm && locationUrl) {
        locationForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            showFormError(locationForm, '');

            if (!assertWithinWindow()) {
                return;
            }

            const latitude = locationForm.querySelector('[name="latitude"]')?.value;
            const longitude = locationForm.querySelector('[name="longitude"]')?.value;

            try {
                const response = await fetch(locationUrl, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({ latitude, longitude }),
                });

                if (!response.ok) {
                    const error = await response.json().catch(() => ({}));
                    showFormError(locationForm, error.message || 'Unable to send location.');

                    return;
                }

                const data = await response.json();
                appendMessage(data.message);
                locationForm.reset();
                closeModal(locationForm);
            } catch {
                showFormError(locationForm, 'Unable to send location.');
            }
        });
    }

    const stickerForm = document.querySelector('[data-inbox-sticker-form]');
    const stickerPreview = document.querySelector('[data-inbox-sticker-preview]');

    if (stickerForm) {
        const fileInput = stickerForm.querySelector('input[type="file"]');

        fileInput?.addEventListener('change', () => {
            const file = fileInput.files?.[0];

            if (!file || !stickerPreview) {
                return;
            }

            stickerPreview.innerHTML = '';
            const image = document.createElement('img');
            image.src = URL.createObjectURL(file);
            image.alt = 'Sticker preview';
            image.className = 'size-full rounded-xl object-contain p-4';
            stickerPreview.appendChild(image);
        });
    }

    if (stickerForm && stickerUrl) {
        stickerForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            showFormError(stickerForm, '');

            if (!assertWithinWindow()) {
                return;
            }

            const formData = new FormData(stickerForm);

            try {
                const response = await fetch(stickerUrl, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': csrf,
                    },
                    credentials: 'same-origin',
                    body: formData,
                });

                if (!response.ok) {
                    const error = await response.json().catch(() => ({}));
                    showFormError(stickerForm, error.message || 'Unable to send sticker.');

                    return;
                }

                const data = await response.json();
                appendMessage(data.message);
                stickerForm.reset();
                if (stickerPreview) {
                    stickerPreview.innerHTML = '';
                }
                closeModal(stickerForm);
            } catch {
                showFormError(stickerForm, 'Unable to send sticker.');
            }
        });
    }

    const paymentForm = document.querySelector('[data-inbox-payment-form]');

    if (paymentForm && sendUrl) {
        paymentForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            showFormError(paymentForm, '');

            if (!assertWithinWindow()) {
                return;
            }

            const amount = paymentForm.querySelector('[name="amount"]')?.value;
            const description = paymentForm.querySelector('[name="description"]')?.value;

            if (!amount || !description) {
                showFormError(paymentForm, 'Amount and description are required.');

                return;
            }

            try {
                const data = await postJsonMessage(sendUrl, {
                    body: `Payment request: ${amount} - ${description}`,
                });
                appendMessage(data.message);
                paymentForm.reset();
                closeModal(paymentForm);
            } catch (error) {
                showFormError(paymentForm, error.message || 'Unable to send payment link.');
            }
        });
    }

    const reactionForm = document.querySelector('[data-inbox-reaction-form]');

    if (reactionForm && sendUrl) {
        reactionForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            showFormError(reactionForm, '');

            if (!assertWithinWindow()) {
                return;
            }

            const emoji = reactionForm.querySelector('[name="emoji"]')?.value;

            if (!emoji) {
                showFormError(reactionForm, 'Select a reaction.');

                return;
            }

            try {
                const data = await postJsonMessage(sendUrl, { body: emoji });
                appendMessage(data.message);
                reactionForm.reset();
                closeModal(reactionForm);
            } catch (error) {
                showFormError(reactionForm, error.message || 'Unable to send reaction.');
            }
        });
    }
}

function isToggleSwitchActive(toggle) {
    return toggle?.getAttribute('aria-checked') === 'true';
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

function initInboxTeamFeatures() {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    if (!csrf) return;

    const chat = document.querySelector('[data-inbox-chat]');
    const aiToggle = chat?.querySelector('[data-inbox-ai-toggle]');
    const aiStatus = chat?.querySelector('[data-inbox-ai-status]');
    const responseTypeUrl = chat?.dataset.responseTypeUrl;

    const setAiStatusLabel = (enabled) => {
        if (aiStatus) {
            aiStatus.textContent = enabled ? 'AI enabled' : 'Human reply';
        }
    };

    if (aiToggle && responseTypeUrl) {
        aiToggle.addEventListener('click', async (event) => {
            event.preventDefault();

            const previous = isToggleSwitchActive(aiToggle);
            const next = !previous;

            setToggleSwitchActive(aiToggle, next);
            setAiStatusLabel(next);

            try {
                const response = await fetch(responseTypeUrl, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({ ai_enabled: next }),
                });

                if (!response.ok) {
                    setToggleSwitchActive(aiToggle, previous);
                    setAiStatusLabel(previous);
                }
            } catch {
                setToggleSwitchActive(aiToggle, previous);
                setAiStatusLabel(previous);
            }
        });
    }

    const aiToggleAll = document.querySelector('[data-inbox-ai-toggle-all]');
    const root = document.querySelector('[data-inbox-root]');

    if (aiToggleAll && root) {
        const url = `${window.location.origin}/inbox/api/response-type/all${window.location.search}`;

        aiToggleAll.addEventListener('click', async (event) => {
            event.preventDefault();
            event.stopPropagation();

            const previous = isToggleSwitchActive(aiToggleAll);
            const next = !previous;

            setToggleSwitchActive(aiToggleAll, next);

            try {
                const response = await fetch(url, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({ ai_enabled: next }),
                });

                if (!response.ok) {
                    setToggleSwitchActive(aiToggleAll, previous);
                }
            } catch {
                setToggleSwitchActive(aiToggleAll, previous);
            }
        });
    }
}

function initInboxAddContact() {
    const form = document.querySelector('[data-inbox-add-contact-form]');
    const root = document.querySelector('[data-inbox-root]');
    const url = root?.dataset.addContactUrl;
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    const errorEl = form?.querySelector('[data-inbox-add-contact-error]');

    if (!form || !url || !csrf) return;

    const showError = (message) => {
        if (!errorEl) return;
        errorEl.textContent = message;
        errorEl.classList.toggle('hidden', !message);
    };

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        showError('');

        const submitButton = form.querySelector('[type="submit"]');
        submitButton?.setAttribute('disabled', 'disabled');

        const payload = {
            name: form.querySelector('[name="name"]')?.value?.trim(),
            country_code: form.querySelector('[name="country_code"]')?.value,
            phone: form.querySelector('[name="phone"]')?.value?.trim(),
            response_type: form.querySelector('[name="response_type"]')?.value,
        };

        try {
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                },
                credentials: 'same-origin',
                body: JSON.stringify(payload),
            });

            const data = await response.json().catch(() => ({}));

            if (!response.ok) {
                showError(data.message || 'Unable to add contact.');

                return;
            }

            if (data.redirect) {
                window.location.href = data.redirect;

                return;
            }

            window.location.reload();
        } catch {
            showError('Unable to add contact.');
        } finally {
            submitButton?.removeAttribute('disabled');
        }
    });
}

function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function initInboxSearch() {
    const form = document.querySelector('[data-inbox-search-form]');
    const input = document.querySelector('[data-inbox-search-input]');
    const list = document.querySelector('[data-inbox-thread-list]');
    const root = document.querySelector('[data-inbox-root]');
    const threadsUrl = root?.dataset.threadsUrl;
    const inboxBaseUrl = root?.dataset.inboxBaseUrl;

    if (!input || !list || !threadsUrl || !inboxBaseUrl) {
        return;
    }

    let debounceTimer = null;
    let activeRequest = 0;

    const selectedUuid = document.querySelector('[data-inbox-chat]')?.dataset.conversationUuid ?? null;

    const buildParams = () => {
        const params = new URLSearchParams(window.location.search);

        if (input.value.trim()) {
            params.set('q', input.value.trim());
        } else {
            params.delete('q');
        }

        return params;
    };

    const renderThreads = (threads) => {
        if (!Array.isArray(threads) || threads.length === 0) {
            list.innerHTML = '<div class="p-6 text-center text-sm text-text-body/70" data-inbox-thread-empty>No conversations found.</div>';
            return;
        }

        const params = buildParams();
        const query = params.toString();

        list.innerHTML = threads.map((thread) => {
            const isSelected = thread.uuid === selectedUuid;
            const rowClass = isSelected ? 'bg-green-50' : 'bg-elevated hover:bg-surface';
            const unreadCount = Number(thread.unread || 0);
            const unreadClass = unreadCount > 0 ? 'flex' : 'hidden';
            const href = `${inboxBaseUrl}/${encodeURIComponent(thread.uuid)}${query ? `?${query}` : ''}`;

            return `
                <a
                    href="${href}"
                    class="flex border-b border-divider px-2 py-1.5 last:border-0 ${rowClass}"
                    data-thread-uuid="${escapeHtml(thread.uuid)}"
                >
                    <div class="flex min-w-0 flex-1 items-center gap-3 p-2">
                        <div class="fd-btn-sm flex size-8 shrink-0 items-center justify-center rounded-2xl bg-green-50 text-green-500">${escapeHtml(thread.initials)}</div>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center justify-between gap-2">
                                <span class="fd-table-name truncate">${escapeHtml(thread.name)}</span>
                                <span class="fd-status-chip shrink-0 text-text-body/60" data-thread-time>${escapeHtml(thread.time)}</span>
                            </div>
                            <div class="flex items-center justify-between gap-2">
                                <p class="fd-table-cell truncate text-xs opacity-50" data-thread-preview>${escapeHtml(thread.preview)}</p>
                                <span class="fd-status-chip ${unreadClass} size-4 shrink-0 items-center justify-center rounded-full bg-green-500 text-white" data-thread-unread>${unreadCount > 0 ? unreadCount : ''}</span>
                            </div>
                        </div>
                    </div>
                </a>
            `;
        }).join('');
    };

    const fetchThreads = async () => {
        const requestId = ++activeRequest;
        const params = buildParams();

        try {
            const response = await fetch(`${threadsUrl}?${params.toString()}`, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });

            if (!response.ok || requestId !== activeRequest) {
                return;
            }

            const data = await response.json();
            renderThreads(data.items || []);

            const nextQuery = params.toString();
            const nextUrl = `${window.location.pathname}${nextQuery ? `?${nextQuery}` : ''}`;
            window.history.replaceState({}, '', nextUrl);
        } catch {
            // Keep the current list if the search request fails.
        }
    };

    input.addEventListener('input', () => {
        window.clearTimeout(debounceTimer);
        debounceTimer = window.setTimeout(fetchThreads, 300);
    });

    form?.addEventListener('submit', (event) => {
        event.preventDefault();
        window.clearTimeout(debounceTimer);
        fetchThreads();
    });
}


function initGlobalSearch() {
    const root = document.querySelector('[data-global-search]');
    const form = root?.querySelector('[data-global-search-form]');
    const input = root?.querySelector('[data-global-search-input]');
    const results = root?.querySelector('[data-global-search-results]');
    const searchUrl = root?.dataset.searchUrl;

    if (!root || !input || !results || !searchUrl) {
        return;
    }

    let debounceTimer = null;
    let activeRequest = 0;

    const closeResults = () => {
        results.classList.add('hidden');
        results.innerHTML = '';
        input.setAttribute('aria-expanded', 'false');
    };

    const openResults = () => {
        results.classList.remove('hidden');
        input.setAttribute('aria-expanded', 'true');
    };

    const renderEmpty = (message) => {
        results.innerHTML = `<div class="px-4 py-3 text-sm text-text-body/70">${escapeHtml(message)}</div>`;
        openResults();
    };

    const renderResults = (payload) => {
        const pages = Array.isArray(payload?.pages) ? payload.pages : [];
        const conversations = Array.isArray(payload?.conversations) ? payload.conversations : [];

        if (pages.length === 0 && conversations.length === 0) {
            renderEmpty('No results found.');
            return;
        }

        let html = '';

        if (pages.length > 0) {
            html += '<div class="border-b border-divider px-3 py-2 text-[11px] font-semibold uppercase tracking-wide text-text-body/60">Pages</div>';
            html += pages.map((page) => `
                <a href="${escapeHtml(page.url)}" class="flex flex-col gap-0.5 border-b border-divider px-4 py-3 transition hover:bg-surface" role="option">
                    <span class="text-sm font-medium text-text-primary">${escapeHtml(page.label)}</span>
                    ${page.group ? `<span class="text-xs text-text-body/60">${escapeHtml(page.group)}</span>` : ''}
                </a>
            `).join('');
        }

        if (conversations.length > 0) {
            html += '<div class="border-b border-divider px-3 py-2 text-[11px] font-semibold uppercase tracking-wide text-text-body/60">Inbox</div>';
            html += conversations.map((conversation) => `
                <a href="${escapeHtml(conversation.url)}" class="flex flex-col gap-0.5 border-b border-divider px-4 py-3 transition hover:bg-surface last:border-0" role="option">
                    <span class="text-sm font-medium text-text-primary">${escapeHtml(conversation.name)}</span>
                    <span class="truncate text-xs text-text-body/60">${escapeHtml(conversation.preview || conversation.phone)}</span>
                </a>
            `).join('');
        }

        results.innerHTML = html;
        openResults();
    };

    const fetchResults = async () => {
        const query = input.value.trim();

        if (query === '') {
            closeResults();
            return;
        }

        const requestId = ++activeRequest;

        try {
            const response = await fetch(`${searchUrl}?q=${encodeURIComponent(query)}`, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });

            if (!response.ok || requestId !== activeRequest) {
                return;
            }

            renderResults(await response.json());
        } catch {
            if (requestId === activeRequest) {
                renderEmpty('Unable to search right now.');
            }
        }
    };

    const syncInboxSearch = () => {
        const inboxSearch = document.querySelector('[data-inbox-search-input]');

        if (!inboxSearch) {
            return;
        }

        inboxSearch.value = input.value.trim();
        inboxSearch.dispatchEvent(new Event('input', { bubbles: true }));
    };

    input.addEventListener('input', () => {
        window.clearTimeout(debounceTimer);
        debounceTimer = window.setTimeout(() => {
            fetchResults();

            if (window.location.pathname.startsWith('/inbox')) {
                syncInboxSearch();
            }
        }, 300);
    });

    input.addEventListener('focus', () => {
        if (input.value.trim() !== '') {
            fetchResults();
        }
    });

    form?.addEventListener('submit', (event) => {
        event.preventDefault();

        const query = input.value.trim();

        if (query === '') {
            return;
        }

        const firstResult = results.querySelector('a[href]');

        if (firstResult instanceof HTMLAnchorElement) {
            window.location.href = firstResult.href;
            return;
        }

        window.location.href = `/inbox?q=${encodeURIComponent(query)}`;
    });

    document.addEventListener('click', (event) => {
        if (!root.contains(event.target)) {
            closeResults();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeResults();
            input.blur();
        }
    });
}

function initInboxFilters() {
    const button = document.querySelector('[data-inbox-mark-all-read]');
    if (!button) return;

    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    const url = button.dataset.markAllUrl;

    if (!csrf || !url) return;

    button.addEventListener('click', async () => {
        button.setAttribute('disabled', 'disabled');

        try {
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrf,
                },
                credentials: 'same-origin',
            });

            if (response.ok) {
                window.location.reload();
            }
        } finally {
            button.removeAttribute('disabled');
        }
    });
}

function initTeamPage() {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    const root = document.querySelector('[data-team-root]');

    if (!root || !csrf) {
        return;
    }

    root.querySelectorAll('[data-team-status-toggle]').forEach((toggle) => {
        toggle.addEventListener('click', async (event) => {
            event.preventDefault();

            const url = toggle.dataset.statusUrl;
            const row = toggle.closest('[data-team-row]');
            const statusLabel = row?.querySelector('[data-team-status-label]');

            if (!url) {
                return;
            }

            const previous = isToggleSwitchActive(toggle);

            setToggleSwitchActive(toggle, !previous);

            try {
                const response = await fetch(url, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                    },
                    credentials: 'same-origin',
                });

                if (!response.ok) {
                    setToggleSwitchActive(toggle, previous);
                    return;
                }

                const data = await response.json();

                if (statusLabel) {
                    statusLabel.innerHTML = data.is_active
                        ? '<span class="inline-flex items-center justify-center rounded bg-[rgba(0,128,0,0.1)] px-2 py-1 text-[10px] font-medium leading-[1.2] text-[green]">Active</span>'
                        : '<span class="inline-flex items-center justify-center rounded bg-[rgba(0,0,0,0.1)] px-2 py-1 text-[10px] font-medium leading-[1.2] text-text-muted">Inactive</span>';
                }
            } catch {
                setToggleSwitchActive(toggle, previous);
            }
        });
    });

    document.querySelectorAll('[data-team-permission-toggle]').forEach((toggle) => {
        const container = toggle.closest('.flex');
        const checkbox = container?.querySelector('[data-team-permission-checkbox]');

        if (!checkbox) {
            return;
        }

        toggle.addEventListener('click', (event) => {
            event.preventDefault();
            const next = !isToggleSwitchActive(toggle);
            setToggleSwitchActive(toggle, next);
            checkbox.checked = next;
        });
    });
}
