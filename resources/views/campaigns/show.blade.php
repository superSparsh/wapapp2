<x-layouts.app title="{{ $campaign->name }} - WapApp" active="campaigns.index">
  <x-campaigns.campaign-header :campaign="$campaign" activeTab="overview">
    <div class="flex flex-col gap-4 bg-surface px-4 pb-4">
      <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
        <div class="rounded-lg bg-elevated p-4">
          <p class="text-xs font-medium text-text-subtle">Total Recipients</p>
          <p class="mt-1 text-2xl font-bold text-text-primary">{{ number_format($metrics['total'] ?? $campaign->total_recipients) }}</p>
        </div>
        <div class="rounded-lg bg-elevated p-4">
          <p class="text-xs font-medium text-text-subtle">Delivered</p>
          <p class="mt-1 text-2xl font-bold text-green-500">{{ number_format($metrics['delivered'] ?? $campaign->total_delivered) }}</p>
        </div>
        <div class="rounded-lg bg-elevated p-4">
          <p class="text-xs font-medium text-text-subtle">Failed</p>
          <p class="mt-1 text-2xl font-bold text-red-500">{{ number_format($metrics['failed'] ?? $campaign->total_failed) }}</p>
        </div>
        <div class="rounded-lg bg-elevated p-4">
          <p class="text-xs font-medium text-text-subtle">Read</p>
          <p class="mt-1 text-2xl font-bold text-blue-500">{{ number_format($metrics['read'] ?? $campaign->total_read) }}</p>
        </div>
        <div class="rounded-lg bg-elevated p-4">
          <p class="text-xs font-medium text-text-subtle">Response</p>
          <p class="mt-1 text-2xl font-bold text-purple-500">{{ number_format($metrics['response'] ?? $campaign->total_response) }}</p>
        </div>
      </div>

      <div class="flex flex-col gap-3 rounded-lg bg-elevated p-4">
        <h3 class="text-sm font-semibold text-text-primary">Campaign Details</h3>
        <div class="grid gap-3 sm:grid-cols-2">
          <div>
            <p class="text-xs text-text-subtle">Audience</p>
            <p class="text-sm font-medium text-text-primary">{{ $campaign->audience?->name ?? 'Not set' }}</p>
          </div>
          <div>
            <p class="text-xs text-text-subtle">WhatsApp Line</p>
            <p class="text-sm font-medium text-text-primary">{{ $campaign->whatsappLine?->displayPhone() ?? 'Not set' }}</p>
          </div>
          <div>
            <p class="text-xs text-text-subtle">Template</p>
            <p class="text-sm font-medium text-text-primary">{{ $campaign->template?->name ?? 'Not set' }}</p>
          </div>
          <div>
            <p class="text-xs text-text-subtle">Scheduled At</p>
            <p class="text-sm font-medium text-text-primary">{{ $campaign->scheduled_at?->format('d M Y h:i A') ?? 'Not scheduled' }}</p>
          </div>
          <div>
            <p class="text-xs text-text-subtle">Completion Rate</p>
            <p class="text-sm font-medium text-text-primary">{{ $campaign->completionRate() }}</p>
          </div>
        </div>
      </div>

      <div class="flex flex-wrap gap-3">
        <a href="{{ route('campaigns.statistics', $campaign) }}" class="fd-btn flex items-center justify-center gap-2 rounded bg-green-500 px-4 py-3 text-xs font-semibold text-primary-2 transition-opacity hover:opacity-90">
          View Statistics
        </a>
        <button type="button" data-open-modal="create-delivered-list" class="fd-btn flex items-center justify-center rounded border border-green-500 bg-elevated px-4 py-3 text-xs font-semibold text-green-500 transition-colors hover:bg-surface">
          Create list from delivered
        </button>
        @if ($campaign->canBeEdited())
          <a href="{{ route('campaigns.edit', $campaign) }}" class="fd-btn flex items-center justify-center rounded border border-green-500 bg-elevated px-4 py-3 text-xs font-semibold text-green-500 transition-colors hover:bg-surface">
            Edit Campaign
          </a>
        @endif
        <form method="POST" action="{{ route('campaigns.destroy', $campaign) }}" data-confirm="Delete this campaign? This action cannot be undone." data-confirm-title="Delete campaign" data-confirm-label="Delete">
          @csrf
          @method('DELETE')
          <button type="submit" class="fd-btn flex items-center justify-center rounded bg-[red] px-4 py-3" aria-label="Delete campaign">
            <img src="{{ asset('images/automation/trash-white.svg') }}" alt="" class="size-5" width="20" height="20">
          </button>
        </form>
      </div>
    </div>
  </x-campaigns.campaign-header>

  <div id="modal-create-delivered-list" data-modal="create-delivered-list" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/60 p-4">
    <div class="w-full max-w-md rounded-[20px] bg-elevated p-5 shadow-lg">
      <div class="mb-4 flex items-start justify-between">
        <h2 class="text-xl font-bold text-text-primary">Create list from delivered</h2>
        <button type="button" data-modal-close class="text-text-muted">&times;</button>
      </div>
      <form method="POST" action="{{ route('campaigns.create-delivered-list', $campaign) }}" class="space-y-4">
        @csrf
        <div>
          <label class="mb-1 block text-sm font-semibold">New list name</label>
          <input name="new_list_name" type="text" required value="{{ $campaign->name }} - Delivered" class="w-full rounded-xl border border-border bg-elevated px-3 py-3 text-sm">
        </div>
        <p class="text-xs text-text-muted">Contacts with Delivered / Read / Response status will be copied into the new list.</p>
        <div class="flex justify-end gap-2">
          <button type="button" data-modal-close class="rounded border border-green-500 px-4 py-2 text-sm font-semibold text-green-500">Cancel</button>
          <button type="submit" class="rounded bg-green-500 px-4 py-2 text-sm font-semibold text-primary-2">Create list</button>
        </div>
      </form>
    </div>
  </div>

  @push('scripts')
  <script src="{{ asset('js/campaigns/campaigns.js') }}" defer></script>
  @endpush
</x-layouts.app>
