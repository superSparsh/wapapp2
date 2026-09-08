<x-admin.layout title="Payment Gateways - Admin" active="admin.payment-gateways.edit">
  <div class="p-4">
    <h1 class="text-2xl font-bold text-text-primary">Payment gateways</h1>
    <p class="text-sm text-text-subtle opacity-70">Razorpay credentials and offline payment instructions.</p>
  </div>

  <form method="POST" action="{{ route('admin.payment-gateways.update') }}" class="mx-4 mb-8 max-w-3xl space-y-4">
    @csrf
    @method('PUT')

    @if ($errors->any())
      <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-600">{{ $errors->first() }}</div>
    @endif

    <section class="rounded-[20px] border border-border bg-elevated p-5">
      <h2 class="text-lg font-bold">Razorpay</h2>
      <div class="mt-4 grid gap-4 sm:grid-cols-2">
        <label class="flex flex-col gap-1.5 text-sm">
          <span class="font-semibold">Enabled</span>
          <select name="payment_razorpay_enabled" class="rounded-lg border border-border px-3 py-2">
            <option value="0" @selected(old('payment_razorpay_enabled', $settings['payment.razorpay_enabled'] ?? '0') === '0')>No</option>
            <option value="1" @selected(old('payment_razorpay_enabled', $settings['payment.razorpay_enabled'] ?? '0') === '1')>Yes</option>
          </select>
        </label>
        <label class="flex flex-col gap-1.5 text-sm">
          <span class="font-semibold">Key</span>
          <input name="payment_razorpay_key" value="{{ old('payment_razorpay_key', $settings['payment.razorpay_key'] ?? '') }}" class="rounded-lg border border-border px-3 py-2">
        </label>
        <label class="flex flex-col gap-1.5 text-sm">
          <span class="font-semibold">Secret</span>
          <input name="payment_razorpay_secret" value="{{ old('payment_razorpay_secret', $settings['payment.razorpay_secret'] ?? '') }}" class="rounded-lg border border-border px-3 py-2">
        </label>
        <label class="flex flex-col gap-1.5 text-sm">
          <span class="font-semibold">Webhook secret</span>
          <input name="payment_razorpay_webhook_secret" value="{{ old('payment_razorpay_webhook_secret', $settings['payment.razorpay_webhook_secret'] ?? '') }}" class="rounded-lg border border-border px-3 py-2">
        </label>
      </div>
    </section>

    <section class="rounded-[20px] border border-border bg-elevated p-5">
      <h2 class="text-lg font-bold">Offline payments</h2>
      <label class="mt-4 flex flex-col gap-1.5 text-sm">
        <span class="font-semibold">Instructions shown to customers</span>
        <textarea name="payment_offline_instructions" rows="6" class="rounded-lg border border-border px-3 py-2">{{ old('payment_offline_instructions', $settings['payment.offline_instructions'] ?? '') }}</textarea>
      </label>
    </section>

    <button class="rounded-lg bg-green-500 px-4 py-2 text-sm font-semibold text-white">Save gateways</button>
  </form>
</x-admin.layout>
