<?php

declare(strict_types=1);

namespace App\Models;

use App\Domains\Templates\Enums\TemplateSource;
use App\Domains\Templates\Enums\TemplateStatus;
use App\Models\TemplateStatusLog as ModelsTemplateStatusLog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Template extends TenantModel
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'language',
        'category',
        'status',
        'source',
        'whatsapp_line_id',
        'team_member_id',
        'team_member_name',
        'payload',
        'body_preview',
        'rejection_reason',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => TemplateStatus::class,
            'source' => TemplateSource::class,
            'payload' => 'array',
            'synced_at' => 'datetime',
        ];
    }

    /**
     * Logs for every status transition of this template.
     */
    public function statusLogs(): HasMany
    {
        return $this->hasMany(ModelsTemplateStatusLog::class);
    }

    public function whatsappLine(): BelongsTo
    {
        return $this->belongsTo(WhatsappLine::class);
    }

    public function variables(): BelongsToMany
    {
        return $this->belongsToMany(Variable::class, 'template_variables')
            ->withPivot(['placement', 'position'])
            ->withTimestamps();
    }

    /** @return array<string, mixed> */
    public function wizardPayload(): array
    {
        return array_merge(self::defaultPayload(), $this->payload ?? []);
    }

    public function isSetupComplete(): bool
    {
        return (bool) ($this->wizardPayload()['meta']['setup_completed'] ?? false);
    }

    public function whatsappCode(): ?string
    {
        if (filled($this->code)) {
            return $this->code;
        }

        $archived = data_get($this->payload, 'meta.archived_code');

        return is_string($archived) && $archived !== '' ? $archived : null;
    }

    protected static function booted(): void
    {
        static::deleting(function (Template $template): void {
            if ($template->isForceDeleting() || ! filled($template->code)) {
                return;
            }

            $payload = $template->payload ?? [];
            $payload['meta'] = array_merge($payload['meta'] ?? [], [
                'archived_code' => $template->code,
            ]);

            $template->code = null;
            $template->payload = $payload;
            $template->saveQuietly();
        });
    }

    /** @return array<string, mixed> */
    public static function defaultPayload(): array
    {
        return [
            'meta' => [
                'name' => '',
                'category' => 'MARKETING',
                'language' => 'en_GB',
                'template_type' => 'regular',
                'setup_completed' => false,
            ],
            'header' => [
                'type' => 'none',
                'text' => '',
                'media_path' => null,
                'media_url' => null,
                'use_url' => false,
                'doc_name' => null,
            ],
            'body' => [
                'text' => '',
                'samples' => [],
            ],
            'footer' => [
                'text' => '',
            ],
            'buttons' => [],
            'button_mode' => '',
            'is_opt_out' => false,
            // Authentication template fields
            'auth' => [
                'enabled' => false,
                'copy_button_text' => 'Copy Code',
                'auto_fill' => false,
                'zero_tap' => false,
                'fill_button_text' => 'Autofill',
                'message_validity' => false,
                'validity_seconds' => 120,
                'expiration_time' => false,
                'expiration_minutes' => 120,
                'add_secret_recommendation' => false,
                'supported_apps' => [],
            ],
            // Limited Time Offer fields
            'lto' => [
                'enabled' => false,
                'discount_introduction' => '',
                'expiration_time' => false,
                'time_variable' => null,
                'coupon_code' => '',
            ],
            // Carousel fields
            'carousel' => [
                'enabled' => false,
                'cards' => [],
            ],
        ];
    }
}
