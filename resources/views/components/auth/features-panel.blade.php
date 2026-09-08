@props(['variant' => 'login'])

@php
    $isSignup = $variant === 'signup';
    $slides = collect(config('auth-features.slides', []))->map(function (array $slide) {
        return [
            'label' => $slide['label'],
            'title' => $slide['title'],
            'description' => $slide['description'],
            'image' => asset($slide['image']),
        ];
    })->values()->all();
    $slideCount = count($slides);
@endphp

<aside
    class="relative flex flex-1 flex-col overflow-hidden rounded-2xl bg-green-50 p-6 sm:p-10 lg:p-20"
    data-auth-features-carousel
    data-interval="{{ config('auth-features.carousel_interval_ms', 6000) }}"
    data-slides='@json($slides)'
    tabindex="0"
    aria-label="WapApp platform features"
>
    {{-- Align with left column logo --}}
    <div class="h-[136px] shrink-0" aria-hidden="true"></div>

  {{-- Align with "Login to Dashboard" / signup title --}}
    <div @class([
        'flex min-h-0 flex-1 flex-col gap-8',
        'mt-20' => ! $isSignup,
        'mt-8' => $isSignup,
    ])>
        <div class="flex w-full max-w-[520px] flex-col gap-5">
            <span class="inline-flex w-fit items-center justify-center rounded-lg border border-border bg-elevated px-3 py-2 text-sm font-semibold shadow-[0px_1px_2px_0px_rgba(35,39,46,0.08)]" style="font-family: var(--font-display)">
                Features
            </span>

            <div class="flex flex-col gap-2" aria-live="polite">
                <p class="text-xs font-semibold uppercase tracking-wide text-green-600" data-auth-feature-label>
                    {{ $slides[0]['label'] ?? '' }}
                </p>
                <h2 class="text-[32px] font-bold leading-[1.2] text-text-primary" data-auth-feature-title>
                    {{ $slides[0]['title'] ?? 'WapApp Features' }}
                </h2>
            </div>
        </div>

        <div @class([
            'relative mt-4 w-full max-w-[520px] flex-1 overflow-hidden',
            'min-h-[240px] sm:min-h-[280px] lg:min-h-[320px]' => ! $isSignup,
            'min-h-[220px] sm:min-h-[260px] lg:min-h-[300px]' => $isSignup,
        ])>
            @foreach ($slides as $index => $slide)
                <img
                    src="{{ $slide['image'] }}"
                    alt="{{ $slide['title'] }}"
                    @class([
                        'absolute inset-0 size-full rounded-2xl border border-border object-cover object-top transition-all duration-700 ease-out',
                        'opacity-100 translate-y-0 scale-100' => $index === 0,
                        'pointer-events-none opacity-0 translate-y-3 scale-[1.02]' => $index !== 0,
                    ])
                    data-auth-feature-image
                    data-index="{{ $index }}"
                    @if ($index > 1) loading="lazy" @endif
                >
            @endforeach
            <div class="pointer-events-none absolute inset-0 rounded-2xl bg-gradient-to-b from-green-50/0 via-green-50/10 to-green-50"></div>
        </div>

        <div class="flex w-full max-w-[520px] flex-col gap-4">
            <p class="text-base font-medium leading-[1.5] text-text-primary/54" data-auth-feature-description>
                {{ $slides[0]['description'] ?? '' }}
            </p>

            <div class="flex items-center gap-4">
                <div class="h-1 flex-1 overflow-hidden rounded-full bg-green-200/80">
                    <div
                        class="h-full rounded-full bg-green-500 transition-all duration-500 ease-out"
                        data-auth-feature-progress
                        style="width: {{ $slideCount > 0 ? round(100 / $slideCount) : 0 }}%"
                    ></div>
                </div>
                <span class="shrink-0 text-xs font-semibold tabular-nums text-text-primary/50" data-auth-feature-counter>
                    1 / {{ $slideCount }}
                </span>
            </div>
        </div>
    </div>
</aside>
