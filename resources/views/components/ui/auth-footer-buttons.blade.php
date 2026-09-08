@props([
    'backHref' => null,
    'backLabel' => 'Back',
    'nextLabel' => 'Next',
    'single' => false,
    'submit' => false,
])

@if ($submit)
    <x-ui.button type="submit" class="w-full rounded-xl bg-auth-gradient p-3.5 text-base font-extrabold text-white">
        {{ $nextLabel }}
    </x-ui.button>
@elseif ($single)
    <x-ui.link-button href="{{ $nextHref }}" class="w-full rounded-xl bg-auth-gradient p-3.5 text-base font-extrabold text-white hover:opacity-90">
        {{ $nextLabel }}
    </x-ui.link-button>
@else
    <div class="flex gap-4">
        <x-ui.link-button
            href="{{ $backHref }}"
            variant="outline"
            class="flex-1 rounded-xl border border-border bg-elevated p-3.5 text-base font-extrabold text-primary-2"
        >
            {{ $backLabel }}
        </x-ui.link-button>
        <x-ui.link-button
            href="{{ $nextHref }}"
            class="flex-1 rounded-xl bg-auth-gradient p-3.5 text-base font-extrabold text-white hover:opacity-90"
        >
            {{ $nextLabel }}
        </x-ui.link-button>
    </div>
@endif
