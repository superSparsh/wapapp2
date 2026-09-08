@props([
  'categories' => [],
  'search' => '',
])

<aside class="flex w-full shrink-0 flex-col gap-3 lg:sticky lg:top-4 lg:w-[280px] lg:max-h-[calc(100vh-2rem)]">
  <label class="flex shrink-0 items-center gap-3 overflow-hidden rounded-lg border border-solid border-[#707070] p-3">
    <img src="{{ asset('images/tutorials/search.svg') }}" alt="" class="size-5 shrink-0" width="20" height="20">
    <input
      id="tutorial-search"
      type="search"
      value="{{ $search }}"
      placeholder="Search tutorials"
      autocomplete="off"
      class="min-w-0 flex-1 bg-transparent text-sm font-medium leading-[1.4] text-text-body placeholder:text-text-body/60 focus:outline-none"
    >
  </label>

  <div id="tutorial-sidebar-list" class="min-h-0 flex-1 space-y-1 overflow-y-auto overscroll-y-contain pr-1">
    @forelse ($categories as $index => $category)
      @php
        $categorySearch = mb_strtolower($category['label']);
      @endphp

      <details
        class="tutorial-category group"
        data-tutorial-category
        data-search="{{ $categorySearch }}"
        @if ($category['expanded']) open @endif
      >
        <summary class="flex cursor-pointer list-none items-center gap-2 rounded-xl border border-solid border-green-100 bg-elevated px-2 py-1.5 transition-colors group-open:bg-green-100 [&::-webkit-details-marker]:hidden">
          <span class="p-2 text-[13px] font-normal leading-[1.5] whitespace-nowrap text-text-body">{{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}</span>
          <span class="min-w-0 flex-1 p-2 text-xs font-semibold uppercase leading-[1.5] text-text-subtle">{{ $category['label'] }}</span>
          <img
            src="{{ asset('images/tutorials/arrow-down.svg') }}"
            alt=""
            class="size-5 shrink-0 transition-transform group-open:rotate-180"
            width="20"
            height="20"
          >
        </summary>

        <div class="mt-1 space-y-1 pb-1">
          @if (! empty($category['children']))
            @foreach ($category['children'] as $child)
              @php
                $childSearch = mb_strtolower($child['label']);
              @endphp
              <div class="tutorial-submodule space-y-1 pl-2" data-tutorial-submodule data-search="{{ $childSearch }}">
                <p class="px-3 py-1 text-[11px] font-semibold uppercase tracking-wide text-text-muted">{{ $child['label'] }}</p>
                @foreach ($child['videos'] as $video)
                  <a
                    href="{{ $video['url'] }}"
                    data-tutorial-video
                    data-search="{{ mb_strtolower($video['title'].' '.$category['label'].' '.$child['label']) }}"
                    @class([
                      'flex items-center gap-2 rounded-lg px-3 py-2 text-xs font-medium leading-[1.5] transition-colors',
                      'bg-green-500 text-primary-2' => $video['active'],
                      'bg-green-50 text-text-subtle hover:bg-green-100' => ! $video['active'],
                    ])
                  >
                    <img
                      src="{{ asset($video['active'] ? 'images/tutorials/video-circle-active.svg' : 'images/tutorials/video-circle.svg') }}"
                      alt=""
                      class="size-5 shrink-0"
                      width="20"
                      height="20"
                    >
                    <span class="min-w-0 flex-1 leading-snug">{{ $video['title'] }}</span>
                  </a>
                @endforeach
              </div>
            @endforeach
          @elseif (! empty($category['videos']))
            <div class="space-y-1">
              @foreach ($category['videos'] as $video)
                <a
                  href="{{ $video['url'] }}"
                  data-tutorial-video
                  data-search="{{ mb_strtolower($video['title'].' '.$category['label']) }}"
                  @class([
                    'flex items-center gap-2 rounded-lg px-3 py-2 text-xs font-medium leading-[1.5] transition-colors',
                    'bg-green-500 text-primary-2' => $video['active'],
                    'bg-green-50 text-text-subtle hover:bg-green-100' => ! $video['active'],
                  ])
                >
                  <img
                    src="{{ asset($video['active'] ? 'images/tutorials/video-circle-active.svg' : 'images/tutorials/video-circle.svg') }}"
                    alt=""
                    class="size-5 shrink-0"
                    width="20"
                    height="20"
                  >
                  <span class="min-w-0 flex-1 leading-snug">{{ $video['title'] }}</span>
                </a>
              @endforeach
            </div>
          @endif
        </div>
      </details>
    @empty
      <div class="rounded-lg bg-elevated p-4 text-sm text-text-muted">
        No tutorials match your search.
      </div>
    @endforelse

    <p id="tutorial-search-empty" class="hidden rounded-lg bg-elevated p-4 text-sm text-text-muted">
      No tutorials match your search.
    </p>
  </div>
</aside>
