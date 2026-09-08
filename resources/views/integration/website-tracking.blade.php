<x-layouts.app title="Website Tracking - WapApp" active="integration.index">
  <div class="flex flex-col bg-surface">
    <div class="p-4">
      <x-ui.page-header title="Website Tracking" subtitle="Generate tracking snippets for your websites." />
    </div>

    @if (session('status'))
      <div class="mx-4 mb-2 rounded-lg bg-green-100 px-4 py-2 text-sm text-green-700">{{ session('status') }}</div>
    @endif

    <section class="grid gap-4 p-4 pt-0 lg:grid-cols-[360px_1fr]">
      <form method="post" action="{{ route('integration.websites.store') }}" class="flex flex-col gap-4 rounded-xl border border-border bg-elevated p-5">
        @csrf
        <h2 class="fd-card-title">Add Tracker</h2>

        <div class="flex flex-col gap-1">
          <x-form.label for="name">Name</x-form.label>
          <x-form.input id="name" name="name" required />
        </div>

        <div class="flex flex-col gap-1">
          <x-form.label for="domain">Domain</x-form.label>
          <x-form.input id="domain" name="domain" placeholder="example.com" required />
        </div>

        <button type="submit" class="fd-btn inline-flex items-center justify-center rounded bg-green-500 px-4 py-3 text-sm font-semibold text-primary-2">
          Create Tracker
        </button>
      </form>

      <div class="rounded-xl border border-border bg-elevated p-5">
        <h2 class="fd-card-title mb-4">Trackers</h2>

        @if ($trackers->isEmpty())
          <p class="text-sm text-text-muted">No website trackers yet.</p>
        @else
          <x-ui.data-table :headers="['Name', 'Domain', 'Snippet', 'Status']" :total="$trackers->count()">
            @foreach ($trackers as $tracker)
              <tr class="bg-elevated">
                <td class="fd-table-cell p-2 align-middle">{{ $tracker->name }}</td>
                <td class="fd-table-cell p-2 align-middle">{{ $tracker->domain }}</td>
                <td class="fd-table-cell p-2 align-middle">
                  <code class="text-xs">&lt;script src="{{ url('/v1/track/'.$tracker->tracking_token.'.js') }}"&gt;&lt;/script&gt;</code>
                </td>
                <td class="fd-table-cell p-2 align-middle">{{ $tracker->status }}</td>
              </tr>
            @endforeach
          </x-ui.data-table>
        @endif
      </div>
    </section>
  </div>
</x-layouts.app>
