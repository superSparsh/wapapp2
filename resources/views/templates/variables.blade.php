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
          Create reusable placeholders for WhatsApp templates and chatbot flows. Use syntax like <code class="font-mono text-xs">@verbatim{{variable_name}}@endverbatim</code> in your template body.
        </p>
      </div>

      <form method="get" action="{{ route('templates.variables') }}" class="flex flex-wrap items-center justify-between gap-4">
        <label class="flex w-full max-w-[550px] items-center gap-3 overflow-hidden rounded-lg bg-elevated p-3">
          <img src="{{ asset('images/templates/search.svg') }}" alt="" class="size-5 shrink-0" width="20" height="20">
          <input
            type="search"
            name="q"
            value="{{ $search }}"
            placeholder="Search variables"
            class="min-w-0 flex-1 bg-transparent text-sm font-medium leading-[1.4] text-text-body placeholder:text-text-body/60 focus:outline-none"
          >
        </label>

        <div class="flex items-center gap-2">
          <x-templates.refresh-button />
          <a
            href="{{ route('templates.variables.create') }}"
            class="fd-btn inline-flex shrink-0 items-center justify-center gap-2 rounded bg-green-500 px-4 py-3 text-primary-2 transition-opacity hover:opacity-90"
          >
            <img src="{{ asset('images/icons/add-linear.svg') }}" alt="" class="size-5" width="20" height="20">
            Add New
          </a>
        </div>
      </form>
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
