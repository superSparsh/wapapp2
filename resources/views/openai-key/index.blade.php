@php
  $tabs = [
    'bot-manager' => 'AI Bot Manager',
    'api-settings' => 'API Settings',
    'knowledge-base' => 'Knowledge Base',
    'test-bot' => 'Test Bot',
    'usage-analytics' => 'Usage Analytics',
    'global-settings' => 'Global Settings',
  ];
  $currentTab = $tab ?? 'bot-manager';
@endphp

<x-layouts.app title="OpenAI key - WapApp" active="openai-key.index">
  <div class="flex flex-col bg-surface">
    <div class="flex flex-col gap-4 p-4">
      @if (session('status'))
        <div class="rounded-lg bg-green-50 px-4 py-3 text-sm font-medium text-green-700">{{ session('status') }}</div>
      @endif
      @if ($errors->any())
        <div class="rounded-lg bg-red-50 px-4 py-3 text-sm font-medium text-red-700">
          <ul class="list-inside list-disc">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
      @endif

      <nav class="flex flex-wrap border-b-2 border-blue-50">
        @foreach ($tabs as $key => $label)
          <a
            href="{{ route('openai-key.index', ['tab' => $key]) }}"
            @class([
              'flex flex-1 items-center justify-center px-9 py-2.5 text-center text-sm font-medium leading-[1.4] whitespace-nowrap text-text-body transition-colors',
              'border-b-2 border-green-500 bg-green-100' => $currentTab === $key,
            ])
          >{{ $label }}</a>
        @endforeach
      </nav>

      @if ($currentTab === 'bot-manager')
        <div class="flex flex-col gap-1">
          <h1 class="text-2xl font-bold leading-[1.5] text-text-primary">AI Bot Manager</h1>
          <p class="max-w-[854px] text-sm font-normal leading-[1.4] text-text-subtle opacity-50">
            Create and manage specific bots for different purposes (Sales, Support, etc.). The bot marked as&nbsp;Default&nbsp;will be used as the fallback for all general queries and system settings.
          </p>
        </div>

        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
          <form action="{{ route('openai-key.index', ['tab' => 'bot-manager']) }}" method="GET" class="flex w-full max-w-[550px] items-center gap-3 overflow-hidden rounded-lg bg-elevated p-3">
            <x-icons.nav-icon name="search" class="size-5 shrink-0 opacity-60" />
            <input
              type="search"
              name="search"
              value="{{ request('search') }}"
              placeholder="Search bots"
              class="min-w-0 flex-1 bg-transparent text-sm font-medium leading-[1.4] text-text-body/60 placeholder:text-text-body/60 placeholder:opacity-60 focus:outline-none"
            >
          </form>
          <button
            type="button"
            data-open-modal="create-bot"
            class="fd-btn inline-flex items-center justify-center gap-2 self-end rounded bg-green-500 px-4 py-3 text-sm font-semibold leading-[1.5] text-primary-2 transition-opacity hover:opacity-90 sm:self-auto"
          >
            <img src="{{ asset('images/team/add.svg') }}" alt="" class="size-5" width="20" height="20">
            Create New Bot
          </button>
        </div>
      @elseif ($currentTab === 'api-settings')
        <div class="flex flex-col gap-1">
          <h1 class="text-2xl font-bold leading-[1.5] text-text-primary">Step - 1 : Configure your API settings</h1>
          <p class="max-w-[854px] text-sm font-normal leading-[1.4] text-text-subtle opacity-50">
            Manage your OpenAI, Gemini, and Azure OpenAI API keys. Keys are encrypted at rest and shared across all bots.
          </p>
          <a href="https://platform.openai.com/api-keys" target="_blank" rel="noopener noreferrer" class="max-w-[854px] text-sm font-normal leading-[1.4] text-green-500 underline">
            Don't have an API Key? Click to learn how to get one.
          </a>
        </div>

        <form action="{{ route('openai-key.provider-keys.store') }}" method="POST" class="flex w-full flex-col gap-4 overflow-hidden rounded-xl border border-border-light bg-muted-surface p-4">
          @csrf
          <div class="flex flex-col gap-1">
            <h2 class="text-xl font-bold leading-[1.5] text-text-primary">Global API Configuration</h2>
            <p class="max-w-[854px] text-sm font-normal leading-[1.4] text-text-subtle opacity-50">
              The API Key is shared across all bots for this account.
            </p>
          </div>

          <div class="flex flex-col gap-8 lg:flex-row lg:items-start">
            <div class="flex min-w-0 flex-1 flex-col gap-2">
              <label for="provider" class="text-sm font-semibold leading-[1.4] text-text-primary">
                AI Platform <span class="text-[red]">*</span>
              </label>
              <div class="relative">
                <select
                  id="provider"
                  name="provider"
                  required
                  class="w-full appearance-none rounded-xl border border-border bg-elevated p-3.5 pr-10 text-sm font-medium leading-[1.4] text-text-muted focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500"
                >
                  <option value="" selected disabled>Select AI Platform</option>
                  <option value="openai">OpenAI</option>
                  <option value="gemini">Google Gemini</option>
                  <option value="azure">Azure OpenAI</option>
                </select>
                <img
                  src="{{ asset('images/commerce/arrow-down.svg') }}"
                  alt=""
                  class="pointer-events-none absolute top-1/2 right-3.5 size-4 -translate-y-1/2"
                  width="16"
                  height="16"
                >
              </div>
            </div>

            <div class="flex min-w-0 flex-1 flex-col gap-2">
              <label for="api_key" class="text-sm font-semibold leading-[1.4] text-text-primary">
                API Key<span class="text-[red]">*</span>
              </label>
              <input
                id="api_key"
                name="api_key"
                type="password"
                required
                placeholder="sk-..."
                class="w-full rounded-xl border border-border bg-elevated p-3.5 text-sm font-medium leading-[1.4] text-text-muted placeholder:text-text-muted focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500"
              >
            </div>
          </div>

          <div class="flex flex-col gap-8 lg:flex-row lg:items-start">
            <div class="flex min-w-0 flex-1 flex-col gap-2">
              <label for="chat_model" class="text-sm font-semibold leading-[1.4] text-text-primary">
                Default Chat Model <span class="text-text-muted">(optional)</span>
              </label>
              <input
                id="chat_model"
                name="chat_model"
                type="text"
                placeholder="gpt-4o-mini"
                class="w-full rounded-xl border border-border bg-elevated p-3.5 text-sm font-medium leading-[1.4] text-text-muted placeholder:text-text-muted focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500"
              >
            </div>
            <div class="flex min-w-0 flex-1 flex-col gap-2">
              <label for="embedding_model" class="text-sm font-semibold leading-[1.4] text-text-primary">
                Default Embedding Model <span class="text-text-muted">(optional)</span>
              </label>
              <input
                id="embedding_model"
                name="embedding_model"
                type="text"
                placeholder="text-embedding-3-small"
                class="w-full rounded-xl border border-border bg-elevated p-3.5 text-sm font-medium leading-[1.4] text-text-muted placeholder:text-text-muted focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500"
              >
            </div>
          </div>

          <button
            type="submit"
            class="fd-btn-sm inline-flex w-fit items-center justify-center gap-2 rounded border border-solid border-green-500 bg-green-50 px-4 py-1.5 text-sm font-medium leading-[1.4] text-green-500 transition-opacity hover:opacity-90"
          >
            <img src="{{ asset('images/openai-key/export.svg') }}" alt="" class="size-5" width="20" height="20">
            Add API Key
          </button>
        </form>
      @elseif ($currentTab === 'knowledge-base')
        @if ($selectedBot)
          <div class="flex w-full items-center gap-3 rounded-xl border border-solid border-green-500 bg-green-50 p-3.5">
            <img src="{{ asset('images/profile/radio-selected.svg') }}" alt="" class="size-4 shrink-0" width="16" height="16">
            <p class="min-w-0 flex-1 text-sm font-medium leading-[1.4] text-text-body">
              &nbsp;Configuring Bot:&nbsp;{{ $selectedBot->name }}&nbsp;({{ $selectedBot->type ?? 'General' }})
            </p>
            <a href="{{ route('openai-key.index', ['tab' => 'bot-manager']) }}" class="shrink-0 text-sm font-medium leading-[1.4] text-green-500 underline">
              Change
            </a>
          </div>
        @else
          <div class="flex w-full items-center gap-3 rounded-xl border border-solid border-border bg-elevated p-3.5">
            <p class="min-w-0 flex-1 text-sm font-medium leading-[1.4] text-text-muted">
              No bots available. Create a bot first to manage its knowledge base.
            </p>
            <a href="{{ route('openai-key.index', ['tab' => 'bot-manager']) }}" class="shrink-0 text-sm font-medium leading-[1.4] text-green-500 underline">
              Go to Bot Manager
            </a>
          </div>
        @endif

        <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
          <div class="flex min-w-0 flex-1 flex-col gap-1">
            <h1 class="text-2xl font-bold leading-[1.5] text-text-primary">Knowledge Base Content</h1>
            <p class="max-w-[854px] text-sm font-normal leading-[1.4] text-text-subtle opacity-50">
              Add text, documents, or URLs to teach your bot about your business.
            </p>
          </div>
          @if ($selectedBot)
            <button
              type="button"
              data-open-modal="add-sources"
              class="fd-btn inline-flex shrink-0 items-center justify-center gap-2 self-end rounded bg-green-500 px-4 py-3 text-sm font-semibold leading-[1.5] text-primary-2 transition-opacity hover:opacity-90 sm:self-auto"
            >
              <img src="{{ asset('images/team/add.svg') }}" alt="" class="size-5" width="20" height="20">
              Add Sources
            </button>
          @endif
        </div>
      @elseif ($currentTab === 'test-bot')
        <div class="flex flex-col gap-1">
          <h1 class="text-2xl font-bold leading-[1.5] text-text-primary">Test Bot</h1>
          <p class="max-w-[854px] text-sm font-normal leading-[1.4] text-text-subtle opacity-50">
            Send a test message to any of your active bots and see the AI response in real time.
          </p>
        </div>
      @elseif ($currentTab === 'usage-analytics')
        <div class="flex flex-col gap-3">
          <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <h1 class="text-2xl font-bold leading-[1.5] text-text-primary">Usage Statistics</h1>
            <form action="{{ route('openai-key.usage.clear') }}" method="POST" onsubmit="return confirm('Clear all usage data?')">
              @csrf @method('DELETE')
              <button
                type="submit"
                class="fd-btn-sm inline-flex items-center justify-center gap-2 self-start rounded border border-solid border-[red] px-6 py-2 text-xs font-semibold leading-[1.5] text-[red] transition-colors hover:bg-red-50 sm:self-auto"
              >
                <img src="{{ asset('images/openai-key/trash-red.svg') }}" alt="" class="size-5" width="20" height="20">
                Clear Storage
              </button>
            </form>
          </div>

          <div class="flex flex-col gap-3">
            <h2 class="text-base font-semibold leading-[1.4] text-text-primary">&nbsp;Storage Information</h2>
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
              @php
                $storageInfo = $storageInfo ?? ['total_documents' => 0, 'total_size_mb' => '0.00', 'fileTypes' => []];
                $fileTypeCount = count($fileTypes ?? []);
                $fileTypeLabels = !empty($fileTypes) ? implode(', ', array_map(fn($k) => ucfirst($k), array_keys($fileTypes))) : 'None';
              @endphp
              <div class="flex items-start gap-5 overflow-hidden rounded-xl border border-solid border-border bg-elevated p-5">
                <div class="flex shrink-0 items-center overflow-hidden rounded-xl bg-green-50 p-3">
                  <img src="{{ asset('images/openai-key/graph.svg') }}" alt="" class="size-6" width="24" height="24">
                </div>
                <div class="flex min-w-0 flex-1 flex-col gap-2">
                  <p class="text-base font-medium leading-[1.4] whitespace-nowrap text-text-primary">Total Documents</p>
                  <p class="text-2xl font-bold leading-[38px] text-text-primary">{{ number_format($storageInfo['total_documents'] ?? 0) }}</p>
                </div>
              </div>
              <div class="flex items-start gap-5 overflow-hidden rounded-xl border border-solid border-border bg-elevated p-5">
                <div class="flex shrink-0 items-center overflow-hidden rounded-xl bg-green-50 p-3">
                  <img src="{{ asset('images/openai-key/presention-chart.svg') }}" alt="" class="size-6" width="24" height="24">
                </div>
                <div class="flex min-w-0 flex-1 flex-col gap-2">
                  <p class="text-base font-medium leading-[1.4] whitespace-nowrap text-text-primary">Total Storage</p>
                  <p class="text-2xl font-bold leading-[38px] text-text-primary">{{ $storageInfo['total_size_mb'] ?? '0.00' }} MB</p>
                </div>
              </div>
              <div class="flex items-start gap-5 overflow-hidden rounded-xl border border-solid border-border bg-elevated p-5">
                <div class="flex shrink-0 items-center overflow-hidden rounded-xl bg-green-50 p-3">
                  <img src="{{ asset('images/openai-key/personalcard.svg') }}" alt="" class="size-6" width="24" height="24">
                </div>
                <div class="flex min-w-0 flex-1 flex-col gap-2">
                  <p class="text-base font-medium leading-[1.4] whitespace-nowrap text-text-primary">File Types</p>
                  <p class="text-2xl font-bold leading-[38px] text-text-primary">{{ $fileTypeCount }} {{ str('Type')->plural($fileTypeCount) }}</p>
                </div>
              </div>
              <div class="flex items-start gap-5 overflow-hidden rounded-xl border border-solid border-border bg-elevated p-5">
                <div class="flex shrink-0 items-center overflow-hidden rounded-xl bg-green-50 p-3">
                  <img src="{{ asset('images/openai-key/personalcard.svg') }}" alt="" class="size-6" width="24" height="24">
                </div>
                <div class="flex min-w-0 flex-1 flex-col gap-2">
                  <p class="text-base font-medium leading-[1.4] whitespace-nowrap text-text-primary">Types</p>
                  <p class="text-2xl font-bold leading-[38px] text-text-primary">{{ $fileTypeLabels }}</p>
                </div>
              </div>
            </div>
          </div>
        </div>
      @else
        <div class="flex flex-col gap-1">
          <h1 class="text-2xl font-bold leading-[1.5] text-text-primary">AI Auto Response Setting</h1>
          <p class="max-w-[854px] text-sm font-normal leading-[1.4] text-text-subtle opacity-50">
            Configure global AI behavior for all customer conversations.
          </p>
        </div>
      @endif
    </div>

    <section class="bg-surface p-4 pt-0">
      @if ($currentTab === 'bot-manager')
        <div class="overflow-hidden rounded-xl bg-elevated shadow-[0px_4px_6px_rgba(0,0,0,0.04)]">
          <div class="overflow-x-auto">
            <table class="w-full min-w-[900px] text-left">
              <thead>
                <tr class="bg-elevated">
                  <th class="w-[54px] p-2 text-[13px] font-medium leading-[1.5] whitespace-nowrap text-text-body">SI. No</th>
                  <th class="p-2 text-[13px] font-medium leading-[1.5] text-text-body">Bot Name</th>
                  <th class="p-2 text-center text-[13px] font-medium leading-[1.5] text-text-body">Type</th>
                  <th class="p-2 text-center text-[13px] font-medium leading-[1.5] text-text-body">Actions</th>
                  <th class="p-2 text-center text-[13px] font-medium leading-[1.5] text-text-body">&nbsp;</th>
                  <th class="p-2 text-[13px] font-medium leading-[1.5] text-text-body">Select Default</th>
                </tr>
              </thead>
              <tbody>
                @forelse ($bots as $bot)
                  <tr class="border-t border-divider bg-elevated">
                    <td class="w-[54px] p-2 text-[13px] font-normal leading-[1.5] text-text-body">{{ $loop->iteration }}</td>
                    <td class="p-2 text-[13px] font-semibold leading-[1.5] text-text-subtle">{{ $bot->name }}</td>
                    <td class="p-2 text-center">
                      <span class="inline-flex items-center justify-center rounded bg-stat-blue/15 px-2 py-1 text-[10px] font-medium leading-[1.2] text-stat-blue">
                        {{ $bot->type ?? 'General' }}
                      </span>
                    </td>
                    <td class="p-2">
                      <div class="flex items-center justify-center gap-6">
                        <button type="button" data-open-modal="bot-analytics" data-bot-id="{{ $bot->uuid }}" data-bot-name="{{ $bot->name }}" data-bot-provider="{{ $bot->provider->label() }}" data-bot-model="{{ $bot->chat_model }}" data-bot-prompt="{{ $bot->system_prompt ? 'Yes' : 'No' }}" data-bot-kb="{{ $bot->businessInfoEntries->count() }}" aria-label="View analytics">
                          <img src="{{ asset('images/team/chart.svg') }}" alt="" class="size-5" width="20" height="20">
                        </button>
                        <a href="{{ route('ai-bots.edit', $bot) }}" aria-label="Edit bot">
                          <img src="{{ asset('images/team/edit.svg') }}" alt="" class="size-5" width="20" height="20">
                        </a>
                        <form action="{{ route('openai-key.bots.destroy', $bot) }}" method="POST" onsubmit="return confirm('Delete this bot?')" class="inline">
                          @csrf @method('DELETE')
                          <button type="submit" aria-label="Delete bot">
                            <img src="{{ asset('images/team/trash.svg') }}" alt="" class="size-5" width="20" height="20">
                          </button>
                        </form>
                      </div>
                    </td>
                    <td class="p-2 text-center">
                      <a href="{{ route('ai-bots.show', $bot) }}"
                        class="fd-btn-sm inline-flex items-center justify-center rounded bg-green-500 px-4 py-1.5 text-[10px] font-semibold leading-[1.5] text-primary-2 transition-opacity hover:opacity-90"
                      >
                        Manage
                      </a>
                    </td>
                    <td class="p-2">
                      <button
                        type="button"
                        onclick="toggleDefault(@js($bot->uuid), this)"
                        @class([
                          'inline-flex cursor-pointer items-center justify-center gap-2 rounded px-4 py-1.5',
                          'border border-green-500 bg-green-50' => $bot->is_default,
                          'border border-solid border-border' => ! $bot->is_default,
                        ])
                      >
                        <span class="relative size-4 shrink-0">
                          <img
                            src="{{ asset('images/profile/radio-unselected.svg') }}"
                            alt=""
                            class="size-4 {{ $bot->is_default ? 'hidden' : '' }}"
                            width="16"
                            height="16"
                          >
                          <img
                            src="{{ asset('images/profile/radio-selected.svg') }}"
                            alt=""
                            class="size-4 {{ $bot->is_default ? '' : 'hidden' }}"
                            width="16"
                            height="16"
                          >
                        </span>
                        <span
                          @class([
                            'text-sm font-medium leading-[1.4]',
                            'text-green-500' => $bot->is_default,
                            'text-[#bcbcbc]' => ! $bot->is_default,
                          ])
                        >
                          Default
                        </span>
                      </button>
                    </td>
                  </tr>
                @empty
                  <tr class="border-t border-divider bg-elevated">
                    <td colspan="6" class="p-8 text-center text-sm text-text-muted">
                      No AI bots found. <button type="button" data-open-modal="create-bot" class="text-green-500 underline">Create one now</button>.
                    </td>
                  </tr>
                @endforelse
              </tbody>
            </table>
          </div>
          @if ($bots->hasPages())
            <x-ui.table-pagination :paginator="$bots" />
          @endif
        </div>
      @elseif ($currentTab === 'api-settings')
        <div class="overflow-hidden rounded-xl bg-elevated shadow-[0px_4px_6px_rgba(0,0,0,0.04)]">
          <div class="overflow-x-auto">
            <table class="w-full min-w-[640px] text-left">
              <thead>
                <tr class="bg-elevated">
                  <th class="p-3 text-[13px] font-medium leading-[1.5] text-text-body">Provider</th>
                  <th class="p-3 text-[13px] font-medium leading-[1.5] text-text-body">Chat Model</th>
                  <th class="p-3 text-[13px] font-medium leading-[1.5] text-text-body">Embedding Model</th>
                  <th class="p-3 text-[13px] font-medium leading-[1.5] text-text-body">Status</th>
                  <th class="p-3 text-[13px] font-medium leading-[1.5] text-text-body">Actions</th>
                </tr>
              </thead>
              <tbody>
                @forelse ($keys as $key)
                  <tr class="border-t border-divider bg-elevated">
                    <td class="p-3 text-[13px] font-semibold leading-[1.5] text-text-primary">{{ $key->provider->label() }}</td>
                    <td class="p-3 text-[13px] text-text-body">{{ $key->chat_model ?? '-' }}</td>
                    <td class="p-3 text-[13px] text-text-body">{{ $key->embedding_model ?? '-' }}</td>
                    <td class="p-3">
                      @if ($key->is_validated)
                        <span class="inline-flex items-center justify-center rounded bg-[rgba(0,128,0,0.1)] px-2 py-1 text-[10px] font-medium leading-[1.2] text-[green]">Validated</span>
                      @elseif ($key->is_active)
                        <span class="inline-flex items-center justify-center rounded bg-[rgba(0,0,255,0.08)] px-2 py-1 text-[10px] font-medium leading-[1.2] text-[blue]">Active</span>
                      @else
                        <span class="inline-flex items-center justify-center rounded bg-[rgba(0,0,0,0.1)] px-2 py-1 text-[10px] font-medium leading-[1.2] text-text-muted">Inactive</span>
                      @endif
                    </td>
                    <td class="p-3">
                      <div class="flex items-center gap-3">
                        <button type="button" onclick="validateKey({{ $key->id }}, this)" class="text-xs text-green-500 hover:underline">Validate</button>
                        <form action="{{ route('openai-key.provider-keys.destroy', $key) }}" method="POST" onsubmit="return confirm('Delete this key?')">
                          @csrf @method('DELETE')
                          <button type="submit" class="text-xs text-red-500 hover:underline">Delete</button>
                        </form>
                      </div>
                    </td>
                  </tr>
                @empty
                  <tr class="border-t border-divider bg-elevated">
                    <td colspan="5" class="p-8 text-center text-sm text-text-muted">No provider keys configured.</td>
                  </tr>
                @endforelse
              </tbody>
            </table>
          </div>
          @if ($keys->hasPages())
            <x-ui.table-pagination :paginator="$keys" />
          @endif
        </div>
      @elseif ($currentTab === 'knowledge-base')
        @if ($selectedBot)
          <div class="flex flex-col gap-4">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
              <div class="flex flex-wrap items-center gap-4">
                <p class="text-sm font-normal leading-[1.4] text-text-subtle opacity-50">
                  Total Chunks:&nbsp;<span class="font-bold">{{ $entries->total() ?? 0 }}</span>
                </p>
                <span class="hidden h-[13.5px] w-px bg-[rgba(13,13,13,0.2)] sm:block" aria-hidden="true"></span>
                <p class="text-sm font-normal leading-[1.4] text-text-subtle opacity-50">
                  Storage:&nbsp;<span class="font-bold">{{ $storageInfo['total_size_mb'] ?? '0.00' }}&nbsp;MB</span>
                </p>
                <span class="hidden h-[13.5px] w-px bg-[rgba(13,13,13,0.2)] sm:block" aria-hidden="true"></span>
                <div class="flex items-center gap-1">
                  <p class="text-sm font-normal leading-[1.4] text-text-subtle opacity-50">Types:</p>
                  @foreach (['text' => 'Text', 'url' => 'URL', 'document' => 'Document'] as $val => $label)
                    @if ($entries->contains('content_type', $val))
                      <span class="inline-flex items-center justify-center rounded bg-stat-blue/15 px-2 py-1 text-[10px] font-medium leading-[1.2] text-stat-blue">{{ $label }}</span>
                    @endif
                  @endforeach
                </div>
              </div>

              <div class="flex flex-wrap items-center gap-4">
                <a href="{{ route('openai-key.index', ['tab' => 'knowledge-base', 'bot' => $selectedBot->uuid]) }}"
                  class="fd-btn inline-flex items-center justify-center gap-3 rounded border border-solid border-border-light bg-elevated px-4 py-3 text-sm font-semibold leading-[1.5] text-green-500 transition-colors hover:bg-surface"
                >
                  <img src="{{ asset('images/openai-key/refresh.svg') }}" alt="" class="size-4" width="16" height="16">
                  Refresh
                </a>
              </div>
            </div>

            <div class="overflow-hidden rounded-xl bg-elevated shadow-[0px_4px_6px_rgba(0,0,0,0.04)]">
              <div class="overflow-x-auto">
                <table class="w-full min-w-[800px] text-left">
                  <thead>
                    <tr class="bg-elevated">
                      <th class="w-[54px] p-2 text-[13px] font-medium leading-[1.5] whitespace-nowrap text-text-body">SI. No</th>
                      <th class="w-[140px] p-2 text-[13px] font-medium leading-[1.5] text-text-body">Source</th>
                      <th class="w-[80px] p-2 text-[13px] font-medium leading-[1.5] text-text-body">Type</th>
                      <th class="p-2 text-[13px] font-medium leading-[1.5] text-text-body">Preview</th>
                      <th class="w-[80px] p-2 text-[13px] font-medium leading-[1.5] text-text-body">Action</th>
                    </tr>
                  </thead>
                  <tbody>
                    @forelse ($entries as $entry)
                      <tr class="border-t border-divider bg-elevated">
                        <td class="w-[54px] p-2 text-[13px] font-normal leading-[1.5] text-text-body">{{ $loop->iteration }}</td>
                        <td class="w-[140px] p-2 text-[13px] font-semibold leading-[1.5] text-text-subtle">{{ $entry->title }}</td>
                        <td class="w-[80px] p-2">
                          <span class="inline-flex items-center justify-center rounded bg-stat-blue/15 px-2 py-1 text-[10px] font-medium leading-[1.2] text-stat-blue">
                            {{ $entry->content_type->value }}
                          </span>
                        </td>
                        <td class="p-2 text-[13px] font-normal leading-[1.5] text-text-body opacity-60">
                          {{ \Illuminate\Support\Str::limit($entry->content ?: $entry->file_name ?: '', 150) }}
                        </td>
                        <td class="w-[80px] p-2">
                          <form action="{{ route('openai-key.business-info.destroy', [$selectedBot, $entry]) }}" method="POST" onsubmit="return confirm('Delete this entry?')">
                            @csrf @method('DELETE')
                            <button type="submit" aria-label="Delete entry" class="inline-flex text-red-500 hover:underline">
                              <img src="{{ asset('images/team/trash.svg') }}" alt="" class="size-5" width="20" height="20">
                            </button>
                          </form>
                        </td>
                      </tr>
                    @empty
                      <tr class="border-t border-divider bg-elevated">
                        <td colspan="5" class="p-8 text-center text-sm text-text-muted">No knowledge base entries yet.</td>
                      </tr>
                    @endforelse
                  </tbody>
                </table>
              </div>
              @if ($entries->hasPages())
                <x-ui.table-pagination :paginator="$entries" />
              @endif
            </div>
          </div>
        @else
          <div class="rounded-xl bg-elevated p-8 text-center text-sm text-text-muted">
            No bots available. <a href="{{ route('openai-key.index', ['tab' => 'bot-manager']) }}" class="text-green-500 underline">Create a bot first</a>.
          </div>
        @endif
      @elseif ($currentTab === 'test-bot')
        <div class="rounded-xl bg-elevated p-5">
          <div class="flex flex-col items-start gap-4 xl:flex-row">
            <div class="flex w-full min-w-0 flex-1 flex-col gap-4">
              <div class="flex flex-col gap-3">
                <label for="test_bot_id" class="text-sm font-semibold leading-[1.4] text-text-primary">
                  Select Bot
                </label>
                <select
                  id="test_bot_id"
                  class="w-full appearance-none rounded-xl border border-border bg-elevated p-3.5 text-sm font-medium leading-[1.4] text-text-muted focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500"
                >
                  <option value="" selected disabled>Select a bot</option>
                  @foreach ($bots as $bot)
                    <option value="{{ $bot->uuid }}">{{ $bot->name }} ({{ $bot->provider->label() }})</option>
                  @endforeach
                </select>
              </div>
              <div class="flex flex-col gap-3">
                <label for="test_message" class="text-sm font-semibold leading-[1.4] text-text-primary">
                  Ask Questions
                </label>
                <input
                  id="test_message"
                  type="text"
                  placeholder="Enter your question"
                  class="w-full rounded-xl border border-border bg-elevated p-3.5 text-sm font-medium leading-[1.4] text-text-muted placeholder:text-text-muted focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500"
                >
              </div>
              <div class="flex items-center justify-end">
                <button
                  type="button"
                  onclick="testBot()"
                  class="fd-btn-sm inline-flex items-center justify-center rounded bg-green-500 px-6 py-3 text-xs font-semibold leading-[1.5] text-primary-2 transition-opacity hover:opacity-90"
                >
                  Test
                </button>
              </div>
              <div id="test-response" class="hidden rounded-xl border border-border bg-muted-surface p-4">
                <p class="text-sm font-semibold text-text-primary">AI Response</p>
                <p id="test-response-text" class="mt-2 text-sm text-text-body"></p>
                <p id="test-response-error" class="mt-2 text-sm text-red-500"></p>
                <p id="test-response-tokens" class="mt-1 text-xs text-text-muted"></p>
              </div>
            </div>

            <div class="mx-auto w-full max-w-[425px] shrink-0 px-2 py-4 xl:mx-0">
              <div class="relative h-[518px] w-full overflow-hidden">
                <div class="pointer-events-none absolute inset-[0_2.5px_-338px_3.5px] rounded-[62px] border border-[rgba(255,255,255,0.6)] shadow-[inset_0px_0px_8px_0px_rgba(0,0,0,0.3)]">
                  <div class="absolute inset-0 rounded-[62px] bg-muted-surface"></div>
                </div>
                <div class="absolute inset-[4px_6.5px_-334px_7.5px] rounded-[58px] bg-black"></div>
                <div class="absolute top-[136px] left-[0.5px] h-[30px] w-[3px] rounded-tl-[1px] rounded-bl-[1px] bg-border shadow-[inset_1px_0px_2px_0px_white]"></div>
                <div class="absolute top-[198px] left-[0.5px] h-[62px] w-[3px] rounded-tl-[1px] rounded-bl-[1px] bg-border shadow-[inset_1px_0px_2px_0px_white]"></div>
                <div class="absolute top-[278px] left-[0.5px] h-[62px] w-[3px] rounded-tl-[1px] rounded-bl-[1px] bg-border shadow-[inset_1px_0px_2px_0px_white]"></div>
                <div class="absolute top-[220px] right-[-0.5px] h-[100px] w-[3px] rounded-tr-[1px] rounded-br-[1px] bg-border shadow-[inset_-1px_0px_2px_0px_white]"></div>

                <div class="absolute inset-[22px_24px_0_26px] overflow-hidden rounded-t-[40px] bg-elevated">
                  <img
                    src="{{ asset('images/openai-key/test-bot-phone.png') }}"
                    alt=""
                    class="absolute top-0 left-1/2 h-[496px] w-[375px] max-w-none -translate-x-1/2 rounded-t-lg object-cover object-top"
                    width="375"
                    height="496"
                  >
                </div>

                <div id="phone-response" class="hidden absolute top-[315px] left-[43px] z-10 flex w-[min(354px,calc(100%-86px))] items-start gap-3 rounded-lg border border-border bg-elevated p-3.5">
                  <div class="min-w-0 flex-1 text-xs font-normal leading-[1.4] text-text-muted">
                    <p id="phone-response-text"></p>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      @elseif ($currentTab === 'usage-analytics')
        @php
          $stats = $stats ?? [];
          $totalTokens = (int) ($stats['total_tokens'] ?? 0);
          $totalCost = (float) ($stats['total_cost_usd'] ?? 0);
          $totalRequests = (int) ($stats['total_requests'] ?? 0);
          $byModel = $stats['by_model'] ?? [];
        @endphp
        <div class="flex flex-col gap-4">
          <div class="grid gap-4 md:grid-cols-3">
            <div class="rounded-xl bg-elevated p-5">
              <p class="text-xs text-text-muted">Total Requests</p>
              <p class="mt-1 text-2xl font-bold text-text-primary">{{ number_format($totalRequests) }}</p>
            </div>
            <div class="rounded-xl bg-elevated p-5">
              <p class="text-xs text-text-muted">Total Tokens</p>
              <p class="mt-1 text-2xl font-bold text-text-primary">{{ number_format($totalTokens) }}</p>
            </div>
            <div class="rounded-xl bg-elevated p-5">
              <p class="text-xs text-text-muted">Estimated Cost (USD)</p>
              <p class="mt-1 text-2xl font-bold text-green-500">${{ number_format($totalCost, 4) }}</p>
            </div>
          </div>

          @if (! empty($byModel))
            <div class="overflow-hidden rounded-xl bg-elevated shadow-[0px_4px_6px_rgba(0,0,0,0.04)]">
              <div class="p-4 text-base font-semibold text-text-primary">Usage by Model</div>
              <div class="overflow-x-auto">
                <table class="w-full text-left">
                  <thead>
                    <tr class="border-t border-divider bg-elevated">
                      <th class="p-3 text-[13px] font-medium leading-[1.5] text-text-body">Model</th>
                      <th class="p-3 text-[13px] font-medium leading-[1.5] text-text-body">Requests</th>
                      <th class="p-3 text-[13px] font-medium leading-[1.5] text-text-body">Prompt Tokens</th>
                      <th class="p-3 text-[13px] font-medium leading-[1.5] text-text-body">Completion Tokens</th>
                      <th class="p-3 text-[13px] font-medium leading-[1.5] text-text-body">Total Tokens</th>
                      <th class="p-3 text-[13px] font-medium leading-[1.5] text-text-body">Cost (USD)</th>
                    </tr>
                  </thead>
                  <tbody>
                    @foreach ($byModel as $row)
                      <tr class="border-t border-divider bg-elevated">
                        <td class="p-3 text-[13px] font-semibold text-text-primary">{{ $row['model'] }}</td>
                        <td class="p-3 text-[13px] text-text-body">{{ number_format($row['requests']) }}</td>
                        <td class="p-3 text-[13px] text-text-body">{{ number_format($row['prompt_tokens']) }}</td>
                        <td class="p-3 text-[13px] text-text-body">{{ number_format($row['completion_tokens']) }}</td>
                        <td class="p-3 text-[13px] text-text-body">{{ number_format($row['total_tokens']) }}</td>
                        <td class="p-3 text-[13px] text-green-500">${{ number_format($row['cost_usd'], 4) }}</td>
                      </tr>
                    @endforeach
                  </tbody>
                </table>
              </div>
            </div>
          @else
            <div class="rounded-xl bg-elevated p-8 text-center text-sm text-text-muted">No usage data recorded yet.</div>
          @endif

          <div class="flex flex-col gap-4">
            <h2 class="text-base font-semibold leading-[1.4] text-text-primary">&nbsp;API Usage Information</h2>
            <div class="flex flex-col gap-4 rounded-xl bg-elevated p-3">
              <div class="flex items-start gap-3 rounded-xl bg-green-100 p-3.5">
                <img src="{{ asset('images/openai-key/tick-circle.svg') }}" alt="" class="size-6 shrink-0" width="24" height="24">
                <div class="flex min-w-0 flex-1 flex-col gap-1">
                  <p class="text-sm font-bold leading-[1.4] text-text-body">API Status</p>
                  <p class="text-sm font-normal leading-[1.4] text-text-muted">
                    For detailed usage information, please visit
                    <a href="https://platform.openai.com/usage" target="_blank" rel="noopener noreferrer" class="underline">
                      https://platform.openai.com/usage
                    </a>
                  </p>
                </div>
              </div>
            </div>
          </div>
        </div>
      @else
        <form action="{{ route('openai-key.settings.save') }}" method="POST" class="flex flex-col gap-4">
          @csrf
          <div class="flex w-full items-start gap-3 rounded-xl bg-green-100 p-3.5">
            <div class="flex min-w-0 flex-1 flex-col gap-2">
              <p class="text-sm font-bold leading-[1.4] text-text-body">
                Enable AI Response for All Customers
              </p>
              <div class="flex w-full items-center gap-2.5 p-2">
                <input type="checkbox" name="ai_auto_response_enabled" value="1" id="ai_auto_response" class="peer sr-only" @checked($autoResponseEnabled)>
                <label for="ai_auto_response" class="relative h-6 w-11 cursor-pointer rounded-full bg-gray-300 transition-colors peer-checked:bg-green-500">
                  <span class="absolute left-0.5 top-0.5 size-5 rounded-full bg-white transition-transform peer-checked:translate-x-5"></span>
                </label>
                <p class="min-w-0 flex-1 text-sm font-normal leading-[1.4] text-text-muted">
                  {{ $autoResponseEnabled ? 'AI responses are enabled' : 'AI responses are disabled' }}
                </p>
              </div>
            </div>
          </div>

          <div class="flex items-end">
            <button
              type="submit"
              class="fd-btn inline-flex items-center justify-center rounded bg-green-500 px-6 py-3 text-sm font-semibold leading-[1.5] text-primary-2 transition-opacity hover:opacity-90"
            >
              Save Setting
            </button>
          </div>
        </form>
      @endif
    </section>
  </div>

  <x-openai-key.create-bot-modal
    :open="request('modal') === 'create-bot'"
    :close-href="route('openai-key.index', ['tab' => $currentTab])"
  />
  @if ($currentTab === 'knowledge-base' && isset($selectedBot) && $selectedBot)
    <x-openai-key.add-sources-modal
      :open="request('modal') === 'add-sources'"
      :close-href="route('openai-key.index', ['tab' => 'knowledge-base', 'bot' => $selectedBot->uuid])"
      :bot-id="$selectedBot->uuid"
    />
  @endif
  <x-openai-key.bot-analytics-modal
    :open="request('modal') === 'bot-analytics'"
    :close-href="route('openai-key.index', ['tab' => 'bot-manager'])"
  />
</x-layouts.app>

<script>
async function toggleDefault(botId, btn) {
  const token = document.querySelector('meta[name="csrf-token"]').content;
  try {
    const res = await fetch('/openai-key/bots/' + botId + '/toggle-default', {
      method: 'POST',
      headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json' },
    });
    if (res.ok) {
      window.location.reload();
    }
  } catch (e) { console.error(e); }
}

async function validateKey(keyId, btn) {
  const token = document.querySelector('meta[name="csrf-token"]').content;
  btn.textContent = 'Validating...';
  try {
    const res = await fetch('/openai-key/provider-keys/' + keyId + '/validate', {
      method: 'POST',
      headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json' },
    });
    const data = await res.json();
    btn.textContent = data.is_validated ? 'Validated' : 'Validate';
    if (data.is_validated) { window.location.reload(); }
  } catch (e) {
    btn.textContent = 'Validate';
    console.error(e);
  }
}

async function testBot() {
  const botId = document.getElementById('test_bot_id').value;
  const message = document.getElementById('test_message').value;
  const responseDiv = document.getElementById('test-response');
  const responseText = document.getElementById('test-response-text');
  const responseError = document.getElementById('test-response-error');
  const responseTokens = document.getElementById('test-response-tokens');
  const phoneResponse = document.getElementById('phone-response');
  const phoneText = document.getElementById('phone-response-text');

  if (!botId || !message) return;

  responseDiv.classList.remove('hidden');
  responseError.textContent = '';
  responseText.textContent = 'Loading...';
  responseTokens.textContent = '';
  phoneResponse.classList.add('hidden');

  const token = document.querySelector('meta[name="csrf-token"]').content;
  try {
    const res = await fetch('{{ route("openai-key.test-bot") }}', {
      method: 'POST',
      headers: { 'X-CSRF-TOKEN': token, 'Content-Type': 'application/json', 'Accept': 'application/json' },
      body: JSON.stringify({ bot_id: botId, message: message }),
    });
    const data = await res.json();
    if (data.error) {
      responseText.textContent = '';
      responseError.textContent = data.error;
    } else {
      responseText.textContent = data.response;
      responseTokens.textContent = data.tokens + ' tokens used';
      phoneText.innerHTML = '<p>' + data.response + '</p>';
      phoneResponse.classList.remove('hidden');
    }
  } catch (e) {
    responseText.textContent = '';
    responseError.textContent = 'An error occurred. Please try again.';
  }
}
</script>
