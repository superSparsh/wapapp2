@props(['active' => 'access-token'])

<div class="flex flex-wrap gap-6">
  @foreach ([
    ['id' => 'access-token', 'label' => 'Access Token', 'route' => 'integration.calendly'],
    ['id' => 'notifications', 'label' => 'Notifications', 'route' => 'integration.calendly.connected'],
    ['id' => 'events', 'label' => 'Your Events', 'route' => 'integration.calendly.events'],
  ] as $tab)
    <a
      href="{{ route($tab['route']) }}"
      @class([
        'fd-tab inline-flex w-[140px] items-center justify-center rounded-lg border border-border-sidebar px-5 py-2 text-sm font-medium leading-[1.4] shadow-[0px_0px_2px_rgba(0,0,0,0.04)] transition-colors',
        'bg-green-500 text-primary-2' => $active === $tab['id'],
        'bg-elevated text-text-primary opacity-50 hover:opacity-100' => $active !== $tab['id'],
      ])
    >
      {{ $tab['label'] }}
    </a>
  @endforeach
</div>
