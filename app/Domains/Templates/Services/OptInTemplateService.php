<?php

declare(strict_types=1);

namespace App\Domains\Templates\Services;

use App\Domains\Templates\Enums\TemplateStatus;
use App\Domains\Templates\Support\TemplateVariableSyntax;
use App\Domains\Templates\Support\VariableActorContext;
use App\Models\Template;
use App\Models\Variable;
use App\Models\WhatsappLine;
use Illuminate\Support\Facades\Log;
use Throwable;

class OptInTemplateService
{
    public const TEMPLATE_NAME = 'opt_in_message';

    public const TEMPLATE_NAME_V3 = 'opt_in_message_v3';

    private const AUTO_SUBMIT_COOLDOWN_MINUTES = 15;

    public function __construct(
        private readonly VariableActorContext $actorContext,
    ) {}

    /**
     * Backward-compatible alias used by CampaignSendService.
     */
    public function ensureExists(): Template
    {
        return $this->ensureTemplate();
    }

    public function usesV3(): bool
    {
        // Legacy parity: always send original Marketing `opt_in_message`.
        // V3 Utility template is kept for old rows only - never used for sends.
        return false;
    }

    public function activeTemplateName(): string
    {
        return $this->usesV3() ? self::TEMPLATE_NAME_V3 : self::TEMPLATE_NAME;
    }

    /**
     * Ensure the opt-in template exists for the current tenant.
     * On Rejected / broken payload / missing CAMS handoff, auto-repair and resubmit.
     */
    public function ensureTemplate(?WhatsappLine $line = null): Template
    {
        $useV3 = $this->usesV3();
        $name = $useV3 ? self::TEMPLATE_NAME_V3 : self::TEMPLATE_NAME;
        $lineId = $line?->id ?? $this->actorContext->whatsappLineId();

        $candidates = Template::query()
            ->where('name', $name)
            ->when($lineId, function ($q) use ($lineId): void {
                $q->where(function ($inner) use ($lineId): void {
                    $inner->where('whatsapp_line_id', $lineId)->orWhereNull('whatsapp_line_id');
                });
            })
            ->orderByDesc('id')
            ->get();

        // Prefer a WhatsApp-approved provider TemplateCode on this line.
        $approvedProvider = $candidates->first(
            static fn (Template $row): bool => $row->status === TemplateStatus::Approved
                && $row->whatsappCode() !== null,
        );
        if ($approvedProvider instanceof Template) {
            return $approvedProvider;
        }

        $template = $candidates->first(
            static fn (Template $row): bool => $lineId === null || (int) $row->whatsapp_line_id === (int) $lineId,
        ) ?? $candidates->first();

        $variant = $this->contentVariantFor($template);
        $desired = $this->desiredContent($useV3, $variant);
        $bodyText = $desired['body'];
        $payload = $this->buildDesiredPayload($name, $desired, $bodyText);

        if (! $template instanceof Template) {
            $template = Template::query()->create([
                'name' => $name,
                // Do not store the local name as `code` - that column is for CAMS TemplateCode.
                'code' => null,
                'language' => 'en_GB',
                'category' => $desired['category'],
                'status' => TemplateStatus::PendingReview,
                'whatsapp_line_id' => $lineId,
                'team_member_id' => $this->actorContext->teamMemberId(),
                'team_member_name' => $this->actorContext->teamMemberName(),
                'payload' => $payload,
                'body_preview' => $bodyText,
                'synced_at' => null,
                'rejection_reason' => null,
            ]);
            $repaired = true;
        } else {
            $repaired = $this->repairTemplate($template, $name, $desired, $payload, $bodyText, $lineId);
        }

        $this->ensureFullNameVariable($template);

        if ($this->shouldAutoSubmit($template->fresh() ?? $template, $repaired)) {
            $this->autoSubmit($template->fresh() ?? $template);
        }

        return $template->fresh() ?? $template;
    }

    /**
     * @param  array{body: string, category: string, buttons: list<array{text: string, type: string, url: string, flow_id: string}>}  $desired
     * @return array<string, mixed>
     */
    private function buildDesiredPayload(string $name, array $desired, string $bodyText): array
    {
        $payload = Template::defaultPayload();
        $payload['meta'] = [
            'name' => $name,
            'category' => $desired['category'],
            'language' => 'en_GB',
            'template_type' => 'regular',
            'setup_completed' => true,
        ];
        $payload['body'] = ['text' => $bodyText, 'samples' => ['John Doe']];
        $payload['footer'] = ['text' => ''];
        $payload['button_mode'] = 'quick_reply';
        $payload['is_opt_out'] = false;
        $payload['buttons'] = $desired['buttons'];

        return $payload;
    }

    /**
     * @param  array{body: string, category: string, buttons: list<array{text: string, type: string, url: string, flow_id: string}>}  $desired
     * @param  array<string, mixed>  $desiredPayload
     */
    private function repairTemplate(
        Template $template,
        string $name,
        array $desired,
        array $desiredPayload,
        string $bodyText,
        ?int $lineId,
    ): bool {
        $current = $template->wizardPayload();
        $rawBody = (string) ($current['body']['text'] ?? '');
        $currentBody = TemplateVariableSyntax::normalizeBodyText($rawBody);
        $currentSamples = is_array($current['body']['samples'] ?? null)
            ? array_values(array_filter($current['body']['samples'], static fn ($s) => filled($s)))
            : [];
        $needsUpdate = false;

        // Normalize {{full_name}} → $(full_name) and keep desired marketing copy in sync.
        if (str_contains($rawBody, '{{') || trim($currentBody) !== trim($bodyText)) {
            $needsUpdate = true;
        }

        if ($currentSamples === []) {
            $needsUpdate = true;
        }

        if (($current['button_mode'] ?? '') !== 'quick_reply' || empty($current['buttons'])) {
            $needsUpdate = true;
        }

        if (strcasecmp((string) $template->category, $desired['category']) !== 0) {
            $template->category = $desired['category'];
            $needsUpdate = true;
        }

        if ($lineId !== null && (int) ($template->whatsapp_line_id ?? 0) !== (int) $lineId) {
            $template->whatsapp_line_id = $lineId;
            $needsUpdate = true;
        }

        // Fake local-name codes block Create; clear them so submit can resolve Meta / create.
        if (filled($template->code) && ! filled($template->whatsappCode())) {
            $template->code = null;
            $needsUpdate = true;
        }

        $recoverableReject = $template->status === TemplateStatus::Rejected
            && $this->isRecoverableRejection($template->rejection_reason);

        if ($recoverableReject) {
            $needsUpdate = true;
        }

        if (! $needsUpdate) {
            return false;
        }

        $merged = array_merge($current, $desiredPayload);
        $merged['meta'] = array_merge(
            is_array($current['meta'] ?? null) ? $current['meta'] : [],
            $desiredPayload['meta'],
        );
        // Preserve archive / variant meta. Clear submit throttle on recoverable reject
        // so the repaired payload can be pushed immediately once.
        foreach (['archived_code', 'opt_in_content_variant'] as $keep) {
            if (isset($current['meta'][$keep])) {
                $merged['meta'][$keep] = $current['meta'][$keep];
            }
        }
        if (! $recoverableReject && isset($current['meta']['opt_in_auto_submit_at'])) {
            $merged['meta']['opt_in_auto_submit_at'] = $current['meta']['opt_in_auto_submit_at'];
        }
        $merged['meta']['opt_in_content_variant'] = $this->contentVariantFor($template);

        $template->payload = $merged;
        $template->body_preview = $bodyText;
        $template->language = $template->language ?: 'en_GB';

        if ($recoverableReject || $template->status === TemplateStatus::Draft) {
            $template->status = TemplateStatus::PendingReview;
            $template->synced_at = null;
            $template->rejection_reason = null;
        }

        $template->save();

        Log::info('Opt-in template auto-repaired', [
            'template_id' => $template->id,
            'name' => $name,
            'status' => $template->status->value,
            'variant' => $merged['meta']['opt_in_content_variant'] ?? 0,
        ]);

        return true;
    }

    private function ensureFullNameVariable(Template $template): void
    {
        $variable = Variable::query()->firstOrCreate(
            ['name' => 'full_name'],
            ['type' => 'static', 'data_type' => 'string', 'value' => null],
        );

        $linked = $template->variables()->where('variable_id', $variable->id)->exists();
        if (! $linked) {
            $template->variables()->attach($variable->id, [
                'placement' => 'body',
                'position' => 0,
            ]);
        }
    }

    private function shouldAutoSubmit(Template $template, bool $repaired): bool
    {
        if ($template->status === TemplateStatus::Approved && $template->whatsappCode() !== null) {
            return false;
        }

        // Already handed to CAMS / Meta is auditing - do not resubmit.
        if ($template->status === TemplateStatus::PendingReview && $template->synced_at !== null) {
            return false;
        }

        if ($template->status === TemplateStatus::Rejected
            && ! $this->isRecoverableRejection($template->rejection_reason)
        ) {
            return false;
        }

        if (! in_array($template->status, [
            TemplateStatus::Draft,
            TemplateStatus::Rejected,
            TemplateStatus::PendingReview,
        ], true)) {
            return false;
        }

        $last = data_get($template->wizardPayload(), 'meta.opt_in_auto_submit_at');
        if (is_string($last) && $last !== '') {
            try {
                if (now()->parse($last)->gt(now()->subMinutes(self::AUTO_SUBMIT_COOLDOWN_MINUTES))) {
                    return false;
                }
            } catch (Throwable) {
                // Ignore bad timestamps and allow submit.
            }
        }

        // Fresh create / content repair / never handed to CAMS / recoverable reject.
        return $repaired
            || $template->synced_at === null
            || $template->status === TemplateStatus::Rejected
            || $template->whatsappCode() === null;
    }

    private function autoSubmit(Template $template): void
    {
        try {
            $payload = $template->wizardPayload();
            $payload['meta'] = is_array($payload['meta'] ?? null) ? $payload['meta'] : [];
            $payload['meta']['opt_in_auto_submit_at'] = now()->toIso8601String();
            $template->forceFill(['payload' => $payload])->saveQuietly();

            app(TemplateBuilderService::class)->submit($template);

            Log::info('Opt-in template auto-submitted', [
                'template_id' => $template->id,
                'status' => $template->fresh()?->status?->value,
                'code' => $template->fresh()?->code,
            ]);
        } catch (Throwable $e) {
            Log::warning('Opt-in template auto-submit failed', [
                'template_id' => $template->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function isRecoverableRejection(?string $reason): bool
    {
        $haystack = strtolower(trim((string) $reason));
        if ($haystack === '') {
            return true;
        }

        return str_contains($haystack, 'must not be null')
            || str_contains($haystack, 'missing example')
            || str_contains($haystack, 'invalid_format')
            || str_contains($haystack, 'duplicate content')
            || str_contains($haystack, 'invalidparameter')
            || str_contains($haystack, 'message must not be null')
            || str_contains($haystack, 'custspaceid')
            || str_contains($haystack, 'not connected');
    }

    private function contentVariantFor(?Template $template): int
    {
        if (! $template instanceof Template) {
            return 0;
        }

        $reason = strtolower((string) $template->rejection_reason);
        // Meta rejected as duplicate → use alternate marketing wording on next repair.
        if (str_contains($reason, 'duplicate')) {
            return 1;
        }

        return (int) data_get($template->wizardPayload(), 'meta.opt_in_content_variant', 0) >= 1 ? 1 : 0;
    }

    /**
     * @return array{body: string, category: string, buttons: list<array{text: string, type: string, url: string, flow_id: string}>}
     */
    private function desiredContent(bool $useV3, int $variant = 0): array
    {
        $nameVar = TemplateVariableSyntax::placeholder('full_name');

        if ($useV3) {
            return [
                'body' => 'Hi '.$nameVar.', to ensure a seamless experience, we are updating our communication settings. Please confirm your preference to receive standard updates, support responses, and regular notifications on WhatsApp.',
                'category' => 'UTILITY',
                'buttons' => [
                    ['text' => 'Yes, confirm', 'type' => 'quick_reply', 'url' => '', 'flow_id' => ''],
                    ['text' => 'No, thanks', 'type' => 'quick_reply', 'url' => '', 'flow_id' => ''],
                ],
            ];
        }

        if ($variant >= 1) {
            return [
                'body' => 'Hi '.$nameVar."!\n"
                    .'Thanks for connecting with us on WhatsApp. '
                    .'Confirm opt-in to receive offers, seasonal promotions, and important account alerts from our team here.\n'
                    .'Would you like to continue?',
                'category' => 'MARKETING',
                'buttons' => [
                    ['text' => 'Yes', 'type' => 'quick_reply', 'url' => '', 'flow_id' => ''],
                    ['text' => 'No', 'type' => 'quick_reply', 'url' => '', 'flow_id' => ''],
                    ['text' => 'STOP', 'type' => 'quick_reply', 'url' => '', 'flow_id' => ''],
                ],
            ];
        }

        return [
            'body' => 'Hi '.$nameVar."!\n"
                .'We want to make sure you never miss out on our latest updates and exclusive benefits. '
                .'By opting in, you will get instant access to special offers, seasonal promotions, '
                ."and important account alerts directly here on WhatsApp.\n"
                .'Would you like to stay connected with us?',
            'category' => 'MARKETING',
            'buttons' => [
                ['text' => 'Yes', 'type' => 'quick_reply', 'url' => '', 'flow_id' => ''],
                ['text' => 'No', 'type' => 'quick_reply', 'url' => '', 'flow_id' => ''],
                ['text' => 'STOP', 'type' => 'quick_reply', 'url' => '', 'flow_id' => ''],
            ],
        ];
    }
}
