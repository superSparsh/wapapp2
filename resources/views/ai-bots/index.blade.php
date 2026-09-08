<x-layouts.app title="AI Bots - WapApp" active="ai-bots">
  <div class="flex flex-col bg-surface">
    <div class="flex flex-col gap-4 p-4">
      <div class="flex flex-col gap-1">
        <h1 class="text-2xl font-bold leading-[1.5] text-text-primary">AI Bots</h1>
        <p class="max-w-[854px] text-sm font-normal leading-[1.4] text-text-subtle opacity-50">
          Manage your AI bots, configure providers, and set up knowledge bases for intelligent conversations.
        </p>
      </div>

      @if (session('status'))
        <div class="rounded-lg bg-green-50 px-4 py-3 text-sm font-medium text-green-700">{{ session('status') }}</div>
      @endif

      <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <form action="{{ route('ai-bots.index') }}" method="GET" class="flex w-full max-w-[550px] items-center gap-3 overflow-hidden rounded-lg bg-elevated p-3">
          <img src="{{ asset('images/automation/search.svg') }}" alt="" class="size-5 shrink-0" width="20" height="20">
          <input type="search" name="search" value="{{ request('search') }}" placeholder="Search bots" class="min-w-0 flex-1 bg-transparent text-sm font-medium leading-[1.4] text-text-body/60 placeholder:text-text-body/60 placeholder:opacity-60 focus:outline-none">
        </form>
        <div class="flex flex-wrap items-center gap-6">
          <a href="{{ route('ai-bots.provider-keys.index') }}" class="inline-flex items-center justify-center gap-3 rounded-lg border border-solid border-green-500 bg-green-50 px-4 py-3 text-sm font-semibold leading-[1.5] text-green-500">API Keys</a>
          <a href="{{ route('ai-bots.create') }}" class="inline-flex items-center justify-center gap-2 rounded bg-green-500 px-4 py-3 text-sm font-semibold leading-[1.5] text-primary-2 transition-opacity hover:opacity-90">
            <img src="{{ asset('images/automation/add.svg') }}" alt="" class="size-5" width="20" height="20">
            Create Bot
          </a>
        </div>
      </div>
    </div>

    <section class="bg-surface p-4 pt-0">
      <div class="overflow-hidden rounded-xl bg-elevated shadow-[0px_4px_6px_rgba(0,0,0,0.04)]">
        <div class="overflow-x-auto">
          <table class="w-full min-w-[800px] text-left">
            <thead>
              <tr class="bg-elevated">
                <th class="p-2 text-[13px] font-medium leading-[1.5] text-text-body">Name</th>
                <th class="p-2 text-[13px] font-medium leading-[1.5] text-text-body">Provider</th>
                <th class="p-2 text-[13px] font-medium leading-[1.5] text-text-body">Model</th>
                <th class="p-2 text-[13px] font-medium leading-[1.5] text-text-body">Status</th>
                <th class="p-2 text-[13px] font-medium leading-[1.5] text-text-body">Default</th>
                <th class="p-2 text-[13px] font-medium leading-[1.5] text-text-body">Actions</th>
              </tr>
            </thead>
            <tbody>
              @forelse ($bots as $bot)
                <tr class="border-t border-divider bg-elevated">
                  <td class="p-2">
                    <a href="{{ route('ai-bots.show', $bot) }}" class="text-[13px] font-semibold leading-[1.5] text-text-subtle hover:text-green-500">{{ $bot->name }}</a>
                  </td>
                  <td class="p-2 text-[13px] text-text-body">{{ $bot->provider->label() }}</td>
                  <td class="p-2 text-[13px] text-text-body">{{ $bot->chat_model }}</td>
                  <td class="p-2">
                    @if ($bot->isActive())
                      <span class="inline-flex items-center justify-center rounded bg-[rgba(0,128,0,0.1)] px-2 py-1 text-[10px] font-medium leading-[1.2] text-[green]">Active</span>
                    @else
                      <span class="inline-flex items-center justify-center rounded bg-[rgba(0,0,0,0.1)] px-2 py-1 text-[10px] font-medium leading-[1.2] text-text-muted">Inactive</span>
                    @endif
                  </td>
                  <td class="p-2">
                    @if ($bot->is_default)
                      <span class="text-xs font-semibold text-green-500">Default</span>
                    @else
                      <form action="{{ route('ai-bots.toggle-default', $bot) }}" method="POST" class="inline">
                        @csrf @method('PATCH')
                        <button type="submit" class="text-xs text-text-muted hover:text-green-500">Set default</button>
                      </form>
                    @endif
                  </td>
                  <td class="p-2">
                    <div class="flex items-center gap-4">
                      <a href="{{ route('ai-bots.edit', $bot) }}" aria-label="Edit" title="Edit">
                        <img src="{{ asset('images/automation/edit.svg') }}" alt="" class="size-5" width="20" height="20">
                      </a>
                      <a href="{{ route('ai-bots.business-info.index', $bot) }}" aria-label="Knowledge" title="Knowledge" class="text-xs text-text-body hover:underline">Knowledge</a>
                      <a href="{{ route('ai-bots.usage', $bot) }}" aria-label="Usage" title="Usage" class="text-xs text-text-body hover:underline">Usage</a>
                      <form action="{{ route('ai-bots.destroy', $bot) }}" method="POST" data-confirm="Delete this bot?" data-confirm-title="Delete bot" data-confirm-label="Delete">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="flex size-5 items-center justify-center" aria-label="Delete" title="Delete">
                          <img src="{{ asset('images/automation/trash.svg') }}" alt="" class="size-5" width="20" height="20">
                        </button>
                      </form>
                    </div>
                  </td>
                </tr>
              @empty
                <tr class="border-t border-divider bg-elevated">
                  <td colspan="6" class="p-8 text-center text-sm text-text-muted">
                    No AI bots found. <a href="{{ route('ai-bots.create') }}" class="text-green-500 underline">Create one now</a>.
                  </td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>
        @if ($bots->hasPages())
          <x-ui.table-pagination :paginator="$bots" />
        @endif
      </div>
    </section>
  </div>
</x-layouts.app>
