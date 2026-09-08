<x-layouts.app title="Roles & Access - WapApp" active="manager.team.index">
  <div class="flex flex-col bg-surface">
    <div class="flex flex-col gap-1 p-4">
      <h1 class="text-2xl font-bold leading-[1.5] text-text-primary">Roles &amp; Access</h1>
      <p class="max-w-[854px] text-sm text-text-subtle opacity-50">Configure module access for {{ $member->displayName() }}.</p>
    </div>
    <section class="flex flex-col gap-4 bg-surface p-4 pt-0">
      <form class="flex flex-col gap-4 rounded-lg bg-elevated p-3" action="{{ route('manager.team.roles.update', $member) }}" method="post">
        @csrf
        @method('PUT')
        <h2 class="text-xl font-semibold text-text-primary">Permissions</h2>
        <div class="w-full max-w-[576px] overflow-hidden rounded-xl border border-green-50 bg-elevated">
          @foreach ($permissionLabels as $key => $label)
            <div @class(['flex items-center gap-2 px-2 py-1.5', 'border-t border-divider' => ! $loop->first])>
              <div class="min-w-0 flex-1 p-2 text-[13px] font-semibold text-text-subtle">{{ $label }}</div>
              <div class="flex min-w-0 flex-1 items-center justify-center gap-2.5 p-2">
                <input type="hidden" name="permissions[{{ $key }}]" value="0">
                <x-ui.toggle-switch :active="! empty($permissions[$key])" data-team-permission-toggle data-permission-input="permissions[{{ $key }}]" />
                <input type="checkbox" name="permissions[{{ $key }}]" value="1" class="sr-only" @checked(! empty($permissions[$key])) data-team-permission-checkbox>
              </div>
            </div>
          @endforeach
        </div>
        <div class="flex justify-end gap-2">
          <a href="{{ route('manager.team.index') }}" class="rounded border border-green-500 px-4 py-2 text-xs font-semibold text-green-500">Cancel</a>
          <button type="submit" class="rounded bg-green-500 px-4 py-2 text-xs font-semibold text-primary-2">Save</button>
        </div>
      </form>
    </section>
  </div>
</x-layouts.app>
