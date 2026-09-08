/**
 * Shared listing page filters: sort, enter-to-search, filter auto-submit.
 */
export function initListingFilters() {
    document.querySelectorAll('[data-listing-sort]').forEach((select) => {
        if (select.dataset.listingSortBound) {
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

            form.requestSubmit();
        });
    });

    document.querySelectorAll('[data-listing-filter]').forEach((control) => {
        if (control.dataset.listingFilterBound) {
            return;
        }

        control.dataset.listingFilterBound = '1';

        control.addEventListener('change', () => {
            control.closest('form')?.requestSubmit();
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
            form.requestSubmit();
        });
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
