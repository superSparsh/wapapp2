/**
 * WhatsApp Flow Builder — Vanilla JS module for screen-based form design.
 *
 * Manages screens, form fields, field configuration, navigation rules,
 * category-tabbed palette with drag/click-to-add, live WhatsApp preview,
 * and save/load via the builder API.
 */

let state = { screens: [], first_screen: null };
let currentScreenId = null;
let currentFieldIndex = null;
let flowId = null;
let canvasEl = null;
let screenCounter = 0;
let isDraggingFromPalette = false;
let previewVisible = false;

const FIELD_LABELS = {
    text: 'Text Input',
    email: 'Email',
    phone: 'Phone Number',
    number: 'Number',
    password: 'Password / Passcode',
    paragraph: 'Paragraph / Text Area',
    date: 'Date Picker',
    radio: 'Single Choice (Radio)',
    checkbox: 'Multi Choice (Checkbox)',
    dropdown: 'Dropdown',
    'opt-in': 'Opt-in Checkbox',
    'large-heading': 'Large Heading',
    'small-heading': 'Small Heading',
    'text-display': 'Text Body',
    caption: 'Caption Text',
    image: 'Image',
    footer: 'Footer Button',
};

export function initWhatsappFlowBuilder(config) {
    flowId = config.flowId;
    state = config.flowJson && config.flowJson.screens
        ? config.flowJson
        : { screens: [], first_screen: null };
    canvasEl = document.getElementById('flow-canvas');
    if (!canvasEl) return;

    screenCounter = state.screens.length;

    setupCategoryTabs();
    setupPaletteDrag();
    setupPaletteClick();
    setupScreenList();
    setupScreenTitleEditing();
    setupFieldDropTarget();
    setupNavRules();
    setupToolbarActions();
    setupImportModal();
    setupPreviewListener();
    renderScreenList();
    updateBadges();

    if (state.screens.length > 0) {
        selectScreen(state.first_screen || state.screens[0].id);
    }
}

/* ── Category Tabs ── */

function setupCategoryTabs() {
    const tabWrappers = document.querySelectorAll('.category-tab-wrapper');
    if (tabWrappers.length === 0) return;

    tabWrappers.forEach((wrapper) => {
        const tabBtn = wrapper.querySelector('.category-tab');
        const dropdown = wrapper.querySelector('.category-dropdown');
        const arrow = wrapper.querySelector('.category-arrow');
        if (!tabBtn || !dropdown) return;

        tabBtn.addEventListener('click', (e) => {
            if (isDraggingFromPalette) return;
            e.stopPropagation();

            const isCurrentlyOpen = !dropdown.classList.contains('hidden');

            closeAllCategoryTabs();

            if (!isCurrentlyOpen) {
                dropdown.classList.remove('hidden');
                tabBtn.classList.add('bg-green-100');
                tabBtn.classList.remove('bg-elevated');
                if (arrow) arrow.classList.add('rotate-180');
            }
        });
    });

    document.addEventListener('click', (e) => {
        if (isDraggingFromPalette) return;
        if (!e.target.closest('.category-tab-wrapper')) {
            closeAllCategoryTabs();
        }
    });
}

function closeAllCategoryTabs() {
    document.querySelectorAll('.category-tab-wrapper').forEach((wrapper) => {
        const tabBtn = wrapper.querySelector('.category-tab');
        const dropdown = wrapper.querySelector('.category-dropdown');
        const arrow = wrapper.querySelector('.category-arrow');
        if (dropdown) dropdown.classList.add('hidden');
        if (tabBtn) {
            tabBtn.classList.remove('bg-green-100');
            tabBtn.classList.add('bg-elevated');
        }
        if (arrow) arrow.classList.remove('rotate-180');
    });
}

/* ── Drag from Palette ── */

function setupPaletteDrag() {
    document.querySelectorAll('.palette-field').forEach((field) => {
        field.addEventListener('dragstart', (e) => {
            isDraggingFromPalette = true;
            e.dataTransfer.setData('text/plain', JSON.stringify({
                type: field.dataset.fieldType,
                label: field.dataset.fieldLabel,
            }));
            e.dataTransfer.effectAllowed = 'copy';
        });

        field.addEventListener('dragend', () => {
            setTimeout(() => { isDraggingFromPalette = false; }, 100);
        });
    });
}

/* ── Click to add from Palette ── */

function setupPaletteClick() {
    document.querySelectorAll('.palette-field').forEach((field) => {
        field.addEventListener('click', (e) => {
            if (isDraggingFromPalette) return;
            e.stopPropagation();
            if (!currentScreenId) {
                showStatus('Please select or create a screen first.', 'error');
                return;
            }
            addFieldToScreen(field.dataset.fieldType, field.dataset.fieldLabel);
            closeAllCategoryTabs();
        });
    });
}

function setupFieldDropTarget() {
    const container = document.getElementById('fields-container');
    if (!container) return;

    container.addEventListener('dragover', (e) => {
        if (!isDraggingFromPalette) return;
        e.preventDefault();
        e.dataTransfer.dropEffect = 'copy';
    });

    container.addEventListener('drop', (e) => {
        if (!isDraggingFromPalette) return;
        e.preventDefault();

        try {
            const data = JSON.parse(e.dataTransfer.getData('text/plain'));
            addFieldToScreen(data.type, data.label);
        } catch {
            // Ignore invalid drops
        }

        setTimeout(() => { isDraggingFromPalette = false; }, 100);
    });
}

/* ── Screen List Management ── */

function setupScreenList() {
    const addBtn = document.querySelector('[data-action="add-screen"]');
    if (addBtn) {
        addBtn.addEventListener('click', () => {
            screenCounter++;
            const id = 'screen_' + screenCounter;
            const screen = { id, title: 'Screen ' + screenCounter, fields: [], next_screen: '', conditions: [] };
            state.screens.push(screen);
            if (!state.first_screen) state.first_screen = id;
            renderScreenList();
            updateBadges();
            selectScreen(id);
        });
    }

    const deleteBtn = document.querySelector('[data-action="delete-screen"]');
    if (deleteBtn) {
        deleteBtn.addEventListener('click', async () => {
            if (!currentScreenId || state.screens.length <= 1) return;

            const confirmed = window.showAppConfirm
                ? await window.showAppConfirm({ message: 'Delete this screen?', title: 'Delete screen', variant: 'danger', confirmLabel: 'Delete' })
                : confirm('Delete this screen?');
            if (!confirmed) return;

            const idx = state.screens.findIndex(s => s.id === currentScreenId);
            if (idx === -1) return;
            state.screens.splice(idx, 1);

            if (state.first_screen === currentScreenId) {
                state.first_screen = state.screens.length > 0 ? state.screens[0].id : null;
            }
            state.screens.forEach(s => { if (s.next_screen === currentScreenId) s.next_screen = ''; });

            currentScreenId = null;
            renderScreenList();
            updateBadges();
            if (state.screens.length > 0) selectScreen(state.screens[0].id);
            else clearEditor();
        });
    }
}

/* ── Screen Title Editing ── */

function setupScreenTitleEditing() {
    const titleDisplayWrap = document.getElementById('screen-title-display-wrap');
    const titleDisplay = document.getElementById('current-screen-title');
    const editBtn = document.getElementById('edit-screen-title-btn');
    const titleForm = document.getElementById('screen-title-form');
    const titleInput = document.getElementById('screen-title-input');
    const cancelBtn = document.getElementById('cancel-screen-title');

    if (editBtn) {
        editBtn.addEventListener('click', () => {
            if (!currentScreenId) return;
            const screen = getScreen(currentScreenId);
            if (!screen) return;

            if (titleDisplayWrap) titleDisplayWrap.classList.add('hidden');
            if (titleForm) titleForm.classList.remove('hidden');
            if (titleInput) {
                titleInput.value = screen.title || '';
                titleInput.focus();
                titleInput.select();
            }
        });
    }

    if (titleDisplay) {
        titleDisplay.addEventListener('dblclick', () => {
            if (editBtn && !editBtn.classList.contains('hidden')) {
                editBtn.click();
            }
        });
    }

    if (cancelBtn) {
        cancelBtn.addEventListener('click', () => {
            if (titleForm) titleForm.classList.add('hidden');
            if (titleDisplayWrap) titleDisplayWrap.classList.remove('hidden');
        });
    }

    if (titleForm) {
        titleForm.addEventListener('submit', (e) => {
            e.preventDefault();
            if (!currentScreenId) return;
            const screen = getScreen(currentScreenId);
            if (!screen) return;

            const newTitle = titleInput?.value.trim();
            if (newTitle) {
                screen.title = newTitle;
                if (titleDisplay) titleDisplay.textContent = newTitle;
                renderScreenList();
                renderNavSelect();
                renderPreview();
            }

            titleForm.classList.add('hidden');
            if (titleDisplayWrap) titleDisplayWrap.classList.remove('hidden');
        });
    }
}

function renderScreenList() {
    const list = document.getElementById('screen-list');
    if (!list) return;

    list.innerHTML = '';
    state.screens.forEach((screen, i) => {
        const el = document.createElement('button');
        el.type = 'button';
        el.className = 'flex w-full items-center justify-between rounded-lg px-3 py-2 text-left text-sm transition-colors '
            + (screen.id === currentScreenId ? 'bg-green-100 text-text-subtle font-semibold' : 'bg-surface hover:bg-green-50 text-text-body');
        el.dataset.screenId = screen.id;

        const label = document.createElement('span');
        label.className = 'truncate';
        label.textContent = (i === 0 && state.first_screen === screen.id ? '★ ' : '') + screen.title;
        el.appendChild(label);

        const badge = document.createElement('span');
        badge.className = 'rounded bg-elevated px-1.5 py-0.5 text-[10px] text-text-muted';
        badge.textContent = screen.fields.length + ' fields';
        el.appendChild(badge);

        el.addEventListener('click', () => selectScreen(screen.id));
        list.appendChild(el);
    });
}

function selectScreen(screenId) {
    currentScreenId = screenId;
    currentFieldIndex = null;
    const screen = getScreen(screenId);
    if (!screen) return;

    renderScreenList();

    const titleEl = document.getElementById('current-screen-title');
    if (titleEl) titleEl.textContent = screen.title;

    const editBtn = document.getElementById('edit-screen-title-btn');
    if (editBtn) editBtn.classList.remove('hidden');

    const titleDisplayWrap = document.getElementById('screen-title-display-wrap');
    if (titleDisplayWrap) titleDisplayWrap.classList.remove('hidden');

    const titleForm = document.getElementById('screen-title-form');
    if (titleForm) titleForm.classList.add('hidden');

    const deleteBtn = document.querySelector('[data-action="delete-screen"]');
    if (deleteBtn) deleteBtn.classList.toggle('hidden', state.screens.length <= 1);

    const navSection = document.getElementById('nav-rules-section');
    if (navSection) navSection.classList.remove('hidden');

    renderFields();
    renderNavSelect();
    hideConfigPanel();
    renderPreview();
}

function clearEditor() {
    const titleEl = document.getElementById('current-screen-title');
    if (titleEl) titleEl.textContent = 'Select a screen';

    const editBtn = document.getElementById('edit-screen-title-btn');
    if (editBtn) editBtn.classList.add('hidden');

    const titleDisplayWrap = document.getElementById('screen-title-display-wrap');
    if (titleDisplayWrap) titleDisplayWrap.classList.remove('hidden');

    const titleForm = document.getElementById('screen-title-form');
    if (titleForm) titleForm.classList.add('hidden');

    const container = document.getElementById('fields-container');
    if (container) {
        container.innerHTML = '<div id="empty-screen-hint" class="flex flex-col items-center justify-center gap-2 py-20 text-center"><p class="text-sm font-medium text-text-muted">Select a screen from the left or create a new one</p></div>';
    }
    const navSection = document.getElementById('nav-rules-section');
    if (navSection) navSection.classList.add('hidden');
    const deleteBtn = document.querySelector('[data-action="delete-screen"]');
    if (deleteBtn) deleteBtn.classList.add('hidden');
    hideConfigPanel();
    renderPreview();
}

/* ── Field Management ── */

function addFieldToScreen(type, label) {
    const screen = getScreen(currentScreenId);
    if (!screen) return;

    const name = type.replace(/-/g, '_') + '_' + Date.now();
    const field = {
        name,
        type,
        label: label || FIELD_LABELS[type] || type,
        required: false,
        helper_text: '',
    };

    if (['text', 'email', 'phone', 'number', 'password', 'paragraph'].includes(type)) {
        field.placeholder = '';
    }
    if (['radio', 'checkbox', 'dropdown'].includes(type)) {
        field.options = ['Option 1', 'Option 2'];
    }
    if (['large-heading', 'small-heading', 'text-display', 'caption'].includes(type)) {
        field.text = type === 'large-heading'
            ? 'Heading Text'
            : (type === 'small-heading'
                ? 'Subheading Text'
                : (type === 'caption' ? 'Caption text' : 'Display text here'));
    }
    if (type === 'image') {
        field.src = '';
    }
    if (type === 'footer') {
        field.text = '';
    }

    screen.fields.push(field);
    renderFields();
    renderScreenList();
    updateBadges();
    renderPreview();
}

function renderFields() {
    const container = document.getElementById('fields-container');
    if (!container) return;
    const screen = getScreen(currentScreenId);
    if (!screen) return;

    container.innerHTML = '';
    if (screen.fields.length === 0) {
        container.innerHTML = '<div class="flex flex-col items-center justify-center gap-2 py-16 text-center"><p class="text-sm font-medium text-text-muted">Drag fields from the palette above or click to add</p></div>';
        return;
    }

    screen.fields.forEach((field, idx) => {
        const row = document.createElement('div');
        row.className = 'flex items-center gap-3 rounded-lg border border-divider bg-surface p-3 transition-colors cursor-pointer '
            + (idx === currentFieldIndex ? 'border-green-500 ring-1 ring-green-500' : 'hover:border-green-300');
        row.addEventListener('click', () => { currentFieldIndex = idx; showConfigPanel(field, idx); renderFields(); });

        // Drag handle for reordering
        const handle = document.createElement('span');
        handle.className = 'cursor-grab text-text-muted select-none';
        handle.textContent = '⠿';
        handle.draggable = true;
        handle.addEventListener('dragstart', e => { e.dataTransfer.setData('text/plain', String(idx)); });
        handle.addEventListener('dragover', e => e.preventDefault());
        handle.addEventListener('drop', e => {
            e.preventDefault();
            const from = parseInt(e.dataTransfer.getData('text/plain'), 10);
            if (from === idx) return;
            const [moved] = screen.fields.splice(from, 1);
            screen.fields.splice(idx, 0, moved);
            renderFields();
            renderPreview();
        });
        row.appendChild(handle);

        // Type badge
        const typeBadge = document.createElement('span');
        typeBadge.className = 'shrink-0 rounded bg-green-50 px-2 py-0.5 text-[10px] font-semibold text-green-600';
        typeBadge.textContent = FIELD_LABELS[field.type] || field.type;
        row.appendChild(typeBadge);

        // Label
        const labelEl = document.createElement('span');
        labelEl.className = 'min-w-0 flex-1 truncate text-sm text-text-body';
        labelEl.textContent = field.label || field.name;
        row.appendChild(labelEl);

        // Required indicator
        if (field.required) {
            const req = document.createElement('span');
            req.className = 'text-[10px] font-medium text-red-400';
            req.textContent = 'REQ';
            row.appendChild(req);
        }

        // Remove button
        const removeBtn = document.createElement('button');
        removeBtn.type = 'button';
        removeBtn.className = 'shrink-0 rounded p-1 text-text-muted hover:bg-red-50 hover:text-red-500';
        removeBtn.innerHTML = '&times;';
        removeBtn.addEventListener('click', e => {
            e.stopPropagation();
            screen.fields.splice(idx, 1);
            if (currentFieldIndex === idx) { currentFieldIndex = null; hideConfigPanel(); }
            renderFields();
            renderScreenList();
            updateBadges();
            renderPreview();
        });
        row.appendChild(removeBtn);

        container.appendChild(row);
    });
}

/* ── Field Config Panel ── */

function showConfigPanel(field, idx) {
    const panel = document.getElementById('field-config-panel');
    const body = document.getElementById('config-panel-body');
    const title = document.getElementById('config-panel-title');
    if (!panel || !body) return;

    panel.classList.remove('hidden');
    if (title) title.textContent = (FIELD_LABELS[field.type] || field.type) + ' Config';

    body.innerHTML = '';

    // Label / Title
    if (['large-heading', 'small-heading', 'caption', 'text-display', 'footer'].includes(field.type)) {
        body.appendChild(createTextarea('Text Content', field.text || field.label || '', v => {
            field.text = v;
            field.label = v;
            renderFields();
            renderPreview();
        }));
    } else {
        body.appendChild(createInput('Label', field.label || '', v => {
            field.label = v;
            renderFields();
            renderPreview();
        }));
    }

    // Field Name (Identifier)
    const nameRow = document.createElement('div');
    nameRow.className = 'flex flex-col gap-1';
    nameRow.innerHTML = '<label class="text-xs font-medium text-text-muted">Field Name (Payload Key)</label><p class="text-xs text-text-body bg-surface rounded px-2 py-1.5 font-mono">' + escapeHtml(field.name) + '</p>';
    body.appendChild(nameRow);

    // Placeholder
    if (['text', 'email', 'phone', 'number', 'password', 'paragraph'].includes(field.type)) {
        body.appendChild(createInput('Placeholder', field.placeholder || '', v => {
            field.placeholder = v;
            renderPreview();
        }));
    }

    // Helper Text (optional description for user)
    if (!['large-heading', 'small-heading', 'caption', 'text-display', 'footer', 'image'].includes(field.type)) {
        body.appendChild(createInput('Helper Text (optional description)', field.helper_text || '', v => {
            field.helper_text = v;
            renderPreview();
        }));
    }

    // Image URL
    if (field.type === 'image') {
        body.appendChild(createInput('Image URL', field.src || '', v => {
            field.src = v;
            renderPreview();
        }));
    }

    // Required toggle
    if (!['large-heading', 'small-heading', 'caption', 'text-display', 'footer', 'image'].includes(field.type)) {
        body.appendChild(createToggle('Required', !!field.required, v => {
            field.required = v;
            renderFields();
            renderPreview();
        }));
    }

    // Options editor for selection types
    if (['radio', 'checkbox', 'dropdown'].includes(field.type)) {
        body.appendChild(createOptionsEditor(field));
    }

    const removeBtn = document.createElement('button');
    removeBtn.type = 'button';
    removeBtn.className = 'mt-2 w-full rounded-lg bg-red-50 py-2 text-xs font-semibold text-red-500 hover:bg-red-100';
    removeBtn.textContent = 'Remove Field';
    removeBtn.addEventListener('click', () => {
        const screen = getScreen(currentScreenId);
        if (screen) {
            screen.fields.splice(idx, 1);
            currentFieldIndex = null;
            hideConfigPanel();
            renderFields();
            renderScreenList();
            updateBadges();
            renderPreview();
        }
    });
    body.appendChild(removeBtn);
}

function hideConfigPanel() {
    const panel = document.getElementById('field-config-panel');
    if (panel) panel.classList.add('hidden');
}

function createInput(labelText, value, onChange) {
    const wrap = document.createElement('div');
    wrap.className = 'flex flex-col gap-1';
    const lbl = document.createElement('label');
    lbl.className = 'text-xs font-medium text-text-muted';
    lbl.textContent = labelText;
    wrap.appendChild(lbl);
    const input = document.createElement('input');
    input.type = 'text';
    input.value = value;
    input.className = 'w-full rounded border border-divider bg-surface px-3 py-2 text-xs text-text-body focus:border-green-500 focus:outline-none';
    input.addEventListener('input', () => onChange(input.value));
    wrap.appendChild(input);
    return wrap;
}

function createTextarea(labelText, value, onChange) {
    const wrap = document.createElement('div');
    wrap.className = 'flex flex-col gap-1';
    const lbl = document.createElement('label');
    lbl.className = 'text-xs font-medium text-text-muted';
    lbl.textContent = labelText;
    wrap.appendChild(lbl);
    const ta = document.createElement('textarea');
    ta.value = value;
    ta.rows = 3;
    ta.className = 'w-full rounded border border-divider bg-surface px-3 py-2 text-xs text-text-body focus:border-green-500 focus:outline-none';
    ta.addEventListener('input', () => onChange(ta.value));
    wrap.appendChild(ta);
    return wrap;
}

function createToggle(labelText, checked, onChange) {
    const wrap = document.createElement('div');
    wrap.className = 'flex items-center justify-between';
    const lbl = document.createElement('label');
    lbl.className = 'text-xs font-medium text-text-muted';
    lbl.textContent = labelText;
    wrap.appendChild(lbl);
    const cb = document.createElement('input');
    cb.type = 'checkbox';
    cb.checked = checked;
    cb.className = 'size-4 accent-green-500';
    cb.addEventListener('change', () => onChange(cb.checked));
    wrap.appendChild(cb);
    return wrap;
}

function createOptionsEditor(field) {
    const wrap = document.createElement('div');
    wrap.className = 'flex flex-col gap-2';
    const lbl = document.createElement('label');
    lbl.className = 'text-xs font-medium text-text-muted';
    lbl.textContent = 'Options';
    wrap.appendChild(lbl);

    const list = document.createElement('div');
    list.className = 'flex flex-col gap-1';

    function render() {
        list.innerHTML = '';
        (field.options || []).forEach((opt, i) => {
            const row = document.createElement('div');
            row.className = 'flex items-center gap-2';
            const input = document.createElement('input');
            input.type = 'text';
            input.value = opt;
            input.className = 'flex-1 rounded border border-divider bg-surface px-2 py-1.5 text-xs text-text-body focus:border-green-500 focus:outline-none';
            input.addEventListener('input', () => { field.options[i] = input.value; renderPreview(); });
            row.appendChild(input);
            const rm = document.createElement('button');
            rm.type = 'button';
            rm.className = 'text-text-muted hover:text-red-500 text-xs';
            rm.textContent = '✕';
            rm.addEventListener('click', () => { field.options.splice(i, 1); render(); renderPreview(); });
            row.appendChild(rm);
            list.appendChild(row);
        });
    }
    render();

    const addBtn = document.createElement('button');
    addBtn.type = 'button';
    addBtn.className = 'rounded bg-green-50 px-2 py-1 text-xs font-semibold text-green-500 hover:bg-green-100';
    addBtn.textContent = '+ Add Option';
    addBtn.addEventListener('click', () => {
        if (!field.options) field.options = [];
        field.options.push('Option ' + (field.options.length + 1));
        render();
        renderPreview();
    });

    wrap.appendChild(list);
    wrap.appendChild(addBtn);
    return wrap;
}

/* ── Navigation Rules ── */

function setupNavRules() {
    const select = document.getElementById('next-screen-select');
    if (!select) return;
    select.addEventListener('change', () => {
        const screen = getScreen(currentScreenId);
        if (screen) {
            screen.next_screen = select.value;
            renderPreview();
        }
    });
}

function renderNavSelect() {
    const select = document.getElementById('next-screen-select');
    if (!select) return;
    const screen = getScreen(currentScreenId);
    if (!screen) return;

    select.innerHTML = '<option value="">(End / Success)</option>';
    state.screens.forEach(s => {
        if (s.id === currentScreenId) return;
        const opt = document.createElement('option');
        opt.value = s.id;
        opt.textContent = s.title;
        if (screen.next_screen === s.id) opt.selected = true;
        select.appendChild(opt);
    });
}

/* ── Preview ── */

function setupPreviewListener() {
    window.addEventListener('flow-preview-render', () => {
        previewVisible = true;
        renderPreview();
    });
}

function renderPreview() {
    const panel = document.getElementById('preview-panel');
    const content = document.getElementById('preview-content');
    const indicator = document.getElementById('preview-screen-indicator');
    const nextBtn = document.getElementById('preview-next-btn');

    if (!panel || panel.classList.contains('hidden')) return;

    const screen = getScreen(currentScreenId);

    if (!screen || screen.fields.length === 0) {
        if (content) content.innerHTML = '<p class="py-8 text-center text-xs text-[#667781]">No fields to preview</p>';
        if (indicator) indicator.textContent = 'No screen';
        if (nextBtn) nextBtn.textContent = 'Next';
        return;
    }

    // Screen indicator
    const screenIdx = state.screens.findIndex(s => s.id === currentScreenId);
    if (indicator) indicator.textContent = 'Screen ' + (screenIdx + 1) + ' of ' + state.screens.length;

    // Next button text
    if (nextBtn) {
        const nextScreen = screen.next_screen ? getScreen(screen.next_screen) : null;
        nextBtn.textContent = nextScreen ? 'Next' : 'Submit';
    }

    // Build WhatsApp-style form preview
    let html = '<div class="rounded-lg bg-white p-3 shadow-sm">';
    html += '<p class="mb-3 text-sm font-semibold text-[#111b21]">' + escapeHtml(screen.title) + '</p>';

    screen.fields.forEach(field => {
        html += renderPreviewField(field);
    });

    html += '</div>';

    if (content) content.innerHTML = html;
}

function renderPreviewField(field) {
    const reqStar = field.required ? ' <span class="text-red-500">*</span>' : '';
    const label = '<label class="mb-1 block text-xs font-medium text-[#667781]">' + escapeHtml(field.label || field.name) + reqStar + '</label>';
    const helperHtml = field.helper_text ? '<p class="mt-1 text-[10px] text-[#8696a0]">' + escapeHtml(field.helper_text) + '</p>' : '';

    switch (field.type) {
        case 'text':
        case 'email':
        case 'phone':
        case 'number':
        case 'password':
            const iconSvg = field.type === 'phone'
                ? '<span class="text-[10px] text-[#667781] pr-1.5 border-r border-[#d1d7db] mr-2">📞</span>'
                : (field.type === 'email'
                    ? '<span class="text-[10px] text-[#667781] pr-1.5 border-r border-[#d1d7db] mr-2">✉️</span>'
                    : (field.type === 'password' ? '<span class="text-[10px] text-[#667781] pr-1.5 border-r border-[#d1d7db] mr-2">🔒</span>' : ''));
            return '<div class="mb-3">' + label
                + '<div class="flex items-center rounded border border-[#d1d7db] bg-white px-3 py-2 text-xs text-[#667781]">'
                + iconSvg
                + escapeHtml(field.placeholder || ('Enter ' + (field.label || field.name)))
                + '</div>'
                + helperHtml
                + '</div>';

        case 'paragraph':
            return '<div class="mb-3">' + label
                + '<div class="min-h-[55px] rounded border border-[#d1d7db] bg-white px-3 py-2 text-xs text-[#667781]">'
                + escapeHtml(field.placeholder || ('Enter ' + (field.label || field.name) + '...'))
                + '</div>'
                + helperHtml
                + '</div>';

        case 'date':
            return '<div class="mb-3">' + label
                + '<div class="flex items-center justify-between rounded border border-[#d1d7db] bg-white px-3 py-2 text-xs text-[#667781]">'
                + '<span>Select date</span>'
                + '<svg class="size-3.5 text-[#667781]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>'
                + '</div>'
                + helperHtml
                + '</div>';

        case 'radio':
            let radioHtml = '<div class="mb-3">' + label;
            (field.options || []).forEach((opt, i) => {
                radioHtml += '<label class="mb-1.5 flex items-center gap-2 text-xs text-[#111b21] cursor-pointer">'
                    + '<span class="inline-block size-3.5 shrink-0 rounded-full border-2 border-[#00a884] ' + (i === 0 ? 'bg-[#00a884]' : '') + '"></span>'
                    + escapeHtml(opt) + '</label>';
            });
            return radioHtml + helperHtml + '</div>';

        case 'checkbox':
            let checkHtml = '<div class="mb-3">' + label;
            (field.options || []).forEach((opt, i) => {
                checkHtml += '<label class="mb-1.5 flex items-center gap-2 text-xs text-[#111b21] cursor-pointer">'
                    + '<span class="inline-block size-3.5 shrink-0 rounded-sm border border-[#00a884] ' + (i === 0 ? 'bg-[#00a884]' : '') + '"></span>'
                    + escapeHtml(opt) + '</label>';
            });
            return checkHtml + helperHtml + '</div>';

        case 'dropdown':
            let dropHtml = '<div class="mb-3">' + label;
            dropHtml += '<div class="flex items-center justify-between rounded border border-[#d1d7db] bg-white px-3 py-2 text-xs text-[#667781]">'
                + '<span>Select...</span>'
                + '<span class="text-[10px]">▼</span>'
                + '</div>';
            return dropHtml + helperHtml + '</div>';

        case 'opt-in':
            return '<div class="mb-3">'
                + '<label class="flex items-start gap-2 text-xs text-[#111b21] cursor-pointer">'
                + '<span class="inline-block size-4 mt-0.5 shrink-0 rounded border-2 border-[#00a884] bg-[#00a884] text-white text-center leading-[14px] text-[10px]">✓</span>'
                + '<span class="flex-1">' + escapeHtml(field.label || 'I agree to the terms') + reqStar + '</span>'
                + '</label>'
                + helperHtml
                + '</div>';

        case 'large-heading':
            return '<div class="mb-2"><h2 class="text-base font-bold text-[#111b21] leading-tight">'
                + escapeHtml(field.text || field.label || 'Heading') + '</h2></div>';

        case 'small-heading':
            return '<div class="mb-2"><h3 class="text-sm font-semibold text-[#111b21] leading-snug">'
                + escapeHtml(field.text || field.label || 'Subheading') + '</h3></div>';

        case 'caption':
            return '<div class="mb-2"><p class="text-[11px] text-[#667781] italic leading-normal">'
                + escapeHtml(field.text || field.label || 'Caption text') + '</p></div>';

        case 'text-display':
            return '<div class="mb-3 rounded bg-[#d9fdd3] p-2.5 text-xs text-[#111b21]">'
                + escapeHtml(field.text || 'Display text') + '</div>';

        case 'image':
            return '<div class="mb-3">' + label
                + '<div class="flex h-20 items-center justify-center rounded bg-[#f0f2f5] text-[10px] text-[#667781] overflow-hidden">'
                + (field.src ? '<img src="' + escapeHtml(field.src) + '" class="max-h-full max-w-full object-contain" alt="">' : '[Image Placeholder]')
                + '</div></div>';

        case 'footer':
            return '<div class="mt-2 border-t border-[#d1d7db] pt-2 text-center text-[10px] text-[#667781]">'
                + escapeHtml(field.text || 'Footer text') + '</div>';

        default:
            return '<div class="mb-3">' + label
                + '<div class="rounded border border-[#d1d7db] bg-white px-3 py-2 text-xs text-[#667781]">'
                + escapeHtml(field.placeholder || ('Enter ' + (field.label || field.name)))
                + '</div>'
                + helperHtml
                + '</div>';
    }
}

/* ── Toolbar Actions ── */

function setupToolbarActions() {
    document.querySelectorAll('[data-action]').forEach(btn => {
        const action = btn.dataset.action;
        if (action === 'save-flow') {
            btn.addEventListener('click', saveFlow);
        } else if (action === 'export-flow') {
            btn.addEventListener('click', exportFlow);
        } else if (action === 'import-flow') {
            btn.addEventListener('click', () => {
                const modal = document.getElementById('modal-import-flow');
                if (modal) { modal.classList.remove('hidden'); modal.classList.add('flex'); }
            });
        }
    });
}

function setupImportModal() {
    const form = document.getElementById('import-flow-form');
    if (!form) return;

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const nameInput = document.getElementById('import-name');
        const fileInput = document.getElementById('import-file');
        if (!nameInput?.value || !fileInput?.files?.[0]) return;

        const file = fileInput.files[0];
        const text = await file.text();

        try {
            const data = JSON.parse(text);
            const csrf = document.querySelector('meta[name="csrf-token"]').content;
            const importUrl = canvasEl?.dataset.importUrl || '/whatsapp-flows/import';

            let flowJson = data;
            if (data.flow_json) flowJson = data.flow_json;
            else if (data.flow?.flow_json) flowJson = data.flow.flow_json;

            flowJson.name = nameInput.value;

            const resp = await fetch(importUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ flow_json: flowJson }),
            });

            const result = await resp.json();

            if (result.success) {
                window.location.href = '/whatsapp-flows/' + result.flow_id + '/edit';
            } else {
                showStatus('Import failed: ' + (result.message || 'Unknown error'), 'error');
            }
        } catch (err) {
            showStatus('Import error: ' + err.message, 'error');
        }
    });
}

/* ── Save / Export ── */

async function saveFlow() {
    const saveUrl = canvasEl?.dataset.saveUrl;
    if (!saveUrl) return;

    showStatus('Saving flow...');

    try {
        const csrf = document.querySelector('meta[name="csrf-token"]').content;
        const resp = await fetch(saveUrl, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'Accept': 'application/json',
            },
            body: JSON.stringify({ flow_json: state }),
        });

        const result = await resp.json();

        if (result.success) {
            showStatus('Flow saved! Screens: ' + result.screen_count + ', Fields: ' + result.field_count, 'success');
            updateBadges();
            if (result.draft_synced) {
                const publishBtn = document.getElementById('publish-flow-btn');
                if (publishBtn) {
                    publishBtn.disabled = false;
                    publishBtn.title = 'Publish to WhatsApp';
                }
            }
        } else {
            showStatus('Failed to save flow.', 'error');
        }
    } catch (err) {
        showStatus('Error saving flow: ' + err.message, 'error');
    }
}

async function exportFlow() {
    const exportUrl = canvasEl?.dataset.exportUrl;
    if (!exportUrl) return;

    try {
        const resp = await fetch(exportUrl);
        const data = await resp.json();
        const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'whatsapp-flow-' + (data.flow?.name || flowId) + '.json';
        a.click();
        URL.revokeObjectURL(url);
        showStatus('Flow exported!', 'success');
    } catch (err) {
        showStatus('Export failed: ' + err.message, 'error');
    }
}

/* ── Badges ── */

function updateBadges() {
    const screenBadge = document.getElementById('screen-count-badge');
    const fieldBadge = document.getElementById('field-count-badge');

    const totalFields = state.screens.reduce((sum, s) => sum + (s.fields?.length || 0), 0);

    if (screenBadge) screenBadge.textContent = 'Screens: ' + state.screens.length;
    if (fieldBadge) fieldBadge.textContent = 'Fields: ' + totalFields;
}

/* ── Helpers ── */

function getScreen(id) {
    return state.screens.find(s => s.id === id) || null;
}

function showStatus(message, type) {
    const el = document.getElementById('flow-builder-status');
    if (!el) return;

    el.textContent = message;
    el.className = 'rounded-lg px-4 py-3 text-sm font-medium ' + (
        type === 'error'
            ? 'bg-red-50 text-red-700'
            : type === 'success'
                ? 'bg-green-50 text-green-700'
                : 'bg-blue-50 text-blue-700'
    );
    el.classList.remove('hidden');

    if (type !== 'error') {
        setTimeout(() => el.classList.add('hidden'), 3000);
    }
}

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}
