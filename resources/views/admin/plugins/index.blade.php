<x-admin.layout title="Plugins - Admin" active="admin.plugins.index">
  <div class="p-4">
    <h1 class="text-2xl font-bold text-text-primary">Plugins</h1>
    <p class="text-sm text-text-subtle opacity-70">Optional modules that can be switched on per platform.</p>
  </div>

  <form method="POST" action="{{ route('admin.plugins.store') }}" class="mx-4 mb-4 flex flex-wrap items-end gap-3 rounded-[20px] border border-border bg-elevated p-4">
    @csrf
    <label class="flex min-w-[180px] flex-1 flex-col gap-1.5 text-sm">
      <span class="font-semibold">Title</span>
      <input name="title" value="{{ old('title') }}" required class="rounded-lg border border-border px-3 py-2 text-sm">
    </label>
    <label class="flex min-w-[140px] flex-col gap-1.5 text-sm">
      <span class="font-semibold">Key</span>
      <input name="name" value="{{ old('name') }}" class="rounded-lg border border-border px-3 py-2 text-sm" placeholder="auto">
    </label>
    <label class="flex min-w-[120px] flex-col gap-1.5 text-sm">
      <span class="font-semibold">Type</span>
      <input name="type" value="{{ old('type', 'general') }}" class="rounded-lg border border-border px-3 py-2 text-sm">
    </label>
    <label class="flex min-w-[100px] flex-col gap-1.5 text-sm">
      <span class="font-semibold">Version</span>
      <input name="version" value="{{ old('version') }}" class="rounded-lg border border-border px-3 py-2 text-sm" placeholder="1.0.0">
    </label>
    <button class="rounded-lg bg-green-500 px-4 py-2 text-xs font-semibold text-white">Register plugin</button>
  </form>

  @if ($errors->any())
    <div class="mx-4 mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-600">{{ $errors->first() }}</div>
  @endif

  <div class="p-4 pt-0">
    <x-ui.data-table :headers="['Plugin', 'Type', 'Version', 'Enabled', '']" :paginator="$rows">
      @forelse ($rows as $row)
        <tr>
          <td class="p-3">
            <div class="font-semibold">{{ $row->title }}</div>
            <div class="text-xs text-text-subtle">{{ $row->name }}</div>
          </td>
          <td class="p-3 text-sm">{{ $row->type }}</td>
          <td class="p-3 text-sm">{{ $row->version ?: '—' }}</td>
          <td class="p-3 text-sm">{{ $row->is_enabled ? 'Yes' : 'No' }}</td>
          <td class="p-3">
            <div class="flex gap-2">
              <form method="POST" action="{{ route('admin.plugins.toggle', $row) }}">@csrf
                <button class="text-xs font-semibold text-green-600 hover:underline">{{ $row->is_enabled ? 'Disable' : 'Enable' }}</button>
              </form>
              <form method="POST" action="{{ route('admin.plugins.destroy', $row) }}" data-confirm="Remove this plugin?" data-confirm-title="Remove plugin" data-confirm-variant="danger">
                @csrf @method('DELETE')
                <button class="text-xs font-semibold text-red-600 hover:underline">Remove</button>
              </form>
            </div>
          </td>
        </tr>
      @empty
        <tr><td colspan="5" class="p-6 text-center text-sm text-text-subtle">No plugins registered.</td></tr>
      @endforelse
    </x-ui.data-table>
  </div>
</x-admin.layout>
