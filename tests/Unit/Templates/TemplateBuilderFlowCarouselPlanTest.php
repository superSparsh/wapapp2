<?php

declare(strict_types=1);

namespace Tests\Unit\Templates;

use App\Domains\Billing\Services\SubscriptionService;
use App\Domains\Templates\Support\TemplateBuilderFlow;
use App\Models\Plan;
use Mockery;
use Tests\TestCase;

final class TemplateBuilderFlowCarouselPlanTest extends TestCase
{
    public function test_ginger_advance_plan_enables_carousel(): void
    {
        $plan = new Plan([
            'name' => 'Ginger Advance',
            'slug' => 'ginger-advance',
            'features' => ['legacy_plan_id' => 1],
        ]);

        $subscriptions = Mockery::mock(SubscriptionService::class);
        $subscriptions->shouldReceive('currentPlan')->andReturn($plan);

        $flow = new TemplateBuilderFlow($subscriptions);

        $this->assertTrue($flow->canUseCarousel());
    }

    public function test_basic_plan_disables_carousel(): void
    {
        config(['templates.carousel_requires_advance_plan' => true]);

        $plan = new Plan([
            'name' => 'Starter',
            'slug' => 'starter',
            'features' => [],
        ]);

        $subscriptions = Mockery::mock(SubscriptionService::class);
        $subscriptions->shouldReceive('currentPlan')->andReturn($plan);

        $flow = new TemplateBuilderFlow($subscriptions);

        $this->assertFalse($flow->canUseCarousel());
    }
}
