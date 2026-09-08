<x-layouts.app title="Create AI Bot - WapApp" active="ai-bots">
  <div class="flex flex-col bg-surface">
    <div class="flex flex-col gap-4 p-4">
      <div class="flex flex-col gap-1">
        <div class="flex items-center gap-2"><a href="{{ route('ai-bots.index') }}" class="text-text-subtle hover:text-text-body">&larr; Back</a></div>
        <h1 class="text-2xl font-bold leading-[1.5] text-text-primary">Create AI Bot</h1>
      </div>
      @if ($errors->any())
        <div class="rounded-lg bg-red-50 px-4 py-3 text-sm font-medium text-red-700">
          <ul class="list-inside list-disc">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
      @endif
      <form action="{{ route('ai-bots.store') }}" method="POST" class="max-w-[600px] rounded-xl bg-elevated p-6 shadow-[0px_4px_6px_rgba(0,0,0,0.04)]">
        @csrf
        <div class="flex flex-col gap-5">
          <div class="flex flex-col gap-1.5">
            <label for="name" class="text-sm font-semibold text-text-body">Bot Name <span class="text-red-500">*</span></label>
            <input type="text" id="name" name="name" value="{{ old('name') }}" required placeholder="e.g. Customer Support Bot" class="w-full rounded-lg border border-divider bg-surface px-4 py-3 text-sm text-text-body focus:border-green-500 focus:outline-none">
          </div>
          <div class="flex flex-col gap-1.5">
            <label for="provider" class="text-sm font-semibold text-text-body">Provider</label>
            <select id="provider" name="provider" class="w-full rounded-lg border border-divider bg-surface px-4 py-3 text-sm text-text-body focus:border-green-500 focus:outline-none">
              <option value="openai">OpenAI</option>
              <option value="gemini">Google Gemini</option>
              <option value="azure">Azure OpenAI</option>
            </select>
          </div>
          <div class="flex flex-col gap-1.5">
            <label for="chat_model" class="text-sm font-semibold text-text-body">Chat Model</label>
            <input type="text" id="chat_model" name="chat_model" value="{{ old('chat_model', 'gpt-4o-mini') }}" class="w-full rounded-lg border border-divider bg-surface px-4 py-3 text-sm text-text-body focus:border-green-500 focus:outline-none">
          </div>
          <div class="flex flex-col gap-1.5">
            <label for="temperature" class="text-sm font-semibold text-text-body">Temperature</label>
            <input type="number" id="temperature" name="temperature" value="{{ old('temperature', '0.3') }}" step="0.1" min="0" max="2" class="w-full rounded-lg border border-divider bg-surface px-4 py-3 text-sm text-text-body focus:border-green-500 focus:outline-none">
          </div>
          <div class="flex flex-col gap-1.5">
            <label for="system_prompt" class="text-sm font-semibold text-text-body">System Prompt</label>
            <textarea id="system_prompt" name="system_prompt" rows="4" placeholder="You are a helpful assistant..." class="w-full rounded-lg border border-divider bg-surface px-4 py-3 text-sm text-text-body focus:border-green-500 focus:outline-none">{{ old('system_prompt') }}</textarea>
          </div>
          <div class="flex flex-col gap-1.5">
            <label for="business_information" class="text-sm font-semibold text-text-body">Business Information</label>
            <textarea id="business_information" name="business_information" rows="3" placeholder="Describe your business..." class="w-full rounded-lg border border-divider bg-surface px-4 py-3 text-sm text-text-body focus:border-green-500 focus:outline-none">{{ old('business_information') }}</textarea>
          </div>
          <div class="flex items-center gap-3">
            <input type="checkbox" id="is_default" name="is_default" value="1" class="rounded border-divider">
            <label for="is_default" class="text-sm text-text-body">Set as default bot</label>
          </div>
          <div class="flex items-center gap-3 pt-2">
            <a href="{{ route('ai-bots.index') }}" class="inline-flex items-center justify-center rounded-lg border border-divider bg-surface px-5 py-3 text-sm font-semibold text-text-body">Cancel</a>
            <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-lg bg-green-500 px-5 py-3 text-sm font-semibold text-primary-2 hover:opacity-90">Create Bot</button>
          </div>
        </div>
      </form>
    </div>
  </div>
</x-layouts.app>
