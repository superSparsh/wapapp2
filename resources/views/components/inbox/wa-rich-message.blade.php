@props([
    'body' => '',
    'header' => null,
    'footer' => '',
    'actions' => [],
    'list' => null,
    'caption' => null,
])

@php
  use App\Domains\Templates\Support\WhatsAppTextFormatter;

  $bodyText = trim((string) $body);
  $bodyHtml = $bodyText !== '' ? WhatsAppTextFormatter::toHtml($bodyText) : '';
  $footerText = trim((string) $footer);
  $headerData = is_array($header) ? $header : null;
  $headerType = strtolower((string) ($headerData['type'] ?? ''));
  $headerText = trim((string) ($headerData['text'] ?? ''));
  $headerMedia = trim((string) ($headerData['media_url'] ?? ''));
  $listData = is_array($list) ? $list : null;
  $listSections = is_array($listData['sections'] ?? null) ? $listData['sections'] : [];
@endphp

<div {{ $attributes->class('flex w-full min-w-[180px] max-w-full flex-col gap-1.5') }}>
  @if ($headerText !== '')
    <div class="text-sm font-semibold leading-[1.4] text-text-body">{{ $headerText }}</div>
  @endif

  @if ($headerMedia !== '' && $headerType === 'image')
    <img src="{{ $headerMedia }}" alt="" class="max-h-48 w-full rounded-lg object-cover">
  @elseif ($headerMedia !== '' && $headerType === 'video')
    <video src="{{ $headerMedia }}" class="max-h-48 w-full rounded-lg object-cover" muted playsinline preload="metadata"></video>
  @elseif ($headerMedia !== '' && $headerType === 'document')
    <a href="{{ $headerMedia }}" target="_blank" rel="noopener" class="rounded-lg border border-black/10 bg-white/70 px-3 py-2 text-xs font-semibold text-link-green underline">
      Open document
    </a>
  @endif

  @if ($bodyHtml !== '')
    <div class="wa-preview-body text-xs leading-[1.6] text-text-body break-words">{!! $bodyHtml !!}</div>
  @endif

  @if ($footerText !== '')
    <div class="text-[11px] leading-[1.4] text-text-body/55">{{ $footerText }}</div>
  @endif

  @if ($listSections !== [])
    <div class="mt-0.5 overflow-hidden rounded-lg border border-black/10 bg-white/60">
      @foreach ($listSections as $section)
        @php
          $sectionTitle = trim((string) ($section['title'] ?? ''));
          $rows = is_array($section['rows'] ?? null) ? $section['rows'] : [];
        @endphp
        @if ($sectionTitle !== '')
          <div class="border-b border-black/5 bg-black/[0.03] px-2.5 py-1 text-[10px] font-semibold uppercase tracking-wide text-text-body/50">
            {{ $sectionTitle }}
          </div>
        @endif
        @foreach ($rows as $row)
          @php
            $rowTitle = trim((string) ($row['title'] ?? ''));
            $rowDescription = trim((string) ($row['description'] ?? ''));
          @endphp
          @continue($rowTitle === '' && $rowDescription === '')
          <div class="border-b border-black/5 px-2.5 py-1.5 last:border-b-0">
            @if ($rowTitle !== '')
              <div class="text-xs font-medium text-text-body">{{ $rowTitle }}</div>
            @endif
            @if ($rowDescription !== '')
              <div class="text-[11px] text-text-body/55">{{ $rowDescription }}</div>
            @endif
          </div>
        @endforeach
      @endforeach
    </div>
  @endif

  <x-inbox.wa-action-rows :actions="$actions" />

  @if (filled($caption))
    <div class="mt-0.5 text-[10px] font-semibold uppercase tracking-wide text-green-700/80">{{ $caption }}</div>
  @endif
</div>
