@php
  $isEdit = isset($row);
  $action = $isEdit ? route('admin.pricing.update', $row) : route('admin.pricing.store');
  $regionCodes = old('region_codes', isset($row) && is_array($row->region_codes) ? implode(', ', $row->region_codes) : '');
@endphp

<x-admin.layout :title="($isEdit ? 'Edit pricing' : 'Add pricing').' - Admin'" active="admin.pricing.index">
  <div class="p-4">
    <a href="{{ route('admin.pricing.index') }}" class="text-xs font-semibold text-green-600 hover:underline">← Country pricing</a>
    <h1 class="mt-2 text-2xl font-bold text-text-primary">{{ $isEdit ? 'Edit country pricing' : 'Add country pricing' }}</h1>
  </div>

  <form method="POST" action="{{ $action }}" class="mx-4 mb-8 max-w-4xl rounded-[20px] border border-border bg-elevated p-5">
    @csrf
    @if ($isEdit) @method('PUT') @endif

    @if ($errors->any())
      <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-600">{{ $errors->first() }}</div>
    @endif

    <div class="grid gap-4 sm:grid-cols-2">
      <label class="flex flex-col gap-1.5 text-sm">
        <span class="font-semibold">Country code</span>
        <input name="country_code" maxlength="255" value="{{ old('country_code', $row->country_code ?? '') }}" class="rounded-lg border border-border px-3 py-2" @disabled($isEdit)>
        @if ($isEdit)<input type="hidden" name="country_code" value="{{ $row->country_code }}">@endif
      </label>
      <label class="flex flex-col gap-1.5 text-sm">
        <span class="font-semibold">Country name</span>
        <input name="country_name" value="{{ old('country_name', $row->country_name ?? '') }}" required class="rounded-lg border border-border px-3 py-2">
      </label>
      <label class="flex flex-col gap-1.5 text-sm">
        <span class="font-semibold">Dial code</span>
        <input name="dial_code" value="{{ old('dial_code', $row->dial_code ?? '') }}" class="rounded-lg border border-border px-3 py-2" placeholder="+91">
      </label>
      <label class="flex flex-col gap-1.5 text-sm">
        <span class="font-semibold">Region codes</span>
        <input name="region_codes" value="{{ $regionCodes }}" class="rounded-lg border border-border px-3 py-2" placeholder="IN, IND (comma separated)">
      </label>
      <label class="flex flex-col gap-1.5 text-sm">
        <span class="font-semibold">Currency</span>
        <input name="currency" maxlength="10" value="{{ old('currency', $row->currency ?? '₹') }}" required class="rounded-lg border border-border px-3 py-2">
      </label>
      <label class="inline-flex items-center gap-2 self-end text-sm">
        <input type="checkbox" name="status" value="1" @checked(old('status', isset($row) ? $row->isActive() : true))>
        <span class="font-semibold">Active</span>
      </label>
    </div>

    <h2 class="mt-6 text-sm font-bold text-text-primary">Meta prices</h2>
    <div class="mt-3 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
      @foreach ([
        'marketing_price' => 'Marketing',
        'utility_price' => 'Utility',
        'auth_price' => 'Auth',
        'auth_international_price' => 'Auth international',
        'service_price' => 'Service',
      ] as $field => $label)
        <label class="flex flex-col gap-1.5 text-sm">
          <span class="font-semibold">{{ $label }}</span>
          <input type="number" step="0.0001" min="0" name="{{ $field }}" value="{{ old($field, $row->{$field} ?? '') }}" class="rounded-lg border border-border px-3 py-2">
        </label>
      @endforeach
    </div>

    <h2 class="mt-6 text-sm font-bold text-text-primary">Tekpro prices</h2>
    <div class="mt-3 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
      @foreach ([
        'tekpro_marketing_price' => 'Tekpro marketing',
        'tekpro_utility_price' => 'Tekpro utility',
        'tekpro_auth_price' => 'Tekpro auth',
        'tekpro_auth_international_price' => 'Tekpro auth intl',
        'tekpro_service_price' => 'Tekpro service',
      ] as $field => $label)
        <label class="flex flex-col gap-1.5 text-sm">
          <span class="font-semibold">{{ $label }}</span>
          <input type="number" step="0.0001" min="0" name="{{ $field }}" value="{{ old($field, $row->{$field} ?? '') }}" class="rounded-lg border border-border px-3 py-2">
        </label>
      @endforeach
    </div>

    <button class="mt-5 rounded-lg bg-green-500 px-4 py-2 text-sm font-semibold text-white">{{ $isEdit ? 'Update' : 'Save' }}</button>
  </form>
</x-admin.layout>
