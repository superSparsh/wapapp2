<x-admin.layout title="Admins - Admin" active="admin.admins.index">
  <div class="flex flex-wrap items-start justify-between gap-3 p-4">
    <div>
      <h1 class="text-2xl font-bold text-text-primary">Admins</h1>
      <p class="text-sm text-text-subtle opacity-70">Platform staff accounts.</p>
    </div>
    <a href="{{ route('admin.admins.create') }}" class="rounded-lg bg-green-500 px-3 py-2 text-xs font-semibold text-white">Add admin</a>
  </div>

  <div class="p-4 pt-0">
    <x-ui.data-table :headers="['Name', 'Email', 'Role', 'Status', 'Last login', 'Actions']" :paginator="$admins">
      @forelse ($admins as $admin)
        <tr>
          <td class="p-3 font-semibold">{{ $admin->name }}</td>
          <td class="p-3 text-sm">{{ $admin->email }}</td>
          <td class="p-3 text-sm">{{ $admin->adminRole?->name ?: '—' }}</td>
          <td class="p-3 text-sm">{{ $admin->is_active ? 'Active' : 'Disabled' }}</td>
          <td class="p-3 text-sm text-text-subtle">{{ optional($admin->last_login_at)->diffForHumans() ?: '—' }}</td>
          <td class="p-3">
            <div class="flex gap-2">
              <a href="{{ route('admin.admins.edit', $admin) }}" class="text-xs font-semibold text-green-600 hover:underline">Edit</a>
              <form method="POST" action="{{ route('admin.admins.toggle-status', $admin) }}">
                @csrf
                <button class="text-xs font-semibold hover:underline">{{ $admin->is_active ? 'Disable' : 'Enable' }}</button>
              </form>
            </div>
          </td>
        </tr>
      @empty
        <tr><td colspan="6" class="p-6 text-center text-sm text-text-subtle">No admins.</td></tr>
      @endforelse
    </x-ui.data-table>
  </div>
</x-admin.layout>
