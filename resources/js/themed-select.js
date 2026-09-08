let openSelect = null;
let ignoreScrollUntil = 0;

function arrowMarkup(variant) {
    const colorClass = variant === 'header' ? 'text-white' : 'text-green-500';

    return `<svg class="size-4 ${colorClass}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m6 9 6 6 6-6" stroke-linecap="round" stroke-linejoin="round"/></svg>`;
}

function detectVariant(select) {
    if (select.dataset.selectVariant) {
        return select.dataset.selectVariant;
    }

    if (select.closest('.bg-green-500')) {
        return 'header';
    }

    if (select.classList.contains('fd-filter-label')) {
        return 'filter';
    }

    return 'default';
}

function selectedLabel(select) {
    const option = select.selectedOptions[0];

    if (!option) {
        return '';
    }

    return option.textContent?.trim() ?? '';
}

function renderOptionContent(item, option) {
    item.replaceChildren();

    const labelSpan = document.createElement('span');
    labelSpan.className = 'fd-select__option-label min-w-0 flex-1 truncate text-left';
    labelSpan.textContent = option.textContent?.trim() ?? '';
    item.appendChild(labelSpan);

    /** @type {Array<{ text: string, kind: string }>} */
    const pills = [];
    const categoryPill = option.dataset.categoryPill?.trim();
    const variablePill = option.dataset.pill?.trim();
    if (categoryPill) {
        const categoryKind = (option.dataset.category || '')
            .trim()
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '_')
            .replace(/^_|_$/g, '') || 'default';
        pills.push({ text: categoryPill, kind: categoryKind });
    }
    if (variablePill) {
        pills.push({ text: variablePill, kind: 'variables' });
    }

    if (pills.length > 0) {
        const pillsWrap = document.createElement('span');
        pillsWrap.className = 'fd-select__pills shrink-0';
        pills.forEach(({ text, kind }) => {
            const pillSpan = document.createElement('span');
            pillSpan.className = `fd-select__pill fd-select__pill--${kind}`;
            pillSpan.textContent = text;
            pillsWrap.appendChild(pillSpan);
        });
        item.appendChild(pillsWrap);
        item.dataset.searchText = `${labelSpan.textContent} ${pills.map((p) => p.text).join(' ')}`.toLowerCase();
    } else {
        item.dataset.searchText = labelSpan.textContent.toLowerCase();
    }
}

function optionsList(menu) {
    return menu.querySelector('[data-themed-select-options]');
}

function resetSearch(menu) {
    const search = menu.querySelector('[data-themed-select-search]');
    if (search instanceof HTMLInputElement) {
        search.value = '';
    }

    menu.querySelectorAll('[data-themed-select-option]').forEach((button) => {
        button.hidden = false;
    });

    const empty = menu.querySelector('[data-themed-select-empty]');
    if (empty instanceof HTMLElement) {
        empty.hidden = true;
    }
}

function filterOptions(menu, query) {
    const needle = query.trim().toLowerCase();
    let visible = 0;

    menu.querySelectorAll('[data-themed-select-option]').forEach((button) => {
        const label = (button.dataset.searchText || button.textContent || '').trim().toLowerCase();
        const match = needle === '' || label.includes(needle);
        button.hidden = !match;
        if (match) {
            visible += 1;
        }
    });

    const empty = menu.querySelector('[data-themed-select-empty]');
    if (empty instanceof HTMLElement) {
        empty.hidden = visible > 0;
    }
}

function closeOpenSelect() {
    if (!openSelect) {
        return;
    }

    const { wrap, trigger, menu } = openSelect;
    wrap.classList.remove('fd-select-open');
    trigger.setAttribute('aria-expanded', 'false');
    menu.classList.add('hidden');
    menu.classList.remove('fd-select__menu--fixed', 'fd-select__menu--open');
    menu.style.top = '';
    menu.style.left = '';
    menu.style.width = '';
    menu.style.height = '';
    menu.style.maxHeight = '';
    menu.style.display = '';

    if (menu.parentElement !== wrap) {
        wrap.appendChild(menu);
    }

    resetSearch(menu);
    openSelect = null;
}

function positionMenu(menu, trigger) {
    const rect = trigger.getBoundingClientRect();
    const viewportPadding = 8;
    const preferredHeight = 300;
    const spaceBelow = window.innerHeight - rect.bottom - viewportPadding;
    const spaceAbove = rect.top - viewportPadding;
    const openUp = spaceBelow < 180 && spaceAbove > spaceBelow;
    const menuHeight = Math.max(180, Math.min(preferredHeight, openUp ? spaceAbove - 4 : spaceBelow - 4));

    // Portal to body so overflow:hidden ancestors cannot clip/block the menu.
    if (menu.parentElement !== document.body) {
        document.body.appendChild(menu);
    }

    menu.classList.add('fd-select__menu--fixed');
    menu.style.width = `${Math.max(rect.width, 180)}px`;
    menu.style.height = `${menuHeight}px`;
    menu.style.maxHeight = `${menuHeight}px`;

    if (openUp) {
        menu.style.top = `${Math.max(viewportPadding, rect.top - menuHeight - 4)}px`;
    } else {
        menu.style.top = `${rect.bottom + 4}px`;
    }

    let left = rect.left;
    const menuWidth = Math.max(rect.width, 180);
    if (left + menuWidth > window.innerWidth - viewportPadding) {
        left = window.innerWidth - menuWidth - viewportPadding;
    }
    menu.style.left = `${Math.max(viewportPadding, left)}px`;
}

function syncMenuState(select, menu) {
    menu.querySelectorAll('[data-themed-select-option]').forEach((button) => {
        const isSelected = button.dataset.value === select.value;
        button.classList.toggle('fd-select__option--selected', isSelected);
        button.setAttribute('aria-selected', isSelected ? 'true' : 'false');
    });
}

function rebuildMenuOptions(select, wrap, trigger, menu, label) {
    const list = optionsList(menu);
    if (!(list instanceof HTMLElement)) {
        return;
    }

    list.querySelectorAll('[data-themed-select-option]').forEach((button) => button.remove());

    const empty = menu.querySelector('[data-themed-select-empty]');
    Array.from(select.options).forEach((option) => {
        const item = document.createElement('button');
        item.type = 'button';
        item.className = 'fd-select__option';
        item.dataset.themedSelectOption = 'true';
        item.dataset.value = option.value;
        item.setAttribute('role', 'option');
        renderOptionContent(item, option);

        if (option.disabled) {
            item.disabled = true;
            item.classList.add('fd-select__option--disabled');
        }

        item.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();
            chooseOption(select, option.value, wrap, trigger, menu, label);
        });

        if (empty) {
            list.insertBefore(item, empty);
        } else {
            list.appendChild(item);
        }
    });

    syncMenuState(select, menu);
}

function syncFromNative(select, label, menu) {
    label.textContent = selectedLabel(select);
    syncMenuState(select, menu);
}

function chooseOption(select, value, wrap, trigger, menu, label) {
    if (select.disabled) {
        return;
    }

    select.value = value;
    select.dispatchEvent(new Event('change', { bubbles: true }));
    syncFromNative(select, label, menu);
    closeOpenSelect();
}

function scrollSelectedIntoList(menu) {
    const list = optionsList(menu);
    const selected = menu.querySelector('.fd-select__option--selected:not([hidden])');

    if (!(list instanceof HTMLElement) || !(selected instanceof HTMLElement)) {
        return;
    }

    const listTop = list.scrollTop;
    const listBottom = listTop + list.clientHeight;
    const itemTop = selected.offsetTop;
    const itemBottom = itemTop + selected.offsetHeight;

    if (itemTop < listTop) {
        list.scrollTop = itemTop;
    } else if (itemBottom > listBottom) {
        list.scrollTop = itemBottom - list.clientHeight;
    }
}

function toggleMenu(wrap, trigger, menu, select, label) {
    if (select.disabled) {
        return;
    }

    if (openSelect?.wrap === wrap) {
        closeOpenSelect();
        return;
    }

    closeOpenSelect();
    ignoreScrollUntil = Date.now() + 400;
    rebuildMenuOptions(select, wrap, trigger, menu, label);
    wrap.classList.add('fd-select-open');
    trigger.setAttribute('aria-expanded', 'true');
    menu.classList.remove('hidden');
    menu.classList.add('fd-select__menu--open');
    menu.style.display = 'flex';
    positionMenu(menu, trigger);
    openSelect = { wrap, trigger, menu };

    const search = menu.querySelector('[data-themed-select-search]');
    if (search instanceof HTMLInputElement) {
        // Defer focus so layout is settled and we do not scroll the page.
        requestAnimationFrame(() => {
            if (openSelect?.menu === menu) {
                search.focus({ preventScroll: true });
                scrollSelectedIntoList(menu);
            }
        });
    } else {
        scrollSelectedIntoList(menu);
    }
}

function unwrapLegacyWrapper(select) {
    const parent = select.parentElement;

    if (!parent?.classList.contains('relative')) {
        return select;
    }

    // Never unwrap real layout containers — that would detach the select from its form.
    if (
        parent instanceof HTMLFormElement
        || parent.matches('form, label, [data-keep-relative]')
        || parent.querySelector(':scope > input:not([type="hidden"]), :scope > button, :scope > textarea')
    ) {
        return select;
    }

    const grandparent = parent.parentElement;

    if (!grandparent) {
        return select;
    }

    parent.querySelectorAll('svg, img, [class*="pointer-events-none"]').forEach((node) => {
        if (node !== select) {
            node.remove();
        }
    });

    grandparent.insertBefore(select, parent);
    parent.remove();

    return select;
}

function buildTriggerClasses(select, variant) {
    const classes = ['fd-select__trigger', `fd-select__trigger--${variant}`];

    if (select.disabled) {
        classes.push('fd-select__trigger--disabled');
    }

    if (select.classList.contains('w-full')) {
        classes.push('w-full');
    }

    return classes.join(' ');
}

function stopMenuScrollPropagation(event) {
    event.stopPropagation();
}

function containWheelInside(list, event) {
    const delta = event.deltaY;
    const atTop = list.scrollTop <= 0;
    const atBottom = list.scrollTop + list.clientHeight >= list.scrollHeight - 1;

    if ((delta < 0 && atTop) || (delta > 0 && atBottom)) {
        event.preventDefault();
    }

    event.stopPropagation();
}

function buildMenu(select, wrap, trigger, label) {
    const menu = document.createElement('div');
    menu.className = 'fd-select__menu hidden';
    menu.setAttribute('role', 'listbox');
    menu.dataset.themedSelectMenu = 'true';

    const searchWrap = document.createElement('div');
    searchWrap.className = 'fd-select__search';

    const search = document.createElement('input');
    search.type = 'search';
    search.className = 'fd-select__search-input';
    search.placeholder = 'Search...';
    search.autocomplete = 'off';
    search.dataset.themedSelectSearch = 'true';
    search.setAttribute('aria-label', 'Search options');

    search.addEventListener('click', (event) => {
        event.stopPropagation();
    });

    search.addEventListener('mousedown', (event) => {
        event.stopPropagation();
    });

    search.addEventListener('keydown', (event) => {
        event.stopPropagation();
        if (event.key === 'Escape') {
            event.preventDefault();
            closeOpenSelect();
            trigger.focus();
        }
    });

    search.addEventListener('input', () => {
        filterOptions(menu, search.value);
    });

    searchWrap.appendChild(search);

    const list = document.createElement('div');
    list.className = 'fd-select__options';
    list.dataset.themedSelectOptions = 'true';

    const empty = document.createElement('p');
    empty.className = 'fd-select__empty';
    empty.dataset.themedSelectEmpty = 'true';
    empty.hidden = true;
    empty.textContent = 'No options found';

    Array.from(select.options).forEach((option) => {
        const item = document.createElement('button');
        item.type = 'button';
        item.className = 'fd-select__option';
        item.dataset.themedSelectOption = 'true';
        item.dataset.value = option.value;
        item.setAttribute('role', 'option');
        renderOptionContent(item, option);

        if (option.disabled) {
            item.disabled = true;
            item.classList.add('fd-select__option--disabled');
        }

        if (option.selected) {
            item.classList.add('fd-select__option--selected');
            item.setAttribute('aria-selected', 'true');
        } else {
            item.setAttribute('aria-selected', 'false');
        }

        item.addEventListener('click', () => {
            chooseOption(select, option.value, wrap, trigger, menu, label);
        });

        list.appendChild(item);
    });

    list.appendChild(empty);
    menu.append(searchWrap, list);

    list.addEventListener('scroll', stopMenuScrollPropagation, { passive: true });
    list.addEventListener('wheel', (event) => containWheelInside(list, event), { passive: false });
    list.addEventListener('touchmove', stopMenuScrollPropagation, { passive: true });
    menu.addEventListener('wheel', (event) => {
        event.stopPropagation();
    }, { passive: true });

    return menu;
}

export function enhanceSelect(select) {
    if (!(select instanceof HTMLSelectElement)) {
        return;
    }

    if (select.multiple || select.dataset.nativeSelect === 'true' || select.dataset.themedSelect === 'true') {
        return;
    }

    if (select.closest('.fd-select')) {
        return;
    }

    const cleanedSelect = unwrapLegacyWrapper(select);
    const variant = detectVariant(cleanedSelect);
    const mountParent = cleanedSelect.parentElement;

    if (!mountParent) {
        return;
    }

    cleanedSelect.dataset.themedSelect = 'true';

    const wrap = document.createElement('div');
    wrap.className = 'fd-select';

    if (cleanedSelect.classList.contains('w-full')) {
        wrap.classList.add('w-full');
    }

    mountParent.insertBefore(wrap, cleanedSelect);
    wrap.appendChild(cleanedSelect);

    cleanedSelect.classList.add('fd-select__native');
    cleanedSelect.tabIndex = -1;
    cleanedSelect.setAttribute('aria-hidden', 'true');

    const trigger = document.createElement('button');
    trigger.type = 'button';
    trigger.className = buildTriggerClasses(cleanedSelect, variant);
    trigger.setAttribute('aria-haspopup', 'listbox');
    trigger.setAttribute('aria-expanded', 'false');

    if (cleanedSelect.id) {
        trigger.id = `${cleanedSelect.id}-trigger`;
        trigger.setAttribute('aria-labelledby', cleanedSelect.id);
    }

    const label = document.createElement('span');
    label.className = 'fd-select__label min-w-0 flex-1 truncate text-left';
    label.textContent = selectedLabel(cleanedSelect);

    const icon = document.createElement('span');
    icon.className = 'fd-select__icon shrink-0 transition-transform duration-200';
    icon.innerHTML = arrowMarkup(variant);

    trigger.append(label, icon);

    const menu = buildMenu(cleanedSelect, wrap, trigger, label);

    wrap.append(trigger, menu);

    if (cleanedSelect.disabled) {
        trigger.disabled = true;
    }

    trigger.addEventListener('pointerdown', (event) => {
        event.preventDefault();
        event.stopPropagation();
        toggleMenu(wrap, trigger, menu, cleanedSelect, label);
    });

    cleanedSelect.addEventListener('change', () => {
        syncFromNative(cleanedSelect, label, menu);
    });

    // Keep label clicks opening the themed trigger instead of the hidden native select.
    if (cleanedSelect.id) {
        document.querySelectorAll(`label[for="${cleanedSelect.id}"]`).forEach((fieldLabel) => {
            fieldLabel.setAttribute('for', trigger.id);
        });
    }
}

export function initThemedSelects(root = document) {
    root.querySelectorAll('select').forEach((select) => {
        enhanceSelect(select);
    });
}

function isInsideOpenMenu(target) {
    if (!(target instanceof Node) || !openSelect) {
        return false;
    }

    return openSelect.menu.contains(target) || openSelect.wrap.contains(target);
}

export function initThemedSelectObserver() {
    document.addEventListener('pointerdown', (event) => {
        if (!(event.target instanceof Element)) {
            return;
        }

        if (!isInsideOpenMenu(event.target)) {
            closeOpenSelect();
        }
    }, true);

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeOpenSelect();
        }
    });

    window.addEventListener('scroll', (event) => {
        if (!openSelect || Date.now() < ignoreScrollUntil) {
            return;
        }

        if (isInsideOpenMenu(event.target)) {
            return;
        }

        // Ignore scroll events that originate from the options list itself.
        const list = optionsList(openSelect.menu);
        if (list && (event.target === list || (event.target instanceof Node && list.contains(event.target)))) {
            return;
        }

        closeOpenSelect();
    }, true);

    window.addEventListener('resize', () => {
        closeOpenSelect();
    });

    const observer = new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
            mutation.addedNodes.forEach((node) => {
                if (!(node instanceof Element)) {
                    return;
                }

                if (node.matches('select')) {
                    enhanceSelect(node);
                }

                node.querySelectorAll('select').forEach((select) => {
                    enhanceSelect(select);
                });
            });
        });
    });

    observer.observe(document.body, { childList: true, subtree: true });
}
