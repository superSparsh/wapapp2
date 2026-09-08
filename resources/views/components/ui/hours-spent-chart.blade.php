@php
$bars = [
    ['label' => 'Jan', 'gray' => 177, 'green' => 95],
    ['label' => 'Feb', 'gray' => 107, 'green' => 83],
    ['label' => 'Mar', 'gray' => 192, 'green' => 165],
    ['label' => 'Apr', 'gray' => 136, 'green' => 94, 'tooltip' => true],
    ['label' => 'May', 'gray' => 65, 'green' => 41],
];
@endphp

<div class="relative h-[256px] w-full">
  <div class="absolute left-0 top-2 flex h-[220px] flex-col justify-between text-sm text-text-muted" style="font-family: Inter, sans-serif">
    @foreach (['100', '80', '60', '40', '20', '0'] as $tick)
      <span>{{ $tick }}</span>
    @endforeach
  </div>

  <div class="absolute left-10 right-0 top-2 h-[220px] border-t border-[#d5d8df]">
    <div class="flex h-full items-end justify-between gap-3 px-2">
      @foreach ($bars as $bar)
        <div class="relative flex h-full flex-1 flex-col items-center justify-end">
          @if (! empty($bar['tooltip']))
            <div class="absolute left-1/2 top-4 z-10 -translate-x-1/2">
              <img src="{{ asset('images/dashboard/charts/hours-spent-tooltip.svg') }}" alt="" class="h-[62px] w-[72px]" width="72" height="62">
              <div class="absolute inset-0 flex flex-col justify-center gap-2 px-3 py-2">
                <div class="flex items-center gap-1.5">
                  <span class="size-2.5 rounded-sm bg-green-500"></span>
                  <span class="text-xs font-medium text-text-muted" style="font-family: Inter, sans-serif">35 Hr</span>
                </div>
                <div class="flex items-center gap-1.5">
                  <span class="size-2.5 rounded-sm bg-blue-50"></span>
                  <span class="text-xs font-medium text-text-muted" style="font-family: Inter, sans-serif">52 Hr</span>
                </div>
              </div>
            </div>
          @endif

          <div class="relative w-[55px] rounded-xl bg-blue-50" style="height: {{ $bar['gray'] }}px">
            <div
              class="absolute bottom-0 left-0 right-0 rounded-b-xl bg-auth-gradient"
              style="height: {{ $bar['green'] }}px"
            ></div>
          </div>
          <span class="mt-2 text-sm text-text-muted" style="font-family: Inter, sans-serif">{{ $bar['label'] }}</span>
        </div>
      @endforeach
    </div>
  </div>
</div>
