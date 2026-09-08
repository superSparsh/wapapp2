@props(['step' => 1])

@php
$tabForStep = match (true) {
    in_array($step, [1, 2], true) => 'info',
    $step === 3 => 'template',
    in_array($step, [4, 5], true) => 'variables',
    default => 'schedule',
};

$tabs = [
    'info' => [
        'label' => 'Info & Recipients',
        'route' => route('campaigns.create.step', $step === 2 ? 2 : 1),
    ],
    'template' => ['label' => 'Template', 'route' => route('campaigns.create.step', 3)],
    'variables' => [
        'label' => 'Variables',
        'route' => route('campaigns.create.step', $step === 5 ? 5 : 4),
    ],
    'schedule' => ['label' => 'Schedule & Confirm', 'route' => route('campaigns.create.step', 6)],
];
@endphp

<nav class="flex flex-wrap overflow-x-auto border-b-2 border-blue-50">
  @foreach ($tabs as $key => $tab)
    <a
      href="{{ $tab['route'] }}"
      @class([
        'fd-tab whitespace-nowrap px-9 py-2.5 transition-colors',
        'border-b-2 border-green-500 bg-green-100 text-text-body' => $tabForStep === $key,
        'text-text-body hover:bg-surface' => $tabForStep !== $key,
      ])
    >
      {{ $tab['label'] }}
    </a>
  @endforeach
</nav>
