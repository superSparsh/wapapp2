<x-layouts.app title="Subscriber Detail - WapApp" active="audience.subscribers">
  <div class="flex flex-col">
    <x-audience.list-header :title="$contact->mailList?->name ?? 'Subscriber Detail'" :subscribers="(string) ($contact->mailList?->totalContactsCount() ?? 0)" />
    <x-audience.sub-nav />

    <div class="p-4">
      <x-ui.page-header :title="$contact->name ?? 'Unknown'" :subtitle="$contact->phone">
        <x-slot:actions>
          <x-ui.link-button href="{{ route('audience.subscribers') }}" variant="outline" size="sm">Back</x-ui.link-button>
          <span @class([
            'fd-status-chip inline-flex items-center rounded px-2 py-1',
            'bg-[rgba(0,128,0,0.1)] text-green-600' => $contact->status?->value === 'subscribed',
            'bg-black/10 text-[#767676]' => $contact->status?->value === 'unsubscribed',
            'bg-red-50 text-red-600' => $contact->status?->value === 'blacklisted',
          ])>{{ $contact->status?->label() ?? 'Unknown' }}</span>
        </x-slot:actions>
      </x-ui.page-header>
    </div>

    <section class="grid gap-4 p-4 pt-0 lg:grid-cols-3">
      <div class="rounded-lg border border-[0.5px] border-border-light bg-elevated p-4 lg:col-span-1">
        <h3 class="fd-section-title mb-4">Contact Info</h3>
        <dl class="space-y-4">
          <div>
            <dt class="fd-table-cell text-text-body/70">Phone</dt>
            <dd class="mt-1 text-[13px] font-semibold leading-[1.5] text-text-body">{{ $contact->phone }}</dd>
          </div>
          @if($contact->email)
          <div>
            <dt class="fd-table-cell text-text-body/70">Email</dt>
            <dd class="mt-1 text-[13px] font-semibold leading-[1.5] text-text-body">{{ $contact->email }}</dd>
          </div>
          @endif
          <div>
            <dt class="fd-table-cell text-text-body/70">Subscribed</dt>
            <dd class="mt-1 text-[13px] font-semibold leading-[1.5] text-text-body">{{ $contact->opted_in_at?->format('Y-m-d h:i A') ?? $contact->created_at?->format('Y-m-d h:i A') ?? '-' }}</dd>
          </div>
          <div>
            <dt class="fd-table-cell text-text-body/70">Opt-in Status</dt>
            <dd class="mt-1 text-[13px] font-semibold leading-[1.5] text-text-body">{{ $contact->opt_in_status?->label() ?? 'Unknown' }}</dd>
          </div>
          @if($contact->mailList)
          <div>
            <dt class="fd-table-cell text-text-body/70">List</dt>
            <dd class="mt-1 text-[13px] font-semibold leading-[1.5] text-text-body">{{ $contact->mailList->name }}</dd>
          </div>
          @endif
          @if($contact->tags->isNotEmpty())
          <div>
            <dt class="fd-table-cell text-text-body/70">Tags</dt>
            <dd class="mt-1 flex flex-wrap gap-2">
              @foreach($contact->tags as $tag)
                <span class="inline-flex items-center rounded-lg border border-green-500 bg-elevated px-2 py-1 text-xs font-medium text-green-500">{{ $tag->name }}</span>
              @endforeach
            </dd>
          </div>
          @endif
        </dl>
      </div>

      <div class="rounded-lg border border-[0.5px] border-border-light bg-elevated p-4 lg:col-span-2">
        <h3 class="fd-section-title mb-4">Activity</h3>
        <div class="space-y-0">
          <div class="flex items-center justify-between border-b border-divider py-3">
            <span class="fd-table-cell">Contact created</span>
            <span class="fd-table-cell text-text-body/70">{{ $contact->created_at?->format('Y-m-d h:i A') ?? '-' }}</span>
          </div>
          @if($contact->opted_in_at)
          <div class="flex items-center justify-between border-b border-divider py-3">
            <span class="fd-table-cell">Opted in</span>
            <span class="fd-table-cell text-text-body/70">{{ $contact->opted_in_at->format('Y-m-d h:i A') }}</span>
          </div>
          @endif
          @if($contact->opted_out_at)
          <div class="flex items-center justify-between border-b border-divider py-3">
            <span class="fd-table-cell">Opted out</span>
            <span class="fd-table-cell text-text-body/70">{{ $contact->opted_out_at->format('Y-m-d h:i A') }}</span>
          </div>
          @endif
          <div class="flex items-center justify-between border-b border-divider py-3">
            <span class="fd-table-cell">Last updated</span>
            <span class="fd-table-cell text-text-body/70">{{ $contact->updated_at?->format('Y-m-d h:i A') ?? '-' }}</span>
          </div>
        </div>
      </div>
    </section>
  </div>
</x-layouts.app>
