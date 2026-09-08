<x-layouts.app title="Automation Events - WapApp" active="automation.index">
  <div class="flex flex-col bg-surface">
    <div class="flex flex-wrap items-center gap-3 p-4">
      <x-ui.page-header
        title="Automation Events"
        subtitle="Schedule and manage automation event triggers."
        class="min-w-0 flex-1"
      />
      <a href="{{ route('automation.index') }}" class="fd-btn inline-flex items-center gap-2 rounded border border-border bg-elevated px-4 py-2 text-sm">
        Back
      </a>
    </div>

    @if (session('status'))
      <div class="mx-4 mb-2 rounded-lg bg-green-100 px-4 py-2 text-sm text-green-700">{{ session('status') }}</div>
    @endif

    <section class="grid gap-4 p-4 pt-0 lg:grid-cols-[360px_1fr]">
      <form method="post" action="{{ route('automation.events.store') }}" class="flex flex-col gap-4 rounded-xl border border-border bg-elevated p-5">
        @csrf
        <h2 class="fd-card-title">Create Event</h2>

        <div class="flex flex-col gap-1">
          <x-form.label for="name">Name</x-form.label>
          <x-form.input id="name" name="name" value="{{ old('name') }}" required />
        </div>

        <div class="flex flex-col gap-1">
          <x-form.label for="event_type">Event Type</x-form.label>
          <x-form.input id="event_type" name="event_type" value="{{ old('event_type', 'custom') }}" required />
        </div>

        <div class="flex flex-col gap-1">
          <x-form.label for="scheduled_at">Scheduled At</x-form.label>
          <x-form.input id="scheduled_at" name="scheduled_at" type="datetime-local" value="{{ old('scheduled_at') }}" />
        </div>

        <button type="submit" class="fd-btn inline-flex items-center justify-center rounded bg-green-500 px-4 py-3 text-sm font-semibold text-primary-2">
          Save Event
        </button>
      </form>

      <div class="rounded-xl border border-border bg-elevated p-5">
        <h2 class="fd-card-title mb-4">Scheduled Events</h2>

        @if ($events->isEmpty())
          <p class="text-sm text-text-muted">No automation events yet.</p>
        @else
          <x-ui.data-table
            :headers="['Name', 'Type', 'Status', 'Scheduled', 'Actions']"
            :paginator="$events"
          >
            @foreach ($events as $event)
              <tr class="bg-elevated">
                <td class="fd-table-cell p-2 align-middle">{{ $event->name }}</td>
                <td class="fd-table-cell p-2 align-middle">{{ $event->event_type }}</td>
                <td class="fd-table-cell p-2 align-middle">{{ $event->status }}</td>
                <td class="fd-table-cell p-2 align-middle">{{ $event->scheduled_at?->format('Y-m-d H:i') ?? '—' }}</td>
                <td class="fd-table-cell p-2 align-middle">
                  <form method="post" action="{{ route('automation.events.destroy', $event) }}" onsubmit="return confirm('Delete this event?')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="text-sm text-red-600 hover:underline">Delete</button>
                  </form>
                </td>
              </tr>
            @endforeach
          </x-ui.data-table>
        @endif
      </div>
    </section>
  </div>
</x-layouts.app>
