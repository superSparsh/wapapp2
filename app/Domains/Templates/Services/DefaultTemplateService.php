<?php

declare(strict_types=1);

namespace App\Domains\Templates\Services;

use App\Domains\Templates\Enums\TemplateSource;
use App\Domains\Templates\Enums\TemplateStatus;
use App\Domains\Templates\Support\VariableActorContext;
use App\Models\Template;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DefaultTemplateService
{
    /**
     * Default template definitions shipped with the platform.
     *
     * @var array<int, array{name: string, category: string, language: string, body: string, footer?: string, buttons?: array}>
     */
    private const DEFAULT_TEMPLATES = [
        [
            'name' => 'welcome_message',
            'category' => 'MARKETING',
            'language' => 'en_GB',
            'body' => "Hi {{full_name}}!\nWelcome to our WhatsApp channel. Stay tuned for exclusive offers, updates, and more.\nReply STOP to unsubscribe.",
            'footer' => 'Tap Stop promotions to opt out',
            'button_mode' => 'quick_reply',
            'is_opt_out' => true,
            'buttons' => [
                ['text' => 'Stop promotions', 'type' => 'quick_reply', 'url' => '', 'flow_id' => ''],
            ],
        ],
        [
            'name' => 'order_confirmation',
            'category' => 'UTILITY',
            'language' => 'en_GB',
            'body' => "Hi {{full_name}},\nYour order #{{order_id}} has been confirmed.\nTrack your order or contact us for any questions.",
            'footer' => 'Order Support',
            'button_mode' => 'call_to_action',
            'is_opt_out' => false,
            'buttons' => [
                ['text' => 'Track Order', 'type' => 'url', 'url' => 'https://example.com/track/{{order_id}}', 'flow_id' => ''],
                ['text' => 'Call Us', 'type' => 'phone', 'url' => '+919876543210', 'flow_id' => ''],
            ],
        ],
        [
            'name' => 'appointment_reminder',
            'category' => 'UTILITY',
            'language' => 'en_GB',
            'body' => "Hi {{full_name}},\nThis is a reminder for your appointment on {{appointment_date}} at {{appointment_time}}.\nPlease confirm or reschedule.",
            'footer' => 'Reply with Confirm or Reschedule',
            'button_mode' => 'quick_reply',
            'is_opt_out' => false,
            'buttons' => [
                ['text' => 'Confirm', 'type' => 'quick_reply', 'url' => '', 'flow_id' => ''],
                ['text' => 'Reschedule', 'type' => 'quick_reply', 'url' => '', 'flow_id' => ''],
            ],
        ],
    ];

    public function __construct(
        private readonly VariableActorContext $actorContext,
    ) {}

    /**
     * Create default starter templates for a tenant.
     */
    public function createDefaults(): array
    {
        $created = [];
        $lineId = $this->actorContext->whatsappLineId();

        foreach (self::DEFAULT_TEMPLATES as $definition) {
            // Skip if already exists
            $existing = Template::query()
                ->where('name', $definition['name'])
                ->when($lineId, fn ($q) => $q->where('whatsapp_line_id', $lineId))
                ->exists();

            if ($existing) {
                continue;
            }

            $payload = Template::defaultPayload();
            $payload['meta'] = [
                'name' => $definition['name'],
                'category' => $definition['category'],
                'language' => $definition['language'],
            ];
            $payload['body'] = [
                'text' => $definition['body'],
                'samples' => [],
            ];
            $payload['footer'] = [
                'text' => $definition['footer'] ?? '',
            ];
            $payload['button_mode'] = $definition['button_mode'] ?? 'call_to_action';
            $payload['is_opt_out'] = $definition['is_opt_out'] ?? false;
            $payload['buttons'] = $definition['buttons'] ?? [];

            $template = Template::query()->create([
                'name' => $definition['name'],
                'code' => Str::slug($definition['name'], '_'),
                'language' => $definition['language'],
                'category' => $definition['category'],
                'status' => TemplateStatus::Draft,
                'source' => TemplateSource::Local,
                'whatsapp_line_id' => $lineId,
                'team_member_id' => $this->actorContext->teamMemberId(),
                'team_member_name' => $this->actorContext->teamMemberName(),
                'payload' => $payload,
                'body_preview' => $definition['body'],
            ]);

            $created[] = $template;
        }

        return $created;
    }
}
