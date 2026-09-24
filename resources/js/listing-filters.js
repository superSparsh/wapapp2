/**
 * Shared listing page filters: sort, enter-to-search, filter auto-submit.
 */

function submitListingForm(form) {
    if (!(form instanceof HTMLFormElement)) {
        return;
    }

    // Drop page so a sort/filter change always returns to results page 1.
    form.querySelectorAll('input[name="page"]').forEach((input) => input.remove());

    if (typeof form.requestSubmit === 'function') {
        try {
            form.requestSubmit();
            return;
        } catch {
            // Fall through — some environments reject requestSubmit without a submitter.
        }
    }

    const method = (form.getAttribute('method') || form.method || 'get').toLowerCase();
    if (method === 'get') {
        const action = form.getAttribute('action') || window.location.pathname;
        const url = new URL(action, window.location.origin);
        const params = new URLSearchParams(new FormData(form));

        // Keep URL tidy: omit empty filter values.
        Array.from(params.keys()).forEach((key) => {
            if (params.get(key) === '') {
                params.delete(key);
            }
        });

        url.search = params.toString();
        window.location.assign(url.toString());
        return;
    }

    form.submit();
}

export function initListingFilters() {
    document.querySelectorAll('[data-listing-sort]').forEach((select) => {
        if (!(select instanceof HTMLSelectElement) || select.dataset.listingSortBound) {
            return;
        }

        select.dataset.listingSortBound = '1';

        const current = select.dataset.listingSortCurrent;
        if (current && Array.from(select.options).some((option) => option.value === current)) {
            select.value = current;
        }

        select.addEventListener('change', () => {
            const form = select.closest('form');
            if (!form) {
                return;
            }

            const [sort, direction] = String(select.value).split(':');
            const sortInput = form.querySelector('[data-listing-sort-field]');
            const directionInput = form.querySelector('[data-listing-sort-direction]');

            if (sortInput && sort) {
                sortInput.value = sort;
            }

            if (directionInput && direction) {
                directionInput.value = direction;
            }

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
        if (input.dataset.listingSearchBound) {
            return;
        }

        input.dataset.listingSearchBound = '1';

        const form = input.closest('form');
        if (!form) {
            return;
        }

        input.addEventListener('keydown', (event) => {
            if (event.key !== 'Enter') {
                return;
            }

            event.preventDefault();
            submitListingForm(form);
        });

        const debounceMs = Number(input.dataset.listingSearchDebounce || 0);
        if (debounceMs > 0) {
            let timer = null;

            input.addEventListener('input', () => {
                window.clearTimeout(timer);
                timer = window.setTimeout(() => {
                    submitListingForm(form);
                }, debounceMs);
            });
        }
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
