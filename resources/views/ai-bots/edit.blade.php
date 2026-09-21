<x-layouts.app title="Edit {{ $bot->name }} - WapApp" active="ai-bots">
  <div class="flex flex-col bg-surface">
    <div class="flex flex-col gap-4 p-4">
      <div class="flex items-center gap-2"><a href="{{ route('ai-bots.index') }}" class="text-text-subtle hover:text-text-body">&larr; Back</a></div>
      <h1 class="text-2xl font-bold text-text-primary">Edit: {{ $bot->name }}</h1>
      @if ($errors->any())
        <div class="rounded-lg bg-red-50 px-4 py-3 text-sm font-medium text-red-700"><ul class="list-inside list-disc">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
      @endif
      <form action="{{ route('ai-bots.update', $bot) }}" method="POST" class="max-w-[600px] rounded-xl bg-elevated p-6">
        @csrf @method('PUT')
        <div class="flex flex-col gap-5">
          <div class="flex flex-col gap-1.5">
            <label for="name" class="text-sm font-semibold text-text-body">Bot Name</label>
            <input type="text" id="name" name="name" value="{{ old('name', $bot->name) }}" required class="w-full rounded-lg border border-divider bg-surface px-4 py-3 text-sm text-text-body focus:border-green-500 focus:outline-none">
          </div>
          <div class="flex flex-col gap-1.5">
            <label for="provider" class="text-sm font-semibold text-text-body">Provider</label>
            <select id="provider" name="provider" class="w-full rounded-lg border border-divider bg-surface px-4 py-3 text-sm text-text-body focus:border-green-500 focus:outline-none">
              <option value="openai" @selected($bot->provider->value === 'openai')>OpenAI</option>
              <option value="gemini" @selected($bot->provider->value === 'gemini')>Google Gemini</option>
            </select>
          </div>
          <div class="flex flex-col gap-1.5">
            <label for="chat_model" class="text-sm font-semibold text-text-body">Chat Model</label>
            <select id="chat_model" name="chat_model" class="w-full rounded-lg border border-divider bg-surface px-4 py-3 text-sm text-text-body focus:border-green-500 focus:outline-none">
              <option value="{{ old('chat_model', $bot->chat_model) }}" selected>{{ old('chat_model', $bot->chat_model) ?: 'Select chat model' }}</option>
            </select>
            <p class="text-xs text-text-muted">Loads from the active API key for this provider.</p>
          </div>
          <div class="flex flex-col gap-1.5">
            <label for="embedding_model" class="text-sm font-semibold text-text-body">Embedding Model</label>
            <select id="embedding_model" name="embedding_model" class="w-full rounded-lg border border-divider bg-surface px-4 py-3 text-sm text-text-body focus:border-green-500 focus:outline-none">
              <option value="{{ old('embedding_model', $bot->embedding_model) }}" selected>{{ old('embedding_model', $bot->embedding_model) ?: 'Select embedding model' }}</option>
            </select>
          </div>
          <div class="flex flex-col gap-1.5">
            <label for="temperature" class="text-sm font-semibold text-text-body">Temperature</label>
            <input type="number" id="temperature" name="temperature" value="{{ old('temperature', $bot->temperature) }}" step="0.1" min="0" max="2" class="w-full rounded-lg border border-divider bg-surface px-4 py-3 text-sm text-text-body focus:border-green-500 focus:outline-none">
          </div>
          <div class="flex flex-col gap-1.5">
            <label for="system_prompt" class="text-sm font-semibold text-text-body">System Prompt</label>
            <textarea id="system_prompt" name="system_prompt" rows="4" class="w-full rounded-lg border border-divider bg-surface px-4 py-3 text-sm text-text-body focus:border-green-500 focus:outline-none">{{ old('system_prompt', $bot->system_prompt) }}</textarea>
          </div>
          <div class="flex flex-col gap-1.5">
            <label for="business_information" class="text-sm font-semibold text-text-body">Business Information</label>
            <textarea id="business_information" name="business_information" rows="3" class="w-full rounded-lg border border-divider bg-surface px-4 py-3 text-sm text-text-body focus:border-green-500 focus:outline-none">{{ old('business_information', $bot->business_information) }}</textarea>
          </div>
          <div class="flex flex-col gap-1.5">
            <label for="status" class="text-sm font-semibold text-text-body">Status</label>
            <select id="status" name="status" class="w-full rounded-lg border border-divider bg-surface px-4 py-3 text-sm text-text-body focus:border-green-500 focus:outline-none">
              <option value="active" @selected($bot->status === 'active')>Active</option>
              <option value="inactive" @selected($bot->status === 'inactive')>Inactive</option>
            </select>
          </div>
          <div class="flex items-center gap-3 pt-2">
            <a href="{{ route('ai-bots.index') }}" class="rounded-lg border border-divider bg-surface px-5 py-3 text-sm font-semibold text-text-body">Cancel</a>
            <button type="submit" class="rounded-lg bg-green-500 px-5 py-3 text-sm font-semibold text-primary-2 hover:opacity-90">Save Changes</button>
          </div>
        </div>
      </form>
    </div>
  </div>
</x-layouts.app>

<script>
(function () {
  const modelsUrl = @json(route('openai-key.provider-keys.models'));
  const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
  const providerSel = document.getElementById('provider');
  const chatSel = document.getElementById('chat_model');
  const embedSel = document.getElementById('embedding_model');
  if (!providerSel || !chatSel) return;

  function modelId(m) { return (m && (m.id || m.name)) ? String(m.id || m.name) : ''; }

  function fillSelect(select, models, keep) {
    const current = keep || select.value;
    select.innerHTML = '';
    const empty = document.createElement('option');
    empty.value = '';
    empty.textContent = 'Select model';
    select.appendChild(empty);
    (models || []).forEach(function (m) {
      const id = modelId(m);
      if (!id) return;
      const opt = document.createElement('option');
      opt.value = id;
      opt.textContent = m.name || id;
      select.appendChild(opt);
    });
    if (current && Array.from(select.options).some(function (o) { return o.value === current; })) {
      select.value = current;
    } else if (!current && select.options.length > 1) {
      select.selectedIndex = 1;
    }
  }

  async function load() {
    const provider = providerSel.value;
    const keepChat = chatSel.value;
    const keepEmbed = embedSel ? embedSel.value : '';
    try {
      const res = await fetch(modelsUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
        body: JSON.stringify({ provider: provider }),
      });
      const data = await res.json();
      if (!res.ok) throw new Error(data.error || 'Failed');
      fillSelect(chatSel, data.chat_models, keepChat);
      if (embedSel) fillSelect(embedSel, data.embedding_models, keepEmbed);
    } catch (e) { /* keep current option */ }
  }

  providerSel.addEventListener('change', load);
  load();
})();
</script>
