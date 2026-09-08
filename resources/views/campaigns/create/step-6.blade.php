@php
  $savedSendMode = $wizardData['send_mode'] ?? 'schedule';
  $savedScheduledAt = $wizardData['scheduled_at'] ?? '';
  $hasVariables = is_array($wizardData['template_variables'] ?? null)
    && count($wizardData['template_variables']) > 0;
  $previousStep = $hasVariables ? 4 : 3;
@endphp

<x-campaigns.create-layout
  :step="6"
  :campaign-name="$wizardData['name'] ?? 'New Campaign'"
  :show-cancel="false"
  previous-route="{{ route('campaigns.create.step', $previousStep) }}"
  previous-label="Previous Step"
  next-route="{{ route('campaigns.store') }}"
  next-label="Send Campaign"
  secondary-open-modal="send-test-message"
  secondary-next-label="Send a test WhatsApp message"
>
  <x-slot:modals>
    @include('components.campaigns.send-test-message-modal', ['wizardData' => $wizardData])
  </x-slot:modals>

  <form id="campaign-wizard-form" method="POST" action="{{ route('campaigns.store') }}" data-validate-form class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_458px]">
    @csrf
    <input type="hidden" name="name" value="{{ $wizardData['name'] ?? '' }}">
    <input type="hidden" name="whatsapp_line_id" value="{{ $wizardData['whatsapp_line_id'] ?? '' }}">
    <input type="hidden" name="audience_id" value="{{ $wizardData['audience_id'] ?? '' }}">
    <input type="hidden" name="template_id" value="{{ $wizardData['template_id'] ?? '' }}">
    @if (! empty($wizardData['template_variables']))
      @foreach ((array) $wizardData['template_variables'] as $key => $val)
        <input type="hidden" name="template_variables[{{ $key }}]" value="{{ $val }}">
      @endforeach
    @endif

    <div class="flex flex-col gap-6">
      <div class="flex flex-col gap-3">
        <h2 class="text-xl font-semibold leading-[1.4] text-text-primary">Schedule</h2>

        <label
          class="flex cursor-pointer items-center gap-3 rounded-xl border p-4 transition-colors send-mode-option {{ $savedSendMode === 'schedule' ? 'border-green-500 bg-[rgba(34,197,94,0.06)]' : 'border-border bg-elevated hover:border-green-200' }}"
          data-send-mode-label="schedule"
        >
          <input type="radio" name="send_mode" value="schedule" class="sr-only" @checked($savedSendMode === 'schedule')>
          <img src="{{ asset('images/campaigns/create/radio-' . ($savedSendMode === 'schedule' ? 'checked' : 'unchecked') . '.svg') }}" alt="" class="size-4 shrink-0 radio-icon" width="16" height="16">
          <span class="text-base font-semibold leading-[1.5] send-mode-text {{ $savedSendMode === 'schedule' ? 'text-green-500' : 'text-text-muted' }}">Schedule</span>
        </label>

        <div id="schedule-date-wrapper" class="grid gap-3 pl-7 sm:grid-cols-2 {{ $savedSendMode === 'now' ? 'opacity-40 pointer-events-none' : '' }}">
          <div class="flex flex-col gap-2" data-validate-field>
            <label class="text-sm font-semibold leading-[1.4] text-text-primary" for="schedule-date">Select Date & Time</label>
            <input
              id="schedule-date"
              name="scheduled_at"
              type="datetime-local"
              value="{{ $savedScheduledAt }}"
              class="w-full rounded-xl border border-border bg-elevated p-3.5 text-sm font-medium leading-[1.4] text-text-muted outline-none focus:border-green-500"
            >
            @error('scheduled_at') <p class="text-xs text-red-500">{{ $message }}</p> @enderror
          </div>
        </div>

        <label
          class="flex cursor-pointer items-center gap-3 rounded-xl border p-4 transition-colors send-mode-option {{ $savedSendMode === 'now' ? 'border-green-500 bg-[rgba(34,197,94,0.06)]' : 'border-border bg-elevated hover:border-green-200' }}"
          data-send-mode-label="now"
        >
          <input type="radio" name="send_mode" value="now" class="sr-only" @checked($savedSendMode === 'now')>
          <img src="{{ asset('images/campaigns/create/radio-' . ($savedSendMode === 'now' ? 'checked' : 'unchecked') . '.svg') }}" alt="" class="size-4 shrink-0 radio-icon" width="16" height="16">
          <span class="text-base font-semibold leading-[1.5] send-mode-text {{ $savedSendMode === 'now' ? 'text-green-500' : 'text-text-muted' }}">Send now</span>
        </label>
      </div>

      <p id="schedule-error" class="hidden text-sm font-medium text-red-500"></p>
      @error('send_mode') <p class="text-sm font-medium text-red-500">{{ $message }}</p> @enderror
      @error('audience_id') <p class="text-sm font-medium text-red-500">{{ $message }}</p> @enderror
      @error('whatsapp_line_id') <p class="text-sm font-medium text-red-500">{{ $message }}</p> @enderror
      @error('template_id') <p class="text-sm font-medium text-red-500">{{ $message }}</p> @enderror

      <div class="flex flex-col gap-6">
        <h2 class="text-xl font-semibold leading-[1.4] text-text-primary">You're all set to send</h2>

        <div class="flex flex-col gap-3">
          @foreach ([
            ['status-up.svg', 'Campaign Name', $wizardData['name'] ?? 'N/A', route('campaigns.create.step', 1)],
            ['task.svg', 'Audience', 'Selected in step 2', route('campaigns.create.step', 2)],
            ['element-4.svg', 'Template', 'Selected in step 3', route('campaigns.create.step', 3)],
          ] as [$icon, $title, $value, $editRoute])
            <div class="flex items-center gap-4">
              <img src="{{ asset('images/campaigns/create/' . $icon) }}" alt="" class="size-6 shrink-0" width="24" height="24">
              <div class="min-w-0 flex-1">
                <p class="text-base font-semibold leading-[1.5] text-text-primary">{{ $title }}</p>
                <p class="text-sm font-normal leading-[1.4] text-[#878787]">{{ $value }}</p>
              </div>
              <a href="{{ $editRoute }}" class="fd-btn inline-flex w-20 shrink-0 items-center justify-center rounded border border-border-light bg-elevated p-2.5 text-xs font-semibold text-primary-2 transition-colors hover:bg-surface">
                Edit
              </a>
            </div>
            @if (! $loop->last)
              <div class="h-px w-full bg-border"></div>
            @endif
          @endforeach
        </div>
      </div>
    </div>

    <x-templates.phone-preview
      title="Message Preview"
      subtitle="Template preview message look like"
      size="compact"
    >
      <x-templates.message-preview-bubble :preview-data="$previewData" size="compact" scroll-body />
    </x-templates.phone-preview>
  </form>

  @push('scripts')
  <script>
  (function () {
    'use strict';

    var checkedIcon = '{{ asset('images/campaigns/create/radio-checked.svg') }}';
    var uncheckedIcon = '{{ asset('images/campaigns/create/radio-unchecked.svg') }}';
    var form = document.getElementById('campaign-wizard-form');
    var errorEl = document.getElementById('schedule-error');

    function syncRadios() {
      if (!form) return;
      var radios = form.querySelectorAll('input[name="send_mode"]');
      var dateWrapper = document.getElementById('schedule-date-wrapper');

      radios.forEach(function (radio) {
        var label = radio.closest('label');
        var icon = label ? label.querySelector('.radio-icon') : null;
        var text = label ? label.querySelector('.send-mode-text') : null;

        if (icon) {
          icon.src = radio.checked ? checkedIcon : uncheckedIcon;
        }

        if (label) {
          if (radio.checked) {
            label.classList.remove('border-border', 'bg-elevated', 'hover:border-green-200');
            label.classList.add('border-green-500', 'bg-[rgba(34,197,94,0.06)]');
          } else {
            label.classList.remove('border-green-500', 'bg-[rgba(34,197,94,0.06)]');
            label.classList.add('border-border', 'bg-elevated', 'hover:border-green-200');
          }
        }

        if (text) {
          if (radio.checked) {
            text.classList.remove('text-text-muted');
            text.classList.add('text-green-500');
          } else {
            text.classList.remove('text-green-500');
            text.classList.add('text-text-muted');
          }
        }
      });

      if (dateWrapper) {
        var scheduleRadio = form.querySelector('input[name="send_mode"][value="schedule"]');
        var dateInput = form.querySelector('[name="scheduled_at"]');
        if (scheduleRadio && scheduleRadio.checked) {
          dateWrapper.classList.remove('opacity-40', 'pointer-events-none');
          if (dateInput) dateInput.setAttribute('required', 'required');
        } else {
          dateWrapper.classList.add('opacity-40', 'pointer-events-none');
          if (dateInput) dateInput.removeAttribute('required');
        }
      }
    }

    if (form) {
      form.addEventListener('change', function (e) {
        if (e.target.name === 'send_mode') {
          syncRadios();
          if (errorEl) errorEl.classList.add('hidden');
        }
      });

      form.addEventListener('submit', function (e) {
        var selected = form.querySelector('input[name="send_mode"]:checked');
        var mode = selected ? selected.value : 'schedule';

        if (mode === 'schedule') {
          var dateInput = form.querySelector('[name="scheduled_at"]');
          if (!dateInput || !dateInput.value) {
            e.preventDefault();
            e.stopPropagation();
            if (errorEl) {
              errorEl.textContent = 'Please select a date and time for scheduling.';
              errorEl.classList.remove('hidden');
            }
            if (dateInput) dateInput.focus();
            return false;
          }
          if (dateInput.value && new Date(dateInput.value).getTime() <= Date.now()) {
            e.preventDefault();
            e.stopPropagation();
            if (errorEl) {
              errorEl.textContent = 'Scheduled time must be in the future.';
              errorEl.classList.remove('hidden');
            }
            dateInput.focus();
            return false;
          }
        }
      });

      syncRadios();
    }

    // Test message modal submit
    var testForm = document.querySelector('[data-campaign-test-message-form]');
    var modal = document.getElementById('modal-send-test-message');
    if (testForm && modal) {
      var setBusy = function (busy) {
        var submitBtn = modal.querySelector('[data-test-message-submit]');
        var label = modal.querySelector('[data-test-message-submit-label]');
        var icon = modal.querySelector('[data-test-message-send-icon]');
        var spinner = modal.querySelector('[data-test-message-spinner]');
        if (submitBtn) {
          submitBtn.disabled = !!busy;
          submitBtn.setAttribute('aria-busy', busy ? 'true' : 'false');
        }
        if (label) label.textContent = busy ? 'Sending…' : 'Send';
        if (icon) icon.classList.toggle('hidden', !!busy);
        if (spinner) spinner.classList.toggle('hidden', !busy);
      };

      var setStatus = function (type, message) {
        var statusBox = modal.querySelector('[data-test-message-status]');
        if (!statusBox) return;
        statusBox.classList.remove('hidden', 'text-red-500', 'text-green-600');
        if (!message) {
          statusBox.classList.add('hidden');
          statusBox.textContent = '';
          return;
        }
        statusBox.textContent = message;
        statusBox.classList.add(type === 'success' ? 'text-green-600' : 'text-red-500');
      };

      var notify = function (type, message) {
        if (typeof window.showAppToast === 'function') {
          window.showAppToast({ type: type, message: message, title: type === 'success' ? 'Test message sent' : 'Test message failed' });
          return;
        }
        setStatus(type, message);
      };

      var extractError = function (payload, fallback) {
        if (!payload || typeof payload !== 'object') return fallback;
        if (typeof payload.message === 'string' && payload.message.trim() !== '') {
          // Prefer first validation error when present (more specific than summary).
          var firstError = Object.values(payload.errors || {}).flat()[0];
          if (typeof firstError === 'string' && firstError.trim() !== '' && payload.message === 'The given data was invalid.') {
            return firstError;
          }
          return payload.message;
        }
        var validationError = Object.values(payload.errors || {}).flat()[0];
        if (typeof validationError === 'string' && validationError.trim() !== '') {
          return validationError;
        }
        return fallback;
      };

      testForm.addEventListener('submit', async function (event) {
        event.preventDefault();
        var url = modal.dataset.testMessageUrl;
        if (!url) return;

        setStatus(null, '');
        setBusy(true);

        var formData = new FormData(testForm);

        try {
          var response = await fetch(url, {
            method: 'POST',
            headers: {
              Accept: 'application/json',
              'X-Requested-With': 'XMLHttpRequest',
              'X-CSRF-TOKEN': formData.get('_token') || document.querySelector('meta[name="csrf-token"]')?.content || '',
            },
            credentials: 'same-origin',
            body: formData,
          });
          var payload = await response.json().catch(function () { return {}; });

          if (!response.ok || !payload.success) {
            var errorMessage = extractError(payload, 'Unable to send test message. Please try again.');
            setStatus('error', errorMessage);
            notify('error', errorMessage);
            return;
          }

          var successMessage = payload.message || 'Test WhatsApp message sent successfully.';
          setStatus('success', successMessage);
          notify('success', successMessage);
        } catch (err) {
          var networkError = 'Unable to send test message. Check your connection and try again.';
          setStatus('error', networkError);
          notify('error', networkError);
        } finally {
          setBusy(false);
        }
      });
    }
  })();
  </script>
  @endpush
</x-campaigns.create-layout>
