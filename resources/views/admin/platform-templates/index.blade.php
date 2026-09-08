<x-admin.layout title="Template Gallery - Admin" active="admin.platform-templates.index">
  <div class="flex flex-wrap items-start justify-between gap-3 p-4">
    <div>
      <h1 class="text-2xl font-bold text-text-primary">Template gallery</h1>
      <p class="text-sm text-text-subtle opacity-70">Ready-made message templates offered to customers.</p>
    </div>
    <a href="{{ route('admin.platform-templates.create') }}" class="rounded-lg bg-green-500 px-3 py-2 text-xs font-semibold text-white">Add template</a>
  </div>

  <form method="GET" class="mx-4 mb-4 flex flex-wrap gap-3 rounded-[20px] border border-border bg-elevated p-4">
    <select name="category" class="rounded-lg border border-border px-3 py-2 text-sm">
      <option value="">All categories</option>
      @foreach ($categories as $option)
        <option value="{{ $option }}" @selected($category === $option)>{{ $option }}</option>
      @endforeach
    </select>
    <button class="rounded-lg bg-green-500 px-4 py-2 text-sm font-semibold text-white">Filter</button>
  </form>

  <div class="p-4 pt-0">
    <x-ui.data-table :headers="['Name', 'Category', 'Type', 'Body', 'Active', '']" :paginator="$rows">
      @forelse ($rows as $row)
        <tr>
          <td class="p-3 font-semibold">{{ $row->name }}</td>
          <td class="p-3 text-sm">{{ $row->category ?: '—' }}</td>
          <td class="p-3 text-sm">{{ $row->type }}</td>
          <td class="p-3 text-xs text-text-subtle">{{ \Illuminate\Support\Str::limit($row->body, 60) ?: '—' }}</td>
          <td class="p-3 text-sm">{{ $row->is_active ? 'Yes' : 'No' }}</td>
          <td class="p-3">
            <div class="flex gap-2">
              <a href="{{ route('admin.platform-templates.edit', $row) }}" class="text-xs font-semibold text-green-600 hover:underline">Edit</a>
              <form method="POST" action="{{ route('admin.platform-templates.destroy', $row) }}" data-confirm="Delete this template?" data-confirm-title="Delete template" data-confirm-variant="danger">
                @csrf @method('DELETE')
                <button class="text-xs font-semibold text-red-600 hover:underline">Delete</button>
              </form>
            </div>
          </td>
        </tr>
      @empty
        <tr><td colspan="6" class="p-6 text-center text-sm text-text-subtle">No templates yet.</td></tr>
      @endforelse
    </x-ui.data-table>
  </div>
</x-admin.layout>
