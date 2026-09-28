<?php

declare(strict_types=1);

namespace App\Domains\Billing\Support;

/**
 * Admin-editable plan feature flags stored on Plan.features JSON.
 * Keys are stable for later customer-side gating.
 */
final class PlanFeatureCatalog
{
    /**
     * @return array<string, array{label: string, group: string, description?: string}>
     */
    public static function definitions(): array
    {
        return [
            // Basic
            'campaigns' => [
                'label' => 'Bulk Broadcast Campaigns',
                'group' => 'basic',
            ],
            'inbox' => [
                'label' => 'Smart Unified Inbox',
                'group' => 'basic',
            ],
            'drip' => [
                'label' => 'Drip Campaign Builder',
                'group' => 'basic',
            ],
            'segments' => [
                'label' => 'Contact Segmentation',
                'group' => 'basic',
            ],
            'analytics' => [
                'label' => 'Real-time Analytics',
                'group' => 'basic',
            ],
            'shopify_woocommerce' => [
                'label' => 'Shopify / WooCommerce Integration',
                'group' => 'basic',
            ],
            'whatsapp_api' => [
                'label' => 'WhatsApp Business API Access',
                'group' => 'basic',
            ],

            // Advance
            'advance' => [
                'label' => 'Advance plan (master)',
                'group' => 'advance',
                'description' => 'Marks this as an Advance-tier plan (used by carousel and future gates).',
            ],
            'ai_chatbot' => [
                'label' => 'AI Chatbot & Smart Assistants',
                'group' => 'advance',
            ],
            'whatsapp_flows' => [
                'label' => 'Interactive WhatsApp Flows',
                'group' => 'advance',
            ],
            'carousel_templates' => [
                'label' => 'Carousel Templates',
                'group' => 'advance',
            ],
            'appointments' => [
                'label' => 'Appointment Booking System',
                'group' => 'advance',
            ],
            'form_builder' => [
                'label' => 'Custom Form Builder',
                'group' => 'advance',
            ],
            'commerce' => [
                'label' => 'WhatsApp Commerce',
                'group' => 'advance',
            ],
            'ai_suggestions' => [
                'label' => 'AI Response Suggestions',
                'group' => 'advance',
            ],
            'international_messaging' => [
                'label' => 'International Messaging',
                'group' => 'advance',
            ],
            'full_api_access' => [
                'label' => 'Full API Access',
                'group' => 'advance',
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_keys(self::definitions());
    }

    /**
     * @return array<string, array{label: string, group: string, description?: string}>
     */
    public static function forGroup(string $group): array
    {
        return array_filter(
            self::definitions(),
            static fn (array $def): bool => ($def['group'] ?? '') === $group,
        );
    }

    /**
     * Defaults for a new Basic-style plan (all basic on, advance off).
     *
     * @return array<string, bool>
     */
    public static function basicDefaults(): array
    {
        $features = [];
        foreach (self::definitions() as $key => $def) {
            $features[$key] = ($def['group'] ?? '') === 'basic';
        }

        return $features;
    }

    /**
     * Defaults for an Advance-style plan (everything on).
     *
     * @return array<string, bool>
     */
    public static function advanceDefaults(): array
    {
        $features = [];
        foreach (self::keys() as $key) {
            $features[$key] = true;
        }

        return $features;
    }

    /**
     * Merge submitted feature toggles into existing features JSON (keeps legacy keys).
     *
     * @param  array<string, mixed>  $existing
     * @param  array<string, mixed>  $submitted  keyed by feature name => truthy
     * @return array<string, mixed>
     */
    public static function mergeSubmitted(array $existing, array $submitted): array
    {
        foreach (self::keys() as $key) {
            $existing[$key] = filter_var($submitted[$key] ?? false, FILTER_VALIDATE_BOOLEAN);
        }

        // Keep legacy alias keys in sync for existing consumers.
        $existing['ai_response'] = (bool) ($existing['ai_chatbot'] ?? false);
        $existing['is_international_plan'] = (bool) ($existing['international_messaging'] ?? false);

        return $existing;
    }

    /**
     * @param  array<string, mixed>|null  $features
     */
    public static function enabledCount(?array $features): int
    {
        if (! is_array($features)) {
            return 0;
        }

        $count = 0;
        foreach (self::keys() as $key) {
            if (! empty($features[$key])) {
                $count++;
            }
        }

        return $count;
    }
}
