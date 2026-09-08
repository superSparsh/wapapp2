@php
  $isEdit = isset($row);
  $action = $isEdit ? route('admin.pricing.update', $row) : route('admin.pricing.store');
@endphp

<x-admin.layout :title="($isEdit ? 'Edit pricing' : 'Add pricing').' - Admin'" active="admin.pricing.index">
  <div class="p-4">
    <a href="{{ route('admin.pricing.index') }}" class="text-xs font-semibold text-green-600 hover:underline">← Country pricing</a>
    <h1 class="mt-2 text-2xl font-bold text-text-primary">{{ $isEdit ? 'Edit country pricing' : 'Add country pricing' }}</h1>
  </div>

  <form method="POST" action="{{ $action }}" class="mx-4 mb-8 max-w-3xl rounded-[20px] border border-border bg-elevated p-5">
    @csrf
    @if ($isEdit) @method('PUT') @endif

    @if ($errors->any())
      <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-600">{{ $errors->first() }}</div>
    @endif

    <div class="grid gap-4 sm:grid-cols-2">
      <label class="flex flex-col gap-1.5 text-sm">
        <span class="font-semibold">Country code</span>
        <input name="country_code" maxlength="8" value="{{ old('country_code', $row->country_code ?? '') }}" required class="rounded-lg border border-border px-3 py-2" @disabled($isEdit)>
        @if ($isEdit)<input type="hidden" name="country_code" value="{{ $row->country_code }}">@endif
      </label>
      <label class="flex flex-col gap-1.5 text-sm">
        <span class="font-semibold">Country name</span>
        <input name="country_name" value="{{ old('country_name', $row->country_name ?? '') }}" required class="rounded-lg border border-border px-3 py-2">
      </label>
      @foreach ([
        'marketing_rate' => 'Marketing rate',
        'utility_rate' => 'Utility rate',
        'authentication_rate' => 'Authentication rate',
        'service_rate' => 'Service rate',
      ] as $field => $label)
        <label class="flex flex-col gap-1.5 text-sm">
          <span class="font-semibold">{{ $label }}</span>
          <input type="number" step="0.0001" min="0" name="{{ $field }}" value="{{ old($field, $row->{$field} ?? '0') }}" required class="rounded-lg border border-border px-3 py-2">
        </label>
      @endforeach
      <label class="flex flex-col gap-1.5 text-sm">
        <span class="font-semibold">Currency</span>
        <input name="currency" maxlength="3" value="{{ old('currency', $row->currency ?? 'USD') }}" required class="rounded-lg border border-border px-3 py-2">
      </label>
      <label class="inline-flex items-center gap-2 self-end text-sm">
        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $row->is_active ?? true))>
        <span class="font-semibold">Active</span>
      </label>
    </div>

    <button class="mt-5 rounded-lg bg-green-500 px-4 py-2 text-sm font-semibold text-white">{{ $isEdit ? 'Update' : 'Save' }}</button>
  </form>
</x-admin.layout>
