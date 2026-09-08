@props(['contactsInAction' => '16', 'contactsDone' => '12.50%', 'skippedPending' => '12.50%', 'errors' => '0'])

<div class="flex w-full flex-col items-start justify-center rounded-lg border border-dashed border-border bg-blue-50 px-4 py-2">
  <div class="flex w-full items-center">
    <div class="flex min-w-0 flex-1 flex-col items-center justify-center gap-0.5 p-2 text-center text-text-body">
      <p class="text-xl font-medium leading-[1.5] whitespace-nowrap">{{ $contactsInAction }}</p>
      <p class="text-sm font-medium leading-[1.5] whitespace-nowrap opacity-80">Contacts in action</p>
    </div>
    <img
      src="{{ asset('images/automation/stats-divider-v.svg') }}"
      alt=""
      class="h-12 w-px shrink-0 self-stretch"
      width="1"
      height="48"
    >
    <div class="flex min-w-0 flex-1 flex-col items-center justify-center gap-0.5 p-2 text-center text-text-body">
      <p class="text-xl font-medium leading-[1.5] whitespace-nowrap">{{ $contactsDone }}</p>
      <p class="text-sm font-medium leading-[1.5] whitespace-nowrap opacity-80">Contacts done</p>
    </div>
  </div>

  {{-- <img
    src="{{ asset('images/automation/stats-divider-h.svg') }}"
    alt=""
    class="w-full"
    width="100%"
    height="1"
  > --}}

  <div class="flex w-full items-center">
    <div class="flex min-w-0 flex-1 flex-col items-center justify-center gap-0.5 p-2 text-center text-text-body">
      <p class="text-xl font-medium leading-[1.5] whitespace-nowrap">{{ $skippedPending }}</p>
      <p class="text-sm font-medium leading-[1.5] whitespace-nowrap opacity-80">Skipped / Pending</p>
    </div>
    <img
      src="{{ asset('images/automation/stats-divider-v.svg') }}"
      alt=""
      class="h-12 w-px shrink-0 self-stretch"
      width="1"
      height="48"
    >
    <div class="flex min-w-0 flex-1 flex-col items-center justify-center gap-0.5 p-2 text-center text-[red]">
      <p class="text-xl font-medium leading-[1.5] whitespace-nowrap">{{ $errors }}</p>
      <p class="text-sm font-medium leading-[1.5] whitespace-nowrap opacity-80">Error</p>
    </div>
  </div>
</div>
