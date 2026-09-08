@php
    $chatbots = [
        ['01', 'tittu chatbot using interactive messages', '04-08-2025 / 11:50 AM', 'Active', true],
        ['02', 'tittu chatbot using interactive messages', '04-08-2025 / 11:50 AM', 'Active', true],
        ['03', 'tittu chatbot using interactive messages', '04-08-2025 / 11:50 AM', 'Inactive', true],
        ['04', 'tittu chatbot using interactive messages', '04-08-2025 / 11:50 AM', 'Inactive', true],
        ['05', 'tittu chatbot using interactive messages', '04-08-2025 / 11:50 AM', 'Inactive', true],
    ];
@endphp

<x-layouts.app title="Chatbot Builder - WapApp" active="automation.chatbot">
  <div class="flex flex-col bg-surface">
    <div class="flex flex-col gap-4 p-4">
      <x-ui.page-header title="Chatbot">
        <x-slot:subtitle>
          <x-automation.page-note />
        </x-slot:subtitle>
      </x-ui.page-header>

      <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex w-full max-w-[550px] items-center gap-3 rounded-lg bg-elevated p-3">
          <img src="{{ asset('images/icons/sidebar/059a8053c8ef2ad2aae8a0b9c28cef8b48c5e37b.svg') }}" alt="" class="size-5 shrink-0" width="20" height="20">
          <input type="search" placeholder="Search" class="fd-filter-placeholder min-w-0 flex-1 bg-transparent focus:outline-none">
        </div>
        <div class="flex items-center gap-6">
          <x-ui.link-button variant="green-outline" size="sm" class="fd-btn gap-3 !rounded-lg px-4 py-3">
            <img src="{{ asset('images/automation/refresh.svg') }}" alt="" class="size-4" width="16" height="16">
            Refresh
          </x-ui.link-button>
          <x-ui.link-button href="{{ route('chatbot.index') }}" variant="primary" size="sm" class="fd-btn w-[118px] justify-center gap-2 !rounded px-4 py-3">
            <img src="{{ asset('images/icons/sidebar/dbfd6f4cd73e6e1ecbcca79a8be160d3f18f5172.svg') }}" alt="" class="size-5" width="20" height="20">
            Create
          </x-ui.link-button>
        </div>
      </div>
    </div>

    <section class="p-4 pt-0">
      <x-ui.data-table :headers="['SI. No', 'Chatbot Name', 'Published', 'Actions', 'En/Disable']" :total="25">
        @foreach ($chatbots as [$no, $name, $date, $status, $enabled])
          <tr class="bg-elevated">
            <td class="fd-table-cell w-[54px] p-2">{{ $no }}</td>
            <td class="w-[320px] p-2">
              <p class="fd-table-name">{{ $name }}</p>
              <p class="fd-table-cell">{{ $date }}</p>
            </td>
            <td class="p-2">
              <x-ui.status-chip :label="$status" :variant="$status === 'Active' ? 'active' : 'disabled'" />
            </td>
            <td class="p-2">
              <x-ui.table-actions
                :actions="['edit', 'trash']"
                :links="['edit' => route('chatbot.index')]"
              />
            </td>
            <td class="p-2">
              <x-ui.toggle-switch :active="$enabled" />
            </td>
          </tr>
        @endforeach
      </x-ui.data-table>
    </section>
  </div>
</x-layouts.app>
