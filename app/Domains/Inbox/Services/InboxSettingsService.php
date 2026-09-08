<?php

declare(strict_types=1);

namespace App\Domains\Inbox\Services;

use App\Domains\Inbox\Support\InboxActor;

class InboxSettingsService
{
    public function isPhoneMaskingEnabled(): bool
    {
        if (InboxActor::teamMember() === null) {
            return false;
        }

        $settings = tenant()?->settings ?? [];

        if (is_array($settings) && array_key_exists('inbox_phone_masking_enabled', $settings)) {
            return (bool) $settings['inbox_phone_masking_enabled'];
        }

        if (is_array($settings['inbox'] ?? null) && array_key_exists('phone_masking_enabled', $settings['inbox'])) {
            return (bool) $settings['inbox']['phone_masking_enabled'];
        }

        return (bool) config('inbox.phone_masking_enabled', false);
    }

    public function shouldMaskPhone(?string $phone): ?string
    {
        if (! $this->isPhoneMaskingEnabled()) {
            return $phone;
        }

        return \App\Domains\Inbox\Support\InboxPhoneMasker::mask($phone);
    }
}
