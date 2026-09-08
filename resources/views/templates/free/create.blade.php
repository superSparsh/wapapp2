@php
  $isEdit = $message instanceof \App\Models\InteractiveMessage;
  $content = $content ?? [];
  $type = old('type', $message?->type ?? 'button');
  $listSections = old('list_sections', $content['list_sections'] ?? [['title' => '', 'rows' => [['title' => '', 'description' => '']]]]);
  $previewDefaults = $previewData ?? [
    'body' => $content['body'] ?? '',
    'footer' => $content['footer'] ?? '',
    'buttons' => $content['buttons'] ?? [],
    'header_type' => 'none',
    'header_text' => '',
    'header_image' => null,
  ];
@endphp

<x-layouts.app :title="($isEdit ? 'Edit' : 'Create') . ' Free Template - WapApp'" active="templates.index">
  <div class="flex flex-col bg-surface" data-template-builder data-template-step="free" data-preview-defaults='@json($previewDefaults)'>
    <div class="flex flex-col gap-4 p-4">
      <div class="flex flex-col gap-1">
        <h1 class="fd-page-title">{{ $isEdit ? 'Edit Free Template' : 'Create Free Template' }}</h1>
        <p class="fd-page-note">Interactive messages for inbox and chatbot flows — button, list, product, or flow types.</p>
      </div>
    </div>

    <div class="flex flex-col gap-6 px-4 pb-4 lg:flex-row lg:items-start lg:justify-between">
      <form
        method="post"
        action="{{ $isEdit ? route('templates.free.update', $message) : route('templates.free.store') }}"
        class="min-w-0 w-full max-w-[725px] flex flex-col gap-4 rounded-lg bg-elevated p-4"
        id="free-template-form"
      >
        @csrf
        @if ($isEdit)
          @method('PUT')
        @endif

        <div class="flex flex-col gap-3">
          <label for="free_name" class="fd-label">Template Name<span class="text-[red]">*</span></label>
          <input id="free_name" name="name" type="text" value="{{ old('name', $message?->name) }}" class="fd-input w-full rounded-xl border border-border bg-elevated p-3.5" required>
        </div>

        <div class="flex flex-col gap-3">
          <label for="free_type" class="fd-label">Type<span class="text-[red]">*</span></label>
          <x-ui.select id="free_type" name="type" variant="default" class="w-full">
            @foreach (['button' => 'Button', 'list' => 'List', 'product' => 'Product', 'flow' => 'Flow'] as $value => $label)
              <option value="{{ $value }}" @selected($type === $value)>{{ $label }}</option>
            @endforeach
          </x-ui.select>
        </div>

        <div class="flex flex-col gap-3">
          <label for="body_text" class="fd-label">Message Body<span class="text-[red]">*</span></label>
          <textarea id="body_text" name="body" rows="6" class="fd-input w-full rounded-xl border border-border p-3.5" required>{{ old('body', $content['body'] ?? '') }}</textarea>
        </div>

        <div class="flex flex-col gap-3">
          <label for="footer_text" class="fd-label">Footer</label>
          <textarea id="footer_text" name="footer" rows="2" class="fd-input w-full rounded-xl border border-border p-3.5">{{ old('footer', $content['footer'] ?? '') }}</textarea>
        </div>

        <div id="free-type-sections" class="flex flex-col gap-4">
          <div id="free-section-button" @class(['flex flex-col gap-3', 'hidden' => $type !== 'button'])>
            <div id="template-buttons-root" data-saved-buttons='@json(old('buttons', $content['buttons'] ?? []))' class="flex flex-col gap-3">
              <input type="hidden" id="button_mode" name="button_mode" value="quick_reply">
              <p class="text-sm font-semibold text-text-body">Quick Reply Buttons (max 3)</p>
              <div id="template-button-rows" class="flex flex-col gap-3"></div>
              <button type="button" id="add-template-button" class="fd-btn-sm w-fit rounded border border-green-500 px-4 py-2 text-green-500">+ Add Quick Reply</button>
            </div>
          </div>

          <div id="free-section-list" @class(['flex flex-col gap-3', 'hidden' => $type !== 'list'])>
            <label for="list_button_text" class="fd-label">List button text<span class="text-[red]">*</span></label>
            <input id="list_button_text" name="list_button_text" value="{{ old('list_button_text', $content['list_button_text'] ?? 'View options') }}" maxlength="20" class="fd-input w-full rounded-xl border border-border p-3.5">
            <p class="text-sm font-semibold text-text-body">Sections</p>
            <div id="free-list-sections" class="flex flex-col gap-3">
              @foreach ($listSections as $sectionIndex => $section)
                <div class="flex flex-col gap-2 rounded-lg border border-divider p-3" data-list-section>
                  <input name="list_sections[{{ $sectionIndex }}][title]" value="{{ $section['title'] ?? '' }}" placeholder="Section title *" required class="fd-input rounded-xl border border-border p-3">
                  <div data-list-rows class="flex flex-col gap-2">
                    @foreach ($section['rows'] ?? [['title' => '', 'description' => '']] as $rowIndex => $row)
                      <div class="grid grid-cols-1 gap-2 md:grid-cols-2" data-list-row>
                        <input name="list_sections[{{ $sectionIndex }}][rows][{{ $rowIndex }}][title]" value="{{ $row['title'] ?? '' }}" placeholder="Row title *" required class="fd-input rounded-xl border border-border p-3">
                        <input name="list_sections[{{ $sectionIndex }}][rows][{{ $rowIndex }}][description]" value="{{ $row['description'] ?? '' }}" placeholder="Description" class="fd-input rounded-xl border border-border p-3">
                      </div>
                    @endforeach
                  </div>
                  <button type="button" data-add-list-row class="fd-btn-sm w-fit text-sm text-green-500">+ Add row</button>
                </div>
              @endforeach
            </div>
            <button type="button" id="free-add-list-section" class="fd-btn-sm w-fit rounded border border-green-500 px-4 py-2 text-green-500">+ Add section</button>
          </div>

          <div id="free-section-product" @class(['flex flex-col gap-3', 'hidden' => $type !== 'product'])>
            <label for="catalog_id" class="fd-label">Catalog ID<span class="text-[red]">*</span></label>
            <input id="catalog_id" name="catalog_id" value="{{ old('catalog_id', $content['catalog_id'] ?? '') }}" class="fd-input w-full rounded-xl border border-border p-3.5" placeholder="WhatsApp catalog ID">
            <label for="product_retailer_id" class="fd-label">Product retailer ID<span class="text-[red]">*</span></label>
            <input id="product_retailer_id" name="product_retailer_id" value="{{ old('product_retailer_id', $content['product_retailer_id'] ?? '') }}" class="fd-input w-full rounded-xl border border-border p-3.5">
          </div>

          <div id="free-section-flow" @class(['flex flex-col gap-3', 'hidden' => $type !== 'flow'])>
            <label for="flow_id" class="fd-label">WhatsApp Flow<span class="text-[red]">*</span></label>
            <x-ui.select id="flow_id" name="flow_id" variant="default" class="w-full">
              <option value="">Select flow</option>
              @foreach ($whatsappFlows ?? [] as $flow)
                <option
                  value="{{ $flow->uuid }}"
                  @selected(
                    (string) old('flow_id', $content['flow_id'] ?? '') === (string) $flow->uuid
                    || (string) old('flow_id', $content['flow_id'] ?? '') === (string) $flow->id
                  )
                >{{ $flow->name }}</option>
              @endforeach
            </x-ui.select>
            <label for="flow_cta" class="fd-label">Flow CTA button text<span class="text-[red]">*</span></label>
            <input id="flow_cta" name="flow_cta" value="{{ old('flow_cta', $content['flow_cta'] ?? 'Open') }}" maxlength="20" class="fd-input w-full rounded-xl border border-border p-3.5">
          </div>
        </div>

        <div class="flex justify-end gap-3">
          <a href="{{ route('templates.index', ['tab' => 'free']) }}" class="fd-btn rounded border border-border px-4 py-3">Cancel</a>
          <button type="submit" class="fd-btn rounded bg-green-500 px-4 py-3 text-primary-2">{{ $isEdit ? 'Update' : 'Create' }}</button>
        </div>
      </form>

      <div class="w-full shrink-0 lg:w-[425px]">
        <x-templates.phone-preview>
          <x-templates.message-preview-bubble :preview-data="$previewDefaults" live />
        </x-templates.phone-preview>
      </div>
    </div>
  </div>
</x-layouts.app>
