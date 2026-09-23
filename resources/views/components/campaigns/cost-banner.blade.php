@props([
    'recipients' => 0,
    'planName' => 'Current plan',
    'unitCost' => 0,
    'totalCost' => 0,
    'templateName' => '',
    'templateType' => 'Marketing',
    'currency' => 'INR',
    'categoryRates' => [],
])

@php
  $symbol = $currency === 'INR' ? '₹' : $currency.' ';
  $formatMoney = static fn (float|int $amount): string => $symbol.number_format((float) $amount, 2);
@endphp

<div
  class="rounded-xl bg-stat-orange/15 p-3.5"
  data-campaign-cost-banner
  data-recipients="{{ (int) $recipients }}"
  data-plan-name="{{ $planName }}"
  data-currency-symbol="{{ $symbol }}"
  data-category-rates='@json($categoryRates)'
>
  <p class="text-sm font-bold leading-[1.4] text-text-body">Estimated Campaign Cost</p>
  <p class="mt-2.5 text-sm leading-[1.4] text-text-muted">
    Total Recipients: <span data-cost-recipients>{{ (int) $recipients }}</span><br>
    Plan: <span data-cost-plan>{{ $planName !== '' ? $planName : 'Current plan' }}</span><br>
    Per Message Cost: <span data-cost-unit>{{ $formatMoney($unitCost) }}</span><br>
    Total Estimated Cost: <span data-cost-total>{{ $formatMoney($totalCost) }}</span>
  </p>
  <div class="my-2.5 h-px rounded-full bg-text-muted"></div>
  <p class="text-sm font-bold leading-[1.4] text-text-body">Selected Template:</p>
  <p class="mt-2.5 text-sm leading-[1.4] text-text-muted" data-cost-template-name>
    {{ $templateName !== '' ? $templateName : 'Select a template' }}
  </p>
  <p class="mt-2.5 text-sm leading-[1.4] text-text-muted">
    Template Type: <span data-cost-template-type>{{ $templateType !== '' ? $templateType : '—' }}</span><br>
    Template-Specific Cost: <span data-cost-template-total>{{ $formatMoney($totalCost) }}</span>
  </p>
  <p class="mt-2.5 text-sm leading-[1.4] text-text-muted">
    Cost is estimated based on your current plan and template type. Actual cost may vary based on delivery status and recipient location.
  </p>
</div>
