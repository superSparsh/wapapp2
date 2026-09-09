<?php

declare(strict_types=1);

namespace App\Domains\Templates\Services;

use App\Domains\Templates\Enums\TemplateStatus;
use App\Domains\Templates\Support\VariableActorContext;
use App\Models\Template;
use App\Models\Variable;

class OptInTemplateService
{
    public const TEMPLATE_NAME = 'opt_in_message';

    public const TEMPLATE_NAME_V3 = 'opt_in_message_v3';

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
        if (config('opt_in.v2_global')) {
            return true;
        }

        $tenantId = (int) (tenant('id') ?? 0);
        $ids = config('opt_in.v2_customer_ids', []);

        return $tenantId > 0 && is_array($ids) && in_array($tenantId, array_map('intval', $ids), true);
    }

    public function activeTemplateName(): string
    {
        return $this->usesV3() ? self::TEMPLATE_NAME_V3 : self::TEMPLATE_NAME;
    }

    /**
     * Ensure the opt-in template exists for the current tenant.
     */
    public function ensureTemplate(): Template
    {
        $useV3 = $this->usesV3();
        $name = $useV3 ? self::TEMPLATE_NAME_V3 : self::TEMPLATE_NAME;
        $lineId = $this->actorContext->whatsappLineId();

        $template = Template::query()
            ->where('name', $name)
            ->when($lineId, fn ($q) => $q->where('whatsapp_line_id', $lineId))
            ->first();

        $desired = $this->desiredContent($useV3);
        $bodyText = $desired['body'];

        $payload = Template::defaultPayload();
        $payload['meta'] = [
            'name' => $name,
            'category' => $desired['category'],
            'language' => 'en_GB',
        ];
        $payload['body'] = ['text' => $bodyText, 'samples' => []];
        $payload['footer'] = ['text' => ''];
        $payload['button_mode'] = 'quick_reply';
        $payload['is_opt_out'] = false;
        $payload['buttons'] = $desired['buttons'];

        if (! $template instanceof Template) {
            $template = Template::query()->create([
                'name' => $name,
                'code' => $name,
                'language' => 'en_GB',
                'category' => $desired['category'],
                'status' => TemplateStatus::PendingReview,
                'whatsapp_line_id' => $lineId,
                'team_member_id' => $this->actorContext->teamMemberId(),
                'team_member_name' => $this->actorContext->teamMemberName(),
                'payload' => $payload,
                'body_preview' => $bodyText,
            ]);
        } else {
            $needsUpdate = false;
            $currentBody = (string) ($template->wizardPayload()['body']['text'] ?? '');
            if (trim($currentBody) !== trim($bodyText)) {
                $payload = array_merge($template->wizardPayload(), $payload);
                $needsUpdate = true;
            }

            if (empty($template->code)) {
                $template->code = $name;
                $template->status = TemplateStatus::PendingReview;
                $needsUpdate = true;
            }

            if (strcasecmp((string) $template->category, $desired['category']) !== 0) {
                $template->category = $desired['category'];
                $needsUpdate = true;
            }

            if ($needsUpdate) {
                $template->payload = $payload;
                $template->body_preview = $bodyText;
                $template->save();
            }
        }

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

        return $template;
    }

    /**
     * @return array{body: string, category: string, buttons: list<array{text: string, type: string, url: string, flow_id: string}>}
     */
    private function desiredContent(bool $useV3): array
    {
        if ($useV3) {
            return [
                'body' => 'Hi {{full_name}}, to ensure a seamless experience, we are updating our communication settings. Please confirm your preference to receive standard updates, support responses, and regular notifications on WhatsApp.',
                'category' => 'UTILITY',
                'buttons' => [
                    ['text' => 'Yes, confirm', 'type' => 'quick_reply', 'url' => '', 'flow_id' => ''],
                    ['text' => 'No, thanks', 'type' => 'quick_reply', 'url' => '', 'flow_id' => ''],
                ],
            ];
        }

        return [
            'body' => "Hi {{full_name}}!\n"
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
