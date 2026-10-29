@props([
  'title',
  'subtitle' => null,
  'active',
  'effectiveDate' => null,
])

<x-profile.layout :title="$title.' - WapApp'" :headerTitle="$title" :active="$active">
  <div class="mx-auto flex w-full max-w-4xl flex-col gap-4 p-4">
    <div class="flex flex-col gap-1">
      <h1 class="text-2xl font-bold leading-[1.5] text-text-primary">{{ $title }}</h1>
      @if ($subtitle)
        <p class="max-w-[854px] text-sm font-normal leading-[1.4] text-text-subtle opacity-50">{{ $subtitle }}</p>
      @endif
      @if ($effectiveDate)
        <p class="text-xs font-medium text-text-muted">Effective date: {{ $effectiveDate }}</p>
      @endif
    </div>

    <x-profile.info-banner title="Internal review draft">
      This page is available only to signed-in accounts so leadership can review the wording.
      It is not linked from the public footer or login screen yet.
    </x-profile.info-banner>

    <article class="rounded-[20px] border border-border-light bg-elevated p-5 shadow-[0px_4px_6px_rgba(0,0,0,0.04)] sm:p-8">
      <div class="legal-doc space-y-6 text-sm leading-[1.6] text-text-body">
        {{ $slot }}
      </div>
    </article>

    <nav class="flex flex-wrap gap-3 text-sm font-semibold">
      <a href="{{ route('profile.privacy-policy') }}" @class(['underline', 'text-primary-2' => ! request()->routeIs('profile.privacy-policy'), 'text-text-muted' => request()->routeIs('profile.privacy-policy')])>Privacy Policy</a>
      <span class="text-text-muted">·</span>
      <a href="{{ route('profile.terms') }}" @class(['underline', 'text-primary-2' => ! request()->routeIs('profile.terms'), 'text-text-muted' => request()->routeIs('profile.terms')])>Terms of Service</a>
    </nav>
  </div>
</x-profile.layout>
