@props(['title' => 'Message Preview'])

<div class="flex flex-col gap-4">
  <h2 class="text-xl font-bold leading-[1.5] text-text-primary" style="font-family: var(--font-display)">{{ $title }}</h2>
  <div class="relative mx-auto w-full max-w-[425px]">
    <div class="relative h-[518px] overflow-hidden">
      <div class="absolute inset-x-[3.5px] inset-y-0 rounded-[62px] border border-white/60 bg-muted-surface shadow-[inset_0px_0px_8px_0px_rgba(0,0,0,0.3)]"></div>
      <div class="absolute inset-[4px_6.5px_4px_7.5px] rounded-[58px] bg-black"></div>
      <div class="absolute inset-[22px_24px_22px_26px] overflow-hidden rounded-t-[40px] bg-elevated">
        <img
          src="{{ asset('images/form-builder/whatsapp-screen.png') }}"
          alt=""
          class="h-full w-full object-cover object-top"
          width="375"
          height="496"
        >
        <x-ui.phone-preview-header-name size="default" />
        <div class="absolute left-[17px] top-[235px] w-[354px] rounded-lg border border-border bg-elevated p-3.5 text-xs leading-[1.4] text-text-muted" style="font-family: var(--font-display)">
          {{ $slot }}
        </div>
      </div>
    </div>
  </div>
</div>
