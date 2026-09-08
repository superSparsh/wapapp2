/**
 * Campaigns – progressive enhancements.
 * Vanilla JS, no dependencies. All features degrade gracefully if JS is disabled
 * because forms/buttons already work via standard HTML submission.
 */
(function () {
  'use strict';

  /* ------------------------------------------------------------------ */
  /*  Helpers                                                            */
  /* ------------------------------------------------------------------ */

  function csrfToken() {
    var meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : '';
  }

  function ajax(method, url, body, callback) {
    var xhr = new XMLHttpRequest();
    xhr.open(method, url, true);
    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
    xhr.setRequestHeader('Accept', 'application/json');
    xhr.setRequestHeader('X-CSRF-TOKEN', csrfToken());
    if (body && typeof body === 'string') {
      xhr.setRequestHeader('Content-Type', 'application/json');
    }
    xhr.onreadystatechange = function () {
      if (xhr.readyState === 4) {
        callback(xhr);
      }
    };
    xhr.send(body || null);
  }

  /* ------------------------------------------------------------------ */
  /*  1. Toggle switch via AJAX                                          */
  /* ------------------------------------------------------------------ */

  function initToggleSwitches() {
    var toggleForms = document.querySelectorAll('[data-campaign-toggle]');
    toggleForms.forEach(function (form) {
      form.addEventListener('submit', function (e) {
        e.preventDefault();
        var btn = form.querySelector('button[type="submit"]');
        if (btn) btn.disabled = true;

        ajax('PATCH', form.action, null, function (xhr) {
          if (xhr.status >= 200 && xhr.status < 300) {
            var resp;
            try { resp = JSON.parse(xhr.responseText); } catch (_) { resp = null; }
            if (resp && typeof resp.active !== 'undefined') {
              var toggle = form.querySelector('[role="switch"]');
              if (toggle) {
                var isActive = resp.active;
                toggle.setAttribute('aria-checked', isActive ? 'true' : 'false');
                toggle.className = toggle.className
                  .replace(/bg-green-500/g, isActive ? 'bg-green-500' : 'bg-green-50')
                  .replace(/bg-green-50/g, isActive ? 'bg-green-500' : 'bg-green-50');
                var knob = toggle.querySelector('span');
                if (knob) {
                  knob.className = knob.className
                    .replace(/left-\[24px\]/g, isActive ? 'left-[24px]' : 'left-[2px]')
                    .replace(/left-\[2px\]/g, isActive ? 'left-[24px]' : 'left-[2px]');
                }
              }
            }
          } else {
            window.location.reload();
          }
          if (btn) btn.disabled = false;
        });
      });
    });
  }

  /* ------------------------------------------------------------------ */
  /*  2. Search debounce                                                 */
  /* ------------------------------------------------------------------ */

  function initSearchDebounce() {
    var searchInputs = document.querySelectorAll('[data-campaign-search]');
    searchInputs.forEach(function (input) {
      if (input.dataset.campaignSearchBound) return;
      input.dataset.campaignSearchBound = '1';

      var timer;
      input.addEventListener('input', function () {
        clearTimeout(timer);
        timer = setTimeout(function () {
          var form = input.closest('form');
          if (form) form.submit();
        }, 300);
      });
    });
  }

  /* ------------------------------------------------------------------ */
  /*  Init                                                               */
  /* ------------------------------------------------------------------ */

  function init() {
    initToggleSwitches();
    initSearchDebounce();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
