<x-admin.layout title="Platform Settings - Admin" active="admin.settings.index">
  <div class="p-4">
    <h1 class="text-2xl font-bold text-text-primary">Platform settings</h1>
    <p class="text-sm text-text-subtle opacity-70">General, mailer, and payment gateway flags.</p>
  </div>

  <form method="POST" action="{{ route('admin.settings.update') }}" class="mx-4 mb-8 max-w-3xl space-y-4">
    @csrf
    @method('PUT')

    @if (session('status'))
      <div class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
      <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-600">{{ $errors->first() }}</div>
    @endif

    <section class="rounded-[20px] border border-border bg-elevated p-5">
      <h2 class="text-lg font-bold">General</h2>
      <div class="mt-4 grid gap-4 sm:grid-cols-2">
        <label class="flex flex-col gap-1.5 text-sm sm:col-span-2">
          <span class="font-semibold">App name</span>
          <input name="general_app_name" value="{{ old('general_app_name', $settings['general.app_name'] ?? '') }}" class="rounded-lg border border-border px-3 py-2">
        </label>
        <label class="flex flex-col gap-1.5 text-sm sm:col-span-2">
          <span class="font-semibold">Support email</span>
          <input type="email" name="general_support_email" value="{{ old('general_support_email', $settings['general.support_email'] ?? '') }}" class="rounded-lg border border-border px-3 py-2">
        </label>
      </div>
    </section>

    <section class="rounded-[20px] border border-border bg-elevated p-5">
      <h2 class="text-lg font-bold">Mailer</h2>
      <div class="mt-4 grid gap-4 sm:grid-cols-2">
        <label class="flex flex-col gap-1.5 text-sm">
          <span class="font-semibold">From address</span>
          <input type="email" name="mailer_from_address" value="{{ old('mailer_from_address', $settings['mailer.from_address'] ?? '') }}" class="rounded-lg border border-border px-3 py-2">
        </label>
        <label class="flex flex-col gap-1.5 text-sm">
          <span class="font-semibold">From name</span>
          <input name="mailer_from_name" value="{{ old('mailer_from_name', $settings['mailer.from_name'] ?? '') }}" class="rounded-lg border border-border px-3 py-2">
        </label>
      </div>
    </section>

    <section class="rounded-[20px] border border-border bg-elevated p-5">
      <h2 class="text-lg font-bold">Payment</h2>
      <div class="mt-4 grid gap-4 sm:grid-cols-2">
        <label class="flex flex-col gap-1.5 text-sm">
          <span class="font-semibold">Razorpay enabled</span>
          <select name="payment_razorpay_enabled" class="rounded-lg border border-border px-3 py-2">
            <option value="0" @selected(old('payment_razorpay_enabled', $settings['payment.razorpay_enabled'] ?? '0') === '0')>No</option>
            <option value="1" @selected(old('payment_razorpay_enabled', $settings['payment.razorpay_enabled'] ?? '0') === '1')>Yes</option>
          </select>
        </label>
        <label class="flex flex-col gap-1.5 text-sm">
          <span class="font-semibold">Primary gateway</span>
          <input name="payment_primary_gateway" value="{{ old('payment_primary_gateway', $settings['payment.primary_gateway'] ?? '') }}" class="rounded-lg border border-border px-3 py-2" placeholder="razorpay">
        </label>
      </div>
    </section>

    <section class="rounded-[20px] border border-border bg-elevated p-5">
      <h2 class="text-lg font-bold">Wallet</h2>
      <p class="mt-1 text-sm text-text-subtle">Defaults for balance storage unit and INR↔USD conversion (legacy parity).</p>
      <div class="mt-4 grid gap-4 sm:grid-cols-2">
        <label class="flex flex-col gap-1.5 text-sm">
          <span class="font-semibold">Wallet balance unit</span>
          @php $balanceUnit = old('wallet_balance_unit', $settings['wallet.balance_unit'] ?? config('services.wallet_balance_unit', 'inr')); @endphp
          <select name="wallet_balance_unit" class="rounded-lg border border-border px-3 py-2">
            <option value="inr" @selected($balanceUnit === 'inr')>INR</option>
            <option value="usd" @selected($balanceUnit === 'usd')>USD</option>
          </select>
          <span class="text-xs text-text-subtle">Applies to how new wallet transaction amounts are interpreted.</span>
        </label>
        <label class="flex flex-col gap-1.5 text-sm">
          <span class="font-semibold">Conversion price (INR per 1 USD)</span>
          <input type="number" step="0.0001" min="0" name="wallet_conversion_price" value="{{ old('wallet_conversion_price', $settings['wallet.conversion_price'] ?? '83.17') }}" class="rounded-lg border border-border px-3 py-2">
        </label>
        <label class="flex flex-col gap-1.5 text-sm">
          <span class="font-semibold">Default display currency</span>
          @php $displayDefault = old('wallet_display_currency_default', $settings['wallet.display_currency_default'] ?? config('services.wallet_display_currency_default', 'INR')); @endphp
          <select name="wallet_display_currency_default" class="rounded-lg border border-border px-3 py-2">
            <option value="INR" @selected(strtoupper((string) $displayDefault) === 'INR')>INR</option>
            <option value="USD" @selected(strtoupper((string) $displayDefault) === 'USD')>USD</option>
          </select>
        </label>
      </div>
    </section>

    <button class="rounded-lg bg-green-500 px-4 py-2 text-sm font-semibold text-white">Save settings</button>
  </form>
</x-admin.layout>
