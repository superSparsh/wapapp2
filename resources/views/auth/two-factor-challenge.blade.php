<x-layouts.guest title="Two-Factor Authentication - WapApp">
    <main class="flex min-h-screen items-center justify-center p-4 sm:p-8">
        <div class="w-full max-w-[520px] rounded-[24px] bg-elevated p-8 sm:p-12">
            <x-auth.logo size="compact" />

            <header class="mt-12 flex flex-col gap-4">
                <h1 class="text-[32px] font-bold leading-[1.2] text-text-primary">Two-factor challenge</h1>
                <p class="text-base font-medium leading-[1.5] text-text-primary/54">Enter the 6-digit code from your authenticator app, or use a recovery code.</p>
            </header>

            <form action="{{ route('two-factor.verify') }}" method="POST" class="mt-12 flex flex-col gap-8" data-2fa-form>
                @csrf

                <div data-2fa-totp>
                    <x-auth.otp-inputs
                        name="code"
                        label="Authentication code"
                        :error="$errors->first('code')"
                        :old="old('code')"
                    />
                </div>

                <div class="hidden flex-col gap-3" data-2fa-recovery>
                    <x-form.input
                        id="recovery_code"
                        name="recovery_code_input"
                        type="text"
                        autocomplete="one-time-code"
                        placeholder="Enter recovery code"
                        :error="$errors->first('code')"
                    >
                        <x-slot:label>Recovery code</x-slot:label>
                    </x-form.input>
                </div>

                <button
                    type="button"
                    class="text-left text-sm font-semibold text-green-500 underline"
                    data-2fa-toggle
                >Use a recovery code instead</button>

                <x-ui.button type="submit" class="rounded-xl p-3.5">Verify</x-ui.button>
            </form>
        </div>
    </main>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const form = document.querySelector('[data-2fa-form]');
                if (!form) return;

                const totpWrap = form.querySelector('[data-2fa-totp]');
                const recoveryWrap = form.querySelector('[data-2fa-recovery]');
                const toggle = form.querySelector('[data-2fa-toggle]');
                const hiddenCode = form.querySelector('[data-otp-hidden]');
                const recoveryInput = form.querySelector('#recovery_code');
                let recoveryMode = false;

                toggle?.addEventListener('click', () => {
                    recoveryMode = !recoveryMode;
                    totpWrap?.classList.toggle('hidden', recoveryMode);
                    recoveryWrap?.classList.toggle('hidden', !recoveryMode);
                    recoveryWrap?.classList.toggle('flex', recoveryMode);
                    toggle.textContent = recoveryMode
                        ? 'Use authenticator code instead'
                        : 'Use a recovery code instead';

                    if (recoveryMode) {
                        if (hiddenCode) hiddenCode.removeAttribute('name');
                        recoveryInput?.setAttribute('name', 'code');
                        recoveryInput?.focus();
                    } else {
                        recoveryInput?.removeAttribute('name');
                        if (hiddenCode) hiddenCode.setAttribute('name', 'code');
                        form.querySelector('#otp-1')?.focus();
                    }
                });
            });
        </script>
    @endpush
</x-layouts.guest>
