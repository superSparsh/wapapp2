@php
  use App\Domains\Templates\Support\TemplateCategoryCatalog;

  $selectedTemplateId = (string) old('template_id', $wizardData['template_id'] ?? '');
  $selectedTemplate = collect($templates ?? [])->first(
    fn ($template) => (string) $template->uuid === $selectedTemplateId
      || (string) $template->id === $selectedTemplateId,
  );
  $showMarketingPricing = $selectedTemplate
    && strtoupper((string) $selectedTemplate->category) === TemplateCategoryCatalog::MARKETING;
@endphp

<x-campaigns.create-layout
  :step="3"
  :campaign-name="$wizardData['name'] ?? 'New Campaign'"
  previous-route="{{ route('campaigns.create.step', 2) }}"
  next-route="{{ route('campaigns.create.save', 3) }}"
>
  <form
    id="campaign-wizard-form"
    method="POST"
    action="{{ route('campaigns.create.save', 3) }}"
    data-validate-form
    data-campaign-wizard
    data-preview-url="{{ url('/campaigns/create/template-preview') }}"
    class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_458px]"
  >
    @csrf
    <div class="flex flex-col gap-6">
      <div class="flex flex-col gap-2">
        <label class="text-sm font-semibold leading-[1.4] text-text-primary" for="template_id">
          Select Template <x-form.required />
        </label>
        <select
          id="template_id"
          name="template_id"
          required
          data-campaign-template-select
          class="w-full rounded-xl border border-border bg-elevated p-3.5 text-sm font-medium leading-[1.4] text-text-muted outline-none focus:border-green-500"
        >
          <option value="">Choose</option>
          @foreach ($templates ?? [] as $template)
            @php
              $category = strtoupper((string) ($template->category ?? ''));
              $categoryLabel = TemplateCategoryCatalog::label($category);
              $isMarketing = $category === TemplateCategoryCatalog::MARKETING;
            @endphp
            <option
              value="{{ $template->uuid }}"
              @selected(
                $selectedTemplateId === (string) $template->uuid
                || $selectedTemplateId === (string) $template->id
              )
              data-category="{{ $category }}"
              @if ($categoryLabel !== '') data-category-pill="{{ $categoryLabel }}" @endif
              @if ($isMarketing) data-show-pricing="1" @endif
              @if ($template->has_variables) data-pill="contain variables" @endif
            >
              {{ $template->name }}
            </option>
          @endforeach
        </select>
        @error('template_id') <p class="text-xs text-red-500">{{ $message }}</p> @enderror
      </div>

      <div
        data-campaign-marketing-pricing
        @class([
          'flex flex-col gap-6',
          'hidden' => ! $showMarketingPricing,
        ])
      >
        <x-campaigns.pricing-notice />
        <x-campaigns.cost-banner />
      </div>
    </div>

    <x-templates.phone-preview
      title="Message Preview"
      subtitle="Template preview message look like"
      size="compact"
    >
      <x-templates.message-preview-bubble :preview-data="$previewData" size="compact" scroll-body />
    </x-templates.phone-preview>
  </form>
</x-campaigns.create-layout>
