<x-layouts.app title="Roles & Access - WapApp" active="my-team.index">
  @php
    $permissionDescriptions = [
      'inbox_read' => 'Conversations, replies, and inbox tools',
      'template_read' => 'WhatsApp templates, variables, and free messages',
      'audience_read' => 'Lists, contacts, segments, and blacklist',
      'campaign_read' => 'Broadcast campaigns and delivery tools',
      'chatbot_read' => 'Chatbot flows and automation builder',
      'drip_read' => 'Drip marketing sequences and audiences',
      'whatsapp_flow_read' => 'WhatsApp Flow forms and submissions',
      'form_builder_read' => 'Website / embeddable form builder',
      'commerce_read' => 'Catalogs, orders, and payment settings',
      'integration_read' => 'Shopify, Calendly, Google Calendar, and more',
    ];
  @endphp

  <div class="flex flex-col bg-surface">
    <div class="flex flex-col gap-1 p-4">
      <h1 class="text-2xl font-bold leading-[1.5] text-text-primary">Roles &amp; Access</h1>
      <p class="max-w-[854px] text-sm font-normal leading-[1.4] text-text-subtle opacity-50">
        Choose which modules {{ $member->displayName() }} can use. Turning a module on grants full access for that area.
      </p>
    </div>

    @if (session('status'))
      <div class="mx-4 rounded-lg border border-green-500/30 bg-green-50 px-4 py-3 text-sm text-green-700">
        {{ session('status') }}
      </div>
    @endif

    <section class="flex flex-col gap-4 bg-surface p-4 pt-0">
      <form class="flex flex-col gap-4 rounded-lg bg-elevated p-3" action="{{ $formAction ?? route('my-team.roles.update', $member) }}" method="post">
        @csrf
        @method('PUT')

        <div class="flex flex-col gap-1 px-1 pt-1">
          <h2 class="text-xl font-semibold leading-[1.4] text-text-primary">Module permissions</h2>
          <p class="text-sm text-text-muted">Only enabled modules appear in their sidebar after they sign in.</p>
        </div>

        <div class="w-full max-w-[760px] overflow-hidden rounded-xl border border-green-50 bg-elevated shadow-[0px_4px_6px_rgba(0,0,0,0.04)]" data-team-permissions>
          @foreach ($permissionLabels as $key => $label)
            @php $enabled = ! empty($permissions[$key]); @endphp
            <div
              @class([
                'grid grid-cols-1 items-center gap-3 px-4 py-3 sm:grid-cols-[minmax(0,1fr)_auto] sm:gap-4',
                'border-t border-divider' => ! $loop->first,
              ])
              data-team-permission-row
            >
              <div class="min-w-0">
                <p class="text-[13px] font-semibold leading-[1.5] text-text-subtle">{{ $label }}</p>
                <p class="mt-0.5 text-[12px] leading-[1.4] text-text-muted">
                  {{ $permissionDescriptions[$key] ?? 'Allow access to this module' }}
                </p>
              </div>

              <div class="flex shrink-0 items-center justify-start gap-3 sm:justify-end">
                <span class="w-8 text-right text-[13px] leading-none text-text-body">No</span>
                <input type="hidden" name="permissions[{{ $key }}]" value="0" data-team-permission-hidden @disabled($enabled)>
                <x-ui.toggle-switch
                  :active="$enabled"
                  data-team-permission-toggle
                  data-permission-input="permissions[{{ $key }}]"
                  aria-label="Toggle {{ $label }} access"
                />
                <input
                  type="checkbox"
                  name="permissions[{{ $key }}]"
                  value="1"
                  class="sr-only"
                  @checked($enabled)
                  @disabled(! $enabled)
                  data-team-permission-checkbox
                >
                <span class="w-8 text-left text-[13px] leading-none text-text-body">Yes</span>
              </div>
            </div>
          @endforeach
        </div>

        @if ($member->isManager())
          <div class="flex flex-col gap-2">
            <h2 class="text-xl font-semibold leading-[1.4] text-text-primary">Team Members</h2>
            <div class="flex w-full max-w-[564px] flex-col gap-2">
              <label for="assign_members" class="text-sm font-semibold leading-[1.4] text-text-primary">
                Assign Members to this Manager
              </label>
              <x-ui.select id="assign_members" name="member_uuids[]" multiple data-native-select="true" class="min-h-[120px]">
                @foreach ($assignableMembers as $assignable)
                  <option value="{{ $assignable['uuid'] }}" @selected(in_array($assignable['uuid'], $assignedMemberUuids, true))>
                    {{ $assignable['label'] }}
                  </option>
                @endforeach
              </x-ui.select>
              <p class="text-sm font-medium leading-[1.4] text-text-muted">
                Hold Ctrl/Cmd to select multiple. Saving will replace previous assignments for this manager.
              </p>
            </div>
          </div>
        @endif

        <div class="flex flex-wrap items-center justify-end gap-2.5">
          <a href="{{ $cancelUrl ?? route('my-team.index') }}" class="fd-btn-sm inline-flex items-center justify-center rounded border border-green-500 bg-elevated px-6 py-3 text-xs font-semibold text-green-500">Cancel</a>
          <button type="submit" class="fd-btn-sm inline-flex items-center justify-center rounded bg-green-500 px-6 py-3 text-xs font-semibold text-primary-2">Save</button>
        </div>
      </form>
    </section>
  </div>
</x-layouts.app>
