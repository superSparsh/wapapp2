<x-auth.onboarding-layout
    :step="3"
    title="You're all set!"
    subtitle="Your WhatsApp account is now being verified. This usually takes just a few minutes."
>
    <div class="flex flex-col items-center gap-6 py-8 text-center">
        <div class="flex size-20 items-center justify-center rounded-full bg-green-50">
            <span class="text-4xl text-green-500" aria-hidden="true">✓</span>
        </div>
        <p class="text-base font-medium leading-[1.5] text-text-primary/70">
            You can now access your dashboard and start using WhatsApp automation features.
        </p>
    </div>

    <x-slot:footer>
        <x-ui.link-button
            href="{{ route('dashboard') }}"
            class="w-full rounded-xl bg-auth-gradient p-3.5 text-base font-extrabold text-white hover:opacity-90"
        >
            Go to Dashboard
        </x-ui.link-button>
    </x-slot:footer>
</x-auth.onboarding-layout>
