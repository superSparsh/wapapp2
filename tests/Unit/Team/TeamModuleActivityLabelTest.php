<?php

declare(strict_types=1);

namespace Tests\Unit\Team;

use App\Domains\Account\Services\ActivityLogService;
use App\Domains\Team\Support\TeamModuleActivityLabel;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TeamModuleActivityLabelTest extends TestCase
{
    #[DataProvider('labelProvider')]
    public function test_labels_are_plain_english(string $verb, string $route, string $expected): void
    {
        $this->assertSame($expected, TeamModuleActivityLabel::describe($verb, $route));
    }

    public function test_label_for_keeps_actor_suffix(): void
    {
        $label = ActivityLogService::labelFor(
            'team.module.POST.inbox.api.read',
            'Submitted Inbox Api Read (by Sohel Shaikh)',
        );

        $this->assertSame('Marked a chat as read (by Sohel Shaikh)', $label);
    }

    public function test_unmapped_routes_do_not_say_submitted_api(): void
    {
        $label = TeamModuleActivityLabel::describe('POST', 'inbox.api.something-new');

        $this->assertStringNotContainsString('Submitted', $label);
        $this->assertStringNotContainsString('Api', $label);
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function labelProvider(): array
    {
        return [
            'inbox read' => ['POST', 'inbox.api.read', 'Marked a chat as read'],
            'inbox send' => ['POST', 'inbox.api.send', 'Sent a chat message'],
            'login as' => ['POST', 'my-team.login-as', 'Logged in as a team member'],
            'campaign create' => ['POST', 'campaigns.store', 'Created a campaign'],
            'campaign delete' => ['DELETE', 'campaigns.destroy', 'Deleted a campaign'],
            'template submit' => ['POST', 'templates.builder.submit.save', 'Submitted a template for WhatsApp approval'],
            'chatbot publish' => ['POST', 'chatbot.publish', 'Published a chatbot'],
        ];
    }
}
