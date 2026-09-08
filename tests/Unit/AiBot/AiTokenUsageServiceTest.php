<?php

namespace Tests\Unit\AiBot;

use App\Domains\AiBot\Services\AiTokenUsageService;
use App\Models\AiBot;
use App\Models\AiTokenUsageLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class AiTokenUsageServiceTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    private AiTokenUsageService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->service = new AiTokenUsageService();
    }

    protected function tearDown(): void
    {
        $this->tearDownTenant();
        parent::tearDown();
    }

    public function test_log_creates_usage_record(): void
    {
        $bot = AiBot::factory()->create();

        $log = $this->service->log([
            'ai_bot_id' => $bot->id,
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'request_type' => 'chat',
            'prompt_tokens' => 100,
            'completion_tokens' => 50,
            'total_tokens' => 150,
        ]);

        $this->assertInstanceOf(AiTokenUsageLog::class, $log);
        $this->assertSame(150, $log->total_tokens);
        $this->assertDatabaseHas('ai_token_usage_logs', [
            'ai_bot_id' => $bot->id,
            'total_tokens' => 150,
        ]);
    }

    public function test_bot_stats_returns_aggregates(): void
    {
        $bot = AiBot::factory()->create();

        $this->service->log([
            'ai_bot_id' => $bot->id,
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'prompt_tokens' => 100,
            'completion_tokens' => 50,
            'total_tokens' => 150,
        ]);

        $this->service->log([
            'ai_bot_id' => $bot->id,
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'prompt_tokens' => 200,
            'completion_tokens' => 100,
            'total_tokens' => 300,
        ]);

        $stats = $this->service->botStats($bot->id);

        $this->assertSame(450, $stats['total_tokens']);
        $this->assertSame(2, $stats['request_count']);
    }

    public function test_estimates_cost_for_known_model(): void
    {
        $bot = AiBot::factory()->create();

        $log = $this->service->log([
            'ai_bot_id' => $bot->id,
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'prompt_tokens' => 1_000_000,
            'completion_tokens' => 1_000_000,
            'total_tokens' => 2_000_000,
        ]);

        $this->assertGreaterThan(0, (float) $log->estimated_cost_usd);
    }
}
