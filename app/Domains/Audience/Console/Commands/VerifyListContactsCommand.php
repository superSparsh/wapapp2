<?php

declare(strict_types=1);

namespace App\Domains\Audience\Console\Commands;

use App\Domains\Audience\Services\ListVerificationService;
use App\Models\MailList;
use App\Support\Console\Concerns\IteratesTenants;
use Illuminate\Console\Command;

class VerifyListContactsCommand extends Command
{
    use IteratesTenants;

    protected $signature = 'audience:verify-list-contacts {--tenants=* : Tenant IDs to process} {--list= : Mail list ID}';

    protected $description = 'Verify contacts in audience lists (stub).';

    public function handle(ListVerificationService $service): int
    {
        $verified = 0;

        $this->foreachTenant(function () use ($service, &$verified): void {
            $listId = $this->option('list');

            $query = MailList::query();

            if (is_string($listId) && $listId !== '') {
                $query->whereKey((int) $listId);
            }

            foreach ($query->get() as $mailList) {
                $service->verify($mailList);
                $verified++;
            }
        });

        $this->info("Verified {$verified} list(s) (stub).");

        return self::SUCCESS;
    }
}
