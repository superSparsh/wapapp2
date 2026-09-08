<x-layouts.guest title="Two-Factor Authentication - WapApp">
    <main class="flex min-h-screen items-center justify-center p-4 sm:p-8">
        <div class="w-full max-w-[520px] rounded-[24px] bg-elevated p-8 sm:p-12">
            <x-auth.logo />

            <header class="mt-12 flex flex-col gap-4">
                <h1 class="text-[32px] font-bold leading-[1.2] text-text-primary">Two-factor challenge</h1>
                <p class="text-base font-medium leading-[1.5] text-text-primary/54">Enter the 6-digit code from your authenticator app or a recovery code.</p>
            </header>

            <form action="{{ route('two-factor.verify') }}" method="POST" class="mt-12 flex flex-col gap-8">
                @csrf

                <x-form.input
                    id="code"
                    name="code"
                    type="text"
                    inputmode="numeric"
                    autocomplete="one-time-code"
                    placeholder="000000"
                    :error="$errors->first('code')"
                    required
                    autofocus
                >
                    <x-slot:label>Authentication code</x-slot:label>
                </x-form.input>

                <x-ui.button type="submit" class="rounded-xl p-3.5">Verify</x-ui.button>
            </form>
        </div>
    </main>
</x-layouts.guest>
