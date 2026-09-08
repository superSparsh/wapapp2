@php
    use App\Domains\Team\Support\TeamActor;

    $teamItems = TeamActor::isOwner()
        ? config('team-navigation')
        : (TeamActor::isManager()
            ? [['route' => 'manager.team.index', 'label' => 'My Team', 'icon' => 'document-text']]
            : []);

    $iconMap = [
        'smart-home' => 'smart-home',
        'document-text' => 'ticket',
        'data' => 'circle-square',
        'task-square' => 'box',
    ];
@endphp

<div
  id="user-panel"
  class="absolute right-0 top-[calc(100%+8px)] z-50 hidden w-[320px] overflow-hidden rounded-2xl bg-elevated px-4 py-3 shadow-[0px_8px_24px_rgba(0,0,0,0.12)]"
  role="menu"
>
  <div class="flex items-center gap-3 rounded-md p-3">
    <img
      src="{{ $currentAccountAvatar ?? asset('images/profile/photo-sample.png') }}"
      alt=""
      class="size-12 shrink-0 rounded-full object-cover"
      width="48"
      height="48"
    >
    <div class="min-w-0 flex-1">
      <p class="truncate text-base font-semibold leading-[1.4] text-text-primary">{{ $currentAccountName ?? 'Account' }}</p>
      <p class="truncate text-sm font-normal leading-[1.4] text-text-subtle">{{ $currentAccountEmail ?? '' }}</p>
    </div>
    <span class="fd-status-chip shrink-0 rounded bg-[rgba(0,128,0,0.1)] px-2 py-1 text-green-700">Active</span>
  </div>

  <img src="{{ asset('images/icons/header/panel-divider.svg') }}" alt="" class="my-3 block w-full" width="288" height="1">

  <nav class="flex flex-col gap-3">
    @foreach ($teamItems as $item)
      @php
        $isActive = request()->routeIs($item['route']) || request()->routeIs($item['route'] . '.*');
        $iconName = $iconMap[$item['icon']] ?? 'ticket';
      @endphp
      <a
        href="{{ route($item['route']) }}"
        @class([
          'fd-nav-item flex items-center gap-3 rounded-md p-3 transition-colors',
          'border-l-4 border-[#283756] bg-green-500 text-text-subtle' => $isActive,
          'text-blue-200 hover:bg-surface' => ! $isActive,
        ])
        role="menuitem"
      >
        <x-icons.sidebar-icon :name="$iconName" :active="$isActive" class="size-5 shrink-0" />
        <span class="min-w-0 flex-1">{{ $item['label'] }}</span>
      </a>
    @endforeach

    @if (! empty($canAccessAdminView))
      <a
        href="{{ route('admin.dashboard') }}"
        class="fd-nav-item flex items-center gap-3 rounded-md p-3 text-blue-200 transition-colors hover:bg-surface"
        role="menuitem"
      >
        <x-icons.sidebar-icon name="box" :active="false" class="size-5 shrink-0" />
        <span class="min-w-0 flex-1">Admin View</span>
      </a>
    @endif
  </nav>

  <form action="{{ route('logout') }}" method="POST">
    @csrf
    <button
      type="submit"
      class="mt-3 flex h-11 w-full items-center gap-3 overflow-hidden rounded-xl bg-[rgba(255,56,60,0.1)] p-3 text-sm font-medium leading-5 text-[#ff383c] hover:bg-[rgba(255,56,60,0.15)]"
      role="menuitem"
    >
      <img src="{{ asset('images/icons/header/logout.svg') }}" alt="" class="size-4 shrink-0" width="16" height="16">
      <span>Logout</span>
    </button>
  </form>
</div>
