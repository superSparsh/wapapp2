/**
 * Shared listing page filters: sort, live search, filter auto-submit.
 */

function syncSortFields(form) {
    if (!(form instanceof HTMLFormElement)) {
        return;
    }

    const select = form.querySelector('[data-listing-sort]');
    if (!(select instanceof HTMLSelectElement) || !select.value) {
        return;
    }

    const [sort, direction] = String(select.value).split(':');
    const sortInput = form.querySelector('[data-listing-sort-field]');
    const directionInput = form.querySelector('[data-listing-sort-direction]');

    if (sortInput instanceof HTMLInputElement && sort) {
        sortInput.value = sort;
    }

    if (directionInput instanceof HTMLInputElement && direction) {
        directionInput.value = direction;
    }
}

function submitListingForm(form) {
    if (!(form instanceof HTMLFormElement)) {
        return;
    }

    syncSortFields(form);

    // Drop page so a sort/filter/search change always returns to results page 1.
    form.querySelectorAll('input[name="page"]').forEach((input) => input.remove());

    const method = (form.getAttribute('method') || form.method || 'get').toLowerCase();

    // GET listings: build the URL ourselves so empty search clears `q` and sort
    // always comes from the current hidden fields (not a stale query string).
    if (method === 'get') {
        const action = form.getAttribute('action') || window.location.pathname;
        const url = new URL(action, window.location.origin);
        const params = new URLSearchParams();

        new FormData(form).forEach((value, key) => {
            const text = typeof value === 'string' ? value.trim() : String(value);
            if (text === '') {
                return;
            }
            params.append(key, text);
        });

        url.search = params.toString();
        window.location.assign(url.toString());
        return;
    }

    if (typeof form.requestSubmit === 'function') {
        try {
            form.requestSubmit();
            return;
        } catch {
            // Fall through.
        }
    }

    form.submit();
}

function bindSearchInput(input) {
    if (!(input instanceof HTMLInputElement) || input.dataset.listingSearchBound) {
        return;
    }

    input.dataset.listingSearchBound = '1';

    const form = input.closest('form');
    if (!form) {
        return;
    }

    // Default live search (as-you-type) unless explicitly disabled with 0.
    const rawDebounce = input.dataset.listingSearchDebounce;
    const debounceMs = rawDebounce === undefined || rawDebounce === ''
        ? 350
        : Math.max(0, Number(rawDebounce));

    let timer = null;

    const cancelPending = () => {
        window.clearTimeout(timer);
        timer = null;
    };

    const queueSubmit = (immediate = false) => {
        cancelPending();

        if (immediate || debounceMs === 0) {
            submitListingForm(form);
            return;
        }

        timer = window.setTimeout(() => {
            timer = null;
            submitListingForm(form);
        }, debounceMs);
    };

    input.addEventListener('keydown', (event) => {
        if (event.key !== 'Enter') {
            return;
        }

        event.preventDefault();
        queueSubmit(true);
    });

    input.addEventListener('input', () => {
        // Clearing the box should reset results immediately.
        if (input.value.trim() === '') {
            queueSubmit(true);
            return;
        }

        queueSubmit(false);
    });

    // Native type=search clear (x) button.
    input.addEventListener('search', () => {
        if (input.value.trim() === '') {
            queueSubmit(true);
        }
    });
}

export function initListingFilters() {
    document.querySelectorAll('[data-listing-sort]').forEach((select) => {
        if (!(select instanceof HTMLSelectElement) || select.dataset.listingSortBound) {
            return;
        }

        select.dataset.listingSortBound = '1';

        const form = select.closest('form');
        const sortInput = form?.querySelector('[data-listing-sort-field]');
        const directionInput = form?.querySelector('[data-listing-sort-direction]');

        // Prefer hidden field values (server truth) over the display composite attribute.
        const fromFields = sortInput instanceof HTMLInputElement && directionInput instanceof HTMLInputElement
            ? `${sortInput.value}:${directionInput.value}`
            : '';
        const fromAttr = select.dataset.listingSortCurrent || '';
        const preferred = [fromFields, fromAttr].find((value) => (
            value
            && Array.from(select.options).some((option) => option.value === value)
        ));

        if (preferred) {
            select.value = preferred;
            syncSortFields(form);
        }

        select.addEventListener('change', () => {
            syncSortFields(form);
            submitListingForm(form);
        });
    });

    document.querySelectorAll('[data-listing-filter]').forEach((control) => {
        if (control.dataset.listingFilterBound) {
            return;
        }

        control.dataset.listingFilterBound = '1';

        control.addEventListener('change', () => {
            const form = control.closest('form');
            if (form) {
                submitListingForm(form);
            }
        });
    });

    document.querySelectorAll('[data-listing-search-enter]').forEach((input) => {
        bindSearchInput(input);
    });
}

export function applyServerFieldErrors(errors = {}) {
    if (!errors || typeof errors !== 'object') {
        return;
    }

    Object.entries(errors).forEach(([field, messages]) => {
        const message = Array.isArray(messages) ? messages[0] : messages;
        const input = document.querySelector(`[name="${field}"], [name="${field}[]"]`);

        if (!input || !message) {
            return;
        }

        input.classList.add('border-red-500');
        input.setAttribute('aria-invalid', 'true');

        const wrapper = input.closest('[data-validate-field]') || input.parentElement;
        if (!wrapper) {
            return;
        }

        let error = wrapper.querySelector('[data-validate-error]');
        if (!error) {
            error = document.createElement('p');
            error.dataset.validateError = '1';
            error.className = 'mt-1 text-xs text-red-500';
            wrapper.appendChild(error);
        }

        error.textContent = message;
    });
}
