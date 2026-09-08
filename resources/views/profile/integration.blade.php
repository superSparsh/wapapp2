@php
    $metaGuideline = "As per Meta's WhatsApp Business platform Phone Numbers Guidelines, business accounts are initially limited to 2 registered business WhatsApp phone numbers. This limit can be increased to up to 20.";
    $requirementTitle = '1. Official Business Requirements for increasing the WhatsApp phone number limit from 2 to 20';
    $line = $business['line'] ?? null;
@endphp

<x-profile.layout title="Integration - WapApp" headerTitle="Integration" active="profile.integration">
  @if (session('status'))
    <div class="mx-4 rounded-lg bg-green-50 p-3 text-sm text-primary-2">{{ session('status') }}</div>
  @endif
  @if ($errors->any())
    <div class="mx-4 rounded-lg bg-red-50 p-3 text-sm text-red-600">{{ $errors->first() }}</div>
  @endif

  <div class="flex flex-col p-4">
    <div class="flex flex-col gap-1">
      <h1 class="text-2xl font-bold leading-[1.5] text-text-primary">Integration</h1>
      <p class="max-w-[854px] text-sm font-normal leading-[1.4] text-text-subtle opacity-50">
        Manage your WhatsApp Business (WABA) profile and connected channels.
      </p>
    </div>
  </div>

  <section class="flex flex-col p-4 pt-0">
    <div class="rounded-lg bg-elevated p-3">
      <div class="flex flex-col gap-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
          <h2 class="text-xl font-semibold leading-[1.4] text-text-primary">Business Details</h2>
          <form method="POST" action="{{ route('profile.integration.sync') }}">
            @csrf
            <button type="submit" class="fd-btn inline-flex items-center justify-center gap-3 rounded border border-border-light bg-elevated px-4 py-3 text-sm font-semibold leading-[1.5] text-green-500 transition-colors hover:bg-surface">
              <img src="{{ asset('images/profile/refresh.svg') }}" alt="" class="size-4 shrink-0" width="16" height="16">
              Sync Data
            </button>
          </form>
        </div>

        <div class="overflow-hidden rounded-xl border border-divider bg-elevated shadow-[0px_4px_12px_rgba(0,0,0,0.04)]">
          <div class="grid grid-cols-3 gap-2 bg-elevated p-2">
            <div class="p-2 text-[13px] font-medium text-text-body">Business ID</div>
            <div class="p-2 text-[13px] font-medium text-text-body">Business Name</div>
            <div class="p-2 text-[13px] font-medium text-text-body">Status</div>
          </div>
          <div class="grid grid-cols-3 gap-2 border-t border-divider bg-elevated px-2 py-1.5">
            <div class="p-2 text-xs text-text-body">{{ $business['waba_id'] ?? '—' }}</div>
            <div class="p-2 text-xs text-text-body">{{ $business['business_name'] ?? '—' }}</div>
            <div class="p-2">
              @if ($business['verified'] ?? false)
                <span class="inline-flex items-center justify-center rounded bg-[rgba(0,128,0,0.1)] px-2 py-1 text-[10px] font-medium text-[green]">Verified</span>
              @else
                <span class="inline-flex items-center justify-center rounded bg-[rgba(255,0,0,0.1)] px-2 py-1 text-[10px] font-medium text-[red]">Not connected</span>
              @endif
            </div>
          </div>
        </div>

        <h2 class="text-xl font-semibold text-text-primary">Channels</h2>

        @if ($lines->isEmpty())
          <div class="rounded-xl border border-dashed border-border bg-surface p-6 text-sm text-text-muted">
            No WhatsApp lines connected yet.
          </div>
        @else
          <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($lines as $whatsappLine)
              <x-profile.channel-card
                :phone="$whatsappLine->phone"
                :display-name="$whatsappLine->display_name"
                :quality="$whatsappLine->quality_rating"
                :limit="$whatsappLine->messaging_limit_tier"
                :connected="filled($whatsappLine->waba_id)"
                :is-default="(bool) $whatsappLine->is_default"
                :href="route('profile.integration.connected', $whatsappLine)"
                class="max-w-none"
              />
            @endforeach
          </div>
        @endif

        <div class="flex w-full items-start gap-3 rounded-xl bg-stat-blue/15 p-3.5">
          <img src="{{ asset('images/profile/info-circle.svg') }}" alt="" class="size-6 shrink-0">
          <div class="flex min-w-0 flex-1 flex-col gap-2.5 text-sm leading-[1.4]">
            <p class="font-bold text-text-body">Note:</p>
            <p class="font-bold text-text-body">{{ $requirementTitle }}</p>
            <ul class="list-disc pl-[21px] font-normal text-text-muted">
              <li>{{ $metaGuideline }}</li>
            </ul>
          </div>
        </div>
      </div>
    </div>
  </section>
</x-profile.layout>
