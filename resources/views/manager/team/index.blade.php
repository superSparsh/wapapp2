<x-layouts.app title="My Team - WapApp" active="manager.team.index">
  <div class="flex flex-col bg-surface" data-team-root>
    <div class="flex flex-col gap-4 p-4">
      <div class="flex flex-col gap-1">
        <h1 class="text-2xl font-bold leading-[1.5] text-text-primary">Assigned Team Members</h1>
        <x-team.plan-limit :usage="$usage ?? null" />
      </div>

      @if (session('status'))
        <div class="rounded-lg border border-green-500/30 bg-green-50 px-4 py-3 text-sm text-green-700">{{ session('status') }}</div>
      @endif

      @if (session('import_report'))
        @php $report = session('import_report'); @endphp
        @if (! empty($report['errors']))
          <div class="rounded-lg border border-amber-500/30 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            <p class="font-semibold">Import completed with row errors:</p>
            <ul class="mt-2 list-disc pl-5">
              @foreach ($report['errors'] as $line => $message)
                <li>Row {{ $line }}: {{ $message }}</li>
              @endforeach
            </ul>
          </div>
        @endif
      @endif

      <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <form method="get" action="{{ route('manager.team.index') }}" class="flex w-full max-w-[550px] items-center gap-3 overflow-hidden rounded-lg bg-elevated p-3">
          <x-icons.nav-icon name="search" class="size-5 shrink-0 opacity-60" />
          <input type="search" name="q" value="{{ $search ?? '' }}" placeholder="Search by name, email or phone" class="fd-filter-placeholder min-w-0 flex-1 bg-transparent focus:outline-none">
        </form>
        <div class="flex flex-wrap items-center gap-2 self-end sm:self-auto">
          <a href="{{ route('manager.team.import') }}" class="fd-btn inline-flex items-center justify-center rounded border border-green-500 px-4 py-3 text-sm font-semibold text-green-500">Import CSV</a>
          @if ($canCreate)
            <a href="{{ route('manager.team.create') }}" class="fd-btn inline-flex items-center justify-center gap-2 rounded bg-green-500 px-4 py-3 text-sm font-semibold text-primary-2">Create</a>
          @endif
        </div>
      </div>
    </div>

    <section class="bg-surface p-4 pt-0">
      <div class="overflow-hidden rounded-xl bg-elevated shadow-[0px_4px_6px_rgba(0,0,0,0.04)]">
        <div class="overflow-x-auto">
          <table class="w-full min-w-[1000px] text-left">
            <thead>
              <tr class="bg-elevated">
                <th class="p-2 text-[13px] font-medium text-text-body">Name</th>
                <th class="p-2 text-[13px] font-medium text-text-body">Phone</th>
                <th class="p-2 text-[13px] font-medium text-text-body">Email</th>
                <th class="p-2 text-center text-[13px] font-medium text-text-body">Assigned Conversations</th>
                <th class="p-2 text-[13px] font-medium text-text-body">Status</th>
                <th class="p-2 text-[13px] font-medium text-text-body">In/Active</th>
                <th class="p-2 text-center text-[13px] font-medium text-text-body">Actions</th>
              </tr>
            </thead>
            <tbody>
              @forelse ($members as $member)
                <tr class="border-t border-divider bg-elevated" data-team-row="{{ $member['uuid'] }}">
                  <td class="p-2 text-[13px] font-semibold text-text-subtle">{{ $member['name'] }}</td>
                  <td class="p-2 text-[13px] text-text-body">{{ $member['phone'] ?: '—' }}</td>
                  <td class="p-2 text-[13px] text-text-body">{{ $member['email'] }}</td>
                  <td class="p-2 text-center text-[13px] text-text-body">{{ $member['assigned_conversations'] }}</td>
                  <td class="p-2" data-team-status-label>
                    @if ($member['is_active'])
                      <span class="inline-flex rounded bg-[rgba(0,128,0,0.1)] px-2 py-1 text-[10px] font-medium text-[green]">Active</span>
                    @else
                      <span class="inline-flex rounded bg-[rgba(0,0,0,0.1)] px-2 py-1 text-[10px] font-medium text-text-muted">Inactive</span>
                    @endif
                  </td>
                  <td class="p-2">
                    <x-ui.toggle-switch :active="$member['is_active']" data-team-status-toggle data-status-url="{{ $member['toggle_url'] }}" />
                  </td>
                  <td class="p-2">
                    <div class="flex items-center justify-center gap-4">
                      <a href="{{ $member['roles_url'] }}" aria-label="Roles"><img src="{{ asset('images/team/profile-2user.svg') }}" alt="" class="size-5"></a>
                      <a href="{{ $member['edit_url'] }}" aria-label="Edit"><img src="{{ asset('images/team/edit.svg') }}" alt="" class="size-5"></a>
                      <form method="post" action="{{ $member['login_as_url'] }}">@csrf<button type="submit" class="text-xs font-semibold text-green-500">Login as</button></form>
                      <form method="post" action="{{ $member['delete_url'] }}" data-confirm="Delete this team member?" data-confirm-title="Delete team member" data-confirm-label="Delete">@csrf @method('DELETE')<button type="submit"><img src="{{ asset('images/team/trash.svg') }}" alt="" class="size-5"></button></form>
                    </div>
                  </td>
                </tr>
              @empty
                <tr><td colspan="7" class="p-8 text-center text-sm text-text-body/70">No assigned team members yet.</td></tr>
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
