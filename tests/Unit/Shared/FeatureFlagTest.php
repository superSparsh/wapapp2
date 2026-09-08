<?php

declare(strict_types=1);

namespace Tests\Unit\Shared;

use App\Shared\Services\FeatureFlag;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class FeatureFlagTest extends TestCase
{
    private FeatureFlag $flags;

    protected function setUp(): void
    {
        parent::setUp();
        $this->flags = app(FeatureFlag::class);

        // Clean up any cached flags
        Cache::forget('feature_flag:chatbot:enhanced-conditions');
        Cache::forget('feature_flag:chatbot:carousel-template');
        Cache::forget('feature_flag:messaging:bulk-import');
        Cache::forget('feature_flag:messaging:ai-suggestions');
        Cache::forget('feature_flag:campaigns:scheduling');
        Cache::forget('feature_flag:test:flag');
    }

    public function test_disabled_by_default_when_not_in_config(): void
    {
        $this->assertFalse($this->flags->isEnabled('nonexistent.flag'));
    }

    public function test_reads_default_from_config(): void
    {
        // messaging.bulk-import has default=true in config/features.php
        $this->assertTrue($this->flags->isEnabled('messaging.bulk-import'));

        // chatbot.enhanced-conditions has default=false
        $this->assertFalse($this->flags->isEnabled('chatbot.enhanced-conditions'));
    }

    public function test_enable_overrides_default(): void
    {
        $this->assertFalse($this->flags->isEnabled('chatbot.enhanced-conditions'));

        $this->flags->enable('chatbot.enhanced-conditions');

        $this->assertTrue($this->flags->isEnabled('chatbot.enhanced-conditions'));
    }

    public function test_disable_overrides_default(): void
    {
        $this->assertTrue($this->flags->isEnabled('messaging.bulk-import'));

        $this->flags->disable('messaging.bulk-import');

        $this->assertFalse($this->flags->isEnabled('messaging.bulk-import'));
    }

    public function test_reset_removes_override(): void
    {
        $this->flags->enable('chatbot.enhanced-conditions');
        $this->assertTrue($this->flags->isEnabled('chatbot.enhanced-conditions'));

        $this->flags->reset('chatbot.enhanced-conditions');
        $this->assertFalse($this->flags->isEnabled('chatbot.enhanced-conditions'));
    }

    public function test_is_disabled_is_inverse_of_is_enabled(): void
    {
        $this->assertTrue($this->flags->isDisabled('chatbot.enhanced-conditions'));
        $this->assertFalse($this->flags->isDisabled('messaging.bulk-import'));
    }

    public function test_enable_with_ttl_expires(): void
    {
        // TTL of 1 second
        $this->flags->enable('chatbot.enhanced-conditions', ttl: 1);
        $this->assertTrue($this->flags->isEnabled('chatbot.enhanced-conditions'));

        // After 2 seconds, the cache should expire
        $this->travel(2)->seconds();

        $this->assertFalse($this->flags->isEnabled('chatbot.enhanced-conditions'));
    }

    public function test_all_returns_all_defined_features(): void
    {
        $all = $this->flags->all();

        $this->assertArrayHasKey('chatbot.enhanced-conditions', $all);
        $this->assertArrayHasKey('chatbot.carousel-template', $all);
        $this->assertArrayHasKey('messaging.bulk-import', $all);
        $this->assertArrayHasKey('campaigns.scheduling', $all);

        foreach ($all as $name => $info) {
            $this->assertArrayHasKey('enabled', $info);
            $this->assertArrayHasKey('description', $info);
            $this->assertArrayHasKey('default', $info);
        }
    }

    public function test_enabled_returns_only_enabled_features(): void
    {
        $enabled = $this->flags->enabled();

        $this->assertContains('messaging.bulk-import', $enabled);
        $this->assertContains('campaigns.scheduling', $enabled);
        $this->assertNotContains('chatbot.enhanced-conditions', $enabled);
    }
}
