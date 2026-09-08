<?php

declare(strict_types=1);

namespace App\Domains\Campaigns\Http\Controllers;

use App\Domains\Campaigns\Http\Requests\SaveCampaignWizardStepRequest;
use App\Domains\Campaigns\Services\CampaignPresenter;
use App\Domains\Campaigns\Services\CampaignService;
use App\Domains\Campaigns\Services\CampaignTestMessageService;
use App\Domains\Campaigns\Services\CampaignVariableGridService;
use App\Domains\Templates\Enums\TemplateStatus;
use App\Domains\Templates\Services\TemplatePreviewService;
use App\Domains\Templates\Support\CamsTemplateIdentity;
use App\Enums\CampaignStatus;
use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Template;
use App\Models\WhatsappLine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class CampaignCreateController extends Controller
{
    private const SESSION_KEY = 'campaign_wizard';

    private const MAX_STEPS = 6;

    public function __construct(
        private readonly CampaignPresenter $presenter,
        private readonly TemplatePreviewService $previewService,
        private readonly CampaignTestMessageService $testMessageService,
        private readonly CampaignService $campaignService,
        private readonly CampaignVariableGridService $variableGridService,
    ) {}

    /**
     * Start a brand-new wizard. Leftover session data is saved as a draft first.
     */
    public function start(Request $request): RedirectResponse
    {
        $this->stashWizardAsDraft($request->session()->get(self::SESSION_KEY, []));
        $request->session()->forget(self::SESSION_KEY);

        return redirect()->route('campaigns.create.step', 1);
    }

    /**
     * Resume an existing draft/scheduled campaign in the create wizard.
     */
    public function edit(Request $request, Campaign $bulkCampaign): RedirectResponse
    {
        abort_unless(
            $bulkCampaign->canBeEdited(),
            422,
            'Only draft or scheduled campaigns can be edited.',
        );

        $this->stashWizardAsDraft($request->session()->get(self::SESSION_KEY, []));

        $wizardData = [
            'draft_id' => $bulkCampaign->id,
            'name' => $bulkCampaign->name,
            'whatsapp_line_id' => $bulkCampaign->whatsapp_line_id,
            'audience_id' => $bulkCampaign->audience_id,
            'template_id' => $bulkCampaign->template_id,
            'template_variables' => is_array($bulkCampaign->template_variables)
                ? $bulkCampaign->template_variables
                : [],
            'scheduled_at' => $bulkCampaign->scheduled_at?->format('Y-m-d\TH:i'),
            'send_mode' => $bulkCampaign->scheduled_at ? 'schedule' : 'now',
        ];

        $request->session()->put(self::SESSION_KEY, $wizardData);

        return redirect()->route('campaigns.create.step', $this->resumeStep($wizardData));
    }

    /**
     * Leave the wizard: keep progress as a draft, then return to the list.
     */
    public function cancel(Request $request): RedirectResponse
    {
        $draft = $this->stashWizardAsDraft($request->session()->get(self::SESSION_KEY, []));
        $request->session()->forget(self::SESSION_KEY);

        return redirect()
            ->route('campaigns.index')
            ->with('status', $draft
                ? 'Campaign saved as draft.'
                : 'Campaign creation cancelled.');
    }

    /**
     * Show a specific wizard step.
     */
    public function showStep(Request $request, int $step): View|RedirectResponse
    {
        if ($step < 1 || $step > self::MAX_STEPS) {
            abort(404);
        }

        // Step 5 is a legacy review screen — send users to Schedule & Confirm.
        if ($step === 5) {
            return redirect()->route('campaigns.create.step', 6);
        }

        $wizardData = $request->session()->get(self::SESSION_KEY, []);

        if ($step === 4) {
            $wizardData = $this->ensureDraftRecipients($wizardData);
            $request->session()->put(self::SESSION_KEY, $wizardData);
        }

        $stepData = $this->presenter->stepData($step, $wizardData);

        return view("campaigns.create.step-{$step}", array_merge($stepData, [
            'wizardData' => $wizardData,
            'step' => $step,
        ]));
    }

    /**
     * Persist per-recipient variable grid values for the draft campaign.
     */
    public function saveVariables(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'recipients' => ['required', 'array'],
            'recipients.*.recipient_id' => ['required', 'integer'],
            'recipients.*.values' => ['nullable', 'array'],
            'recipients.*.values.*' => ['nullable', 'string', 'max:60'],
        ]);

        $wizardData = $request->session()->get(self::SESSION_KEY, []);
        $wizardData = $this->ensureDraftRecipients($wizardData);
        $request->session()->put(self::SESSION_KEY, $wizardData);

        $draft = $this->draftFromWizard($wizardData);
        abort_if($draft === null, 422, 'Save campaign info and audience before editing variables.');

        $rows = [];
        foreach ($validated['recipients'] as $row) {
            $rows[] = [
                'recipient_id' => (int) $row['recipient_id'],
                'values' => is_array($row['values'] ?? null) ? $row['values'] : [],
            ];
        }

        $updated = $this->variableGridService->save($draft, $rows);

        return response()->json([
            'success' => true,
            'updated' => $updated,
            'message' => 'Variable values saved.',
        ]);
    }

    /**
     * Import CSV variable values onto existing draft recipients.
     */
    public function importVariables(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'csv_file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ]);

        $wizardData = $request->session()->get(self::SESSION_KEY, []);
        $wizardData = $this->ensureDraftRecipients($wizardData);
        $request->session()->put(self::SESSION_KEY, $wizardData);

        $draft = $this->draftFromWizard($wizardData);
        abort_if($draft === null, 422, 'Save campaign info and audience before importing variables.');

        $templateId = (int) ($wizardData['template_id'] ?? $draft->template_id ?? 0);
        $template = Template::query()->find($templateId);
        abort_if($template === null, 422, 'Please select a template first.');

        $result = $this->variableGridService->applyCsv($draft, $template, $validated['csv_file']);

        if (isset($result['error'])) {
            return response()->json([
                'success' => false,
                'message' => $result['error'],
            ], 422);
        }

        return response()->json([
            'success' => true,
            'updated' => $result['updated'],
            'skipped' => $result['skipped'],
            'missing' => $result['missing'],
            'message' => sprintf(
                'Imported values for %d recipient(s). %d row(s) skipped, %d phone(s) not in this campaign.',
                $result['updated'],
                $result['skipped'],
                $result['missing'],
            ),
        ]);
    }

    /**
     * Live template preview for campaign wizard (legacy /preview-template equivalent).
     */
    public function templatePreview(Request $request, int $template): JsonResponse
    {
        $model = Template::query()
            ->with('variables')
            ->where('status', TemplateStatus::Approved)
            ->findOrFail($template);

        $values = is_array($request->input('variables'))
            ? $request->input('variables')
            : [];

        return response()->json($this->previewService->forTemplate($model, $values, keepPlaceholders: true));
    }

    /**
     * Send a test WhatsApp message using wizard session data (before campaign is stored).
     */
    public function testMessage(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:32'],
            'template_variables' => ['nullable', 'array'],
            'template_variables.*' => ['nullable', 'string', 'max:60'],
        ]);

        try {
            $wizardData = $request->session()->get(self::SESSION_KEY, []);
            $lineId = (int) ($wizardData['whatsapp_line_id'] ?? 0);
            $templateId = (int) ($wizardData['template_id'] ?? 0);

            $line = WhatsappLine::query()->find($lineId);
            $template = Template::query()->find($templateId);

            abort_if($line === null, 422, 'Please select a From Number in Info & Recipients.');
            abort_if($template === null, 422, 'Please select a template first.');

            $sessionVars = is_array($wizardData['template_variables'] ?? null)
                ? $wizardData['template_variables']
                : [];
            $postedVars = is_array($validated['template_variables'] ?? null)
                ? $validated['template_variables']
                : [];

            $message = $this->testMessageService->sendDirect(
                line: $line,
                template: $template,
                phone: (string) $validated['phone'],
                templateVariables: array_merge($sessionVars, $postedVars),
            );
        } catch (HttpExceptionInterface $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage() ?: 'Unable to send test message.',
            ], $exception->getStatusCode() ?: 422);
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json([
                'success' => false,
                'message' => $exception->getMessage() !== ''
                    ? $exception->getMessage()
                    : 'Unable to send test message. Please try again.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Test WhatsApp message sent successfully to '.$validated['phone'].'.',
            'message_id' => $message->id,
            'debug' => [
                'template_id' => $template->id,
                'template_code' => $message->metadata['template_code'] ?? $template->code,
                'language_sent' => CamsTemplateIdentity::language($template->language),
                'language_on_template' => $template->language,
                'from' => $line->phone,
                'cust_space_id' => $line->alibaba_cust_space_id,
            ],
        ]);
    }

    /**
     * Save wizard step data to session and redirect to next step.
     */
    public function saveStep(SaveCampaignWizardStepRequest $request, int $step): RedirectResponse
    {
        if ($step < 1 || $step > self::MAX_STEPS) {
            abort(404);
        }

        $wizardData = $request->session()->get(self::SESSION_KEY, []);

        $stepFields = $this->fieldsForStep($step);
        foreach ($stepFields as $field) {
            if ($request->has($field)) {
                $wizardData[$field] = $request->input($field);
            }
        }

        $wizardData = $this->stashWizardAsDraft($wizardData) ?? $wizardData;
        $request->session()->put(self::SESSION_KEY, $wizardData);

        if ($step === 4) {
            $this->persistGridFromRequest($request, $wizardData);
        }

        if ($step === self::MAX_STEPS) {
            return redirect()->route('campaigns.store');
        }

        return redirect()->route('campaigns.create.step', $this->nextStepAfter($step, $wizardData));
    }

    /**
     * @return string[]
     */
    private function fieldsForStep(int $step): array
    {
        return match ($step) {
            1 => ['name', 'whatsapp_line_id'],
            2 => ['audience_id'],
            3 => ['template_id'],
            4 => [],
            5 => [],
            6 => ['scheduled_at', 'send_mode'],
            default => [],
        };
    }

    /**
     * Legacy-aligned navigation: skip Variables when template has none;
     * after Variables go straight to Schedule & Confirm.
     */
    private function nextStepAfter(int $step, array $wizardData): int
    {
        return match ($step) {
            1 => 2,
            2 => 3,
            3 => $this->templateHasVariables((int) ($wizardData['template_id'] ?? 0)) ? 4 : 6,
            4, 5 => 6,
            default => min($step + 1, self::MAX_STEPS),
        };
    }

    private function templateHasVariables(int $templateId): bool
    {
        if ($templateId <= 0) {
            return false;
        }

        $template = Template::query()->with('variables')->find($templateId);
        if (! $template instanceof Template) {
            return false;
        }

        return count($this->variableGridService->variableNames($template)) > 0;
    }

    /**
     * Persist wizard progress as a Draft campaign (create or update).
     *
     * @param  array<string, mixed>  $wizardData
     * @return array<string, mixed>|null  Updated wizard data with draft_id, or null when nothing to save
     */
    private function stashWizardAsDraft(array $wizardData): ?array
    {
        $name = trim((string) ($wizardData['name'] ?? ''));
        if ($name === '') {
            return null;
        }

        $payload = [
            'name' => $name,
            'whatsapp_line_id' => $this->nullableId($wizardData['whatsapp_line_id'] ?? null),
            'audience_id' => $this->nullableId($wizardData['audience_id'] ?? null),
            'template_id' => $this->nullableId($wizardData['template_id'] ?? null),
            'template_variables' => is_array($wizardData['template_variables'] ?? null)
                ? $wizardData['template_variables']
                : null,
        ];

        // Keep wizard progress as Draft while creating; don't wipe schedule on an
        // already-scheduled campaign until the final store step.
        $draftId = (int) ($wizardData['draft_id'] ?? 0);
        $existing = $draftId > 0
            ? Campaign::query()
                ->whereIn('status', [CampaignStatus::Draft, CampaignStatus::Scheduled])
                ->find($draftId)
            : null;

        if (! $existing instanceof Campaign || $existing->status === CampaignStatus::Draft) {
            $payload['scheduled_at'] = null;
        }

        $draft = $existing instanceof Campaign
            ? $this->campaignService->update($existing, $payload)
            : $this->campaignService->create($payload);

        $wizardData['draft_id'] = $draft->id;

        return $wizardData;
    }

    /**
     * @param  array<string, mixed>  $wizardData
     * @return array<string, mixed>
     */
    private function ensureDraftRecipients(array $wizardData): array
    {
        $stashed = $this->stashWizardAsDraft($wizardData);
        if ($stashed !== null) {
            $wizardData = $stashed;
        }

        $draft = $this->draftFromWizard($wizardData);
        if ($draft instanceof Campaign && $draft->audience_id && (int) $draft->total_recipients === 0) {
            $this->campaignService->populateRecipients($draft);
        }

        return $wizardData;
    }

    /**
     * @param  array<string, mixed>  $wizardData
     */
    private function draftFromWizard(array $wizardData): ?Campaign
    {
        $draftId = (int) ($wizardData['draft_id'] ?? 0);
        if ($draftId <= 0) {
            return null;
        }

        return Campaign::query()
            ->whereIn('status', [CampaignStatus::Draft, CampaignStatus::Scheduled])
            ->find($draftId);
    }

    /**
     * @param  array<string, mixed>  $wizardData
     */
    private function persistGridFromRequest(Request $request, array $wizardData): void
    {
        $draft = $this->draftFromWizard($wizardData);
        if ($draft === null) {
            return;
        }

        $posted = $request->input('recipients');
        if (! is_array($posted) || $posted === []) {
            return;
        }

        $rows = [];
        foreach ($posted as $recipientId => $payload) {
            $id = (int) (is_array($payload) ? ($payload['recipient_id'] ?? $recipientId) : $recipientId);
            if ($id <= 0) {
                continue;
            }
            $values = is_array($payload) ? ($payload['values'] ?? $payload) : [];
            if (isset($values['recipient_id'])) {
                unset($values['recipient_id']);
            }
            if (isset($values['values']) && is_array($values['values'])) {
                $values = $values['values'];
            }
            $rows[] = [
                'recipient_id' => $id,
                'values' => is_array($values) ? $values : [],
            ];
        }

        if ($rows !== []) {
            $this->variableGridService->save($draft, $rows);
        }
    }

    /**
     * Pick the first incomplete wizard step for an existing campaign.
     *
     * @param  array<string, mixed>  $wizardData
     */
    private function resumeStep(array $wizardData): int
    {
        if (trim((string) ($wizardData['name'] ?? '')) === '' || $this->nullableId($wizardData['whatsapp_line_id'] ?? null) === null) {
            return 1;
        }

        if ($this->nullableId($wizardData['audience_id'] ?? null) === null) {
            return 2;
        }

        if ($this->nullableId($wizardData['template_id'] ?? null) === null) {
            return 3;
        }

        if ($this->templateHasVariables((int) $wizardData['template_id'])) {
            return 4;
        }

        return 6;
    }

    private function nullableId(mixed $value): ?int
    {
        $id = (int) $value;

        return $id > 0 ? $id : null;
    }
}
