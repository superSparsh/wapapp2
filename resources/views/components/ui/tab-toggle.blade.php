@props([
    'tabs' => [],
    'active' => 0,
    'name' => 'tab',
])

<div {{ $attributes->merge(['class' => 'flex gap-4']) }} data-tab-toggle>
    @foreach ($tabs as $index => $tab)
        @php
            $classes = 'flex flex-1 items-center justify-center rounded-[12px] border-2 p-4 text-sm font-semibold leading-[1.4] transition-colors';
            $activeClasses = 'border-green-500 bg-green-50 text-green-500 shadow-[0px_1px_2px_0px_rgba(35,39,46,0.08)]';
            $inactiveClasses = 'border-blue-50 bg-elevated text-blue-200';
        @endphp
        @if (! empty($tab['href']))
            <a
                href="{{ $tab['href'] }}"
                @class([$classes, $activeClasses => $index === $active, $inactiveClasses => $index !== $active])
            >
                {{ $tab['label'] }}
            </a>
        @else
            <button
                type="button"
                data-tab-button
                data-tab-index="{{ $index }}"
                data-tab-target="{{ $tab['target'] ?? '' }}"
                @class([$classes, $activeClasses => $index === $active, $inactiveClasses => $index !== $active])
            >
                {{ $tab['label'] }}
            </button>
        @endif
    @endforeach
</div>
