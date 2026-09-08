@props(['divided' => true])

<div @class([
    'flex min-h-0 flex-1 flex-col overflow-y-auto p-4',
    'border-border-light lg:border-r' => $divided,
])>
    <div class="rounded-xl border border-border-light bg-muted-surface p-4">
        {{ $slot }}
    </div>
</div>
