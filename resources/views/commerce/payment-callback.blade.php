<x-layouts.guest title="Payment Status - WapApp">
  <div class="flex min-h-screen flex-col items-center justify-center gap-6 p-8">
    @if ($status === 'paid')
      <div class="flex size-20 items-center justify-center rounded-full bg-green-100">
        <svg class="size-10 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
        </svg>
      </div>
      <div class="text-center">
        <h1 class="text-2xl font-bold text-text-primary">Payment Successful!</h1>
        <p class="mt-2 text-text-subtle">Thank you. Your payment has been received.</p>
      </div>
    @else
      <div class="flex size-20 items-center justify-center rounded-full bg-orange-100">
        <svg class="size-10 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
      </div>
      <div class="text-center">
        <h1 class="text-2xl font-bold text-text-primary">Payment Pending</h1>
        <p class="mt-2 text-text-subtle">Payment status: {{ $status ?? 'unknown' }}</p>
      </div>
    @endif

    @if (! empty($authenticated))
      <a href="{{ route('commerce.settings') }}" class="fd-btn rounded bg-green-500 px-6 py-3 font-semibold text-white hover:opacity-90">
        Back to Dashboard
      </a>
    @endif
  </div>
</x-layouts.guest>
