@props([
  'credits' => [],
  'creditsPeriod' => 'daily',
])

@php
  $period = $creditsPeriod ?? ($credits['period'] ?? 'daily');
  $periodLabels = [
    'daily' => 'Daily',
    'weekly' => 'Weekly',
    'monthly' => 'Monthly',
    'yearly' => 'Yearly',
    'all' => 'All time',
  ];

  $serviceUsed = (int) ($credits['service'] ?? 0);
  $serviceLimit = $credits['service_limit'] ?? null;
  $serviceValue = $serviceLimit !== null
    ? number_format($serviceUsed).'/'.number_format((int) $serviceLimit)
    : number_format($serviceUsed);

  $freePerNumber = (int) ($credits['service_free_per_number'] ?? config('billing.service_free_messages_per_month', 1000));
  $whatsappNumbers = max(1, (int) ($credits['service_whatsapp_numbers'] ?? 1));
  $freeRemaining = (int) ($credits['service_free_remaining'] ?? 0);
  $perLine = is_array($credits['service_free_per_line'] ?? null) ? $credits['service_free_per_line'] : [];
  $showServiceFreeInfo = $serviceLimit !== null;
@endphp

<section
  class="relative z-0 flex min-h-[182px] flex-col justify-center gap-4 overflow-visible p-4 pt-0"
  data-dashboard-credits
  data-credits-url="{{ route('dashboard.credits') }}"
>
  <div class="flex flex-wrap items-center gap-4">
    <h2 class="fd-section-title min-w-0 flex-1">Credits Used</h2>
    <button type="button" data-credits-refresh class="fd-btn inline-flex items-center gap-2.5 rounded-lg border border-border bg-elevated px-3 py-2 text-sm font-medium text-text-primary transition-colors">
      <span data-refresh-text>Refresh</span>
      <x-icons.nav-icon name="refresh-2" class="size-4" data-refresh-icon />
    </button>
    <label class="sr-only" for="credits_period">Sort by period</label>
    <select
      id="credits_period"
      name="credits_period"
      data-credits-period
      data-native-select="true"
      class="min-w-[10.5rem] rounded-lg border border-border bg-elevated py-2 pl-3 pr-8 text-sm font-medium text-text-primary outline-none focus:border-green-500"
    >
      @foreach ($periodLabels as $value => $label)
        <option value="{{ $value }}" @selected($period === $value)>Sort by : {{ $label }}</option>
      @endforeach
    </select>
  </div>

  <div class="grid gap-4 overflow-visible md:grid-cols-2 xl:grid-cols-4">
    <x-ui.credit-stat-card label="Sent" icon="send" data-credit-key="sent" :value="number_format((int) ($credits['sent'] ?? 0)).'/'.number_format((int) ($credits['sent_limit'] ?? 1000))" />
    <x-ui.credit-stat-card label="Marketing Conversations" icon="megaphone" data-credit-key="marketing" :value="number_format((int) ($credits['marketing'] ?? 0)).'/'.number_format((int) ($credits['marketing_limit'] ?? 1000))" />
    <x-ui.credit-stat-card label="Utility Conversations" icon="wrench" data-credit-key="utility" :value="number_format((int) ($credits['utility'] ?? 0)).'/'.number_format((int) ($credits['utility_limit'] ?? 1000))" />

    <x-ui.credit-stat-card
      label="Service messages"
      icon="headset"
      data-credit-key="service"
      :value="$serviceValue"
      @if ($showServiceFreeInfo) data-service-free-card @endif
    >
      @if ($showServiceFreeInfo)
        <x-slot:info>
          <div data-service-free-summary class="font-semibold text-text-primary">
            {{ number_format($freeRemaining) }} free left this month
          </div>
          <p data-service-free-rule class="text-text-muted">
            {{ number_format($freePerNumber) }} free × {{ $whatsappNumbers }} {{ $whatsappNumbers === 1 ? 'WhatsApp number' : 'WhatsApp numbers' }}
            · not shared across numbers
          </p>
          @if (count($perLine) > 0)
            <ul data-service-free-lines class="mt-1 space-y-1.5 border-t border-border pt-2">
              @foreach ($perLine as $line)
                <li class="flex items-center justify-between gap-3">
                  <span class="truncate font-medium text-text-primary">{{ $line['label'] }}</span>
                  <span class="shrink-0 tabular-nums text-text-muted">{{ number_format((int) $line['remaining']) }}/{{ number_format((int) $line['limit']) }}</span>
                </li>
              @endforeach
            </ul>
          @endif
        </x-slot:info>
      @endif
    </x-ui.credit-stat-card>
  </div>
</section>
