<x-layouts.app title="My Team - WapApp" active="my-team.index">
  <div class="flex flex-col bg-surface">
    <div class="flex flex-col gap-4 p-4">
      <div class="flex flex-col gap-1">
        <h1 class="text-2xl font-bold leading-[1.5] text-text-primary">Team Member List</h1>
        <x-team.plan-limit :usage="$usage ?? null" />
      </div>

      @if (session('status'))
        <div class="rounded-lg border border-green-500/30 bg-green-50 px-4 py-3 text-sm text-green-700">
          {{ session('status') }}
        </div>
      @endif

      <x-ui.listing-toolbar
        :action="route('my-team.index')"
        search-name="q"
        :search-value="$search ?? ''"
        search-placeholder="Search by name, email or phone"
        :current-sort="$currentSort ?? 'created_at'"
        :current-direction="$currentDirection ?? 'desc'"
        :sort-options="[
          ['value' => 'created_at', 'label' => 'Newest first', 'direction' => 'desc'],
          ['value' => 'created_at', 'label' => 'Oldest first', 'direction' => 'asc'],
          ['value' => 'name', 'label' => 'Name A–Z', 'direction' => 'asc'],
          ['value' => 'name', 'label' => 'Name Z–A', 'direction' => 'desc'],
          ['value' => 'phone', 'label' => 'Phone A–Z', 'direction' => 'asc'],
          ['value' => 'email', 'label' => 'Email A–Z', 'direction' => 'asc'],
          ['value' => 'status', 'label' => 'Active first', 'direction' => 'desc'],
        ]"
      >
        <x-slot:actions>
          <a
            href="{{ route('my-team.settings') }}"
            class="fd-btn inline-flex items-center justify-center gap-2 rounded border border-border-light bg-elevated px-4 py-3 text-sm font-semibold leading-[1.5] text-primary-2 transition-colors hover:bg-surface"
          >
            Settings
          </a>
          @if ($canCreate)
            <a
              href="{{ route('my-team.create') }}"
              class="fd-btn inline-flex items-center justify-center gap-2 rounded bg-green-500 px-4 py-3 text-sm font-semibold leading-[1.5] text-primary-2 transition-opacity hover:opacity-90"
            >
              <img src="{{ asset('images/team/add.svg') }}" alt="" class="size-5" width="20" height="20">
              Create
            </a>
          @endif
        </x-slot:actions>
      </x-ui.listing-toolbar>
    </div>

    <section class="bg-surface p-4 pt-0" data-team-root>
      <div class="overflow-hidden rounded-xl bg-elevated shadow-[0px_4px_6px_rgba(0,0,0,0.04)]">
        <div class="overflow-x-auto">
          <table class="w-full min-w-[1000px] text-left">
            <thead>
              <tr class="bg-elevated">
                <th class="p-2 text-[13px] font-medium leading-[1.5] text-text-body">Name</th>
                <th class="p-2 text-[13px] font-medium leading-[1.5] text-text-body">Phone Number</th>
                <th class="w-[180px] p-2 text-[13px] font-medium leading-[1.5] text-text-body">Email</th>
                <th class="w-[106px] p-2 text-center text-[13px] font-medium leading-[1.5] text-text-body">Assigned Conversations</th>
                <th class="p-2 text-[13px] font-medium leading-[1.5] text-text-body">Role</th>
                <th class="p-2 text-[13px] font-medium leading-[1.5] text-text-body">Status</th>
                <th class="p-2 text-[13px] font-medium leading-[1.5] text-text-body">In/Active</th>
                <th class="p-2 text-center text-[13px] font-medium leading-[1.5] text-text-body">Actions</th>
              </tr>
            </thead>
            <tbody>
              @forelse ($members as $member)
                <tr class="border-t border-divider bg-elevated" data-team-row="{{ $member['uuid'] }}">
                  <td class="p-2 text-[13px] font-semibold leading-[1.5] text-text-subtle">{{ $member['name'] }}</td>
                  <td class="p-2 text-[13px] font-normal leading-[1.5] text-text-body">{{ $member['phone'] ?: '—' }}</td>
                  <td class="w-[180px] p-2 text-[13px] font-normal leading-[1.5] text-text-body">{{ $member['email'] }}</td>
                  <td class="w-[106px] p-2 text-center text-[13px] font-normal leading-[1.5] text-text-body">{{ $member['assigned_conversations'] }}</td>
                  <td class="p-2">
                    <x-ui.role-badge :label="$member['role_label']" :variant="$member['role']" />
                  </td>
                  <td class="p-2" data-team-status-label>
                    @if ($member['is_active'])
                      <span class="inline-flex items-center justify-center rounded bg-[rgba(0,128,0,0.1)] px-2 py-1 text-[10px] font-medium leading-[1.2] text-[green]">Active</span>
                    @else
                      <span class="inline-flex items-center justify-center rounded bg-[rgba(0,0,0,0.1)] px-2 py-1 text-[10px] font-medium leading-[1.2] text-text-muted">Inactive</span>
                    @endif
                  </td>
                  <td class="p-2">
                    <x-ui.toggle-switch
                      :active="$member['is_active']"
                      data-team-status-toggle
                      data-status-url="{{ $member['toggle_url'] }}"
                      aria-label="Toggle team member status"
                    />
                  </td>
                  <td class="p-2">
                    <div class="flex items-center justify-center gap-6">
                      <a href="{{ $member['roles_url'] }}" aria-label="Roles and access">
                        <img src="{{ asset('images/team/profile-2user.svg') }}" alt="" class="size-5" width="20" height="20">
                      </a>
                      <a href="{{ $member['edit_url'] }}" aria-label="Edit member">
                        <img src="{{ asset('images/team/edit.svg') }}" alt="" class="size-5" width="20" height="20">
                      </a>
                      <form method="post" action="{{ $member['delete_url'] }}" data-confirm="Delete this team member?" data-confirm-title="Delete team member" data-confirm-label="Delete">
                        @csrf
                        @method('DELETE')
                        <button type="submit" aria-label="Delete member">
                          <img src="{{ asset('images/team/trash.svg') }}" alt="" class="size-5" width="20" height="20">
                        </button>
                      </form>
                    </div>
                  </td>
                </tr>
              @empty
                <tr>
                  <td colspan="8" class="p-8 text-center text-sm text-text-body/70">No team members yet.</td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>
        @if ($members->hasPages())
          <x-ui.table-pagination :paginator="$members" />
        @endif
      </div>
    </section>
  </div>
</x-layouts.app>
