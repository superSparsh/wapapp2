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

    public function __construct(
        private readonly VariableActorContext $actorContext,
    ) {}

    /**
     * Ensure the opt-in template exists for the current tenant.
     * Creates it with the standard opt-in body and Yes/No/STOP quick replies.
     */
    public function ensureTemplate(): Template
    {
        $lineId = $this->actorContext->whatsappLineId();

        $template = Template::query()
            ->where('name', self::TEMPLATE_NAME)
            ->when($lineId, fn ($q) => $q->where('whatsapp_line_id', $lineId))
            ->first();

        $bodyText = "Hi {{full_name}}!\n"
            . "We want to make sure you never miss out on our latest updates and exclusive benefits. "
            . "By opting in, you will get instant access to special offers, seasonal promotions, "
            . "and important account alerts directly here on WhatsApp.\n"
            . "Would you like to stay connected with us?";

        $payload = Template::defaultPayload();
        $payload['meta'] = [
            'name' => self::TEMPLATE_NAME,
            'category' => 'MARKETING',
            'language' => 'en_GB',
        ];
        $payload['body'] = ['text' => $bodyText, 'samples' => []];
        $payload['footer'] = ['text' => ''];
        $payload['button_mode'] = 'quick_reply';
        $payload['is_opt_out'] = false;
        $payload['buttons'] = [
            ['text' => 'Yes', 'type' => 'quick_reply', 'url' => '', 'flow_id' => ''],
            ['text' => 'No', 'type' => 'quick_reply', 'url' => '', 'flow_id' => ''],
            ['text' => 'STOP', 'type' => 'quick_reply', 'url' => '', 'flow_id' => ''],
        ];

        if (! $template instanceof Template) {
            $template = Template::query()->create([
                'name' => self::TEMPLATE_NAME,
                'code' => self::TEMPLATE_NAME,
                'language' => 'en_GB',
                'category' => 'MARKETING',
                'status' => TemplateStatus::PendingReview,
                'whatsapp_line_id' => $lineId,
                'team_member_id' => $this->actorContext->teamMemberId(),
                'team_member_name' => $this->actorContext->teamMemberName(),
                'payload' => $payload,
                'body_preview' => $bodyText,
            ]);
        } else {
            // Ensure body and buttons are up to date
            $needsUpdate = false;

            $currentBody = (string) ($template->wizardPayload()['body']['text'] ?? '');
            if (trim($currentBody) !== trim($bodyText)) {
                $payload = array_merge($template->wizardPayload(), $payload);
                $needsUpdate = true;
            }

            if (empty($template->code)) {
                $template->status = TemplateStatus::PendingReview;
                $needsUpdate = true;
            }

            if ($needsUpdate) {
                $template->payload = $payload;
                $template->body_preview = $bodyText;
                $template->save();
            }
        }

        // Ensure the full_name variable exists and is linked
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
}
