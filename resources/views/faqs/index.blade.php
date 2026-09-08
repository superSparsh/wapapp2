<x-layouts.app title="FAQs - WapApp" active="faqs.index">
  <div class="flex flex-col bg-surface">
    <div class="flex flex-col gap-4 p-4">
      <h1 class="w-full text-2xl font-bold leading-[1.5] text-text-primary">FAQs</h1>

      <form method="get" action="{{ route('faqs.index') }}" class="flex w-full max-w-[386px] items-center gap-3 overflow-hidden rounded-lg bg-elevated p-3">
        <img src="{{ asset('images/faqs/search.svg') }}" alt="" class="size-5 shrink-0" width="20" height="20">
        <input
          id="faq-search"
          type="search"
          name="q"
          value="{{ $search }}"
          placeholder="Search FAQs"
          class="min-w-0 flex-1 bg-transparent text-sm font-medium leading-[1.4] text-text-body/60 placeholder:text-text-body/60 placeholder:opacity-60 focus:outline-none"
        >
      </form>
    </div>

    <section id="faq-list" class="flex flex-col gap-4 bg-surface p-4 pt-0">
      @forelse ($faqs as $faq)
        <div
          class="faq-item flex flex-col overflow-hidden rounded-xl"
          data-heading="{{ strtolower($faq['heading']) }}"
        >
          <details class="group" @if ($faq['expanded']) open @endif id="faq-{{ $faq['slug'] }}">
            <summary class="flex cursor-pointer list-none items-center gap-3 bg-green-100 p-4 [&::-webkit-details-marker]:hidden">
              <span class="shrink-0 text-lg font-semibold leading-[1.3] whitespace-nowrap text-text-body">{{ $faq['number'] }}</span>
              <p class="min-w-0 flex-1 text-lg font-medium leading-[1.55] text-text-body">{{ $faq['heading'] }}</p>
              <img
                src="{{ asset('images/faqs/arrow-down.svg') }}"
                alt=""
                class="size-6 shrink-0 transition-transform group-open:rotate-180"
                width="24"
                height="24"
              >
            </summary>
            <div class="flex flex-col gap-5 rounded-bl-xl rounded-br-xl border-2 border-solid border-green-100 p-4">
              <div class="prose prose-sm max-w-none text-sm font-normal leading-[1.5] text-text-body opacity-80">
                {!! $faq['description'] !!}
              </div>
            </div>
          </details>
        </div>
      @empty
        <div class="rounded-xl bg-green-100 p-6 text-center text-sm text-text-muted">
          No FAQs found.
        </div>
      @endforelse
    </section>
  </div>

  <script>
    (() => {
      const input = document.getElementById('faq-search');
      const items = document.querySelectorAll('.faq-item');

      if (!input || !items.length) {
        return;
      }

      const filter = () => {
        const value = input.value.trim().toLowerCase();
        items.forEach((item) => {
          const heading = item.dataset.heading || '';
          item.classList.toggle('hidden', value !== '' && !heading.includes(value));
        });
      };

      input.addEventListener('input', filter);
      filter();

      const activeSlug = @json($activeSlug);
      if (activeSlug) {
        const target = document.getElementById(`faq-${activeSlug}`);
        target?.scrollIntoView({ behavior: 'smooth', block: 'center' });
      }
    })();
  </script>
</x-layouts.app>
