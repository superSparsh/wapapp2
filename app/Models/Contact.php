<?php

declare(strict_types=1);

namespace App\Models;

use App\Domains\Audience\Enums\ContactStatus;
use App\Enums\ContactOptInStatus;
use App\Domains\Audience\Models\ContactTag;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Contact extends TenantModel
{
    use HasFactory;
    protected $fillable = [
        'phone',
        'name',
        'email',
        'country_code',
        'opt_in_status',
        'opted_in_at',
        'opted_out_at',
        'source',
        'mail_list_id',
        'custom_fields',
        'metadata',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => ContactStatus::class,
            'opt_in_status' => ContactOptInStatus::class,
            'opted_in_at' => 'datetime',
            'opted_out_at' => 'datetime',
            'custom_fields' => 'array',
            'metadata' => 'array',
        ];
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    public function tags(): HasMany
    {
        return $this->hasMany(ContactTag::class);
    }

    public function mailList(): BelongsTo
    {
        return $this->belongsTo(MailList::class, 'mail_list_id');
    }

    /**
     * @param  Builder<Contact>  $query
     * @return Builder<Contact>
     */
    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        $search = trim((string) $search);
        if ($search === '') {
            return $query;
        }

        return $query->where(function (Builder $builder) use ($search): void {
            $builder->where('name', 'like', '%'.$search.'%')
                ->orWhere('phone', 'like', '%'.$search.'%')
                ->orWhere('email', 'like', '%'.$search.'%');
        });
    }

    /**
     * @param  Builder<Contact>  $query
     * @return Builder<Contact>
     */
    public function scopeFilterByStatus(Builder $query, ?string $status): Builder
    {
        $status = trim((string) $status);
        if ($status === '') {
            return $query;
        }

        return $query->where('status', $status);
    }

    /**
     * @param  Builder<Contact>  $query
     * @return Builder<Contact>
     */
    public function scopeFilterByOptIn(Builder $query, ?string $optIn): Builder
    {
        $optIn = trim((string) $optIn);
        if ($optIn === '') {
            return $query;
        }

        return $query->where('opt_in_status', $optIn);
    }

    /**
     * @param  Builder<Contact>  $query
     * @return Builder<Contact>
     */
    public function scopeFilterByDateRange(Builder $query, ?string $dateFrom, ?string $dateTo): Builder
    {
        if (filled($dateFrom)) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        if (filled($dateTo)) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        return $query;
    }

    /**
     * @param  list<string>  $tags
     */
    public function syncTags(array $tags): void
    {
        $this->tags()->delete();

        foreach (array_unique(array_filter($tags)) as $tag) {
            $this->tags()->create(['name' => $tag]);
        }
    }
}
