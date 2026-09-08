@props(['active' => 'header', 'template' => null, 'setupComplete' => true, 'builderSteps' => null])

@php
$templateParam = $template ? ['template' => $template] : [];
$steps = $builderSteps ?? [
    ['route' => 'templates.builder.body', 'key' => 'body', 'label' => 'Body', 'required' => true],
    ['route' => 'templates.builder.header', 'key' => 'header', 'label' => 'Header'],
    ['route' => 'templates.builder.footer', 'key' => 'footer', 'label' => 'Footer'],
    ['route' => 'templates.builder.buttons', 'key' => 'buttons', 'label' => 'Buttons'],
    ['route' => 'templates.builder.submit', 'key' => 'submit', 'label' => 'Submit For Approval'],
];
@endphp

<nav class="flex flex-wrap items-center overflow-x-auto border-b-2 border-blue-50">
  @foreach ($steps as $step)
    @php
      $isDisabled = ! $setupComplete && $step['key'] !== 'body';
      $href = $isDisabled ? '#' : route($step['route'], $templateParam);
    @endphp
    <a
      href="{{ $href }}"
      @if ($isDisabled) aria-disabled="true" tabindex="-1" @endif
      @class([
        'fd-tab whitespace-nowrap px-9 py-2.5 text-center transition-colors',
        'border-b-2 border-green-500 bg-green-100' => $active === $step['key'],
        'hover:bg-surface' => $active !== $step['key'] && ! $isDisabled,
        'pointer-events-none opacity-50' => $isDisabled,
      ])
    >
      {{ $step['label'] }}@if (! empty($step['required']))<span class="text-[red]">*</span>@endif
    </a>
  @endforeach
</nav>
