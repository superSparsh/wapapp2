@props(['value' => 'Our Services'])

<div class="flex w-full max-w-[317px] flex-col gap-3">
  <div class="flex items-center gap-3">
    <div class="flex items-center overflow-hidden rounded-md bg-green-500 p-1.5">
      <img src="{{ asset('images/commerce/catalog-icon.png') }}" alt="" class="size-6 object-cover" width="24" height="24">
    </div>
    <span class="text-sm font-semibold leading-[1.4] text-text-primary">Select a Catalog:</span>
  </div>
  <div class="flex items-center gap-3 rounded-xl border border-border bg-elevated p-3.5">
    <span class="min-w-0 flex-1 text-sm font-medium leading-[1.4] text-text-body">{{ $value }}</span>
    <img src="{{ asset('images/commerce/arrow-down.svg') }}" alt="" class="size-4 shrink-0" width="16" height="16">
  </div>
</div>
