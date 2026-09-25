<x-layouts.app title="Tutorials - WapApp" active="tutorials.index">
  <div class="flex min-h-full flex-col bg-surface">
    <div class="shrink-0 p-4 pb-0">
      <h1 class="text-2xl font-bold leading-[1.5] text-text-primary">Tutorials</h1>
    </div>

    <section class="flex-1 p-4">
      <div
        role="status"
        class="mb-4 rounded-xl border border-green-500 bg-green-50 px-4 py-3 text-sm leading-[1.5] text-text-primary"
      >
        <p class="font-semibold text-green-700">Note: These are tutorials from the previous WapApp design.</p>
        <p class="mt-1 text-text-subtle">
          We will upload updated videos for the new UI soon. The steps are mostly the same — only the look has changed.
        </p>
      </div>

      <div class="flex h-full flex-col gap-4 rounded-lg bg-elevated p-3 lg:min-h-[calc(100vh-8rem)] lg:flex-row lg:items-start">
        <x-tutorials.sidebar :categories="$categories" :search="$search" />

        <div class="flex min-w-0 w-full flex-1 flex-col gap-4 lg:sticky lg:top-4 lg:max-h-[calc(100vh-2rem)] lg:self-start lg:overflow-y-auto lg:rounded-lg lg:bg-muted-surface lg:p-3">
          @if ($current)
            <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
              <div class="min-w-0 flex-1">
                <h2 class="text-xl font-semibold leading-[1.4] text-text-primary">{{ $current['title'] }}</h2>
              </div>
              <button
                type="button"
                id="tutorial-share-btn"
                data-share-url="{{ $shareUrl }}"
                data-video-id="{{ $current['id'] }}"
                class="fd-btn-sm inline-flex shrink-0 items-center justify-center gap-2 rounded border border-solid border-green-500 px-6 py-3 text-xs font-semibold leading-[1.5] text-green-500 transition-colors hover:bg-green-50"
              >
                Copy Link
                <img src="{{ asset('images/tutorials/share.svg') }}" alt="" class="size-5" width="20" height="20">
              </button>
            </div>

            <div class="relative aspect-video w-full shrink-0 overflow-hidden rounded-xl bg-black shadow-sm">
              @if (! empty($current['stream_url']))
                <video
                  controls
                  playsinline
                  preload="metadata"
                  class="absolute inset-0 size-full object-contain"
                  src="{{ $current['stream_url'] }}{{ str_contains($current['stream_url'], '?') ? '&' : '?' }}v={{ $current['id'] }}"
                >
                  Your browser does not support the video tag.
                </video>
              @else
                <div class="absolute inset-0 flex flex-col items-center justify-center gap-1 px-6 text-center text-sm text-white/80">
                  <p>Video file not found on server.</p>
                  <p class="text-xs text-white/60">Upload the MP4 to <code class="text-white/80">public/assets/videos/tutorials/</code> with the exact filename from admin.</p>
                </div>
              @endif
            </div>

            @if (! empty($current['description']))
              <p class="text-sm leading-[1.6] text-text-body/80">{{ $current['description'] }}</p>
            @endif

            <div class="flex flex-col gap-3 border-t border-divider pt-3 sm:flex-row sm:items-center sm:justify-between">
              <div class="flex flex-wrap items-center gap-2.5">
                @if ($navigation['previous_id'])
                  <a
                    href="{{ route('tutorials.index', array_filter(['video_id' => $navigation['previous_id'], 'q' => $search !== '' ? $search : null], fn ($value) => $value !== null && $value !== '')) }}"
                    class="fd-btn-sm inline-flex items-center justify-center gap-2 rounded border border-solid border-green-500 px-6 py-3 text-xs font-semibold leading-[1.5] text-green-500 transition-colors hover:bg-green-50"
                  >
                    <img src="{{ asset('images/tutorials/arrow-left.svg') }}" alt="" class="size-5" width="20" height="20">
                    Previous
                  </a>
                @endif

                @if ($navigation['next_id'])
                  <a
                    href="{{ route('tutorials.index', array_filter(['video_id' => $navigation['next_id'], 'q' => $search !== '' ? $search : null], fn ($value) => $value !== null && $value !== '')) }}"
                    class="fd-btn-sm inline-flex items-center justify-center gap-2 rounded border border-solid border-green-500 px-6 py-3 text-xs font-semibold leading-[1.5] text-green-500 transition-colors hover:bg-green-50"
                  >
                    Next
                    <img src="{{ asset('images/tutorials/arrow-right.svg') }}" alt="" class="size-5" width="20" height="20">
                  </a>
                @endif
              </div>

              <div class="flex flex-wrap items-center justify-end gap-4">
                <span class="text-sm font-semibold leading-[1.5] whitespace-nowrap text-green-500">
                  {{ $navigation['index'] }} / {{ $navigation['total'] }}
                </span>
                <button
                  type="button"
                  id="tutorial-complete-btn"
                  data-video-id="{{ $current['id'] }}"
                  class="fd-btn-sm inline-flex items-center justify-center rounded bg-green-500 px-6 py-3 text-xs font-semibold leading-[1.5] text-primary-2 transition-opacity hover:opacity-90"
                >
                  Mark as Complete
                </button>
              </div>
            </div>
          @else
            <div class="flex min-h-[320px] flex-col items-center justify-center gap-3 rounded-xl bg-elevated p-8 text-center">
              <p class="text-lg font-semibold text-text-primary">No tutorials yet</p>
              <p class="text-sm text-text-muted">Tutorial videos will appear here once they are published.</p>
            </div>
          @endif
        </div>
      </div>
    </section>
  </div>

  <script>
    (() => {
      const storageKey = 'tutorial_completed_videos';
      const completeBtn = document.getElementById('tutorial-complete-btn');
      const shareBtn = document.getElementById('tutorial-share-btn');
      const searchInput = document.getElementById('tutorial-search');
      const emptyState = document.getElementById('tutorial-search-empty');

      const filterSidebar = () => {
        if (!searchInput) {
          return;
        }

        const query = searchInput.value.trim().toLowerCase();
        let visibleCategories = 0;

        document.querySelectorAll('[data-tutorial-category]').forEach((category) => {
          let categoryHasMatch = false;
          const categoryMatches = query === '' || (category.dataset.search || '').includes(query);

          category.querySelectorAll('[data-tutorial-video]').forEach((video) => {
            const matches = query === '' || (video.dataset.search || '').includes(query);
            video.classList.toggle('hidden', !matches);
            if (matches) {
              categoryHasMatch = true;
            }
          });

          category.querySelectorAll('[data-tutorial-submodule]').forEach((submodule) => {
            const visibleVideos = [...submodule.querySelectorAll('[data-tutorial-video]')].filter((video) => !video.classList.contains('hidden'));
            submodule.classList.toggle('hidden', visibleVideos.length === 0 && query !== '');
            if (visibleVideos.length > 0) {
              categoryHasMatch = true;
            }
          });

          const shouldShow = query === '' ? true : (categoryMatches || categoryHasMatch);
          category.classList.toggle('hidden', !shouldShow);

          if (shouldShow) {
            visibleCategories += 1;
            if (query !== '' && categoryHasMatch && category instanceof HTMLDetailsElement) {
              category.open = true;
            }
          }
        });

        emptyState?.classList.toggle('hidden', visibleCategories > 0 || query === '');
      };

      searchInput?.addEventListener('input', filterSidebar);
      filterSidebar();

      if (completeBtn) {
        const videoId = Number(completeBtn.dataset.videoId || 0);
        const saved = new Set(JSON.parse(localStorage.getItem(storageKey) || '[]'));

        const syncLabel = () => {
          completeBtn.textContent = saved.has(videoId) ? 'Mark as Incomplete' : 'Mark as Complete';
        };

        syncLabel();

        completeBtn.addEventListener('click', () => {
          if (saved.has(videoId)) {
            saved.delete(videoId);
          } else {
            saved.add(videoId);
          }
          localStorage.setItem(storageKey, JSON.stringify([...saved]));
          syncLabel();
        });
      }

      shareBtn?.addEventListener('click', async () => {
        const videoId = shareBtn.dataset.videoId || '';
        let url = shareBtn.dataset.shareUrl || window.location.href;

        try {
          const parsed = new URL(url, window.location.origin);
          if (videoId) {
            parsed.searchParams.set('video_id', videoId);
          }
          // Always use the current host (APP_URL may be wrong on server).
          parsed.protocol = window.location.protocol;
          parsed.host = window.location.host;
          url = parsed.toString();
        } catch (e) {
          // keep url as-is
        }

        const label = shareBtn.childNodes[0];
        const setLabel = (text) => {
          if (label && label.nodeType === Node.TEXT_NODE) {
            label.textContent = text;
          } else {
            shareBtn.textContent = text;
          }
        };

        const copied = async () => {
          setLabel('Copied!');
          setTimeout(() => setLabel('Copy Link'), 1600);
        };

        try {
          if (navigator.clipboard?.writeText) {
            await navigator.clipboard.writeText(url);
            await copied();
            return;
          }
        } catch (e) {
          // fall through
        }

        const input = document.createElement('input');
        input.value = url;
        input.setAttribute('readonly', '');
        input.style.position = 'fixed';
        input.style.opacity = '0';
        document.body.appendChild(input);
        input.select();
        input.setSelectionRange(0, input.value.length);
        try {
          document.execCommand('copy');
          await copied();
        } catch (e) {
          window.prompt('Copy this link:', url);
        } finally {
          document.body.removeChild(input);
        }
      });
    })();
  </script>
</x-layouts.app>
