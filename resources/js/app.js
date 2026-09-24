import './echo.js';
import './form-validation.js';
import { initThemedSelects, initThemedSelectObserver } from './themed-select.js';
import { initConfirmDialog, showAppAlert, showAppConfirm } from './confirm-dialog.js';
import { initToast } from './toast.js';
import { initTemplateBuilder } from './template-builder.js';
import { initTemplatesIndex } from './templates-index.js';
import { initFreeTemplateBuilder } from './free-template-builder.js';
import { initListingFilters, applyServerFieldErrors } from './listing-filters.js';
import { initBuilderSectionMaximize, initDripCanvasMaximize, initInboxMaximize } from './builder-maximize.js';
import { initChatbotListingToggle } from './chatbot-listing.js';
import { initCampaignWizard } from './campaign-wizard.js';
import { initPageLoader } from './page-loader.js';
import { initDashboard } from './dashboard.js';
import { initCommerceOrderModal } from './commerce.js';

document.addEventListener('DOMContentLoaded', () => {
    initToast();
    // Confirm must register before the page loader so intercepted submits never flash a loader.
    initConfirmDialog();
    initPageLoader();
    initThemeToggle();
    initTabToggle();
    initPasswordToggle();
    initOtpInputs();
    initLoginOtp();
    initAuthFeaturesCarousel();
    initThemedSelects();
    initThemedSelectObserver();
    try {
        initDashboard();
    } catch (error) {
        console.warn('Dashboard init failed', error);
    }
    initCampaignWizard();
    initInboxModals();
    initInboxMessageMenu();
    initCommerceOrderModal();
    initInboxNotifications();
    initInboxNavBadge();
    try {
        initInboxRealtime();
    } catch {
        // Polling still starts from initInboxChat.
    }
    try {
        initInboxChat();
    } catch {
        // Outbound modals still initialize below.
    }
    initInboxDeleteChat();
    initInboxMediaLightbox();
    initInboxFailedTooltips();
    initInboxOutboundModals();
    initInboxTeamFeatures();
    initInboxExportModal();
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

    menu.querySelectorAll('[data-open-modal], [data-inbox-menu-action]').forEach((item) => {
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
            canSendFreeForm: () => chat.dataset.walletBlocked !== '1',
        };
    }

    const root = inboxRoot();
    const banner = chat.querySelector('[data-inbox-window-banner]');
    const bannerText = chat.querySelector('[data-inbox-window-banner-text]');
    const composer = chat.querySelector('[data-inbox-composer]');
    const expiredActions = chat.querySelector('[data-inbox-expired-actions]');
    const input = chat.querySelector('[data-inbox-message-input]');
    const sendButton = chat.querySelector('[data-inbox-send-button]');
    const sessionActions = chat.querySelectorAll('[data-inbox-session-action]');
    const windowRequiredItems = chat.querySelectorAll('[data-inbox-requires-window]');
    const windowHours = Number(chat.dataset.windowHours || 24);
    const walletBlocked = () =>
        chat.dataset.walletBlocked === '1' || root?.dataset.walletBlocked === '1';
    const walletCopy = 'Your wallet balance is currently insufficient to send messages.';
    const expiredCopy = [
        'Session expired. Send an approved template message to reach this contact again.',
        '',
        'When they reply, a 24-hour window opens so you can send normal free-form messages.',
        '',
        'Sending a template alone does not start that window — the customer still needs to reply once so the 24-hour window can begin.',
    ].join('\n');

    let withinWindow = true;

    const applyWindowState = (status) => {
        withinWindow = Boolean(status?.within_window);
        chat.dataset.withinWindow = withinWindow ? '1' : '0';

        const blocked = walletBlocked();
        // Legacy parity: wallet block acts like timelapsed for free-form composer.
        const canFreeForm = withinWindow && !blocked;

        if (banner && bannerText) {
            banner.classList.remove('border-green-300', 'bg-green-50', 'text-green-900', 'border-amber-300', 'bg-amber-50', 'text-amber-900');

            if (blocked) {
                banner.classList.remove('hidden');
                banner.classList.add('border-amber-300', 'bg-amber-50', 'text-amber-900');
                bannerText.textContent = walletCopy;
            } else if (withinWindow && status?.expires_at) {
                const remaining = formatWindowExpiry(status.expires_at);
                banner.classList.remove('hidden');
                banner.classList.add('border-green-300', 'bg-green-50', 'text-green-900');
                bannerText.textContent = `Session active for ${remaining} more (resets on customer reply, max ${status.window_hours ?? windowHours}h).`;
            } else if (!withinWindow) {
                banner.classList.remove('hidden');
                banner.classList.add('border-amber-300', 'bg-amber-50', 'text-amber-900');
                bannerText.textContent = expiredCopy;
            } else {
                banner.classList.add('hidden');
            }
        }

        if (composer) {
            composer.classList.toggle('hidden', !canFreeForm);
        }

        if (expiredActions) {
            expiredActions.classList.toggle('opacity-100', !canFreeForm);
        }

        input?.toggleAttribute('disabled', !canFreeForm);
        sendButton?.toggleAttribute('disabled', !canFreeForm);

        sessionActions.forEach((element) => {
            element.toggleAttribute('disabled', !canFreeForm);
            element.setAttribute('aria-disabled', canFreeForm ? 'false' : 'true');
            element.classList.toggle('pointer-events-none', !canFreeForm);
            element.classList.toggle('opacity-50', !canFreeForm);
        });

        windowRequiredItems.forEach((element) => {
            element.toggleAttribute('disabled', !canFreeForm);
            element.setAttribute('aria-disabled', canFreeForm ? 'false' : 'true');
            element.classList.toggle('pointer-events-none', !canFreeForm);
            element.classList.toggle('opacity-50', !canFreeForm);
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
        canSendFreeForm: () => withinWindow && !walletBlocked(),
    };
}

function inboxRoot() {
    return document.querySelector('[data-inbox-root]');
}

function inboxSelectedConversationUuid() {
    return document.querySelector('[data-inbox-chat]')?.dataset.conversationUuid ?? null;
}

function inboxBaseUrl() {
    return inboxRoot()?.dataset.inboxBaseUrl || '/inbox';
}

const INBOX_NOTIFY_KEY = 'inbox.web_notifications';
const inboxUnreadSeen = new Map();
let inboxUnreadWatermark = null;
let inboxLatestFingerprint = null;
let lastInboxNotifyKey = '';
let lastInboxNotifyAt = 0;

function threadDisplayName(thread) {
    return String(thread?.name || thread?.phone || 'Unknown');
}

function threadPhoneSubtitle(thread) {
    const name = String(thread?.name || '').trim();
    const phone = String(thread?.phone || '').trim();

    if (phone === '' || phone === name) {
        return '';
    }

    return phone;
}

function seedInboxUnreadFromDom() {
    document.querySelectorAll('[data-thread-uuid]').forEach((row) => {
        const uuid = row.dataset.threadUuid;
        if (!uuid || inboxUnreadSeen.has(uuid)) {
            return;
        }

        const unreadEl = row.querySelector('[data-thread-unread]');
        const hidden = unreadEl?.classList.contains('hidden');
        const count = hidden ? 0 : Number(unreadEl?.textContent || 0);
        inboxUnreadSeen.set(uuid, Number.isFinite(count) ? count : 0);
    });
}

function inboxNotificationsWanted() {
    return window.localStorage.getItem(INBOX_NOTIFY_KEY) !== '0';
}

function inboxCanNotify() {
    return (
        inboxNotificationsWanted() &&
        window.isSecureContext &&
        typeof Notification !== 'undefined' &&
        Notification.permission === 'granted'
    );
}

function isAppleDesktopBrowser() {
    const ua = navigator.userAgent || '';

    return /Macintosh|Mac OS X/i.test(ua) && !/Mobile/i.test(ua);
}

function isWindowsDesktopBrowser() {
    const ua = navigator.userAgent || '';
    const platform = navigator.platform || navigator.userAgentData?.platform || '';

    return /Win/i.test(platform) || /Windows NT/i.test(ua);
}

function inboxNotifyIconUrl() {
    const raw = document.body?.dataset.inboxNotifyIcon || '/images/logo.png';

    try {
        return new URL(raw, window.location.origin).href;
    } catch {
        return `${window.location.origin}/images/logo.png`;
    }
}

function setInboxNotifyUi(enabled) {
    const btn = document.querySelector('[data-inbox-notify-toggle]');
    const label = document.querySelector('[data-inbox-notify-label]');

    if (btn) {
        btn.setAttribute('aria-pressed', enabled ? 'true' : 'false');
        btn.classList.toggle('border-green-500', enabled);
        btn.classList.toggle('text-green-600', enabled);
    }

    if (label) {
        label.textContent = enabled ? 'Notifications on' : 'Notifications';
    }
}

function showInboxWebNotification(thread, message, { force = false } = {}) {
    if (!inboxCanNotify()) {
        return false;
    }

    const openUuid = inboxSelectedConversationUuid();
    // Skip only when this exact chat is open AND the tab is visibly focused.
    if (
        !force &&
        openUuid &&
        thread?.uuid &&
        thread.uuid === openUuid &&
        document.visibilityState === 'visible' &&
        document.hasFocus()
    ) {
        return false;
    }

    const title = threadDisplayName(thread) || 'WapApp Inbox';
    const body =
        String(
            message?.body ||
                message?.interactive_preview?.body ||
                thread?.preview ||
                (message?.message_type && message.message_type !== 'text'
                    ? `[${message.message_type}]`
                    : '') ||
                'New WhatsApp message',
        ).trim() || 'New WhatsApp message';
    const key = `${thread?.uuid || 'inbox'}:${body}`;
    const now = Date.now();

    if (!force && key === lastInboxNotifyKey && now - lastInboxNotifyAt < 4000) {
        return false;
    }

    lastInboxNotifyKey = key;
    lastInboxNotifyAt = now;

    const icon = inboxNotifyIconUrl();
    // macOS often ignores renotify, so unique tags are required there.
    // Windows/Linux: stable per-conversation tag + renotify (Linux-style banners;
    // unique tags flood Windows Action Center and get rate-limited).
    const apple = isAppleDesktopBrowser();
    const tag = force
        ? `inbox-test-${now}`
        : apple
          ? `inbox-${thread?.uuid || 'general'}-${now}`
          : `inbox-${thread?.uuid || 'general'}`;

    let created = false;

    try {
        /** @type {NotificationOptions} */
        const options = {
            body,
            tag,
            icon,
            // Absolute icon again as badge — Windows Chrome/Edge show this in the tray.
            badge: icon,
            silent: false,
            requireInteraction: false,
        };

        if (!apple) {
            options.renotify = true;
        }

        const notification = new Notification(title, options);

        created = true;

        notification.onclick = () => {
            try {
                window.focus();
            } catch {
                // ignore focus errors (some Windows browsers)
            }
            if (thread?.uuid) {
                const params = window.location.search || '';
                window.location.href = `${inboxBaseUrl()}/${encodeURIComponent(thread.uuid)}${params}`;
            }
            notification.close();
        };

        // Windows Action Center keeps banners forever unless closed; auto-dismiss
        // matches Linux Chrome behavior (~8s) so the tray does not pile up.
        if (isWindowsDesktopBrowser() && !force) {
            window.setTimeout(() => {
                try {
                    notification.close();
                } catch {
                    // already closed
                }
            }, 8000);
        }
    } catch {
        created = false;
    }

    // Do not fall back to in-app toasts for chat traffic — high volume freezes the tab.
    // Desktop OS notifications only (when permission is granted).

    return created;
}

function applyInboxUnreadSnapshot(data, { notify = true } = {}) {
    const total = Number(data?.unread_total || 0);
    const latest = data?.latest || null;
    const fingerprint = latest
        ? `${latest.uuid || ''}|${latest.unread || 0}|${latest.preview || ''}`
        : '';

    if (typeof data?.unread_total === 'number') {
        setInboxNavBadge(total);
    }

    if (inboxUnreadWatermark === null) {
        inboxUnreadWatermark = total;
        inboxLatestFingerprint = fingerprint;

        return;
    }

    const totalIncreased = notify && total > inboxUnreadWatermark;
    // Same chat got another inbound message → chat-count watermark stays flat.
    const latestChanged =
        notify &&
        fingerprint !== '' &&
        fingerprint !== inboxLatestFingerprint &&
        Number(latest?.unread || 0) > 0;

    if (totalIncreased || latestChanged) {
        showInboxWebNotification(latest || {}, {
            body: latest?.preview,
        });
    }

    inboxUnreadWatermark = total;
    if (fingerprint !== '') {
        inboxLatestFingerprint = fingerprint;
    }
}

function inboxUnreadTotalFromDom() {
    // Nav badge = chats with unread inbound, not sum of message counts.
    let chats = 0;

    document.querySelectorAll('[data-inbox-thread-list] [data-thread-unread]').forEach((el) => {
        if (el.classList.contains('hidden')) {
            return;
        }

        const count = Number(el.textContent || 0);
        if (Number.isFinite(count) && count > 0) {
            chats += 1;
        }
    });

    return chats;
}

function inboxUnreadChatCountFromSeen() {
    let chats = 0;

    inboxUnreadSeen.forEach((count) => {
        if (Number(count) > 0) {
            chats += 1;
        }
    });

    return chats;
}

function setInboxNavBadge(count) {
    const next = Number(count || 0);
    const label = next > 99 ? '99+' : next > 0 ? String(next) : '';

    document.querySelectorAll('[data-inbox-nav-badge]').forEach((el) => {
        el.textContent = label;
        el.classList.toggle('hidden', label === '');
    });

    const root = inboxRoot();
    if (root) {
        root.dataset.unreadTotal = String(Math.max(0, next));
    }

    const baseTitle = document.title.replace(/^\(\d+\+?\)\s+/, '');
    document.title = next > 0 ? `(${next > 99 ? '99+' : next}) ${baseTitle}` : baseTitle;
}

function syncInboxNavBadge() {
    // Visible thread chips can be a partial list (lookback / pagination).
    // Never shrink a server-rendered or API-backed menu badge from that sum.
}

function initInboxNavBadge() {
    if (!document.querySelector('[data-inbox-nav-badge]')) {
        return;
    }

    const url = document.body?.dataset.inboxUnreadUrl;
    if (!url) {
        return;
    }

    const refresh = async () => {
        try {
            const response = await fetch(url, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });

            if (!response.ok) {
                return;
            }

            const data = await response.json();
            applyInboxUnreadSnapshot(data);
        } catch {
            // Ignore transient poll errors.
        }
    };

    refresh();
    window.setInterval(refresh, 4000);
}

function maybeRefreshOpenChat(thread) {
    const chat = document.querySelector('[data-inbox-chat]');
    if (!chat || !thread?.uuid || chat.dataset.conversationUuid !== thread.uuid) {
        return;
    }

    if (typeof window.__inboxRefreshMessages === 'function') {
        window.__inboxRefreshMessages();
    }
}

function rememberThreadUnread(thread, { notify = false } = {}) {
    if (!thread?.uuid) {
        return;
    }

    const next = Number(thread.unread || 0);
    const prev = inboxUnreadSeen.has(thread.uuid) ? Number(inboxUnreadSeen.get(thread.uuid) || 0) : 0;
    inboxUnreadSeen.set(thread.uuid, next);

    const wasUnread = prev > 0;
    const isUnread = next > 0;

    // Nav badge tracks chats, not messages: ±1 when a chat crosses unread/read.
    if (wasUnread !== isUnread) {
        const root = inboxRoot();
        const current = Number(root?.dataset.unreadTotal || 0);
        setInboxNavBadge(Math.max(0, current + (isUnread ? 1 : -1)));
    }

    // Echo-down fallback: notify when this thread's unread count rises.
    if (notify && next > prev && next > 0) {
        const openUuid = inboxSelectedConversationUuid();
        const isFocusedOpenChat =
            openUuid &&
            thread.uuid === openUuid &&
            document.visibilityState === 'visible' &&
            document.hasFocus();

        if (!isFocusedOpenChat) {
            showInboxWebNotification(thread, { body: thread.preview });
        }
    }
}

function refreshInboxNavBadgeFromThreads() {
    // Prefer chat count from the seen map when available; never inflate past a
    // higher server/API total (thread list can be a partial page).
    if (inboxUnreadSeen.size === 0) {
        setInboxNavBadge(inboxUnreadTotalFromDom());

        return;
    }

    const chats = inboxUnreadChatCountFromSeen();
    const root = inboxRoot();
    const current = Number(root?.dataset.unreadTotal || 0);

    if (chats <= current) {
        setInboxNavBadge(chats);
    }
}

function buildThreadRowHtml(thread, selectedUuid = null) {
    const isSelected = thread.uuid === selectedUuid;
    const rowClass = isSelected ? 'bg-green-50' : 'bg-elevated hover:bg-surface';
    const unreadCount = Number(thread.unread || 0);
    const unreadClass = unreadCount > 0 ? 'flex' : 'hidden';
    const params = new URLSearchParams(window.location.search);
    const query = params.toString();
    const href = `${inboxBaseUrl()}/${encodeURIComponent(thread.uuid)}${query ? `?${query}` : ''}`;
    const stopBadge = thread.stopped
        ? '<span class="rounded bg-red-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-red-700" title="This contact marked STOP and is unsubscribed">STOP</span>'
        : '';
    const phone = threadPhoneSubtitle(thread);
    const phoneClass = phone ? '' : 'hidden';

    return `
        <a
            href="${href}"
            class="flex border-b border-divider px-2 py-1.5 last:border-0 ${rowClass}"
            data-thread-uuid="${escapeHtml(thread.uuid)}"
        >
            <div class="flex min-w-0 flex-1 items-center gap-3 p-2">
                <div class="fd-btn-sm flex size-8 shrink-0 items-center justify-center rounded-2xl bg-green-50 text-green-500">${escapeHtml(thread.initials || '?')}</div>
                <div class="min-w-0 flex-1">
                    <div class="flex items-center justify-between gap-2">
                        <span class="fd-table-name truncate" data-thread-name>${escapeHtml(threadDisplayName(thread))}</span>
                        <div class="flex shrink-0 items-center gap-1.5">
                            ${stopBadge}
                            <span class="fd-status-chip text-text-body/60" data-thread-time>${escapeHtml(thread.time || '')}</span>
                        </div>
                    </div>
                    <p class="${phoneClass} truncate text-[11px] leading-tight text-text-body/55" data-thread-phone>${escapeHtml(phone)}</p>
                    <div class="flex items-center justify-between gap-2">
                        <p class="fd-table-cell truncate text-xs opacity-50" data-thread-preview>${escapeHtml(thread.preview || '')}</p>
                        <div class="flex shrink-0 items-center gap-1.5">
                            ${thread.assignee ? `<span class="hidden max-w-[72px] truncate rounded bg-surface px-1.5 py-0.5 text-[10px] font-medium text-text-body/70 sm:inline" data-thread-assignee title="Assigned: ${escapeHtml(thread.assignee)}">${escapeHtml(thread.assignee)}</span>` : ''}
                            <span class="fd-status-chip ${unreadClass} h-4 min-w-4 shrink-0 items-center justify-center rounded-full bg-green-500 px-1 text-white" data-thread-unread>${unreadCount > 0 ? unreadCount : ''}</span>
                        </div>
                    </div>
                </div>
            </div>
        </a>
    `;
}

function upsertThreadRow(thread, { notify = true, bump = false } = {}) {
    if (!thread?.uuid) {
        return;
    }

    // WhatsApp parity: only clear unread while the agent is actually looking at this chat.
    const openUuid = inboxSelectedConversationUuid();
    if (
        openUuid &&
        thread.uuid === openUuid &&
        document.visibilityState === 'visible' &&
        document.hasFocus()
    ) {
        thread = { ...thread, unread: 0 };
    }

    const list = document.querySelector('[data-inbox-thread-list]');
    if (!list) {
        return;
    }

    const empty = list.querySelector('[data-inbox-thread-empty]');
    if (empty) {
        empty.remove();
    }

    const selectedUuid = openUuid;
    let row = list.querySelector(`[data-thread-uuid="${thread.uuid}"]`);

    if (!row) {
        list.insertAdjacentHTML('afterbegin', buildThreadRowHtml(thread, selectedUuid));
        row = list.querySelector(`[data-thread-uuid="${thread.uuid}"]`);
    } else {
        const preview = row.querySelector('[data-thread-preview]');
        const time = row.querySelector('[data-thread-time]');
        const unread = row.querySelector('[data-thread-unread]');
        const name = row.querySelector('[data-thread-name]') || row.querySelector('.fd-table-name');
        const phoneEl = row.querySelector('[data-thread-phone]');

        if (name && thread.name) {
            name.textContent = thread.name;
        }

        if (phoneEl && thread.phone !== undefined) {
            const subtitle = threadPhoneSubtitle(thread);
            phoneEl.textContent = subtitle;
            phoneEl.classList.toggle('hidden', subtitle === '');
        }

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

        let assigneeEl = row.querySelector('[data-thread-assignee]');
        if (thread.assignee !== undefined) {
            if (thread.assignee) {
                if (!assigneeEl) {
                    const unreadWrap = row.querySelector('[data-thread-unread]')?.parentElement;
                    if (unreadWrap) {
                        assigneeEl = document.createElement('span');
                        assigneeEl.dataset.threadAssignee = '';
                        assigneeEl.className =
                            'hidden max-w-[72px] truncate rounded bg-surface px-1.5 py-0.5 text-[10px] font-medium text-text-body/70 sm:inline';
                        unreadWrap.insertBefore(assigneeEl, unreadWrap.firstChild);
                    }
                }
                if (assigneeEl) {
                    assigneeEl.textContent = thread.assignee;
                    assigneeEl.title = `Assigned: ${thread.assignee}`;
                    assigneeEl.classList.remove('hidden');
                    assigneeEl.classList.add('sm:inline');
                }
            } else if (assigneeEl) {
                assigneeEl.remove();
            }
        }

        // Only bump on real activity (new message) — not on mark-read / poll merges.
        if (bump && list.firstElementChild !== row) {
            list.insertBefore(row, list.firstElementChild);
        }
    }

    if (thread.unread !== undefined) {
        rememberThreadUnread(thread, { notify });
    }

    maybeRefreshOpenChat(thread);

    return row;
}

function updateThreadRow(thread) {
    upsertThreadRow(thread);
}

function getThreadsCursorState() {
    const panel = document.querySelector('[data-inbox-thread-panel]');
    const root = inboxRoot();

    return {
        panel,
        root,
        cursor: panel?.dataset.threadsCursor || root?.dataset.threadsCursor || '',
        hasMore: (panel?.dataset.threadsHasMore || root?.dataset.threadsHasMore) === '1',
    };
}

function setThreadsCursorState({ cursor = '', hasMore = false } = {}) {
    const panel = document.querySelector('[data-inbox-thread-panel]');
    const root = inboxRoot();
    const nextCursor = cursor || '';
    const nextHasMore = hasMore ? '1' : '0';

    if (panel) {
        panel.dataset.threadsCursor = nextCursor;
        panel.dataset.threadsHasMore = nextHasMore;
    }

    if (root) {
        root.dataset.threadsCursor = nextCursor;
        root.dataset.threadsHasMore = nextHasMore;
    }
}

function interactiveButtonLabels(interactive) {
    if (!interactive || typeof interactive !== 'object') {
        return [];
    }

    const labels = [];

    (interactive.action?.buttons || []).forEach((button) => {
        const title = button?.reply?.title || button?.title || button?.text || button?.label || '';
        if (title) {
            labels.push(String(title));
        }
    });

    (interactive.action?.sections || []).forEach((section) => {
        (section?.rows || []).forEach((row) => {
            if (row?.title) {
                labels.push(String(row.title));
            }
        });
    });

    return [...new Set(labels)];
}

function messageDisplayBody(message) {
    if (!message || typeof message !== 'object') {
        return '';
    }

    const direct = String(message.body || '').trim();
    if (direct !== '') {
        return direct;
    }

    const text = message.text;
    if (typeof text === 'string' && text.trim() !== '') {
        return text.trim();
    }

    if (text && typeof text === 'object') {
        const nested = String(text.body || text.text || '').trim();
        if (nested !== '') {
            return nested;
        }
    }

    return '';
}

function inboxApiErrorMessage(payload, fallback) {
    if (!payload || typeof payload !== 'object') {
        return fallback;
    }

    if (typeof payload.message === 'string' && payload.message.trim() !== '') {
        return payload.message;
    }

    if (payload.errors && typeof payload.errors === 'object') {
        const first = Object.values(payload.errors)
            .flat()
            .find((value) => typeof value === 'string' && value.trim() !== '');
        if (first) {
            return first;
        }
    }

    return fallback;
}

const INBOX_DOUBLE_CHECK_PATH =
    'M15.01 3.316l-.478-.372a.365.365 0 0 0-.51.063L8.666 9.879a.32.32 0 0 1-.484.033l-.358-.325a.319.319 0 0 0-.484.032l-.378.483a.418.418 0 0 0 .036.541l1.32 1.266c.143.14.361.125.484-.033l6.272-8.048a.366.366 0 0 0-.064-.512zm-4.1 0l-.478-.372a.365.365 0 0 0-.51.063L4.566 9.879a.32.32 0 0 1-.484.033L1.891 7.769a.366.366 0 0 0-.515.006l-.423.433a.364.364 0 0 0 .006.514l3.258 3.185c.143.14.361.125.484-.033l6.272-8.048a.365.365 0 0 0-.063-.51z';

function inboxMessageStatusIcon(status, failedReason = null) {
    const normalized = String(status || 'queued').toLowerCase();
    const wrap = document.createElement('span');
    wrap.className = 'inline-flex items-center';
    wrap.dataset.messageStatus = normalized;

    const reason = String(failedReason || '').trim();
    if (normalized === 'failed') {
        wrap.classList.add('cursor-help');
        wrap.dataset.failedReason = reason || 'Message failed to send.';
        wrap.setAttribute('aria-label', wrap.dataset.failedReason);
    } else {
        wrap.title = normalized.charAt(0).toUpperCase() + normalized.slice(1);
    }

    if (normalized === 'failed') {
        const label = document.createElement('span');
        label.className = 'font-semibold text-red-500';
        label.textContent = 'Failed';
        wrap.appendChild(label);
        return wrap;
    }

    const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    svg.setAttribute('viewBox', '0 0 16 15');
    svg.setAttribute('width', '14');
    svg.setAttribute('height', '14');
    svg.setAttribute('aria-label', normalized);
    svg.classList.add(normalized === 'read' ? 'text-[#53bdeb]' : 'text-text-body/50');

    const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
    path.setAttribute('fill', 'currentColor');
    path.setAttribute('d', INBOX_DOUBLE_CHECK_PATH);
    svg.appendChild(path);
    wrap.appendChild(svg);

    return wrap;
}

function ensureInboxFailedTooltip() {
    let tip = document.querySelector('[data-inbox-failed-tooltip]');
    if (tip) {
        if (tip.parentElement !== document.body) {
            document.body.appendChild(tip);
        }

        return tip;
    }

    tip = document.createElement('div');
    tip.dataset.inboxFailedTooltip = '';
    tip.className =
        'pointer-events-none fixed z-[220] hidden max-w-xs rounded-md bg-gray-900 px-3 py-2 text-left text-xs leading-relaxed text-white shadow-lg';
    tip.setAttribute('role', 'tooltip');
    document.body.appendChild(tip);

    return tip;
}

function positionInboxFailedTooltip(tip, anchor) {
    const rect = anchor.getBoundingClientRect();
    const pad = 8;
    const tipRect = tip.getBoundingClientRect();
    let left = rect.right - tipRect.width;
    let top = rect.top - tipRect.height - pad;

    if (left < pad) {
        left = pad;
    }
    if (left + tipRect.width > window.innerWidth - pad) {
        left = window.innerWidth - tipRect.width - pad;
    }
    if (top < pad) {
        top = rect.bottom + pad;
    }

    tip.style.left = `${Math.round(left)}px`;
    tip.style.top = `${Math.round(top)}px`;
}

function showInboxFailedTooltip(anchor) {
    const reason = String(anchor.dataset.failedReason || '').trim() || 'Message failed to send.';
    const tip = ensureInboxFailedTooltip();
    tip.textContent = reason;
    tip.classList.remove('hidden');
    // Measure then place (needs visible for getBoundingClientRect).
    positionInboxFailedTooltip(tip, anchor);
}

function hideInboxFailedTooltip() {
    const tip = document.querySelector('[data-inbox-failed-tooltip]');
    if (tip) {
        tip.classList.add('hidden');
        tip.textContent = '';
    }
}

function initInboxFailedTooltips() {
    ensureInboxFailedTooltip();

    document.addEventListener('mouseover', (event) => {
        const anchor = event.target.closest?.('[data-message-status="failed"]');
        if (!anchor) {
            return;
        }

        showInboxFailedTooltip(anchor);
    });

    document.addEventListener('mouseout', (event) => {
        const anchor = event.target.closest?.('[data-message-status="failed"]');
        if (!anchor) {
            return;
        }

        const next = event.relatedTarget;
        if (next && anchor.contains(next)) {
            return;
        }

        hideInboxFailedTooltip();
    });

    document.addEventListener('scroll', hideInboxFailedTooltip, true);
    window.addEventListener('resize', hideInboxFailedTooltip);
}

function appendInboxMessageMeta(bubble, message) {
    const footer = document.createElement('div');
    footer.className =
        'mt-1 flex items-center justify-end gap-1 text-[10px] leading-none text-text-body/55';

    const time = document.createElement('span');
    time.dataset.messageTime = '';
    time.textContent = String(message.time || '').trim();
    footer.appendChild(time);

    if (message.is_outbound) {
        footer.appendChild(inboxMessageStatusIcon(message.status, message.failed_reason));
    }

    bubble.appendChild(footer);
}

function updateInboxMessageStatus(message) {
    if (!message?.uuid) {
        return;
    }

    const row = document.querySelector(`[data-inbox-messages] [data-message-uuid="${String(message.uuid).replace(/"/g, '')}"]`);
    if (!row) {
        return;
    }

    const bubble = row.querySelector(':scope > div') || row.lastElementChild;
    if (!bubble) {
        return;
    }

    let statusEl = bubble.querySelector('[data-message-status]');
    const next = inboxMessageStatusIcon(message.status, message.failed_reason);

    if (statusEl) {
        statusEl.replaceWith(next);
    } else if (message.is_outbound !== false) {
        let footer = bubble.querySelector('[data-message-time]')?.parentElement;
        if (!footer) {
            appendInboxMessageMeta(bubble, message);
            return;
        }
        footer.appendChild(next);
    }
}

function fillMessageBubble(bubble, message) {
    const body = messageDisplayBody(message);
    const messageType = String(message.message_type || 'text');
    const mediaUrl = message.media_url || null;
    const fileName = message.file_name || null;
    const displayBody = body !== '' ? body : `[${messageType}]`;

    const appendText = (text, className = '') => {
        const textEl = document.createElement('div');
        if (className) {
            textEl.className = className;
        }
        textEl.textContent = text;
        bubble.appendChild(textEl);
    };

    const appendClickableMedia = (type) => {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.dataset.inboxMediaOpen = '';
        btn.dataset.mediaType = type;
        btn.dataset.mediaUrl = mediaUrl;
        btn.className = 'mb-2 block max-w-full cursor-zoom-in border-0 bg-transparent p-0 text-left';

        if (type === 'video') {
            const video = document.createElement('video');
            video.src = mediaUrl;
            video.className = 'pointer-events-none max-h-72 max-w-full rounded-lg';
            video.muted = true;
            video.preload = 'metadata';
            btn.appendChild(video);
        } else {
            const img = document.createElement('img');
            img.src = mediaUrl;
            img.alt = fileName || 'Media';
            img.className = 'pointer-events-none max-h-72 max-w-full rounded-lg object-contain';
            btn.appendChild(img);
        }

        bubble.appendChild(btn);
    };

    if ((messageType === 'image' || messageType === 'sticker') && mediaUrl) {
        appendClickableMedia('image');
        if (body !== '') {
            appendText(body);
        }
        appendInboxMessageMeta(bubble, message);
        return;
    }

    if (messageType === 'video' && mediaUrl) {
        appendClickableMedia('video');
        if (body !== '') {
            appendText(body);
        }
        appendInboxMessageMeta(bubble, message);
        return;
    }

    if (messageType === 'audio' && mediaUrl) {
        const audio = document.createElement('audio');
        audio.src = mediaUrl;
        audio.controls = true;
        audio.className = 'w-full';
        bubble.appendChild(audio);
        appendInboxMessageMeta(bubble, message);
        return;
    }

    if (messageType === 'document' && mediaUrl) {
        const link = document.createElement('a');
        link.href = mediaUrl;
        link.target = '_blank';
        link.rel = 'noopener';
        link.className = 'font-semibold text-green-700 underline';
        link.textContent = fileName || 'Download document';
        bubble.appendChild(link);
        if (body !== '') {
            appendText(body, 'mt-1');
        }
        appendInboxMessageMeta(bubble, message);
        return;
    }

    if (messageType === 'location' && message.latitude != null && message.longitude != null) {
        const link = document.createElement('a');
        link.href = `https://www.google.com/maps?q=${message.latitude},${message.longitude}`;
        link.target = '_blank';
        link.rel = 'noopener';
        link.className = 'font-semibold text-green-700 underline';
        link.textContent = `View location (${message.latitude}, ${message.longitude})`;
        bubble.appendChild(link);
        appendInboxMessageMeta(bubble, message);
        return;
    }

    if (messageType === 'contact' && Array.isArray(message.contacts) && message.contacts.length > 0) {
        const wrap = document.createElement('div');
        wrap.className = 'flex flex-col gap-2';

        message.contacts.forEach((sharedContact) => {
            const card = document.createElement('div');
            card.className = 'rounded-lg border border-green-200/80 bg-white/70 px-3 py-2';

            const label = document.createElement('div');
            label.className = 'text-[10px] font-semibold uppercase tracking-wide text-green-700';
            label.textContent = 'Contact';
            card.appendChild(label);

            const name = document.createElement('div');
            name.className = 'mt-1 font-semibold text-text-subtle';
            name.textContent =
                sharedContact?.name?.formatted_name ||
                sharedContact?.name?.first_name ||
                sharedContact?.name ||
                body ||
                'Contact';
            card.appendChild(name);

            const phone = sharedContact?.phones?.[0]?.phone;
            if (phone) {
                const phoneEl = document.createElement('div');
                phoneEl.className = 'text-text-body/70';
                phoneEl.textContent = String(phone);
                card.appendChild(phoneEl);
            }

            const company = sharedContact?.org?.company;
            if (company) {
                const companyEl = document.createElement('div');
                companyEl.className = 'text-text-body/60';
                companyEl.textContent = String(company);
                card.appendChild(companyEl);
            }

            wrap.appendChild(card);
        });

        bubble.appendChild(wrap);
        appendInboxMessageMeta(bubble, message);
        return;
    }

    if (messageType === 'template') {
        const templateCode = String(message.template_code || '').trim();
        const templateName = String(message.template_name || '').trim();
        const buttons = Array.isArray(message.template_buttons) ? message.template_buttons : [];
        const isProviderCodeBody = body !== '' && (body === templateCode || /^[0-9]{10,}$/.test(body));

        if (body !== '' && !isProviderCodeBody) {
            appendText(body);

            if (buttons.length > 0) {
                const row = document.createElement('div');
                row.className = 'mt-1 flex flex-wrap gap-1.5';
                buttons.forEach((button) => {
                    const label = String(button?.text || button || '').trim();
                    if (!label) {
                        return;
                    }

                    const chip = document.createElement('span');
                    chip.className = 'rounded border border-green-300 bg-white/80 px-2 py-1 text-[11px] font-medium text-green-800';
                    chip.textContent = label;
                    row.appendChild(chip);
                });
                bubble.appendChild(row);
            }

            const caption = document.createElement('div');
            caption.className = 'mt-1 text-[10px] font-semibold uppercase tracking-wide text-green-700';
            caption.textContent = templateName ? `Template · ${templateName}` : 'Template';
            bubble.appendChild(caption);
            appendInboxMessageMeta(bubble, message);
            return;
        }

        const chip = document.createElement('div');
        chip.className =
            'inline-flex items-center gap-2 rounded-full border border-green-300 bg-white/70 px-3 py-1 text-[11px] font-semibold text-green-800';

        const kind = document.createElement('span');
        kind.textContent = 'Template';
        chip.appendChild(kind);

        const code = document.createElement('span');
        code.className = 'font-medium text-text-body';
        code.textContent = templateName || templateCode || body || 'message';
        chip.appendChild(code);

        bubble.appendChild(chip);
        appendInboxMessageMeta(bubble, message);
        return;
    }

    if (messageType === 'interactive') {
        const preview = message.interactive_preview || null;
        const interactiveBody = String(preview?.body || body || '[interactive]').trim();
        const buttons = Array.isArray(preview?.buttons)
            ? preview.buttons
            : interactiveButtonLabels(message.interactive);

        const wrap = document.createElement('div');
        wrap.className = 'flex flex-col gap-2';

        const bodyEl = document.createElement('div');
        bodyEl.textContent = interactiveBody || '[interactive]';
        wrap.appendChild(bodyEl);

        if (buttons.length > 0) {
            const row = document.createElement('div');
            row.className = 'mt-1 flex flex-wrap gap-1.5';
            buttons.forEach((label) => {
                const chip = document.createElement('span');
                chip.className =
                    'rounded border border-green-300 bg-white/80 px-2 py-1 text-[11px] font-medium text-green-800';
                chip.textContent = String(label);
                row.appendChild(chip);
            });
            wrap.appendChild(row);
        }

        bubble.appendChild(wrap);
        appendInboxMessageMeta(bubble, message);
        return;
    }

    bubble.textContent = displayBody;
    appendInboxMessageMeta(bubble, message);
}

/**
 * Tenant-wide Echo subscription + list polling.
 * Runs even when no conversation is open so new chats appear live.
 */
function subscribeInboxEcho(channelName, bindings) {
    if (!window.Echo || !channelName) {
        return;
    }

    try {
        const channel = window.Echo.private(channelName);
        Object.entries(bindings).forEach(([eventName, handler]) => {
            channel.listen(eventName, handler);
        });
    } catch {
        // Websocket subscribe must never block polling.
    }
}

function initInboxRealtime() {
    seedInboxUnreadFromDom();

    const root = inboxRoot();
    if (!root) {
        return;
    }

    const tenantId = root.dataset.tenantId;
    const realtimeEnabled = root.dataset.realtimeEnabled === '1';
    const threadsUrl = root.dataset.threadsUrl;
    // Poll stays fast even if Echo exists but the websocket never connects.
    const listPollMs = Math.min(Number(root.dataset.pollInterval || 30000), 4000);

    if (realtimeEnabled && tenantId && window.Echo) {
        subscribeInboxEcho(`inbox.${tenantId}`, {
            '.thread.updated': (payload) => {
                // Mark-read / assignee / AI toggles — update in place, do not reorder.
                upsertThreadRow(payload.thread, { bump: false });
            },
            '.message.created': (payload) => {
                const message = payload.message || {};
                const isOutbound = Boolean(message.is_outbound);
                const chat = document.querySelector('[data-inbox-chat]');
                const openUuid = chat?.dataset.conversationUuid;
                const isOpenChat = openUuid && payload.conversation_uuid === openUuid;
                const viewingOpenChat =
                    isOpenChat &&
                    document.visibilityState === 'visible' &&
                    document.hasFocus();

                // Outbound never adds unread. Open+focused chat is treated as read.
                const thread = {
                    ...(payload.thread || {}),
                    unread: isOutbound || viewingOpenChat
                        ? 0
                        : Number(payload.thread?.unread || 0),
                };

                // Suppress badge-based notify here — showInboxWebNotification below
                // has the real message body and correct open-chat/focus checks.
                upsertThreadRow(thread, { notify: false, bump: true });

                if (!isOutbound) {
                    showInboxWebNotification(thread, message);
                }

                if (isOpenChat && typeof window.__inboxAppendMessage === 'function') {
                    window.__inboxAppendMessage(message);
                } else if (isOpenChat) {
                    maybeRefreshOpenChat(thread);
                }
            },
            '.message.status_updated': (payload) => {
                updateInboxMessageStatus(payload.message || {});
            },
        });
    }

    if (!threadsUrl || listPollMs <= 0) {
        return;
    }

    let threadsAppended = false;
    let loadingMoreThreads = false;

    const refreshThreadList = async () => {
        // Don't clobber an active search query mid-typing.
        const searchInput = document.querySelector('[data-inbox-search-input]');
        if (searchInput && document.activeElement === searchInput && searchInput.value.trim() !== '') {
            return;
        }

        if (loadingMoreThreads) {
            return;
        }

        try {
            const params = new URLSearchParams(window.location.search);
            params.delete('cursor');
            const response = await fetch(`${threadsUrl}?${params.toString()}`, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });

            if (!response.ok) {
                return;
            }

            const data = await response.json();
            const threads = Array.isArray(data.items) ? data.items : [];
            const list = document.querySelector('[data-inbox-thread-list]');
            if (!list) {
                return;
            }

            const applyUnreadTotal = () => {
                if (typeof data.unread_total === 'number') {
                    setInboxNavBadge(data.unread_total);
                } else {
                    syncInboxNavBadge();
                }
            };

            if (threadsAppended) {
                // User already loaded older pages — update in place only.
                // Do not prepend known rows (that caused the selected chat to jump).
                const selectedUuid = inboxSelectedConversationUuid();
                [...threads].reverse().forEach((thread) => {
                    if (!thread?.uuid) {
                        return;
                    }

                    const existing = list.querySelector(`[data-thread-uuid="${thread.uuid}"]`);
                    if (existing) {
                        upsertThreadRow(thread, { bump: false });
                    } else {
                        list.insertAdjacentHTML('afterbegin', buildThreadRowHtml(thread, selectedUuid));
                        rememberThreadUnread(thread, { notify: true });
                        maybeRefreshOpenChat(thread);
                    }
                });
                applyUnreadTotal();
                return;
            }

            setThreadsCursorState({
                cursor: data.next_cursor || '',
                hasMore: Boolean(data.has_more),
            });
            threadsAppended = false;

            if (threads.length === 0) {
                if (!list.querySelector('[data-thread-uuid]')) {
                    list.innerHTML = '<div class="p-6 text-center text-sm text-text-body/70" data-inbox-thread-empty>No conversations yet.</div>';
                }
                applyUnreadTotal();
                return;
            }

            const selectedUuid = inboxSelectedConversationUuid();
            // Atomic rebuild — moving rows one-by-one into a fragment made the
            // open chat visibly climb to the top before settling.
            const seen = new Set();
            const html = [];

            threads.forEach((thread) => {
                if (!thread?.uuid || seen.has(thread.uuid)) {
                    return;
                }
                seen.add(thread.uuid);
                html.push(buildThreadRowHtml(thread, selectedUuid));
                rememberThreadUnread(thread, { notify: true });
                maybeRefreshOpenChat(thread);
            });

            const prevScroll = list.scrollTop;
            list.innerHTML = html.join('');
            list.scrollTop = prevScroll;
            applyUnreadTotal();
        } catch {
            // Ignore transient poll errors.
        }
    };

    const loadMoreThreads = async () => {
        const state = getThreadsCursorState();
        if (!state.hasMore || !state.cursor || loadingMoreThreads) {
            return;
        }

        loadingMoreThreads = true;

        try {
            const params = new URLSearchParams(window.location.search);
            params.set('cursor', state.cursor);
            const response = await fetch(`${threadsUrl}?${params.toString()}`, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });

            if (!response.ok) {
                return;
            }

            const data = await response.json();
            const threads = Array.isArray(data.items) ? data.items : [];
            const list = document.querySelector('[data-inbox-thread-list]');
            if (!list) {
                return;
            }

            const selectedUuid = inboxSelectedConversationUuid();
            const existing = new Set(
                [...list.querySelectorAll('[data-thread-uuid]')].map((el) => el.dataset.threadUuid),
            );

            threads.forEach((thread) => {
                if (!thread?.uuid || existing.has(thread.uuid)) {
                    return;
                }

                list.insertAdjacentHTML('beforeend', buildThreadRowHtml(thread, selectedUuid));
                existing.add(thread.uuid);
            });

            setThreadsCursorState({
                cursor: data.next_cursor || '',
                hasMore: Boolean(data.has_more),
            });
            threadsAppended = true;
        } catch {
            // Ignore transient load-more errors.
        } finally {
            loadingMoreThreads = false;
        }
    };

    const threadList = document.querySelector('[data-inbox-thread-list]');
    threadList?.addEventListener('scroll', () => {
        if (!threadList) {
            return;
        }

        const remaining = threadList.scrollHeight - threadList.scrollTop - threadList.clientHeight;
        if (remaining < 120) {
            loadMoreThreads();
        }
    });

    window.__inboxResetThreadsPagination = () => {
        threadsAppended = false;
    };

    // Immediate catch-up, then legacy-like list polling.
    refreshThreadList();
    window.setInterval(refreshThreadList, listPollMs);
}

function initInboxChat() {
    const chat = document.querySelector('[data-inbox-chat]');
    if (!chat) return;

    // Bind assign early — must not depend on composer/send URLs.
    initInboxAssignee(chat);

    const form = chat.querySelector('[data-inbox-send-form]');
    const input = chat.querySelector('[data-inbox-message-input]');
    const messagesEl = chat.querySelector('[data-inbox-messages]');
    const sendUrl = chat.dataset.sendUrl;
    const messagesUrl = chat.dataset.messagesUrl;
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;

    if (!form || !input || !messagesUrl || !sendUrl || !csrf) return;

    const serviceWindow = initInboxServiceWindow(chat);
    const seenMessageUuids = new Set();
    const root = inboxRoot();
    const realtimeEnabled = root?.dataset.realtimeEnabled === '1';
    const tenantId = root?.dataset.tenantId;
    const conversationUuid = chat.dataset.conversationUuid;
    const pollInterval = 3000;
    let loadingOlderMessages = false;

    // Seed uuids already rendered by Blade so Echo/poll don't duplicate them.
    messagesEl?.querySelectorAll('[data-message-uuid]').forEach((el) => {
        if (el.dataset.messageUuid) {
            seenMessageUuids.add(el.dataset.messageUuid);
        }
    });

    const buildMessageRow = (message) => {
        const row = document.createElement('div');
        row.className = message.is_outbound ? 'flex justify-end' : 'flex justify-start';
        if (message.uuid) {
            row.dataset.messageUuid = message.uuid;
        }
        if (message.id != null) {
            row.dataset.messageId = String(message.id);
        }
        if (message.date_key) {
            row.dataset.dateKey = String(message.date_key);
        }
        if (message.date_label) {
            row.dataset.dateLabel = String(message.date_label);
        }

        const bubble = document.createElement('div');
        bubble.className = message.is_outbound
            ? 'max-w-[640px] rounded-bl-[12px] rounded-tl-[12px] rounded-tr-[12px] bg-green-100 p-4 text-xs leading-[1.8] text-text-body'
            : 'max-w-[640px] rounded-bl-[12px] rounded-br-[12px] rounded-tr-[12px] bg-muted-surface p-4 text-xs leading-[1.8] text-text-body';
        bubble.style.fontFamily = "'Poppins', var(--font-sans)";
        fillMessageBubble(bubble, message);
        row.appendChild(bubble);

        return row;
    };

    const createDateSeparator = (dateKey, dateLabel) => {
        const sep = document.createElement('div');
        sep.className = 'flex justify-center py-1';
        sep.dataset.inboxDateSep = String(dateKey);
        const chip = document.createElement('span');
        chip.className = 'rounded-full bg-elevated/90 px-3 py-1 text-[11px] font-semibold text-text-body shadow-sm';
        chip.textContent = dateLabel || dateKey;
        sep.appendChild(chip);

        return sep;
    };

    const rebuildDateSeparators = () => {
        if (!messagesEl) {
            return;
        }

        messagesEl.querySelectorAll('[data-inbox-date-sep]').forEach((el) => el.remove());

        let lastKey = null;
        messagesEl.querySelectorAll('[data-message-uuid], [data-message-id]').forEach((row) => {
            const key = row.dataset.dateKey;
            if (!key || key === lastKey) {
                return;
            }

            messagesEl.insertBefore(createDateSeparator(key, row.dataset.dateLabel || key), row);
            lastKey = key;
        });
    };

    const scrollMessagesToLatest = () => {
        if (!messagesEl) {
            return;
        }

        const jump = () => {
            messagesEl.scrollTop = messagesEl.scrollHeight;
        };

        jump();
        requestAnimationFrame(jump);
        window.setTimeout(jump, 50);
        window.setTimeout(jump, 250);

        messagesEl.querySelectorAll('img').forEach((img) => {
            if (!img.complete) {
                img.addEventListener('load', jump, { once: true });
            }
        });
    };

    const appendMessage = (message, { scroll = true } = {}) => {
        if (!messagesEl || !message) return false;

        if (message.uuid) {
            if (seenMessageUuids.has(message.uuid)) {
                return false;
            }

            seenMessageUuids.add(message.uuid);
        }

        const lastRow = [...messagesEl.querySelectorAll('[data-message-uuid], [data-message-id]')].at(-1);
        const lastKey = lastRow?.dataset.dateKey || null;
        if (message.date_key && message.date_key !== lastKey) {
            messagesEl.appendChild(createDateSeparator(message.date_key, message.date_label));
        }

        messagesEl.appendChild(buildMessageRow(message));

        if (scroll) {
            scrollMessagesToLatest();
        }

        return true;
    };

    const prependMessage = (message) => {
        if (!messagesEl || !message) return false;

        if (message.uuid) {
            if (seenMessageUuids.has(message.uuid)) {
                return false;
            }

            seenMessageUuids.add(message.uuid);
        }

        messagesEl.insertBefore(buildMessageRow(message), messagesEl.firstChild);
        rebuildDateSeparators();

        return true;
    };

    const loadOlderMessages = async () => {
        if (!messagesEl || !messagesUrl || loadingOlderMessages) {
            return;
        }

        if (messagesEl.dataset.hasMore !== '1') {
            return;
        }

        const beforeId = messagesEl.dataset.oldestId;
        if (!beforeId) {
            return;
        }

        loadingOlderMessages = true;
        const previousHeight = messagesEl.scrollHeight;
        const previousTop = messagesEl.scrollTop;

        try {
            const params = new URLSearchParams(window.location.search);
            params.set('before_id', beforeId);
            const response = await fetch(`${messagesUrl}?${params.toString()}`, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });

            if (!response.ok) {
                return;
            }

            const data = await response.json();
            const items = Array.isArray(data.items) ? data.items : [];
            const frag = document.createDocumentFragment();

            items.forEach((message) => {
                if (message.uuid && seenMessageUuids.has(message.uuid)) {
                    return;
                }

                if (message.uuid) {
                    seenMessageUuids.add(message.uuid);
                }

                frag.appendChild(
                    buildMessageRow({
                        id: message.id,
                        uuid: message.uuid,
                        body: message.body,
                        message_type: message.message_type,
                        is_outbound: message.is_outbound ?? message.direction === 'outbound',
                        status: message.status,
                        time: message.time,
                        date_key: message.date_key,
                        date_label: message.date_label,
                        media_url: message.media_url,
                        file_name: message.file_name,
                        latitude: message.latitude,
                        longitude: message.longitude,
                        contacts: message.contacts,
                        template_code: message.template_code,
                        template_name: message.template_name,
                        template_buttons: message.template_buttons,
                        interactive: message.interactive,
                        interactive_preview: message.interactive_preview,
                        failed_reason: message.failed_reason,
                    }),
                );
            });

            if (frag.childNodes.length > 0) {
                messagesEl.insertBefore(frag, messagesEl.firstChild);
                rebuildDateSeparators();
            }

            if (data.oldest_id != null) {
                messagesEl.dataset.oldestId = String(data.oldest_id);
            }

            messagesEl.dataset.hasMore = data.has_more ? '1' : '0';
            messagesEl.scrollTop = previousTop + (messagesEl.scrollHeight - previousHeight);
        } catch {
            // Ignore transient older-message load errors.
        } finally {
            loadingOlderMessages = false;
        }
    };

    messagesEl?.addEventListener('scroll', () => {
        if (!messagesEl || messagesEl.scrollTop > 80) {
            return;
        }

        loadOlderMessages();
    });

    window.__inboxAppendMessage = (message) => {
        if (!message) {
            return;
        }

        if (!messageDisplayBody(message) && String(message.message_type || 'text') === 'text') {
            refreshMessages();
            return;
        }

        const before = message.uuid ? seenMessageUuids.has(message.uuid) : false;
        appendMessage(message);

        if (!before && message.uuid && seenMessageUuids.has(message.uuid) && !message.is_outbound) {
            markConversationRead();
        }
    };

    const markConversationRead = async () => {
        const readUrl = chat.dataset.readUrl;
        if (!readUrl || !csrf) {
            return;
        }

        const openUuid = chat.dataset.conversationUuid;
        if (openUuid) {
            upsertThreadRow({
                uuid: openUuid,
                unread: 0,
            });
        }

        try {
            await fetch(readUrl, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrf,
                },
                credentials: 'same-origin',
            });
        } catch {
            // Ignore mark-read failures; live append still works.
        }
    };

    const refreshMessages = async () => {
        try {
            const params = new URLSearchParams(window.location.search);
            params.delete('cursor');
            const response = await fetch(`${messagesUrl}?${params.toString()}`, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });

            if (!response.ok) return;

            const data = await response.json();
            if (!Array.isArray(data.items) || !messagesEl) return;

            let appendedInbound = false;

            data.items.forEach((message) => {
                const before = message.uuid ? seenMessageUuids.has(message.uuid) : false;
                const isOutbound = message.is_outbound ?? message.direction === 'outbound';

                appendMessage({
                    id: message.id,
                    uuid: message.uuid,
                    body: message.body,
                    message_type: message.message_type,
                    is_outbound: isOutbound,
                    status: message.status,
                    time: message.time,
                    date_key: message.date_key,
                    date_label: message.date_label,
                    media_url: message.media_url,
                    file_name: message.file_name,
                    latitude: message.latitude,
                    longitude: message.longitude,
                    contacts: message.contacts,
                    template_code: message.template_code,
                    template_name: message.template_name,
                    template_buttons: message.template_buttons,
                    interactive: message.interactive,
                    interactive_preview: message.interactive_preview,
                    failed_reason: message.failed_reason,
                });

                // Poll catch-up: if the row already existed, still refresh ticks.
                if (before && isOutbound) {
                    updateInboxMessageStatus(message);
                }

                if (!before && message.uuid && seenMessageUuids.has(message.uuid) && !isOutbound) {
                    appendedInbound = true;
                    showInboxWebNotification(
                        {
                            uuid: conversationUuid,
                            name:
                                chat.querySelector('.fd-card-title')?.textContent?.trim() ||
                                threadDisplayName({ uuid: conversationUuid }),
                            preview: message.body,
                        },
                        message,
                    );
                }
            });

            if (appendedInbound) {
                // Only mark read when the agent is actually viewing this tab.
                if (document.visibilityState === 'visible' && document.hasFocus()) {
                    await markConversationRead();
                }
            }
        } catch {
            // Ignore transient poll errors.
        }
    };

    window.__inboxRefreshMessages = refreshMessages;

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        const body = input.value.trim();
        if (!body) return;

        if (!serviceWindow.canSendFreeForm()) {
            const walletBlocked =
                chat.dataset.walletBlocked === '1' || root?.dataset.walletBlocked === '1';
            showAppAlert(
                walletBlocked
                    ? 'Your wallet balance is currently insufficient to send messages.'
                    : 'Outside the 24-hour messaging window. Send an approved template instead.',
                walletBlocked ? 'Insufficient wallet balance' : 'Session expired',
            );

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
                status: data.message?.status ?? 'queued',
                time: data.message?.time,
                date_key: data.message?.date_key,
                date_label: data.message?.date_label,
                message_type: data.message?.message_type ?? 'text',
                contacts: data.message?.contacts,
                template_code: data.message?.template_code,
                interactive: data.message?.interactive,
                failed_reason: data.message?.failed_reason,
            });
            input.value = '';
            refreshMessages().catch(() => {});
        } catch {
            showAppAlert('Unable to send message. Please try again.', 'Unable to send');
        } finally {
            button?.removeAttribute('disabled');
        }
    });

    const optInButtons = chat.querySelectorAll('[data-inbox-resend-opt-in]');
    const optInUrl = chat.dataset.optInUrl;

    if (optInButtons.length && optInUrl) {
        optInButtons.forEach((optInButton) => {
            optInButton.addEventListener('click', async () => {
                optInButton.setAttribute('disabled', 'disabled');

                try {
                    const response = await fetch(optInUrl, {
                        method: 'POST',
                        headers: {
                            Accept: 'application/json',
                            'X-CSRF-TOKEN': csrf,
                        },
                        credentials: 'same-origin',
                    });

                    if (!response.ok) {
                        const error = await response.json().catch(() => ({}));
                        showAppAlert(inboxApiErrorMessage(error, 'Unable to send opt-in.'), 'Opt-in failed');

                        return;
                    }

                    const data = await response.json();
                    appendMessage({
                        body: data.message?.body ?? 'Opt-in template sent.',
                        is_outbound: true,
                        message_type: data.message?.message_type ?? 'template',
                        time: data.message?.time,
                        date_key: data.message?.date_key,
                        date_label: data.message?.date_label,
                    });
                    showAppAlert('Opt-in template sent.', 'Sent');
                } catch {
                    showAppAlert('Unable to send opt-in.', 'Opt-in failed');
                } finally {
                    optInButton.removeAttribute('disabled');
                }
            });
        });
    }

    // Conversation-scoped Echo (tenant channel is handled in initInboxRealtime).
    if (realtimeEnabled && tenantId && conversationUuid && window.Echo) {
        subscribeInboxEcho(`inbox.${tenantId}.conversation.${conversationUuid}`, {
            '.message.created': (payload) => {
                if (payload.conversation_uuid !== conversationUuid) {
                    return;
                }

                const message = payload.message || {};
                const isOutbound = Boolean(message.is_outbound);
                const viewing =
                    document.visibilityState === 'visible' && document.hasFocus();

                window.__inboxAppendMessage?.(message);

                upsertThreadRow(
                    {
                        ...(payload.thread || {}),
                        uuid: conversationUuid,
                        unread: isOutbound || viewing ? 0 : Number(payload.thread?.unread || 1),
                    },
                    { notify: false, bump: true },
                );

                if (!isOutbound) {
                    showInboxWebNotification(
                        {
                            ...(payload.thread || {}),
                            uuid: conversationUuid,
                            name:
                                payload.thread?.name ||
                                chat.querySelector('.fd-card-title')?.textContent?.trim() ||
                                'WapApp Inbox',
                        },
                        message,
                    );

                    if (viewing) {
                        markConversationRead();
                    }
                }

                serviceWindow.refresh();
            },
            '.message.status_updated': (payload) => {
                if (payload.conversation_uuid !== conversationUuid) {
                    return;
                }

                updateInboxMessageStatus(payload.message || {});
            },
        });
    }

    if (pollInterval > 0) {
        refreshMessages();
        window.setInterval(async () => {
            await refreshMessages();
            await serviceWindow.refresh();
        }, pollInterval);
    }

    // Open chat is read (WhatsApp-style) — clear badge even if SSR already marked read.
    markConversationRead();
    scrollMessagesToLatest();
}

function initInboxAssignee(chat) {
    const assigneeSelect = chat.querySelector('[data-inbox-assignee]');
    const assignUrl = chat.dataset.assignUrl;
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;

    if (!assigneeSelect || !assignUrl || !csrf || assigneeSelect.dataset.assignBound === '1') {
        return;
    }

    assigneeSelect.dataset.assignBound = '1';
    let previousAssignee = assigneeSelect.value;

    assigneeSelect.addEventListener('change', async () => {
        const nextValue = assigneeSelect.value;

        try {
            const response = await fetch(assignUrl, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                body: JSON.stringify({ assignee: nextValue }),
            });

            if (!response.ok) {
                const error = await response.json().catch(() => ({}));
                assigneeSelect.value = previousAssignee;
                showAppAlert(inboxApiErrorMessage(error, 'Unable to assign agent.'), 'Assign failed');

                return;
            }

            previousAssignee = nextValue;
            const label =
                nextValue === 'unassigned'
                    ? 'Unassigned'
                    : assigneeSelect.options[assigneeSelect.selectedIndex]?.textContent?.trim() || 'Agent';

            const openUuid = chat.dataset.conversationUuid;
            if (openUuid) {
                upsertThreadRow({
                    uuid: openUuid,
                    assignee: nextValue === 'unassigned' ? null : label,
                });
            }

            showAppAlert(`Chat assigned to ${label}.`, 'Assigned');
        } catch {
            assigneeSelect.value = previousAssignee;
            showAppAlert('Unable to assign agent. Check your connection and try again.', 'Assign failed');
        }
    });
}

function ensureInboxMediaLightbox() {
    let root = document.querySelector('[data-inbox-media-lightbox]');
    if (root) {
        if (root.parentElement !== document.body) {
            document.body.appendChild(root);
        }

        return root;
    }

    root = document.createElement('div');
    root.id = 'inbox-media-lightbox';
    root.dataset.inboxMediaLightbox = '';
    root.className = 'fixed inset-0 z-[200] hidden items-center justify-center bg-black/85 p-4';
    root.setAttribute('role', 'dialog');
    root.setAttribute('aria-modal', 'true');
    root.innerHTML = `
      <button type="button" data-inbox-media-close class="absolute top-4 right-4 rounded-full bg-white/15 px-3 py-1.5 text-sm font-semibold text-white hover:bg-white/25">Close</button>
      <img data-inbox-lightbox-image src="" alt="" class="hidden max-h-[90vh] max-w-[90vw] rounded-lg object-contain">
      <video data-inbox-lightbox-video src="" class="hidden max-h-[90vh] max-w-[90vw] rounded-lg" controls playsinline></video>
    `;
    document.body.appendChild(root);

    return root;
}

function openInboxMediaLightbox(type, url) {
    if (!url) {
        return;
    }

    const root = ensureInboxMediaLightbox();
    const image = root.querySelector('[data-inbox-lightbox-image]');
    const video = root.querySelector('[data-inbox-lightbox-video]');

    if (image) {
        image.classList.add('hidden');
        image.removeAttribute('src');
    }
    if (video) {
        video.classList.add('hidden');
        video.pause?.();
        video.removeAttribute('src');
    }

    if (type === 'video' && video) {
        video.src = url;
        video.classList.remove('hidden');
        video.play?.().catch(() => {});
    } else if (image) {
        image.src = url;
        image.classList.remove('hidden');
    }

    root.classList.remove('hidden');
    root.classList.add('flex');
}

function closeInboxMediaLightbox() {
    const root = document.querySelector('[data-inbox-media-lightbox]');
    if (!root) {
        return;
    }

    const video = root.querySelector('[data-inbox-lightbox-video]');
    if (video) {
        video.pause?.();
        video.removeAttribute('src');
        video.classList.add('hidden');
    }

    const image = root.querySelector('[data-inbox-lightbox-image]');
    if (image) {
        image.removeAttribute('src');
        image.classList.add('hidden');
    }

    root.classList.add('hidden');
    root.classList.remove('flex');
}

function initInboxMediaLightbox() {
    ensureInboxMediaLightbox();

    document.addEventListener('click', (event) => {
        const openBtn = event.target.closest?.('[data-inbox-media-open]');
        if (openBtn) {
            event.preventDefault();
            openInboxMediaLightbox(openBtn.dataset.mediaType || 'image', openBtn.dataset.mediaUrl);

            return;
        }

        if (event.target.closest?.('[data-inbox-media-close]')) {
            closeInboxMediaLightbox();

            return;
        }

        const root = document.querySelector('[data-inbox-media-lightbox]');
        if (root && !root.classList.contains('hidden') && event.target === root) {
            closeInboxMediaLightbox();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeInboxMediaLightbox();
        }
    });
}

function initInboxOutboundModals() {
    const chat = document.querySelector('[data-inbox-chat]');
    if (!chat) return;

    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    const mediaUrl = chat.dataset.mediaUrl;
    const templateUrl = chat.dataset.templateUrl;
    const flowUrl = chat.dataset.flowUrl;
    const flowsUrl = chat.dataset.flowsUrl;
    const interactiveUrl = chat.dataset.interactiveUrl;
    const interactiveMessagesUrl = chat.dataset.interactiveMessagesUrl;
    const locationUrl = chat.dataset.locationUrl;
    const stickerUrl = chat.dataset.stickerUrl;
    const contactUrl = chat.dataset.contactUrl;
    const sendUrl = chat.dataset.sendUrl;
    const templatesUrl = chat.dataset.templatesUrl || inboxRoot()?.dataset.templatesUrl;
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
        if (!messagesEl || !message) return;

        if (typeof window.__inboxAppendMessage === 'function') {
            window.__inboxAppendMessage({
                ...message,
                is_outbound: message.is_outbound ?? true,
            });

            return;
        }

        const row = document.createElement('div');
        row.className = 'flex justify-end';

        const bubble = document.createElement('div');
        bubble.className = 'max-w-[640px] rounded-bl-[12px] rounded-tl-[12px] rounded-tr-[12px] bg-green-100 p-4 text-xs leading-[1.8] text-text-body';
        bubble.style.fontFamily = "'Poppins', var(--font-sans)";
        fillMessageBubble(bubble, {
            ...message,
            is_outbound: true,
        });

        row.appendChild(bubble);
        messagesEl.appendChild(row);
        messagesEl.scrollTop = messagesEl.scrollHeight;
    };

    const showFormError = (form, message) => {
        const errorEl = form.querySelector('[data-inbox-media-error], [data-inbox-template-error], [data-inbox-flow-error], [data-inbox-interactive-error], [data-inbox-location-error], [data-inbox-sticker-error], [data-inbox-contact-error], [data-inbox-payment-error], [data-inbox-reaction-error], [data-inbox-compose-error]');
        if (!errorEl) return;

        errorEl.textContent = message;
        errorEl.classList.toggle('hidden', !message);
    };

    const extractApiError = (payload, fallback) => inboxApiErrorMessage(payload, fallback);

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
                    showFormError(mediaForm, extractApiError(error, 'Unable to send media.'));

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
    const templateParamsEl = document.querySelector('[data-inbox-template-params]');
    const templatePreviewRoot = document.querySelector('[data-inbox-template-preview]');
    let selectedTemplatePreview = null;

    const templateVariableName = (variable) => {
        if (typeof variable === 'string') {
            return variable.trim();
        }

        return typeof variable?.name === 'string' ? variable.name.trim() : '';
    };

    const escapePreviewHtml = (value) => String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');

    const formatPreviewWhatsApp = (text) => {
        let html = escapePreviewHtml(text || '');
        html = html.replace(/```([^`]+)```/g, '<code class="wa-mono">$1</code>');
        html = html.replace(/\*([^*\n]+)\*/g, '<strong>$1</strong>');
        html = html.replace(/\^([^\^\n]+)\^/g, '<strong>$1</strong>');
        html = html.replace(/(?<![A-Za-z0-9])_([^_\n]+)_(?![A-Za-z0-9])/g, '<em>$1</em>');
        html = html.replace(/~([^~\n]+)~/g, '<del>$1</del>');

        return html.replace(/\n/g, '<br>');
    };

    const substitutePreviewPlaceholders = (text, values) => String(text || '').replace(
        /\$\(([a-zA-Z0-9_]+)\)|\{\{([a-zA-Z0-9_]+)\}\}/g,
        (match, dollarName, braceName) => {
            const name = dollarName || braceName;
            const value = values?.[name];
            if (value === undefined || value === null || String(value).trim() === '') {
                return `$(${name})`;
            }

            return String(value);
        },
    );

    const previewButtonIcon = (type) => {
        if (type === 'phone') {
            return '/images/inbox/modals/call.svg';
        }
        if (type === 'flow') {
            return '/images/templates/flow.svg';
        }
        if (type === 'quick_reply') {
            return '/images/templates/quick-reply.svg';
        }

        return '/images/inbox/modals/export.svg';
    };

    const applyInboxTemplatePreview = (preview, params = {}) => {
        if (!templatePreviewRoot) {
            return;
        }

        const data = preview && typeof preview === 'object' ? preview : null;
        const headerType = data?.header_type || 'none';
        const headerImageEl = templatePreviewRoot.querySelector('[data-preview-header-image]');
        const headerVideoEl = templatePreviewRoot.querySelector('[data-preview-header-video]');
        const headerTextEl = templatePreviewRoot.querySelector('[data-preview-header-text]');
        const bodyEl = templatePreviewRoot.querySelector('[data-preview-body]');
        const footerEl = templatePreviewRoot.querySelector('[data-preview-footer]');
        const buttonsEl = templatePreviewRoot.querySelector('[data-preview-buttons]');
        const dividerEl = templatePreviewRoot.querySelector('[data-preview-divider]');

        const resolvedBody = data
            ? substitutePreviewPlaceholders(data.body || '', params)
            : 'Select a template to preview the message.';
        const resolvedFooter = data ? substitutePreviewPlaceholders(data.footer || '', params) : '';
        const resolvedHeaderText = data ? substitutePreviewPlaceholders(data.header_text || '', params) : '';

        const showImage = headerType === 'image' && !!data?.header_image;
        const showVideo = headerType === 'video' && !!(data?.header_video || data?.header_image);
        const showHeaderText = (headerType === 'text' || headerType === 'location') && resolvedHeaderText.trim() !== '';

        if (headerImageEl) {
            headerImageEl.classList.toggle('hidden', !showImage);
            if (showImage) {
                headerImageEl.src = data.header_image;
            }
        }

        if (headerVideoEl) {
            headerVideoEl.classList.toggle('hidden', !showVideo);
            if (showVideo) {
                headerVideoEl.src = data.header_video || data.header_image || '';
            }
        }

        if (headerTextEl) {
            headerTextEl.classList.toggle('hidden', !showHeaderText);
            headerTextEl.textContent = resolvedHeaderText;
        }

        if (bodyEl) {
            bodyEl.innerHTML = formatPreviewWhatsApp(resolvedBody);
        }

        if (footerEl) {
            const footerValue = resolvedFooter.trim();
            footerEl.classList.toggle('hidden', footerValue === '');
            footerEl.textContent = footerValue;
        }

        const buttons = Array.isArray(data?.buttons) ? data.buttons : [];
        if (dividerEl) {
            dividerEl.classList.toggle('hidden', buttons.length === 0);
        }

        if (buttonsEl) {
            buttonsEl.replaceChildren();
            buttons.forEach((button) => {
                const text = String(button?.text || '').trim();
                if (!text) {
                    return;
                }

                const row = document.createElement('div');
                row.className = 'flex w-full items-center justify-center gap-2 py-1';

                const icon = document.createElement('img');
                icon.src = previewButtonIcon(button?.type);
                icon.alt = '';
                icon.className = 'size-4 shrink-0';
                icon.width = 16;
                icon.height = 16;

                const label = document.createElement('span');
                label.className = 'text-base font-medium leading-[1.4] text-link-green';
                label.style.fontFamily = 'var(--font-display)';
                label.textContent = text;

                row.appendChild(icon);
                row.appendChild(label);
                buttonsEl.appendChild(row);
            });
        }
    };

    const collectTemplateParams = () => {
        const params = {};

        templateForm?.querySelectorAll('[data-template-param]').forEach((input) => {
            const name = input.dataset.templateParam?.trim();
            if (!name) {
                return;
            }

            params[name] = input.value?.trim() ?? '';
        });

        return params;
    };

    const renderTemplateParams = (variables) => {
        if (!templateParamsEl) {
            return;
        }

        templateParamsEl.replaceChildren();

        const names = (Array.isArray(variables) ? variables : [])
            .map(templateVariableName)
            .filter(Boolean);

        if (names.length === 0) {
            templateParamsEl.classList.add('hidden');

            return;
        }

        templateParamsEl.classList.remove('hidden');

        const heading = document.createElement('p');
        heading.className = 'text-sm font-semibold text-text-primary';
        heading.textContent = 'Template variables';
        templateParamsEl.appendChild(heading);

        names.forEach((name) => {
            const wrap = document.createElement('div');
            wrap.className = 'flex flex-col gap-2';

            const label = document.createElement('label');
            label.className = 'text-sm font-medium text-text-body';
            label.htmlFor = `inbox-template-param-${name}`;
            label.textContent = name;

            const input = document.createElement('input');
            input.id = `inbox-template-param-${name}`;
            input.type = 'text';
            input.name = `template_params[${name}]`;
            input.dataset.templateParam = name;
            input.required = true;
            input.maxLength = 1024;
            input.placeholder = `Value for ${name}`;
            input.className = 'w-full rounded-xl border border-border bg-elevated px-3.5 py-3.5 text-sm focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500';
            input.addEventListener('input', () => {
                applyInboxTemplatePreview(selectedTemplatePreview, collectTemplateParams());
            });

            wrap.appendChild(label);
            wrap.appendChild(input);
            templateParamsEl.appendChild(wrap);
        });
    };

    const syncTemplateParamsFromSelect = () => {
        const option = templateSelect?.selectedOptions?.[0];
        let variables = [];
        selectedTemplatePreview = null;

        try {
            variables = JSON.parse(option?.dataset.variables || '[]');
        } catch {
            variables = [];
        }

        try {
            selectedTemplatePreview = JSON.parse(option?.dataset.preview || 'null');
        } catch {
            selectedTemplatePreview = null;
        }

        renderTemplateParams(variables);
        applyInboxTemplatePreview(selectedTemplatePreview, collectTemplateParams());
    };

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
                    option.textContent = 'No WhatsApp-approved templates — sync templates first';
                    templateSelect.appendChild(option);
                    renderTemplateParams([]);
                    applyInboxTemplatePreview(null);

                    return;
                }

                const placeholder = document.createElement('option');
                placeholder.value = '';
                placeholder.textContent = 'Select a template';
                templateSelect.appendChild(placeholder);

                items.forEach((template) => {
                    const option = document.createElement('option');
                    option.value = template.code;
                    option.textContent = template.name || template.code;
                    option.dataset.language = template.language || '';
                    option.dataset.variables = JSON.stringify(template.variables || []);
                    option.dataset.preview = JSON.stringify(template.preview || null);
                    templateSelect.appendChild(option);
                });

                syncTemplateParamsFromSelect();
            })
            .catch(() => {
                templateSelect.innerHTML = '<option value="">No templates available</option>';
                renderTemplateParams([]);
                applyInboxTemplatePreview(null);
            });

        templateSelect.addEventListener('change', syncTemplateParamsFromSelect);
    } else if (templateSelect) {
        templateSelect.innerHTML = '<option value="">Open a conversation to load templates</option>';
        applyInboxTemplatePreview(null);
    }

    if (templateForm) {
        templateForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            showFormError(templateForm, '');

            if (!templateUrl) {
                showFormError(templateForm, 'Open a conversation before sending a template.');

                return;
            }

            const templateCode = templateSelect?.value;
            if (!templateCode) {
                showFormError(templateForm, 'Select a template.');

                return;
            }

            const templateParams = collectTemplateParams();
            const missingParam = Object.entries(templateParams).find(([, value]) => value === '');
            if (missingParam) {
                showFormError(templateForm, `Enter a value for ${missingParam[0]}.`);

                return;
            }

            const language = templateSelect.selectedOptions?.[0]?.dataset.language || undefined;
            const submitButton = templateForm.querySelector('[type="submit"]');
            submitButton?.setAttribute('disabled', 'disabled');

            try {
                const response = await fetch(templateUrl, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        template_code: templateCode,
                        language,
                        template_params: templateParams,
                    }),
                });

                if (!response.ok) {
                    const error = await response.json().catch(() => ({}));
                    showFormError(templateForm, extractApiError(error, 'Unable to send template.'));

                    return;
                }

                const data = await response.json();
                appendMessage(data.message);
                closeModal(templateForm);
            } catch {
                showFormError(templateForm, 'Unable to send template.');
            } finally {
                submitButton?.removeAttribute('disabled');
            }
        });
    }

    const flowForm = document.querySelector('[data-inbox-flow-form]');
    const flowSelect = document.querySelector('[data-inbox-flow-select]');

    if (flowSelect && flowsUrl) {
        fetch(flowsUrl, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        })
            .then((response) => (response.ok ? response.json() : { flow_data: { data: [] } }))
            .then((data) => {
                const items = Array.isArray(data?.flow_data?.data) ? data.flow_data.data : [];
                flowSelect.innerHTML = '';

                if (items.length === 0) {
                    const option = document.createElement('option');
                    option.value = '';
                    option.textContent = 'No published flows';
                    flowSelect.appendChild(option);

                    return;
                }

                const placeholder = document.createElement('option');
                placeholder.value = '';
                placeholder.textContent = 'Select a flow';
                flowSelect.appendChild(placeholder);

                items.forEach((flow) => {
                    const option = document.createElement('option');
                    option.value = flow.id;
                    option.textContent = flow.name || flow.flowName || `Flow #${flow.id}`;
                    flowSelect.appendChild(option);
                });
            })
            .catch(() => {
                flowSelect.innerHTML = '<option value="">No published flows</option>';
            });
    }

    if (flowForm && flowUrl) {
        flowForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            showFormError(flowForm, '');

            if (!assertWithinWindow()) {
                return;
            }

            const flowId = flowSelect?.value;
            const body = flowForm.querySelector('[name="body"]')?.value?.trim();
            const flowCta = flowForm.querySelector('[name="flow_cta"]')?.value?.trim();

            if (!flowId) {
                showFormError(flowForm, 'Select a published flow.');

                return;
            }

            try {
                const response = await fetch(flowUrl, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        flow_id: Number(flowId),
                        body,
                        flow_cta: flowCta,
                    }),
                });

                if (!response.ok) {
                    const error = await response.json().catch(() => ({}));
                    showFormError(flowForm, error.message || 'Unable to send WhatsApp Flow.');

                    return;
                }

                const data = await response.json();
                appendMessage(data.message);
                closeModal(flowForm);
            } catch {
                showFormError(flowForm, 'Unable to send WhatsApp Flow.');
            }
        });
    }

    const interactiveForm = document.querySelector('[data-inbox-interactive-form]');
    const interactiveSelect = document.querySelector('[data-inbox-interactive-select]');

    if (interactiveSelect && interactiveMessagesUrl) {
        fetch(interactiveMessagesUrl, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        })
            .then((response) => (response.ok ? response.json() : { items: [] }))
            .then((data) => {
                const items = Array.isArray(data?.items) ? data.items : [];
                interactiveSelect.innerHTML = '';

                if (items.length === 0) {
                    const option = document.createElement('option');
                    option.value = '';
                    option.textContent = 'No free templates saved';
                    interactiveSelect.appendChild(option);

                    return;
                }

                const placeholder = document.createElement('option');
                placeholder.value = '';
                placeholder.textContent = 'Select a saved message';
                interactiveSelect.appendChild(placeholder);

                items.forEach((item) => {
                    const option = document.createElement('option');
                    option.value = item.uuid || item.id;
                    const typeLabel = item.type ? ` (${item.type})` : '';
                    option.textContent = `${item.name || 'Untitled'}${typeLabel}`;
                    interactiveSelect.appendChild(option);
                });
            })
            .catch(() => {
                interactiveSelect.innerHTML = '<option value="">No free templates saved</option>';
            });
    }

    if (interactiveForm && interactiveUrl) {
        interactiveForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            showFormError(interactiveForm, '');

            if (!assertWithinWindow()) {
                return;
            }

            const interactiveMessageId = interactiveSelect?.value;
            if (!interactiveMessageId) {
                showFormError(interactiveForm, 'Select a saved free template.');

                return;
            }

            try {
                const response = await fetch(interactiveUrl, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        interactive_message_id: interactiveMessageId,
                    }),
                });

                if (!response.ok) {
                    const error = await response.json().catch(() => ({}));
                    showFormError(interactiveForm, error.message || 'Unable to send interactive message.');

                    return;
                }

                const data = await response.json();
                appendMessage(data.message);
                closeModal(interactiveForm);
            } catch {
                showFormError(interactiveForm, 'Unable to send interactive message.');
            }
        });
    }

    const nestedFormToObject = (form) => {
        const payload = {};

        const setPath = (target, path, value) => {
            const keys = path.replace(/\]/g, '').split('[');
            let current = target;

            keys.forEach((key, index) => {
                if (index === keys.length - 1) {
                    current[key] = value;
                    return;
                }

                const nextIsIndex = /^\d+$/.test(keys[index + 1] ?? '');
                if (!current[key] || typeof current[key] !== 'object') {
                    current[key] = nextIsIndex ? [] : {};
                }
                current = current[key];
            });
        };

        new FormData(form).forEach((value, key) => {
            if (key.includes('[')) {
                setPath(payload, key, String(value ?? '').trim());
                return;
            }

            payload[key] = String(value ?? '').trim();
        });

        if (Array.isArray(payload.buttons)) {
            payload.buttons = payload.buttons.filter((button) => button?.title);
        }

        if (Array.isArray(payload.sections)) {
            payload.sections = payload.sections
                .map((section) => ({
                    title: section?.title || '',
                    rows: Array.isArray(section?.rows)
                        ? section.rows.filter((row) => row?.title)
                        : [],
                }))
                .filter((section) => section.rows.length > 0);
        }

        const retailerText = payload.product_retailer_ids_text || '';
        if (retailerText) {
            payload.product_retailer_ids = retailerText
                .split(/[\n,]+/)
                .map((id) => id.trim())
                .filter(Boolean)
                .slice(0, 10);
        }
        delete payload.product_retailer_ids_text;

        return payload;
    };

    const composeUrl = chat.dataset.interactiveComposeUrl;
    document.querySelectorAll('[data-inbox-compose-form]').forEach((composeForm) => {
        if (!composeUrl) {
            return;
        }

        composeForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            showFormError(composeForm, '');

            if (!assertWithinWindow()) {
                return;
            }

            const payload = nestedFormToObject(composeForm);

            try {
                const data = await postJsonMessage(composeUrl, payload);
                appendMessage(data.message);
                composeForm.reset();
                closeModal(composeForm);
            } catch (error) {
                showFormError(composeForm, error.message || 'Unable to send interactive message.');
            }
        });
    });

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

    const contactForm = document.querySelector('[data-inbox-contact-form]');
    if (contactForm && contactUrl) {
        contactForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            showFormError(contactForm, '');

            if (!assertWithinWindow()) {
                return;
            }

            const payload = {
                name: contactForm.querySelector('[name="name"]')?.value?.trim() || '',
                first_name: contactForm.querySelector('[name="first_name"]')?.value?.trim() || null,
                last_name: contactForm.querySelector('[name="last_name"]')?.value?.trim() || null,
                phone: contactForm.querySelector('[name="phone"]')?.value?.trim() || '',
                phone_type: contactForm.querySelector('[name="phone_type"]')?.value || 'CELL',
                email: contactForm.querySelector('[name="email"]')?.value?.trim() || null,
                company: contactForm.querySelector('[name="company"]')?.value?.trim() || null,
            };

            if (!payload.name || !payload.phone) {
                showFormError(contactForm, 'Name and phone are required.');

                return;
            }

            try {
                const response = await fetch(contactUrl, {
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
                    showFormError(contactForm, error.message || 'Unable to send contact.');

                    return;
                }

                const data = await response.json();
                appendMessage(data.message);
                contactForm.reset();
                closeModal(contactForm);
            } catch {
                showFormError(contactForm, 'Unable to send contact.');
            }
        });
    }

    const paymentForm = document.querySelector('[data-inbox-payment-form]');
    const paymentUrl = chat.dataset.paymentUrl;

    if (paymentForm && paymentUrl) {
        paymentForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            showFormError(paymentForm, '');

            const amount = paymentForm.querySelector('[name="amount"]')?.value;
            const description = paymentForm.querySelector('[name="description"]')?.value;

            if (!amount || !description) {
                showFormError(paymentForm, 'Amount and description are required.');

                return;
            }

            try {
                const response = await fetch(paymentUrl, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({ amount, description }),
                });

                const data = await response.json().catch(() => ({}));

                if (!response.ok) {
                    throw new Error(data.message || 'Unable to send payment link.');
                }

                appendMessage({
                    ...(data.message || {}),
                    is_outbound: true,
                    body: data.message?.body || `Payment link created: ${data.payment_link || ''}`,
                });
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

function initInboxEchoNotifications() {
    if (inboxRoot()) {
        return;
    }

    const tenantId = document.body?.dataset.inboxTenantId;
    const realtimeEnabled = document.body?.dataset.inboxRealtimeEnabled === '1';

    if (!realtimeEnabled || !tenantId || !window.Echo) {
        return;
    }

    subscribeInboxEcho(`inbox.${tenantId}`, {
        '.message.created': (payload) => {
            if (payload?.thread) {
                showInboxWebNotification(payload.thread, payload.message);
            }
        },
    });
}

function initInboxNotifications() {
    seedInboxUnreadFromDom();
    initInboxEchoNotifications();

    const sync = () => {
        setInboxNotifyUi(inboxCanNotify());
    };

    // If the browser already granted permission, keep notifications enabled unless
    // the user explicitly turned them off.
    if (
        window.isSecureContext &&
        typeof Notification !== 'undefined' &&
        Notification.permission === 'granted' &&
        window.localStorage.getItem(INBOX_NOTIFY_KEY) !== '0'
    ) {
        window.localStorage.setItem(INBOX_NOTIFY_KEY, '1');
    }

    const requestFromGesture = async (event) => {
        if (event.target?.closest?.('[data-inbox-notify-toggle]')) {
            return;
        }

        if (!window.isSecureContext || typeof Notification === 'undefined') {
            return;
        }

        if (Notification.permission !== 'default') {
            sync();
            return;
        }

        if (window.localStorage.getItem(INBOX_NOTIFY_KEY) === '0') {
            return;
        }

        const permission = await Notification.requestPermission();
        if (permission === 'granted') {
            window.localStorage.setItem(INBOX_NOTIFY_KEY, '1');
        }
        sync();
    };

    document.addEventListener('click', requestFromGesture, { once: true, capture: true });

    const toggle = document.querySelector('[data-inbox-notify-toggle]');
    if (!toggle) {
        sync();
        return;
    }

    if (!window.isSecureContext || typeof Notification === 'undefined') {
        toggle.hidden = true;
        return;
    }

    sync();

    toggle.addEventListener('click', async (event) => {
        event.preventDefault();
        event.stopPropagation();

        if (inboxCanNotify()) {
            window.localStorage.setItem(INBOX_NOTIFY_KEY, '0');
            sync();
            return;
        }

        if (Notification.permission === 'denied') {
            showAppAlert(
                'Notifications are blocked in this browser. Allow them for this site in the browser settings, then try again.',
                'Notifications blocked',
            );
            return;
        }

        const permission =
            Notification.permission === 'granted'
                ? 'granted'
                : await Notification.requestPermission();

        if (permission !== 'granted') {
            window.localStorage.setItem(INBOX_NOTIFY_KEY, '0');
            sync();
            showAppAlert(
                'Browser permission was not granted. Click Notifications again and choose Allow.',
                'Notifications off',
            );
            return;
        }

        window.localStorage.setItem(INBOX_NOTIFY_KEY, '1');
        sync();

        const shown = showInboxWebNotification(
            { name: 'WapApp Inbox', uuid: '' },
            { body: 'Notifications are on. You will be alerted for new WhatsApp messages.' },
            { force: true },
        );

        if (!shown) {
            showAppAlert(
                isAppleDesktopBrowser()
                    ? 'Browser permission is granted, but macOS blocked the banner. Open System Settings → Notifications and enable Google Chrome plus “Google Chrome Helper (Alerts)” (or Safari). Turn Focus / Do Not Disturb off, then try again.'
                    : isWindowsDesktopBrowser()
                      ? 'Browser permission is granted, but Windows blocked the banner. Open Settings → System → Notifications and turn on notifications for Google Chrome or Microsoft Edge. Turn Focus Assist / Do Not Disturb off, then try again. Keep at least one WapApp tab open.'
                      : 'Browser permission is granted, but the OS blocked the test alert. Check notification settings for this browser, and keep at least one WapApp tab open.',
                'Notifications on',
            );
        } else if (isAppleDesktopBrowser()) {
            showAppAlert(
                'Notifications are on. On Mac, also enable System Settings → Notifications → Google Chrome and “Google Chrome Helper (Alerts)” (or Safari). Keep at least one WapApp tab open; banners work best when the window is in the background.',
                'Notifications on',
            );
        } else if (isWindowsDesktopBrowser()) {
            showAppAlert(
                'Notifications are on. On Windows, keep Chrome/Edge notifications enabled under Settings → System → Notifications, and turn Focus Assist off. Keep at least one WapApp tab open (banners work in the background like on Linux).',
                'Notifications on',
            );
        } else {
            showAppAlert(
                'Notifications are on. Keep at least one WapApp tab open to receive desktop alerts for new WhatsApp messages.',
                'Notifications on',
            );
        }
    });
}

function initInboxDeleteChat() {
    const button = document.querySelector('[data-inbox-delete-chat]');
    const chat = document.querySelector('[data-inbox-chat]');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    const deleteUrl = button?.dataset.deleteUrl || chat?.dataset.deleteUrl;

    if (!button || !deleteUrl || !csrf) {
        return;
    }

    button.addEventListener('click', async () => {
        const confirmed = await showAppConfirm({
            title: 'Delete this chat?',
            message: 'This conversation and its messages will be removed from inbox. The contact is not deleted.',
            variant: 'danger',
            confirmLabel: 'Delete chat',
        });

        if (!confirmed) {
            return;
        }

        try {
            const response = await fetch(deleteUrl, {
                method: 'DELETE',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrf,
                },
                credentials: 'same-origin',
            });
            const data = await response.json().catch(() => ({}));

            if (!response.ok) {
                showAppAlert(data.message || 'Unable to delete this chat.', 'Delete failed');
                return;
            }

            window.location.href = data.redirect || inboxBaseUrl();
        } catch {
            showAppAlert('Unable to delete this chat.', 'Delete failed');
        }
    });
}

function initInboxTeamFeatures() {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    if (!csrf) return;

    const chat = document.querySelector('[data-inbox-chat]');
    const aiToggle = chat?.querySelector('[data-inbox-ai-toggle]');
    const aiStatus = chat?.querySelector('[data-inbox-ai-status]');
    const aiMode = chat?.querySelector('[data-inbox-ai-mode]');
    const responseTypeUrl = chat?.dataset.responseTypeUrl;

    const setAiStatusLabel = (enabled) => {
        if (aiStatus) {
            aiStatus.textContent = enabled ? 'AI reply' : 'Human reply';
        }
        if (aiMode) {
            aiMode.textContent = enabled ? 'AI' : 'Human';
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
                } else if (aiToggle) {
                    setToggleSwitchActive(aiToggle, next);
                    setAiStatusLabel(next);
                }
            } catch {
                setToggleSwitchActive(aiToggleAll, previous);
            }
        });
    }
}

function initInboxExportModal() {
    const form = document.querySelector('[data-inbox-export-form]');
    const root = document.querySelector('[data-inbox-root]');
    const chat = document.querySelector('[data-inbox-chat]');
    const errorEl = form?.querySelector('[data-inbox-export-error]');

    if (!form || !root) {
        return;
    }

    const showError = (message) => {
        if (!errorEl) {
            return;
        }
        errorEl.textContent = message;
        errorEl.classList.toggle('hidden', !message);
    };

    const formatDate = (date) => {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');

        return `${year}-${month}-${day}`;
    };

    form.querySelectorAll('[data-export-preset]').forEach((button) => {
        button.addEventListener('click', () => {
            const preset = button.dataset.exportPreset;
            const to = new Date();
            const from = new Date();

            if (preset === 'today') {
                from.setTime(to.getTime());
            } else if (preset === '7d') {
                from.setDate(to.getDate() - 6);
            } else if (preset === '30d') {
                from.setDate(to.getDate() - 29);
            } else if (preset === 'month') {
                from.setDate(1);
            }

            const fromInput = form.querySelector('[name="from"]');
            const toInput = form.querySelector('[name="to"]');
            if (fromInput) fromInput.value = formatDate(from);
            if (toInput) toInput.value = formatDate(to);
        });
    });

    form.addEventListener('submit', (event) => {
        event.preventDefault();
        showError('');

        const scope = form.querySelector('[name="export_scope"]:checked')?.value || 'all';
        if (scope === 'current') {
            const exportUrl = chat?.dataset.exportUrl;
            if (!exportUrl) {
                showError('Open a chat first to export only that conversation.');
                return;
            }

            window.location.href = exportUrl;
            return;
        }

        const exportAllUrl = root.dataset.exportAllUrl;
        if (!exportAllUrl) {
            showError('Export is unavailable.');
            return;
        }

        const params = new URLSearchParams(window.location.search);
        const from = form.querySelector('[name="from"]')?.value;
        const to = form.querySelector('[name="to"]')?.value;
        const skip = form.querySelector('[name="skip_phones"]')?.value?.trim();

        if (!from || !to) {
            showError('Choose a from and to date.');
            return;
        }

        params.set('from', from);
        params.set('to', to);
        if (skip) {
            params.set('skip_phones', skip);
        } else {
            params.delete('skip_phones');
        }

        window.location.href = `${exportAllUrl}?${params.toString()}`;
    });
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

        params.delete('cursor');

        return params;
    };

    const renderThreads = (threads) => {
        if (!Array.isArray(threads) || threads.length === 0) {
            list.innerHTML = '<div class="p-6 text-center text-sm text-text-body/70" data-inbox-thread-empty>No conversations found.</div>';
            return;
        }

        list.innerHTML = threads.map((thread) => buildThreadRowHtml(thread, selectedUuid)).join('');
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
            setThreadsCursorState({
                cursor: data.next_cursor || '',
                hasMore: Boolean(data.has_more),
            });
            window.__inboxResetThreadsPagination?.();

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
