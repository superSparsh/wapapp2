<x-auth.signup-layout :step="2">
    <form action="{{ route('signup.step-2.store') }}" method="POST" class="flex flex-col gap-6">
        @csrf

        <x-auth.signup-question
            name="answer"
            description="Website Requirement: A valid business website is needed for seamless onboarding."
            question='Q. Do you have official business website <span class="text-red-500">*</span>'
        />

        @error('answer')
            <p class="text-sm font-medium text-red-500">{{ $message }}</p>
        @enderror

        <div class="mt-8 flex gap-4">
            <x-ui.link-button
                href="{{ route('signup.step-1') }}"
                variant="outline"
                class="flex-1 rounded-xl border border-border bg-elevated p-3.5 text-base font-extrabold text-primary-2"
            >
                Back
            </x-ui.link-button>
            <x-ui.button type="submit" class="flex-1 rounded-xl p-3.5">Next</x-ui.button>
        </div>
    </form>
</x-auth.signup-layout>
