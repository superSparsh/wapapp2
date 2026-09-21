@props([
    'status' => null,
    'label' => null,
])

@php
    $raw = $status;
    if ($raw instanceof \BackedEnum) {
        $raw = $raw->value;
    } elseif (is_bool($raw)) {
        $raw = $raw ? 'active' : 'inactive';
    }

    $value = is_scalar($raw) ? strtolower(trim((string) $raw)) : '';

    $display = $label;
    if ($display === null || $display === '') {
        $display = match ($value) {
            '1', 'true', 'yes' => 'Yes',
            '0', 'false', 'no' => 'No',
            '' => '—',
            default => ucwords(str_replace(['_', '-'], ' ', $value)),
        };
    }

    $tone = match ($value) {
        'active', 'enabled', 'approved', 'credited', 'paid', 'success', 'completed', 'processed',
        'yes', '1', 'true', 'created', 'authenticated', 'running', 'green' => 'bg-green-50 text-green-700 ring-green-200',
        'pending', 'processing', 'queued', 'draft', 'new', 'open', 'submitted', 'in_progress',
        'yellow', 'amber' => 'bg-amber-50 text-amber-800 ring-amber-200',
        'suspended', 'disabled', 'inactive', 'rejected', 'failed', 'cancelled', 'canceled', 'expired',
        'declined', 'no', '0', 'false', 'error', 'deleted', 'red' => 'bg-red-50 text-red-700 ring-red-200',
        'halted', 'paused', 'on_hold', 'review', 'unknown' => 'bg-orange-50 text-orange-800 ring-orange-200',
        default => 'bg-surface text-text-subtle ring-border',
    };
@endphp

<span {{ $attributes->merge([
    'class' => "inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset {$tone}",
]) }}>
  {{ $display }}
</span>
