<x-layouts.app title="Variables - WapApp" active="templates.variables">
  <div class="flex flex-col bg-surface">
    <div class="flex flex-col gap-4 p-4">
      @if (session('status'))
        <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
          {{ session('status') }}
        </div>
      @endif

      <div class="flex flex-col gap-1">
        <h1 class="fd-page-title">Variables</h1>
        <p class="fd-page-note max-w-[854px]">
          Create reusable placeholders for WhatsApp templates and chatbot flows. In templates use <code class="font-mono text-xs">$(variable_name)</code>. Chatbot flows can also use <code class="font-mono text-xs">@verbatim{{variable_name}}@endverbatim</code>.
        </p>
      </div>

      <x-ui.listing-toolbar
        :action="route('templates.variables')"
        search-name="q"
        :search-value="$search"
        search-placeholder="Search variables"
        :current-sort="$currentSort ?? 'id'"
        :current-direction="$currentDirection ?? 'desc'"
        :sort-options="[
          ['value' => 'id', 'label' => 'Newest first', 'direction' => 'desc'],
          ['value' => 'id', 'label' => 'Oldest first', 'direction' => 'asc'],
          ['value' => 'created_at', 'label' => 'Created date', 'direction' => 'desc'],
          ['value' => 'name', 'label' => 'Name A–Z', 'direction' => 'asc'],
          ['value' => 'name', 'label' => 'Name Z–A', 'direction' => 'desc'],
        ]"
      >
        <x-slot:actions>
          <a
            href="{{ route('templates.variables', array_filter(['q' => $search !== '' ? $search : null, 'sort' => $currentSort ?? null, 'direction' => $currentDirection ?? null])) }}"
            class="fd-btn inline-flex shrink-0 items-center justify-center gap-3 rounded border border-border-light bg-elevated px-4 py-3 text-green-500"
          >
            <img src="{{ asset('images/templates/refresh-2.svg') }}" alt="" class="size-4" width="16" height="16">
            Refresh
          </a>
          <a
            href="{{ route('templates.variables.create') }}"
            class="fd-btn inline-flex shrink-0 items-center justify-center gap-2 rounded bg-green-500 px-4 py-3 text-primary-2 transition-opacity hover:opacity-90"
          >
            <img src="{{ asset('images/icons/add-linear.svg') }}" alt="" class="size-5" width="20" height="20">
            Add New
          </a>
        </x-slot:actions>
      </x-ui.listing-toolbar>
    </div>

    <section class="bg-surface p-4 pt-0">
      <div class="overflow-hidden rounded-xl bg-elevated shadow-[0px_4px_6px_rgba(0,0,0,0.04)]">
        <div class="overflow-x-auto">
          <table class="w-full min-w-[1100px] text-left">
            <thead>
              <tr class="bg-elevated">
                <th class="fd-table-head w-[54px] p-2">SI. No</th>
                <th class="fd-table-head w-[320px] p-2">Variable Name</th>
                <th class="fd-table-head p-2">Created On / Time</th>
                <th class="fd-table-head p-2 text-center">Variable Type</th>
                <th class="fd-table-head p-2 text-center">Type</th>
                <th class="fd-table-head p-2 text-center">Actions</th>
              </tr>
            </thead>
            <tbody>
              @forelse ($variables as $variable)
                <tr class="border-t border-divider bg-elevated">
                  <td class="fd-table-cell p-2">{{ $variable['serial'] }}</td>
                  <td class="w-[320px] p-2">
                    <p class="fd-table-name">{{ $variable['name'] }}</p>
                  </td>
                  <td class="fd-table-cell p-2">{{ $variable['created_at'] }}</td>
                  <td class="fd-table-cell p-2 text-center">{{ $variable['data_type_label'] }}</td>
                  <td class="fd-table-cell p-2 text-center">{{ $variable['type_label'] }}</td>
                  <td class="p-2">
                    <div class="flex items-start justify-center gap-2">
                      <x-ui.table-actions
                        :actions="['edit']"
                        :links="['edit' => $variable['edit_url']]"
                      />
                      <form method="post" action="{{ $variable['delete_url'] }}" data-confirm="Delete this variable?" data-confirm-title="Delete variable" data-confirm-label="Delete">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="flex size-5 items-center justify-center" aria-label="Delete">
                          <img src="{{ asset('images/icons/table/trash.svg') }}" alt="" class="size-5" width="20" height="20">
                        </button>
                      </form>
                    </div>
                  </td>
                </tr>
              @empty
                <tr class="border-t border-divider bg-elevated">
                  <td colspan="6" class="p-8 text-center text-sm text-text-muted">
                    No variables found. Create your first variable to get started.
                  </td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>
        <x-ui.table-pagination :paginator="$paginator" />
      </div>
    </section>
  </div>
</x-layouts.app>
