<x-profile.layout title="Data Deletion - WapApp" headerTitle="Data Deletion" active="profile.data-deletion">
  @if (session('status'))
    <div class="mx-4 rounded-lg bg-green-50 p-3 text-sm text-primary-2">{{ session('status') }}</div>
  @endif

  @if ($errors->any())
    <div class="mx-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-600">
      {{ $errors->first() }}
    </div>
  @endif

  <div class="flex flex-col gap-1 p-4">
    <h1 class="text-2xl font-bold leading-[1.5] text-text-primary">Data Deletion</h1>
    <p class="max-w-[854px] text-sm font-normal leading-[1.4] text-text-subtle opacity-50">
      Export or schedule deletion of your tenant data by module and age.
    </p>
  </div>

  <section class="flex flex-col gap-4 p-4 pt-0">
    <div class="grid gap-4 xl:grid-cols-2">
      <form
        method="POST"
        action="{{ route('profile.data-deletion.schedule') }}"
        class="flex flex-col gap-4 rounded-[20px] bg-[#ffebeb] p-5"
        data-confirm="Schedule data deletion? This action cannot be undone once the scheduled time arrives."
        data-confirm-title="Schedule data deletion"
        data-confirm-label="Schedule deletion"
        data-confirm-variant="danger"
      >
        @csrf
        <h2 class="text-2xl font-bold text-text-primary">Schedule Data Deletion</h2>

        <x-profile.info-banner title="Warning" variant="warning">
          <ul class="list-disc pl-[21px]">
            <li>This action is irreversible. Once data is deleted, it cannot be recovered.</li>
          </ul>
        </x-profile.info-banner>

        <div class="flex flex-col gap-6 rounded-xl border border-border-light bg-elevated p-4">
          <div class="flex flex-col gap-2">
            <label for="schedule_data_age" class="text-sm font-semibold text-text-primary">Select Data Age<span class="text-[red]">*</span></label>
            <select id="schedule_data_age" name="data_age" required class="w-full rounded-xl border border-border bg-elevated p-3.5 text-sm font-medium text-text-primary">
              @foreach ($dataAgeOptions as $value => $label)
                <option value="{{ $value }}">{{ $label }}</option>
              @endforeach
            </select>
          </div>

          <div class="flex flex-col gap-2">
            <p class="text-sm font-semibold text-text-primary">Select Modules</p>
            <div class="flex flex-wrap gap-3">
              @foreach ($modules as $key => $label)
                <label class="flex items-center gap-2">
                  <input type="checkbox" name="modules[]" value="{{ $key }}" checked class="size-4">
                  <span class="text-sm font-medium text-text-body">{{ $label }}</span>
                </label>
              @endforeach
            </div>
          </div>

          <div class="flex flex-col gap-2">
            <label for="schedule_delay" class="text-sm font-semibold text-text-primary">Schedule Deletion<span class="text-[red]">*</span></label>
            <select id="schedule_delay" name="schedule_delay" required class="w-full rounded-xl border border-border bg-elevated p-3.5 text-sm font-medium text-text-primary">
              @foreach ($scheduleOptions as $value => $label)
                <option value="{{ $value }}">{{ $label }}</option>
              @endforeach
            </select>
          </div>

          <label class="flex items-start gap-2">
            <input type="checkbox" name="export_before_delete" value="1" checked class="mt-1 size-4">
            <span>
              <span class="block text-sm font-semibold text-text-primary">Export data before deletion</span>
              <span class="block text-xs text-text-muted">Recommended: Creates a downloadable backup before deleting</span>
            </span>
          </label>
        </div>

        <button type="submit" class="fd-btn flex h-[45px] w-full items-center justify-center gap-2 rounded-lg bg-[rgba(255,0,0,0.2)] px-4 py-3 text-sm font-semibold text-[red]">
          Schedule Deletion
        </button>
      </form>

      <form method="POST" action="{{ route('profile.data-deletion.export') }}" class="flex flex-col gap-4 rounded-[20px] bg-[#ebebff] p-5">
        @csrf
        <div class="flex flex-col gap-1">
          <h2 class="text-2xl font-bold text-text-primary">Export Data Only</h2>
          <p class="text-sm text-text-muted">Export your data without deleting it. Downloads remain available for {{ $exportRetentionDays }} days.</p>
        </div>

        <div class="flex flex-col gap-6 rounded-xl border border-border-light bg-elevated p-4">
          <div class="flex flex-col gap-2">
            <label for="export_data_age" class="text-sm font-semibold text-text-primary">Select Data Age<span class="text-[red]">*</span></label>
            <select id="export_data_age" name="data_age" required class="w-full rounded-xl border border-border bg-elevated p-3.5 text-sm font-medium text-text-primary">
              @foreach ($dataAgeOptions as $value => $label)
                <option value="{{ $value }}">{{ $label }}</option>
              @endforeach
            </select>
          </div>

          <div class="flex flex-col gap-2">
            <p class="text-sm font-semibold text-text-primary">Select Modules</p>
            <div class="flex flex-wrap gap-3">
              @foreach ($modules as $key => $label)
                <label class="flex items-center gap-2">
                  <input type="checkbox" name="modules[]" value="{{ $key }}" checked class="size-4">
                  <span class="text-sm font-medium text-text-body">{{ $label }}</span>
                </label>
              @endforeach
            </div>
          </div>
        </div>

        <button type="submit" class="fd-btn flex h-[45px] w-full items-center justify-center gap-2 rounded-lg bg-[rgba(0,0,255,0.2)] px-4 py-3 text-sm font-semibold text-[blue]">
          Export Data
        </button>
      </form>
    </div>

    <div class="flex flex-col gap-4 rounded-lg bg-elevated p-4">
      <h2 class="text-xl font-semibold text-text-primary">Scheduled Deletions</h2>
      <div class="overflow-x-auto">
        <table class="w-full min-w-[900px] text-left text-[13px]">
          <thead>
            <tr>
              <th class="p-2 font-semibold text-text-body">Scheduled For</th>
              <th class="p-2 font-semibold text-text-body">Data Filter</th>
              <th class="p-2 font-semibold text-text-body">Modules</th>
              <th class="p-2 font-semibold text-text-body">Status</th>
              <th class="p-2 font-semibold text-text-body">Actions</th>
            </tr>
          </thead>
          <tbody>
            @forelse ($schedules as $schedule)
              <tr class="border-t border-divider">
                <td class="p-2">{{ $schedule->scheduled_for?->format('M d, Y g:i A') }}</td>
                <td class="p-2">{{ $dataAgeOptions[$schedule->data_age] ?? $schedule->data_age }}</td>
                <td class="p-2">
                  <div class="flex flex-wrap gap-2">
                    @foreach ($schedule->modules as $module)
                      <span class="rounded-lg bg-green-50 px-2 py-1 text-xs text-primary-2">{{ $modules[$module] ?? $module }}</span>
                    @endforeach
                  </div>
                </td>
                <td class="p-2">{{ ucfirst($schedule->status->value) }}</td>
                <td class="p-2">
                  @if ($schedule->status->value === 'scheduled')
                    <form
                      method="POST"
                      action="{{ route('profile.data-deletion.cancel', $schedule) }}"
                      data-confirm="Cancel this scheduled data deletion?"
                      data-confirm-title="Cancel scheduled deletion"
                      data-confirm-label="Yes, cancel"
                      data-confirm-variant="danger"
                    >
                      @csrf
                      @method('DELETE')
                      <button type="submit" class="text-xs font-semibold text-[red] underline">Cancel</button>
                    </form>
                  @endif
                </td>
              </tr>
            @empty
              <tr class="border-t border-divider"><td colspan="5" class="p-4 text-center text-text-muted">No scheduled deletions.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>

    <div class="flex flex-col gap-4 rounded-lg bg-elevated p-4">
      <h2 class="text-xl font-semibold text-text-primary">Available Exports</h2>
      <div class="overflow-x-auto">
        <table class="w-full min-w-[800px] text-left text-[13px]">
          <thead>
            <tr>
              <th class="p-2 font-semibold text-text-body">Created</th>
              <th class="p-2 font-semibold text-text-body">Modules</th>
              <th class="p-2 font-semibold text-text-body">Size</th>
              <th class="p-2 font-semibold text-text-body">Status</th>
              <th class="p-2 font-semibold text-text-body">Expires</th>
              <th class="p-2 font-semibold text-text-body">Actions</th>
            </tr>
          </thead>
          <tbody>
            @forelse ($exports as $export)
              <tr class="border-t border-divider">
                <td class="p-2">{{ $export->created_at?->format('M d, Y g:i A') }}</td>
                <td class="p-2">{{ collect($export->modules)->map(fn ($m) => $modules[$m] ?? $m)->join(', ') }}</td>
                <td class="p-2">{{ $export->file_size ? number_format($export->file_size / 1024, 2).' KB' : '—' }}</td>
                <td class="p-2">{{ ucfirst($export->status->value) }}</td>
                <td class="p-2">{{ $export->expires_at?->diffForHumans() ?? '—' }}</td>
                <td class="p-2">
                  @if ($export->status->value === 'completed')
                    <a href="{{ route('profile.data-deletion.download', $export) }}" class="inline-flex rounded bg-green-50 p-1">
                      <img src="{{ asset('images/profile/download.svg') }}" alt="Download" class="size-4">
                    </a>
                  @endif
                </td>
              </tr>
            @empty
              <tr class="border-t border-divider"><td colspan="6" class="p-4 text-center text-text-muted">No exports yet.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </section>
</x-profile.layout>
