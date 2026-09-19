@php
  $state = $state ?? ['enabled' => false, 'message' => '', 'until' => null, 'modules' => []];
  $modules = $modules ?? [];
  $live = $live ?? false;
@endphp

<x-admin.layout title="Maintenance mode - Admin" active="admin.maintenance.edit">
  <div class="flex flex-wrap items-start justify-between gap-3 p-4">
    <div>
      <h1 class="text-2xl font-bold text-text-primary">Maintenance mode</h1>
      <p class="text-sm text-text-subtle opacity-70">
        Lock the customer dashboard while keeping selected automations alive.
      </p>
    </div>
    <div @class([
      'rounded-full px-3 py-1.5 text-xs font-semibold',
      'bg-amber-100 text-amber-800' => $live,
      'bg-green-100 text-green-800' => ! $live,
    ])>
      {{ $live ? 'LIVE — customer site locked' : 'Off — customer site open' }}
    </div>
  </div>
@if ($errors->any())
    <div class="mx-4 mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-600">{{ $errors->first() }}</div>
  @endif

  <form method="POST" action="{{ route('admin.maintenance.update') }}" class="mx-4 mb-10 max-w-5xl space-y-4">
    @csrf
    @method('PUT')

    <section class="overflow-hidden rounded-[24px] border border-border bg-elevated">
      <div class="bg-gradient-to-r from-slate-900 via-slate-800 to-emerald-900 px-5 py-6 text-white">
        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-200">Platform control</p>
        <h2 class="mt-2 text-xl font-bold">Customer access kill switch</h2>
        <p class="mt-2 max-w-2xl text-sm text-slate-200/90">
          When ON, tenants cannot open dashboard / login UI. Admin panel stays available.
          Choose which background modules must keep running.
        </p>
      </div>

      <div class="grid gap-5 p-5 lg:grid-cols-[1.1fr_0.9fr]">
        <div class="space-y-4">
          <label class="flex items-center justify-between gap-3 rounded-2xl border border-border bg-surface px-4 py-3">
            <span>
              <span class="block text-sm font-semibold text-text-primary">Enable maintenance mode</span>
              <span class="block text-xs text-text-subtle">Blocks customer site immediately after save.</span>
            </span>
            <input type="hidden" name="enabled" value="0">
            <input type="checkbox" name="enabled" value="1" class="size-5 accent-green-600" @checked(old('enabled', $state['enabled'] ?? false))>
          </label>

          <label class="flex flex-col gap-1.5 text-sm">
            <span class="font-semibold">Customer-facing message</span>
            <textarea name="message" rows="5" class="rounded-xl border border-border bg-elevated px-3 py-3" placeholder="We are upgrading systems. WhatsApp messages will still be processed.">{{ old('message', $state['message'] ?? '') }}</textarea>
          </label>

          <label class="flex flex-col gap-1.5 text-sm">
            <span class="font-semibold">Expected back (optional)</span>
            <input type="datetime-local" name="until" value="{{ old('until', filled($state['until'] ?? null) ? \Illuminate\Support\Carbon::parse($state['until'])->format('Y-m-d\\TH:i') : '') }}" class="rounded-xl border border-border bg-elevated px-3 py-2.5">
          </label>
        </div>

        <div class="rounded-2xl border border-dashed border-emerald-300/70 bg-emerald-50/50 p-4 text-sm text-emerald-950">
          <p class="font-semibold">Always stays on</p>
          <ul class="mt-2 list-disc space-y-1 pl-5 text-emerald-900/80">
            <li>Admin panel (`/admin`)</li>
            <li>Health check (`/up`)</li>
            <li>Queue workers / scheduler processes (module flags control work)</li>
          </ul>
          <p class="mt-4 font-semibold">Always locked when maintenance is ON</p>
          <ul class="mt-2 list-disc space-y-1 pl-5 text-emerald-900/80">
            <li>Customer login / signup / dashboard UI</li>
            <li>Customer API (unless enabled below)</li>
          </ul>
        </div>
      </div>
    </section>

    <section class="rounded-[24px] border border-border bg-elevated p-5">
      <div class="flex flex-wrap items-end justify-between gap-2">
        <div>
          <h2 class="text-lg font-bold text-text-primary">Keep-alive modules</h2>
          <p class="text-sm text-text-subtle">Checked = continue during maintenance. Unchecked = pause that module’s jobs/schedulers.</p>
        </div>
      </div>

      <div class="mt-4 grid gap-3 md:grid-cols-2">
        @foreach ($modules as $key => $meta)
          @php
            $checked = (bool) ($state['modules'][$key] ?? $meta['default']);
            if (old('modules.'.$key) !== null) {
              $checked = (string) old('modules.'.$key) === '1';
            }
          @endphp
          <label class="flex cursor-pointer gap-3 rounded-2xl border border-border bg-surface p-4 transition hover:border-green-400">
            <input type="hidden" name="modules[{{ $key }}]" value="0">
            <input type="checkbox" name="modules[{{ $key }}]" value="1" class="mt-1 size-4 accent-green-600" @checked($checked)>
            <span>
              <span class="block text-sm font-semibold text-text-primary">{{ $meta['label'] }}</span>
              <span class="mt-1 block text-xs leading-relaxed text-text-subtle">{{ $meta['description'] }}</span>
            </span>
          </label>
        @endforeach
      </div>
    </section>

    <button class="rounded-lg bg-green-500 px-5 py-2.5 text-sm font-semibold text-white hover:opacity-90">
      Save maintenance settings
    </button>
  </form>
</x-admin.layout>
