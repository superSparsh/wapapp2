<?php

declare(strict_types=1);

namespace App\Domains\Templates\Support;

use App\Domains\Billing\Services\SubscriptionService;
use App\Models\Template;

class TemplateBuilderFlow
{
    public function __construct(
        private readonly SubscriptionService $subscriptionService,
    ) {}

    public function canUseCarousel(): bool
    {
        if (! config('templates.carousel_requires_advance_plan', true)) {
            return true;
        }

        $plan = $this->subscriptionService->currentPlan();
        if (! $plan) {
            return false;
        }

        $features = $plan->features ?? [];

        if (! empty($features['carousel_templates']) || ! empty($features['advance'])) {
            return true;
        }

        return in_array(strtolower((string) $plan->slug), ['advance', 'advanced'], true);
    }

    public function isAuthentication(Template $template): bool
    {
        return TemplateCategoryCatalog::isAuthentication((string) $template->category);
    }

    public function isCarousel(Template $template): bool
    {
        if (TemplateCategoryCatalog::isCarousel((string) $template->category)) {
            return true;
        }

        $payload = $template->wizardPayload();

        return (bool) ($payload['carousel']['enabled'] ?? false);
    }

    public function isLto(Template $template): bool
    {
        if (TemplateCategoryCatalog::isLto((string) $template->category)) {
            return true;
        }

        $payload = $template->wizardPayload();

        return (bool) ($payload['lto']['enabled'] ?? false);
    }

    /**
     * @return list<array{route: string, key: string, label: string, required?: bool}>
     */
    public function stepsFor(Template $template): array
    {
        $steps = [
            ['route' => 'templates.builder.body', 'key' => 'body', 'label' => 'Body', 'required' => true],
        ];

        if ($this->isAuthentication($template)) {
            $steps[] = ['route' => 'templates.builder.auth', 'key' => 'auth', 'label' => 'Authentication'];
            $steps[] = ['route' => 'templates.builder.submit', 'key' => 'submit', 'label' => 'Submit For Approval'];

            return $steps;
        }

        if ($this->isCarousel($template)) {
            $steps[] = ['route' => 'templates.builder.carousel', 'key' => 'carousel', 'label' => 'Carousel'];
            $steps[] = ['route' => 'templates.builder.submit', 'key' => 'submit', 'label' => 'Submit For Approval'];

            return $steps;
        }

        if ($this->isLto($template)) {
            $steps[] = ['route' => 'templates.builder.lto', 'key' => 'lto', 'label' => 'Limited Time Offer'];
        }

        return array_merge($steps, [
            ['route' => 'templates.builder.header', 'key' => 'header', 'label' => 'Header'],
            ['route' => 'templates.builder.footer', 'key' => 'footer', 'label' => 'Footer'],
            ['route' => 'templates.builder.buttons', 'key' => 'buttons', 'label' => 'Buttons'],
            ['route' => 'templates.builder.submit', 'key' => 'submit', 'label' => 'Submit For Approval'],
        ]);
    }

    public function nextRouteAfterBody(Template $template): string
    {
        if ($this->isAuthentication($template)) {
            return 'templates.builder.auth';
        }

        if ($this->isCarousel($template)) {
            abort_unless($this->canUseCarousel(), 403, 'Carousel templates require an advance plan.');

            return 'templates.builder.carousel';
        }

        if ($this->isLto($template)) {
            return 'templates.builder.lto';
        }

        return 'templates.builder.header';
    }

    public function nextRouteAfterLto(): string
    {
        return 'templates.builder.header';
    }

    public function nextRouteAfterAuth(): string
    {
        return 'templates.builder.submit';
    }

    public function nextRouteAfterCarousel(): string
    {
        return 'templates.builder.submit';
    }
}
