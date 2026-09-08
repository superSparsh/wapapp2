<x-admin.layout title="Upload Cloud Bill - Admin" active="admin.cloud-bills.index">
  <div class="p-4">
    <a href="{{ route('admin.cloud-bills.index') }}" class="text-xs font-semibold text-green-600 hover:underline">← Cloud bills</a>
    <h1 class="mt-2 text-2xl font-bold text-text-primary">Upload cloud bill</h1>
  </div>

  <form method="POST" action="{{ route('admin.cloud-bills.store') }}" enctype="multipart/form-data" class="mx-4 mb-8 max-w-xl rounded-[20px] border border-border bg-elevated p-5">
    @csrf
    @if ($errors->any())
      <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-600">{{ $errors->first() }}</div>
    @endif

    <div class="grid gap-4">
      <label class="flex flex-col gap-1.5 text-sm">
        <span class="font-semibold">File</span>
        <input type="file" name="file" required class="rounded-lg border border-border px-3 py-2">
      </label>
      <label class="flex flex-col gap-1.5 text-sm">
        <span class="font-semibold">Period</span>
        <input name="period" value="{{ old('period') }}" placeholder="2026-08" class="rounded-lg border border-border px-3 py-2">
      </label>
      <div class="grid gap-4 sm:grid-cols-3">
        <label class="flex flex-col gap-1.5 text-sm">
          <span class="font-semibold">Amount</span>
          <input type="number" step="0.01" min="0" name="amount" value="{{ old('amount') }}" class="rounded-lg border border-border px-3 py-2">
        </label>
        <label class="flex flex-col gap-1.5 text-sm">
          <span class="font-semibold">Currency</span>
          <input name="currency" maxlength="3" value="{{ old('currency', 'USD') }}" class="rounded-lg border border-border px-3 py-2">
        </label>
        <label class="flex flex-col gap-1.5 text-sm">
          <span class="font-semibold">Exchange rate</span>
          <input type="number" step="0.000001" min="0" name="exchange_rate" value="{{ old('exchange_rate') }}" class="rounded-lg border border-border px-3 py-2">
        </label>
      </div>
    </div>

    <button class="mt-5 rounded-lg bg-green-500 px-4 py-2 text-sm font-semibold text-white">Upload</button>
  </form>
</x-admin.layout>
