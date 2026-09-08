<x-layouts.guest title="Account Security - WapApp">
    <main class="flex min-h-screen items-center justify-center p-4 sm:p-8">
        <div class="w-full max-w-[640px] rounded-[24px] bg-elevated p-8 sm:p-12">
            <x-auth.logo />

            <header class="mt-12 flex flex-col gap-4">
                <h1 class="text-[32px] font-bold leading-[1.2] text-text-primary">Account security</h1>
                <p class="text-base font-medium leading-[1.5] text-text-primary/54">Protect your account with two-factor authentication (TOTP).</p>
            </header>

            @if (session('status'))
                <div class="mt-8 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-600">
                    {{ session('status') }}
                </div>
            @endif

            @if ($enabled)
                <div class="mt-8 rounded-xl border border-green-200 bg-green-50 p-4 text-sm font-medium text-green-700">
                    Two-factor authentication is enabled on your account.
                </div>

                <form
                    action="{{ route('account.security.disable') }}"
                    method="POST"
                    class="mt-8"
                    data-confirm="Disable two-factor authentication on your account?"
                    data-confirm-title="Disable 2FA"
                    data-confirm-label="Disable 2FA"
                    data-confirm-variant="danger"
                >
                    @csrf
                    @method('DELETE')
                    <x-ui.button type="submit" class="rounded-xl p-3.5">Disable 2FA</x-ui.button>
                </form>
            @else
                <div class="mt-8 flex flex-col gap-6">
                    <p class="text-sm font-medium text-text-muted">Scan this QR code with Google Authenticator, Authy, or any TOTP app.</p>

                    <div class="flex justify-center rounded-xl border border-border bg-white p-4">
                        {!! $qrCode !!}
                    </div>

                    <p class="text-sm font-medium text-text-muted">Manual setup key: <span class="font-mono text-text-primary">{{ $secret }}</span></p>

                    <form action="{{ route('account.security.enable') }}" method="POST" class="flex flex-col gap-6">
                        @csrf
                        <input type="hidden" name="secret" value="{{ $secret }}">

                        <x-form.input
                            id="code"
                            name="code"
                            type="text"
                            inputmode="numeric"
                            placeholder="000000"
                            :error="$errors->first('code')"
                            required
                        >
                            <x-slot:label>Confirm with 6-digit code</x-slot:label>
                        </x-form.input>

                        <x-ui.button type="submit" class="rounded-xl p-3.5">Enable 2FA</x-ui.button>
                    </form>
                </div>
            @endif

            @if (! empty($recoveryCodes))
                <div class="mt-8 rounded-xl border border-border bg-muted-surface p-4">
                    <p class="mb-3 text-sm font-semibold text-text-primary">Save these recovery codes</p>
                    <ul class="grid grid-cols-2 gap-2 font-mono text-sm">
                        @foreach ($recoveryCodes as $code)
                            <li>{{ $code }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    </main>
</x-layouts.guest>
