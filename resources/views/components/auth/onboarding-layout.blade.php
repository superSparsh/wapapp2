@props([
    'step' => 1,
    'title' => 'Set up your account',
    'subtitle' => 'We’ll guide you through a few quick steps.',
])

<x-layouts.guest :title="'Onboarding - '.config('app.name', 'WapApp')">
    <main class="flex min-h-screen items-center justify-center p-4 sm:p-8">
        <div class="w-full max-w-[1440px] overflow-hidden rounded-[24px] bg-elevated">
            <div class="flex flex-col gap-4 p-4 sm:p-8 lg:flex-row lg:items-stretch">
                <section class="flex min-h-[720px] flex-1 flex-col justify-between rounded-2xl bg-muted-surface p-6 sm:p-10 lg:p-20">
                    <div class="flex flex-col gap-8">
                        <div class="flex items-start justify-between gap-4">
                            <x-auth.logo />

                            <form method="POST" action="{{ route('logout') }}" class="shrink-0 pt-2">
                                @csrf
                                <button
                                    type="submit"
                                    class="rounded-xl border border-danger px-4 py-2 text-sm font-semibold text-danger transition hover:bg-danger hover:text-white"
                                >
                                    Logout
                                </button>
                            </form>
                        </div>

                        <header class="flex max-w-[520px] flex-col gap-4">
                            <h1 class="text-[32px] font-bold leading-[1.2] text-text-primary">{{ $title }}</h1>
                            @if ($subtitle)
                                <p class="text-base font-medium leading-[1.5] text-text-primary/54">{{ $subtitle }}</p>
                            @endif
                        </header>

                        <x-ui.stepper :current="$step" :total="3" />

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
    </main>
</x-layouts.guest>
