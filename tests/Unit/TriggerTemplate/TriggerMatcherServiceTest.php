<?php

namespace Tests\Unit\TriggerTemplate;

use App\Domains\TriggerTemplate\Services\TriggerMatcherService;
use App\Models\TriggerVariable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class TriggerMatcherServiceTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    private TriggerMatcherService $matcher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->matcher = app(TriggerMatcherService::class);
    }

    protected function tearDown(): void
    {
        $this->tearDownTenant();
        parent::tearDown();
    }

    public function test_matches_keyword_inside_inbound_message(): void
    {
        $trigger = TriggerVariable::factory()->create([
            'variable_name' => 'hello',
            'template_code' => 'welcome_template',
        ]);

        $match = $this->matcher->match(
            collect([$trigger]),
            'Hi there, hello world',
            false,
        );

        $this->assertNotNull($match);
        $this->assertSame('hello', $match->variable_name);
    }

    public function test_prefers_longer_keyword_over_shorter_overlap(): void
    {
        $short = TriggerVariable::factory()->create(['variable_name' => 'book']);
        $long = TriggerVariable::factory()->create(['variable_name' => 'book_now']);

        $match = $this->matcher->match(
            collect([$short, $long]),
            'I want to book_now please',
            false,
        );

        $this->assertNotNull($match);
        $this->assertSame('book_now', $match->variable_name);
    }

    public function test_short_substring_keyword_does_not_steal_unrelated_words(): void
    {
        $short = TriggerVariable::factory()->create(['variable_name' => 'i']);
        $hi = TriggerVariable::factory()->create(['variable_name' => 'hi']);

        $this->assertSame(
            'hi',
            $this->matcher->match(collect([$short, $hi]), 'hi', false)?->variable_name,
        );

        // "this" must not match trigger "hi" or lone "i" as a stolen substring reply
        $this->assertNull($this->matcher->match(collect([$short, $hi]), 'this', false));
    }

    public function test_prefers_exact_match_over_longer_partial(): void
    {
        $partial = TriggerVariable::factory()->create(['variable_name' => 'help desk']);
        $exact = TriggerVariable::factory()->create(['variable_name' => 'help']);

        $match = $this->matcher->match(
            collect([$partial, $exact]),
            'help',
            false,
        );

        $this->assertNotNull($match);
        $this->assertSame('help', $match->variable_name);
    }

    public function test_any_message_trigger_only_fires_on_first_message(): void
    {
        $trigger = TriggerVariable::factory()->anyMessage()->create([
            'template_code' => 'welcome_template',
        ]);

        $first = $this->matcher->match(collect([$trigger]), 'Random first text', true);
        $second = $this->matcher->match(collect([$trigger]), 'Random second text', false);

        $this->assertNotNull($first);
        $this->assertNull($second);
    }

    public function test_returns_null_for_empty_message(): void
    {
        $trigger = TriggerVariable::factory()->create(['variable_name' => 'hello']);

        $this->assertNull($this->matcher->match(collect([$trigger]), '   ', false));
    }

    public function test_legacy_short_message_matches_longer_trigger_name(): void
    {
        $trigger = TriggerVariable::factory()->create([
            'variable_name' => 'pricing_info',
        ]);

        $match = $this->matcher->match(collect([$trigger]), 'pricing', false);

        $this->assertNotNull($match);
        $this->assertSame('pricing_info', $match->variable_name);
    }
}
