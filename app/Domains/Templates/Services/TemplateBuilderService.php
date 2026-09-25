<?php

declare(strict_types=1);

namespace App\Domains\Templates\Services;

use App\Domains\Templates\Enums\TemplateSource;
use App\Domains\Templates\Enums\TemplateStatus;
use App\Domains\Templates\Enums\VariableDataType;
use App\Domains\Templates\Enums\VariableType;
use App\Domains\Templates\Jobs\SubmitTemplateJob;
use App\Domains\Templates\Support\TemplateCategoryCatalog;
use App\Domains\Templates\Support\TemplateNameValidator;
use App\Domains\Templates\Support\TemplateVariableSyntax;
use App\Domains\Templates\Support\VariableActorContext;
use App\Models\Template;
use App\Models\TemplateStatusLog;
use App\Models\Variable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TemplateBuilderService
{
    public function __construct(
        private readonly VariableActorContext $actorContext,
    ) {}

    public function createDraft(): Template
    {
        $payload = Template::defaultPayload();
        $payload['meta']['setup_completed'] = false;

        return Template::query()->create([
            'name' => $this->uniqueName('draft_template'),
            'category' => '',
            'language' => '',
            'status' => TemplateStatus::Draft,
            'whatsapp_line_id' => $this->actorContext->whatsappLineId(),
            'team_member_id' => $this->actorContext->teamMemberId(),
            'team_member_name' => $this->actorContext->teamMemberName(),
            'payload' => $payload,
        ]);
    }

    /**
     * @param  array{name: string, category: string, language: string, template_type: string}  $setup
     */
    public function createFromSetup(array $setup): Template
    {
        $name = (string) $setup['name'];
        $lineId = $this->actorContext->whatsappLineId();

        if (TemplateNameValidator::nameExistsForLine($name, $lineId)) {
            $message = TemplateNameValidator::nameBlockedByMetaCooldown($name, $lineId)
                ? TemplateNameValidator::metaCooldownMessage()
                : 'A template with this name already exists on this WhatsApp number. Please choose a different name.';

            throw ValidationException::withMessages([
                'name' => $message,
            ]);
        }

        $selectedCategory = strtoupper((string) $setup['category']);
        $isCarousel = TemplateCategoryCatalog::isCarouselSelection($selectedCategory);
        $storedCategory = TemplateCategoryCatalog::storedCategory($selectedCategory);

        $payload = Template::defaultPayload();
        $payload['meta'] = [
            'name' => $name,
            'category' => $storedCategory,
            'language' => (string) $setup['language'],
            'template_type' => (string) $setup['template_type'],
        ];
        $payload['carousel']['enabled'] = $isCarousel;
        $payload['lto']['enabled'] = TemplateCategoryCatalog::isLto($storedCategory);

        return Template::query()->create([
            'name' => $name,
            'category' => $storedCategory,
            'language' => (string) $setup['language'],
            // code is filled only after Alibaba returns TemplateCode — never store the local name here
            'code' => null,
            'status' => TemplateStatus::Draft,
            'whatsapp_line_id' => $lineId,
            'team_member_id' => $this->actorContext->teamMemberId(),
            'team_member_name' => $this->actorContext->teamMemberName(),
            'payload' => $payload,
        ]);
    }

    /**
     * Ensure the template name is unique within the tenant by appending _1, _2, etc.
     */
    private function uniqueName(string $baseName): string
    {
        $existing = Template::query()->where('name', $baseName)->exists();

        if (! $existing) {
            return $baseName;
        }

        $counter = 1;
        do {
            $candidate = "{$baseName}_{$counter}";
            $exists = Template::query()->where('name', $candidate)->exists();
            $counter++;
        } while ($exists);

        return $candidate;
    }

    /**
     * Rename a draft/failed template without touching category/language/type.
     */
    public function saveEditableName(Template $template, ?string $name): Template
    {
        if (! $template->canEditIdentity() || $name === null) {
            return $template;
        }

        $requestedName = trim($name);
        if ($requestedName === '' || $requestedName === $template->name) {
            return $template;
        }

        if (TemplateNameValidator::nameExistsForLine($requestedName, $template->whatsapp_line_id, $template->id)) {
            throw ValidationException::withMessages([
                'name' => 'A template with this name already exists on this WhatsApp number. Please choose a different name.',
            ]);
        }

        return DB::transaction(function () use ($template, $requestedName): Template {
            $payload = $template->wizardPayload();
            $template->name = $requestedName;
            $payload['meta']['name'] = $requestedName;
            $template->payload = $payload;
            $template->save();

            return $template->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $stepData
     */
    public function saveStep(Template $template, string $step, array $stepData): Template
    {
        return DB::transaction(function () use ($template, $step, $stepData): Template {
            $payload = $template->wizardPayload();

            if ($step === 'buttons') {
                $payload['button_mode'] = $stepData['button_mode'] ?? 'call_to_action';
                $payload['is_opt_out'] = $stepData['is_opt_out'] ?? false;
                $payload['buttons'] = $stepData['buttons'] ?? [];
            } else {
                $payload[$step] = array_merge($payload[$step] ?? [], $stepData);
            }

            if ($step === 'meta') {
                $requestedName = (string) ($stepData['name'] ?? $template->name);

                if (TemplateNameValidator::nameExistsForLine($requestedName, $template->whatsapp_line_id, $template->id)) {
                    throw ValidationException::withMessages([
                        'name' => 'A template with this name already exists on this WhatsApp number. Please choose a different name.',
                    ]);
                }

                $selectedCategory = strtoupper((string) ($stepData['category'] ?? $template->category));
                $isCarousel = TemplateCategoryCatalog::isCarouselSelection($selectedCategory);
                // Legacy: carousel option → Marketing category + is_carousel flag
                $storedCategory = TemplateCategoryCatalog::storedCategory($selectedCategory);

                $template->name = $requestedName;
                $template->category = $storedCategory;
                $template->language = (string) ($stepData['language'] ?? $template->language);
                // Never store the local name as Alibaba TemplateCode

                $payload['meta']['name'] = $template->name;
                $payload['meta']['category'] = $storedCategory;
                $payload['meta']['language'] = $template->language;

                $payload['carousel'] = array_merge($payload['carousel'] ?? [], [
                    'enabled' => $isCarousel,
                ]);
                $payload['lto']['enabled'] = TemplateCategoryCatalog::isLto($storedCategory);

                if (isset($stepData['template_type'])) {
                    $payload['meta']['template_type'] = (string) $stepData['template_type'];
                }

                if (array_key_exists('setup_completed', $stepData)) {
                    $payload['meta']['setup_completed'] = (bool) $stepData['setup_completed'];
                }
            }

            if ($step === 'body') {
                $bodyText = TemplateVariableSyntax::normalizeBodyText((string) ($stepData['text'] ?? ''));
                $stepData['text'] = $bodyText;
                $payload['body'] = array_merge($payload['body'] ?? [], $stepData);
                $template->body_preview = $bodyText;
                $this->syncBodyVariables($template, $bodyText);
            }

            $template->payload = $payload;
            $template->save();

            return $template->refresh();
        });
    }

    public function submit(Template $template): Template
    {
        $this->normalizeCarouselStorage($template);
        $template->refresh();

        $previousStatus = $template->status;

        // Clear local snake_case "codes" (name-as-code). Real Alibaba TemplateCode is numeric.
        if (filled($template->code) && ! filled($template->whatsappCode())) {
            $template->forceFill(['code' => null])->saveQuietly();
        }

        // Do NOT put the local name into `code` — that value is reserved for the
        // Alibaba TemplateCode returned by CreateChatappTemplate. Filling it early
        // makes retry jobs call Modify with a fake code.
        $template->update([
            'status' => TemplateStatus::PendingReview,
            'synced_at' => null,
            'rejection_reason' => null,
        ]);

        $this->logStatusChange($template, $previousStatus, TemplateStatus::PendingReview);

        // Auto-create payment_link variable if button URL references $(payment_link)
        $this->ensurePaymentLinkVariable($template);

        $template = $template->fresh() ?? $template;
        $isEdit = filled($template->whatsappCode());
        $whatsapp = app(TemplateWhatsAppService::class);

        // Push to CAMS immediately (same request) so approval does not wait on queue workers.
        $pushed = $isEdit
            ? $whatsapp->modifyTemplate($template)
            : $whatsapp->submitTemplate($template);

        $template->refresh();

        // Retry via queue/cron only when still pending and never handed to CAMS.
        if (! $pushed && $template->status === TemplateStatus::PendingReview && $template->synced_at === null) {
            SubmitTemplateJob::dispatch($template->id, $isEdit);
        }

        return $template;
    }

    /**
     * Log a status transition for audit trail.
     */
    private function logStatusChange(Template $template, TemplateStatus $previous, TemplateStatus $current, ?string $reason = null): void
    {
        if ($previous === $current && $reason === null) {
            return;
        }

        TemplateStatusLog::query()->create([
            'template_id' => $template->id,
            'previous_status' => $previous->value,
            'new_status' => $current->value,
            'reason' => $reason,
            'meta' => [
                'name' => $template->name,
                'code' => $template->code,
            ],
        ]);
    }

    /**
     * If any button URL contains $(payment_link), auto-create the variable
     * and link it to this template.
     */
    private function ensurePaymentLinkVariable(Template $template): void
    {
        $payload = $template->wizardPayload();
        $buttons = $payload['buttons'] ?? [];

        $needsPaymentLink = collect($buttons)
            ->pluck('url')
            ->filter(fn ($url) => is_string($url) && str_contains($url, '$(payment_link)'))
            ->isNotEmpty();

        if (! $needsPaymentLink) {
            return;
        }

        $variable = Variable::query()->firstOrCreate(
            ['name' => 'payment_link'],
            [
                'type' => 'static',
                'data_type' => 'url',
                'value' => '',
            ],
        );

        $existing = $template->variables()->where('variable_id', $variable->id)->exists();
        if (! $existing) {
            $template->variables()->attach($variable->id, [
                'placement' => 'buttons',
                'position' => 0,
            ]);
        }
    }

    /**
     * Duplicate a template as a local draft (no WhatsApp code).
     */
    public function duplicate(Template $template): Template
    {
        return DB::transaction(function () use ($template): Template {
            $template->loadMissing('variables');

            $copy = $template->replicate([
                'uuid',
                'code',
                'synced_at',
                'rejection_reason',
                'deleted_at',
            ]);
            $copy->name = $this->uniqueName($template->name.'_copy');
            $copy->code = null;
            $copy->status = TemplateStatus::Draft;
            $copy->source = TemplateSource::Local;
            $copy->synced_at = null;
            $copy->rejection_reason = null;

            $payload = $template->wizardPayload();
            unset($payload['meta']['archived_code']);
            $payload['meta']['name'] = $copy->name;
            $payload['meta']['setup_completed'] = (bool) ($payload['meta']['setup_completed'] ?? false);
            $copy->payload = $payload;
            $copy->body_preview = $template->body_preview;
            $copy->save();

            foreach ($template->variables as $variable) {
                $copy->variables()->attach($variable->id, [
                    'placement' => (string) ($variable->pivot->placement ?? 'body'),
                    'position' => (int) ($variable->pivot->position ?? 0),
                ]);
            }

            return $copy->refresh();
        });
    }

    /**
     * Legacy parity: carousel is Marketing + flag, never a stored WhatsApp category.
     */
    private function normalizeCarouselStorage(Template $template): void
    {
        $payload = $template->wizardPayload();
        $isLegacyCarouselCategory = TemplateCategoryCatalog::isCarousel((string) $template->category);
        $enabled = (bool) data_get($payload, 'carousel.enabled', false);

        if (! $isLegacyCarouselCategory && ! $enabled) {
            return;
        }

        $payload['carousel'] = array_merge($payload['carousel'] ?? [], ['enabled' => true]);
        $payload['meta']['category'] = TemplateCategoryCatalog::MARKETING;

        $template->forceFill([
            'category' => TemplateCategoryCatalog::MARKETING,
            'payload' => $payload,
        ])->saveQuietly();
    }

    /**
     * Sync body placeholders to template_variables without wiping header/button pivots.
     * Missing custom variable names are auto-created (legacy parity).
     */
    private function syncBodyVariables(Template $template, string $body): void
    {
        $names = collect(TemplateVariableSyntax::extractVariableNames($body))->values();

        DB::table('template_variables')
            ->where('template_id', $template->id)
            ->where('placement', 'body')
            ->delete();

        if ($names->isEmpty()) {
            return;
        }

        foreach ($names as $index => $name) {
            $variable = Variable::query()->firstOrCreate(
                [
                    'name' => $name,
                    'whatsapp_line_id' => $template->whatsapp_line_id,
                    'team_member_id' => $template->team_member_id,
                ],
                [
                    'type' => VariableType::Dynamic,
                    'data_type' => VariableDataType::String,
                    'value' => null,
                    'team_member_name' => $template->team_member_name,
                ],
            );

            $template->variables()->attach($variable->id, [
                'placement' => 'body',
                'position' => $index,
            ]);
        }
    }
}
