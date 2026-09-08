@php
    $filters = [
        ['label' => 'All', 'active' => true],
        ['label' => 'Active', 'active' => false],
        ['label' => 'Canceled', 'active' => false],
    ];

    $events = [
        'Active', 'Active', 'Active',
        'Active', 'Canceled', 'Canceled',
        'Canceled', 'Active', 'Canceled',
        'Active', 'Canceled', 'Active',
    ];
@endphp

<x-layouts.app title="Calendly Events - WapApp" active="integration.calendly">
  <div class="flex flex-col bg-surface">
    <div class="flex flex-wrap items-center gap-3 p-4">
      <h1 class="fd-page-title min-w-0 flex-1 text-2xl">Calendly</h1>
      <button
        type="button"
        class="fd-btn inline-flex shrink-0 items-center justify-center rounded border border-red-500 px-4 py-3 text-sm font-semibold leading-[1.5] text-red-500 transition-colors hover:bg-red-50"
      >
        Uninstall Plugin
      </button>
    </div>

    <section class="flex flex-col gap-6 p-4 pt-0">
      <x-integration.calendly-tabs active="events" />

      <div class="flex flex-col gap-4">
        <div class="flex flex-wrap items-center gap-4">
          <div class="flex min-w-0 flex-1 flex-wrap items-center gap-4">
            @foreach ($filters as $filter)
              <button type="button" class="inline-flex items-center gap-2">
                <img
                  src="{{ asset($filter['active'] ? 'images/integration/radio-checked.svg' : 'images/integration/radio-unchecked.svg') }}"
                  alt=""
                  class="size-4 shrink-0"
                  width="16"
                  height="16"
                >
                <span @class([
                  'text-sm font-medium leading-[1.4]',
                  'text-green-500' => $filter['active'],
                  'text-blue-200' => ! $filter['active'],
                ])>{{ $filter['label'] }}</span>
              </button>
            @endforeach
          </div>

          <button
            type="button"
            class="fd-btn inline-flex shrink-0 items-center justify-center gap-3 rounded border border-border-light bg-elevated p-3 text-sm font-semibold leading-[1.5] text-green-500 transition-colors hover:bg-surface"
          >
            <img src="{{ asset('images/integration/refresh.svg') }}" alt="" class="size-4 shrink-0" width="16" height="16">
            Sync Events
          </button>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
          @foreach ($events as $status)
            <article class="flex flex-col gap-4 rounded-lg border-[0.5px] border-border-light bg-elevated p-4 shadow-[0px_0px_1.5px_rgba(0,0,0,0.08)]">
              <div class="flex items-center gap-4">
                <p class="min-w-0 flex-1 text-sm font-semibold leading-[1.5] text-text-primary">29 Apr • 30 Minute Meeting</p>
                <span @class([
                  'inline-flex shrink-0 items-center justify-center rounded px-2 py-1.5 text-xs font-semibold leading-[1.5]',
                  'bg-green-50 text-green-500' => $status === 'Active',
                  'bg-[rgba(255,0,0,0.05)] text-red-500' => $status === 'Canceled',
                ])>{{ $status }}</span>
              </div>

              <div class="h-px w-full bg-divider"></div>

              <div class="flex items-center gap-2 text-sm leading-[1.4]">
                <span class="min-w-0 flex-1 text-text-muted">Time</span>
                <span class="shrink-0 text-base text-text-muted">:</span>
                <span class="min-w-0 flex-1 text-text-primary">09:30 AM - 10:00 AM</span>
              </div>

              <div class="flex items-center gap-2 text-sm leading-[1.4]">
                <span class="min-w-0 flex-1 text-text-muted">Invitee</span>
                <span class="shrink-0 text-base text-text-muted">:</span>
                <span class="min-w-0 flex-1 text-text-primary">NA</span>
              </div>

              <div class="flex items-center gap-2 text-sm leading-[1.4]">
                <span class="min-w-0 flex-1 text-text-muted">WhatsApp Number</span>
                <span class="shrink-0 text-base text-text-muted">:</span>
                <span class="min-w-0 flex-1 text-text-primary">917018107871</span>
              </div>
            </article>
          @endforeach
        </div>
      </div>
    </section>
  </div>
</x-layouts.app>
