@php
  $methodColors = [
    'GET' => 'bg-stat-blue/15 text-stat-blue',
    'POST' => 'bg-primary-2/15 text-primary-2',
    'PATCH' => 'bg-green-500/15 text-green-700',
    'DELETE' => 'bg-[rgba(255,0,0,0.12)] text-[red]',
  ];
@endphp

<x-profile.layout title="API Documentation - WapApp" headerTitle="API Documentation" active="profile.api">
  <div class="flex flex-wrap items-start justify-between gap-3">
    <div class="flex flex-col gap-1">
      <h1 class="text-2xl font-bold leading-[1.5] text-text-primary">API Documentation</h1>
      <p class="max-w-[854px] text-sm font-normal leading-[1.4] text-text-subtle opacity-50">
        REST API v1 reference for your tenant.
      </p>
    </div>
    <a href="{{ route('profile.api') }}" class="text-xs font-normal leading-[1.5] text-text-body underline">Back to API token</a>
  </div>

  <div class="mt-6 flex w-full items-start gap-3 rounded-xl bg-stat-blue/15 p-3.5">
    <img src="{{ asset('images/profile/info-circle.svg') }}" alt="" class="size-5 shrink-0" width="20" height="20">
    <p class="min-w-0 flex-1 text-sm font-medium leading-[1.4] text-text-body">
      Add parameter <strong>api_token=YOUR_API_TOKEN</strong> to each API request.
      <br>
      Example: {{ $exampleUrl }}
    </p>
  </div>

  <section class="mt-6 flex flex-col gap-6">
    @foreach ($sections as $section)
      <div class="rounded-lg bg-elevated p-3">
        <h2 class="mb-4 px-2 text-[13px] font-semibold uppercase tracking-wide text-text-body">{{ $section['title'] }}</h2>

        <div class="overflow-hidden rounded-xl border border-divider bg-elevated shadow-[0px_4px_12px_rgba(0,0,0,0.04)]">
          <div class="overflow-x-auto">
            <table class="w-full min-w-[900px] text-left">
              <thead>
                <tr class="border-b border-divider bg-elevated">
                  <th class="w-[90px] p-2 text-[13px] font-medium leading-[1.5] text-text-body">Method</th>
                  <th class="w-[35%] p-2 text-[13px] font-medium leading-[1.5] text-text-body">Endpoint</th>
                  <th class="p-2 text-[13px] font-medium leading-[1.5] text-text-body">Function</th>
                </tr>
              </thead>
              <tbody>
                @foreach ($section['functions'] as $function)
                  <tr class="border-b border-divider align-top">
                    <td class="p-2">
                      <span @class([
                        'inline-flex rounded px-2 py-1 text-[11px] font-semibold',
                        $methodColors[$function['method']] ?? 'bg-muted-surface text-text-body',
                      ])>{{ $function['method'] }}</span>
                    </td>
                    <td class="p-2">
                      <button type="button" class="api-doc-toggle text-left text-xs font-normal leading-[1.5] text-text-body underline">
                        {{ $function['uri'] }}
                      </button>
                    </td>
                    <td class="p-2 text-xs font-normal leading-[1.5] text-text-body">{{ $function['description'] }}</td>
                  </tr>
                  <tr class="api-doc-detail hidden border-b border-divider bg-muted-surface/40">
                    <td></td>
                    <td class="p-4 align-top">
                      <div class="description detailed">
                        @if (! empty($function['parameters']))
                          <h3 class="mb-2 text-sm font-semibold text-text-primary">Parameters</h3>
                          <dl class="mb-4 space-y-2">
                            @foreach ($function['parameters'] as $parameter)
                              <div>
                                <dt class="font-mono text-xs font-semibold text-text-primary">
                                  {{ $parameter['name'] }}
                                  @if (! empty($parameter['optional']))
                                    <span class="font-normal text-text-muted">
                                      (optional
                                      @if (! empty($parameter['default']))
                                        — default: {{ $parameter['default'] }}
                                      @endif
                                      )
                                    </span>
                                  @endif
                                </dt>
                                <dd class="text-sm text-text-muted">{!! $parameter['description'] !!}</dd>
                              </div>
                            @endforeach
                          </dl>
                        @endif

                        <h3 class="mb-2 text-sm font-semibold text-text-primary">Returns</h3>
                        <p class="text-sm text-text-body">{{ $function['returns'] ?? '—' }}</p>
                      </div>
                    </td>
                    <td class="p-4 align-top">
                      @if (! empty($function['example']))
                        <h3 class="mb-2 text-sm font-semibold text-text-primary">Example</h3>
                        @foreach ((array) $function['example'] as $example)
                          <pre class="mb-3 overflow-x-auto rounded-lg bg-[#111827] p-3 text-xs text-green-100"><code>{{ $example }}</code></pre>
                        @endforeach
                      @endif

                      @if (! empty($function['help']))
                        <div class="text-sm text-text-body">{!! $function['help'] !!}</div>
                      @endif
                    </td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        </div>
      </div>
    @endforeach
  </section>

  <script>
    document.addEventListener('DOMContentLoaded', () => {
      document.querySelectorAll('.api-doc-toggle').forEach((button) => {
        button.addEventListener('click', (event) => {
          event.preventDefault();
          const row = button.closest('tr')?.nextElementSibling;
          row?.classList.toggle('hidden');
        });
      });
    });
  </script>
</x-profile.layout>
