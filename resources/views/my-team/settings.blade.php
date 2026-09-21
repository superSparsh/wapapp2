<x-layouts.app title="Team Settings - WapApp" active="my-team.index">
  <div class="flex flex-col gap-4 bg-surface p-4">
    <div class="flex flex-col gap-1">
      <h1 class="text-2xl font-bold text-text-primary">Team Settings</h1>
      <p class="text-sm text-text-subtle opacity-50">
        When enabled, new incoming chats are assigned automatically to your active team members (least open chats first). Turn off to assign manually.
      </p>
    </div>

    @if (session('status'))
      <div class="rounded-lg border border-green-500/30 bg-green-50 px-4 py-3 text-sm text-green-700">{{ session('status') }}</div>
    @endif

    <form method="post" action="{{ route('my-team.settings.update') }}" class="max-w-xl rounded-lg bg-elevated p-4">
      @csrf
      @method('PATCH')
      <label class="flex items-center gap-3">
        <input type="checkbox" name="auto_assign_chats" value="1" @checked($autoAssignChats) class="size-4 rounded border-border text-green-500">
        <span class="text-sm text-text-primary">Auto-assign new chats to available team members</span>
      </label>
      <button type="submit" class="mt-4 rounded bg-green-500 px-4 py-2 text-sm font-semibold text-primary-2">Save</button>
    </form>
  </div>
</x-layouts.app>
