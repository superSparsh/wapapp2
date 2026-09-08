<?php

declare(strict_types=1);

namespace App\Models;

use App\Domains\Audience\Enums\ContactStatus;
use App\Domains\Audience\Models\Segment;
use App\Enums\RecordStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MailList extends TenantModel
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'status',
        'embedded_form_options',
    ];

    protected function casts(): array
    {
        return [
            'status' => RecordStatus::class,
            'embedded_form_options' => 'array',
        ];
    }

    // ─── Relationships ──────────────────────────────────────────────

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class, 'mail_list_id');
    }

    public function signupForms(): HasMany
    {
        return $this->hasMany(SignupForm::class, 'list_id');
    }

    public function segments(): HasMany
    {
        return $this->hasMany(Segment::class, 'mail_list_id');
    }

    public function listFields(): HasMany
    {
        return $this->hasMany(\App\Domains\Audience\Models\ListField::class, 'mail_list_id');
    }

    // ─── Count Helpers ──────────────────────────────────────────────

    public function subscribedCount(): int
    {
        return $this->contacts()
            ->where('status', ContactStatus::Subscribed)
            ->count();
    }

    public function unsubscribedCount(): int
    {
        return $this->contacts()
            ->where('status', ContactStatus::Unsubscribed)
            ->count();
    }

    public function blacklistedCount(): int
    {
        return $this->contacts()
            ->where('status', ContactStatus::Blacklisted)
            ->count();
    }

    public function totalContactsCount(): int
    {
        return $this->contacts()->count();
    }
}
