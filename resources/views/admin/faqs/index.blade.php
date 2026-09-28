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
            <x-ui.table-actions
              :actions="['edit', 'toggle', 'trash']"
              :links="[
                'edit' => route('admin.faqs.edit', $row),
                'toggle' => route('admin.faqs.toggle', $row),
                'trash' => route('admin.faqs.destroy', $row),
              ]"
              :methods="['toggle' => 'POST', 'trash' => 'DELETE']"
              confirm="Delete this FAQ? This action cannot be undone."
              confirm-title="Delete FAQ"
              confirm-label="Delete"
            />
          </td>
        </tr>
      @empty
        <tr><td colspan="5" class="p-6 text-center text-sm text-text-subtle">No FAQs yet. Import from legacy or add one.</td></tr>
      @endforelse
    </x-ui.data-table>
  </div>
</x-admin.layout>
