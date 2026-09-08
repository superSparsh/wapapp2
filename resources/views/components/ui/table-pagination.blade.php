@props([
    'paginator' => null,
    'total' => null,
    'perPage' => null,
    'pages' => null,
    'current' => null,
    'pageName' => 'page',
])

@php
  $hasPaginator = $paginator !== null;
  if ($hasPaginator) {
      $total = $paginator->total();
      $perPage = $paginator->perPage();
      $current = $paginator->currentPage();
      $pages = max(1, $paginator->lastPage());
      $pageName = method_exists($paginator, 'getPageName') ? $paginator->getPageName() : $pageName;
  } else {
      $total = (int) ($total ?? 0);
      $perPage = max(1, (int) ($perPage ?? 10));
      $current = max(1, (int) ($current ?? 1));
      $pages = max(1, (int) ($pages ?? (int) ceil($total / $perPage)));
  }

  $pageUrl = function (int $page) use ($hasPaginator, $paginator, $pageName): string {
      if ($hasPaginator) {
          return (string) $paginator->url($page);
      }

      return request()->fullUrlWithQuery([$pageName => $page]);
  };

  $prevUrl = $hasPaginator
      ? $paginator->previousPageUrl()
      : ($current > 1 ? $pageUrl($current - 1) : null);
  $nextUrl = $hasPaginator
      ? $paginator->nextPageUrl()
      : ($current < $pages ? $pageUrl($current + 1) : null);

  $canNavigate = $hasPaginator || $pages > 1 || $total > $perPage;
@endphp

<div {{ $attributes->class('flex flex-col gap-4 border-t border-transparent bg-elevated px-5 py-2 sm:flex-row sm:items-center sm:justify-between') }}>
  <p class="fd-pagination-total">Total items: {{ $total }}</p>
  <div class="flex flex-wrap items-center gap-[70px]">
    <p class="fd-pagination-meta">Items per page: {{ $perPage }}</p>
    <p class="fd-pagination-meta">Total pages: {{ $pages }}</p>
    <div class="flex items-center gap-[15px] p-2">
      @if ($canNavigate && $prevUrl)
        <a href="{{ $prevUrl }}" class="flex items-center justify-center rounded-[5px] bg-muted-surface px-[5px] py-2.5 shadow-[2px_2px_2px_rgba(95,87,255,0.1)]" aria-label="Previous page">
          <img src="{{ asset('images/templates/pagination-left.svg') }}" alt="" class="size-5" width="20" height="20">
        </a>
      @else
        <span class="flex items-center justify-center rounded-[5px] bg-muted-surface px-[5px] py-2.5 opacity-40 shadow-[2px_2px_2px_rgba(95,87,255,0.1)]" aria-hidden="true">
          <img src="{{ asset('images/templates/pagination-left.svg') }}" alt="" class="size-5" width="20" height="20">
        </span>
      @endif

      @for ($i = max(1, $current - 1); $i <= min($pages, $current + 1); $i++)
        @if ($canNavigate)
          <a
            href="{{ $pageUrl($i) }}"
            @class([
              $i === $current ? 'fd-pagination-page-active' : 'fd-pagination-page-link',
            ])
          >{{ $i }}</a>
        @else
          <span @class([
            $i === $current ? 'fd-pagination-page-active' : 'fd-pagination-page-link',
          ])>{{ $i }}</span>
        @endif
      @endfor

      @if ($canNavigate && $nextUrl)
        <a href="{{ $nextUrl }}" class="flex items-center justify-center rounded-[5px] bg-elevated px-[5px] py-2.5 shadow-[2px_2px_2px_rgba(95,87,255,0.1)]" aria-label="Next page">
          <img src="{{ asset('images/templates/pagination-right.svg') }}" alt="" class="size-5" width="20" height="20">
        </a>
      @else
        <span class="flex items-center justify-center rounded-[5px] bg-elevated px-[5px] py-2.5 opacity-40 shadow-[2px_2px_2px_rgba(95,87,255,0.1)]" aria-hidden="true">
          <img src="{{ asset('images/templates/pagination-right.svg') }}" alt="" class="size-5" width="20" height="20">
        </span>
      @endif
    </div>
  </div>
</div>
