<x-layouts.app title="Template Variables - WapApp" active="templates.index">
  <div class="flex flex-col bg-surface">
    <div class="p-4">
      <div class="flex items-center justify-between gap-4">
        <div>
          <h1 class="text-2xl font-bold leading-[1.5] text-text-primary" style="font-family: var(--font-display)">Variable Samples</h1>
          <p class="text-sm leading-[1.4] text-text-subtle/50" style="font-family: var(--font-display)">Template preview message look like</p>
        </div>
        <x-ui.link-button href="{{ route('templates.variables') }}" variant="outline" size="sm">Back to Variables</x-ui.link-button>
      </div>
    </div>

    <section class="grid gap-6 p-4 pt-0 lg:grid-cols-[minmax(0,1fr)_458px]">
      <div class="rounded-lg bg-elevated p-4">
        <form class="flex flex-col gap-4">
          @foreach ([['@{{1}}', 'Customer Name', 'Rajesh Kumar'], ['@{{2}}', 'Order ID', 'ORD-1234'], ['@{{3}}', 'Amount', '₹ 1,500']] as [$var, $label, $value])
            <div>
              <label class="text-sm font-semibold leading-[1.4] text-text-primary" style="font-family: var(--font-display)">{{ $var }} — {{ $label }}</label>
              <input type="text" value="{{ $value }}" class="mt-2 w-full rounded-xl border border-border bg-elevated px-3.5 py-3.5 text-sm font-medium text-text-muted" style="font-family: var(--font-display)">
            </div>
          @endforeach
        </form>
      </div>

      <x-templates.phone-preview>
        <div class="max-w-[85%] rounded-lg rounded-tl-none bg-elevated p-3 text-sm text-text-primary shadow-sm">
          <p class="font-bold">Order Confirmed</p>
          <p class="mt-2">Hi Rajesh Kumar, your order ORD-1234 for ₹ 1,500 has been confirmed!</p>
          <p class="mt-2 text-[10px] text-text-body/50">10:30 AM</p>
        </div>
      </x-templates.phone-preview>
    </section>
  </div>
</x-layouts.app>
