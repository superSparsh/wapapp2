<x-campaigns.create-layout
  :step="5"
  :campaign-name="$wizardData['name'] ?? 'New Campaign'"
  previous-route="{{ route('campaigns.create.step', 4) }}"
  next-route="{{ route('campaigns.create.step', 6) }}"
  next-label="Next step"
>
  <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_458px]">
    <div class="flex flex-col gap-6">
      <h2 class="fd-section-title">You're all set to send</h2>

      <div class="flex flex-col gap-3">
        @foreach ([
          ['Campaign Name', $wizardData['name'] ?? 'N/A', route('campaigns.create.step', 1)],
          ['Audience', 'Selected in step 2', route('campaigns.create.step', 2)],
          ['Template', 'Selected in step 3', route('campaigns.create.step', 3)],
        ] as [$title, $value, $editRoute])
          <div class="flex items-center gap-4">
            <div class="flex-1">
              <p class="fd-section-title text-base">{{ $title }}</p>
              <p class="fd-page-note text-[#878787] opacity-100">{{ $value }}</p>
            </div>
            <x-ui.link-button :href="$editRoute" variant="outline" size="sm" class="min-w-[80px]">Edit</x-ui.link-button>
          </div>
          @if (! $loop->last)
            <div class="h-px bg-border"></div>
          @endif
        @endforeach
      </div>
    </div>

    <x-templates.phone-preview
      title="Message Preview"
      subtitle="Template preview message look like"
      size="compact"
    >
      <x-templates.message-preview-bubble :preview-data="$previewData" size="compact" scroll-body />
    </x-templates.phone-preview>
  </div>
</x-campaigns.create-layout>
