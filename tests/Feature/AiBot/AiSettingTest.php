<?php

namespace Tests\Feature\AiBot;

use App\Models\AiSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class AiSettingTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
    }

    protected function tearDown(): void
    {
        $this->tearDownTenant();
        parent::tearDown();
    }

    public function test_get_returns_default_when_missing(): void
    {
        $this->assertSame('fallback', AiSetting::get('non_existent_key', 'fallback'));
    }

    public function test_get_returns_null_when_no_default_given(): void
    {
        $this->assertNull(AiSetting::get('non_existent_key'));
    }

    public function test_set_creates_new_setting(): void
    {
        AiSetting::set('ai_auto_response_enabled', '1');

        $this->assertDatabaseHas('ai_settings', [
            'key' => 'ai_auto_response_enabled',
            'value' => '1',
        ]);

        $this->assertSame('1', AiSetting::get('ai_auto_response_enabled'));
    }

    public function test_set_updates_existing_setting(): void
    {
        AiSetting::set('ai_auto_response_enabled', '0');
        AiSetting::set('ai_auto_response_enabled', '1');

        $this->assertDatabaseHas('ai_settings', [
            'key' => 'ai_auto_response_enabled',
            'value' => '1',
        ]);

        $this->assertCount(1, AiSetting::query()->where('key', 'ai_auto_response_enabled')->get());
    }
}
