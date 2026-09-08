@props(['roles', 'selected' => 'member'])

@php use App\Enums\TeamMemberRole; @endphp

<div class="flex flex-col gap-6 border-b border-divider p-5 sm:p-6">
  <div class="flex flex-col gap-1">
    <h2 class="text-base font-semibold text-text-primary">Role</h2>
    <p class="text-sm text-text-subtle opacity-70">Managers can manage assigned members. Team members use permissions you set later.</p>
  </div>

  <div class="grid gap-3 sm:grid-cols-2">
    @foreach ($roles as $role)
      @php $isManager = $role === TeamMemberRole::Manager; @endphp
      <label class="group flex cursor-pointer flex-col gap-2 rounded-xl border-2 border-border bg-surface p-4 transition-colors hover:border-green-500/40 has-[:checked]:border-green-500 has-[:checked]:bg-green-500/5">
        <span class="flex items-center gap-2">
          <input type="radio" name="role" value="{{ $role->value }}" class="sr-only" @checked($selected === $role->value)>
          <span class="flex size-4 shrink-0 items-center justify-center rounded-full border-2 border-border bg-elevated group-has-[:checked]:border-green-500 group-has-[:checked]:bg-green-500">
            <span class="size-1.5 rounded-full bg-white opacity-0 group-has-[:checked]:opacity-100"></span>
          </span>
          <span class="text-sm font-semibold text-text-primary group-has-[:checked]:text-green-500">
            {{ $isManager ? 'Manager' : 'Team member' }}
          </span>
        </span>
        <span class="pl-6 text-xs leading-relaxed text-text-subtle opacity-80">
          {{ $isManager
            ? 'Create and manage a group of team members, import CSV, and login as members.'
            : 'Handle assigned chats and modules based on permissions you configure.' }}
        </span>
      </label>
    @endforeach
  </div>
  @error('role')<p class="text-xs text-red-500">{{ $message }}</p>@enderror
</div>
