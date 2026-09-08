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

      <div class="flex gap-3">
        <a href="{{ route('campaigns.statistics', $campaign) }}" class="fd-btn flex items-center justify-center gap-2 rounded bg-green-500 px-4 py-3 text-xs font-semibold text-primary-2 transition-opacity hover:opacity-90">
          View Statistics
        </a>
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
  @push('scripts')
  <script src="{{ asset('js/campaigns/campaigns.js') }}" defer></script>
  @endpush
</x-layouts.app>
