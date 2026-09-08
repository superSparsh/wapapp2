@props(['headers' => [], 'pagination' => false, 'paginator' => null, 'total' => 0])

<div class="overflow-hidden rounded-xl border border-divider bg-elevated shadow-[0px_4px_12px_rgba(0,0,0,0.04)]">
  <div class="overflow-x-auto">
    <table class="w-full min-w-[700px] text-left">
      <thead>
        <tr class="bg-elevated">
          @foreach ($headers as $header)
            <th class="fd-table-head p-2">{{ $header }}</th>
          @endforeach
        </tr>
      </thead>
      <tbody class="divide-y divide-[rgba(13,13,13,0.1)]">
        {{ $slot }}
      </tbody>
    </table>
  </div>
  @if ($paginator)
    <x-ui.table-pagination :paginator="$paginator" />
  @elseif ($pagination)
    <x-ui.table-pagination :total="$total" />
  @endif
</div>
