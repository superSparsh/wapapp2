<?php

declare(strict_types=1);

namespace App\Domains\Campaigns\Services;

use App\Domains\Templates\Enums\TemplateStatus;
use App\Domains\Templates\Services\TemplatePreviewService;
use App\Domains\Templates\Services\TemplateRegistryService;
use App\Models\Campaign;
use App\Models\MailList;
use App\Models\Template;
use App\Models\WhatsappLine;

class CampaignPresenter
{
    public function __construct(
        private readonly TemplatePreviewService $previewService,
        private readonly TemplateRegistryService $templateRegistry,
        private readonly CampaignVariableGridService $variableGridService,
    ) {}

    /**
     * Format campaign data for index list card.
     *
     * @return array{name: string, audience: string, status_label: string, status_variant: string, recipients: int, delivered: string, read: string, response: string, failed: string, completion_rate: string, created_at: string, scheduled_at: string|null}
     */
    public function indexCard(Campaign $campaign): array
    {
        $statusVariant = match ($campaign->status) {
            \App\Enums\CampaignStatus::Draft => 'new',
            \App\Enums\CampaignStatus::Scheduled => 'fd-draft',
            \App\Enums\CampaignStatus::Sending => 'sending',
            \App\Enums\CampaignStatus::Completed => 'fd-approved',
            \App\Enums\CampaignStatus::Paused => 'paused',
            \App\Enums\CampaignStatus::Cancelled => 'cancelled',
            default => 'default',
        };

        $total = max(0, (int) $campaign->total_recipients);
        $ratio = static fn (int $count): string => $count.'/'.$total;

        return [
            'name' => $campaign->name,
            'audience' => $campaign->audience?->name ?? 'No audience',
            'whatsapp_line' => $campaign->whatsappLine?->displayPhone() ?? 'N/A',
            'status_label' => $campaign->status?->label() ?? 'Unknown',
            'status_variant' => $statusVariant,
            'recipients' => $total,
            'delivered' => $ratio(max(0, (int) $campaign->total_delivered)),
            'read' => $ratio(max(0, (int) $campaign->total_read)),
            'response' => $ratio(max(0, (int) $campaign->total_response)),
            'failed' => $ratio(max(0, (int) $campaign->total_failed)),
            'completion_rate' => $campaign->completionRate(),
            'created_at' => $campaign->created_at?->format('d M Y h:i A') ?? '',
            'scheduled_at' => $campaign->scheduled_at?->format('d M Y h:i A'),
        ];
    }

    /**
     * Review summary for step 5 of the wizard.
     *
     * @return array{campaign_name: string, recipients_count: int, audience_name: string, template_name: string, whatsapp_line: string}
     */
    public function reviewSummary(Campaign $campaign): array
    {
        return [
            'campaign_name' => $campaign->name,
            'recipients_count' => $campaign->total_recipients,
            'audience_name' => $campaign->audience?->name ?? 'Not selected',
            'template_name' => $campaign->template?->name ?? 'Not selected',
            'whatsapp_line' => $campaign->whatsappLine?->displayPhone() ?? 'Not selected',
        ];
    }

    /**
     * Data needed for each wizard step.
     *
     * @return array<string, mixed>
     */
    public function stepData(int $step, array $wizardData = []): array
    {
        return match ($step) {
            1, 2 => [
                'whatsappLines' => WhatsappLine::query()
                    ->select(['id', 'phone', 'display_name', 'status'])
                    ->orderBy('display_name')
                    ->get(),
                'audiences' => MailList::query()
                    ->select(['id', 'name'])
                    ->withCount('contacts')
                    ->orderBy('name')
                    ->get(),
            ],
            3 => $this->templateStepData($wizardData),
            4 => $this->variableStepData($wizardData),
            5, 6 => $this->previewStepData($wizardData),
            default => [],
        };
    }

    /**
     * Step 3: Template selection with preview data.
     */
    private function templateStepData(array $wizardData): array
    {
        $this->templateRegistry->pruneCamsDuplicatesOfLocal();

        $templates = Template::query()
            ->with('variables')
            ->select(['id', 'name', 'code', 'language', 'category', 'body_preview', 'payload'])
            ->where('status', TemplateStatus::Approved)
            ->whereNotNull('code')
            ->where('code', '!=', '')
            ->orderBy('name')
            ->get()
            ->map(function (Template $template): Template {
                $template->setAttribute(
                    'has_variables',
                    $this->previewService->templateHasVariables($template),
                );

                return $template;
            });

        $previewData = null;
        $templateId = $wizardData['template_id'] ?? null;
        if ($templateId) {
            $template = Template::query()
                ->with('variables')
                ->where('status', TemplateStatus::Approved)
                ->whereNotNull('code')
                ->where('code', '!=', '')
                ->find($templateId);
            if ($template instanceof Template) {
                $previewData = $this->previewService->forTemplate($template, keepPlaceholders: true);
            }
        }

        return [
            'templates' => $templates,
            'previewData' => $previewData,
        ];
    }

    /**
     * Step 4: Per-recipient variable grid (legacy parity).
     */
    private function variableStepData(array $wizardData): array
    {
        $template = null;
        $templateId = $wizardData['template_id'] ?? null;
        $previewData = null;
        $templateVars = [];
        $variableNames = [];
        $customVariableNames = [];
        $hasCustomVars = false;
        $rows = [];
        $paginator = null;

        if ($templateId) {
            $template = Template::query()
                ->with('variables')
                ->find($templateId);

            if ($template instanceof Template) {
                $previewData = $this->previewService->forTemplate($template, keepPlaceholders: true);
                $templateVars = $previewData['variables'] ?? $this->previewService->variablesForTemplate($template);
                $variableNames = $this->variableGridService->variableNames($template);
                $customVariableNames = $this->variableGridService->customVariableNames($template);
                $hasCustomVars = $customVariableNames !== [];

                $draftId = (int) ($wizardData['draft_id'] ?? 0);
                $draft = $draftId > 0
                    ? Campaign::query()
                        ->whereIn('status', [
                            \App\Enums\CampaignStatus::Draft,
                            \App\Enums\CampaignStatus::Scheduled,
                        ])
                        ->find($draftId)
                    : null;

                if ($draft instanceof Campaign) {
                    $page = max(1, (int) request()->query('page', 1));
                    $grid = $this->variableGridService->page($draft, $template, $page);
                    $rows = $grid['rows'];
                    $paginator = $grid['paginator'];
                    $variableNames = $grid['variable_names'];
                    $customVariableNames = $grid['custom_variable_names'];
                    $hasCustomVars = $grid['has_custom_vars'];
                }
            }
        }

        return [
            'template' => $template,
            'templateVars' => $templateVars,
            'variableNames' => $variableNames,
            'customVariableNames' => $customVariableNames,
            'hasCustomVars' => $hasCustomVars,
            'variableRows' => $rows,
            'variablePaginator' => $paginator,
            'previewData' => $previewData,
        ];
    }

    /**
     * Steps 5 & 6: Review/Schedule with preview data.
     */
    private function previewStepData(array $wizardData): array
    {
        $previewData = null;
        $templateId = $wizardData['template_id'] ?? null;
        $savedVars = is_array($wizardData['template_variables'] ?? null)
            ? $wizardData['template_variables']
            : [];

        if ($templateId) {
            $template = Template::query()->with('variables')->find($templateId);
            if ($template instanceof Template) {
                $previewData = $this->previewService->forTemplate($template, $savedVars, keepPlaceholders: true);
            }
        }

        return [
            'previewData' => $previewData,
        ];
    }
}
