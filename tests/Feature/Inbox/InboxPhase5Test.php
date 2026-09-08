<?php

namespace Tests\Feature\Inbox;

use App\Domains\Inbox\Services\InboxMessageService;
use App\Domains\Team\Support\TeamPermissions;
use App\Enums\ConversationResponseType;
use App\Enums\RecordStatus;
use App\Enums\TeamMemberRole;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\TeamMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class InboxPhase5Test extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    private InboxMessageService $messageService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->messageService = app(InboxMessageService::class);
    }

    protected function tearDown(): void
    {
        $this->tearDownTenant();
        parent::tearDown();
    }

    public function test_team_member_cannot_open_unassigned_conversation(): void
    {
        $member = $this->createTeamMember();
        $conversation = $this->createConversation();

        $this->actingAsTeamMember($member)
            ->get(route('inbox.show', $conversation))
            ->assertForbidden();
    }

    public function test_team_member_can_open_assigned_conversation(): void
    {
        $member = $this->createTeamMember();
        $conversation = $this->createConversation([
            'assigned_team_member_id' => $member->id,
        ]);

        $this->messageService->recordInbound($conversation, 'Hello team');

        $this->actingAsTeamMember($member)
            ->get(route('inbox.show', $conversation))
            ->assertOk()
            ->assertSee('Hello team');
    }

    public function test_phone_numbers_are_masked_for_team_members_when_enabled(): void
    {
        $this->enablePhoneMasking();

        $member = $this->createTeamMember();
        $conversation = $this->createConversation([
            'assigned_team_member_id' => $member->id,
            'contact_phone' => '918888888899',
        ]);

        $this->actingAsTeamMember($member)
            ->get(route('inbox.show', $conversation))
            ->assertOk()
            ->assertSee('********8899', false);
    }

    public function test_ai_toggle_updates_conversation_response_type(): void
    {
        $conversation = $this->createConversation();
        $this->messageService->recordInbound($conversation, 'Inbound');

        $this->actingAsTenantUser()
            ->postJson(route('inbox.api.response-type', $conversation), [
                'ai_enabled' => true,
            ])
            ->assertOk()
            ->assertJsonPath('ai_enabled', true);

        $conversation->refresh();
        $this->assertSame(ConversationResponseType::Ai, $conversation->response_type);
    }

    public function test_export_conversation_returns_csv(): void
    {
        $conversation = $this->createConversation();
        $this->messageService->recordInbound($conversation, 'Export me');

        $response = $this->actingAsTenantUser()
            ->get(route('inbox.api.export', $conversation));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('Export me', $response->streamedContent());
    }

    public function test_toggle_all_response_type_updates_filtered_conversations(): void
    {
        $conversationA = $this->createConversation();
        $conversationB = $this->createConversation();

        $this->actingAsTenantUser()
            ->postJson(route('inbox.api.response-type-all'), [
                'ai_enabled' => true,
            ])
            ->assertOk()
            ->assertJsonPath('updated', 2);

        $this->assertSame(
            ConversationResponseType::Ai,
            $conversationA->fresh()->response_type,
        );
        $this->assertSame(
            ConversationResponseType::Ai,
            $conversationB->fresh()->response_type,
        );
    }

    private function createTeamMember(): TeamMember
    {
        return TeamMember::query()->create([
            'parent_user_id' => $this->testUser->id,
            'first_name' => 'Team',
            'last_name' => 'Agent',
            'email' => 'team-agent@test.test',
            'password' => Hash::make('password'),
            'role' => TeamMemberRole::Member,
            'status' => RecordStatus::Active,
            'permissions' => array_merge(TeamPermissions::defaults(), ['inbox_read' => true]),
            'assigned_whatsapp_line_ids' => [$this->testLine->id],
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createConversation(array $overrides = []): Conversation
    {
        $contact = Contact::factory()->create();

        return Conversation::factory()->create(array_merge([
            'whatsapp_line_id' => $this->testLine->id,
            'contact_id' => $contact->id,
            'contact_phone' => $contact->phone,
            'line_phone' => $this->testLine->phone,
            'contact_name' => $contact->name,
            'last_message_at' => now(),
            'response_type' => ConversationResponseType::Human->value,
        ], $overrides));
    }

    private function enablePhoneMasking(): void
    {
        $this->testTenant->forceFill([
            'settings' => ['inbox_phone_masking_enabled' => true],
        ])->save();

        tenancy()->end();
        tenancy()->initialize($this->testTenant);
    }
}
