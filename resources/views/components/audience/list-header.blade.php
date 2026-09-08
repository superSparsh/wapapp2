@props([
    'title' => 'Qualified Leads - AI',
    'subscribers' => '1000',
    'showBack' => true,
])

<div class="flex flex-col gap-3 p-4 pb-0">
  @if ($showBack)
    {{-- <a href="{{ route('audience.index') }}" class="fd-btn inline-flex items-center gap-2 text-blue-200 hover:text-primary-2">
      <img src="{{ asset('images/icons/chevron-right-fd.svg') }}" alt="" class="size-4 rotate-180" width="16" height="16">
      My lists
    </a> --}}
  @endif
  <div class="flex flex-col gap-1">
    <h1 class="fd-page-title">{{ $title }}</h1>
    <div class="flex flex-wrap items-center gap-3">
      <span class="text-base font-bold text-text-primary/64">Overall Subscribers:</span>
      <span class="rounded-lg border border-green-500 bg-elevated px-3 py-2 text-sm font-bold text-green-500 shadow-[0px_0px_2px_rgba(0,0,0,0.08)]">{{ $subscribers }}</span>
    </div>
  </div>
</div>
