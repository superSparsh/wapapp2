<?php

declare(strict_types=1);

namespace App\Domains\Audience\Console\Commands;

use App\Models\Contact;
use App\Support\Console\Concerns\IteratesTenants;
use Illuminate\Console\Command;

class PurgeSoftDeletedContactsCommand extends Command
{
    use IteratesTenants;

    protected $signature = 'audience:purge-soft-deleted-contacts
                            {--days=30 : Soft-deleted contacts older than this many days are permanently removed}
                            {--tenants=* : Tenant IDs to process}';

    protected $description = 'Permanently delete soft-deleted contacts older than the retention window (active contacts are never touched).';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $cutoff = now()->subDays($days);
        $purged = 0;

        $this->foreachTenant(function () use ($cutoff, &$purged): void {
            Contact::onlyTrashed()
                ->where('deleted_at', '<=', $cutoff)
                ->orderBy('id')
                ->chunkById(200, function ($contacts) use (&$purged): void {
                    foreach ($contacts as $contact) {
                        $contact->forceDelete();
                        $purged++;
                    }
                });
        });

        $this->info("Permanently deleted {$purged} soft-deleted contact(s) older than {$days} day(s).");

        return self::SUCCESS;
    }
}
