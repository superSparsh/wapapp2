<x-layouts.app title="Trigger Template - WapApp" active="trigger-template.index">
  <div class="flex flex-col bg-surface">
    <div class="flex flex-col gap-4 p-4">
      @if (session('status'))
        <div class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
          {{ session('status') }}
        </div>
      @endif

      <div class="flex flex-col gap-1">
        <h1 class="text-2xl font-bold leading-[1.5] text-text-primary">Intent Response Setting</h1>
        <p class="max-w-[854px] text-sm font-normal leading-[1.4] text-text-subtle opacity-50">
          Map keywords to auto-response WhatsApp templates. Use trigger name
          <code class="font-mono text-xs">{{ config('trigger-template.any_message_trigger') }}</code>
          for the first message from a new contact.
        </p>
      </div>

      <div class="flex items-center justify-end">
        <a
          href="{{ route('trigger-template.index', ['modal' => 'add-trigger']) }}"
          class="fd-btn inline-flex items-center justify-center gap-2 rounded bg-green-500 px-4 py-3 text-sm font-semibold leading-[1.5] text-primary-2 transition-opacity hover:opacity-90"
        >
          <img src="{{ asset('images/team/add.svg') }}" alt="" class="size-5" width="20" height="20">
          Add New
        </a>
      </div>
    </div>

    <section class="bg-surface p-4 pt-0">
      <div class="overflow-hidden rounded-xl bg-elevated shadow-[0px_4px_6px_rgba(0,0,0,0.04)]">
        <div class="overflow-x-auto">
          <table class="w-full min-w-[800px] text-left">
            <thead>
              <tr class="bg-elevated">
                <th class="w-[54px] p-2 text-[13px] font-medium leading-[1.5] whitespace-nowrap text-text-body">SI. No</th>
                <th class="p-2 text-[13px] font-medium leading-[1.5] text-text-body">Trigger Name</th>
                <th class="p-2 text-[13px] font-medium leading-[1.5] text-text-body">Trigger Template</th>
                <th class="p-2 text-[13px] font-medium leading-[1.5] text-text-body">Mail List</th>
                <th class="p-2 text-[13px] font-medium leading-[1.5] text-text-body">Actions</th>
              </tr>
            </thead>
            <tbody>
              @forelse ($triggers as $trigger)
                <tr class="border-t border-divider bg-elevated">
                  <td class="w-[54px] p-2 text-[13px] font-normal leading-[1.5] text-text-body">{{ str_pad((string) $trigger['serial'], 2, '0', STR_PAD_LEFT) }}</td>
                  <td class="p-2 text-[13px] font-semibold leading-[1.5] text-text-subtle">{{ $trigger['variable_name'] }}</td>
                  <td class="p-2 text-[13px] font-normal leading-[1.5] text-text-body">{{ $trigger['template_name'] }}</td>
                  <td class="p-2 text-[13px] font-normal leading-[1.5] text-text-body">{{ $trigger['list_name'] }}</td>
                  <td class="p-2">
                    <form
                      method="post"
                      action="{{ $trigger['delete_url'] }}"
                      data-confirm="Delete this trigger?" data-confirm-title="Delete trigger" data-confirm-label="Delete"
                    >
                      @csrf
                      @method('DELETE')
                      <button type="submit" aria-label="Delete trigger">
                        <img src="{{ asset('images/team/trash.svg') }}" alt="" class="size-5" width="20" height="20">
                      </button>
                    </form>
                  </td>
                </tr>
              @empty
                <tr class="border-t border-divider bg-elevated">
                  <td colspan="5" class="p-6 text-center text-sm text-text-muted">
                    No triggers yet. Click <strong>Add New</strong> to create your first keyword trigger.
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

  <x-trigger-template.add-trigger-modal
    :open="$openModal || $errors->any()"
    :close-href="route('trigger-template.index')"
    :template-options="$templateOptions"
    :mail-list-options="$mailListOptions"
  />
</x-layouts.app>
