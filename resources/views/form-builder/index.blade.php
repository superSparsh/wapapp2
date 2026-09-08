@php
    $statStyles = [
        'bg-[rgba(156,163,175,0.1)] text-text-muted',
        'bg-[rgba(16,185,129,0.1)] text-stat-emerald',
        'bg-[rgba(59,130,246,0.1)] text-stat-blue',
        'bg-[rgba(239,68,68,0.1)] text-danger',
    ];
@endphp

<x-layouts.app title="Form Builder - WapApp" active="form-builder.index">
  <div class="flex flex-col bg-surface">
    <div class="flex flex-col gap-4 p-4">
      <div class="flex flex-col gap-1">
        <h1 class="fd-page-title text-2xl">Form Builder</h1>
        <p class="fd-page-note">Create and manage signup forms for lead generation.</p>
      </div>

      <div class="flex flex-wrap items-center justify-between gap-3">
        <form method="get" action="{{ route('form-builder.index') }}" class="flex w-full max-w-[550px] items-center gap-3 rounded-lg bg-elevated p-3">
          <img src="{{ asset('images/form-builder/search.svg') }}" alt="" class="size-5 shrink-0" width="20" height="20">
          <input type="search" name="q" value="{{ $search ?? '' }}" placeholder="Search forms..." class="fd-filter-placeholder min-w-0 flex-1 bg-transparent text-sm font-medium leading-[1.4] text-text-body/60 focus:outline-none">
        </form>

        <div class="flex flex-wrap items-center gap-2">
          <a
            href="{{ route('form-builder.index', array_filter(['q' => $search ?? '', 'page' => 1, 'refresh' => 1])) }}"
            class="fd-btn inline-flex items-center justify-center gap-3 rounded border border-border-light bg-elevated px-4 py-3 text-sm font-semibold leading-[1.5] text-green-500 transition-colors hover:bg-surface"
          >
            <img src="{{ asset('images/form-builder/refresh.svg') }}" alt="" class="size-4 shrink-0" width="16" height="16">
            Refresh
          </a>
          <a
            href="{{ route('form-builder.create') }}"
            class="fd-btn inline-flex items-center justify-center gap-2 rounded bg-green-500 px-4 py-3 text-sm font-semibold leading-[1.5] text-primary-2 transition-colors hover:opacity-90"
          >
            <img src="{{ asset('images/form-builder/add.svg') }}" alt="" class="size-5 shrink-0" width="20" height="20">
            Create Form
          </a>
        </div>
      </div>
    </div>

    <section class="p-4 pt-0">
      @if (session('status'))
        <div class="mb-4 rounded-xl border border-green-500/30 bg-green-50 px-4 py-3 text-sm font-medium text-green-700">
          {{ session('status') }}
        </div>
      @endif

      @if (empty($rows))
        <div class="flex flex-col items-center justify-center gap-4 rounded-xl bg-elevated p-14 text-center">
          <div class="relative flex size-16 shrink-0 items-center justify-center overflow-hidden rounded-full bg-[#f2a356]">
            <img src="{{ asset('images/form-builder/field-icon.png') }}" alt="" class="size-8" width="32" height="32">
          </div>
          <div class="flex flex-col gap-1">
            <h3 class="text-xl font-semibold text-text-primary">No forms yet</h3>
            <p class="text-sm text-text-muted">Create your first signup form to start collecting leads.</p>
          </div>
          <a
            href="{{ route('form-builder.create') }}"
            class="fd-btn inline-flex items-center justify-center gap-2 rounded bg-green-500 px-6 py-3 text-sm font-semibold leading-[1.5] text-primary-2 transition-colors hover:opacity-90"
          >
            <img src="{{ asset('images/form-builder/add.svg') }}" alt="" class="size-5 shrink-0" width="20" height="20">
            Create Your First Form
          </a>
        </div>
      @else
        <x-ui.data-table
          :headers="['SI. No', 'Form Name', 'Form URL', 'Communication Status', 'Status', 'En/Disable', 'Actions']"
          :total="$pagination['total']"
          :per-page="$pagination['per_page']"
          :pages="$pagination['pages']"
          :current="$pagination['current']"
        >
          @foreach ($rows as $row)
            @php
                $statsArr = [
                    $row['stats']['sent'] . '/' . $row['stats']['total'] . ' Sent',
                    $row['stats']['read'] . '/' . $row['stats']['total'] . ' Read',
                    $row['stats']['delivered'] . '/' . $row['stats']['total'] . ' Delivered',
                    $row['stats']['failed'] . '/' . $row['stats']['total'] . ' Failed',
                ];
            @endphp
            <tr class="bg-elevated">
              <td class="fd-table-cell w-[54px] p-2 align-middle">{{ $row['serial'] }}</td>
              <td class="w-[240px] p-2 align-middle">
                <p class="text-[13px] font-semibold leading-[1.5] text-text-subtle">{{ $row['name'] }}</p>
                <p class="fd-table-cell text-[13px]">Created at: {{ $row['created_at'] }}</p>
              </td>
              <td class="w-[220px] p-2 align-middle">
                <a href="{{ $row['url'] }}" target="_blank" class="text-xs leading-[1.5] text-text-body underline">
                  {{ $row['url'] }}
                </a>
              </td>
              <td class="w-[220px] p-2 align-middle">
                <div class="flex flex-wrap content-center items-center justify-center gap-2.5">
                  @foreach ($statsArr as $i => $stat)
                    <span class="inline-flex items-center justify-center rounded px-2 py-1 text-[10px] font-medium leading-[1.2] {{ $statStyles[$i] }}">{{ $stat }}</span>
                  @endforeach
                </div>
              </td>
              <td class="p-2 text-center align-middle">
                <span
                  data-form-status-chip="{{ $row['uuid'] }}"
                  class="inline-flex items-center justify-center rounded px-2 py-1 text-[10px] font-medium leading-[1.2] {{ $row['status_class'] }}"
                >
                  {{ $row['status'] }}
                </span>
              </td>
              <td class="p-2 text-center align-middle">
                <div class="inline-flex justify-center">
                  <form
                    method="post"
                    action="{{ $row['toggle_url'] }}"
                    data-form-toggle
                    data-form-uuid="{{ $row['uuid'] }}"
                  >
                    @csrf
                    <x-ui.toggle-switch
                      :active="$row['is_active']"
                      :submit="true"
                      aria-label="En/Disable form"
                      data-form-toggle-switch
                    />
                  </form>
                </div>
              </td>
              <td class="relative p-2 align-middle">
                <div class="flex items-center justify-center gap-6">
                  <a href="{{ $row['edit_url'] }}" class="flex size-5 items-center justify-center" aria-label="Edit">
                    <img src="{{ asset('images/form-builder/edit.svg') }}" alt="" class="size-5" width="20" height="20">
                  </a>
                  <button type="button" class="flex size-5 items-center justify-center" aria-label="More actions" data-form-menu="{{ $row['uuid'] }}">
                    <img src="{{ asset('images/form-builder/more.svg') }}" alt="" class="size-5 rotate-90" width="20" height="20">
                  </button>
                </div>

                <div data-form-menu-panel="{{ $row['uuid'] }}" class="absolute right-2 top-full z-10 hidden w-[160px] flex-col rounded border border-border-light bg-elevated shadow-[0px_4px_12px_rgba(0,0,0,0.08)]">
                  <a href="{{ route('form-builder.statistics', $row['uuid']) }}" class="flex w-full items-center gap-3 px-2 py-2.5 text-left text-xs leading-[1.5] text-text-body hover:bg-surface">
                    <img src="{{ asset('images/campaigns/chart.svg') }}" alt="" class="size-[18px] shrink-0" width="18" height="18">
                    Statistics
                  </a>
                  <button type="button" data-copy-url="{{ $row['copy_url'] }}" class="flex w-full items-center gap-3 px-2 py-2.5 text-left text-xs leading-[1.5] text-text-body hover:bg-surface">
                    <img src="{{ asset('images/campaigns/copy.svg') }}" alt="" class="size-[18px] shrink-0" width="18" height="18">
                    <span data-copy-label>Copy Link</span>
                  </button>
                  <form
                    method="post"
                    action="{{ $row['delete_url'] }}"
                    data-confirm="Delete this form? This cannot be undone."
                    data-confirm-title="Delete form"
                    data-confirm-label="Delete"
                    data-confirm-variant="danger"
                  >
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="flex w-full items-center gap-3 px-2 py-2.5 text-left text-xs leading-[1.5] text-red-500 hover:bg-surface">
                      <img src="{{ asset('images/form-builder/trash.svg') }}" alt="" class="size-[18px] shrink-0" width="18" height="18">
                      Delete
                    </button>
                  </form>
                </div>
              </td>
            </tr>
          @endforeach
        </x-ui.data-table>
      @endif
    </section>
  </div>
</x-layouts.app>

<script>
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('[data-form-menu]')?.forEach(btn => {
    btn.addEventListener('click', (e) => {
      e.stopPropagation();
      const uuid = btn.dataset.formMenu;
      document.querySelectorAll('[data-form-menu-panel]')?.forEach(p => {
        if (p.getAttribute('data-form-menu-panel') !== uuid) {
          p.classList.add('hidden');
        }
      });
      document.querySelector(`[data-form-menu-panel="${uuid}"]`)?.classList.toggle('hidden');
    });
  });

  document.addEventListener('click', () => {
    document.querySelectorAll('[data-form-menu-panel]')?.forEach(p => p.classList.add('hidden'));
  });

  document.querySelectorAll('[data-copy-url]')?.forEach(btn => {
    btn.addEventListener('click', async (e) => {
      e.preventDefault();
      e.stopPropagation();
      const label = btn.querySelector('[data-copy-label]');
      const original = label?.textContent || 'Copy Link';
      try {
        await navigator.clipboard.writeText(btn.dataset.copyUrl || '');
        if (label) label.textContent = 'Copied!';
        setTimeout(() => {
          if (label) label.textContent = original;
        }, 1500);
      } catch (_) {
        if (label) label.textContent = 'Failed';
        setTimeout(() => {
          if (label) label.textContent = original;
        }, 1500);
      }
    });
  });

  document.querySelectorAll('[data-form-toggle]')?.forEach((form) => {
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      if (form.dataset.busy === '1') return;
      form.dataset.busy = '1';

      const switchBtn = form.querySelector('[data-form-toggle-switch]');
      const chip = document.querySelector(`[data-form-status-chip="${form.dataset.formUuid}"]`);
      const token = form.querySelector('input[name="_token"]')?.value || '';

      try {
        const response = await fetch(form.action, {
          method: 'POST',
          headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': token,
          },
          body: new FormData(form),
          credentials: 'same-origin',
        });

        if (!response.ok) {
          throw new Error('Toggle failed');
        }

        const data = await response.json();
        const active = Boolean(data.is_active);

        if (switchBtn instanceof HTMLElement) {
          switchBtn.setAttribute('aria-checked', active ? 'true' : 'false');
          switchBtn.classList.toggle('bg-green-500', active);
          switchBtn.classList.toggle('bg-green-50', !active);
          const knob = switchBtn.querySelector('span');
          if (knob instanceof HTMLElement) {
            knob.classList.toggle('left-[24px]', active);
            knob.classList.toggle('left-[2px]', !active);
          }
        }

        if (chip instanceof HTMLElement) {
          chip.textContent = data.status_label || (active ? 'Active' : 'Inactive');
          chip.className = active
            ? 'inline-flex items-center justify-center rounded px-2 py-1 text-[10px] font-medium leading-[1.2] bg-green-50 text-green-500'
            : 'inline-flex items-center justify-center rounded px-2 py-1 text-[10px] font-medium leading-[1.2] bg-blue-50 text-primary-2';
        }
      } catch (_) {
        window.location.reload();
      } finally {
        form.dataset.busy = '0';
      }
    });
  });
});
</script>
