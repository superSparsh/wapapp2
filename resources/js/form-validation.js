/**
 * Shared client-side form validation for WapApp.
 * Attach `data-validate-form` to forms; marks invalid fields and blocks submit.
 */
(function () {
  'use strict';

  function showFieldError(input, message) {
    if (!input) {
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
  }

  function clearFieldError(input) {
    if (!input) {
      return;
    }

    input.classList.remove('border-red-500');
    input.removeAttribute('aria-invalid');

    const wrapper = input.closest('[data-validate-field]') || input.parentElement;
    const error = wrapper?.querySelector('[data-validate-error]');
    if (error) {
      error.remove();
    }
  }

  function validateCheckboxGroup(group) {
    const checked = group.querySelectorAll('input[type="checkbox"]:checked');
    const errorEl = group.querySelector('[data-validate-checkbox-error]');

    if (checked.length > 0) {
      if (errorEl) {
        errorEl.classList.add('hidden');
      }
      return true;
    }

    if (errorEl) {
      errorEl.classList.remove('hidden');
    }

    return false;
  }

  function validateForm(form) {
    let valid = true;

    form.querySelectorAll('input, select, textarea').forEach(clearFieldError);

    form.querySelectorAll('[data-validate-checkbox-group]').forEach((group) => {
      if (!group.closest('[hidden]') && !group.classList.contains('hidden')) {
        if (!validateCheckboxGroup(group)) {
          valid = false;
        }
      }
    });

    form.querySelectorAll('input[required], select[required], textarea[required]').forEach((input) => {
      if (input.closest('[hidden]') || input.closest('.hidden')) {
        return;
      }

      if (input.type === 'checkbox' || input.type === 'radio') {
        return;
      }

      const value = String(input.value || '').trim();
      if (!value) {
        valid = false;
        showFieldError(input, input.dataset.validateMessage || 'This field is required.');
        return;
      }

      if (input.type === 'email' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
        valid = false;
        showFieldError(input, 'Enter a valid email address.');
      }

      if (input.type === 'url' && !/^https?:\/\/.+/i.test(value)) {
        valid = false;
        showFieldError(input, 'Enter a valid URL starting with http:// or https://');
      }
    });

    const startDate = form.querySelector('[name="start_date"]');
    const endDate = form.querySelector('[name="end_date"]');
    if (startDate?.value && endDate?.value && endDate.value < startDate.value) {
      valid = false;
      showFieldError(endDate, 'End date must be on or after the start date.');
    }

    if (!valid) {
      const firstInvalid = form.querySelector('.border-red-500, [aria-invalid="true"]');
      firstInvalid?.focus();
    }

    return valid;
  }

  function init() {
    document.querySelectorAll('[data-validate-form]').forEach((form) => {
      if (form.dataset.validateBound) {
        return;
      }
      form.dataset.validateBound = '1';

      form.addEventListener('submit', (event) => {
        if (!validateForm(form)) {
          event.preventDefault();
          event.stopPropagation();
        }
      });
    });
  }

  window.WapAppFormValidation = {
    validateForm: validateForm,
    showFieldError: showFieldError,
    clearFieldError: clearFieldError,
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
