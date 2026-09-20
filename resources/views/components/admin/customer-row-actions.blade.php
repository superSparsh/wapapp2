@props([
    'tenant',
])

@php
    $isActive = $tenant->status === \App\Enums\TenantStatus::Active;
    $toggleLabel = $isActive ? 'Disable' : 'Enable';
    $toggleConfirm = $isActive
        ? 'Disable this customer account?'
        : 'Enable this customer account?';

    $editIcon = null;
    foreach (['images/icons/table/edit.svg', 'images/templates/edit.svg', 'images/campaigns/edit.svg'] as $path) {
        if (file_exists(public_path($path))) {
            $editIcon = asset($path);
            break;
        }
    }
@endphp

<div class="flex items-center justify-end gap-3">
  <form method="POST" action="{{ route('admin.customers.login-as', $tenant) }}" class="inline">
    @csrf
    <button type="submit" class="rounded-md bg-green-500 px-2 py-1 text-[10px] font-bold uppercase tracking-wide text-white hover:bg-green-600" title="Login as customer" aria-label="Login as customer">
      Login
    </button>
  </form>

  <a href="{{ route('admin.customers.edit', $tenant) }}" class="flex size-5 items-center justify-center" title="Edit" aria-label="Edit">
    @if ($editIcon)
      <img src="{{ $editIcon }}" alt="" class="size-5" width="20" height="20">
    @else
      <span class="text-[10px] font-bold text-text-subtle">Edit</span>
    @endif
  </a>

  <details class="relative">
    <summary
      class="flex size-7 cursor-pointer list-none items-center justify-center rounded-md border border-border bg-surface text-sm font-bold text-text-subtle hover:bg-elevated [&::-webkit-details-marker]:hidden"
      title="More actions"
      aria-label="More actions"
    >⋯</summary>
    <div class="absolute right-0 z-30 mt-1 min-w-[220px] rounded-xl border border-border bg-elevated py-1 shadow-lg">
      <a href="{{ route('admin.customers.show', $tenant) }}" class="block px-3 py-2 text-sm text-text-primary hover:bg-surface">View details</a>
      <a href="{{ route('admin.customers.activity-logs', $tenant) }}" class="block px-3 py-2 text-sm text-text-primary hover:bg-surface">Activity logs</a>
      <a href="{{ route('admin.billing-audit.index', ['tenant' => $tenant->id]) }}" class="block px-3 py-2 text-sm text-text-primary hover:bg-surface">Billing audit</a>
      <a href="{{ route('admin.whatsapp-health.index', ['tenant' => $tenant->id]) }}" class="block px-3 py-2 text-sm text-text-primary hover:bg-surface">WhatsApp lines</a>
      <button
        type="button"
        class="block w-full px-3 py-2 text-left text-sm text-text-primary hover:bg-surface"
        data-extend-validity
        data-tenant-name="{{ $tenant->company_name ?: $tenant->name }}"
        data-extend-url="{{ route('admin.customers.extend-validity', $tenant) }}"
        data-valid-until="{{ data_get($tenant->settings, 'valid_until', '') }}"
      >Extend validity &amp; wallet</button>
      <a href="{{ route('admin.customers.show', $tenant) }}#assign-plan" class="block px-3 py-2 text-sm text-text-primary hover:bg-surface">Assign plan</a>
      <div class="my-1 border-t border-border"></div>
      <form
        method="POST"
        action="{{ route('admin.customers.toggle-status', $tenant) }}"
        data-confirm="{{ $toggleConfirm }}"
        data-confirm-title="{{ $toggleLabel }} customer"
        data-confirm-label="{{ $toggleLabel }}"
      >
        @csrf
        <button type="submit" class="block w-full px-3 py-2 text-left text-sm text-text-primary hover:bg-surface">
          {{ $toggleLabel }}
        </button>
      </form>
    </div>
  </details>
</div>
