@php
  $selectedRaw = old('audience_ids', $wizardData['audience_ids'] ?? ($wizardData['audience_id'] ?? null ? [(int)$wizardData['audience_id']] : []));
  $selectedIds = collect(is_array($selectedRaw) ? $selectedRaw : [$selectedRaw])
      ->map(fn ($v) => (string) $v)
      ->filter(fn ($v) => $v !== '')
      ->all();
  $singleAudienceId = (string) old('audience_id', $wizardData['audience_id'] ?? '');
@endphp

<x-campaigns.create-layout
  :step="2"
  :campaign-name="$wizardData['name'] ?? 'New Campaign'"
  previous-route="{{ route('campaigns.create.step', 1) }}"
  next-route="{{ route('campaigns.create.save', 2) }}"
>
  <form id="campaign-wizard-form" method="POST" action="{{ route('campaigns.create.save', 2) }}" data-validate-form class="flex w-full max-w-[720px] flex-col gap-6">
    @csrf

    <div class="flex flex-col gap-3">
      <div class="flex flex-col gap-1">
        <label class="text-base font-semibold leading-[1.4] text-text-primary" for="audience-search-input">
          Select Audience Lists <x-form.required />
        </label>
        <p class="text-xs font-normal leading-[1.5] text-text-muted">
          Select one or more lists to receive this campaign. Duplicate contacts across lists will be automatically merged so each phone number receives only one message.
        </p>
      </div>

      {{-- Fallback single-select hidden input for backward compatibility --}}
      <input type="hidden" name="audience_id" id="primary-audience-id" value="{{ old('audience_id', $wizardData['audience_id'] ?? '') }}">

      {{-- Search & Selection Actions --}}
      <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <div class="relative flex-1">
          <input
            id="audience-search-input"
            type="text"
            placeholder="Search audience lists..."
            autocomplete="off"
            class="w-full rounded-xl border border-border bg-elevated py-2.5 pl-3.5 pr-8 text-sm font-medium text-text-primary placeholder:text-text-muted outline-none transition focus:border-green-500"
          >
        </div>

        <div class="flex items-center gap-2">
          <button
            type="button"
            id="btn-select-all-audiences"
            class="rounded-lg border border-border bg-elevated px-3 py-2 text-xs font-semibold text-text-primary transition hover:border-green-500 hover:text-green-600"
          >
            Select All
          </button>
          <button
            type="button"
            id="btn-deselect-all-audiences"
            class="rounded-lg border border-border bg-elevated px-3 py-2 text-xs font-semibold text-text-muted transition hover:border-border-dark hover:text-text-primary"
          >
            Clear All
          </button>
        </div>
      </div>

      {{-- Checkbox List Container --}}
      <div
        data-validate-checkbox-group
        class="flex flex-col gap-2.5"
      >
        <div id="audience-cards-container" class="flex max-h-[380px] flex-col gap-2.5 overflow-y-auto pr-1">
          @forelse ($audiences ?? [] as $list)
            @php
              $isChecked = in_array((string) $list->uuid, $selectedIds, true)
                || in_array((string) $list->id, $selectedIds, true)
                || $singleAudienceId === (string) $list->uuid
                || $singleAudienceId === (string) $list->id;
              $contactsCount = (int) ($list->subscribed_contacts_count ?? 0);
            @endphp
            <label
              class="audience-card flex cursor-pointer items-center justify-between gap-3.5 rounded-xl border p-3.5 transition-colors {{ $isChecked ? 'border-green-500 bg-[rgba(34,197,94,0.06)]' : 'border-border bg-elevated hover:border-green-200' }}"
              data-audience-card
              data-name="{{ strtolower($list->name) }}"
            >
              <div class="flex min-w-0 items-center gap-3">
                <input
                  type="checkbox"
                  name="audience_ids[]"
                  value="{{ $list->uuid }}"
                  data-list-id="{{ $list->id }}"
                  data-contacts="{{ $contactsCount }}"
                  class="audience-checkbox size-4 rounded border-border text-green-500 focus:ring-green-500"
                  @checked($isChecked)
                >
                <div class="min-w-0 flex-1">
                  <p class="truncate text-sm font-semibold leading-[1.4] text-text-primary">
                    {{ $list->name }}
                  </p>
                  <p class="text-xs text-text-muted">
                    Created {{ $list->created_at?->format('d M Y') ?? 'N/A' }}
                  </p>
                </div>
              </div>

              <div class="flex shrink-0 items-center gap-2">
                <span class="inline-flex items-center rounded-full bg-surface px-2.5 py-1 text-xs font-semibold text-text-primary">
                  {{ number_format($contactsCount) }} contacts
                </span>
              </div>
            </label>
          @empty
            <div class="rounded-xl border border-dashed border-border bg-elevated p-8 text-center">
              <p class="text-sm font-medium text-text-muted">No audience lists found.</p>
              <p class="mt-1 text-xs text-text-subtle">Create an audience list before launching a campaign.</p>
            </div>
          @endforelse

          <div id="no-search-results" class="hidden rounded-xl border border-dashed border-border bg-elevated p-6 text-center">
            <p class="text-sm font-medium text-text-muted">No lists match your search.</p>
          </div>
        </div>

        <p data-validate-checkbox-error class="hidden text-xs font-medium text-red-500">
          Please select at least one audience list.
        </p>
        @error('audience_id') <p class="text-xs font-medium text-red-500">{{ $message }}</p> @enderror
        @error('audience_ids') <p class="text-xs font-medium text-red-500">{{ $message }}</p> @enderror
      </div>

      {{-- Selected Summary Strip --}}
      <div id="audience-selection-summary" class="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-border bg-surface px-3.5 py-2.5 text-xs">
        <div class="flex items-center gap-2">
          <span class="font-semibold text-text-primary">Selected:</span>
          <span id="summary-count" class="font-bold text-green-600">0 lists</span>
          <span class="text-text-subtle">•</span>
          <span id="summary-contacts" class="text-text-muted">0 total contacts</span>
        </div>

        <div class="flex items-center gap-1.5 text-text-muted">
          <img src="{{ asset('images/campaigns/create/info-circle.svg') }}" alt="" class="size-3.5 shrink-0 opacity-70" width="14" height="14">
          <span>Duplicates auto-deduped</span>
        </div>
      </div>
    </div>

    <x-campaigns.policy-confirmation
      :checked="(bool) old('policy_confirmed', $wizardData['policy_confirmed'] ?? false)"
    />
  </form>

  @push('scripts')
  <script>
  (function () {
    'use strict';

    var container = document.getElementById('audience-cards-container');
    var searchInput = document.getElementById('audience-search-input');
    var noResultsEl = document.getElementById('no-search-results');
    var selectAllBtn = document.getElementById('btn-select-all-audiences');
    var deselectAllBtn = document.getElementById('btn-deselect-all-audiences');
    var primaryAudienceInput = document.getElementById('primary-audience-id');
    var summaryCountEl = document.getElementById('summary-count');
    var summaryContactsEl = document.getElementById('summary-contacts');
    var checkboxGroup = document.querySelector('[data-validate-checkbox-group]');
    var errorEl = checkboxGroup ? checkboxGroup.querySelector('[data-validate-checkbox-error]') : null;

    function getCards() {
      return container ? Array.from(container.querySelectorAll('[data-audience-card]')) : [];
    }

    function getCheckboxes() {
      return container ? Array.from(container.querySelectorAll('.audience-checkbox')) : [];
    }

    function updateCardVisual(checkbox) {
      var card = checkbox.closest('[data-audience-card]');
      if (!card) return;
      if (checkbox.checked) {
        card.classList.remove('border-border', 'bg-elevated', 'hover:border-green-200');
        card.classList.add('border-green-500', 'bg-[rgba(34,197,94,0.06)]');
      } else {
        card.classList.remove('border-green-500', 'bg-[rgba(34,197,94,0.06)]');
        card.classList.add('border-border', 'bg-elevated', 'hover:border-green-200');
      }
    }

    function updateSummary() {
      var checkboxes = getCheckboxes();
      var checked = checkboxes.filter(function (cb) { return cb.checked; });
      var totalContacts = 0;

      checked.forEach(function (cb) {
        totalContacts += parseInt(cb.dataset.contacts || '0', 10);
      });

      if (summaryCountEl) {
        summaryCountEl.textContent = checked.length + (checked.length === 1 ? ' list' : ' lists');
      }
      if (summaryContactsEl) {
        summaryContactsEl.textContent = '~' + totalContacts.toLocaleString() + ' contacts';
      }

      if (primaryAudienceInput) {
        primaryAudienceInput.value = checked.length > 0 ? checked[0].value : '';
      }

      if (errorEl && checked.length > 0) {
        errorEl.classList.add('hidden');
      }
    }

    if (container) {
      container.addEventListener('change', function (e) {
        if (e.target.classList.contains('audience-checkbox')) {
          updateCardVisual(e.target);
          updateSummary();
        }
      });
    }

    if (searchInput) {
      searchInput.addEventListener('input', function () {
        var query = (searchInput.value || '').trim().toLowerCase();
        var cards = getCards();
        var visibleCount = 0;

        cards.forEach(function (card) {
          var name = card.dataset.name || '';
          if (query === '' || name.indexOf(query) !== -1) {
            card.classList.remove('hidden');
            visibleCount++;
          } else {
            card.classList.add('hidden');
          }
        });

        if (noResultsEl) {
          if (visibleCount === 0 && cards.length > 0) {
            noResultsEl.classList.remove('hidden');
          } else {
            noResultsEl.classList.add('hidden');
          }
        }
      });
    }

    if (selectAllBtn) {
      selectAllBtn.addEventListener('click', function () {
        getCards().forEach(function (card) {
          if (!card.classList.contains('hidden')) {
            var cb = card.querySelector('.audience-checkbox');
            if (cb && !cb.checked) {
              cb.checked = true;
              updateCardVisual(cb);
            }
          }
        });
        updateSummary();
      });
    }

    if (deselectAllBtn) {
      deselectAllBtn.addEventListener('click', function () {
        getCheckboxes().forEach(function (cb) {
          cb.checked = false;
          updateCardVisual(cb);
        });
        updateSummary();
      });
    }

    // Initial sync
    getCheckboxes().forEach(updateCardVisual);
    updateSummary();
  })();
  </script>
  @endpush
</x-campaigns.create-layout>
