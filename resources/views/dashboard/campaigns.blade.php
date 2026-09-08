<x-layouts.app title="Total Recipient Logs - WapApp" active="dashboard">
  <div class="flex flex-col">
    <div class="flex flex-col gap-4 p-4">
      <div class="flex flex-wrap items-center justify-between gap-4">
        <div class="flex min-w-0 flex-1 flex-col gap-1">
          <p class="fd-page-note !opacity-50">Total Recipient Logs</p>
          <div class="flex flex-wrap items-center gap-3">
            <h1 class="fd-page-title">Campaign test for scheduled later</h1>
            <span class="fd-status-chip rounded-lg border border-[0.255px] border-green-500 bg-elevated px-3 py-2 text-sm font-bold text-green-500 shadow-[0px_0px_2px_rgba(0,0,0,0.08)]">
              30th November 2025
            </span>
          </div>
        </div>
        <x-ui.link-button variant="green-outline" size="toolbar" class="gap-3">
          <x-icons.nav-icon name="refresh-2" class="size-4" />
          Export to CSV
        </x-ui.link-button>
      </div>
    </div>

    <section class="bg-surface px-4 pb-4">
      <div class="overflow-hidden rounded-xl bg-elevated shadow-[0px_4px_6px_rgba(0,0,0,0.04)]">
        <div class="overflow-x-auto">
          <table class="w-full min-w-[1100px] text-left">
            <thead>
              <tr class="bg-elevated">
                <th class="fd-table-head w-[54px] p-2">SI. No</th>
                <th class="fd-table-head w-[160px] p-2">Name</th>
                <th class="fd-table-head w-[160px] p-2">WhatsApp Number</th>
                <th class="fd-table-head w-[320px] p-2">Campaign</th>
                <th class="fd-table-head w-[208px] p-2">Sent At</th>
                <th class="fd-table-head w-[208px] p-2 text-center">Status</th>
              </tr>
            </thead>
            <tbody>
              @foreach (range(1, 10) as $i)
                <tr class="border-t border-divider bg-elevated">
                  <td class="fd-table-cell p-2 pl-4">{{ str_pad($i, 2, '0', STR_PAD_LEFT) }}</td>
                  <td class="fd-table-name p-2">Akhil</td>
                  <td class="fd-table-cell p-2">25896 25698</td>
                  <td class="fd-table-cell p-2">Deployment audience list 30th november 2025</td>
                  <td class="fd-table-cell p-2">8 Sep 2025 09:26:36 PM</td>
                  <td class="p-2 text-center">
                    <x-ui.status-chip label="Sent" variant="sent" />
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
        <x-ui.table-pagination :total="25" :per-page="10" :pages="3" :current="1" />
      </div>
    </section>
  </div>
</x-layouts.app>
