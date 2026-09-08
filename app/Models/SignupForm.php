<?php

declare(strict_types=1);

namespace App\Models;

use App\Domains\FormBuilder\Enums\FormStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class SignupForm extends TenantModel
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'status',
        'whatsapp_line_id',
        'list_id',
        'template_id',
        'team_member_id',
        'team_member_name',
        'fields',
        'logo_path',
        'embed_settings',
        'redirect_url',
        'custom_css',
        'embed_code',
        'submission_count',
        'sent_count',
        'read_count',
        'delivered_count',
        'failed_count',
    ];

    protected function casts(): array
    {
        return [
            'status' => FormStatus::class,
            'fields' => 'array',
            'embed_settings' => 'array',
            'submission_count' => 'integer',
            'sent_count' => 'integer',
            'read_count' => 'integer',
            'delivered_count' => 'integer',
            'failed_count' => 'integer',
        ];
    }

    // ── Relationships ──────────────────────────────────────────────

    public function whatsappLine(): BelongsTo
    {
        return $this->belongsTo(WhatsappLine::class);
    }

    public function mailList(): BelongsTo
    {
        return $this->belongsTo(MailList::class, 'list_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class, 'template_id');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(FormSubmission::class, 'signup_form_id');
    }

    // ── Payload helpers ────────────────────────────────────────────

    /**
     * Get embed settings with defaults.
     *
     * @return array<string, mixed>
     */
    public function embedSettings(): array
    {
        return array_merge(self::defaultEmbedSettings(), $this->embed_settings ?? []);
    }

    /**
     * Check if the form is active and enabled.
     */
    public function isActive(): bool
    {
        return $this->status === FormStatus::Active;
    }

    /**
     * Generate the public URL for this form.
     */
    public function publicUrl(): string
    {
        $tenantId = tenant('id');

        if (! is_string($tenantId) || $tenantId === '') {
            // Fallback for edge cases where tenancy isn't bootstrapped yet.
            $tenantId = (string) (tenancy()->tenant?->getTenantKey() ?? '');
        }

        abort_if($tenantId === '', 500, 'Unable to build public form URL without an active tenant.');

        return url('/form/'.$tenantId.'/'.$this->slug);
    }

    /**
     * Statistics as an array for display.
     *
     * @return array<string, int>
     */
    public function statsArray(): array
    {
        return [
            'sent' => $this->sent_count,
            'read' => $this->read_count,
            'delivered' => $this->delivered_count,
            'failed' => $this->failed_count,
            'total' => $this->submission_count,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaultEmbedSettings(): array
    {
        return [
            'show_required_only' => false,
            'include_js' => true,
            'include_css' => true,
            'show_invisible_fields' => false,
        ];
    }

    /**
     * Generate a unique slug for the form.
     */
    public static function generateSlug(string $name): string
    {
        $base = Str::of($name)
            ->trim()
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '-')
            ->replaceMatches('/^-+|-+$/', '')
            ->limit((int) config('form-builder.slug_length', 20))
            ->toString();

        if ($base === '') {
            $base = 'form';
        }

        $slug = $base;
        $count = 1;

        while (self::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$count;
            $count++;
        }

        return $slug;
    }

    /**
     * Generate embed code for external websites.
     * Mirrors legacy Embedded Form options (include JS / CSS / custom CSS).
     */
    public function generateEmbedCode(): string
    {
        $url = $this->publicUrl();
        $settings = $this->embedSettings();
        $parts = [];

        if (($settings['include_css'] ?? true) && filled($this->custom_css)) {
            $parts[] = '<style>'.trim((string) $this->custom_css).'</style>';
        }

        if ($settings['include_js'] ?? true) {
            $parts[] = '<div id="wapapp-form-'.$this->uuid.'"></div>';
            $parts[] = '<script src="'.$url.'/embed.js" async></script>';
        } else {
            $parts[] = '<iframe src="'.$url.'" title="'.htmlspecialchars($this->name, ENT_QUOTES, 'UTF-8').'" style="width:100%;min-height:600px;border:0;"></iframe>';
        }

        return implode("\n", $parts);
    }
}
