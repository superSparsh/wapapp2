<x-admin.layout title="FAQs - Admin" active="admin.faqs.index">
  <div class="flex flex-wrap items-start justify-between gap-3 p-4">
    <div>
      <h1 class="text-2xl font-bold text-text-primary">FAQs</h1>
      <p class="text-sm text-text-subtle opacity-70">Help center articles shown to customers (same as legacy Admin FAQ).</p>
    </div>
    <div class="flex flex-wrap gap-2">
      <form method="POST" action="{{ route('admin.faqs.import-legacy') }}" onsubmit="return confirm('Import FAQs from the legacy database? Existing slugs will be updated.')">
        @csrf
        <input type="hidden" name="fresh" value="0">
        <button type="submit" class="rounded-lg border border-green-500 px-3 py-2 text-xs font-semibold text-green-600">Import from legacy</button>
      </form>
      <a href="{{ route('admin.faqs.create') }}" class="rounded-lg bg-green-500 px-3 py-2 text-xs font-semibold text-white">Add FAQ</a>
    </div>
  </div>

  @if (session('status'))
    <div class="mx-4 mb-2 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">{{ session('status') }}</div>
  @endif
  @if (session('error'))
    <div class="mx-4 mb-2 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-600">{{ session('error') }}</div>
  @endif

  <x-admin.filter-bar
    :action="route('admin.faqs.index')"
    :search="$filters['q'] ?? ''"
    search-placeholder="Search FAQ…"
    :show-dates="false"
    :sort="$filters['sort'] ?? 'sort_order'"
    :direction="$filters['direction'] ?? 'asc'"
    :sort-options="$sortOptions"
  />

  <div class="p-4 pt-0">
    <x-ui.data-table :headers="['Heading', 'Slug', 'Order', 'Active', 'Actions']" :paginator="$rows">
      @forelse ($rows as $row)
        <tr class="bg-elevated">
          <td class="fd-table-cell p-2 align-middle">
            <div class="fd-table-name">{{ $row->heading }}</div>
            <div class="line-clamp-1 text-xs text-text-subtle">{{ \Illuminate\Support\Str::limit(strip_tags($row->description), 80) }}</div>
          </td>
          <td class="fd-table-cell p-2 align-middle text-sm">{{ $row->slug }}</td>
          <td class="fd-table-cell p-2 align-middle text-sm">{{ $row->sort_order }}</td>
          <td class="fd-table-cell p-2 align-middle"><x-admin.status-badge :status="$row->is_active" /></td>
          <td class="w-[160px] p-2 align-middle">
            <div class="flex flex-wrap gap-2">
              <a href="{{ route('admin.faqs.edit', $row) }}" class="text-xs font-semibold text-green-600 hover:underline">Edit</a>
              <form method="POST" action="{{ route('admin.faqs.toggle', $row) }}">@csrf<button class="text-xs font-semibold text-text-subtle hover:underline">Toggle</button></form>
              <form method="POST" action="{{ route('admin.faqs.destroy', $row) }}" onsubmit="return confirm('Delete this FAQ?')">@csrf @method('DELETE')<button class="text-xs font-semibold text-red-600 hover:underline">Delete</button></form>
            </div>
          </td>
        </tr>
      @empty
        <tr><td colspan="5" class="p-6 text-center text-sm text-text-subtle">No FAQs yet. Import from legacy or add one.</td></tr>
      @endforelse
    </x-ui.data-table>
  </div>
</x-admin.layout>
