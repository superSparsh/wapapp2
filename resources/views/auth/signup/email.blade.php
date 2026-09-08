@php
    $otpMode = $otpSent ?? false;
@endphp

<x-auth.signup-layout
    :step="1"
    title="Verify your email"
    subtitle="Enter the 6-digit code we sent to {{ $email ?? 'your email' }}."
    account-prompt="Already verified?"
    account-action="Sign In"
    :account-href="route('login')"
    :show-stepper="false"
>
    @if (session('status'))
        <div class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-600">
            {{ session('status') }}
        </div>
    @endif

    @if ($otpMode)
        <div class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-600">
            We sent a 6-digit code to {{ $email }}.
        </div>
    @endif

    <form action="{{ route('signup.email.verify') }}" method="POST" class="flex flex-col gap-6 py-4">
        @csrf

        <div class="flex flex-col gap-3">
            <x-form.label for="otp-1">Enter OTP</x-form.label>
            <div class="flex gap-3" data-otp-inputs>
                @for ($i = 1; $i <= 6; $i++)
                    <input
                        id="otp-{{ $i }}"
                        name="code_digits[]"
                        type="text"
                        inputmode="numeric"
                        maxlength="1"
                        class="w-full rounded-xl border border-border bg-elevated px-3.5 py-3.5 text-center text-sm font-medium leading-[1.4] text-text-primary focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500"
                    >
                @endfor
            </div>
            <input type="hidden" name="code" id="code" value="{{ old('code') }}">
            @error('code')
                <p class="text-sm font-medium text-red-500">{{ $message }}</p>
            @enderror
        </div>

        <x-ui.button type="submit" class="rounded-xl p-3.5">Verify email</x-ui.button>
    </form>

    <form action="{{ route('signup.email.resend') }}" method="POST" class="mt-4">
        @csrf
        <button type="submit" class="text-sm font-semibold text-green-500 underline">Resend code</button>
    </form>

    <x-slot:footer></x-slot:footer>
</x-auth.signup-layout>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const digits = Array.from(document.querySelectorAll('[name="code_digits[]"]'));
        const hidden = document.getElementById('code');

        const sync = () => {
            hidden.value = digits.map((input) => input.value).join('');
        };

        digits.forEach((input, index) => {
            input.addEventListener('input', () => {
                input.value = input.value.replace(/\D/g, '').slice(0, 1);
                sync();
                if (input.value && digits[index + 1]) {
                    digits[index + 1].focus();
                }
            });
        });
    });
</script>
