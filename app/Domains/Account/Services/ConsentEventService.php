<?php

declare(strict_types=1);

namespace App\Domains\Account\Services;

use App\Enums\ConsentEventType;
use App\Models\ConsentEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class ConsentEventService
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public function log(
        ConsentEventType $eventType,
        string $purpose,
        ?string $subjectType = null,
        ?string $subjectId = null,
        ?string $subjectEmail = null,
        ?string $tenantId = null,
        string $channel = 'web',
        ?string $source = null,
        ?string $documentUrl = null,
        array $meta = [],
        ?Request $request = null,
    ): ?ConsentEvent {
        if (! $this->tableReady()) {
            return null;
        }

        $request ??= request();

        return ConsentEvent::query()->create([
            'tenant_id' => $tenantId,
            'subject_type' => $subjectType ?? 'unknown',
            'subject_id' => $subjectId,
            'subject_email' => $subjectEmail !== null ? strtolower($subjectEmail) : null,
            'event_type' => $eventType,
            'purpose' => $purpose,
            'channel' => $channel,
            'source' => $source,
            'document_url' => $documentUrl,
            'meta' => $meta !== [] ? $meta : null,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'occurred_at' => now(),
        ]);
    }

    public function tableReady(): bool
    {
        $connection = (string) config('tenancy.database.central_connection', config('database.default'));

        return Schema::connection($connection)->hasTable('consent_events');
    }
}
