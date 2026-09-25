<x-layouts.app title="Subscriber Detail - WapApp" active="audience.subscribers">
  @php
    $listUuid = $mailListId ?? $contact->mailList?->uuid;
  @endphp
  <div class="flex flex-col">
    <x-audience.list-header :title="$contact->mailList?->name ?? 'Subscriber Detail'" :subscribers="(string) ($contact->mailList?->totalContactsCount() ?? 0)" />
    <x-audience.sub-nav active="audience.subscribers" :list-id="$listUuid" />

    <div class="p-4">
      <x-ui.page-header :title="$contact->name ?? 'Unknown'" :subtitle="$contact->phone">
        <x-slot:actions>
          <x-ui.link-button href="{{ $listUuid ? route('audience.subscribers', ['list' => $listUuid]) : route('audience.index') }}" variant="outline" size="sm">Back to list</x-ui.link-button>
          <span @class([
            'fd-status-chip inline-flex items-center rounded px-2 py-1',
            'bg-[rgba(0,128,0,0.1)] text-green-600' => $contact->status?->value === 'subscribed',
            'bg-black/10 text-[#767676]' => $contact->status?->value === 'unsubscribed',
            'bg-red-50 text-red-600' => $contact->status?->value === 'blacklisted',
          ])>{{ $contact->status?->label() ?? 'Unknown' }}</span>
        </x-slot:actions>
      </x-ui.page-header>
    </div>

    @if (session('status'))
      <div class="px-4">
        <p class="rounded-lg bg-green-50 px-4 py-2 text-sm text-green-700">{{ session('status') }}</p>
      </div>
    @endif

    <section class="grid gap-4 p-4 pt-0 lg:grid-cols-3">
      <div class="rounded-lg border border-[0.5px] border-border-light bg-elevated p-4 lg:col-span-1">
        <h3 class="fd-section-title mb-4">Edit contact</h3>
        <form method="POST" action="{{ route('audience.subscribers.update', $contact) }}" class="space-y-4">
          @csrf
          @method('PUT')
          @if ($listUuid)
            <input type="hidden" name="list" value="{{ $listUuid }}">
          @endif
          <div>
            <label class="mb-1 block text-sm font-semibold text-text-primary">Phone</label>
            <input name="phone" type="text" value="{{ old('phone', $contact->phone) }}" required class="w-full rounded-xl border border-border bg-elevated px-3 py-3 text-sm">
            @error('phone')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
          </div>
          <div>
            <label class="mb-1 block text-sm font-semibold text-text-primary">Name</label>
            <input name="name" type="text" value="{{ old('name', $contact->name) }}" class="w-full rounded-xl border border-border bg-elevated px-3 py-3 text-sm">
          </div>
          <div>
            <label class="mb-1 block text-sm font-semibold text-text-primary">Email</label>
            <input name="email" type="email" value="{{ old('email', $contact->email) }}" class="w-full rounded-xl border border-border bg-elevated px-3 py-3 text-sm">
          </div>
          <div>
            <label for="detail_country_code" class="mb-1 block text-sm font-semibold text-text-primary">Country code</label>
            <x-ui.country-code-select
              id="detail_country_code"
              name="country_code"
              :selected="old('country_code', $contact->country_code)"
              class="rounded-xl px-3 py-3"
            />
          </div>
          <div>
            <label class="mb-1 block text-sm font-semibold text-text-primary">Tags (comma separated)</label>
            <input name="tags_raw" type="text" value="{{ old('tags_raw', $contact->tags->pluck('name')->implode(', ')) }}" class="w-full rounded-xl border border-border bg-elevated px-3 py-3 text-sm" placeholder="vip, lead">
          </div>
          <label class="flex items-center gap-2 text-sm font-medium text-text-body">
            <input type="hidden" name="send_opt_in_message" value="no">
            <input
              type="checkbox"
              name="send_opt_in_message"
              value="yes"
              class="size-4 rounded border-border"
              @checked(old('send_opt_in_message', $contact->send_opt_in_message) === 'yes')
            >
            Send WhatsApp opt-in message
          </label>
          @if ($contact->send_opt_in_message === 'yes' && filled($contact->opt_in_message_delivery_status))
            <p class="text-xs text-text-subtle">
              Last opt-in status:
              <span class="font-medium text-text-body">{{ $contact->opt_in_message_delivery_status }}</span>
              @if ($contact->opt_in_message_sent_at)
                · {{ $contact->opt_in_message_sent_at->format('Y-m-d h:i A') }}
              @endif
            </p>
          @endif
          <button type="submit" class="fd-btn rounded bg-green-500 px-4 py-3 text-sm font-semibold text-primary-2">Save changes</button>
        </form>
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
            <span class="fd-table-cell">List</span>
            <span class="fd-table-cell text-text-body/70">{{ $contact->mailList?->name ?? '—' }}</span>
          </div>
          <div class="flex items-center justify-between border-b border-divider py-3">
            <span class="fd-table-cell">Last updated</span>
            <span class="fd-table-cell text-text-body/70">{{ $contact->updated_at?->format('Y-m-d h:i A') ?? '-' }}</span>
          </div>
        </div>
      </div>
    </section>
  </div>
</x-layouts.app>
