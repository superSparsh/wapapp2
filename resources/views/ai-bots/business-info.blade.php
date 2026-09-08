<x-layouts.app title="{{ $bot->name }} - Knowledge Base" active="ai-bots">
  <div class="flex flex-col bg-surface">
    <div class="flex flex-col gap-4 p-4">
      <div class="flex items-center gap-2"><a href="{{ route('ai-bots.show', $bot) }}" class="text-text-subtle hover:text-text-body">&larr; Back to {{ $bot->name }}</a></div>
      <div class="flex flex-col gap-1">
        <h1 class="text-2xl font-bold leading-[1.5] text-text-primary">{{ $bot->name }} — Knowledge Base</h1>
        <p class="max-w-[854px] text-sm font-normal leading-[1.4] text-text-subtle opacity-50">
          Add text, documents, or URLs to teach your bot about your business. Embeddings will be generated automatically.
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
          <h2 class="text-base font-semibold text-text-primary">Add Entry</h2>
          <form action="{{ route('ai-bots.business-info.store', $bot) }}" method="POST" enctype="multipart/form-data" class="flex flex-col gap-4">
            @csrf
            <div class="flex flex-col gap-1.5">
              <label for="title" class="text-sm font-semibold text-text-body">Title</label>
              <input type="text" id="title" name="title" value="{{ old('title') }}" required class="w-full rounded-lg border border-divider bg-surface px-4 py-3 text-sm text-text-body focus:border-green-500 focus:outline-none" placeholder="e.g. FAQ, Product Catalog">
            </div>
            <div class="flex flex-col gap-1.5">
              <label for="content_type" class="text-sm font-semibold text-text-body">Type</label>
              <select id="content_type" name="content_type" class="w-full rounded-lg border border-divider bg-surface px-4 py-3 text-sm text-text-body focus:border-green-500 focus:outline-none">
                <option value="text">Text</option>
                <option value="url">URL</option>
                <option value="document">Document (upload)</option>
              </select>
            </div>
            <div class="flex flex-col gap-1.5">
              <label for="content" class="text-sm font-semibold text-text-body">Content / URL</label>
              <textarea id="content" name="content" rows="4" class="w-full rounded-lg border border-divider bg-surface px-4 py-3 text-sm text-text-body focus:border-green-500 focus:outline-none" placeholder="Paste text or URL...">{{ old('content') }}</textarea>
            </div>
            <div class="flex flex-col gap-1.5">
              <label for="file" class="text-sm font-semibold text-text-body">Or Upload File</label>
              <input type="file" id="file" name="file" class="w-full rounded-lg border border-divider bg-surface px-4 py-2 text-sm text-text-body focus:border-green-500 focus:outline-none">
            </div>
            <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-lg bg-green-500 px-5 py-3 text-sm font-semibold text-primary-2 hover:opacity-90">Add Entry</button>
          </form>
        </div>

        <div class="flex flex-col gap-4 lg:col-span-2">
          <div class="overflow-hidden rounded-xl bg-elevated shadow-[0px_4px_6px_rgba(0,0,0,0.04)]">
            <div class="overflow-x-auto">
              <table class="w-full text-left">
                <thead>
                  <tr class="bg-elevated">
                    <th class="p-3 text-[13px] font-medium leading-[1.5] text-text-body">Title</th>
                    <th class="p-3 text-[13px] font-medium leading-[1.5] text-text-body">Type</th>
                    <th class="p-3 text-[13px] font-medium leading-[1.5] text-text-body">Embedding</th>
                    <th class="p-3 text-[13px] font-medium leading-[1.5] text-text-body">Added</th>
                    <th class="p-3 text-[13px] font-medium leading-[1.5] text-text-body">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  @forelse ($entries as $entry)
                    <tr class="border-t border-divider bg-elevated">
                      <td class="p-3 text-[13px] font-semibold text-text-primary">{{ $entry->title }}</td>
                      <td class="p-3 text-[13px] text-text-body">{{ $entry->content_type->value }}</td>
                      <td class="p-3">
                        @php $status = $entry->embedding_status->value; @endphp
                        @if ($status === 'completed')
                          <span class="inline-flex items-center justify-center rounded bg-[rgba(0,128,0,0.1)] px-2 py-1 text-[10px] font-medium leading-[1.2] text-[green]">Completed</span>
                        @elseif ($status === 'pending')
                          <span class="inline-flex items-center justify-center rounded bg-[rgba(255,165,0,0.15)] px-2 py-1 text-[10px] font-medium leading-[1.2] text-[orange]">Pending</span>
                        @else
                          <span class="inline-flex items-center justify-center rounded bg-[rgba(255,0,0,0.1)] px-2 py-1 text-[10px] font-medium leading-[1.2] text-[red]">Failed</span>
                        @endif
                      </td>
                      <td class="p-3 text-[13px] text-text-body">{{ $entry->created_at->diffForHumans() }}</td>
                      <td class="p-3">
                        <form action="{{ route('ai-bots.business-info.destroy', [$bot, $entry]) }}" method="POST" onsubmit="return confirm('Delete this entry?')">
                          @csrf @method('DELETE')
                          <button type="submit" class="text-xs text-red-500 hover:underline">Delete</button>
                        </form>
                      </td>
                    </tr>
                  @empty
                    <tr class="border-t border-divider bg-elevated">
                      <td colspan="5" class="p-8 text-center text-sm text-text-muted">No knowledge base entries yet.</td>
                    </tr>
                  @endforelse
                </tbody>
              </table>
            </div>
            @if ($entries->hasPages())
              <x-ui.table-pagination :paginator="$entries" />
            @endif
          </div>
        </div>
      </div>
    </div>
  </div>
</x-layouts.app>
