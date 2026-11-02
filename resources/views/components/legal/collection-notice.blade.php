@props([
  'compact' => false,
])

@php
  $product = (string) config('legal.product_name', config('app.name', 'WapApp'));
  $company = (string) config('legal.company_legal_name', 'Tekkonnectpro IT Services');
  $grievance = (string) config('legal.grievance.email', 'support@tittu.in');
  $region = (string) config('legal.processing.primary_region', 'Singapore');
@endphp

<aside {{ $attributes->class(['rounded-xl border border-border bg-surface/60 p-4 text-sm leading-[1.5] text-text-body']) }}>
  <p class="font-semibold text-text-primary">Personal data collection notice</p>
  @unless ($compact)
    <p class="mt-2">
      {{ $company }} (“we”) collects your name, work email, phone, business details, and account credentials to create and operate your
      {{ $product }} account, provide WhatsApp Business messaging services, bill usage, and meet legal obligations.
    </p>
    <p class="mt-2">
      Processing may involve Meta (WhatsApp), our WhatsApp BSP, and infrastructure in {{ $region }} and India. You may withdraw consent where
      processing is consent-based, and exercise access, correction, erasure, and grievance rights by contacting
      <a href="mailto:{{ $grievance }}" class="font-semibold text-primary-2 underline">{{ $grievance }}</a>
      or via Privacy rights after login.
    </p>
  @else
    <p class="mt-2">
      We collect account and business contact details to run {{ $product }}. Rights and grievances:
      <a href="mailto:{{ $grievance }}" class="font-semibold text-primary-2 underline">{{ $grievance }}</a>.
    </p>
  @endunless
  {{ $slot }}
</aside>
