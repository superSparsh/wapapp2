<x-admin.layout title="Announcements - Admin" active="admin.announcements.index">
  <div class="flex flex-wrap items-start justify-between gap-3 p-4">
    <div>
      <h1 class="text-2xl font-bold text-text-primary">Announcements</h1>
      <p class="text-sm text-text-subtle opacity-70">Platform-wide notices for tenants.</p>
    </div>
    <a href="{{ route('admin.announcements.create') }}" class="rounded-lg bg-green-500 px-3 py-2 text-xs font-semibold text-white">New announcement</a>
  </div>

  @if (session('status'))
    <div class="mx-4 mb-4 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">{{ session('status') }}</div>
  @endif

  <div class="p-4 pt-0">
    <x-ui.data-table :headers="['Title', 'Active', 'Window', '']" :paginator="$announcements">
      @forelse ($announcements as $row)
        <tr>
          <td class="p-3">
            <div class="font-semibold">{{ $row->title }}</div>
            <div class="line-clamp-1 text-xs text-text-subtle">{{ \Illuminate\Support\Str::limit($row->body, 80) }}</div>
          </td>
          <td class="p-3 text-sm">{{ $row->is_active ? 'Yes' : 'No' }}</td>
          <td class="p-3 text-xs text-text-subtle">
            {{ optional($row->starts_at)?->toDateString() ?: '—' }} → {{ optional($row->ends_at)?->toDateString() ?: '—' }}
          </td>
          <td class="p-3">
            <div class="flex flex-wrap gap-2">
              <a href="{{ route('admin.announcements.edit', $row) }}" class="text-xs font-semibold text-green-600 hover:underline">Edit</a>
              <form method="POST" action="{{ route('admin.announcements.toggle', $row) }}">@csrf
                <button class="text-xs font-semibold text-text-subtle hover:underline">{{ $row->is_active ? 'Disable' : 'Enable' }}</button>
              </form>
              <form method="POST" action="{{ route('admin.announcements.destroy', $row) }}">@csrf @method('DELETE')
                <button class="text-xs font-semibold text-red-600 hover:underline">Delete</button>
              </form>
            </div>
          </td>
        </tr>
      @empty
        <tr><td colspan="4" class="p-6 text-center text-sm text-text-subtle">No announcements yet.</td></tr>
      @endforelse
    </x-ui.data-table>
  </div>
</x-admin.layout>
