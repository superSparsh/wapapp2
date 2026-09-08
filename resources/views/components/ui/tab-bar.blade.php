@props(['tabs' => [], 'active' => 0])

<div class="flex gap-0 border-b-2 border-blue-50">
  @foreach ($tabs as $index => $tab)
    <button
      type="button"
      @class([
        'fd-tab px-9 py-2.5 transition-colors',
        'border-b-2 border-green-500 bg-green-100' => $index === $active,
        'hover:bg-surface' => $index !== $active,
      ])
    >
      {{ $tab }}
    </button>
  @endforeach
</div>
