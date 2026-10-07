@props([
    'headers' => [],
    'pagination' => null,
    'paginator' => null,
    'total' => null,
    'perPage' => null,
    'pages' => null,
    'current' => null,
    'pageName' => 'page',
    'columnWidths' => [],
    'bulkSelect' => false,
])

@php
  // One pagination only. Show when we have a real paginator or explicit page meta.
  $showPagination = $paginator !== null
      || $pagination === true
      || $pages !== null
      || $current !== null;
  $widths = $columnWidths;
  if ($bulkSelect) {
      array_unshift($widths, 'w-10');
  }
@endphp

<div {{ $attributes->class('overflow-hidden rounded-xl bg-elevated shadow-[0px_4px_6px_rgba(0,0,0,0.04)]') }}>
  <div class="overflow-x-auto">
    <table class="w-full min-w-[900px] text-left">
      <thead>
        <tr class="bg-elevated">
          @if ($bulkSelect)
            <th class="fd-table-head w-10 p-2">
              <input type="checkbox" data-bulk-select-all class="size-4 rounded border-border" aria-label="Select all">
            </th>
          @endif
          @foreach ($headers as $i => $header)
            <th class="fd-table-head p-2 {{ $widths[$bulkSelect ? $i + 1 : $i] ?? '' }}">{{ $header }}</th>
          @endforeach
        </tr>
      </thead>
      <tbody class="divide-y divide-[rgba(13,13,13,0.1)]">
        {{ $slot }}
      </tbody>
    </table>
  </div>
  @if ($showPagination)
    <x-ui.table-pagination
      :paginator="$paginator"
      :total="$total"
      :per-page="$perPage"
      :pages="$pages"
      :current="$current"
      :page-name="$pageName"
    />
  @endif
</div>
