@props(['title', 'headerTitle' => null, 'active' => 'profile.index'])

<x-layouts.app :title="$title" :active="$active" :headerTitle="$headerTitle ?? 'Profile'">
  <div class="bg-surface p-4 lg:p-6">
    {{ $slot }}
  </div>
</x-layouts.app>
