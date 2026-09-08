const BODY_CHAR_LIMIT = Number(document.getElementById('builder-body-form')?.dataset.bodyLimit) || 1024;
const BUTTON_TEXT_LIMIT = Number(document.getElementById('template-buttons-root')?.dataset.buttonTextLimit) || 20;
const BUTTON_URL_LIMIT = 2000;
const MAX_BUTTONS = Number(document.getElementById('template-buttons-root')?.dataset.maxButtons) || 10;
const MAX_URL_BUTTONS = Number(document.getElementById('template-buttons-root')?.dataset.maxUrlButtons) || 2;
const MAX_PHONE_BUTTONS = Number(document.getElementById('template-buttons-root')?.dataset.maxPhoneButtons) || 1;
const DEFAULT_UNSUBSCRIBE_URL = `${window.location.origin}/unsubscribe-list/$(unsub)`;
const COMMON_EMOJIS = ['😀', '😊', '👍', '🎉', '❤️', '🔥', '✅', '🙏', '💬', '📞'];

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

    html = html.replace(/(\^)([^\^]+)(\^)/g, '<strong>$2</strong>');
    html = html.replace(/(_)([^_]+)(_)/g, '<em>$2</em>');
    html = html.replace(/(~)([^~]+)(~)/g, '<del>$2</del>');

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
    if (type === 'phone') {
        return '/images/templates/call.svg';
    }

    if (type === 'flow') {
        return '/images/templates/flow.svg';
    }

    if (type === 'quick_reply') {
        return '/images/templates/quick-reply.svg';
    }

    if (type === 'unsubscribe') {
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
    }

    render(state) {
        const headerType = state.headerType || 'none';
        const showImage = headerType === 'image' && Boolean(state.headerImage);
        const showVideo = headerType === 'video' && Boolean(state.headerVideo);
        const showHeaderText =
            (headerType === 'text' || headerType === 'location') &&
            (Boolean(state.headerText) || headerType === 'location');

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
            this.footer.classList.toggle('hidden', footerText === '');
            this.footer.textContent = footerText;
        }

        if (this.divider) {
            const hasButtons = (state.buttons || []).length > 0;
            this.divider.classList.toggle('hidden', !hasButtons);
        }

        if (this.buttons) {
            const buttons = (state.buttons || []).filter((button) => button.text?.trim());

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
    }
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

    if (!sections.length && !radios.length) {
        return;
    }

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

        scheduleUpdate();
    };

    radios.forEach((radio) => {
        radio.addEventListener('change', syncSections);
    });

    ['header_text', 'template_name'].forEach((id) => {
        document.getElementById(id)?.addEventListener('input', scheduleUpdate);
    });

    syncSections();
}

function initHeaderMedia(scheduleUpdate) {
    const form = document.getElementById('builder-header-form');
    const uploadUrl = form?.dataset.headerUploadUrl || '';

    document.querySelectorAll('[data-header-upload]').forEach((zone) => {
        const input = zone.querySelector('[data-header-media-input]');
        const previewWrap = zone.querySelector('[data-header-media-preview]');
        const imageEl = zone.querySelector('[data-header-media-image]');
        const videoEl = zone.querySelector('[data-header-media-video]');
        const nameEl = zone.querySelector('[data-header-media-name]');

        if (!input) {
            return;
        }

        input.addEventListener('change', async () => {
            const file = input.files?.[0];
            if (!file) {
                return;
            }

            const objectUrl = URL.createObjectURL(file);
            const isVideo = file.type.startsWith('video/');

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
            } else {
                videoEl?.classList.add('hidden');
                if (videoEl) {
                    videoEl.removeAttribute('src');
                }
                if (imageEl) {
                    imageEl.classList.remove('hidden');
                    imageEl.src = objectUrl;
                    imageEl.dataset.previewUrl = objectUrl;
                }
            }

            scheduleUpdate();

            if (!uploadUrl) {
                return;
            }

            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
            const body = new FormData();
            body.append('header_media', file);

            try {
                const response = await fetch(uploadUrl, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
                    },
                    body,
                });

                if (!response.ok) {
                    return;
                }

                const data = await response.json();
                const remoteUrl = data.url || '';

                if (!remoteUrl) {
                    return;
                }

                if (data.type === 'video' && videoEl) {
                    videoEl.src = remoteUrl;
                    videoEl.dataset.previewUrl = remoteUrl;
                    videoEl.classList.remove('hidden');
                    imageEl?.classList.add('hidden');
                } else if (imageEl) {
                    imageEl.src = remoteUrl;
                    imageEl.dataset.previewUrl = remoteUrl;
                    imageEl.classList.remove('hidden');
                    videoEl?.classList.add('hidden');
                }

                scheduleUpdate();
            } catch {
                // Keep local object URL preview when upload fails.
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

function initCarouselBuilder() {
    const root = document.getElementById('carousel-cards-root');
    const form = document.getElementById('carousel-builder-form');
    if (!root || !form) {
        return;
    }

    const minCards = Number(form.dataset.carouselMin || 2);
    const maxCards = Number(form.dataset.carouselMax || 10);
    let cards = JSON.parse(root.dataset.savedCards || '[]');

    const renderCard = (card, index) => `
      <div class="flex flex-col gap-3 rounded-lg border border-divider p-3" data-carousel-card>
        <div class="flex items-center justify-between">
          <p class="text-sm font-semibold text-text-body">Card ${index + 1}</p>
          <button type="button" data-carousel-remove class="text-sm text-red-600">Remove</button>
        </div>
        <input type="hidden" name="cards[${index}][header]" value="${escapeHtml(card.header || 'IMAGE')}">
        <label class="text-sm font-medium">Media URL</label>
        <input name="cards[${index}][media_url]" value="${escapeHtml(card.media_url || '')}" class="fd-input w-full rounded-xl border border-border p-3" placeholder="https://example.com/image.jpg">
        <label class="text-sm font-medium">Body <span class="text-[red]">*</span></label>
        <textarea name="cards[${index}][body]" maxlength="160" rows="3" required class="fd-input w-full rounded-xl border border-border p-3">${escapeHtml(card.body || '')}</textarea>
        <label class="text-sm font-medium">Button text (optional)</label>
        <input name="cards[${index}][buttons][0][text]" value="${escapeHtml(card.buttons?.[0]?.text || '')}" maxlength="25" class="fd-input w-full rounded-xl border border-border p-3">
        <input type="hidden" name="cards[${index}][buttons][0][type]" value="QUICK_REPLY">
      </div>`;

    const paint = () => {
        root.innerHTML = cards.map((card, index) => renderCard(card, index)).join('');
        document.getElementById('add-carousel-card')?.classList.toggle('hidden', cards.length >= maxCards);
    };

    document.getElementById('add-carousel-card')?.addEventListener('click', () => {
        if (cards.length >= maxCards) {
            return;
        }
        cards.push({ header: 'IMAGE', body: '', media_url: '', buttons: [] });
        paint();
    });

    root.addEventListener('click', (event) => {
        const remove = event.target.closest('[data-carousel-remove]');
        if (!remove || cards.length <= minCards) {
            return;
        }
        const cardEl = remove.closest('[data-carousel-card]');
        const index = [...root.querySelectorAll('[data-carousel-card]')].indexOf(cardEl);
        if (index >= 0) {
            cards.splice(index, 1);
            paint();
        }
    });

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
    initCarouselBuilder();
    initAuthApps();

    document.querySelector('textarea[name="footer_text"]')?.addEventListener('input', scheduleUpdate);

    scheduleUpdate();
}
