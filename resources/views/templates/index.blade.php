@php
  $showPreview = $showPreview ?? false;
  $activeTab = $activeTab ?? 'approved';
  $search = $search ?? '';
  $selectedCategory = $selectedCategory ?? '';
  $selectedType = $selectedType ?? '';
  $selectedFreeType = $selectedFreeType ?? '';
  $categories = $categories ?? [];
  $types = $types ?? ['Regular', 'Draft'];
  $freeTypes = $freeTypes ?? ['button', 'list', 'product', 'flow'];
  $templates = $templates ?? [];
  $freeTemplates = $freeTemplates ?? [];
  $isFreeTab = $activeTab === 'free';
  $pagination = $pagination ?? ['total' => count($templates), 'per_page' => 10, 'current' => 1, 'pages' => 1];
  $templateCount = $isFreeTab ? count($freeTemplates) : $pagination['total'];
  $tabQuery = array_filter([
    'q' => $search !== '' ? $search : null,
    'category' => ! $isFreeTab && $selectedCategory !== '' ? $selectedCategory : null,
    'type' => ! $isFreeTab && $selectedType !== '' ? $selectedType : null,
    'free_type' => $isFreeTab && $selectedFreeType !== '' ? $selectedFreeType : null,
    'sort' => ($currentSort ?? 'updated_at') !== 'updated_at' ? ($currentSort ?? null) : null,
    'direction' => ($currentDirection ?? 'desc') !== 'desc' ? ($currentDirection ?? null) : null,
  ]);
@endphp

<x-layouts.app title="Templates - WapApp" active="templates.index">
  <div class="flex flex-col bg-surface" @unless($isFreeTab) data-templates-index data-bulk-destroy-url="{{ route('templates.bulk-destroy') }}" data-statuses-url="{{ route('templates.api.statuses') }}" data-status-poll-ms="15000" @endunless>
    <div class="flex flex-col gap-4 p-4">
      <div class="flex flex-col gap-1">
        <h1 class="fd-page-title">{{ $isFreeTab ? 'Free Template Messages' : 'Templates' }}</h1>
        @if ($isFreeTab)
          <p class="fd-page-note max-w-[854px]">
            <strong>What is a Free Template Message?</strong> Interactive messages with buttons that let customers reply with one tap. Use in chatbots or inbox — not for marketing campaigns.
          </p>
        @else
          <p class="fd-page-note max-w-[854px]">
            Note: According to Meta guidelines, template approval may take up to 24 hours after submission.
          </p>
        @endif
      </div>

      <x-ui.validation-errors class="max-w-[854px]" />

      <x-ui.listing-toolbar
        :action="route('templates.index')"
        search-name="q"
        :search-value="$search"
        search-placeholder="Search"
        :current-sort="$currentSort ?? 'updated_at'"
        :current-direction="$currentDirection ?? 'desc'"
        :sort-options="[
          ['value' => 'updated_at', 'label' => 'Recently updated', 'direction' => 'desc'],
          ['value' => 'created_at', 'label' => 'Newest first', 'direction' => 'desc'],
          ['value' => 'created_at', 'label' => 'Oldest first', 'direction' => 'asc'],
          ['value' => 'name', 'label' => 'Name A–Z', 'direction' => 'asc'],
          ['value' => 'name', 'label' => 'Name Z–A', 'direction' => 'desc'],
          ['value' => 'status', 'label' => 'Status A–Z', 'direction' => 'asc'],
        ]"
        data-templates-filter-form
      >
        <x-slot:hidden>
          <input type="hidden" name="tab" value="{{ $activeTab }}">
        </x-slot:hidden>
        <x-slot:filters>
          @if ($isFreeTab)
            <div class="min-w-[140px] shrink-0">
              <x-ui.select name="free_type" variant="listing" class="min-w-[140px]" data-listing-filter>
                <option value="">Type</option>
                @foreach ($freeTypes as $freeType)
                  <option value="{{ $freeType }}" @selected($selectedFreeType === $freeType)>{{ ucfirst($freeType) }}</option>
                @endforeach
              </x-ui.select>
            </div>
          @else
            <div class="min-w-[140px] shrink-0">
              <x-ui.select name="type" variant="listing" class="min-w-[140px]" data-listing-filter>
                <option value="">Type</option>
                @foreach ($types as $typeOption)
                  <option value="{{ $typeOption }}" @selected($selectedType === $typeOption)>{{ $typeOption }}</option>
                @endforeach
              </x-ui.select>
            </div>
            <div class="min-w-[140px] shrink-0">
              <x-ui.select name="category" variant="listing" class="min-w-[140px]" data-listing-filter>
                <option value="">Category</option>
                @foreach ($categories as $category)
                  <option value="{{ $category }}" @selected($selectedCategory === $category)>{{ \App\Domains\Templates\Support\TemplateCategoryCatalog::label((string) $category) }}</option>
                @endforeach
              </x-ui.select>
            </div>
          @endif
        </x-slot:filters>
        <x-slot:actions>
          @if (! $isFreeTab)
            <div id="templates-bulk-actions" class="hidden items-center gap-3">
              <span class="text-sm text-text-muted"><span data-bulk-count>0</span> selected</span>
              <button type="button" id="templates-bulk-delete" class="fd-btn-sm rounded border border-red-500 px-4 py-2 text-sm text-red-600">Delete selected</button>
            </div>
            <x-templates.refresh-button :action="route('templates.index', array_merge($tabQuery, ['tab' => $activeTab, 'sort' => $currentSort ?? 'updated_at', 'direction' => $currentDirection ?? 'desc']))" />
          @endif
          <a
            href="{{ $isFreeTab ? route('templates.free.create') : route('templates.builder.create') }}"
            class="fd-btn inline-flex shrink-0 items-center justify-center gap-2 rounded bg-green-500 px-4 py-3 text-primary-2 transition-opacity hover:opacity-90"
          >
            <img src="{{ asset('images/icons/add-linear.svg') }}" alt="" class="size-5" width="20" height="20">
            {{ $isFreeTab ? 'Create New' : 'Create New Template' }}
          </a>
        </x-slot:actions>
      </x-ui.listing-toolbar>

      <div class="flex gap-0 border-b-2 border-blue-50">
        <a href="{{ route('templates.index', array_merge($tabQuery, ['tab' => 'free', 'sort' => $currentSort ?? 'updated_at', 'direction' => $currentDirection ?? 'desc'])) }}" @class(['fd-tab px-9 py-2.5 transition-colors', 'border-b-2 border-green-500 bg-green-100' => $isFreeTab, 'hover:bg-surface' => ! $isFreeTab])>Free Templates</a>
        <a href="{{ route('templates.index', array_merge($tabQuery, ['tab' => 'approved', 'sort' => $currentSort ?? 'updated_at', 'direction' => $currentDirection ?? 'desc'])) }}" @class(['fd-tab px-9 py-2.5 transition-colors', 'border-b-2 border-green-500 bg-green-100' => ! $isFreeTab, 'hover:bg-surface' => $isFreeTab])>Regular Templates</a>
      </div>
    </div>

    <section class="bg-surface p-4 pt-0">
      <div class="relative overflow-hidden rounded-xl bg-elevated shadow-[0px_4px_6px_rgba(0,0,0,0.04)]">
        <div class="overflow-x-auto">
          <table class="w-full min-w-[1100px] text-left">
            <thead>
              <tr class="bg-elevated">
                @unless ($isFreeTab)
                  <th class="fd-table-head w-10 p-2">
                    <input type="checkbox" id="templates-select-all" class="size-4 rounded border-border" aria-label="Select all templates">
                  </th>
                @endunless
                <th class="fd-table-head w-[54px] p-2">SI. No</th>
                <th class="fd-table-head w-[320px] p-2">Template Name</th>
                <th class="fd-table-head p-2">Type</th>
                @unless ($isFreeTab)
                  <th class="fd-table-head p-2">Category</th>
                  <th class="fd-table-head p-2">Status</th>
                @endunless
                <th class="fd-table-head p-2">Actions</th>
              </tr>
            </thead>
            <tbody>
              @if ($isFreeTab)
                @forelse ($freeTemplates as $template)
                  <tr class="border-t border-divider bg-elevated">
                    <td class="fd-table-cell p-2">{{ $template['serial'] }}</td>
                    <td class="w-[320px] p-2">
                      <p class="fd-table-name">{{ $template['name'] }}</p>
                      <p class="fd-table-cell">Created at: {{ $template['created_at'] }}</p>
                    </td>
                    <td class="fd-table-cell p-2">{{ $template['type'] }}</td>
                    <td class="p-2">
                      <div class="flex items-center gap-6">
                        <x-ui.table-actions :actions="['eye-view', 'edit']" :links="['eye-view' => $template['preview_url'], 'edit' => $template['edit_url']]" />
                        <form
                          method="post"
                          action="{{ $template['delete_url'] }}"
                          data-confirm="Delete this free template message?"
                          data-confirm-title="Delete free template"
                          data-confirm-label="Delete"
                        >
                          @csrf
                          @method('DELETE')
                          <button type="submit" class="flex size-5 items-center justify-center" aria-label="Delete">
                            <img src="{{ asset('images/icons/table/trash.svg') }}" alt="" class="size-5" width="20" height="20">
                          </button>
                        </form>
                      </div>
                    </td>
                  </tr>
                @empty
                  <tr class="border-t border-divider bg-elevated"><td colspan="4" class="p-8 text-center text-sm text-text-muted">No free template messages found.</td></tr>
                @endforelse
              @else
                @forelse ($templates as $template)
                  <tr class="relative border-t border-divider bg-elevated" data-template-row data-template-uuid="{{ $template['uuid'] }}" data-template-status="{{ $template['status'] }}" data-template-name="{{ $template['name'] }}">
                    <td class="p-2">
                      <input type="checkbox" class="size-4 rounded border-border template-row-checkbox" value="{{ $template['uuid'] }}" aria-label="Select {{ $template['name'] }}">
                    </td>
                    <td class="fd-table-cell p-2">{{ $template['serial'] }}</td>
                    <td class="w-[320px] p-2">
                      <p class="fd-table-name">{{ $template['name'] }}</p>
                      <p class="fd-table-cell">Created at: {{ $template['created_at'] }}</p>
                    </td>
                    <td class="p-2">
                      <x-ui.status-chip
                        :label="$template['type']"
                        :variant="$template['type_variant'] ?? 'fd-type'"
                      />
                    </td>
                    <td class="p-2">
                      <x-ui.status-chip
                        :label="$template['category']"
                        :variant="$template['category_variant'] ?? 'fd-category'"
                      />
                    </td>
                    <td class="relative p-2" data-template-status-cell>
                      <div class="flex flex-col gap-1.5">
                        <div class="flex items-center gap-2">
                          <x-ui.status-chip
                            :label="$template['status']"
                            :variant="$template['status_variant']"
                            data-template-status-chip
                          />
                          <div class="group relative @if (! ($template['error'] ?? false)) hidden @endif" data-template-rejection-wrap>
                            <button
                              type="button"
                              class="inline-flex size-5 items-center justify-center rounded-full border border-red-200 bg-red-50 text-[11px] font-bold leading-none text-red-600 hover:bg-red-100"
                              aria-label="View submission error"
                            >!</button>
                            <div class="absolute right-0 top-7 z-20 hidden w-80 rounded-xl border border-red-100 bg-elevated p-3.5 shadow-[0_10px_28px_rgba(0,0,0,0.14)] group-hover:block group-focus-within:block" data-template-rejection-popover>
                              <p class="mb-1.5 text-sm font-semibold text-red-600" data-template-rejection-title>{{ $template['rejection_title'] ?? 'Submission failed' }}</p>
                              <p class="text-xs leading-[1.5] text-text-body" data-template-rejection-text>{{ $template['rejection_reason'] ?? 'No error details were returned by WhatsApp.' }}</p>
                              <p class="mt-2 text-xs leading-[1.45] text-text-muted @if (empty($template['rejection_hint'])) hidden @endif" data-template-rejection-hint>{{ $template['rejection_hint'] ?? '' }}</p>
                              @if (! empty($template['edit_url']))
                                <a href="{{ $template['edit_url'] }}" class="mt-3 inline-flex text-xs font-semibold text-link-green hover:underline">Edit &amp; resubmit</a>
                              @endif
                            </div>
                          </div>
                        </div>
                        @if (! empty($template['error']) && ! empty($template['rejection_reason']))
                          <p class="max-w-[320px] text-[11px] leading-[1.4] text-red-600" data-template-rejection-inline title="{{ $template['rejection_reason'] }}">
                            {{ \Illuminate\Support\Str::limit($template['rejection_reason'], 180) }}
                          </p>
                        @endif
                      </div>
                    </td>
                    <td class="p-2">
                      @php
                        $actions = ['eye-view', 'copy'];
                        $links = [
                          'eye-view' => $template['preview_url'] ?? '#',
                          'copy' => $template['copy_url'] ?? '#',
                        ];
                        $methods = ['copy' => 'POST'];
                        if (! empty($template['edit_url'])) { $actions[] = 'edit'; $links['edit'] = $template['edit_url']; }
                      @endphp
                      <div class="flex items-center gap-2">
                        <x-ui.table-actions :actions="$actions" :links="$links" :methods="$methods" class="!gap-2" />
                        @if (! empty($template['delete_url']))
                        <form
                          method="post"
                          action="{{ $template['delete_url'] }}"
                          data-confirm="Delete this template?"
                          data-confirm-title="Delete template"
                          data-confirm-label="Delete"
                        >
                          @csrf
                          @method('DELETE')
                          <button type="submit" class="flex size-5 items-center justify-center" aria-label="Delete">
                            <img src="{{ asset('images/icons/table/trash.svg') }}" alt="" class="size-5" width="20" height="20">
                          </button>
                        </form>
                        @endif
                      </div>
                    </td>
                  </tr>
                @empty
                  <tr class="border-t border-divider bg-elevated"><td colspan="7" class="p-8 text-center text-sm text-text-muted">No templates found.</td></tr>
                @endforelse
              @endif
            </tbody>
          </table>
        </div>
        <x-ui.table-pagination :total="$pagination['total']" :per-page="$pagination['per_page']" :pages="$pagination['pages']" :current="$pagination['current']" />
      </div>
    </section>
  </div>

  @if ($showPreview)
    <x-templates.message-preview-modal :close-route="route('templates.index', request()->except('preview', 'code', 'draft'))" :preview-data="$previewData ?? null" />
  @endif
</x-layouts.app>
