<x-admin.layout title="Admin Roles - Admin" active="admin.admin-roles.index">
  <div class="flex flex-wrap items-start justify-between gap-3 p-4">
    <div>
      <h1 class="text-2xl font-bold text-text-primary">Roles</h1>
      <p class="text-sm text-text-subtle opacity-70">Permission sets assigned to platform staff.</p>
    </div>
    <a href="{{ route('admin.admin-roles.create') }}" class="rounded-lg bg-green-500 px-3 py-2 text-xs font-semibold text-white">Add role</a>
  </div>

  <div class="p-4 pt-0">
    <x-ui.data-table :headers="['Name', 'Permissions', 'Admins', 'Active', '']" :paginator="$roles">
      @forelse ($roles as $role)
        <tr>
          <td class="p-3">
            <div class="font-semibold">{{ $role->name }}</div>
            <div class="text-xs text-text-subtle">{{ $role->slug }}</div>
          </td>
          <td class="p-3 text-xs text-text-subtle">{{ implode(', ', $role->permissions ?? []) ?: '—' }}</td>
          <td class="p-3 text-sm">{{ $role->admins_count }}</td>
          <td class="p-3 text-sm">{{ $role->is_active ? 'Yes' : 'No' }}</td>
          <td class="p-3">
            <div class="flex gap-2">
              <a href="{{ route('admin.admin-roles.edit', $role) }}" class="text-xs font-semibold text-green-600 hover:underline">Edit</a>
              <form method="POST" action="{{ route('admin.admin-roles.destroy', $role) }}" data-confirm="Delete this role?" data-confirm-title="Delete role" data-confirm-variant="danger">
                @csrf @method('DELETE')
                <button class="text-xs font-semibold text-red-600 hover:underline">Delete</button>
              </form>
            </div>
          </td>
        </tr>
      @empty
        <tr><td colspan="5" class="p-6 text-center text-sm text-text-subtle">No roles yet.</td></tr>
      @endforelse
    </x-ui.data-table>
  </div>
</x-admin.layout>
