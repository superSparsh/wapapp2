<?php

declare(strict_types=1);

namespace App\Domains\Audience\Services;

use App\Domains\Audience\Enums\ContactStatus;
use App\Domains\Audience\Support\UnsubscribeToken;
use App\Enums\CampaignRecipientStatus;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\Contact;
use App\Models\Tenant;
use App\Support\PhoneNormalizer;

class PublicUnsubscribeService
{
    public function confirm(string $token): string
    {
        $parsed = UnsubscribeToken::parse($token);
        if ($parsed === null) {
            return 'invalid';
        }

        $tenant = Tenant::query()->find($parsed['tenant_id']);
        if (! $tenant instanceof Tenant) {
            return 'invalid';
        }

        $wasInitialized = tenancy()->initialized;
        $previous = $wasInitialized ? tenant() : null;

        if (! $wasInitialized || (string) tenant('id') !== (string) $tenant->id) {
            if ($wasInitialized) {
                tenancy()->end();
            }
            tenancy()->initialize($tenant);
        }

        try {
            $contact = Contact::query()->where('uuid', $parsed['contact_uuid'])->first();
            if (! $contact instanceof Contact) {
                return 'invalid';
            }

            if ($contact->status === ContactStatus::Unsubscribed) {
                return 'already';
            }

            $contact->unsubscribe();
            $this->markCampaignRecipientsUnsubscribed((string) $contact->phone);

            return 'done';
        } finally {
            if (! $wasInitialized) {
                if (tenancy()->initialized) {
                    tenancy()->end();
                }
            } elseif ($previous !== null && (string) tenant('id') !== (string) $previous->id) {
                tenancy()->end();
                tenancy()->initialize($previous);
            }
        }
    }

    private function markCampaignRecipientsUnsubscribed(string $phone): void
    {
        $variants = PhoneNormalizer::lookupVariants($phone);
        if ($variants === []) {
            $variants = [$phone];
        }

        $recipients = CampaignRecipient::query()
            ->whereIn('contact_phone', $variants)
            ->whereIn('status', [
                CampaignRecipientStatus::Pending->value,
                CampaignRecipientStatus::Sent->value,
            ])
            ->get(['id', 'campaign_id']);

        if ($recipients->isEmpty()) {
            return;
        }

        CampaignRecipient::query()
            ->whereIn('id', $recipients->pluck('id'))
            ->update([
                'status' => CampaignRecipientStatus::Unsubscribed->value,
                'unsubscribed_at' => now(),
            ]);

        foreach ($recipients->groupBy('campaign_id') as $campaignId => $rows) {
            Campaign::query()
                ->whereKey($campaignId)
                ->increment('total_unsubscribed', $rows->count());
        }
    }
}
