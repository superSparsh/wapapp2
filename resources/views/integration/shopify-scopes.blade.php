@php
  $savedScopes = $integration->settings['scopes'] ?? [];
  $availableScopes = [
    'access_scope_check'      => 'Access Scope Check',
    'product_listings_add'    => 'Product Listings Add',
    'product_listings_remove' => 'Product Listings Remove',
    'product_listings_update' => 'Product Listings Update',
    'products_create'         => 'Products Create',
    'products_delete'         => 'Products Delete',
    'products_update'         => 'Products Update',
  ];
@endphp

<x-layouts.app title="Shopify Trigger Scopes - WapApp" active="integration.index">
  <div class="flex flex-col bg-surface">
    <section class="grid items-start lg:grid-cols-2">
      {{-- Left: scopes --}}
      <div class="flex min-w-0 flex-col">
        <div class="flex flex-col gap-1 p-4">
          <h1 class="fd-page-title text-2xl">Add Trigger Scope</h1>
          <p class="fd-page-note">Create personalized message templates for initiating conversation with your customers.</p>
        </div>

        <div class="flex flex-col gap-3 p-4 pt-0">
          <div class="flex flex-col gap-3">
            <label class="text-sm font-semibold leading-[1.4] text-text-primary">
              Select a webhook scope<span class="text-red-500">*</span>:
            </label>
            <div class="flex items-center gap-3 rounded-xl border border-border bg-elevated p-3.5">
              <span class="min-w-0 flex-1 text-sm font-medium leading-[1.4] text-text-muted">Select a webhook scope:</span>
              <img src="{{ asset('images/integration/arrow-down.svg') }}" alt="" class="size-4 shrink-0" width="16" height="16">
            </div>
          </div>
        </div>

        <div class="px-4">
          <div class="flex items-start gap-3 rounded-xl bg-stat-blue/15 p-3.5">
            <img src="{{ asset('images/integration/info-circle.svg') }}" alt="" class="size-6 shrink-0" width="24" height="24">
            <p class="text-sm font-normal leading-[1.4] text-text-body">Info about the selected scope</p>
          </div>
        </div>

        <div class="p-4">
          <div class="flex items-center gap-3 overflow-hidden rounded-lg bg-elevated p-3">
            <img src="{{ asset('images/integration/search.svg') }}" alt="" class="size-5 shrink-0" width="20" height="20">
            <span class="min-w-0 flex-1 text-sm font-medium leading-[1.4] text-text-body/60 opacity-60">search Scopes</span>
          </div>
        </div>

        <div class="px-4 pb-4">
          <div class="flex h-[565px] max-h-[565px] flex-col gap-3 overflow-y-auto rounded-lg bg-elevated p-3">
            @forelse ($savedScopes as $scopeKey => $scopeValue)
              <div class="flex items-center gap-3 rounded-xl border border-border bg-elevated p-3.5">
                <span class="min-w-0 flex-1 text-sm font-medium leading-[1.4] text-text-muted">{{ $availableScopes[$scopeKey] ?? $scopeKey }}</span>
                <img src="{{ asset('images/integration/trash.svg') }}" alt="" class="size-5 shrink-0" width="20" height="20">
              </div>
            @empty
              <p class="p-4 text-center text-sm text-text-subtle">No webhook scopes configured yet.</p>
            @endforelse
          </div>
        </div>
      </div>

      {{-- Right: selected scope + preview --}}
      <div class="flex min-w-0 flex-col">
        <div class="px-4 py-2">
          <div class="flex flex-col gap-3">
            <label class="text-sm font-semibold leading-[1.4] text-text-primary">
              Selected Scope<span class="text-red-500">*</span>:
            </label>
            <div class="rounded-xl border border-border bg-elevated p-3.5 text-sm font-medium leading-[1.4] text-text-muted">
              {{ $integration->settings['template_selected'] ?? 'No scope selected' }}
            </div>
          </div>
        </div>

        <div class="flex items-center gap-3 px-4 py-2">
          <span class="text-sm font-semibold leading-[1.4] text-text-primary">Status:</span>
          <div class="p-2">
            <x-ui.toggle-switch :active="true" />
          </div>
        </div>

        <div class="flex flex-col gap-3 px-4 py-2 sm:flex-row sm:items-end sm:gap-3">
          <div class="flex min-w-0 flex-1 flex-col gap-3">
            <span class="text-sm font-semibold leading-[1.4] text-text-primary">Create New Template:</span>
            <button
              type="button"
              class="fd-btn inline-flex w-full items-center justify-center gap-3 rounded border border-border-light bg-elevated px-4 py-3 text-sm font-medium leading-[1.5] text-green-500 transition-colors hover:bg-surface"
            >
              <img src="{{ asset('images/integration/add-green.svg') }}" alt="" class="size-4 shrink-0" width="16" height="16">
              Create New Template
            </button>
          </div>

          <span class="hidden shrink-0 pb-3 text-sm font-semibold leading-[1.4] text-text-primary sm:block">OR</span>
          <span class="text-center text-sm font-semibold leading-[1.4] text-text-primary sm:hidden">OR</span>

          <div class="flex min-w-0 flex-1 flex-col gap-3">
            <span class="text-sm font-semibold leading-[1.4] text-text-primary">Select Template:</span>
            <div class="flex items-center gap-3 rounded-xl border border-border bg-elevated p-3.5">
              <span class="min-w-0 flex-1 text-sm font-medium leading-[1.4] text-text-muted">Select a webhook trigger</span>
              <img src="{{ asset('images/integration/arrow-down.svg') }}" alt="" class="size-4 shrink-0" width="16" height="16">
            </div>
          </div>
        </div>

        <div class="px-4">
          <x-integration.message-preview>
            @if (! empty($savedScopes))
              <p class="mb-0">Your webhook message preview will appear here.</p>
              <p class="mb-0">Configure a template to see how customers will receive notifications.</p>
            @else
              <p class="mb-0 text-text-subtle">Select a scope and template to preview your webhook message.</p>
            @endif
          </x-integration.message-preview>
        </div>

        <div class="p-4 pt-2">
          <button
            type="button"
            class="fd-btn inline-flex items-center justify-center gap-2 rounded bg-green-500 px-4 py-3 text-sm font-semibold leading-[1.5] text-primary-2 transition-colors hover:opacity-90"
          >
            <img src="{{ asset('images/integration/add.svg') }}" alt="" class="size-5 shrink-0" width="20" height="20">
            Create Webhook
          </button>
        </div>
      </div>
    </section>
  </div>
</x-layouts.app>
