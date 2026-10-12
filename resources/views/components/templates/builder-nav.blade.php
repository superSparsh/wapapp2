@props(['active' => 'header', 'template' => null, 'setupComplete' => true, 'builderSteps' => null])

@php
  use App\Domains\Templates\Support\TemplateCategoryCatalog;

  $templateParam = $template ? ['template' => $template] : [];
  $category = strtoupper((string) ($template?->category ?? ''));
  $payload = $template?->wizardPayload() ?? [];
  $isAuth = TemplateCategoryCatalog::isAuthentication($category);
  $isLto = TemplateCategoryCatalog::isLto($category) || (bool) data_get($payload, 'lto.enabled', false);
  $isCarousel = (bool) data_get($payload, 'carousel.enabled', false)
      || TemplateCategoryCatalog::isCarousel($category)
      || TemplateCategoryCatalog::isCarouselSelection($category);

  // Full catalog of steps; JS + server visibility keep only the active flow.
  $allSteps = [
      ['route' => 'templates.builder.body', 'key' => 'body', 'label' => 'Body', 'required' => true, 'flows' => ['standard', 'auth', 'lto', 'carousel']],
      ['route' => 'templates.builder.auth', 'key' => 'auth', 'label' => 'Authentication', 'flows' => ['auth']],
      ['route' => 'templates.builder.lto', 'key' => 'lto', 'label' => 'Limited Time Offer', 'flows' => ['lto']],
      ['route' => 'templates.builder.carousel', 'key' => 'carousel', 'label' => 'Carousel', 'flows' => ['carousel']],
      ['route' => 'templates.builder.header', 'key' => 'header', 'label' => 'Header', 'flows' => ['standard', 'lto']],
      ['route' => 'templates.builder.footer', 'key' => 'footer', 'label' => 'Footer', 'flows' => ['standard', 'lto']],
      ['route' => 'templates.builder.buttons', 'key' => 'buttons', 'label' => 'Buttons', 'flows' => ['standard', 'lto']],
      ['route' => 'templates.builder.submit', 'key' => 'submit', 'label' => 'Submit For Approval', 'flows' => ['standard', 'auth', 'lto', 'carousel']],
  ];

  $currentFlow = $isAuth ? 'auth' : ($isCarousel ? 'carousel' : ($isLto ? 'lto' : 'standard'));
@endphp

<nav
  class="flex flex-wrap items-center overflow-x-auto border-b-2 border-blue-50"
  data-builder-nav
  data-builder-flow="{{ $currentFlow }}"
  data-setup-complete="{{ $setupComplete ? '1' : '0' }}"
>
  @foreach ($allSteps as $step)
    @php
      $inFlow = in_array($currentFlow, $step['flows'], true);
      $isDisabled = (! $setupComplete && $step['key'] !== 'body') || ! $template;
      $href = ($isDisabled || ! $template) ? '#' : route($step['route'], $templateParam);
    @endphp
    <a
      href="{{ $href }}"
      data-nav-key="{{ $step['key'] }}"
      data-nav-flows="{{ implode(',', $step['flows']) }}"
      @if ($isDisabled) aria-disabled="true" tabindex="-1" @endif
      @class([
        'fd-tab whitespace-nowrap px-9 py-2.5 text-center transition-colors',
        'border-b-2 border-green-500 bg-green-100' => $active === $step['key'],
        'hover:bg-surface' => $active !== $step['key'] && ! $isDisabled,
        'pointer-events-none opacity-50' => $isDisabled,
        'hidden' => ! $inFlow,
      ])
    >
      {{ $step['label'] }}@if (! empty($step['required']))<span class="text-[red]">*</span>@endif
    </a>
  @endforeach
</nav>
