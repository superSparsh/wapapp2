@props([
    'description',
    'question',
    'name',
    'checked' => 'yes',
])

<div class="flex flex-col gap-3">
    <p class="text-sm font-medium leading-[1.4] text-text-subtle" style="font-family: var(--font-display)">
        {{ $description }}
    </p>
    <p class="text-base font-semibold leading-[1.4] text-text-body" style="font-family: var(--font-display)">
        {!! $question !!}
    </p>
    <div class="flex gap-[74px] pt-2">
        <label class="flex cursor-pointer items-center gap-2">
            <input type="radio" name="{{ $name }}" value="yes" class="sr-only" @checked($checked === 'yes')>
            <img src="{{ asset($checked === 'yes' ? 'images/auth/radio-checked.svg' : 'images/auth/radio-unchecked.svg') }}" alt="" class="size-4 shrink-0" width="16" height="16">
            <span @class([
                'text-base font-medium',
                'text-green-500' => $checked === 'yes',
                'text-text-body' => $checked !== 'yes',
            ]) style="font-family: var(--font-display)">Yes</span>
        </label>
        <label class="flex cursor-pointer items-center gap-2">
            <input type="radio" name="{{ $name }}" value="no" class="sr-only" @checked($checked === 'no')>
            <img src="{{ asset($checked === 'no' ? 'images/auth/radio-checked.svg' : 'images/auth/radio-unchecked.svg') }}" alt="" class="size-4 shrink-0" width="16" height="16">
            <span @class([
                'text-base font-medium',
                'text-green-500' => $checked === 'no',
                'text-text-body' => $checked !== 'no',
            ]) style="font-family: var(--font-display)">No</span>
        </label>
    </div>
</div>
