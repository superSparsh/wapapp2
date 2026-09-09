<x-admin.layout title="Admin Roles - Admin" active="admin.admin-roles.index">
  <div class="flex flex-wrap items-start justify-between gap-3 p-4">
    <div>
      <h1 class="text-2xl font-bold text-text-primary">Roles</h1>
      <p class="text-sm text-text-subtle opacity-70">Permission sets assigned to platform staff.</p>
    </div>
    <a href="{{ route('admin.admin-roles.create') }}" class="rounded-lg bg-green-500 px-3 py-2 text-xs font-semibold text-white">Add role</a>
  </div>

  <x-admin.filter-bar
    :action="route('admin.admin-roles.index')"
    :search="$filters['q'] ?? ''"
    search-placeholder="Search role name…"
    :show-dates="false"
    :sort="$filters['sort'] ?? 'name'"
    :direction="$filters['direction'] ?? 'asc'"
    :sort-options="$sortOptions"
  />

  <div class="p-4 pt-0">
    <x-ui.data-table :headers="['Name', 'Permissions', 'Admins', 'Active', 'Actions']" :paginator="$roles">
      @forelse ($roles as $role)
        <tr class="bg-elevated">
          <td class="fd-table-cell p-2 align-middle">
            <div class="fd-table-name">{{ $role->name }}</div>
            <div class="text-xs text-text-subtle">{{ $role->slug }}</div>
          </td>
          <td class="fd-table-cell p-2 align-middle text-xs text-text-subtle">{{ implode(', ', $role->permissions ?? []) ?: '—' }}</td>
          <td class="fd-table-cell p-2 align-middle text-sm">{{ $role->admins_count }}</td>
          <td class="fd-table-cell p-2 align-middle text-sm">{{ $role->is_active ? 'Yes' : 'No' }}</td>
          <td class="w-[120px] p-2 align-middle">
            <x-ui.table-actions
              :actions="['edit', 'trash']"
              :links="[
                'edit' => route('admin.admin-roles.edit', $role),
                'trash' => route('admin.admin-roles.destroy', $role),
              ]"
              :methods="['trash' => 'DELETE']"
              confirm="Delete this role? This action cannot be undone."
              confirm-title="Delete role"
              confirm-label="Delete"
            />
          </td>
        </tr>
      @empty
        <tr><td colspan="5" class="p-6 text-center text-sm text-text-subtle">No roles yet.</td></tr>
      @endforelse
    </x-ui.data-table>
  </div>
</x-admin.layout>
