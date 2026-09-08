<x-profile.layout title="API - WapApp" headerTitle="API" active="profile.api">
  <div class="flex flex-col gap-1">
    <h1 class="text-2xl font-bold leading-[1.5] text-text-primary">API</h1>
    <p class="max-w-[854px] text-sm font-normal leading-[1.4] text-text-subtle opacity-50">
      Manage your API token and integration endpoints.
    </p>
  </div>

  @if (session('status'))
    <div class="mt-4 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-600">
      {{ session('status') }}
    </div>
  @endif

  @if ($plainToken)
    <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-medium text-amber-800">
      Copy your new API token now. It will not be shown again:
      <code class="mt-2 block break-all rounded bg-white/70 px-2 py-1 text-xs">{{ $plainToken }}</code>
    </div>
  @endif

  <section class="mt-6 flex flex-col gap-4">
    <div class="rounded-lg bg-elevated p-3">
      <div class="flex flex-col gap-4">
        <div class="overflow-hidden rounded-xl border border-divider bg-elevated shadow-[0px_4px_12px_rgba(0,0,0,0.04)]">
          <div class="grid grid-cols-1 gap-2 bg-elevated p-2 lg:grid-cols-3">
            <div class="p-2 text-[13px] font-medium leading-[1.5] text-text-body">API docs</div>
            <div class="p-2 text-[13px] font-medium leading-[1.5] text-text-body">API Endpoint</div>
            <div class="p-2 text-[13px] font-medium leading-[1.5] text-text-body">Your API token</div>
          </div>
          <div class="grid grid-cols-1 gap-2 border-t border-divider bg-elevated px-2 py-1.5 lg:grid-cols-3">
            <div class="flex items-center gap-2.5 p-2">
              <a href="{{ $docsUrl }}" class="min-w-0 flex-1 truncate text-xs font-normal leading-[1.5] text-text-body underline">{{ $docsUrl }}</a>
            </div>
            <div class="flex items-center gap-2.5 p-2">
              <span class="min-w-0 flex-1 truncate text-xs font-normal leading-[1.5] text-text-body">{{ $baseUrl }}</span>
            </div>
            <div class="flex items-center gap-2.5 p-2">
              <span class="min-w-0 flex-1 break-all text-xs font-normal leading-[1.5] text-text-body">{{ $token ?? 'No token generated yet' }}</span>
            </div>
          </div>
        </div>

        <div class="flex w-full items-start gap-3 rounded-xl bg-stat-blue/15 p-3.5">
          <img src="{{ asset('images/profile/info-circle.svg') }}" alt="" class="size-5 shrink-0" width="20" height="20">
          <p class="min-w-0 flex-1 text-sm font-medium leading-[1.4] text-text-body">
            Add parameter <strong>api_token=YOUR_API_TOKEN</strong> to each API request.
            <br>
            Example: {{ $exampleUrl }}
          </p>
        </div>
      </div>
    </div>

    <form
      action="{{ route('profile.api.renew') }}"
      method="POST"
      class="flex items-center justify-end"
      data-confirm="Renew your API token? The current token will stop working immediately."
      data-confirm-title="Renew API token"
      data-confirm-label="Renew token"
      data-confirm-variant="danger"
    >
      @csrf
      <x-ui.button type="submit" class="rounded px-6 py-3 text-xs">Renew Token</x-ui.button>
    </form>
  </section>
</x-profile.layout>
