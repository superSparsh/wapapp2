<x-admin.layout title="Cloud Bills - Admin" active="admin.cloud-bills.index">
  <div class="flex flex-wrap items-start justify-between gap-3 p-4">
    <div>
      <h1 class="text-2xl font-bold text-text-primary">Cloud bills</h1>
      <p class="text-sm text-text-subtle opacity-70">Uploaded provider invoices and rate notes.</p>
    </div>
    <a href="{{ route('admin.cloud-bills.create') }}" class="rounded-lg bg-green-500 px-3 py-2 text-xs font-semibold text-white">Upload bill</a>
  </div>

  @if (session('status'))
    <div class="mx-4 mb-4 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">{{ session('status') }}</div>
  @endif

  <div class="p-4 pt-0">
    <x-ui.data-table :headers="['File', 'Period', 'Amount', 'Status', 'Uploaded', '']" :paginator="$bills">
      @forelse ($bills as $bill)
        <tr>
          <td class="p-3 font-semibold">{{ $bill->filename }}</td>
          <td class="p-3 text-sm">{{ $bill->period ?: '—' }}</td>
          <td class="p-3 text-sm">{{ $bill->amount !== null ? $bill->currency.' '.$bill->amount : '—' }}</td>
          <td class="p-3 text-sm">{{ $bill->status }}</td>
          <td class="p-3 text-sm text-text-subtle">{{ $bill->created_at?->diffForHumans() }}</td>
          <td class="p-3"><a href="{{ route('admin.cloud-bills.show', $bill) }}" class="text-xs font-semibold text-green-600 hover:underline">Open</a></td>
        </tr>
      @empty
        <tr><td colspan="6" class="p-6 text-center text-sm text-text-subtle">No cloud bills uploaded.</td></tr>
      @endforelse
    </x-ui.data-table>
  </div>
</x-admin.layout>
