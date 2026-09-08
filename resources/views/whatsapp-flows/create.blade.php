<x-layouts.app title="Create Flow - WapApp" active="automation.whatsapp-flows">
  <div class="flex flex-col bg-surface">
    <div class="flex flex-col gap-4 p-4">
      <div class="flex flex-col gap-1">
        <div class="flex items-center gap-2">
          <a href="{{ route('whatsapp-flows.index') }}" class="text-sm text-text-subtle hover:text-text-body">&larr; Back to list</a>
        </div>
        <h1 class="text-2xl font-bold leading-[1.5] text-text-primary">Create WhatsApp Flow</h1>
        <p class="max-w-[854px] text-sm font-normal leading-[1.4] text-text-subtle opacity-50">
          Set up a new interactive form that your customers can fill out directly within WhatsApp.
        </p>
      </div>

      @if ($errors->any())
        <div class="rounded-lg bg-red-50 px-4 py-3 text-sm font-medium text-red-700">
          <ul class="list-inside list-disc">
            @foreach ($errors->all() as $error)
              <li>{{ $error }}</li>
            @endforeach
          </ul>
        </div>
      @endif

      <div class="max-w-2xl rounded-xl bg-elevated p-6 shadow-[0px_4px_6px_rgba(0,0,0,0.04)]">
        <form action="{{ route('whatsapp-flows.store') }}" method="POST" class="flex flex-col gap-5">
          @csrf

          <div class="flex flex-col gap-1.5">
            <label for="name" class="text-sm font-semibold text-text-body">
              Flow Name <span class="text-red-500">*</span>
            </label>
            <input
              type="text"
              id="name"
              name="name"
              value="{{ old('name') }}"
              required
              placeholder="e.g. Customer Feedback Survey"
              class="w-full rounded-lg border border-divider bg-surface px-4 py-3 text-sm text-text-body focus:border-green-500 focus:outline-none"
            >
          </div>

          <div class="flex flex-col gap-1.5">
            <label for="on_submit_action" class="text-sm font-semibold text-text-body">On Submit Action</label>
            <select
              id="on_submit_action"
              name="on_submit_action"
              class="w-full rounded-lg border border-divider bg-surface px-4 py-3 text-sm text-text-body focus:border-green-500 focus:outline-none"
            >
              <option value="">None (just collect data)</option>
              <option value="create_lead" @selected(old('on_submit_action') === 'create_lead')>Create / Update Contact</option>
              <option value="update_contact" @selected(old('on_submit_action') === 'update_contact')>Update Existing Contact</option>
              <option value="webhook" @selected(old('on_submit_action') === 'webhook')>Call Webhook URL</option>
            </select>
          </div>

          <div id="webhook-url-field" class="flex flex-col gap-1.5" style="{{ old('on_submit_action') === 'webhook' ? '' : 'display:none' }}">
            <label for="on_submit_webhook_url" class="text-sm font-semibold text-text-body">Webhook URL</label>
            <input
              type="url"
              id="on_submit_webhook_url"
              name="on_submit_webhook_url"
              value="{{ old('on_submit_webhook_url') }}"
              placeholder="https://your-server.com/webhook"
              class="w-full rounded-lg border border-divider bg-surface px-4 py-3 text-sm text-text-body focus:border-green-500 focus:outline-none"
            >
          </div>

          <div class="flex justify-end gap-3 pt-2">
            <a href="{{ route('whatsapp-flows.index') }}" class="rounded-lg border border-divider bg-surface px-4 py-3 text-sm font-semibold text-text-body">Cancel</a>
            <button type="submit" class="rounded-lg bg-green-500 px-6 py-3 text-sm font-semibold text-white transition-opacity hover:opacity-90">Create &amp; Open Builder</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  @push('scripts')
  <script>
  document.addEventListener('DOMContentLoaded', function () {
      var actionSelect = document.getElementById('on_submit_action');
      var webhookField = document.getElementById('webhook-url-field');
      if (actionSelect && webhookField) {
          actionSelect.addEventListener('change', function () {
              webhookField.style.display = this.value === 'webhook' ? '' : 'none';
          });
      }
  });
  </script>
  @endpush
</x-layouts.app>
