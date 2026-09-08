@props(['title' => null, 'active' => 'dashboard'])

<x-layouts.app :title="$title ?? 'WapApp'" :active="$active">
  <div class="flex flex-col bg-surface">
    <div class="grid gap-4 p-4 lg:grid-cols-[288px_1fr]">
      <x-team.sidebar />
      <div class="min-w-0 flex flex-col">
        {{ $slot }}
      </div>
    </div>
  </div>
</x-layouts.app>
