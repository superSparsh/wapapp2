<x-layouts.app title="Roles & Access - WapApp" active="my-team.index">
  <div class="flex flex-col bg-surface">
    <div class="flex flex-col gap-1 p-4">
      <h1 class="text-2xl font-bold leading-[1.5] text-text-primary">Roles &amp; Access</h1>
      <p class="max-w-[854px] text-sm font-normal leading-[1.4] text-text-subtle opacity-50">
        Configure module access for {{ $member->displayName() }}.
      </p>
    </div>

    <section class="flex flex-col gap-4 bg-surface p-4 pt-0">
      <form class="flex flex-col gap-4 rounded-lg bg-elevated p-3" action="{{ $formAction ?? route('my-team.roles.update', $member) }}" method="post">
        @csrf
        @method('PUT')

        <h2 class="text-xl font-semibold leading-[1.4] text-text-primary">Permissions</h2>

        <div class="w-full max-w-[576px] overflow-hidden rounded-xl border border-green-50 bg-elevated shadow-[0px_4px_6px_rgba(0,0,0,0.04)]">
          @foreach ($permissionLabels as $key => $label)
            <div @class(['flex items-center gap-2 px-2 py-1.5', 'border-t border-divider' => ! $loop->first])>
              <div class="min-w-0 flex-1 p-2">
                <p class="text-[13px] font-semibold leading-[1.5] text-text-subtle">{{ $label }}</p>
              </div>
              <div class="min-w-0 flex-1 p-2">
                <p class="text-[13px] font-normal leading-[1.5] text-text-body">Allow read access</p>
              </div>
              <div class="flex min-w-0 flex-1 items-center justify-center gap-2.5 p-2">
                <span class="flex-1 text-right text-[13px] text-text-body">No</span>
                <input type="hidden" name="permissions[{{ $key }}]" value="0">
                <x-ui.toggle-switch
                  :active="! empty($permissions[$key])"
                  data-team-permission-toggle
                  data-permission-input="permissions[{{ $key }}]"
                  aria-label="Toggle {{ $label }} access"
                />
                <input type="checkbox" name="permissions[{{ $key }}]" value="1" class="sr-only" @checked(! empty($permissions[$key])) data-team-permission-checkbox>
                <span class="flex-1 text-[13px] text-text-body">Yes</span>
              </div>
            </div>
          @endforeach
        </div>

        @if ($member->isManager())
          <div class="flex flex-col gap-2">
            <h2 class="text-xl font-semibold leading-[1.4] text-text-primary">Team Members</h2>
            <div class="flex w-full max-w-[564px] flex-col gap-2">
              <label for="assign_members" class="text-sm font-semibold leading-[1.4] text-text-primary">
                Assign Members to this Manager
              </label>
              <x-ui.select id="assign_members" name="member_uuids[]" multiple data-native-select="true" class="min-h-[120px]">
                @foreach ($assignableMembers as $assignable)
                  <option value="{{ $assignable['uuid'] }}" @selected(in_array($assignable['uuid'], $assignedMemberUuids, true))>
                    {{ $assignable['label'] }}
                  </option>
                @endforeach
              </x-ui.select>
              <p class="text-sm font-medium leading-[1.4] text-text-muted">
                Hold Ctrl/Cmd to select multiple. Saving will replace previous assignments for this manager.
              </p>
            </div>
          </div>
        @endif

        <div class="flex flex-wrap items-center justify-end gap-2.5">
          <a href="{{ $cancelUrl ?? route('my-team.index') }}" class="fd-btn-sm inline-flex items-center justify-center rounded border border-green-500 bg-elevated px-6 py-3 text-xs font-semibold text-green-500">Cancel</a>
          <button type="submit" class="fd-btn-sm inline-flex items-center justify-center rounded bg-green-500 px-6 py-3 text-xs font-semibold text-primary-2">Save</button>
        </div>
      </form>
    </section>
  </div>
</x-layouts.app>
