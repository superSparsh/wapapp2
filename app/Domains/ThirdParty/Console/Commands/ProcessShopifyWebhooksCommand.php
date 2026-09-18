<?php

declare(strict_types=1);

namespace App\Domains\ThirdParty\Console\Commands;

use App\Domains\Admin\Support\RespectsMaintenanceModules;
use App\Domains\ThirdParty\Services\ShopifyWebhookProcessorService;
use Illuminate\Console\Command;

class ProcessShopifyWebhooksCommand extends Command
{
    use RespectsMaintenanceModules;
    protected $signature = 'shopify:process-webhooks {--limit=50 : Max events to process}';

    protected $description = 'Process pending Shopify webhook events and send mapped WhatsApp templates.';

    public function handle(ShopifyWebhookProcessorService $processor): int
    {
        if ($this->skipForMaintenance('shopify', 'Shopify:')) {
            return self::SUCCESS;
        }

        $count = $processor->processPending((int) $this->option('limit'));
        $this->info("Processed {$count} Shopify webhook event(s).");

        return self::SUCCESS;
    }
}
