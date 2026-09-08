<x-layouts.app title="Tutorials - WapApp" active="tutorials.index">
  <div class="flex flex-col bg-surface">
    <div class="flex flex-col items-start justify-center p-4">
      <h1 class="w-full text-2xl font-bold leading-[1.5] text-text-primary">Tutorials</h1>
    </div>

    <section class="bg-surface p-4 pt-0">
      <div class="flex flex-col items-start gap-4 rounded-lg bg-elevated p-3 lg:flex-row">
        <x-tutorials.sidebar :active-module="1" />

        <div class="flex min-w-0 w-full flex-1 flex-col gap-4 self-stretch rounded-lg bg-muted-surface p-3">
          <div class="flex flex-col gap-2">
            <h2 class="text-xl font-semibold leading-[1.4] text-text-primary">Tutorial Modules Names</h2>
            <div class="space-y-4 text-sm font-normal leading-[1.5] text-text-body/80">
              <p>Lorem Ipsum is simply dummy text of the printing and typesetting industry. Lorem Ipsum has been the industry's standard dummy text ever since the 1500s.</p>
              <p>It has survived not only five centuries, but also the leap into electronic typesetting, remaining essentially unchanged.</p>
            </div>
          </div>
          <a href="{{ route('tutorials.index') }}" class="fd-btn-sm inline-flex text-green-500 underline">Back to tutorials</a>
        </div>
      </div>
    </section>
  </div>
</x-layouts.app>
