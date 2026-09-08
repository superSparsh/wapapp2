<x-admin.layout title="Languages - Admin" active="admin.languages.index">
  <div class="flex flex-wrap items-start justify-between gap-3 p-4">
    <div>
      <h1 class="text-2xl font-bold text-text-primary">Languages</h1>
      <p class="text-sm text-text-subtle opacity-70">Locales customers can pick for their workspace.</p>
    </div>
    <a href="{{ route('admin.languages.create') }}" class="rounded-lg bg-green-500 px-3 py-2 text-xs font-semibold text-white">Add language</a>
  </div>

  <div class="p-4 pt-0">
    <x-ui.data-table :headers="['Name', 'Code', 'Region', 'Active', '']" :paginator="$rows">
      @forelse ($rows as $row)
        <tr>
          <td class="p-3 font-semibold">{{ $row->name }}</td>
          <td class="p-3 text-sm">{{ $row->code }}</td>
          <td class="p-3 text-sm">{{ $row->region_code ?: '—' }}</td>
          <td class="p-3 text-sm">{{ $row->is_active ? 'Yes' : 'No' }}</td>
          <td class="p-3">
            <div class="flex gap-2">
              <a href="{{ route('admin.languages.edit', $row) }}" class="text-xs font-semibold text-green-600 hover:underline">Edit</a>
              <form method="POST" action="{{ route('admin.languages.toggle', $row) }}">@csrf
                <button class="text-xs font-semibold text-text-subtle hover:underline">{{ $row->is_active ? 'Disable' : 'Enable' }}</button>
              </form>
              <form method="POST" action="{{ route('admin.languages.destroy', $row) }}" data-confirm="Delete this language?" data-confirm-title="Delete language" data-confirm-variant="danger">
                @csrf @method('DELETE')
                <button class="text-xs font-semibold text-red-600 hover:underline">Delete</button>
              </form>
            </div>
          </td>
        </tr>
      @empty
        <tr><td colspan="5" class="p-6 text-center text-sm text-text-subtle">No languages yet.</td></tr>
      @endforelse
    </x-ui.data-table>
  </div>
</x-admin.layout>
