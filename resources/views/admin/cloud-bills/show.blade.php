<x-admin.layout title="Cloud Bill - Admin" active="admin.cloud-bills.index">
  <div class="flex flex-wrap items-start justify-between gap-3 p-4">
    <div>
      <a href="{{ route('admin.cloud-bills.index') }}" class="text-xs font-semibold text-green-600 hover:underline">← Cloud bills</a>
      <h1 class="mt-1 text-2xl font-bold text-text-primary">{{ $bill->filename }}</h1>
      <p class="text-sm text-text-subtle">{{ $bill->period ?: 'No period' }} · {{ $bill->status }}</p>
    </div>
    <div class="flex gap-2">
      <a href="{{ route('admin.cloud-bills.download', $bill) }}" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold hover:bg-surface">Download</a>
      <form method="POST" action="{{ route('admin.cloud-bills.destroy', $bill) }}">@csrf @method('DELETE')
        <button class="rounded-lg bg-red-500 px-3 py-2 text-xs font-semibold text-white">Delete</button>
      </form>
    </div>
  </div>

  @if (session('status'))
    <div class="mx-4 mb-4 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">{{ session('status') }}</div>
  @endif

  <form method="POST" action="{{ route('admin.cloud-bills.update', $bill) }}" class="mx-4 mb-8 max-w-xl rounded-[20px] border border-border bg-elevated p-5">
    @csrf
    @method('PUT')
    <h2 class="text-lg font-bold">Rate / status</h2>
    <div class="mt-4 grid gap-4 sm:grid-cols-2">
      <label class="flex flex-col gap-1.5 text-sm">
        <span class="font-semibold">Amount</span>
        <input type="number" step="0.01" min="0" name="amount" value="{{ old('amount', $bill->amount) }}" class="rounded-lg border border-border px-3 py-2">
      </label>
      <label class="flex flex-col gap-1.5 text-sm">
        <span class="font-semibold">Currency</span>
        <input name="currency" maxlength="3" value="{{ old('currency', $bill->currency) }}" class="rounded-lg border border-border px-3 py-2">
      </label>
      <label class="flex flex-col gap-1.5 text-sm">
        <span class="font-semibold">Exchange rate</span>
        <input type="number" step="0.000001" min="0" name="exchange_rate" value="{{ old('exchange_rate', $bill->exchange_rate) }}" class="rounded-lg border border-border px-3 py-2">
      </label>
      <label class="flex flex-col gap-1.5 text-sm">
        <span class="font-semibold">Status</span>
        <input name="status" value="{{ old('status', $bill->status) }}" class="rounded-lg border border-border px-3 py-2">
      </label>
    </div>
    <button class="mt-5 rounded-lg bg-green-500 px-4 py-2 text-sm font-semibold text-white">Save</button>
  </form>
</x-admin.layout>
