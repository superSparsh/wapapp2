@props([
    'type' => 'text',
    'error' => null,
])

<div class="flex w-full flex-col gap-3">
    @if (isset($label))
        <x-form.label :for="$attributes->get('id')">{{ $label }}</x-form.label>
    @endif

    <div class="relative">
        <input
            type="{{ $type }}"
            {{ $attributes->merge([
                'class' => 'w-full rounded-xl border border-border bg-elevated px-3.5 py-3.5 text-sm font-medium leading-[1.4] text-text-primary placeholder:text-text-muted focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500' . ($error ? ' border-red-500' : ''),
            ]) }}
        />

        @if (isset($suffix))
            <div class="absolute inset-y-0 right-3.5 flex items-center">
                {{ $suffix }}
            </div>
        @endif
    </div>

    @if ($error)
        <p class="text-xs text-red-500">{{ $error }}</p>
    @endif
</div>
