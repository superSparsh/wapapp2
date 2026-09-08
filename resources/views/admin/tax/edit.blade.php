<x-admin.layout title="Tax Settings - Admin" active="admin.tax.edit">
  <div class="p-4">
    <h1 class="text-2xl font-bold text-text-primary">Tax settings</h1>
    <p class="text-sm text-text-subtle opacity-70">Default tax rate and per-country overrides.</p>
  </div>

  <form method="POST" action="{{ route('admin.tax.update') }}" class="mx-4 mb-8 max-w-3xl rounded-[20px] border border-border bg-elevated p-5">
    @csrf
    @method('PUT')

    @if ($errors->any())
      <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-600">{{ $errors->first() }}</div>
    @endif

    <div class="grid gap-4 sm:grid-cols-2">
      <label class="flex flex-col gap-1.5 text-sm">
        <span class="font-semibold">Tax enabled</span>
        <select name="tax_enabled" class="rounded-lg border border-border px-3 py-2">
          <option value="0" @selected(old('tax_enabled', $settings['tax.enabled'] ?? '0') === '0')>No</option>
          <option value="1" @selected(old('tax_enabled', $settings['tax.enabled'] ?? '0') === '1')>Yes</option>
        </select>
      </label>
      <label class="flex flex-col gap-1.5 text-sm">
        <span class="font-semibold">Default rate (%)</span>
        <input type="number" step="0.01" min="0" max="100" name="tax_default_rate" value="{{ old('tax_default_rate', $settings['tax.default_rate'] ?? '0') }}" class="rounded-lg border border-border px-3 py-2">
      </label>
      <label class="flex flex-col gap-1.5 text-sm sm:col-span-2">
        <span class="font-semibold">Country rates (JSON)</span>
        <textarea name="tax_countries" rows="8" class="rounded-lg border border-border px-3 py-2 font-mono text-xs" placeholder='{"IN": 18, "AE": 5}'>{{ old('tax_countries', $settings['tax.countries'] ?? '') }}</textarea>
      </label>
    </div>

    <button class="mt-6 rounded-lg bg-green-500 px-4 py-2 text-sm font-semibold text-white">Save tax settings</button>
  </form>
</x-admin.layout>
