@props([
    'value' => '',
    'label' => 'UID',
    'compact' => false,
])

@php
    $uid = trim((string) $value);
    $short = $uid;
    if (strlen($uid) > 16) {
        $short = substr($uid, 0, 8).'…'.substr($uid, -4);
    }
@endphp

@if ($uid !== '')
  <div
    {{ $attributes->class([
      'group/copy-uid inline-flex max-w-full items-center gap-1.5 rounded-lg border border-border bg-surface/80',
      $compact ? 'px-1.5 py-0.5' : 'px-2 py-1',
    ]) }}
  >
    <span @class([
      'shrink-0 font-semibold uppercase tracking-wide text-text-subtle',
      $compact ? 'text-[10px]' : 'text-[11px]',
    ])>{{ $label }}</span>
    <code
      class="min-w-0 truncate font-mono text-[11px] leading-none text-text-body"
      title="{{ $uid }}"
    >{{ $short }}</code>
    <button
      type="button"
      data-copy-uid="{{ $uid }}"
      class="inline-flex shrink-0 items-center gap-1 rounded-md border border-green-500/30 bg-green-50 px-1.5 py-0.5 text-[11px] font-semibold text-green-600 transition hover:bg-green-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-green-500/40"
      aria-label="Copy {{ $label }}"
      title="Copy {{ $label }}"
    >
      <img src="{{ asset('images/icons/clipboard-text.svg') }}" alt="" class="size-3 opacity-80" width="12" height="12">
      <span data-copy-uid-label>Copy</span>
    </button>
  </div>

  @once
    @push('scripts')
      <script>
        (function () {
          if (window.__copyUidInit) return;
          window.__copyUidInit = true;

          document.addEventListener('click', async function (event) {
            var btn = event.target.closest('[data-copy-uid]');
            if (!btn) return;

            event.preventDefault();
            event.stopPropagation();

            var value = btn.getAttribute('data-copy-uid') || '';
            if (!value) return;

            var label = btn.querySelector('[data-copy-uid-label]');
            var previous = label ? label.textContent : '';

            try {
              if (navigator.clipboard && navigator.clipboard.writeText) {
                await navigator.clipboard.writeText(value);
              } else {
                var input = document.createElement('textarea');
                input.value = value;
                input.setAttribute('readonly', '');
                input.style.position = 'fixed';
                input.style.left = '-9999px';
                document.body.appendChild(input);
                input.select();
                document.execCommand('copy');
                document.body.removeChild(input);
              }

              if (label) {
                label.textContent = 'Copied';
                window.setTimeout(function () {
                  label.textContent = previous || 'Copy';
                }, 1600);
              }
            } catch (error) {
              if (label) {
                label.textContent = 'Failed';
                window.setTimeout(function () {
                  label.textContent = previous || 'Copy';
                }, 1600);
              }
            }
          });
        })();
      </script>
    @endpush
  @endonce
@endif
