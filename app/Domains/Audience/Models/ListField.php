<?php

declare(strict_types=1);

namespace App\Domains\Audience\Models;

use App\Models\MailList;
use App\Models\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ListField extends TenantModel
{
    protected $table = 'list_fields';

    protected $fillable = [
        'uuid',
        'mail_list_id',
        'label',
        'type',
        'tag',
        'default_value',
        'required',
        'visible',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'required' => 'boolean',
            'visible' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public const TYPE_TEXT = 'text';
    public const TYPE_NUMBER = 'number';
    public const TYPE_DROPDOWN = 'dropdown';
    public const TYPE_MULTISELECT = 'multiselect';
    public const TYPE_CHECKBOX = 'checkbox';
    public const TYPE_RADIO = 'radio';
    public const TYPE_DATE = 'date';
    public const TYPE_DATETIME = 'datetime';
    public const TYPE_TEXTAREA = 'textarea';

    public const TYPES = [
        self::TYPE_TEXT,
        self::TYPE_NUMBER,
        self::TYPE_DROPDOWN,
        self::TYPE_MULTISELECT,
        self::TYPE_CHECKBOX,
        self::TYPE_RADIO,
        self::TYPE_DATE,
        self::TYPE_DATETIME,
        self::TYPE_TEXTAREA,
    ];

    public const PROTECTED_TAGS = ['phone_number', 'FIRST_NAME', 'LAST_NAME', 'country_code'];

    // ─── Relationships ──────────────────────────────────────────────

    public function mailList(): BelongsTo
    {
        return $this->belongsTo(MailList::class, 'mail_list_id');
    }

    public function options(): HasMany
    {
        return $this->hasMany(ListFieldOption::class, 'list_field_id');
    }

    // ─── Helpers ────────────────────────────────────────────────────

    public function isProtected(): bool
    {
        return in_array($this->tag, self::PROTECTED_TAGS, true);
    }

    public function hasOptions(): bool
    {
        return in_array($this->type, [self::TYPE_DROPDOWN, self::TYPE_MULTISELECT, self::TYPE_RADIO, self::TYPE_CHECKBOX], true);
    }
}
