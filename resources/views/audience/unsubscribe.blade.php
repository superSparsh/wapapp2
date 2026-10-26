<x-layouts.guest title="Unsubscribe - WapApp">
  <main class="flex min-h-screen items-center justify-center p-6">
    <section class="w-full max-w-md rounded-2xl border border-border bg-elevated p-6 text-center shadow-sm">
      <h1 class="text-2xl font-bold text-text-primary">Unsubscribe</h1>

      @if ($state === 'done')
        <p class="mt-3 text-sm leading-6 text-text-body">You are unsubscribed. You will no longer receive promotional WhatsApp messages from this business.</p>
      @elseif ($state === 'already')
        <p class="mt-3 text-sm leading-6 text-text-body">You are already unsubscribed.</p>
      @elseif ($state === 'invalid')
        <p class="mt-3 text-sm leading-6 text-text-body">This unsubscribe link is not valid. Reply STOP on WhatsApp to unsubscribe.</p>
      @else
        <p class="mt-3 text-sm leading-6 text-text-body">Confirm to stop promotional WhatsApp messages from this business.</p>
        <form method="POST" action="{{ route('unsubscribe.store', ['token' => $token]) }}" class="mt-5">
          @csrf
          <button class="inline-flex items-center justify-center rounded-lg bg-green-500 px-5 py-3 text-sm font-semibold text-white">Unsubscribe</button>
        </form>
      @endif
    </section>
  </main>
</x-layouts.guest>
