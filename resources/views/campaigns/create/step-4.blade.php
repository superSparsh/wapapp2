@php
  use App\Domains\Campaigns\Services\CampaignTemplateParamsResolver;
  use App\Domains\Templates\Support\TemplateVariableSyntax;

  $variableNames = $variableNames ?? [];
  $customVariableNames = $customVariableNames ?? [];
  $hasCustomVars = (bool) ($hasCustomVars ?? false);
  $variableRows = $variableRows ?? [];
  $variablePaginator = $variablePaginator ?? null;
@endphp

<x-campaigns.create-layout
  :step="4"
  :campaign-name="$wizardData['name'] ?? 'New Campaign'"
  previous-route="{{ route('campaigns.create.step', 3) }}"
  next-route="{{ route('campaigns.create.save', 4) }}"
  next-label="Next step"
>
  @if ($hasCustomVars)
    <x-slot:modals>
      @include('components.campaigns.import-variables-modal', [
        'customVariableNames' => $customVariableNames,
      ])
    </x-slot:modals>
  @endif

  <form
    id="campaign-wizard-form"
    method="POST"
    action="{{ route('campaigns.create.save', 4) }}"
    data-campaign-wizard
    data-campaign-variables-grid
    data-validate-form
    data-preview-url="{{ url('/campaigns/create/template-preview') }}"
    data-template-id="{{ $template?->id }}"
    data-variables-save-url="{{ route('campaigns.create.variables.save') }}"
    data-variables-import-url="{{ route('campaigns.create.variables.import') }}"
    class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_458px]"
  >
    @csrf
    <div class="flex flex-col gap-6">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <p class="fd-filter-label font-semibold text-text-primary">Variables</p>
        @if ($hasCustomVars)
          <button
            type="button"
            data-open-modal="import-campaign-variables"
            class="fd-btn inline-flex items-center justify-center rounded-lg border border-green-500 bg-elevated px-4 py-2 text-sm font-semibold text-green-500 transition-colors hover:bg-surface"
          >
            Import Data
          </button>
        @endif
      </div>

      @if ($variableNames === [])
        <div class="flex flex-col gap-3 rounded-xl border border-dashed border-border bg-elevated p-8">
          <p class="text-sm font-medium text-text-subtle">There are no variables in this template.</p>
          <p class="text-xs text-text-muted">You can continue to Schedule &amp; Confirm.</p>
          <a href="{{ route('campaigns.create.step', 3) }}" class="text-sm font-semibold text-green-500 hover:underline">Change template</a>
        </div>
      @elseif ($variableRows === [])
        <div class="flex flex-col gap-3 rounded-xl border border-dashed border-border bg-elevated p-8">
          <p class="text-sm font-medium text-text-subtle">No recipients found for this audience.</p>
          <p class="text-xs text-text-muted">Go back and choose a list with subscribed contacts.</p>
          <a href="{{ route('campaigns.create.step', 2) }}" class="text-sm font-semibold text-green-500 hover:underline">Change audience</a>
        </div>
      @else
        <div class="overflow-hidden rounded-xl border border-border-light bg-elevated">
          <div class="overflow-x-auto">
            <table class="min-w-full border-collapse text-left text-sm" data-campaign-variables-table>
              <thead class="sticky top-0 z-10 bg-muted-surface">
                <tr>
                  <th class="whitespace-nowrap border-b border-border px-3 py-3 font-semibold text-text-primary">WhatsApp number</th>
                  @foreach ($variableNames as $name)
                    <th class="min-w-[160px] whitespace-nowrap border-b border-border px-3 py-3 font-semibold text-text-primary">
                      <code class="font-mono text-green-600">{{ TemplateVariableSyntax::placeholder($name) }}</code>
                    </th>
                  @endforeach
                </tr>
              </thead>
              <tbody>
                @foreach ($variableRows as $rowIndex => $row)
                  <tr class="border-b border-border/60 last:border-b-0" data-recipient-row data-recipient-id="{{ $row['recipient_id'] }}" data-phone="{{ $row['phone'] }}">
                    <td class="whitespace-nowrap px-3 py-2.5 font-medium text-text-muted">
                      {{ $row['phone'] }}
                      <input type="hidden" name="recipients[{{ $row['recipient_id'] }}][recipient_id]" value="{{ $row['recipient_id'] }}">
                    </td>
                    @foreach ($variableNames as $name)
                      @php
                        $value = $row['values'][$name] ?? '';
                        $source = $row['sources'][$name] ?? '';
                        $isNameVar = in_array($name, CampaignTemplateParamsResolver::CONTACT_NAME_VARS, true) || $name === 'phone';
                        $isCustom = in_array($name, $customVariableNames, true);
                      @endphp
                      <td class="px-3 py-2 align-top">
                        <input
                          type="text"
                          name="recipients[{{ $row['recipient_id'] }}][values][{{ $name }}]"
                          value="{{ $value }}"
                          maxlength="60"
                          data-campaign-variable
                          data-variable-name="{{ $name }}"
                          data-row-index="{{ $rowIndex }}"
                          @if ($isCustom) data-custom-variable @endif
                          class="w-full min-w-[140px] rounded-lg border border-border bg-surface px-3 py-2 text-sm font-medium leading-[1.4] text-text-muted outline-none focus:border-green-500"
                          placeholder="{{ $name }}"
                        >
                        @if ($rowIndex === 0 && $isCustom)
                          <label class="mt-1.5 flex items-center gap-1.5 text-[11px] text-text-subtle">
                            <input
                              type="checkbox"
                              data-same-value-for-all
                              data-variable-name="{{ $name }}"
                              class="size-3.5 rounded border-border text-green-500 focus:ring-green-500"
                            >
                            Same value for all
                          </label>
                        @elseif ($source === 'contact' || ($isNameVar && $value !== ''))
                          <p class="mt-1 text-[11px] text-text-subtle">(from contact)</p>
                        @elseif ($source === 'list')
                          <p class="mt-1 text-[11px] text-text-subtle">(from list)</p>
                        @endif
                      </td>
                    @endforeach
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>

          @if ($variablePaginator)
            <x-ui.table-pagination :paginator="$variablePaginator" />
          @endif
        </div>

        <p class="text-xs text-text-subtle">
          Name fields are filled from each contact automatically. Custom values can be typed here or imported via CSV. Unsubscribe tokens are applied at send time.
        </p>
      @endif
    </div>

    <x-templates.phone-preview
      title="Message Preview"
      subtitle="Template preview message look like"
      size="compact"
    >
      <x-templates.message-preview-bubble :preview-data="$previewData" size="compact" scroll-body />
    </x-templates.phone-preview>
  </form>
</x-campaigns.create-layout>
