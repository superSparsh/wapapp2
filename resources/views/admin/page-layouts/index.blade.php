<x-admin.layout title="Page Layouts - Admin" active="admin.page-layouts.index">
  <div class="flex flex-wrap items-start justify-between gap-3 p-4">
    <div>
      <h1 class="text-2xl font-bold text-text-primary">Page layouts</h1>
      <p class="text-sm text-text-subtle opacity-70">Static marketing and legal page content.</p>
    </div>
    <a href="{{ route('admin.page-layouts.create') }}" class="rounded-lg bg-green-500 px-3 py-2 text-xs font-semibold text-white">Add page layout</a>
  </div>

  <div class="p-4 pt-0">
    <x-ui.data-table :headers="['Name', 'Slug', 'Alias', 'Active', '']" :paginator="$rows">
      @forelse ($rows as $row)
        <tr>
          <td class="p-3 font-semibold">{{ $row->name }}</td>
          <td class="p-3 text-sm">{{ $row->slug }}</td>
          <td class="p-3 text-sm">{{ $row->alias ?: '—' }}</td>
          <td class="p-3 text-sm">{{ $row->is_active ? 'Yes' : 'No' }}</td>
          <td class="p-3">
            <div class="flex gap-2">
              <a href="{{ route('admin.page-layouts.edit', $row) }}" class="text-xs font-semibold text-green-600 hover:underline">Edit</a>
              <form method="POST" action="{{ route('admin.page-layouts.destroy', $row) }}" data-confirm="Delete this page layout?" data-confirm-title="Delete page layout" data-confirm-variant="danger">
                @csrf @method('DELETE')
                <button class="text-xs font-semibold text-red-600 hover:underline">Delete</button>
              </form>
            </div>
          </td>
        </tr>
      @empty
        <tr><td colspan="5" class="p-6 text-center text-sm text-text-subtle">No page layouts yet.</td></tr>
      @endforelse
    </x-ui.data-table>
  </div>
</x-admin.layout>
