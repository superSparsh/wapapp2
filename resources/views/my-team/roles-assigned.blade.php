@php
  $permissions = [
    ['name' => 'Template', 'enabled' => true],
    ['name' => 'Audience', 'enabled' => false],
    ['name' => 'Campaign', 'enabled' => true],
    ['name' => 'Inbox', 'enabled' => true],
  ];

  $assignedMembers = ['members', 'members', 'members'];
@endphp

<x-layouts.app title="Roles & Access - WapApp" active="my-team.index">
  <div class="flex flex-col bg-surface">
    <div class="flex flex-col gap-1 p-4">
      <h1 class="text-2xl font-bold leading-[1.5] text-text-primary">Roles & Access</h1>
      <p class="max-w-[854px] text-sm font-normal leading-[1.4] text-text-subtle opacity-50">
        Create personalized message templates for initiating conversation with your customers.
      </p>
    </div>

    <section class="flex flex-col gap-4 bg-surface p-4 pt-0">
      <div class="flex flex-col gap-4 rounded-lg bg-elevated p-3">
        <h2 class="text-xl font-semibold leading-[1.4] text-text-primary">Permissions</h2>

        <div class="w-full max-w-[576px] overflow-hidden rounded-xl border border-green-50 bg-elevated shadow-[0px_4px_6px_rgba(0,0,0,0.04)]">
          @foreach ($permissions as $permission)
            <div @class([
              'flex items-center gap-2 px-2 py-1.5',
              'border-t border-divider' => ! $loop->first,
            ])>
              <div class="min-w-0 flex-1 p-2">
                <p class="text-[13px] font-semibold leading-[1.5] text-text-subtle">{{ $permission['name'] }}</p>
              </div>
              <div class="min-w-0 flex-1 p-2">
                <p class="text-[13px] font-normal leading-[1.5] text-text-body">Allow Read / Write Access</p>
              </div>
              <div class="flex min-w-0 flex-1 items-center justify-center gap-2.5 p-2">
                <span class="flex-1 text-right text-[13px] font-normal leading-[1.5] text-text-body">No</span>
                <x-ui.toggle-switch :active="$permission['enabled']" />
                <span class="flex-1 text-[13px] font-normal leading-[1.5] text-text-body">Yes</span>
              </div>
            </div>
          @endforeach
        </div>

        <div class="flex flex-col gap-2">
          <h2 class="text-xl font-semibold leading-[1.4] text-text-primary">Team Members</h2>

          <div class="flex w-full max-w-[564px] flex-col gap-2">
            <label class="text-sm font-semibold leading-[1.4] text-text-primary">
              Assign Members to this Manager&nbsp; <span class="text-[red]">*</span>
            </label>

            <div class="flex min-h-[52px] flex-wrap items-center gap-3 rounded-xl border border-border bg-elevated p-3.5">
              @foreach ($assignedMembers as $member)
                <span class="inline-flex items-center justify-center gap-3 overflow-hidden rounded-lg bg-green-50 px-4 py-3">
                  <span class="text-sm font-medium leading-5 text-primary-2">{{ $member }}</span>
                  <button
                    type="button"
                    aria-label="Remove member"
                    class="inline-flex items-center rounded-full border-[0.5px] border-green-500 bg-green-100 p-1 transition-opacity hover:opacity-80"
                  >
                    <img src="{{ asset('images/team/chip-close.svg') }}" alt="" class="size-3" width="12" height="12">
                  </button>
                </span>
              @endforeach
            </div>

            <p class="text-sm font-medium leading-[1.4] text-text-muted">
              Hold Ctrl/Cmd to select multiple. Saving will replace previous assignments for this manager.
            </p>
          </div>
        </div>
      </div>

      <div class="flex flex-wrap items-center justify-end gap-2.5">
        <a
          href="{{ route('my-team.create') }}"
          class="fd-btn-sm inline-flex items-center justify-center rounded border border-green-500 bg-elevated px-6 py-3 text-xs font-semibold leading-[1.5] text-green-500 transition-opacity hover:opacity-90"
        >
          Cancel
        </a>
        <a
          href="{{ route('my-team.index') }}"
          class="fd-btn-sm inline-flex items-center justify-center rounded bg-green-500 px-6 py-3 text-xs font-semibold leading-[1.5] text-primary-2 transition-opacity hover:opacity-90"
        >
          Save
        </a>
      </div>
    </section>
  </div>
</x-layouts.app>
