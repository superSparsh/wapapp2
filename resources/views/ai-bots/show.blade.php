<x-layouts.app title="{{ $bot->name }} - AI Bot" active="ai-bots">
  <div class="flex flex-col bg-surface">
    <div class="flex flex-col gap-4 p-4">
      <div class="flex items-center gap-2"><a href="{{ route('ai-bots.index') }}" class="text-text-subtle hover:text-text-body">&larr; Back</a></div>
      <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <h1 class="text-2xl font-bold text-text-primary">{{ $bot->name }}</h1>
        <div class="flex gap-3">
          <a href="{{ route('ai-bots.edit', $bot) }}" class="rounded-lg bg-green-500 px-4 py-3 text-sm font-semibold text-primary-2">Edit</a>
          <a href="{{ route('ai-bots.business-info.index', $bot) }}" class="rounded-lg border border-green-500 bg-green-50 px-4 py-3 text-sm font-semibold text-green-500">Knowledge Base</a>
        </div>
      </div>
      @if (session('status'))
        <div class="rounded-lg bg-green-50 px-4 py-3 text-sm font-medium text-green-700">{{ session('status') }}</div>
      @endif
      <div class="grid gap-4 md:grid-cols-4">
        <div class="rounded-xl bg-elevated p-5"><p class="text-xs text-text-muted">Provider</p><p class="mt-1 text-lg font-bold text-text-primary">{{ $bot->provider->label() }}</p></div>
        <div class="rounded-xl bg-elevated p-5"><p class="text-xs text-text-muted">Model</p><p class="mt-1 text-lg font-bold text-text-primary">{{ $bot->chat_model }}</p></div>
        <div class="rounded-xl bg-elevated p-5"><p class="text-xs text-text-muted">Temperature</p><p class="mt-1 text-lg font-bold text-text-primary">{{ $bot->temperature }}</p></div>
        <div class="rounded-xl bg-elevated p-5"><p class="text-xs text-text-muted">Default</p><p class="mt-1 text-lg font-bold text-text-primary">{{ $bot->is_default ? 'Yes' : 'No' }}</p></div>
      </div>
      @if ($bot->system_prompt)
        <div class="rounded-xl bg-elevated p-5"><h2 class="text-base font-semibold text-text-primary">System Prompt</h2><pre class="mt-2 whitespace-pre-wrap text-sm text-text-body">{{ $bot->system_prompt }}</pre></div>
      @endif
      @if ($bot->businessInfoEntries->count() > 0)
        <div class="rounded-xl bg-elevated p-5">
          <h2 class="text-base font-semibold text-text-primary">Knowledge Base ({{ $bot->businessInfoEntries->count() }} entries)</h2>
          <ul class="mt-2 space-y-1">@foreach ($bot->businessInfoEntries as $entry)<li class="text-sm text-text-body">{{ $entry->title }} <span class="text-xs text-text-muted">({{ $entry->embedding_status->value }})</span></li>@endforeach</ul>
        </div>
      @endif
    </div>
  </div>
</x-layouts.app>
