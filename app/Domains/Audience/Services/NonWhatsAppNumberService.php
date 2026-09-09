<?php

declare(strict_types=1);

namespace App\Domains\Audience\Services;

use App\Domains\Audience\Enums\ContactStatus;
use App\Enums\ContactOptInStatus;
use App\Models\Contact;
use Illuminate\Support\Facades\Log;

/**
 * WhatsApp error 131026 = Message undeliverable (number not on WhatsApp / unreachable).
 */
class NonWhatsAppNumberService
{
    public const ERROR_CODE = '131026';

    public const TAG = 'non whatsapp number';

    public function isUndeliverableCode(mixed $errorCode): bool
    {
        return str_contains((string) $errorCode, self::ERROR_CODE);
    }

    public function errorLooksLike131026(?string $error): bool
    {
        return filled($error) && str_contains((string) $error, self::ERROR_CODE);
    }

    public function markContact(Contact $contact): void
    {
        $dirty = false;

        if ($contact->status !== ContactStatus::Unsubscribed) {
            $contact->status = ContactStatus::Unsubscribed;
            $contact->opt_in_status = ContactOptInStatus::OptedOut;
            $contact->opted_out_at = $contact->opted_out_at ?? now();
            $dirty = true;
        }

        $existing = $contact->tags()->pluck('name')->all();
        if (! in_array(self::TAG, $existing, true)) {
            $contact->tags()->firstOrCreate(['name' => self::TAG]);
        }

        if ($dirty) {
            $contact->save();
        }

        Log::info('Contact marked non-WhatsApp (131026)', [
            'contact_id' => $contact->id,
            'phone' => $contact->phone,
        ]);
    }

    public function markByPhone(?string $phone): bool
    {
        $phone = trim((string) $phone);
        if ($phone === '') {
            return false;
        }

        $contact = Contact::query()->where('phone', $phone)->first();
        if (! $contact) {
            $digits = preg_replace('/\D+/', '', $phone) ?? '';
            if ($digits !== '') {
                $contact = Contact::query()->where('phone', 'like', '%'.$digits)->first();
            }
        }

        if (! $contact) {
            return false;
        }

        $this->markContact($contact);

        return true;
    }

    /**
     * Backfill from stored opt-in delivery errors.
     *
     * @return array{scanned: int, marked: int, skipped: int}
     */
    public function backfillFromHistory(?int $days = null): array
    {
        $scanned = 0;
        $marked = 0;
        $skipped = 0;

        $query = Contact::query()
            ->where('opt_in_message_delivery_error', 'like', '%'.self::ERROR_CODE.'%');

        if ($days !== null && $days > 0) {
            $query->where(function ($q) use ($days): void {
                $q->where('updated_at', '>=', now()->subDays($days))
                    ->orWhere('created_at', '>=', now()->subDays($days))
                    ->orWhere('opt_in_message_delivered_at', '>=', now()->subDays($days));
            });
        }

        $query->orderBy('id')->chunkById(200, function ($rows) use (&$scanned, &$marked, &$skipped): void {
            foreach ($rows as $contact) {
                $scanned++;
                $hadTag = $contact->tags()->where('name', self::TAG)->exists();
                $this->markContact($contact);
                if ($hadTag) {
                    $skipped++;
                } else {
                    $marked++;
                }
            }
        });

        return compact('scanned', 'marked', 'skipped');
    }
}
