@props([
    'step' => 1,
    'title' => 'Thank you for choosing Whatsapp Automation for your business needs.',
    'subtitle' => "Before you begin, we'd like to share some important information to ensure a smooth experience for you",
    'accountPrompt' => "Already have an account?",
    'accountAction' => 'Login',
    'accountHref' => route('login'),
    'showStepper' => true,
])

<x-layouts.guest title="Sign Up - WapApp">
    <main class="flex min-h-screen items-center justify-center p-4 sm:p-8">
        <div class="w-full max-w-[1440px] overflow-hidden rounded-[24px] bg-elevated">
            <div class="flex flex-col gap-4 p-4 sm:p-8 lg:flex-row lg:items-stretch">
                <section class="flex min-h-[960px] flex-1 flex-col justify-between rounded-2xl bg-muted-surface p-6 sm:p-10 lg:p-20">
                    <div class="flex flex-col gap-8">
                        <x-auth.logo />

                        <header class="flex max-w-[520px] flex-col gap-4">
                            <h1 class="text-[32px] font-bold leading-[1.2] text-text-primary">{{ $title }}</h1>
                            @if ($subtitle)
                                <p class="text-base font-medium leading-[1.5] text-text-primary/54">{{ $subtitle }}</p>
                            @endif
                            <p class="text-sm font-medium leading-[1.4] text-text-primary/60" style="font-family: var(--font-display)">
                                {{ $accountPrompt }}
                                @if ($accountHref)
                                    <a href="{{ $accountHref }}" class="font-extrabold text-green-500 underline">{{ $accountAction }}</a>
                                @else
                                    <span class="font-extrabold text-green-500 underline">{{ $accountAction }}</span>
                                @endif
                            </p>
                        </header>

                        @if ($showStepper)
                            <x-ui.stepper :current="$step" :total="5" />
                        @endif

                        <div class="max-w-[520px]">
                            {{ $slot }}
                        </div>
                    </div>

                    @if (isset($footer))
                        <div class="mt-8 max-w-[520px]">{{ $footer }}</div>
                    @endif
                </section>

                <x-auth.features-panel variant="signup" />
            </div>
        </div>

        @if (isset($modal))
            {{ $modal }}
        @endif
    </main>
</x-layouts.guest>
