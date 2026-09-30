<?php

declare(strict_types=1);

namespace App\Domains\Team\Support;

use Illuminate\Support\Str;

/**
 * Human-readable activity copy for team-member module mutations.
 * Prefer plain English over raw route names ("inbox.api.read" → "Marked a chat as read").
 */
final class TeamModuleActivityLabel
{
    public static function describe(string $verb, string $routeName): string
    {
        $verb = strtoupper($verb);

        if ($mapped = self::mappedDescription($verb, $routeName)) {
            return $mapped;
        }

        if ($patterned = self::patternDescription($verb, $routeName)) {
            return $patterned;
        }

        $subject = self::humanizeRoute($routeName);

        return match ($verb) {
            'POST' => 'Made a change in '.$subject,
            'PUT', 'PATCH' => 'Updated '.$subject,
            'DELETE' => 'Deleted '.$subject,
            default => ucfirst(strtolower($verb)).' '.$subject,
        };
    }

    /**
     * @return array{0: string, 1: string}|null [verb, routeName]
     */
    public static function parseAction(string $action): ?array
    {
        if (preg_match('/^team\.module\.(POST|PUT|PATCH|DELETE)\.(.+)$/i', $action, $matches) !== 1) {
            return null;
        }

        return [strtoupper($matches[1]), $matches[2]];
    }

    private static function mappedDescription(string $verb, string $routeName): ?string
    {
        $labels = [
            // Team
            'my-team.login-as' => ['POST' => 'Logged in as a team member'],
            'manager.team.login-as' => ['POST' => 'Logged in as a team member'],
            'my-team.store' => ['POST' => 'Added a team member'],
            'my-team.update' => ['PUT' => 'Updated a team member', 'PATCH' => 'Updated a team member'],
            'my-team.destroy' => ['DELETE' => 'Removed a team member'],
            'my-team.status' => ['POST' => 'Changed a team member status', 'PATCH' => 'Changed a team member status'],
            'my-team.roles.update' => ['PUT' => 'Updated team member roles', 'PATCH' => 'Updated team member roles'],
            'my-team.settings.update' => ['PUT' => 'Updated team settings', 'PATCH' => 'Updated team settings'],

            // Inbox
            'inbox.api.read' => ['POST' => 'Marked a chat as read'],
            'inbox.api.mark-all-read' => ['POST' => 'Marked all chats as read'],
            'inbox.api.send' => ['POST' => 'Sent a chat message'],
            'inbox.api.send-media' => ['POST' => 'Sent a media message'],
            'inbox.api.send-template' => ['POST' => 'Sent a template message'],
            'inbox.api.send-location' => ['POST' => 'Sent a location'],
            'inbox.api.send-sticker' => ['POST' => 'Sent a sticker'],
            'inbox.api.send-contact' => ['POST' => 'Shared a contact'],
            'inbox.api.send-flow' => ['POST' => 'Sent a WhatsApp flow'],
            'inbox.api.send-interactive' => ['POST' => 'Sent an interactive message'],
            'inbox.api.send-interactive-compose' => ['POST' => 'Sent an interactive message'],
            'inbox.api.request-payment' => ['POST' => 'Requested a payment'],
            'inbox.api.resend-opt-in' => ['POST' => 'Resent an opt-in message'],
            'inbox.api.assign' => ['POST' => 'Assigned a chat'],
            'inbox.api.destroy' => ['DELETE' => 'Deleted a chat'],
            'inbox.api.contacts.store' => ['POST' => 'Added an inbox contact'],
            'inbox.api.response-type' => ['POST' => 'Changed chat response mode'],
            'inbox.api.response-type-all' => ['POST' => 'Changed response mode for all chats'],
            'inbox.api.device-token.store' => ['POST' => 'Registered a notification device'],
            'inbox.api.device-token.destroy' => ['DELETE' => 'Removed a notification device'],

            // Campaigns
            'campaigns.store' => ['POST' => 'Created a campaign'],
            'campaigns.destroy' => ['DELETE' => 'Deleted a campaign'],
            'campaigns.duplicate' => ['POST' => 'Duplicated a campaign'],
            'campaigns.toggle' => ['PATCH' => 'Paused or resumed a campaign', 'POST' => 'Paused or resumed a campaign'],
            'campaigns.test-message' => ['POST' => 'Sent a campaign test message'],
            'campaigns.create.test-message' => ['POST' => 'Sent a campaign test message'],
            'campaigns.create.save' => ['POST' => 'Saved campaign draft'],
            'campaigns.create.variables.save' => ['POST' => 'Saved campaign variables'],
            'campaigns.create.variables.import' => ['POST' => 'Imported campaign variables'],
            'campaigns.import-recipients' => ['POST' => 'Imported campaign recipients'],
            'campaigns.resend-failed' => ['POST' => 'Resent failed campaign messages'],
            'campaigns.resend-opt-in' => ['POST' => 'Resent campaign opt-in messages'],
            'campaigns.create-delivered-list' => ['POST' => 'Created a list from delivered contacts'],
            'campaigns.webhooks.store' => ['POST' => 'Saved a campaign webhook'],

            // Templates
            'templates.builder.submit.save' => ['POST' => 'Submitted a template for WhatsApp approval'],
            'templates.builder.body.save' => ['POST' => 'Saved template body', 'PUT' => 'Updated template body', 'PATCH' => 'Updated template body'],
            'templates.builder.header.save' => ['POST' => 'Saved template header'],
            'templates.builder.footer.save' => ['POST' => 'Saved template footer'],
            'templates.builder.buttons.save' => ['POST' => 'Saved template buttons'],
            'templates.builder.auth.save' => ['POST' => 'Saved authentication template settings'],
            'templates.builder.lto.save' => ['POST' => 'Saved limited-time offer settings'],
            'templates.builder.carousel.save' => ['POST' => 'Saved carousel template'],
            'templates.builder.body-media.save' => ['POST' => 'Saved template media'],
            'templates.builder.header.media' => ['POST' => 'Uploaded template header media'],
            'templates.builder.carousel.media' => ['POST' => 'Uploaded carousel media'],
            'templates.destroy' => ['DELETE' => 'Deleted a template'],
            'templates.bulk-destroy' => ['POST' => 'Deleted multiple templates'],
            'templates.duplicate' => ['POST' => 'Duplicated a template'],
            'templates.free.store' => ['POST' => 'Created a free-form message'],
            'templates.free.update' => ['PUT' => 'Updated a free-form message', 'PATCH' => 'Updated a free-form message'],
            'templates.free.destroy' => ['DELETE' => 'Deleted a free-form message'],
            'templates.variables.store' => ['POST' => 'Created a template variable'],
            'templates.variables.update' => ['PUT' => 'Updated a template variable', 'PATCH' => 'Updated a template variable'],
            'templates.variables.destroy' => ['DELETE' => 'Deleted a template variable'],
            'templates.api.refresh' => ['POST' => 'Refreshed template statuses'],
            'templates.ai.suggest' => ['POST' => 'Generated AI template suggestions'],

            // Chatbot / drip
            'chatbot.store' => ['POST' => 'Created a chatbot'],
            'chatbot.update' => ['PUT' => 'Updated a chatbot', 'PATCH' => 'Updated a chatbot'],
            'chatbot.destroy' => ['DELETE' => 'Deleted a chatbot'],
            'chatbot.toggle' => ['PATCH' => 'Turned a chatbot on or off', 'POST' => 'Turned a chatbot on or off'],
            'chatbot.publish' => ['POST' => 'Published a chatbot'],
            'chatbot.duplicate' => ['POST' => 'Duplicated a chatbot'],
            'chatbot.import' => ['POST' => 'Imported a chatbot'],
            'chatbot.import.flow' => ['POST' => 'Imported a chatbot flow'],
            'chatbot.data.save' => ['POST' => 'Saved chatbot flow data'],
            'chatbot.media-upload' => ['POST' => 'Uploaded chatbot media'],
            'chatbot.clear-cache' => ['POST' => 'Cleared chatbot cache'],
            'automation.drip.store' => ['POST' => 'Created a drip campaign'],
            'automation.drip.destroy' => ['DELETE' => 'Deleted a drip campaign'],
            'automation.drip.duplicate' => ['POST' => 'Duplicated a drip campaign'],
            'automation.drip.toggle' => ['PATCH' => 'Turned a drip campaign on or off', 'POST' => 'Turned a drip campaign on or off'],
            'automation.drip.flow.save' => ['POST' => 'Saved drip campaign flow'],
            'automation.drip.design.update' => ['PUT' => 'Updated drip campaign design', 'PATCH' => 'Updated drip campaign design'],
            'automation.drip.audience.trigger' => ['POST' => 'Triggered drip campaign for an audience'],
            'automation.events.store' => ['POST' => 'Created an automation event'],
            'automation.events.destroy' => ['DELETE' => 'Deleted an automation event'],
            'trigger-template.store' => ['POST' => 'Created a trigger template'],
            'trigger-template.destroy' => ['DELETE' => 'Deleted a trigger template'],

            // Audience
            'audience.lists.store' => ['POST' => 'Created an audience list'],
            'audience.lists.update' => ['PUT' => 'Updated an audience list', 'PATCH' => 'Updated an audience list'],
            'audience.lists.destroy' => ['DELETE' => 'Deleted an audience list'],
            'audience.subscribers.store' => ['POST' => 'Added a contact'],
            'audience.subscribers.update' => ['PUT' => 'Updated a contact', 'PATCH' => 'Updated a contact'],
            'audience.subscribers.destroy' => ['DELETE' => 'Deleted a contact'],
            'audience.subscribers.bulk-delete' => ['POST' => 'Deleted multiple contacts'],
            'audience.subscribers.bulk-tags' => ['POST' => 'Updated contact tags'],
            'audience.subscribers.copy' => ['POST' => 'Copied contacts to another list'],
            'audience.subscribers.move' => ['POST' => 'Moved contacts to another list'],
            'audience.subscribers.subscribe' => ['POST' => 'Subscribed a contact'],
            'audience.subscribers.unsubscribe' => ['POST' => 'Unsubscribed a contact'],
            'audience.subscribers.import.store' => ['POST' => 'Imported contacts'],
            'audience.subscribers.export' => ['POST' => 'Exported contacts'],
            'audience.segments.store' => ['POST' => 'Created a segment'],
            'audience.segments.update' => ['PUT' => 'Updated a segment', 'PATCH' => 'Updated a segment'],
            'audience.segments.destroy' => ['DELETE' => 'Deleted a segment'],
            'audience.blacklist.store' => ['POST' => 'Added a number to blacklist'],
            'audience.blacklist.destroy' => ['DELETE' => 'Removed a number from blacklist'],
            'audience.blacklist.import' => ['POST' => 'Imported blacklist numbers'],
            'audience.list-fields.store' => ['POST' => 'Added a list field'],
            'audience.list-fields.update' => ['PUT' => 'Updated a list field', 'PATCH' => 'Updated a list field'],
            'audience.list-fields.destroy' => ['DELETE' => 'Deleted a list field'],
            'audience.forms.update' => ['PUT' => 'Updated an audience form', 'PATCH' => 'Updated an audience form'],

            // Forms / commerce / integrations / profile
            'form-builder.store' => ['POST' => 'Created a form'],
            'form-builder.update' => ['PUT' => 'Updated a form', 'PATCH' => 'Updated a form'],
            'form-builder.destroy' => ['DELETE' => 'Deleted a form'],
            'form-builder.toggle' => ['PATCH' => 'Turned a form on or off', 'POST' => 'Turned a form on or off'],
            'form-builder.upload-logo' => ['POST' => 'Uploaded a form logo'],
            'form-builder.api.bulk-destroy' => ['POST' => 'Deleted multiple forms'],
            'commerce.orders.status' => ['POST' => 'Updated an order status', 'PATCH' => 'Updated an order status'],
            'commerce.payments.create' => ['POST' => 'Created a payment request'],
            'commerce.settings.save' => ['POST' => 'Saved commerce settings'],
            'notifications.read' => ['POST' => 'Marked notifications as read'],
            'profile.update' => ['PUT' => 'Updated profile', 'PATCH' => 'Updated profile'],
            'profile.alerts.sync' => ['POST' => 'Updated alert settings'],
            'profile.api.renew' => ['POST' => 'Renewed API token'],
            'profile.integration.update' => ['PUT' => 'Updated WhatsApp business profile', 'PATCH' => 'Updated WhatsApp business profile'],
            'profile.integration.sync' => ['POST' => 'Synced WhatsApp business data'],
            'profile.phone-lines.login-as' => ['POST' => 'Switched to a WhatsApp number'],
            'profile.phone-lines.set-default' => ['POST' => 'Changed default WhatsApp number'],
            'profile.phone-lines.password' => ['POST' => 'Updated number access password'],
            'profile.phone-lines.add.store' => ['POST' => 'Added a WhatsApp number'],
            'profile.phone-lines.exit-context' => ['POST' => 'Exited number-specific access'],
            'profile.security.enable' => ['POST' => 'Enabled two-factor authentication'],
            'profile.security.disable' => ['POST' => 'Disabled two-factor authentication'],
            'profile.security.recovery' => ['POST' => 'Regenerated recovery codes'],
            'profile.subscription.cancel' => ['POST' => 'Cancelled subscription'],
            'profile.subscription.wallet-recharge' => ['POST' => 'Started a wallet recharge'],
            'profile.data-deletion.schedule' => ['POST' => 'Scheduled data deletion'],
            'profile.data-deletion.cancel' => ['POST' => 'Cancelled data deletion'],
            'profile.data-deletion.export' => ['POST' => 'Requested a data export'],
        ];

        return $labels[$routeName][$verb] ?? null;
    }

    /**
     * Guess a plain-English label from route name patterns when no explicit map exists.
     */
    private static function patternDescription(string $verb, string $routeName): ?string
    {
        $parts = self::routeParts($routeName);
        if ($parts === []) {
            return null;
        }

        $action = strtolower((string) end($parts));
        $module = self::moduleLabel($parts);
        $object = self::objectLabel($parts);

        return match ($action) {
            'send', 'send-media', 'send-template', 'send-location', 'send-sticker',
            'send-contact', 'send-flow', 'send-interactive', 'send-interactive-compose',
            'test-message' => 'Sent '.$object,
            'store', 'create', 'save' => 'Saved '.$object,
            'update', 'edit' => 'Updated '.$object,
            'destroy', 'delete', 'bulk-destroy', 'bulk-delete' => 'Deleted '.$object,
            'duplicate' => 'Duplicated '.$object,
            'toggle', 'toggle-default' => 'Changed '.$object.' setting',
            'publish' => 'Published '.$object,
            'import' => 'Imported '.$object,
            'export', 'export-all' => 'Exported '.$object,
            'assign' => 'Assigned '.$object,
            'read', 'mark-all-read' => 'Marked '.$object.' as read',
            'subscribe' => 'Subscribed '.$object,
            'unsubscribe' => 'Unsubscribed '.$object,
            'login-as' => 'Logged in as '.$object,
            'resend-failed', 'resend-opt-in' => 'Resent '.$object,
            'upload', 'upload-logo', 'media-upload' => 'Uploaded '.$object,
            'refresh' => 'Refreshed '.$object,
            'sync' => 'Synced '.$object,
            'request', 'request-payment' => 'Requested '.$object,
            default => match ($verb) {
                'DELETE' => 'Deleted '.$object,
                'PUT', 'PATCH' => 'Updated '.$object,
                'POST' => filled($module) ? 'Made a change in '.$module : null,
                default => null,
            },
        };
    }

    private static function humanizeRoute(string $routeName): string
    {
        $parts = self::routeParts($routeName);
        if ($parts === []) {
            return 'account';
        }

        return (string) Str::of(implode(' ', $parts))->squish()->lower();
    }

    /**
     * @return list<string>
     */
    private static function routeParts(string $routeName): array
    {
        $trimmed = preg_replace('/\.(save|store|update|destroy|create|edit|index)$/', '', $routeName) ?? $routeName;

        $parts = preg_split('/[._-]+/', $trimmed) ?: [];

        return array_values(array_filter(
            array_map(static fn ($part): string => strtolower(trim((string) $part)), $parts),
            static fn (string $part): bool => $part !== '' && ! in_array($part, [
                'api', 'web', 'http', 'v1', 'v2',
            ], true),
        ));
    }

    /**
     * @param  list<string>  $parts
     */
    private static function moduleLabel(array $parts): string
    {
        $first = $parts[0] ?? '';

        return match ($first) {
            'inbox' => 'inbox',
            'campaigns', 'campaign' => 'campaigns',
            'templates', 'template' => 'templates',
            'chatbot', 'automation' => 'automation',
            'audience' => 'audience',
            'form', 'forms', 'form-builder' => 'forms',
            'commerce' => 'commerce',
            'profile' => 'profile',
            'my', 'team', 'manager' => 'team',
            'integration', 'integrations' => 'integrations',
            'ai', 'openai', 'openai-key', 'ai-bots' => 'AI bots',
            'trigger', 'trigger-template' => 'trigger templates',
            'drip' => 'drip campaigns',
            'webhooks', 'webhook' => 'webhooks',
            'notifications', 'notification' => 'notifications',
            default => $first !== '' ? str_replace('-', ' ', $first) : 'account',
        };
    }

    /**
     * @param  list<string>  $parts
     */
    private static function objectLabel(array $parts): string
    {
        $module = self::moduleLabel($parts);

        $actionWords = [
            'send', 'store', 'create', 'save', 'update', 'edit', 'destroy', 'delete',
            'duplicate', 'toggle', 'publish', 'import', 'export', 'assign', 'read',
            'subscribe', 'unsubscribe', 'login', 'as', 'resend', 'upload', 'refresh',
            'sync', 'request', 'bulk', 'all', 'media', 'template', 'location', 'sticker',
            'contact', 'flow', 'interactive', 'compose', 'payment', 'opt', 'in', 'failed',
            'default', 'logo', 'cache', 'status', 'token', 'device',
        ];

        $meaningful = array_values(array_filter(
            $parts,
            static fn (string $part): bool => ! in_array($part, $actionWords, true)
                && $part !== ($parts[0] ?? null),
        ));

        if ($meaningful !== []) {
            $label = str_replace('-', ' ', implode(' ', array_slice($meaningful, 0, 3)));

            return match (true) {
                str_starts_with($label, 'a '), str_starts_with($label, 'an ') => $label,
                default => (preg_match('/^[aeiou]/i', $label) === 1 ? 'an ' : 'a ').$label,
            };
        }

        return match ($module) {
            'inbox' => 'a chat',
            'campaigns' => 'a campaign',
            'templates' => 'a template',
            'automation', 'drip campaigns' => 'an automation',
            'audience' => 'a contact list',
            'forms' => 'a form',
            'commerce' => 'a commerce setting',
            'profile' => 'profile',
            'team' => 'a team setting',
            'integrations' => 'an integration',
            'AI bots' => 'an AI bot',
            'trigger templates' => 'a trigger template',
            'notifications' => 'notifications',
            default => 'an item in '.$module,
        };
    }
}
