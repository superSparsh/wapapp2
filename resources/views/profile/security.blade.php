<x-profile.layout title="Account Security - WapApp" headerTitle="Security" active="profile.security">
  <div class="flex flex-col gap-1 p-4">
    <h1 class="text-2xl font-bold leading-[1.5] text-text-primary">Account security</h1>
    <p class="max-w-[854px] text-sm font-normal leading-[1.4] text-text-subtle opacity-50">
      Protect your account with two-factor authentication (TOTP), same as legacy WapApp.
    </p>
  </div>

  @if (session('status'))
    <div class="mx-4 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-600">
      {{ session('status') }}
    </div>
  @endif

  <section class="mx-4 mt-6 rounded-lg bg-elevated p-4">
    @if ($enabled)
      <div class="rounded-xl border border-green-200 bg-green-50 p-4 text-sm font-medium text-green-700">
        Two-factor authentication is enabled on your account.
      </div>

      <form
        action="{{ route('profile.security.disable') }}"
        method="POST"
        class="mt-6 flex max-w-xl flex-col gap-4"
        data-confirm="Disable two-factor authentication on your account?"
        data-confirm-title="Disable 2FA"
        data-confirm-label="Disable 2FA"
        data-confirm-variant="danger"
      >
        @csrf
        <div>
          <h2 class="text-base font-semibold text-text-primary">Disable 2FA</h2>
          <p class="text-sm text-text-muted">Confirm with your password and a current authenticator or recovery code.</p>
        </div>

        <x-form.input id="disable_password" name="password" type="password" :error="$errors->first('password')" required>
          <x-slot:label>Password</x-slot:label>
        </x-form.input>

        <x-form.input id="disable_code" name="code" type="text" inputmode="numeric" placeholder="000000 or recovery code" :error="$errors->first('code')" required>
          <x-slot:label>Authenticator or recovery code</x-slot:label>
        </x-form.input>

        <x-ui.button type="submit" class="w-fit rounded px-6 py-3 text-xs text-[red]">Disable 2FA</x-ui.button>
      </form>

      <hr class="my-6 border-divider">

      <form
        action="{{ route('profile.security.recovery') }}"
        method="POST"
        class="flex max-w-xl flex-col gap-4"
        data-confirm="Regenerate recovery codes? Your existing recovery codes will stop working."
        data-confirm-title="Regenerate recovery codes"
        data-confirm-label="Regenerate"
        data-confirm-variant="danger"
      >
        @csrf
        <div>
          <h2 class="text-base font-semibold text-text-primary">Regenerate recovery codes</h2>
          <p class="text-sm text-text-muted">Enter your current 6-digit authenticator code to generate new recovery codes.</p>
        </div>

        <x-form.input id="recovery_code" name="code" type="text" inputmode="numeric" maxlength="6" placeholder="000000" :error="$errors->first('recovery_code')" required>
          <x-slot:label>Authenticator code</x-slot:label>
        </x-form.input>

        <x-ui.button type="submit" class="w-fit rounded px-6 py-3 text-xs">Regenerate codes</x-ui.button>
      </form>
    @else
      <div class="flex flex-col gap-6">
        <p class="text-sm font-medium text-text-muted">Scan this QR code with Google Authenticator, Authy, or any TOTP app.</p>

        @if ($qrCode)
          <div class="flex justify-center rounded-xl border border-border bg-white p-4">
            {!! $qrCode !!}
          </div>

          <p class="text-sm font-medium text-text-muted">
            Manual setup key: <span class="font-mono text-text-primary">{{ $secret }}</span>
          </p>

          <form action="{{ route('profile.security.enable') }}" method="POST" class="flex max-w-md flex-col gap-6">
            @csrf

            <x-form.input id="code" name="code" type="text" inputmode="numeric" maxlength="6" placeholder="000000" :error="$errors->first('code')" required>
              <x-slot:label>Confirm with 6-digit code</x-slot:label>
            </x-form.input>

            <x-ui.button type="submit" class="rounded px-6 py-3 text-xs">Enable 2FA</x-ui.button>
          </form>
        @endif
      </div>
    @endif

    @if (! empty($recoveryCodes))
      <div class="mt-8 rounded-xl border border-amber-200 bg-amber-50 p-4">
        <p class="mb-1 text-sm font-semibold text-text-primary">Save these recovery codes</p>
        <p class="mb-3 text-xs text-text-muted">Store them securely. Each code can be used once if you lose your device.</p>
        <ul class="grid grid-cols-2 gap-2 font-mono text-sm">
          @foreach ($recoveryCodes as $code)
            <li>{{ $code }}</li>
          @endforeach
        </ul>
      </div>
    @endif
  </section>
</x-profile.layout>
