import { showToast } from './toast.js';

const BODY_CHAR_LIMIT = Number(document.getElementById('builder-body-form')?.dataset.bodyLimit) || 1024;
const BUTTON_TEXT_LIMIT = Number(document.getElementById('template-buttons-root')?.dataset.buttonTextLimit) || 20;
const BUTTON_URL_LIMIT = 2000;
const MAX_BUTTONS = Number(document.getElementById('template-buttons-root')?.dataset.maxButtons) || 10;
const MAX_URL_BUTTONS = Number(document.getElementById('template-buttons-root')?.dataset.maxUrlButtons) || 2;
const MAX_PHONE_BUTTONS = Number(document.getElementById('template-buttons-root')?.dataset.maxPhoneButtons) || 1;
const CAROUSEL_CARD_BODY_LIMIT = 150;
const CAROUSEL_BUTTON_TEXT_LIMIT = 25;
const DEFAULT_UNSUBSCRIBE_URL = `${window.location.origin}/unsubscribe-list/$(unsub)`;
const COMMON_EMOJIS = ['😀', '😊', '👍', '🎉', '❤️', '🔥', '✅', '🙏', '💬', '📞'];

function isUsableMediaPreviewUrl(url) {
    const value = String(url || '').trim();
    if (!value) {
        return false;
    }
    if (typeof window !== 'undefined' && value === window.location.href) {
        return false;
    }
    return (
        value.startsWith('blob:') ||
        value.startsWith('data:') ||
        value.startsWith('http://') ||
        value.startsWith('https://') ||
        value.startsWith('/')
    );
}

function readElementMediaSrc(el) {
    if (!el || el.classList.contains('hidden')) {
        return '';
    }
    const attr = (el.getAttribute('src') || '').trim();
    return isUsableMediaPreviewUrl(attr) ? attr : '';
}

function escapeHtml(value) {
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function countBodyCharacters(text) {
    return text.replace(/\n/g, '\r\n').length;
}

function applyWhatsAppFormatting(text) {
    let html = escapeHtml(text);

    // Monospace first
    html = html.replace(/```([^`]+)```/g, '<code class="wa-mono">$1</code>');

    // Bold: WhatsApp (*) and legacy (^)
    html = html.replace(/\*([^*\n]+)\*/g, '<strong>$1</strong>');
    html = html.replace(/\^([^\^\n]+)\^/g, '<strong>$1</strong>');

    // Italic: _text_ (skip mid-word underscores)
    html = html.replace(/(?<![A-Za-z0-9])_([^_\n]+)_(?![A-Za-z0-9])/g, '<em>$1</em>');

    // Strikethrough
    html = html.replace(/~([^~\n]+)~/g, '<del>$1</del>');

    return html.replace(/\n/g, '<br>');
}

function applyVariableSubstitution(text, samples = []) {
    let output = text;

    output = output.replace(/\{\{([a-zA-Z0-9_]+)\}\}/g, (_, name) => {
        const sample = samples.find((item) => item?.name === name);

        return sample?.value || name;
    });

    output = output.replace(/\{\((\d+)\)\}/g, (_, index) => {
        const sample = samples[Number(index) - 1];

        return sample?.value || `Variable ${index}`;
    });

    output = output.replace(/\$\(([^)]+)\)/g, (_, label) => {
        const name = label.trim();
        const sample = samples.find((item) => item?.name === name);

        return sample?.value || name;
    });

    return output;
}

function wrapSelection(textarea, before, after = before) {
    const start = textarea.selectionStart ?? 0;
    const end = textarea.selectionEnd ?? 0;
    const selected = textarea.value.slice(start, end);
    const replacement = selected ? `${before}${selected}${after}` : `${before}${after}`;
    const cursor = selected ? start + replacement.length : start + before.length;

    textarea.setRangeText(replacement, start, end, 'end');
    textarea.setSelectionRange(cursor, cursor);
    textarea.focus();
    textarea.dispatchEvent(new Event('input', { bubbles: true }));
}

function readSamples(container) {
    if (!container) {
        return [];
    }

    return Array.from(container.querySelectorAll('[data-sample-row]')).map((row) => ({
        name: row.dataset.variableName || '',
        value: row.querySelector('[data-sample-input]')?.value?.trim() || '',
    }));
}

function readHeaderMediaFromUpload() {
    const zone = document.querySelector('[data-header-upload]');
    if (!zone) {
        return { image: null, video: null };
    }

    const previewWrap = zone.querySelector('[data-header-media-preview]');
    if (previewWrap?.classList.contains('hidden')) {
        return { image: null, video: null };
    }

    const readSrc = (element) => {
        if (!element || element.classList.contains('hidden')) {
            return null;
        }

        return element.dataset.previewUrl || element.getAttribute('src') || null;
    };

    return {
        image: readSrc(zone.querySelector('[data-header-media-image]')),
        video: readSrc(zone.querySelector('[data-header-media-video]')),
    };
}

function syncSampleRows(container, bodyText) {
    if (!container) {
        return;
    }

    const matches = [...bodyText.matchAll(/\$\(([a-zA-Z0-9_]+)\)/g)].map((match) => match[1]);
    const uniqueNames = [...new Set(matches)];
    const existing = new Map(
        Array.from(container.querySelectorAll('[data-sample-row]')).map((row) => [
            row.dataset.variableName,
            row,
        ]),
    );

    existing.forEach((row, name) => {
        if (!uniqueNames.includes(name)) {
            row.remove();
        }
    });

    uniqueNames.forEach((name, index) => {
        if (existing.has(name)) {
            const label = existing.get(name).querySelector('[data-sample-label]');
            if (label) {
                label.textContent = `variable ${index + 1}`;
            }

            return;
        }

        const row = document.createElement('div');
        row.className = 'flex w-full flex-col gap-3';
        row.dataset.sampleRow = 'true';
        row.dataset.variableName = name;
        row.innerHTML = `
          <label class="fd-label" data-sample-label>variable ${index + 1}</label>
          <div class="flex w-full items-center gap-3 rounded-xl border border-solid border-border bg-elevated p-3.5">
            <input
              type="text"
              name="samples[${index}]"
              data-sample-input
              value="${escapeHtml(name.replace(/_/g, ' '))}"
              class="fd-input min-w-0 flex-1 bg-transparent text-text-muted focus:outline-none"
            >
            <button type="button" data-sample-remove aria-label="Delete variable" class="shrink-0">
              <img src="/images/templates/trash.svg" alt="" class="size-5" width="20" height="20">
            </button>
          </div>
        `;
        container.appendChild(row);
    });
}

function buttonIcon(type) {
    const normalized = String(type || '')
        .trim()
        .toLowerCase()
        .replace(/-/g, '_');

    if (normalized === 'phone' || normalized === 'phone_number') {
        return '/images/templates/call.svg';
    }

    if (normalized === 'flow') {
        return '/images/templates/flow.svg';
    }

    if (normalized === 'quick_reply') {
        return '/images/templates/quick-reply.svg';
    }

    if (normalized === 'unsubscribe') {
        return '/images/templates/export.svg';
    }

    return '/images/templates/export.svg';
}

class TemplateLivePreview {
    constructor(root) {
        this.root = root;
        this.headerImage = root.querySelector('[data-preview-header-image]');
        this.headerVideo = root.querySelector('[data-preview-header-video]');
        this.headerText = root.querySelector('[data-preview-header-text]');
        this.body = root.querySelector('[data-preview-body]');
        this.footer = root.querySelector('[data-preview-footer]');
        this.buttons = root.querySelector('[data-preview-buttons]');
        this.divider = root.querySelector('[data-preview-divider]');
        this.carousel = root.querySelector('[data-preview-carousel]');
        this.carouselTrack = root.querySelector('[data-preview-carousel-track]');
        this.standard = root.querySelector('[data-preview-standard]');
    }

    render(state) {
        const isCarousel = Boolean(state.isCarousel) || (state.carouselCards || []).length > 0;
        const headerType = isCarousel ? 'none' : state.headerType || 'none';
        const showImage = headerType === 'image' && Boolean(state.headerImage);
        const showVideo = headerType === 'video' && Boolean(state.headerVideo);
        const showHeaderText =
            (headerType === 'text' || headerType === 'location') &&
            (Boolean(state.headerText) || headerType === 'location');

        if (this.standard) {
            this.standard.classList.toggle('hidden', isCarousel);
        }

        if (this.headerImage) {
            this.headerImage.classList.toggle('hidden', !showImage);
            if (state.headerImage && showImage) {
                this.headerImage.src = state.headerImage;
            }
        }

        if (this.headerVideo) {
            this.headerVideo.classList.toggle('hidden', !showVideo);
            if (state.headerVideo && showVideo) {
                this.headerVideo.src = state.headerVideo;
            }
        }

        if (this.headerText) {
            this.headerText.classList.toggle('hidden', !showHeaderText);
            if (headerType === 'location') {
                this.headerText.textContent = state.headerText || '$(locationName)';
            } else {
                this.headerText.textContent = state.headerText || '';
            }
        }

        if (this.body) {
            const formatted = applyWhatsAppFormatting(applyVariableSubstitution(state.body || '', state.samples || []));
            this.body.innerHTML = formatted || '&nbsp;';
        }

        if (this.footer) {
            let footerText = (state.footer || '').trim();
            if (state.isOptOut && !footerText) {
                footerText = 'Not interested? Tap Stop promotions';
            }
            this.footer.classList.toggle('hidden', footerText === '' || isCarousel);
            this.footer.textContent = footerText;
        }

        if (this.divider) {
            const hasButtons = !isCarousel && (state.buttons || []).length > 0;
            this.divider.classList.toggle('hidden', !hasButtons);
        }

        if (this.buttons) {
            const buttons = isCarousel
                ? []
                : (state.buttons || []).filter((button) => button.text?.trim());

            this.buttons.classList.toggle('hidden', isCarousel);
            this.buttons.innerHTML = buttons
                .map(
                    (button) => `
                <div class="flex w-full items-center justify-center gap-2 py-1">
                  <img src="${buttonIcon(button.type)}" alt="" class="size-4 shrink-0" width="16" height="16">
                  <span class="text-base font-medium leading-[1.4] whitespace-nowrap text-link-green">${escapeHtml(button.text)}</span>
                </div>
              `,
                )
                .join('');
        }

        this.renderCarousel(isCarousel ? state.carouselCards || [] : []);
    }

    renderCarousel(cards) {
        if (!this.carousel || !this.carouselTrack) {
            return;
        }

        const list = Array.isArray(cards) ? cards : [];
        this.carousel.classList.toggle('hidden', list.length === 0);

        this.carouselTrack.innerHTML = list
            .map((card) => {
                const header = String(card.header || card.header_type || 'IMAGE').toUpperCase();
                const media = card.media_url || card.header_media || card.url || '';
                const body = applyWhatsAppFormatting(card.body || card.body_text || '') || 'Card body';
                const buttons = (card.buttons || [])
                    .filter((button) => (button.text || button.title || '').trim())
                    .map((button) => {
                        const text = button.text || button.title || 'Button';
                        return `
                          <div class="flex items-center justify-center gap-1.5 py-1">
                            <img src="${buttonIcon(button.type)}" alt="" class="size-3.5 shrink-0" width="14" height="14">
                            <span class="truncate text-[11px] font-medium text-link-green">${escapeHtml(text)}</span>
                          </div>`;
                    })
                    .join('');

                let mediaHtml = `<div class="flex aspect-video w-full items-center justify-center bg-muted-surface text-[11px] text-text-muted">${header === 'VIDEO' ? 'Video' : 'Image'}</div>`;
                if (isUsableMediaPreviewUrl(media) && header === 'VIDEO') {
                    mediaHtml = `<video src="${escapeHtml(media)}" class="aspect-video w-full object-cover bg-muted-surface" muted playsinline preload="metadata"></video>`;
                } else if (isUsableMediaPreviewUrl(media)) {
                    mediaHtml = `<img src="${escapeHtml(media)}" alt="" class="aspect-video w-full object-cover bg-muted-surface">`;
                }

                return `
                  <div class="wa-carousel-card flex w-[200px] shrink-0 flex-col overflow-hidden rounded-lg border border-border bg-white">
                    ${mediaHtml}
                    <div class="flex flex-1 flex-col gap-2 p-2">
                      <p class="wa-preview-body line-clamp-3 text-xs leading-[1.4] text-text-body">${body}</p>
                      ${buttons ? `<div class="mt-auto flex flex-col border-t border-border/60 pt-1">${buttons}</div>` : ''}
                    </div>
                  </div>`;
            })
            .join('');
    }
}

function collectCarouselState(root) {
    const form = document.getElementById('carousel-builder-form');
    const defaults = JSON.parse(root.dataset.previewDefaults || '{}');
    const defaultCards = Array.isArray(defaults.carousel_cards) ? defaults.carousel_cards : [];
    const defaultIsCarousel = Boolean(defaults.is_carousel) || defaultCards.length > 0;

    if (!form && !defaultIsCarousel) {
        return { isCarousel: false, body: null, cards: [] };
    }

    if (!form) {
        return {
            isCarousel: defaultIsCarousel,
            body: defaults.body || '',
            cards: defaultCards,
        };
    }

    const intro =
        form.querySelector('[name="carousel_body"]')?.value ??
        defaults.body ??
        '';

    const cards = Array.from(form.querySelectorAll('[data-carousel-card]')).map((cardEl) => {
        const header =
            cardEl.querySelector('select[name*="[header]"]')?.value ||
            cardEl.querySelector('[name*="[header]"]')?.value ||
            'IMAGE';
        const useUrl = Boolean(cardEl.querySelector('[data-carousel-use-url]')?.checked);
        const mediaPath = cardEl.querySelector('[data-carousel-media-path]')?.value?.trim() || '';
        const mediaUrl = cardEl.querySelector('[data-carousel-media-url]')?.value?.trim() || '';
        const previewUrl =
            (isUsableMediaPreviewUrl(cardEl.dataset.previewUrl) ? cardEl.dataset.previewUrl : '') ||
            readElementMediaSrc(cardEl.querySelector('[data-carousel-media-image]')) ||
            readElementMediaSrc(cardEl.querySelector('[data-carousel-media-video]')) ||
            (useUrl && isUsableMediaPreviewUrl(mediaUrl) ? mediaUrl : '') ||
            '';
        const body = cardEl.querySelector('textarea[name*="[body]"]')?.value || '';
        const buttons = Array.from(cardEl.querySelectorAll('[data-carousel-button]'))
            .map((buttonEl) => ({
                type: buttonEl.querySelector('[data-carousel-button-type]')?.value || 'QUICK_REPLY',
                text: buttonEl.querySelector('input[name*="[text]"]')?.value?.trim() || '',
                url: buttonEl.querySelector('input[name*="[url]"]')?.value?.trim() || '',
            }))
            .filter((button) => button.text !== '');

        return {
            header,
            media_path: mediaPath,
            media_url: useUrl ? mediaUrl : previewUrl || mediaUrl,
            body,
            buttons,
        };
    });

    return {
        isCarousel: true,
        body: intro,
        cards,
    };
}

function collectPreviewState(root) {
    const defaults = JSON.parse(root.dataset.previewDefaults || '{}');
    const state = {
        headerType: defaults.header_type || 'none',
        headerText: defaults.header_text || '',
        headerImage: defaults.header_image || null,
        headerVideo: defaults.header_video || '',
        body: defaults.body || '',
        footer: defaults.footer || '',
        samples: (defaults.body_samples || []).map((value) => ({
            name: '',
            value: String(value),
        })),
        buttons: defaults.buttons || [],
        isOptOut: defaults.is_opt_out || false,
        isCarousel: Boolean(defaults.is_carousel),
        carouselCards: defaults.carousel_cards || [],
    };

    const headerTypeInput = document.querySelector('input[name="header_type"]:checked');
    if (headerTypeInput) {
        state.headerType = headerTypeInput.value;
    }

    const headerTextInput = document.getElementById('header_text');
    if (headerTextInput) {
        state.headerText = headerTextInput.value;
    }

    if (state.headerType === 'none') {
        state.headerImage = null;
        state.headerVideo = '';
        state.headerText = '';
    } else {
        const mediaPreview = readHeaderMediaFromUpload();
        if (mediaPreview.image) {
            state.headerImage = mediaPreview.image;
        }
        if (mediaPreview.video) {
            state.headerVideo = mediaPreview.video;
        }
    }

    const bodyInput = document.getElementById('body_text');
    if (bodyInput) {
        state.body = bodyInput.value;
        state.samples = readSamples(document.getElementById('variable-samples'));
    }

    const footerInput = document.querySelector('textarea[name="footer_text"]');
    if (footerInput) {
        state.footer = footerInput.value;
    }

    const buttonsRoot = document.getElementById('template-buttons-root');
    if (buttonsRoot) {
        state.buttons = collectButtonsState(buttonsRoot);
        state.buttonMode = document.getElementById('button_mode')?.value || 'call_to_action';
        state.isOptOut = document.getElementById('is_opt_out')?.checked || false;
    }

    const carousel = collectCarouselState(root);
    if (carousel.isCarousel) {
        state.isCarousel = true;
        state.carouselCards = carousel.cards;
        if (carousel.body !== null && carousel.body !== undefined) {
            state.body = carousel.body;
        }
        state.headerType = 'none';
        state.headerImage = null;
        state.headerVideo = '';
        state.headerText = '';
        state.footer = '';
        state.buttons = [];
    }

    return state;
}

function collectButtonsState(root) {
    if (!root) {
        return [];
    }

    return Array.from(root.querySelectorAll('[data-button-row]'))
        .map((row) => ({
            text: row.querySelector('[data-button-text]')?.value?.trim() || '',
            type: row.querySelector('[data-button-type]')?.value || 'url',
            url: row.querySelector('[data-button-url]')?.value?.trim() || '',
            flow_id: row.querySelector('[data-button-flow-id]')?.value?.trim() || '',
        }))
        .filter((button) => button.text !== '');
}

function initBodyEditor(root, preview, scheduleUpdate) {
    const textarea = document.getElementById('body_text');
    const samplesContainer = document.getElementById('variable-samples');
    const counter = document.querySelector('[data-body-char-count]');

    if (!textarea) {
        return;
    }

    const updateCounter = () => {
        if (!counter) {
            return;
        }

        const total = countBodyCharacters(textarea.value);
        const limit = Number(document.getElementById('builder-body-form')?.dataset.bodyLimit) || BODY_CHAR_LIMIT;
        counter.textContent = `${Math.min(total, limit)} / ${limit}`;

        if (total > limit) {
            const trimmed = textarea.value.slice(0, limit);
            textarea.value = trimmed.replace(/\r\n/g, '\n');
            counter.textContent = `${limit} / ${limit}`;
        }
    };

    root.querySelectorAll('[data-editor-action]').forEach((button) => {
        button.addEventListener('click', () => {
            const action = button.dataset.editorAction;

            if (action === 'bold') {
                // Legacy template bold marker (^text^) — also rendered as bold in preview
                wrapSelection(textarea, '^');
            } else if (action === 'italic') {
                wrapSelection(textarea, '_');
            } else if (action === 'strike') {
                wrapSelection(textarea, '~');
            } else if (action === 'emoji') {
                openEmojiPicker(button, textarea);
            } else if (action === 'variable') {
                if (typeof window.__openVariableModalWithLoad === 'function') {
                    window.__openVariableModalWithLoad();
                } else {
                    openVariableModal();
                }
            }

            scheduleUpdate();
            updateCounter();
        });
    });

    textarea.addEventListener('input', () => {
        syncSampleRows(samplesContainer, textarea.value);
        updateCounter();
        scheduleUpdate();
    });

    samplesContainer?.addEventListener('input', scheduleUpdate);
    samplesContainer?.addEventListener('click', (event) => {
        const removeButton = event.target.closest('[data-sample-remove]');
        if (!removeButton) {
            return;
        }

        removeButton.closest('[data-sample-row]')?.remove();
        scheduleUpdate();
    });

    syncSampleRows(samplesContainer, textarea.value);
    updateCounter();
}

function openEmojiPicker(anchor, textarea) {
    let picker = document.getElementById('template-emoji-picker');

    if (!picker) {
        picker = document.createElement('div');
        picker.id = 'template-emoji-picker';
        picker.className = 'absolute z-50 grid grid-cols-5 gap-1 rounded-lg border border-border bg-elevated p-2 shadow-lg';
        picker.innerHTML = COMMON_EMOJIS.map(
            (emoji) => `<button type="button" class="rounded p-1 text-lg hover:bg-green-50" data-emoji="${emoji}">${emoji}</button>`,
        ).join('');
        document.body.appendChild(picker);

        picker.addEventListener('click', (event) => {
            const button = event.target.closest('[data-emoji]');
            if (!button) {
                return;
            }

            const start = textarea.selectionStart ?? textarea.value.length;
            textarea.setRangeText(button.dataset.emoji, start, start, 'end');
            textarea.dispatchEvent(new Event('input', { bubbles: true }));
            picker.classList.add('hidden');
        });

        document.addEventListener('click', (event) => {
            if (!picker.classList.contains('hidden') && !picker.contains(event.target) && event.target !== anchor) {
                picker.classList.add('hidden');
            }
        });
    }

    const rect = anchor.getBoundingClientRect();
    picker.style.top = `${rect.bottom + window.scrollY + 6}px`;
    picker.style.left = `${rect.left + window.scrollX}px`;
    picker.classList.remove('hidden');
}

function openVariableModal() {
    const modal = document.getElementById('modal-add-variable');
    if (!modal) {
        return;
    }

    modal.classList.remove('hidden');
    modal.classList.add('flex');
}

function closeVariableModal() {
    const modal = document.getElementById('modal-add-variable');
    if (!modal) {
        return;
    }

    modal.classList.add('hidden');
    modal.classList.remove('flex');
}

function normalizeVariableItem(variable) {
    const name = variable.name || '';
    const label = variable.display_name || variable.label || name;

    return {
        name,
        label,
        displayName: label,
        description: variable.description || '',
        syntax: variable.syntax || `$(${name})`,
        type: variable.type || 'variable',
        category: variable.category || (variable.type === 'custom' ? 'custom' : 'message'),
        dataType: variable.data_type || null,
        searchText: [
            name,
            label,
            variable.syntax,
            variable.description,
            variable.data_type,
        ]
            .filter(Boolean)
            .join(' ')
            .toLowerCase(),
    };
}

const VARIABLE_CATEGORY_LABELS = {
    custom: 'Your variables',
    message: 'Message',
    subscriber: 'Subscriber',
    datetime: 'Date & time',
};

function renderVariableOption(variable) {
    const meta = variable.dataType ? escapeHtml(variable.dataType) : escapeHtml(variable.type || 'variable');

    return `
      <button
        type="button"
        data-variable-insert="${escapeHtml(variable.syntax)}"
        data-variable-label="${escapeHtml(variable.searchText)}"
        class="flex w-full flex-col gap-1 border-b border-solid border-border px-4 py-3 text-left last:border-b-0 hover:bg-green-50"
      >
        <div class="flex items-start justify-between gap-3">
          <span class="min-w-0 text-sm font-semibold leading-[1.4] text-text-body">${escapeHtml(variable.displayName)}</span>
          <code class="shrink-0 rounded bg-muted-surface px-2 py-0.5 text-xs font-medium text-green-600">${escapeHtml(variable.syntax)}</code>
        </div>
        ${
            variable.description
                ? `<span class="text-xs leading-[1.4] text-text-subtle">${escapeHtml(variable.description)}</span>`
                : ''
        }
        <span class="text-[11px] uppercase tracking-wide text-text-subtle/70">${meta}</span>
      </button>`;
}

function renderVariableGroups(customItems, builtinItems, createUrl) {
    const sections = [];

    sections.push(`
      <div data-variable-section="custom" class="flex flex-col">
        <div class="sticky top-0 z-[1] border-b border-solid border-border bg-muted-surface px-4 py-2">
          <p class="text-xs font-semibold uppercase tracking-wide text-text-subtle">Your variables</p>
        </div>
        ${
            customItems.length
                ? customItems.map((variable) => renderVariableOption(variable)).join('')
                : `<div class="flex flex-col items-center gap-2 px-4 py-6 text-center">
                    <p class="text-sm text-text-muted">No custom variables yet.</p>
                    <a href="${escapeHtml(createUrl)}" class="text-sm font-medium text-green-500 hover:underline">Create your first variable</a>
                  </div>`
        }
      </div>
    `);

    const builtinByCategory = builtinItems.reduce((groups, variable) => {
        const key = variable.category || 'message';
        groups[key] = groups[key] || [];
        groups[key].push(variable);

        return groups;
    }, {});

    const categoryOrder = ['message', 'subscriber', 'datetime'];
    const orderedCategories = [
        ...categoryOrder.filter((category) => (builtinByCategory[category] || []).length > 0),
        ...Object.keys(builtinByCategory).filter((category) => !categoryOrder.includes(category)),
    ];

    if (!orderedCategories.length) {
        return sections.join('');
    }

    sections.push(`
      <div data-variable-section="builtin" class="flex flex-col border-t border-solid border-border">
        <div class="sticky top-0 z-[1] border-b border-solid border-border bg-muted-surface px-4 py-2">
          <p class="text-xs font-semibold uppercase tracking-wide text-text-subtle">Built-in variables</p>
        </div>
      </div>
    `);

    orderedCategories.forEach((category) => {
        const items = builtinByCategory[category] || [];
        if (!items.length) {
            return;
        }

        sections.push(`
          <div data-variable-section="builtin-${category}" class="flex flex-col">
            <div class="border-b border-solid border-border bg-elevated px-4 py-2">
              <p class="text-[11px] font-semibold uppercase tracking-wide text-text-subtle/80">${escapeHtml(VARIABLE_CATEGORY_LABELS[category] || category)}</p>
            </div>
            ${items.map((variable) => renderVariableOption(variable)).join('')}
          </div>
        `);
    });

    return sections.join('');
}

function initVariableModal(root) {
    const modal = document.getElementById('modal-add-variable');
    const textarea = document.getElementById('body_text');
    const samplesContainer = document.getElementById('variable-samples');
    const searchInput = document.getElementById('variable-search');
    const optionsHost = document.getElementById('variable-options');
    const variablesUrl = modal?.dataset.variablesUrl || document.getElementById('builder-body-form')?.dataset.variablesUrl;
    const createUrl = modal?.dataset.createVariableUrl || '/templates/variables/create';

    if (!modal || !textarea || !optionsHost) {
        return;
    }

    let customItems = [];
    let builtinItems = [];

    try {
        const initial = JSON.parse(modal.dataset.initialVariables || '{"custom":[],"builtin":[]}');
        customItems = (initial.custom || []).map(normalizeVariableItem);
        builtinItems = (initial.builtin || []).map(normalizeVariableItem);
    } catch {
        customItems = [];
        builtinItems = [];
    }

    const paint = () => {
        optionsHost.innerHTML = renderVariableGroups(customItems, builtinItems, createUrl);
        bindVariableOptionClicks();
        filterVariableOptions(searchInput?.value || '');
    };

    const bindVariableOptionClicks = () => {
        optionsHost.querySelectorAll('[data-variable-insert]').forEach((button) => {
            button.addEventListener('click', () => {
                const syntax = button.dataset.variableInsert || '';
                if (!syntax) {
                    return;
                }

                const start = textarea.selectionStart ?? textarea.value.length;
                textarea.setRangeText(syntax, start, start, 'end');
                textarea.dispatchEvent(new Event('input', { bubbles: true }));
                syncSampleRows(samplesContainer, textarea.value);
                closeVariableModal();
            });
        });
    };

    const filterVariableOptions = (query) => {
        const normalized = query.trim().toLowerCase();

        optionsHost.querySelectorAll('[data-variable-insert]').forEach((option) => {
            const label = option.dataset.variableLabel || '';
            option.classList.toggle('hidden', normalized !== '' && !label.includes(normalized));
        });

        optionsHost.querySelectorAll('[data-variable-section]').forEach((section) => {
            const visibleOptions = section.querySelectorAll('[data-variable-insert]:not(.hidden)');
            const isCustomEmpty = section.dataset.variableSection === 'custom' && !customItems.length;
            section.classList.toggle('hidden', !isCustomEmpty && visibleOptions.length === 0);
        });

        const hasVisible = optionsHost.querySelector('[data-variable-insert]:not(.hidden)');
        let emptyState = optionsHost.querySelector('[data-variable-empty-search]');

        if (!hasVisible && (customItems.length || builtinItems.length)) {
            if (!emptyState) {
                emptyState = document.createElement('p');
                emptyState.dataset.variableEmptySearch = 'true';
                emptyState.className = 'p-4 text-sm text-text-muted';
                emptyState.textContent = 'No variables match your search.';
                optionsHost.appendChild(emptyState);
            }
            emptyState.classList.remove('hidden');
        } else if (emptyState) {
            emptyState.classList.add('hidden');
        }
    };

    const loadVariables = async () => {
        if (!variablesUrl) {
            paint();
            return;
        }

        const loadingEl = optionsHost.querySelector('[data-variable-loading]');
        loadingEl?.classList.remove('hidden');

        try {
            const response = await fetch(variablesUrl, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });

            if (!response.ok) {
                throw new Error(`Request failed (${response.status})`);
            }

            const data = await response.json();
            customItems = (data.custom || []).map(normalizeVariableItem);
            builtinItems = (data.builtin || []).map(normalizeVariableItem);
            paint();
        } catch {
            if (customItems.length || builtinItems.length) {
                paint();
            } else {
                optionsHost.innerHTML = `
                  <div class="flex flex-col items-center gap-2 px-4 py-8 text-center">
                    <p class="text-sm text-red-600">Failed to load variables.</p>
                    <button type="button" data-variable-retry class="text-sm font-medium text-green-500 hover:underline">Try again</button>
                  </div>`;
                optionsHost.querySelector('[data-variable-retry]')?.addEventListener('click', () => {
                    loadVariables();
                });
            }
        }
    };

    const openWithLoad = async () => {
        if (searchInput) {
            searchInput.value = '';
        }

        if (!customItems.length && !builtinItems.length) {
            optionsHost.innerHTML = '<p class="p-4 text-sm text-text-muted" data-variable-loading>Loading variables...</p>';
        } else {
            paint();
        }

        await loadVariables();
        openVariableModal();
    };

    modal.querySelectorAll('[data-modal-close], [data-variable-close]').forEach((button) => {
        button.addEventListener('click', (event) => {
            event.preventDefault();
            closeVariableModal();
        });
    });

    window.__openVariableModalWithLoad = openWithLoad;

    searchInput?.addEventListener('input', () => {
        filterVariableOptions(searchInput.value);
    });

    if (!modal.classList.contains('hidden')) {
        openWithLoad();
    } else if (customItems.length || builtinItems.length) {
        paint();
    }
}

function initHeaderSections(scheduleUpdate) {
    const sections = document.querySelectorAll('[data-header-section]');
    const radios = document.querySelectorAll('input[name="header_type"]');
    const form = document.getElementById('builder-header-form');

    if (!sections.length && !radios.length) {
        return;
    }

    const syncActiveInputs = (selected) => {
        sections.forEach((section) => {
            const sectionType = section.dataset.headerSection;
            const isActive = sectionType === selected
                || (sectionType === 'text' && selected === 'text')
                || (sectionType === 'location' && selected === 'location');

            const useUrlCheckbox = section.querySelector('[data-header-use-url]');
            const useUrl = Boolean(useUrlCheckbox?.checked) && isActive;
            const fileInput = section.querySelector('[data-header-media-input]');
            const urlInput = section.querySelector('[data-header-url-input]');
            const fileWrap = section.querySelector('[data-header-file-wrap]');
            const urlWrap = section.querySelector('[data-header-url-wrap]');
            const docName = section.querySelector('input[name="doc_name"]');

            if (useUrlCheckbox) {
                useUrlCheckbox.disabled = !isActive;
                if (!isActive) {
                    useUrlCheckbox.checked = false;
                }
            }

            if (fileWrap) {
                fileWrap.classList.toggle('hidden', useUrl);
            }
            if (urlWrap) {
                urlWrap.classList.toggle('hidden', !useUrl);
            }

            if (fileInput) {
                const enableFile = isActive && !useUrl;
                fileInput.disabled = !enableFile;
                fileInput.name = enableFile ? 'header_media' : '';
            }

            if (urlInput) {
                const enableUrl = isActive && useUrl;
                urlInput.disabled = !enableUrl;
                urlInput.name = enableUrl ? 'media_url' : '';
            }

            if (docName) {
                docName.disabled = !isActive;
            }
        });
    };

    const syncSections = () => {
        const selected = document.querySelector('input[name="header_type"]:checked')?.value || 'none';

        sections.forEach((section) => {
            const sectionType = section.dataset.headerSection;
            let visible = false;

            if (sectionType === 'text') {
                visible = selected === 'text';
            } else if (sectionType === 'location') {
                visible = selected === 'location';
            } else {
                visible = sectionType === selected;
            }

            section.classList.toggle('hidden', !visible);
        });

        radios.forEach((radio) => {
            const label = radio.closest('label');
            const icon = label?.querySelector('.header-type-icon');
            const text = label?.querySelector('.header-type-label');
            const checked = radio.checked;

            if (icon) {
                icon.src = checked ? icon.dataset.checkedSrc : icon.dataset.uncheckedSrc;
            }

            if (text) {
                text.classList.toggle('text-green-500', checked);
                text.classList.toggle('text-blue-200', !checked);
            }
        });

        syncActiveInputs(selected);
        scheduleUpdate();
    };

    radios.forEach((radio) => {
        radio.addEventListener('change', syncSections);
    });

    form?.querySelectorAll('[data-header-use-url]').forEach((checkbox) => {
        checkbox.addEventListener('change', syncSections);
    });

    ['header_text', 'template_name'].forEach((id) => {
        document.getElementById(id)?.addEventListener('input', scheduleUpdate);
    });

    syncSections();
}

function showHeaderUploadError(zone, message) {
    const section = zone.closest('[data-header-section]');
    const errorEl = section?.querySelector('[data-header-upload-error]');
    if (errorEl) {
        errorEl.textContent = message || '';
        errorEl.classList.toggle('hidden', !message);
    }

    if (message && typeof window.showAppToast === 'function') {
        window.showAppToast({ type: 'error', message, title: 'Upload failed' });
    }
}

function initHeaderMedia(scheduleUpdate) {
    const form = document.getElementById('builder-header-form');
    const uploadUrl = form?.dataset.headerUploadUrl || '';
    const mediaPathInput = form?.querySelector('input[name="media_path"]');

    document.querySelectorAll('[data-header-upload]').forEach((zone) => {
        const input = zone.querySelector('[data-header-media-input]');
        const previewWrap = zone.querySelector('[data-header-media-preview]');
        const imageEl = zone.querySelector('[data-header-media-image]');
        const videoEl = zone.querySelector('[data-header-media-video]');
        const nameEl = zone.querySelector('[data-header-media-name]');
        const maxBytes = Number(zone.dataset.maxBytes || 0);

        if (!input) {
            return;
        }

        input.addEventListener('change', async () => {
            const file = input.files?.[0];
            if (!file) {
                return;
            }

            showHeaderUploadError(zone, '');

            if (maxBytes > 0 && file.size > maxBytes) {
                const mb = Math.round(maxBytes / (1024 * 1024));
                showHeaderUploadError(zone, `File is too large. Max allowed is ${mb} MB.`);
                input.value = '';
                return;
            }

            const objectUrl = URL.createObjectURL(file);
            const isVideo = file.type.startsWith('video/');
            const isImage = file.type.startsWith('image/');

            previewWrap?.classList.remove('hidden');
            if (nameEl) {
                nameEl.textContent = file.name;
            }

            if (isVideo) {
                imageEl?.classList.add('hidden');
                if (videoEl) {
                    videoEl.classList.remove('hidden');
                    videoEl.src = objectUrl;
                    videoEl.dataset.previewUrl = objectUrl;
                }
            } else if (isImage) {
                videoEl?.classList.add('hidden');
                if (videoEl) {
                    videoEl.removeAttribute('src');
                }
                if (imageEl) {
                    imageEl.classList.remove('hidden');
                    imageEl.src = objectUrl;
                    imageEl.dataset.previewUrl = objectUrl;
                }
            } else {
                videoEl?.classList.add('hidden');
                imageEl?.classList.add('hidden');
            }

            scheduleUpdate();

            if (!uploadUrl) {
                return;
            }

            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
            const xsrfCookie = document.cookie
                .split('; ')
                .find((row) => row.startsWith('XSRF-TOKEN='))
                ?.split('=')
                .slice(1)
                .join('=');
            const body = new FormData();
            body.append('header_media', file);
            body.append('_token', csrfToken);

            try {
                const response = await fetch(uploadUrl, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
                        ...(xsrfCookie ? { 'X-XSRF-TOKEN': decodeURIComponent(xsrfCookie) } : {}),
                    },
                    credentials: 'same-origin',
                    body,
                });

                const data = await response.json().catch(() => ({}));

                if (!response.ok) {
                    const firstError = data?.errors
                        ? Object.values(data.errors).flat()[0]
                        : null;
                    let message = firstError || data.message || '';

                    if (!message) {
                        if (response.status === 419) {
                            message = 'Session expired. Please refresh the page and try again.';
                        } else if (response.status === 403) {
                            message = 'Upload forbidden. Check template permissions or refresh and retry.';
                        } else if (response.status === 413) {
                            message = 'File is too large for the server.';
                        } else {
                            message = `Upload failed (${response.status}). Please try again.`;
                        }
                    }

                    showHeaderUploadError(zone, message);
                    return;
                }

                const remoteUrl = data.url || '';
                if (mediaPathInput && data.path) {
                    mediaPathInput.value = data.path;
                }

                // Prefer server URL for preview; if it 403/fails, keep local blob preview.
                if (remoteUrl) {
                    const applyRemote = (ok) => {
                        if (!ok) {
                            return;
                        }
                        if (data.type === 'video' && videoEl) {
                            videoEl.src = remoteUrl;
                            videoEl.dataset.previewUrl = remoteUrl;
                            videoEl.classList.remove('hidden');
                            imageEl?.classList.add('hidden');
                        } else if (imageEl && (data.type === 'image' || isImage)) {
                            imageEl.src = remoteUrl;
                            imageEl.dataset.previewUrl = remoteUrl;
                            imageEl.classList.remove('hidden');
                            videoEl?.classList.add('hidden');
                        }
                        scheduleUpdate();
                    };

                    if (data.type === 'video' || isVideo) {
                        const probe = document.createElement('video');
                        probe.preload = 'metadata';
                        probe.onloadeddata = () => applyRemote(true);
                        probe.onerror = () => applyRemote(false);
                        probe.src = remoteUrl;
                    } else {
                        const probe = new Image();
                        probe.onload = () => applyRemote(true);
                        probe.onerror = () => applyRemote(false);
                        probe.src = remoteUrl;
                    }
                }

                if (typeof window.showAppToast === 'function') {
                    window.showAppToast({
                        type: 'success',
                        message: data.message || 'Media uploaded.',
                        title: 'Uploaded',
                    });
                }

                scheduleUpdate();
            } catch {
                showHeaderUploadError(zone, 'Network error while uploading. Please try again.');
            }
        });
    });
}

const BUTTON_TYPES = {
    url: { label: 'Visit Website', fieldLabel: 'Website URL', placeholder: 'https://example.com', hasUrl: true },
    phone: { label: 'Call Phone Number', fieldLabel: 'Mobile Number', placeholder: '+91 9876543210', hasUrl: true },
    unsubscribe: { label: 'Unsubscribe', fieldLabel: 'Website URL', placeholder: '/unsubscribe-list/$(unsub)', hasUrl: true },
    quick_reply: { label: 'Quick Reply', fieldLabel: 'Quick Reply Text', placeholder: 'Order Now', hasUrl: false },
    flow: { label: 'WhatsApp Flow', fieldLabel: 'Flow', placeholder: 'Select a flow', hasUrl: false, isFlow: true },
    copy_code: { label: 'Copy Code', fieldLabel: 'Coupon code', placeholder: 'SAVE20', hasUrl: true },
};

function renderButtonRow(rowData = {}, index = 0, mode = 'call_to_action', whatsappFlows = [], buttonTextLimit = BUTTON_TEXT_LIMIT) {
    const type = rowData.type || 'url';
    const config = BUTTON_TYPES[type] || BUTTON_TYPES.url;
    const isQuickReply = type === 'quick_reply';
    const isFlow = type === 'flow';
    const isUnsubscribe = type === 'unsubscribe';
    const urlValue = rowData.url || (isUnsubscribe ? DEFAULT_UNSUBSCRIBE_URL : '');
    const flowId = String(rowData.flow_id || rowData.url || '');
    const textLength = (rowData.text || '').length;
    const urlLength = (urlValue || '').length;

    let extraField = '';

    if (isFlow) {
        const flowOptions = whatsappFlows
            .map(
                (flow) =>
                    `<option value="${escapeHtml(flow.id)}" ${flow.id === flowId || flow.meta_flow_id === flowId ? 'selected' : ''}>${escapeHtml(flow.name)}${flow.is_ready ? '' : ' (draft)'}</option>`,
            )
            .join('');

        extraField = `
            <div class="flex min-w-0 flex-1 flex-col gap-1">
              <label class="text-sm font-semibold leading-[1.4] text-text-primary">Flow</label>
              <div class="flex w-full items-center gap-3 rounded-xl border border-solid border-border bg-elevated p-3.5">
                <select
                  name="buttons[${index}][flow_id]"
                  data-button-flow-id
                  class="fd-input min-w-0 flex-1 bg-transparent text-text-muted focus:outline-none"
                >
                  <option value="">Select a flow</option>
                  ${flowOptions}
                </select>
              </div>
            </div>`;
    } else if (!isQuickReply) {
        extraField = `
            <div class="flex min-w-0 flex-1 flex-col gap-1">
              <label class="text-sm font-semibold leading-[1.4] text-text-primary">${config.fieldLabel}</label>
              <div class="flex w-full items-center gap-3 rounded-xl border border-solid border-border bg-elevated p-3.5">
                <input
                  type="text"
                  name="buttons[${index}][url]"
                  data-button-url
                  value="${escapeHtml(urlValue)}"
                  maxlength="${BUTTON_URL_LIMIT}"
                  placeholder="${config.placeholder}"
                  class="fd-input min-w-0 flex-1 bg-transparent text-text-muted focus:outline-none"
                  ${isUnsubscribe ? 'readonly' : ''}
                >
              </div>
              <span class="text-xs text-text-subtle" data-button-url-count>${urlLength}/${BUTTON_URL_LIMIT}</span>
            </div>`;
    }

    return `
      <div class="flex w-full flex-col gap-2 rounded-lg bg-elevated p-2" data-button-row>
        <div class="flex w-full items-center gap-2">
          <span class="rounded border border-solid border-border px-2 py-1 text-xs font-medium text-text-subtle" data-button-action-label>${config.label}</span>
        </div>
        <div class="flex w-full items-center gap-2">
          <input type="hidden" name="buttons[${index}][type]" data-button-type value="${type}">
          <div class="flex min-w-0 flex-1 items-start gap-2">
            <div class="flex min-w-0 flex-1 flex-col gap-1">
              <label class="text-sm font-semibold leading-[1.4] text-text-primary">Button Text</label>
              <div class="flex w-full items-center gap-3 rounded-xl border border-solid border-border bg-elevated p-3.5">
                <input
                  type="text"
                  name="buttons[${index}][text]"
                  data-button-text
                  value="${escapeHtml(rowData.text || '')}"
                  maxlength="${buttonTextLimit}"
                  placeholder="Enter Text"
                  class="fd-input min-w-0 flex-1 bg-transparent text-text-muted focus:outline-none"
                >
              </div>
              <span class="text-xs text-text-subtle" data-button-text-count>${textLength}/${buttonTextLimit}</span>
            </div>
            ${extraField}
          </div>
          <div class="flex w-12 shrink-0 items-end justify-center self-stretch pb-3.5">
            <button type="button" data-button-remove aria-label="Delete button" class="shrink-0">
              <img src="/images/templates/trash.svg" alt="" class="size-5" width="20" height="20">
            </button>
          </div>
        </div>
      </div>
    `;
}

function bindButtonRowCharCounts(rowsHost, buttonTextLimit = BUTTON_TEXT_LIMIT) {
    rowsHost.querySelectorAll('[data-button-row]').forEach((row) => {
        const textInput = row.querySelector('[data-button-text]');
        const urlInput = row.querySelector('[data-button-url]');
        const textCount = row.querySelector('[data-button-text-count]');
        const urlCount = row.querySelector('[data-button-url-count]');

        const syncCounts = () => {
            if (textInput && textCount) {
                textCount.textContent = `${textInput.value.length}/${buttonTextLimit}`;
            }

            if (urlInput && urlCount) {
                urlCount.textContent = `${urlInput.value.length}/${BUTTON_URL_LIMIT}`;
            }
        };

        textInput?.addEventListener('input', syncCounts);
        urlInput?.addEventListener('input', syncCounts);
        syncCounts();
    });
}

function initButtonsEditor(root, preview, scheduleUpdate) {
    const container = document.getElementById('template-buttons-root');
    const rowsHost = document.getElementById('template-button-rows');
    const modeSelect = document.getElementById('button_mode');
    const ctaActions = document.querySelector('[data-cta-actions]');
    const flowSection = document.querySelector('[data-flow-section]');
    const addQuickReply = document.getElementById('add-template-button');
    const addWebsite = document.getElementById('add-cta-website');
    const addPhone = document.getElementById('add-cta-phone');
    const addUnsubscribe = document.getElementById('add-cta-unsubscribe');
    const errorBox = document.getElementById('button-validation-errors');
    const limitSummary = document.querySelector('[data-button-limit-summary]');

    if (!container || !rowsHost) {
        return;
    }

    const buttonTextLimit = Number(container.dataset.buttonTextLimit) || BUTTON_TEXT_LIMIT;
    const buttonLimitMax = Number(container.dataset.maxButtons) || MAX_BUTTONS;
    const urlButtonLimitMax = Number(container.dataset.maxUrlButtons) || MAX_URL_BUTTONS;
    const phoneButtonLimitMax = Number(container.dataset.maxPhoneButtons) || MAX_PHONE_BUTTONS;

    const whatsappFlows = JSON.parse(container.dataset.whatsappFlows || '[]');
    const savedButtons = JSON.parse(container.dataset.savedButtons || '[]');
    let rows = savedButtons.length ? savedButtons : [];

    const currentMode = () => modeSelect?.value || '';
    const maxButtons = () => buttonLimitMax;

    const isCtaMode = () => currentMode() === 'call_to_action';
    const isQuickReplyMode = () => currentMode() === 'quick_reply';
    const isFlowMode = () => currentMode() === 'whatsapp_flows';
    const isMixedMode = () => currentMode() === 'mixed';
    const isLtoMode = () => currentMode() === 'lto';
    const isNoneMode = () => currentMode() === 'none' || currentMode() === '';

    const ctaTypes = ['url', 'phone', 'unsubscribe'];

    function countByType(type) {
        return rows.filter((row) => row.type === type).length;
    }

    function validateRows() {
        const errors = [];

        if (rows.length > buttonLimitMax) {
            errors.push(`A maximum of ${buttonLimitMax} buttons are allowed.`);
        }

        if (isCtaMode() || isMixedMode()) {
            if (countByType('url') + countByType('unsubscribe') > urlButtonLimitMax) {
                errors.push(`A maximum of ${urlButtonLimitMax} URL buttons are allowed.`);
            }
            if (countByType('phone') > phoneButtonLimitMax) {
                errors.push(`Only ${phoneButtonLimitMax} phone number button is allowed.`);
            }
        }

        if (isQuickReplyMode() || isMixedMode()) {
            const seen = {};
            rows.forEach((row) => {
                if (row.type === 'quick_reply' && row.text) {
                    const key = row.text.toLowerCase();
                    if (seen[key]) {
                        errors.push(`Quick reply button texts must be unique. Duplicate: "${row.text}".`);
                    }
                    seen[key] = true;
                }
            });
        }

        if (isMixedMode()) {
            const filledRows = rows.filter((row) => row.text?.trim());
            if (filledRows.length > 0) {
                const hasCta = filledRows.some((row) => ctaTypes.includes(row.type));
                const hasQuickReply = filledRows.some((row) => row.type === 'quick_reply');
                if (!hasCta || !hasQuickReply) {
                    errors.push('Mixed mode requires at least one call-to-action button and one quick reply button.');
                }
            }
        }

        if (errorBox) {
            errorBox.textContent = errors.join(' ');
            errorBox.classList.toggle('hidden', errors.length === 0);
        }

        return errors.length === 0;
    }

    const setVisible = (element, visible, displayClass = 'flex') => {
        if (!element) {
            return;
        }

        element.classList.toggle('hidden', !visible);
        element.classList.toggle(displayClass, visible);
    };

    const syncModeUi = () => {
        const mode = currentMode();
        const hasMode = mode !== '' && mode !== 'none';
        const showCtaActions = hasMode && (isCtaMode() || isMixedMode());
        const showQuickReplyAdd = hasMode && (isQuickReplyMode() || isMixedMode());
        const showFlow = hasMode && isFlowMode();

        setVisible(ctaActions, showCtaActions, 'flex');
        setVisible(addQuickReply, showQuickReplyAdd, 'inline-flex');
        setVisible(flowSection, showFlow, 'flex');
        rowsHost.classList.toggle('hidden', !hasMode);
    };

    const updateLimitSummary = () => {
        if (!limitSummary) {
            return;
        }

        const count = isFlowMode() || isLtoMode() ? (rows.length ? 1 : 0) : rows.length;
        limitSummary.textContent = `${count} / ${buttonLimitMax} buttons (max ${urlButtonLimitMax} URL, ${phoneButtonLimitMax} phone)`;
    };

    const paint = () => {
        const mode = currentMode();

        if (mode === '' || mode === 'none') {
            rowsHost.innerHTML = '';
        } else if (isFlowMode()) {
            const flowRow = rows.find((row) => row.type === 'flow') || { text: '', type: 'flow', url: '', flow_id: '' };
            rowsHost.innerHTML = renderButtonRow(flowRow, 0, 'whatsapp_flows', whatsappFlows, buttonTextLimit);
        } else if (mode === 'lto') {
            const ltoRow = rows.find((row) => row.type === 'copy_code') || { text: 'Copy offer code', type: 'copy_code', url: '' };
            rowsHost.innerHTML = renderButtonRow(ltoRow, 0, 'lto', whatsappFlows, buttonTextLimit);
        } else {
            rowsHost.innerHTML = rows.map((row, index) => renderButtonRow(row, index, mode, whatsappFlows, buttonTextLimit)).join('');
        }

        bindButtonRowCharCounts(rowsHost, buttonTextLimit);
        syncModeUi();
        updateLimitSummary();
        validateRows();
        scheduleUpdate();
    };

    const syncRowsFromDom = () => {
        if (isFlowMode()) {
            const textEl = rowsHost.querySelector('[data-button-text]');
            const flowEl = rowsHost.querySelector('[data-button-flow-id]');
            rows = [{
                text: textEl?.value || '',
                type: 'flow',
                url: '',
                flow_id: flowEl?.value || '',
            }];
            return;
        }

        rows = collectButtonsState(rowsHost).map((button) => ({
            text: button.text,
            type: button.type,
            url: button.url,
            flow_id: button.flow_id,
        }));
    };

    const canAddRow = (type) => {
        if (isFlowMode()) {
            return type === 'flow' && !rows.some((row) => row.type === 'flow');
        }

        if (rows.length >= maxButtons()) {
            return false;
        }

        if (isQuickReplyMode()) {
            return type === 'quick_reply';
        }

        if (type === 'unsubscribe' && rows.some((row) => row.type === 'unsubscribe')) {
            return false;
        }

        if (type === 'phone' && countByType('phone') >= phoneButtonLimitMax) {
            return false;
        }

        const urlCount = countByType('url') + countByType('unsubscribe');
        if ((type === 'url' || type === 'unsubscribe') && urlCount >= urlButtonLimitMax) {
            return false;
        }

        if (isCtaMode()) {
            return ctaTypes.includes(type);
        }

        if (isMixedMode()) {
            return ctaTypes.includes(type) || type === 'quick_reply';
        }

        return false;
    };

    const addRow = (type) => {
        if (!canAddRow(type)) {
            return;
        }

        const row = { text: '', type, url: '' };

        if (type === 'unsubscribe') {
            row.url = DEFAULT_UNSUBSCRIBE_URL;
        }
        if (type === 'flow') {
            rows = [row];
        } else {
            rows.push(row);
        }
        paint();
    };

    addWebsite?.addEventListener('click', () => addRow('url'));
    addPhone?.addEventListener('click', () => addRow('phone'));
    addUnsubscribe?.addEventListener('click', () => addRow('unsubscribe'));
    addQuickReply?.addEventListener('click', () => addRow('quick_reply'));

    rowsHost.addEventListener('change', (event) => {
        if (event.target.matches('[data-button-flow-id]')) {
            const index = [...rowsHost.querySelectorAll('[data-button-row]')].indexOf(
                event.target.closest('[data-button-row]'),
            );
            if (index >= 0) {
                rows[index] = {
                    ...rows[index],
                    flow_id: event.target.value || '',
                };
            }
            validateRows();
            scheduleUpdate();
        }
    });

    rowsHost.addEventListener('input', (event) => {
        if (event.target.matches('[data-button-text], [data-button-url], [data-button-flow-id]')) {
            const index = [...rowsHost.querySelectorAll('[data-button-row]')].indexOf(
                event.target.closest('[data-button-row]'),
            );
            if (index >= 0) {
                const textEl = rowsHost.querySelectorAll('[data-button-text]')[index];
                const urlEl = rowsHost.querySelectorAll('[data-button-url]')[index];
                const flowEl = rowsHost.querySelectorAll('[data-button-flow-id]')[index];
                rows[index] = {
                    ...rows[index],
                    text: textEl?.value || '',
                    url: urlEl?.value || flowEl?.value || '',
                    flow_id: flowEl?.value || '',
                };
            }
            validateRows();
            scheduleUpdate();
        }
    });

    rowsHost.addEventListener('click', (event) => {
        const removeButton = event.target.closest('[data-button-remove]');
        if (!removeButton) {
            return;
        }

        if (isFlowMode()) {
            rows = [];
        } else {
            const index = [...rowsHost.querySelectorAll('[data-button-row]')].indexOf(
                removeButton.closest('[data-button-row]'),
            );
            if (index >= 0) {
                rows.splice(index, 1);
            }
        }
        paint();
    });

    modeSelect?.addEventListener('change', () => {
        const newMode = currentMode();

        if (newMode === '' || newMode === 'none') {
            rows = [];
        } else if (newMode === 'lto') {
            rows = [{ text: 'Copy offer code', type: 'copy_code', url: '' }];
        } else if (newMode === 'whatsapp_flows') {
            const existingFlow = rows.find((row) => row.type === 'flow');
            rows = existingFlow ? [existingFlow] : [{ text: '', type: 'flow', url: '', flow_id: '' }];
        } else if (newMode === 'quick_reply') {
            rows = rows
                .filter((row) => row.type === 'quick_reply' || row.text)
                .slice(0, MAX_BUTTONS)
                .map((row) => ({
                    text: row.text,
                    type: 'quick_reply',
                    url: '',
                }));
        } else if (newMode === 'call_to_action') {
            rows = rows
                .filter((row) => ctaTypes.includes(row.type))
                .slice(0, MAX_BUTTONS)
                .map((row) => ({
                    text: row.text,
                    type: row.type,
                    url: row.url || (row.type === 'unsubscribe' ? DEFAULT_UNSUBSCRIBE_URL : ''),
                }));
        } else if (newMode === 'mixed') {
            rows = rows
                .filter((row) => ctaTypes.includes(row.type) || row.type === 'quick_reply')
                .slice(0, MAX_BUTTONS)
                .map((row) => ({
                    text: row.text,
                    type: row.type,
                    url: row.url || (row.type === 'unsubscribe' ? DEFAULT_UNSUBSCRIBE_URL : ''),
                }));
        }

        paint();
    });

    document.getElementById('template-buttons-form')?.addEventListener('submit', syncRowsFromDom);

    paint();
}

function initUtilityPresets() {
    const presetsRoot = document.querySelector('[data-utility-presets]');
    const categorySelect = document.getElementById('template_category');
    const nameInput = document.getElementById('template_name');
    const bodyTextarea = document.getElementById('body_text');

    if (!presetsRoot || !categorySelect) {
        return;
    }

    const toggle = () => {
        const show = categorySelect.value === 'UTILITY';
        presetsRoot.classList.toggle('hidden', !show);
        presetsRoot.classList.toggle('flex', show);
    };

    categorySelect.addEventListener('change', toggle);
    toggle();

    presetsRoot.querySelectorAll('[data-utility-preset-use]').forEach((button) => {
        button.addEventListener('click', () => {
            if (nameInput && button.dataset.presetName) {
                nameInput.value = button.dataset.presetName;
                nameInput.dispatchEvent(new Event('input', { bubbles: true }));
            }

            if (bodyTextarea && button.dataset.presetBody) {
                bodyTextarea.value = button.dataset.presetBody;
                bodyTextarea.dispatchEvent(new Event('input', { bubbles: true }));
            }
        });
    });
}

function initAiSuggestions(root) {
    const form = document.getElementById('builder-body-form');
    const suggestUrl = form?.dataset.aiSuggestUrl;
    const modal = document.querySelector('[data-ai-suggestions-modal]');
    const textarea = document.getElementById('body_text');

    if (!suggestUrl || !modal || !textarea) {
        return;
    }

    const results = document.getElementById('ai-suggestions-results');
    const errorEl = document.getElementById('ai-suggestions-error');
    const promptInput = document.getElementById('ai-suggestions-prompt');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

    const openModal = () => {
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        errorEl?.classList.add('hidden');
        if (results) {
            results.innerHTML = '';
        }
    };

    const closeModal = () => {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    };

    root.querySelector('[data-editor-action="ai-suggest"]')?.addEventListener('click', openModal);
    modal.querySelector('[data-ai-suggestions-close]')?.addEventListener('click', closeModal);

    modal.querySelector('[data-ai-suggestions-generate]')?.addEventListener('click', async () => {
        const prompt = promptInput?.value?.trim() || '';
        if (!prompt) {
            return;
        }

        errorEl?.classList.add('hidden');
        if (results) {
            results.innerHTML = '<p class="text-sm text-text-subtle">Generating...</p>';
        }

        try {
            const response = await fetch(suggestUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrf,
                },
                body: JSON.stringify({ prompt, context: textarea.value }),
            });

            const data = await response.json();
            if (!response.ok) {
                throw new Error(data.message || 'Failed to generate suggestions.');
            }

            if (!results) {
                return;
            }

            results.innerHTML = (data.suggestions || [])
                .map(
                    (text, index) => `
                <div class="rounded-lg border border-divider p-3">
                  <pre class="mb-2 whitespace-pre-wrap text-sm text-text-body">${escapeHtml(text)}</pre>
                  <button type="button" class="fd-btn-sm rounded border border-green-500 px-3 py-1 text-sm text-green-500" data-ai-use="${index}">Use this</button>
                </div>`,
                )
                .join('');

            results.querySelectorAll('[data-ai-use]').forEach((button, index) => {
                button.addEventListener('click', () => {
                    textarea.value = data.suggestions[index] || '';
                    textarea.dispatchEvent(new Event('input', { bubbles: true }));
                    closeModal();
                });
            });
        } catch (error) {
            if (errorEl) {
                errorEl.textContent = error.message || 'Something went wrong.';
                errorEl.classList.remove('hidden');
            }
            if (results) {
                results.innerHTML = '';
            }
        }
    });
}

function initCarouselBuilder(scheduleUpdate) {
    const root = document.getElementById('carousel-cards-root');
    const form = document.getElementById('carousel-builder-form');
    if (!root || !form) {
        return;
    }

    const minCards = Number(form.dataset.carouselMin || 2);
    const maxCards = Number(form.dataset.carouselMax || 10);
    const uploadUrl = form.dataset.carouselUploadUrl || '';
    let cards = JSON.parse(root.dataset.savedCards || '[]');
    if (!Array.isArray(cards) || cards.length === 0) {
        cards = [
            { header: 'IMAGE', body: '', media_url: '', media_path: '', media_preview_url: '', use_url: false, buttons: [{ text: '', type: 'QUICK_REPLY', url: '' }, { text: '', type: 'QUICK_REPLY', url: '' }] },
            { header: 'IMAGE', body: '', media_url: '', media_path: '', media_preview_url: '', use_url: false, buttons: [{ text: '', type: 'QUICK_REPLY', url: '' }, { text: '', type: 'QUICK_REPLY', url: '' }] },
        ];
    }

    // Keep guidelines modal on document.body so it is never clipped.
    const guidelinesModal = document.getElementById('modal-carousel-guidelines');
    if (guidelinesModal && guidelinesModal.parentElement !== document.body) {
        document.body.appendChild(guidelinesModal);
    }

    const notifyPreview = () => {
        scheduleUpdate?.();
    };

    const cardElements = () => [...root.querySelectorAll('[data-carousel-card]')];

    const syncCardFromDom = (cardEl, index) => {
        if (!cardEl || index < 0) {
            return;
        }

        const useUrl = Boolean(cardEl.querySelector('[data-carousel-use-url]')?.checked);
        const previewFromMedia =
            readElementMediaSrc(cardEl.querySelector('[data-carousel-media-image]')) ||
            readElementMediaSrc(cardEl.querySelector('[data-carousel-media-video]')) ||
            '';
        const previewUrl = isUsableMediaPreviewUrl(cardEl.dataset.previewUrl)
            ? cardEl.dataset.previewUrl
            : previewFromMedia;

        cards[index] = {
            header: cardEl.querySelector('[data-carousel-header-type]')?.value || 'IMAGE',
            body: cardEl.querySelector('textarea[name*="[body]"]')?.value || '',
            media_path: cardEl.querySelector('[data-carousel-media-path]')?.value || '',
            media_url: cardEl.querySelector('[data-carousel-media-url]')?.value || '',
            media_preview_url: previewUrl,
            use_url: useUrl,
            buttons: [0, 1].map((buttonIndex) => {
                const buttonEl = cardEl.querySelectorAll('[data-carousel-button]')[buttonIndex];
                return {
                    type: buttonEl?.querySelector('[data-carousel-button-type]')?.value || 'QUICK_REPLY',
                    text: buttonEl?.querySelector('input[name*="[text]"]')?.value || '',
                    url: buttonEl?.querySelector('input[name*="[url]"]')?.value || '',
                };
            }),
        };
    };

    const syncAllFromDom = () => {
        cardElements().forEach((cardEl, index) => syncCardFromDom(cardEl, index));
    };

    const markInvalid = (el, message) => {
        if (!el) {
            return;
        }
        el.classList.add('border-red-500');
        el.setAttribute('aria-invalid', 'true');
        if (window.WapAppFormValidation?.showFieldError) {
            window.WapAppFormValidation.showFieldError(el, message);
        }
    };

    const clearInvalid = (scope = form) => {
        scope.querySelectorAll('.border-red-500, [aria-invalid="true"]').forEach((el) => {
            el.classList.remove('border-red-500');
            el.removeAttribute('aria-invalid');
            window.WapAppFormValidation?.clearFieldError?.(el);
        });
        scope.querySelectorAll('[data-carousel-upload-error]').forEach((el) => {
            el.textContent = '';
            el.classList.add('hidden');
        });
    };

    const activeButtonsFromCard = (card) =>
        (card.buttons || []).filter((button) => String(button?.text || '').trim() !== '');

    const validateCarousel = () => {
        syncAllFromDom();
        clearInvalid();
        const errors = [];
        let referenceHeader = null;
        let referenceTypes = null;
        let referenceCount = null;
        const quickReplyTexts = new Set();

        cards.forEach((card, index) => {
            const cardEl = cardElements()[index];
            const cardTitle = `Card ${index + 1}`;
            const body = String(card.body || '').trim();
            const useUrl = Boolean(card.use_url);
            const mediaPath = String(card.media_path || '').trim();
            const mediaUrl = String(card.media_url || '').trim();
            const header = String(card.header || 'IMAGE').toUpperCase();
            const activeButtons = activeButtonsFromCard(card);
            const buttonEls = [...(cardEl?.querySelectorAll('[data-carousel-button]') || [])];
            const activeButtonEls = buttonEls.filter((el) =>
                Boolean(el.querySelector('input[name*="[text]"]')?.value?.trim()),
            );

            if (!header) {
                errors.push(`${cardTitle}: Header type is required.`);
                markInvalid(cardEl?.querySelector('[data-carousel-header-type]'), 'Header type is required.');
            }

            if (useUrl) {
                if (!mediaUrl) {
                    errors.push(`${cardTitle}: Header media is required.`);
                    markInvalid(cardEl?.querySelector('[data-carousel-media-url]'), 'Media URL is required.');
                }
            } else if (!mediaPath) {
                errors.push(`${cardTitle}: Header media is required.`);
                const zone = cardEl?.querySelector('[data-carousel-upload]');
                const errorEl = zone?.querySelector('[data-carousel-upload-error]');
                if (errorEl) {
                    errorEl.textContent = 'Please upload media for this card.';
                    errorEl.classList.remove('hidden');
                }
                cardEl?.querySelector('[data-carousel-upload] label')?.classList.add('border-red-500');
            }

            if (!body) {
                errors.push(`${cardTitle}: Body text is required.`);
                markInvalid(cardEl?.querySelector('textarea[name*="[body]"]'), 'Body text is required.');
            } else if (body.length > CAROUSEL_CARD_BODY_LIMIT) {
                errors.push(`${cardTitle}: Body text exceeds ${CAROUSEL_CARD_BODY_LIMIT} characters.`);
                markInvalid(
                    cardEl?.querySelector('textarea[name*="[body]"]'),
                    `Max ${CAROUSEL_CARD_BODY_LIMIT} characters.`,
                );
            }

            if (activeButtons.length === 0) {
                errors.push(`${cardTitle}: At least one button is required.`);
                markInvalid(
                    cardEl?.querySelector('[data-carousel-button] input[name*="[text]"]'),
                    'At least one button is required.',
                );
            }

            if (activeButtons.length > 2) {
                errors.push(`${cardTitle}: No more than 2 buttons allowed.`);
            }

            const types = activeButtons.map((button) => String(button.type || 'QUICK_REPLY').toUpperCase());

            activeButtons.forEach((button, buttonIndex) => {
                const text = String(button.text || '').trim();
                const type = String(button.type || 'QUICK_REPLY').toUpperCase();
                const value = String(button.url || '').trim();
                const buttonEl = activeButtonEls[buttonIndex];
                const textInput = buttonEl?.querySelector('input[name*="[text]"]');
                const urlInput = buttonEl?.querySelector('input[name*="[url]"]');

                if (text.length > CAROUSEL_BUTTON_TEXT_LIMIT) {
                    errors.push(
                        `${cardTitle}: Button text "${text}" exceeds ${CAROUSEL_BUTTON_TEXT_LIMIT} character limit.`,
                    );
                    markInvalid(textInput, `Max ${CAROUSEL_BUTTON_TEXT_LIMIT} characters.`);
                }

                if ((type === 'URL' || type === 'PHONE_NUMBER') && !value) {
                    const fieldLabel = type === 'PHONE_NUMBER' ? 'phone number' : 'website URL';
                    errors.push(`${cardTitle}: Button "${text}" needs a ${fieldLabel}.`);
                    markInvalid(urlInput, `Enter a ${fieldLabel}.`);
                }

                if (type === 'QUICK_REPLY' && text) {
                    const key = text.toLowerCase();
                    if (quickReplyTexts.has(key)) {
                        errors.push(
                            `${cardTitle}: Quick reply button text "${text}" is already used in another card.`,
                        );
                        markInvalid(textInput, 'Quick reply text must be unique across cards.');
                    } else {
                        quickReplyTexts.add(key);
                    }
                }
            });

            if (referenceHeader === null) {
                referenceHeader = header;
            } else if (header !== referenceHeader) {
                errors.push(`${cardTitle}: Header type must match other cards (${referenceHeader}).`);
                markInvalid(cardEl?.querySelector('[data-carousel-header-type]'), `Must be ${referenceHeader}.`);
            }

            if (referenceCount === null && activeButtons.length) {
                referenceCount = activeButtons.length;
                referenceTypes = [...types];
            } else if (referenceCount !== null) {
                if (activeButtons.length !== referenceCount) {
                    errors.push(`${cardTitle}: Button count does not match the first card.`);
                }
                const mismatchType = types.some((type, i) => type !== referenceTypes[i]);
                if (mismatchType) {
                    errors.push(`${cardTitle}: Button types do not match the first card.`);
                }
            }
        });

        if (cards.length < minCards) {
            errors.push(`Carousel needs at least ${minCards} cards.`);
        }

        if (cards.length > maxCards) {
            errors.push(`Carousel can have at most ${maxCards} cards.`);
        }

        return errors;
    };

    const buttonFields = (card, index, buttonIndex) => {
        const button = card.buttons?.[buttonIndex] || {};
        const type = button.type || 'QUICK_REPLY';
        const showUrl = type === 'URL' || type === 'PHONE_NUMBER';

        return `
          <div class="grid grid-cols-1 gap-2 rounded-lg bg-muted-surface p-3 md:grid-cols-3" data-carousel-button>
            <div>
              <label class="text-xs font-medium text-text-subtle">Button ${buttonIndex + 1} type</label>
              <select name="cards[${index}][buttons][${buttonIndex}][type]" class="fd-input mt-1 w-full rounded-xl border border-border p-2.5" data-carousel-button-type>
                <option value="QUICK_REPLY" ${type === 'QUICK_REPLY' ? 'selected' : ''}>Quick Reply</option>
                <option value="URL" ${type === 'URL' ? 'selected' : ''}>Visit Website</option>
                <option value="PHONE_NUMBER" ${type === 'PHONE_NUMBER' ? 'selected' : ''}>Call Phone</option>
              </select>
            </div>
            <div>
              <label class="text-xs font-medium text-text-subtle">Button text</label>
              <input name="cards[${index}][buttons][${buttonIndex}][text]" value="${escapeHtml(button.text || '')}" maxlength="${CAROUSEL_BUTTON_TEXT_LIMIT}" class="fd-input mt-1 w-full rounded-xl border border-border p-2.5" placeholder="Learn more">
            </div>
            <div class="${showUrl ? '' : 'hidden'}" data-carousel-button-url-wrap>
              <label class="text-xs font-medium text-text-subtle">${type === 'PHONE_NUMBER' ? 'Phone number' : 'Website URL'}</label>
              <input name="cards[${index}][buttons][${buttonIndex}][url]" value="${escapeHtml(button.url || '')}" class="fd-input mt-1 w-full rounded-xl border border-border p-2.5" placeholder="${type === 'PHONE_NUMBER' ? '+919876543210' : 'https://example.com'}">
            </div>
          </div>`;
    };

    const mediaFields = (card, index) => {
        const useUrl = Boolean(card.use_url);
        const previewUrl = card.media_preview_url || (!useUrl ? '' : card.media_url) || '';
        const isVideo = String(card.header || 'IMAGE').toUpperCase() === 'VIDEO';
        const accept = isVideo ? 'video/mp4,video/3gpp' : 'image/png,image/jpeg';
        const hint = isVideo ? 'Only .mp4 or .3gp (max 16 MB)' : 'Only .png or .jpg (max 5 MB)';
        const maxBytes = isVideo ? 16777216 : 5242880;
        const hasPreview = Boolean(previewUrl);

        return `
          <div class="flex flex-col gap-3">
            <label class="text-sm font-medium">Media <span class="text-[red]">*</span></label>
            <input type="hidden" name="cards[${index}][media_path]" value="${escapeHtml(card.media_path || '')}" data-carousel-media-path>
            <div class="${useUrl ? '' : 'hidden'}" data-carousel-url-wrap>
              <input type="url" name="cards[${index}][media_url]" value="${escapeHtml(card.media_url || '')}" class="fd-input w-full rounded-xl border border-border p-3" placeholder="${isVideo ? 'https://example.com/video.mp4' : 'https://example.com/image.jpg'}" data-carousel-media-url ${useUrl ? '' : 'disabled'}>
            </div>
            <div class="${useUrl ? 'hidden' : ''}" data-carousel-file-wrap>
              <div class="flex flex-col gap-3" data-carousel-upload data-max-bytes="${maxBytes}">
                <label class="relative flex h-[88px] cursor-pointer flex-col items-center justify-center overflow-hidden rounded-md border border-dashed border-divider px-6 py-3 transition-colors hover:border-green-500">
                  <img src="/images/templates/upload-frame.svg" alt="" class="pointer-events-none size-6" aria-hidden="true">
                  <p class="pointer-events-none mt-2 text-center text-xs text-text-body">
                    <span class="font-medium">Drag &amp; Drop or</span>
                    <span class="font-medium text-green-500"> choose</span>
                    <span class="font-medium"> file to upload</span>
                  </p>
                  <p class="pointer-events-none mt-1 text-center text-[10px] font-medium text-text-body opacity-50">${hint}</p>
                  <input type="file" accept="${accept}" class="absolute inset-0 cursor-pointer opacity-0" data-carousel-media-input aria-label="Upload carousel media">
                </label>
                <div data-carousel-media-preview class="${hasPreview ? '' : 'hidden'}">
                  <img ${isVideo || !hasPreview ? '' : `src="${escapeHtml(previewUrl)}"`} alt="" class="max-h-40 w-full rounded-lg object-cover ${isVideo || !hasPreview ? 'hidden' : ''}" data-carousel-media-image>
                  <video ${isVideo && hasPreview ? `src="${escapeHtml(previewUrl)}"` : ''} class="max-h-40 w-full rounded-lg ${isVideo && hasPreview ? '' : 'hidden'}" ${isVideo && hasPreview ? 'controls' : ''} muted playsinline preload="metadata" data-carousel-media-video></video>
                  <p class="mt-1 text-xs text-text-subtle" data-carousel-media-name></p>
                </div>
                <p class="hidden text-xs text-red-500" data-carousel-upload-error></p>
              </div>
            </div>
            <label class="flex items-center gap-2 text-sm text-text-muted">
              <input type="checkbox" name="cards[${index}][use_url]" value="1" data-carousel-use-url ${useUrl ? 'checked' : ''}>
              Use URL instead of uploading a file
            </label>
          </div>`;
    };

    const canRemove = () => cards.length > minCards;

    const renderCard = (card, index) => {
        const removeDisabled = !canRemove();
        return `
      <div class="flex flex-col gap-3 rounded-lg border border-divider p-4" data-carousel-card data-preview-url="${escapeHtml(card.media_preview_url || '')}">
        <div class="flex items-center justify-between">
          <p class="text-sm font-semibold text-text-body">Card ${index + 1}</p>
          <button
            type="button"
            data-carousel-remove
            class="text-sm font-medium ${removeDisabled ? 'cursor-not-allowed text-text-subtle opacity-50' : 'text-red-600 hover:underline'}"
            ${removeDisabled ? 'disabled aria-disabled="true"' : ''}
            title="${removeDisabled ? `Minimum ${minCards} cards required` : 'Remove this card'}"
          >Remove</button>
        </div>
        <div>
          <label class="text-sm font-medium">Header type</label>
          <select name="cards[${index}][header]" class="fd-input mt-1 w-full max-w-xs rounded-xl border border-border p-3" data-carousel-header-type>
            <option value="IMAGE" ${(card.header || 'IMAGE') === 'IMAGE' ? 'selected' : ''}>Image</option>
            <option value="VIDEO" ${card.header === 'VIDEO' ? 'selected' : ''}>Video</option>
          </select>
        </div>
        ${mediaFields(card, index)}
        <div>
          <label class="text-sm font-medium">Card body <span class="text-[red]">*</span></label>
          <textarea name="cards[${index}][body]" maxlength="${CAROUSEL_CARD_BODY_LIMIT}" rows="3" required class="fd-input mt-1 w-full rounded-xl border border-border p-3">${escapeHtml(card.body || '')}</textarea>
          <p class="mt-1 text-[10px] text-text-subtle">Max ${CAROUSEL_CARD_BODY_LIMIT} characters</p>
        </div>
        <div class="flex flex-col gap-2">
          <p class="text-sm font-medium text-text-body">Buttons (1–2, same types/order on every card)</p>
          ${buttonFields(card, index, 0)}
          ${buttonFields(card, index, 1)}
        </div>
      </div>`;
    };

    const paint = () => {
        root.innerHTML = cards.map((card, index) => renderCard(card, index)).join('');
        document.getElementById('add-carousel-card')?.classList.toggle('hidden', cards.length >= maxCards);
        notifyPreview();
    };

    const showUploadError = (zone, message) => {
        const errorEl = zone?.querySelector('[data-carousel-upload-error]');
        if (!errorEl) {
            return;
        }
        errorEl.textContent = message || '';
        errorEl.classList.toggle('hidden', !message);
    };

    const uploadCardMedia = async (cardEl, file) => {
        const zone = cardEl.querySelector('[data-carousel-upload]');
        const pathInput = cardEl.querySelector('[data-carousel-media-path]');
        const previewWrap = cardEl.querySelector('[data-carousel-media-preview]');
        const imageEl = cardEl.querySelector('[data-carousel-media-image]');
        const videoEl = cardEl.querySelector('[data-carousel-media-video]');
        const nameEl = cardEl.querySelector('[data-carousel-media-name]');
        const headerSelect = cardEl.querySelector('[data-carousel-header-type]');
        const maxBytes = Number(zone?.dataset.maxBytes || 0);

        showUploadError(zone, '');

        if (maxBytes > 0 && file.size > maxBytes) {
            const mb = Math.round(maxBytes / (1024 * 1024));
            showUploadError(zone, `File is too large. Max allowed is ${mb} MB.`);
            return;
        }

        const isVideo = file.type.startsWith('video/');
        const isImage = file.type.startsWith('image/');
        if (!isVideo && !isImage) {
            showUploadError(zone, 'Only image (.jpg/.png) or video (.mp4/.3gp) files are supported.');
            return;
        }

        const previousUrl = cardEl.dataset.previewUrl || '';
        if (previousUrl.startsWith('blob:')) {
            URL.revokeObjectURL(previousUrl);
        }

        const objectUrl = URL.createObjectURL(file);
        if (nameEl) {
            nameEl.textContent = file.name;
        }

        if (isVideo) {
            if (imageEl) {
                imageEl.classList.add('hidden');
                imageEl.removeAttribute('src');
            }
            if (videoEl) {
                videoEl.src = objectUrl;
                videoEl.setAttribute('controls', '');
                videoEl.classList.remove('hidden');
            }
            if (headerSelect) {
                headerSelect.value = 'VIDEO';
            }
        } else {
            if (videoEl) {
                videoEl.classList.add('hidden');
                videoEl.removeAttribute('controls');
                videoEl.removeAttribute('src');
                videoEl.load?.();
            }
            if (imageEl) {
                imageEl.src = objectUrl;
                imageEl.classList.remove('hidden');
            }
            if (headerSelect) {
                headerSelect.value = 'IMAGE';
            }
        }

        previewWrap?.classList.remove('hidden');
        cardEl.dataset.previewUrl = objectUrl;
        notifyPreview();

        if (!uploadUrl) {
            return;
        }

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
        const body = new FormData();
        body.append('carousel_media', file);
        body.append('_token', csrfToken);

        try {
            const response = await fetch(uploadUrl, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                body,
            });
            const data = await response.json().catch(() => ({}));
            if (!response.ok) {
                const message =
                    data.message ||
                    data.errors?.carousel_media?.[0] ||
                    data.errors?.header_media?.[0] ||
                    'Unable to upload media.';
                showUploadError(zone, message);
                return;
            }

            if (pathInput) {
                pathInput.value = data.path || '';
            }
            if (data.url) {
                if (objectUrl.startsWith('blob:')) {
                    URL.revokeObjectURL(objectUrl);
                }
                cardEl.dataset.previewUrl = data.url;
                if (isVideo && videoEl) {
                    videoEl.src = data.url;
                } else if (imageEl) {
                    imageEl.src = data.url;
                }
            }
            if (data.type && headerSelect) {
                headerSelect.value = data.type;
            }
            const index = cardElements().indexOf(cardEl);
            if (index >= 0) {
                syncCardFromDom(cardEl, index);
            }
            notifyPreview();
        } catch {
            showUploadError(zone, 'Network error while uploading. Please try again.');
        }
    };

    const removeCardAt = (index) => {
        syncAllFromDom();
        if (!canRemove()) {
            showToast({
                type: 'warning',
                title: 'Cannot remove',
                message: `Carousel needs at least ${minCards} cards.`,
            });
            return;
        }
        if (index < 0 || index >= cards.length) {
            return;
        }
        cards.splice(index, 1);
        paint();
    };

    document.getElementById('add-carousel-card')?.addEventListener('click', () => {
        syncAllFromDom();
        if (cards.length >= maxCards) {
            showToast({
                type: 'warning',
                title: 'Card limit',
                message: `You can add at most ${maxCards} cards.`,
            });
            return;
        }
        cards.push({
            header: cards[0]?.header || 'IMAGE',
            body: '',
            media_url: '',
            media_path: '',
            media_preview_url: '',
            use_url: false,
            buttons: [
                { text: '', type: 'QUICK_REPLY', url: '' },
                { text: '', type: 'QUICK_REPLY', url: '' },
            ],
        });
        paint();
    });

    // Delegate on form so Remove always works (root innerHTML is replaced on paint).
    form.addEventListener('click', (event) => {
        const remove = event.target.closest('[data-carousel-remove]');
        if (!remove || !form.contains(remove)) {
            return;
        }
        event.preventDefault();
        event.stopPropagation();
        const cardEl = remove.closest('[data-carousel-card]');
        const index = cardElements().indexOf(cardEl);
        removeCardAt(index);
    });

    root.addEventListener('change', (event) => {
        const cardEl = event.target.closest('[data-carousel-card]');
        const index = cardEl ? cardElements().indexOf(cardEl) : -1;

        const typeSelect = event.target.closest('[data-carousel-button-type]');
        if (typeSelect) {
            const wrap = typeSelect.closest('[data-carousel-button]')?.querySelector('[data-carousel-button-url-wrap]');
            const showUrl = typeSelect.value === 'URL' || typeSelect.value === 'PHONE_NUMBER';
            wrap?.classList.toggle('hidden', !showUrl);
            const label = wrap?.querySelector('label');
            const input = wrap?.querySelector('input');
            if (label) {
                label.textContent = typeSelect.value === 'PHONE_NUMBER' ? 'Phone number' : 'Website URL';
            }
            if (input) {
                input.placeholder = typeSelect.value === 'PHONE_NUMBER' ? '+919876543210' : 'https://example.com';
            }
        }

        const useUrlToggle = event.target.closest('[data-carousel-use-url]');
        if (useUrlToggle && cardEl) {
            const useUrl = useUrlToggle.checked;
            cardEl.querySelector('[data-carousel-url-wrap]')?.classList.toggle('hidden', !useUrl);
            cardEl.querySelector('[data-carousel-file-wrap]')?.classList.toggle('hidden', useUrl);
            const urlInput = cardEl.querySelector('[data-carousel-media-url]');
            if (urlInput) {
                urlInput.disabled = !useUrl;
            }
            if (useUrl) {
                const pathInput = cardEl.querySelector('[data-carousel-media-path]');
                if (pathInput) {
                    pathInput.value = '';
                }
            }
        }

        const headerType = event.target.closest('[data-carousel-header-type]');
        if (headerType && cardEl && index >= 0) {
            syncCardFromDom(cardEl, index);
            paint();
            return;
        }

        const fileInput = event.target.closest('[data-carousel-media-input]');
        if (fileInput instanceof HTMLInputElement && cardEl && fileInput.files?.[0]) {
            uploadCardMedia(cardEl, fileInput.files[0]);
        }

        if (index >= 0) {
            syncCardFromDom(cardEl, index);
        }
        notifyPreview();
    });

    root.addEventListener('input', (event) => {
        const cardEl = event.target.closest('[data-carousel-card]');
        const index = cardEl ? cardElements().indexOf(cardEl) : -1;
        if (index >= 0) {
            syncCardFromDom(cardEl, index);
        }
        notifyPreview();
    });

    form.addEventListener(
        'submit',
        (event) => {
            const errors = validateCarousel();
            if (errors.length === 0) {
                return;
            }
            event.preventDefault();
            event.stopImmediatePropagation();
            showToast({
                type: 'error',
                title: 'Fix carousel errors',
                message: errors.slice(0, 4).join(' '),
            });
            form.querySelector('.border-red-500, [aria-invalid="true"], [data-carousel-upload-error]:not(.hidden)')?.scrollIntoView({
                behavior: 'smooth',
                block: 'center',
            });
        },
        true,
    );

    form.querySelector('[name="carousel_body"]')?.addEventListener('input', notifyPreview);

    paint();
}

function initAuthApps() {
    const root = document.getElementById('auth-android-apps');
    const list = root?.querySelector('[data-auth-apps-list]');
    if (!root || !list) {
        return;
    }

    let apps = JSON.parse(root.dataset.authApps || '[]');
    if (!apps.length) {
        apps = [{ package_name: '', signature_hash: '' }];
    }

    const paint = () => {
        list.innerHTML = apps
            .map(
                (app, index) => `
          <div class="grid grid-cols-1 gap-2 md:grid-cols-2" data-auth-app-row>
            <input name="supported_apps[${index}][package_name]" value="${escapeHtml(app.package_name || '')}" placeholder="Package name" class="fd-input rounded-xl border border-border p-3">
            <input name="supported_apps[${index}][signature_hash]" value="${escapeHtml(app.signature_hash || '')}" placeholder="Signature hash" class="fd-input rounded-xl border border-border p-3">
          </div>`,
            )
            .join('');
    };

    root.querySelector('[data-auth-add-app]')?.addEventListener('click', () => {
        if (apps.length >= 55) {
            return;
        }
        apps.push({ package_name: '', signature_hash: '' });
        paint();
    });

    paint();
}

export function initTemplateBuilder() {
    const root = document.querySelector('[data-template-builder]');
    const previewRoot = document.getElementById('template-live-preview');

    if (!root || !previewRoot) {
        return;
    }

    const preview = new TemplateLivePreview(previewRoot);
    let frame = 0;

    const scheduleUpdate = () => {
        cancelAnimationFrame(frame);
        frame = requestAnimationFrame(() => {
            preview.render(collectPreviewState(root));
        });
    };

    initHeaderSections(scheduleUpdate);
    initHeaderMedia(scheduleUpdate);
    initBodyEditor(root, preview, scheduleUpdate);
    initButtonsEditor(root, preview, scheduleUpdate);
    initVariableModal(root);
    initUtilityPresets();
    initAiSuggestions(root);
    initCarouselBuilder(scheduleUpdate);
    initAuthApps();

    document.querySelector('textarea[name="footer_text"]')?.addEventListener('input', scheduleUpdate);

    scheduleUpdate();
}
