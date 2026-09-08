@props(['presets' => [], 'categorySelectId' => 'template_category'])

@if (count($presets) > 0)
  <div
    id="utility-presets"
    class="hidden flex-col gap-3 rounded-lg border border-border bg-surface p-3"
    data-utility-presets
  >
    <p class="text-sm font-semibold text-text-body">Utility template presets</p>
    <p class="text-xs text-text-subtle">Select a preset to fill the template name and body. Variables use $(name) format.</p>
    <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
      @foreach ($presets as $preset)
        <div class="flex flex-col gap-2 rounded-lg border border-divider bg-elevated p-3">
          <p class="text-sm font-semibold text-text-body">{{ $preset['title'] }}</p>
          <p class="text-xs text-text-subtle">Name: <code>{{ $preset['name'] }}</code></p>
          <pre class="max-h-24 overflow-auto whitespace-pre-wrap text-xs text-text-muted">{{ $preset['body'] }}</pre>
          <button
            type="button"
            class="fd-btn-sm w-fit rounded border border-green-500 px-3 py-1.5 text-sm text-green-500"
            data-utility-preset-use
            data-preset-name="{{ $preset['name'] }}"
            data-preset-body="{{ $preset['body'] }}"
          >
            Use Template
          </button>
        </div>
      @endforeach
    </div>
  </div>
@endif
