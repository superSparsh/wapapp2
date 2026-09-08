<x-auth.signup-layout :step="1">
    <form action="{{ route('signup.step-1.store') }}" method="POST" class="flex flex-col gap-6">
        @csrf

        <x-auth.signup-question
            name="answer"
            description="Business Manager Access: Verify your admin access to continue with WapApp setup."
            question='Q. Do you have administrative access to your Meta Business Manager? <span class="text-red-500">*</span>'
        />

        @error('answer')
            <p class="text-sm font-medium text-red-500">{{ $message }}</p>
        @enderror

        <div class="mt-8">
            <x-ui.auth-footer-buttons :submit="true" next-label="Next" />
        </div>
    </form>
</x-auth.signup-layout>
