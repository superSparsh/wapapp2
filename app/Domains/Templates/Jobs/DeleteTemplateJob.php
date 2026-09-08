<?php

declare(strict_types=1);

namespace App\Domains\Templates\Jobs;

use App\Domains\Templates\Services\TemplateWhatsAppService;
use App\Models\Template;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DeleteTemplateJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 2;

    public function __construct(
        public int $templateId,
    ) {}

    public function handle(TemplateWhatsAppService $whatsappService): void
    {
        $template = Template::query()->withTrashed()->find($this->templateId);

        if (! $template instanceof Template) {
            return;
        }

        $deleted = $whatsappService->deleteTemplate($template);

        if ($deleted) {
            $template->forceDelete();
        }
    }
}
