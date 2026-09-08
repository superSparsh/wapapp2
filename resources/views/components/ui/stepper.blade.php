@props(['current' => 1, 'total' => 5])

<div class="flex items-center gap-4 py-4">
    @for ($i = 1; $i <= $total; $i++)
        @if ($i > 1)
            <div @class(['h-px w-6 shrink-0', $i <= $current ? 'bg-green-500' : 'bg-border'])></div>
        @endif
        <div @class([
            'flex size-8 shrink-0 items-center justify-center rounded-full text-base font-medium leading-6',
            'bg-green-500 text-white' => $i <= $current,
            'border border-green-500 bg-elevated text-blue-200' => $i > $current,
        ])>
            {{ $i }}
        </div>
    @endfor
</div>
