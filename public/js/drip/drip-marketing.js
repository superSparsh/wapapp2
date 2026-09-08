/**
 * Drip Marketing – progressive enhancements.
 * Maximize is handled globally in resources/js/app.js (initDripCanvasMaximize).
 */
(function () {
  'use strict';

  function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
  }

  function ajax(method, url, body) {
    return fetch(url, {
      method,
      headers: {
        'X-Requested-With': 'XMLHttpRequest',
        Accept: 'application/json',
        'X-CSRF-TOKEN': csrfToken(),
        ...(body ? { 'Content-Type': 'application/json' } : {}),
      },
      credentials: 'same-origin',
      body: body || null,
    });
  }

  function confirmDelete(message, title) {
    if (typeof window.showAppConfirm === 'function') {
      return window.showAppConfirm(message, title || 'Delete campaign', 'Delete', 'danger');
    }

    return Promise.resolve(window.confirm(message));
  }

  function initToggleSwitches() {
    document.querySelectorAll('[data-drip-toggle]').forEach((form) => {
      if (form.dataset.dripToggleBound) {
        return;
      }
      form.dataset.dripToggleBound = '1';

      form.addEventListener('submit', (event) => {
        event.preventDefault();
        const button = form.querySelector('button[type="submit"]');
        if (button) {
          button.disabled = true;
        }

        ajax('PATCH', form.action)
          .then((response) => {
            if (!response.ok) {
              throw new Error('toggle failed');
            }
            return response.json();
          })
          .then((payload) => {
            if (typeof payload.active === 'undefined') {
              return;
            }

            const toggle = form.querySelector('[role="switch"]');
            if (toggle) {
              toggle.setAttribute('aria-checked', payload.active ? 'true' : 'false');
              toggle.classList.toggle('bg-green-500', payload.active);
              toggle.classList.toggle('bg-green-50', !payload.active);
              const knob = toggle.querySelector('span');
              if (knob) {
                knob.classList.toggle('left-[24px]', payload.active);
                knob.classList.toggle('left-[2px]', !payload.active);
              }
            }

            const row = form.closest('tr');
            const badge = row?.querySelector('[data-drip-status]');
            if (badge) {
              badge.textContent = payload.active ? 'Running' : 'Paused';
              badge.className = payload.active
                ? 'inline-flex items-center justify-center rounded bg-[rgba(0,128,0,0.1)] px-2 py-1 text-[10px] font-medium leading-[1.2] whitespace-nowrap text-[green]'
                : 'inline-flex items-center justify-center rounded bg-[rgba(0,0,0,0.1)] px-2 py-1 text-[10px] font-medium leading-[1.2] whitespace-nowrap text-text-muted';
            }
          })
          .catch(() => window.location.reload())
          .finally(() => {
            if (button) {
              button.disabled = false;
            }
          });
      });
    });
  }

  function initDeleteButtons() {
    document.querySelectorAll('[data-drip-delete]').forEach((form) => {
      if (form.dataset.dripDeleteBound) {
        return;
      }
      form.dataset.dripDeleteBound = '1';

      form.addEventListener('submit', (event) => {
        event.preventDefault();

        const message = form.dataset.confirm || 'Are you sure you want to delete this campaign? This action cannot be undone.';
        const title = form.dataset.confirmTitle || 'Delete campaign';

        confirmDelete(message, title).then((confirmed) => {
          if (!confirmed) {
            return;
          }

          const button = form.querySelector('button[type="submit"]');
          if (button) {
            button.disabled = true;
          }

          ajax('DELETE', form.action)
            .then((response) => {
              if (!response.ok) {
                throw new Error('delete failed');
              }

              const row = form.closest('tr');
              if (row) {
                row.style.transition = 'opacity 0.3s';
                row.style.opacity = '0';
                window.setTimeout(() => row.remove(), 300);
                return;
              }

              window.location.href = form.dataset.dripRedirect || '/automation/drip';
            })
            .catch(() => form.submit())
            .finally(() => {
              if (button) {
                button.disabled = false;
              }
            });
        });
      });
    });
  }

  function initSearchDebounce() {
    document.querySelectorAll('[data-drip-search]').forEach((input) => {
      if (input.dataset.dripSearchBound) {
        return;
      }
      input.dataset.dripSearchBound = '1';

      let timer;
      input.addEventListener('input', () => {
        window.clearTimeout(timer);
        timer = window.setTimeout(() => {
          input.closest('form')?.submit();
        }, 300);
      });
    });
  }

  function initTriggerModal() {
    const modal = document.querySelector('[data-modal="trigger-confirm"]');
    const triggerForm = document.querySelector('[data-trigger-form]');
    if (!modal || !triggerForm) {
      return;
    }

    const phoneInput = triggerForm.querySelector('input[name="phone"]');
    const confirmBtn = modal.querySelector('[data-trigger-confirm]');
    const cancelBtns = modal.querySelectorAll('[data-modal-close], [data-trigger-cancel]');

    document.querySelectorAll('[data-open-modal="trigger-confirm"]').forEach((button) => {
      button.addEventListener('click', () => {
        const row = button.closest('[data-contact-phone]');
        const phone = row?.dataset.contactPhone || '';
        if (phoneInput) {
          phoneInput.value = phone;
        }
        modal.classList.remove('hidden');
        modal.classList.add('flex');
      });
    });

    cancelBtns.forEach((button) => {
      button.addEventListener('click', () => {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
      });
    });

    confirmBtn?.addEventListener('click', () => {
      triggerForm.submit();
    });
  }

  function initDripTriggerSelect() {
    document.querySelectorAll('[data-drip-trigger-select]').forEach((root) => {
      if (root.dataset.dripTriggerBound) {
        return;
      }
      root.dataset.dripTriggerBound = '1';

      const select = root.querySelector('[data-drip-trigger-type]');
      const catalogEl = root.querySelector('[data-drip-trigger-catalog]');
      if (!select || !catalogEl) {
        return;
      }

      let catalog = {};
      try {
        catalog = JSON.parse(catalogEl.textContent || '{}');
      } catch {
        catalog = {};
      }

      const titleEl = root.querySelector('[data-drip-trigger-title]');
      const descriptionEl = root.querySelector('[data-drip-trigger-description]');
      const introEl = root.querySelector('[data-drip-trigger-intro]');
      const panels = root.querySelectorAll('[data-drip-trigger-panel]');
      const flowEditor = document.querySelector('[data-drip-flow-editor]');

      const sync = () => {
        const type = select.value;
        const meta = catalog[type] || {};

        if (titleEl) {
          titleEl.textContent = meta.label || 'Automation trigger';
        }
        if (descriptionEl) {
          descriptionEl.textContent = meta.description || '';
        }
        if (introEl) {
          introEl.textContent = meta.intro || '';
        }

        panels.forEach((panel) => {
          const active = panel.getAttribute('data-drip-trigger-panel') === type;
          panel.classList.toggle('hidden', !active);
          panel.querySelectorAll('input, select, textarea').forEach((input) => {
            if (active) {
              if (input.dataset.dripWasRequired === '1') {
                input.setAttribute('required', '');
              }
            } else if (input.hasAttribute('required')) {
              input.dataset.dripWasRequired = '1';
              input.removeAttribute('required');
            }
          });
        });

        if (flowEditor && meta.tree) {
          flowEditor.dataset.triggerLabel = meta.tree;
          const triggerNode = flowEditor.querySelector('[data-drip-trigger-node] span');
          if (triggerNode) {
            triggerNode.textContent = meta.tree;
          }
        }
      };

      select.addEventListener('change', sync);
      sync();
    });
  }

  function initTimezoneFilter() {
    const searchInput = document.querySelector('[data-tz-search]');
    const list = document.querySelector('[data-tz-list]');
    if (!searchInput || !list) {
      return;
    }

    searchInput.addEventListener('input', () => {
      const query = searchInput.value.toLowerCase();
      list.querySelectorAll('[data-tz-option]').forEach((option) => {
        const visible = option.textContent.toLowerCase().includes(query);
        option.hidden = !visible;
      });
    });
  }

  function showFieldError(input, message) {
    if (!input) {
      return;
    }
    input.classList.add('border-red-500');
    let error = input.parentElement?.querySelector('[data-drip-field-error]');
    if (!error) {
      error = document.createElement('p');
      error.dataset.dripFieldError = '1';
      error.className = 'text-xs text-red-500';
      input.parentElement?.appendChild(error);
    }
    error.textContent = message;
  }

  function clearFieldError(input) {
    if (!input) {
      return;
    }
    input.classList.remove('border-red-500');
    const error = input.parentElement?.querySelector('[data-drip-field-error]');
    if (error) {
      error.remove();
    }
  }

  function validateCheckboxGroup(group) {
    const checked = group.querySelectorAll('[data-drip-trigger-checkbox]:checked');
    const errorEl = group.querySelector('[data-drip-trigger-checkbox-error]');
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

  function initDripSettingsValidation() {
    document.querySelectorAll('[data-drip-settings-form]').forEach((form) => {
      if (form.dataset.dripSettingsBound) {
        return;
      }
      form.dataset.dripSettingsBound = '1';

      form.addEventListener('submit', (event) => {
        let valid = true;

        form.querySelectorAll('input, select, textarea').forEach(clearFieldError);

        form.querySelectorAll('[data-drip-trigger-checkbox-group]').forEach((group) => {
          if (!group.closest('[data-drip-trigger-panel]')?.classList.contains('hidden')) {
            if (!validateCheckboxGroup(group)) {
              valid = false;
            }
          }
        });

        form.querySelectorAll('input[required], select[required], textarea[required]').forEach((input) => {
          if (input.closest('[data-drip-trigger-panel]')?.classList.contains('hidden')) {
            return;
          }
          if (input.type === 'checkbox') {
            return;
          }
          if (!String(input.value || '').trim()) {
            valid = false;
            showFieldError(input, 'This field is required.');
          }
        });

        const startDate = form.querySelector('[name="start_date"]');
        const endDate = form.querySelector('[name="end_date"]');
        if (startDate?.value && endDate?.value && endDate.value < startDate.value) {
          valid = false;
          showFieldError(endDate, 'End date must be on or after the start date.');
        }

        if (!valid) {
          event.preventDefault();
          const firstInvalid = form.querySelector('.border-red-500');
          firstInvalid?.focus();
        }
      });
    });
  }

  function initDripTriggerInfoTip() {
    document.querySelectorAll('[data-drip-trigger-info-wrap]').forEach((wrap) => {
      if (wrap.dataset.dripTriggerInfoBound) {
        return;
      }
      wrap.dataset.dripTriggerInfoBound = '1';

      const button = wrap.querySelector('[data-drip-trigger-info-btn]');
      const panel = wrap.querySelector('[data-drip-trigger-info-panel]');
      if (!button || !panel) {
        return;
      }

      const close = () => {
        panel.classList.add('hidden');
        button.setAttribute('aria-expanded', 'false');
      };

      const toggle = () => {
        const open = panel.classList.contains('hidden');
        document.querySelectorAll('[data-drip-trigger-info-panel]').forEach((other) => {
          if (other !== panel) {
            other.classList.add('hidden');
          }
        });
        panel.classList.toggle('hidden', !open);
        button.setAttribute('aria-expanded', open ? 'true' : 'false');
      };

      button.addEventListener('click', (event) => {
        event.preventDefault();
        event.stopPropagation();
        toggle();
      });

      document.addEventListener('click', (event) => {
        if (!wrap.contains(event.target)) {
          close();
        }
      });

      document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
          close();
        }
      });
    });
  }

  function initDripSort() {
    document.querySelectorAll('[data-drip-sort]').forEach((select) => {
      if (select.dataset.dripSortBound) {
        return;
      }
      select.dataset.dripSortBound = '1';

      select.addEventListener('change', () => {
        const directionInput = select.form?.querySelector('[data-drip-sort-direction]');
        if (directionInput) {
          directionInput.value = select.value === 'name' ? 'asc' : 'desc';
        }
      });
    });
  }

  function init() {
    initToggleSwitches();
    initDeleteButtons();
    initSearchDebounce();
    initTriggerModal();
    initDripTriggerSelect();
    initTimezoneFilter();
    initDripSettingsValidation();
    initDripTriggerInfoTip();
    initDripSort();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
