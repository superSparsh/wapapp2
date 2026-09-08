<?php

declare(strict_types=1);

namespace App\Domains\Audience\Services;

use App\Models\MailList;
use Illuminate\Support\Facades\Log;

class ListVerificationService
{
    public function verify(MailList $mailList): array
    {
        Log::info('ListVerificationService: stub verification', [
            'mail_list_id' => $mailList->id,
        ]);

        return [
            'mail_list_id' => $mailList->id,
            'verified' => 0,
            'invalid' => 0,
            'status' => 'stub',
        ];
    }
}
