<?php

declare(strict_types=1);

namespace Tests\Unit\Drip;

use App\Domains\Drip\Support\DripTriggerCatalog;
use Tests\TestCase;

class DripTriggerCatalogTest extends TestCase
{
    public function test_normalizes_legacy_trigger_aliases(): void
    {
        $this->assertSame('welcome-new-subscriber', DripTriggerCatalog::normalizeType('subscriber_optin'));
        $this->assertSame('api-3-0', DripTriggerCatalog::normalizeType('api'));
        $this->assertSame('specific-date', DripTriggerCatalog::normalizeType('date_based'));
    }

    public function test_tree_label_for_welcome_trigger(): void
    {
        $this->assertSame(
            'New contact subscribes to list',
            DripTriggerCatalog::treeLabel('welcome-new-subscriber'),
        );
    }

    public function test_sanitize_options_keeps_only_allowed_fields(): void
    {
        $options = DripTriggerCatalog::sanitizeOptions('specific-date', [
            'date' => '2026-08-01',
            'at' => '09:30',
            'tag_name' => 'ignored',
        ]);

        $this->assertSame([
            'date' => '2026-08-01',
            'at' => '09:30',
        ], $options);
    }
}
