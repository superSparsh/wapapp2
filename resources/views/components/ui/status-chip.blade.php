@props(['label', 'variant' => 'default'])

@php
$styles = match($variant) {
    'new' => 'bg-stat-blue/15 text-stat-blue',
    'scheduled' => 'bg-stat-orange/15 text-stat-orange',
    'done' => 'bg-stat-emerald/15 text-stat-emerald',
    'approved' => 'bg-stat-emerald/15 text-stat-emerald',
    'fd-approved' => 'bg-green-50 text-green-700',
    'fd-draft' => 'bg-blue-50 text-primary-2',
    'fd-error' => 'bg-danger/10 text-danger',
    'fd-type' => 'bg-indigo-50 text-indigo-700',
    'fd-category' => 'bg-violet-50 text-violet-700',
    'fd-category-marketing' => 'bg-pink-50 text-pink-700',
    'fd-category-utility' => 'bg-sky-50 text-sky-700',
    'fd-category-auth' => 'bg-amber-50 text-amber-800',
    'fd-category-carousel' => 'bg-fuchsia-50 text-fuchsia-700',
    'fd-category-lto' => 'bg-orange-50 text-orange-700',
    'pending' => 'bg-stat-orange/15 text-stat-orange',
    'rejected' => 'bg-danger/10 text-danger',
    'active' => 'bg-green-50 text-green-700',
    'running' => 'bg-teal-50 text-teal-700',
    'sending' => 'bg-teal-50 text-teal-700',
    'paused' => 'bg-divider text-text-muted',
    'cancelled' => 'bg-danger/10 text-danger',
    'inactive' => 'bg-blue-50 text-primary-2',
    'disabled' => 'bg-divider text-text-muted',
    'sent' => 'bg-divider text-text-muted',
    default => 'bg-muted-surface text-text-body',
};
@endphp

<span {{ $attributes->merge(['class' => 'fd-status-chip inline-flex items-center rounded px-2 py-1 '.$styles]) }}>
  {{ $label }}
</span>
