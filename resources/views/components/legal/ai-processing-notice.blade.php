@props([])

@php
  $grievance = (string) config('legal.grievance.email', 'support@tittu.in');
  $product = (string) config('legal.product_name', 'WapApp');
@endphp

<aside {{ $attributes->class(['rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm leading-[1.5] text-amber-950']) }}>
  <p class="font-semibold">AI processing notice</p>
  <p class="mt-2">
    {{ $product }} AI bots may send conversation text, uploaded knowledge, and related metadata to the selected AI provider
    (and embedding/gateway services such as Tittu) to generate replies. Do not put secrets or unnecessary personal data in prompts or knowledge bases.
    End-customer messaging still requires your lawful basis / notice under DPDP. Questions:
    <a href="mailto:{{ $grievance }}" class="font-semibold underline">{{ $grievance }}</a>.
  </p>
</aside>
