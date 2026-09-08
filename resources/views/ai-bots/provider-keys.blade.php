<x-layouts.app title="API Provider Keys - WapApp" active="ai-bots">
  <div class="flex flex-col bg-surface">
    <div class="flex flex-col gap-4 p-4">
      <div class="flex items-center gap-2"><a href="{{ route('ai-bots.index') }}" class="text-text-subtle hover:text-text-body">&larr; Back to AI Bots</a></div>
      <div class="flex flex-col gap-1">
        <h1 class="text-2xl font-bold leading-[1.5] text-text-primary">API Provider Keys</h1>
        <p class="max-w-[854px] text-sm font-normal leading-[1.4] text-text-subtle opacity-50">
          Manage your OpenAI, Gemini, and Azure OpenAI API keys. Keys are encrypted at rest.
        </p>
      </div>

      @if (session('status'))
        <div class="rounded-lg bg-green-50 px-4 py-3 text-sm font-medium text-green-700">{{ session('status') }}</div>
      @endif

      @if ($errors->any())
        <div class="rounded-lg bg-red-50 px-4 py-3 text-sm font-medium text-red-700">
          <ul class="list-inside list-disc">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
      @endif

      <div class="grid gap-4 lg:grid-cols-3">
        <div class="flex flex-col gap-4 rounded-xl bg-elevated p-5 lg:col-span-1">
          <h2 class="text-base font-semibold text-text-primary">Add New Key</h2>
          <form action="{{ route('ai-bots.provider-keys.store') }}" method="POST" class="flex flex-col gap-4">
            @csrf
            <div class="flex flex-col gap-1.5">
              <label for="provider" class="text-sm font-semibold text-text-body">Provider</label>
              <select id="provider" name="provider" required class="w-full rounded-lg border border-divider bg-surface px-4 py-3 text-sm text-text-body focus:border-green-500 focus:outline-none">
                <option value="openai">OpenAI</option>
                <option value="gemini">Google Gemini</option>
                <option value="azure">Azure OpenAI</option>
              </select>
            </div>
            <div class="flex flex-col gap-1.5">
              <label for="api_key" class="text-sm font-semibold text-text-body">API Key</label>
              <input type="password" id="api_key" name="api_key" required class="w-full rounded-lg border border-divider bg-surface px-4 py-3 text-sm text-text-body focus:border-green-500 focus:outline-none" placeholder="sk-...">
            </div>
            <div class="flex flex-col gap-1.5">
              <label for="chat_model" class="text-sm font-semibold text-text-body">Default Chat Model <span class="text-text-muted">(optional)</span></label>
              <input type="text" id="chat_model" name="chat_model" class="w-full rounded-lg border border-divider bg-surface px-4 py-3 text-sm text-text-body focus:border-green-500 focus:outline-none" placeholder="gpt-4o-mini">
            </div>
            <div class="flex flex-col gap-1.5">
              <label for="embedding_model" class="text-sm font-semibold text-text-body">Default Embedding Model <span class="text-text-muted">(optional)</span></label>
              <input type="text" id="embedding_model" name="embedding_model" class="w-full rounded-lg border border-divider bg-surface px-4 py-3 text-sm text-text-body focus:border-green-500 focus:outline-none" placeholder="text-embedding-3-small">
            </div>
            <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-lg bg-green-500 px-5 py-3 text-sm font-semibold text-primary-2 hover:opacity-90">Add Key</button>
          </form>
        </div>

        <div class="flex flex-col gap-4 lg:col-span-2">
          <div class="overflow-hidden rounded-xl bg-elevated shadow-[0px_4px_6px_rgba(0,0,0,0.04)]">
            <div class="overflow-x-auto">
              <table class="w-full text-left">
                <thead>
                  <tr class="bg-elevated">
                    <th class="p-3 text-[13px] font-medium leading-[1.5] text-text-body">Provider</th>
                    <th class="p-3 text-[13px] font-medium leading-[1.5] text-text-body">Chat Model</th>
                    <th class="p-3 text-[13px] font-medium leading-[1.5] text-text-body">Embedding</th>
                    <th class="p-3 text-[13px] font-medium leading-[1.5] text-text-body">Status</th>
                    <th class="p-3 text-[13px] font-medium leading-[1.5] text-text-body">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  @forelse ($keys as $key)
                    <tr class="border-t border-divider bg-elevated">
                      <td class="p-3 text-[13px] font-semibold text-text-primary">{{ \App\Enums\AiProvider::from($key->provider)->label() }}</td>
                      <td class="p-3 text-[13px] text-text-body">{{ $key->chat_model ?? '-' }}</td>
                      <td class="p-3 text-[13px] text-text-body">{{ $key->embedding_model ?? '-' }}</td>
                      <td class="p-3">
                        @if ($key->is_validated)
                          <span class="inline-flex items-center justify-center rounded bg-[rgba(0,128,0,0.1)] px-2 py-1 text-[10px] font-medium leading-[1.2] text-[green]">Validated</span>
                        @elseif ($key->is_active)
                          <span class="inline-flex items-center justify-center rounded bg-[rgba(0,0,255,0.08)] px-2 py-1 text-[10px] font-medium leading-[1.2] text-[blue]">Active</span>
                        @else
                          <span class="inline-flex items-center justify-center rounded bg-[rgba(0,0,0,0.1)] px-2 py-1 text-[10px] font-medium leading-[1.2] text-text-muted">Inactive</span>
                        @endif
                      </td>
                      <td class="p-3">
                        <div class="flex items-center gap-3">
                          <form action="{{ route('ai-bots.provider-keys.validate', $key) }}" method="POST">
                            @csrf
                            <button type="submit" class="text-xs text-green-500 hover:underline">Validate</button>
                          </form>
                          <form action="{{ route('ai-bots.provider-keys.destroy', $key) }}" method="POST" onsubmit="return confirm('Delete this key?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-xs text-red-500 hover:underline">Delete</button>
                          </form>
                        </div>
                      </td>
                    </tr>
                  @empty
                    <tr class="border-t border-divider bg-elevated">
                      <td colspan="5" class="p-8 text-center text-sm text-text-muted">No provider keys configured.</td>
                    </tr>
                  @endforelse
                </tbody>
              </table>
            </div>
            @if ($keys->hasPages())
              <x-ui.table-pagination :paginator="$keys" />
            @endif
          </div>
        </div>
      </div>
    </div>
  </div>
</x-layouts.app>
