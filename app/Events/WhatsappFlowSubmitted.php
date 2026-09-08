<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\WhatsappFlow;
use App\Models\WhatsappFlowSubmission;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WhatsappFlowSubmitted
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly WhatsappFlow $flow,
        public readonly WhatsappFlowSubmission $submission,
    ) {}
}
