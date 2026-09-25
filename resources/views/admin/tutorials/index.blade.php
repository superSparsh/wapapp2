<x-admin.layout title="Tutorials - Admin" active="admin.tutorials.index">
  <div class="flex flex-wrap items-start justify-between gap-3 p-4">
    <div>
      <h1 class="text-2xl font-bold text-text-primary">Tutorials</h1>
      <p class="text-sm text-text-subtle opacity-70">Help center videos shown to customers (same as legacy Admin tutorials).</p>
    </div>
    <div class="flex flex-wrap gap-2">
      <form method="POST" action="{{ route('admin.tutorials.import-legacy') }}" onsubmit="return confirm('Import tutorials from the legacy database? Existing title+module rows will be updated.')">
        @csrf
        <input type="hidden" name="fresh" value="0">
        <input type="hidden" name="copy_videos" value="0">
        <button type="submit" class="rounded-lg border border-green-500 px-3 py-2 text-xs font-semibold text-green-600">Import from legacy</button>
      </form>
      <a href="{{ route('admin.tutorials.create') }}" class="rounded-lg bg-green-500 px-3 py-2 text-xs font-semibold text-white">Add tutorial</a>
    </div>
  </div>

  @if (session('status'))
    <div class="mx-4 mb-2 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">{{ session('status') }}</div>
  @endif
  @if (session('error'))
    <div class="mx-4 mb-2 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-600">{{ session('error') }}</div>
  @endif

  <x-admin.filter-bar
    :action="route('admin.tutorials.index')"
    :search="$filters['q'] ?? ''"
    search-placeholder="Search tutorial…"
    :show-dates="false"
    :sort="$filters['sort'] ?? 'sort_order'"
    :direction="$filters['direction'] ?? 'asc'"
    :sort-options="$sortOptions"
  />

  <div class="p-4 pt-0">
    <x-ui.data-table :headers="['Title', 'Module', 'Video', 'Order', 'Active', 'Actions']" :paginator="$rows">
      @forelse ($rows as $row)
        <tr class="bg-elevated">
          <td class="fd-table-cell p-2 align-middle">
            <div class="fd-table-name">{{ $row->title }}</div>
            @if ($row->duration)
              <div class="text-xs text-text-subtle">{{ $row->duration }}</div>
            @endif
          </td>
          <td class="fd-table-cell p-2 align-middle text-sm">{{ $row->module_name }}</td>
          <td class="fd-table-cell p-2 align-middle text-sm">
            <span class="line-clamp-1 font-mono text-xs" title="{{ $row->youtube_id }}">{{ $row->youtube_id }}</span>
          </td>
          <td class="fd-table-cell p-2 align-middle text-sm">{{ $row->sort_order }}</td>
          <td class="fd-table-cell p-2 align-middle"><x-admin.status-badge :status="$row->is_active" /></td>
          <td class="w-[160px] p-2 align-middle">
            <div class="flex flex-wrap gap-2">
              <a href="{{ route('admin.tutorials.edit', $row) }}" class="text-xs font-semibold text-green-600 hover:underline">Edit</a>
              <form method="POST" action="{{ route('admin.tutorials.toggle', $row) }}">@csrf<button class="text-xs font-semibold text-text-subtle hover:underline">Toggle</button></form>
              <form method="POST" action="{{ route('admin.tutorials.destroy', $row) }}" onsubmit="return confirm('Delete this tutorial?')">@csrf @method('DELETE')<button class="text-xs font-semibold text-red-600 hover:underline">Delete</button></form>
            </div>
          </td>
        </tr>
      @empty
        <tr><td colspan="6" class="p-6 text-center text-sm text-text-subtle">No tutorials yet. Import from legacy or add one.</td></tr>
      @endforelse
    </x-ui.data-table>
  </div>
</x-admin.layout>
