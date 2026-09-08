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
    'pending' => 'bg-stat-orange/15 text-stat-orange',
    'rejected' => 'bg-danger/10 text-danger',
    'active' => 'bg-green-50 text-green-700',
    'running' => 'bg-teal-50 text-teal-700',
    'sending' => 'bg-teal-50 text-teal-700',
    'paused' => 'bg-divider text-text-muted',
    'inactive' => 'bg-blue-50 text-primary-2',
    'disabled' => 'bg-divider text-text-muted',
    'sent' => 'bg-divider text-text-muted',
    default => 'bg-muted-surface text-text-body',
};
@endphp

<span class="fd-status-chip inline-flex items-center rounded px-2 py-1 {{ $styles }}">
  {{ $label }}
</span>
