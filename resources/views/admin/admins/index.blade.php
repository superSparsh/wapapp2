<x-admin.layout title="Admins - Admin" active="admin.admins.index">
  <div class="flex flex-wrap items-start justify-between gap-3 p-4">
    <div>
      <h1 class="text-2xl font-bold text-text-primary">Admins</h1>
      <p class="text-sm text-text-subtle opacity-70">Platform staff accounts.</p>
    </div>
    <a href="{{ route('admin.admins.create') }}" class="rounded-lg bg-green-500 px-3 py-2 text-xs font-semibold text-white">Add admin</a>
  </div>

  <x-admin.filter-bar
    :action="route('admin.admins.index')"
    :search="$filters['q'] ?? ''"
    search-placeholder="Search name or email…"
    :show-dates="false"
    :sort="$filters['sort'] ?? 'id'"
    :direction="$filters['direction'] ?? 'desc'"
    :sort-options="$sortOptions"
  />

  <div class="p-4 pt-0">
    <x-ui.data-table :headers="['Name', 'Email', 'Role', 'Status', 'Last login', 'Actions']" :paginator="$admins">
      @forelse ($admins as $admin)
        <tr class="bg-elevated">
          <td class="fd-table-cell p-2 align-middle font-semibold">{{ $admin->name }}</td>
          <td class="fd-table-cell p-2 align-middle text-sm">{{ $admin->email }}</td>
          <td class="fd-table-cell p-2 align-middle text-sm">{{ $admin->adminRole?->name ?: '—' }}</td>
          <td class="fd-table-cell p-2 align-middle text-sm">{{ $admin->is_active ? 'Active' : 'Disabled' }}</td>
          <td class="fd-table-cell p-2 align-middle text-sm text-text-subtle">{{ format_ist($admin->last_login_at) }}</td>
          <td class="w-[120px] p-2 align-middle">
            <x-ui.table-actions
              :actions="['edit', 'toggle']"
              :links="[
                'edit' => route('admin.admins.edit', $admin),
                'toggle' => route('admin.admins.toggle-status', $admin),
              ]"
              :methods="['toggle' => 'POST']"
            />
          </td>
        </tr>
      @empty
        <tr><td colspan="6" class="p-6 text-center text-sm text-text-subtle">No admins.</td></tr>
      @endforelse
    </x-ui.data-table>
  </div>
</x-admin.layout>
