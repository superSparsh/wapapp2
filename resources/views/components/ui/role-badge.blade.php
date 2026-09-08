@props(['label', 'variant' => 'member', 'active' => true])

@php
  $variants = [
    'member' => ['bg' => 'bg-stat-blue/15', 'text' => 'text-stat-blue', 'icon' => 'frame-badge', 'label' => 'Member'],
    'manager' => ['bg' => 'bg-green-50', 'text' => 'text-green-700', 'icon' => 'teacher', 'label' => 'Manager'],
    'agent' => ['bg' => 'bg-stat-blue/15', 'text' => 'text-stat-blue', 'icon' => 'teacher', 'label' => 'Teacher'],
  ];
  $style = $variants[$variant] ?? $variants['member'];
@endphp

<span class="inline-flex items-center gap-1 rounded px-2 py-1 {{ $style['bg'] }}">
  @if ($style['icon'] === 'teacher')
    <img src="{{ asset('images/team/teacher.svg') }}" alt="" class="size-3" width="12" height="12">
  @else
    <img src="{{ asset('images/team/frame-badge.svg') }}" alt="" class="size-3" width="12" height="12">
  @endif
  <span class="text-[10px] font-medium leading-[1.2] {{ $style['text'] }}" style="font-family: var(--font-display)">{{ $style['label'] ?? $label }}</span>
</span>
