<x-layouts.app title="{{ $flow->name }} - Chatbot Details" active="automation.chatbot">
  <div class="flex flex-col bg-surface">
    <div class="flex flex-col gap-4 p-4">
      <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex min-w-0 flex-1 flex-col gap-1">
          <div class="flex items-center gap-2">
            <a href="{{ route('chatbot.index') }}" class="text-text-subtle hover:text-text-body">&larr; Back</a>
          </div>
          <h1 class="text-2xl font-bold leading-[1.5] text-text-primary">{{ $flow->name }}</h1>
          <p class="text-sm font-normal leading-[1.4] text-text-subtle opacity-50">
            UUID: {{ $flow->uuid }} &middot; Created {{ $flow->created_at?->diffForHumans() }}
          </p>
        </div>

        <div class="flex flex-wrap items-center gap-3">
          <a
            href="{{ route('chatbot.edit', $flow) }}"
            class="inline-flex items-center justify-center gap-2 rounded-lg bg-green-500 px-4 py-3 text-sm font-semibold leading-[1.5] text-primary-2 transition-opacity hover:opacity-90"
          >
            <img src="{{ asset('images/automation/edit.svg') }}" alt="" class="size-4" width="16" height="16">
            Open Builder
          </a>
          <form action="{{ route('chatbot.toggle', $flow) }}" method="POST">
            @csrf
            @method('PATCH')
            <button
              type="submit"
              class="inline-flex items-center justify-center gap-2 rounded-lg border border-solid border-green-500 bg-green-50 px-4 py-3 text-sm font-semibold leading-[1.5] text-green-500 transition-colors hover:bg-green-50"
            >
              {{ $flow->isActive() ? 'Deactivate' : 'Activate' }}
            </button>
          </form>
        </div>
      </div>

      @if (session('status'))
        <div class="rounded-lg bg-green-50 px-4 py-3 text-sm font-medium text-green-700">
          {{ session('status') }}
        </div>
      @endif

      <div class="grid gap-4 md:grid-cols-3">
        <div class="rounded-xl bg-elevated p-5 shadow-[0px_4px_6px_rgba(0,0,0,0.04)]">
          <p class="text-xs font-medium leading-[1.5] text-text-muted">Status</p>
          <p class="mt-1 text-lg font-bold leading-[1.5] text-text-primary">
            @if ($flow->status->value === 'active')
              <span class="text-green-600">Active</span>
            @elseif ($flow->status->value === 'inactive')
              <span class="text-text-muted">Inactive</span>
            @else
              <span class="text-yellow-600">Draft</span>
            @endif
          </p>
        </div>
        <div class="rounded-xl bg-elevated p-5 shadow-[0px_4px_6px_rgba(0,0,0,0.04)]">
          <p class="text-xs font-medium leading-[1.5] text-text-muted">Nodes</p>
          <p class="mt-1 text-lg font-bold leading-[1.5] text-text-primary">{{ $flow->nodeCount() }}</p>
        </div>
        <div class="rounded-xl bg-elevated p-5 shadow-[0px_4px_6px_rgba(0,0,0,0.04)]">
          <p class="text-xs font-medium leading-[1.5] text-text-muted">Published</p>
          <p class="mt-1 text-lg font-bold leading-[1.5] text-text-primary">
            {{ $flow->published_at?->format('M d, Y H:i') ?? 'Not published' }}
          </p>
        </div>
      </div>

      <div class="rounded-xl bg-elevated p-5 shadow-[0px_4px_6px_rgba(0,0,0,0.04)]">
        <h2 class="text-base font-semibold leading-[1.5] text-text-primary">Flow Data</h2>
        @if ($flow->hasFlowData())
          <p class="mt-1 text-sm text-text-subtle">
            This flow contains {{ $flow->nodeCount() }} nodes and has been configured in the builder.
          </p>
          <pre class="mt-3 max-h-[300px] overflow-auto rounded-lg bg-surface p-4 text-xs text-text-body">{{ json_encode($flow->exported_data, JSON_PRETTY_PRINT) }}</pre>
        @else
          <p class="mt-1 text-sm text-text-muted">
            No flow data configured yet. <a href="{{ route('chatbot.edit', $flow) }}" class="text-green-500 underline">Open the builder</a> to design the flow.
          </p>
        @endif
      </div>
    </div>
  </div>
</x-layouts.app>
