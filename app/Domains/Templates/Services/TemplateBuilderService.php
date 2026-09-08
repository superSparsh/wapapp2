<?php

declare(strict_types=1);

namespace App\Domains\Templates\Services;

use App\Domains\Templates\Enums\TemplateStatus;
use App\Domains\Templates\Jobs\SubmitTemplateJob;
use App\Domains\Templates\Support\TemplateCategoryCatalog;
use App\Domains\Templates\Support\TemplateNameValidator;
use App\Domains\Templates\Support\TemplateVariableSyntax;
use App\Domains\Templates\Support\VariableActorContext;
use App\Models\Template;
use App\Models\TemplateStatusLog;
use App\Models\Variable;
use Illuminate\Support\Facades\DB;

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
        $code = TemplateNameValidator::normalizeCode($name);
        $lineId = $this->actorContext->whatsappLineId();

        if (TemplateNameValidator::codeExistsForLine($code, $lineId)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'name' => 'A template with this name already exists. Please choose a different name.',
            ]);
        }

        $payload = Template::defaultPayload();
        $payload['meta'] = [
            'name' => $name,
            'category' => (string) $setup['category'],
            'language' => (string) $setup['language'],
            'template_type' => (string) $setup['template_type'],
        ];

        return Template::query()->create([
            'name' => $name,
            'category' => (string) $setup['category'],
            'language' => (string) $setup['language'],
            'code' => $code,
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
                $code = TemplateNameValidator::normalizeCode($requestedName);

                if (TemplateNameValidator::codeExistsForLine($code, $template->whatsapp_line_id, $template->id)) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'name' => 'A template with this name already exists. Please choose a different name.',
                    ]);
                }

                $template->name = $requestedName;
                $template->category = (string) ($stepData['category'] ?? $template->category);
                $template->language = (string) ($stepData['language'] ?? $template->language);
                $template->code = $code;

                $payload['meta']['name'] = $template->name;
                $payload['meta']['category'] = $template->category;
                $payload['meta']['language'] = $template->language;

                $payload['carousel']['enabled'] = TemplateCategoryCatalog::isCarousel($template->category);
                $payload['lto']['enabled'] = TemplateCategoryCatalog::isLto($template->category);

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
        $previousStatus = $template->status;

        $template->update([
            'status' => TemplateStatus::PendingReview,
            'code' => $template->code ?: TemplateNameValidator::normalizeCode($template->name),
        ]);

        $this->logStatusChange($template, $previousStatus, TemplateStatus::PendingReview);

        // Auto-create payment_link variable if button URL references $(payment_link)
        $this->ensurePaymentLinkVariable($template);

        SubmitTemplateJob::dispatch($template->id);

        return $template->refresh();
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

    private function syncBodyVariables(Template $template, string $body): void
    {
        $names = collect(TemplateVariableSyntax::extractVariableNames($body));

        if ($names->isEmpty()) {
            $template->variables()->detach();

            return;
        }

        $variableIds = Variable::query()
            ->whereIn('name', $names->all())
            ->pluck('id', 'name');

        $sync = [];
        foreach ($names as $index => $name) {
            $id = $variableIds->get($name);
            if ($id !== null) {
                $sync[$id] = ['placement' => 'body', 'position' => $index];
            }
        }

        $template->variables()->sync($sync);
    }
}
