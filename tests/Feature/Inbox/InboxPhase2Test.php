<?php

namespace Tests\Feature\Inbox;

use App\Domains\Inbox\Services\InboxAssignmentService;
use App\Domains\Inbox\Services\InboxMessageService;
use App\Domains\Inbox\Services\InboxQueryService;
use App\Enums\RecordStatus;
use App\Enums\TeamMemberRole;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\TeamMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class InboxPhase2Test extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    private InboxMessageService $messageService;

    private InboxAssignmentService $assignmentService;

    private InboxQueryService $queryService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->messageService = app(InboxMessageService::class);
        $this->assignmentService = app(InboxAssignmentService::class);
        $this->queryService = app(InboxQueryService::class);
    }

    protected function tearDown(): void
    {
        $this->tearDownTenant();
        parent::tearDown();
    }

    public function test_it_assigns_conversation_to_team_member(): void
    {
        $member = TeamMember::query()->create([
            'parent_user_id' => $this->testUser->id,
            'first_name' => 'Agent',
            'last_name' => 'One',
            'email' => 'agent@test.test',
            'password' => Hash::make('password'),
            'role' => TeamMemberRole::Member,
            'status' => RecordStatus::Active,
        ]);

        $contact = Contact::factory()->create();
        $conversation = Conversation::factory()->create([
            'whatsapp_line_id' => $this->testLine->id,
            'contact_id' => $contact->id,
            'contact_phone' => $contact->phone,
            'line_phone' => $this->testLine->phone,
            'last_message_at' => now(),
        ]);

        $updated = $this->assignmentService->assign($conversation, 'member:'.$member->uuid);

        $this->assertSame($member->id, $updated->assigned_team_member_id);
        $this->assertNull($updated->assigned_user_id);
    }

    public function test_it_filters_mine_scope_for_owner(): void
    {
        $mineContact = Contact::factory()->create(['name' => 'Mine']);
        $otherContact = Contact::factory()->create(['name' => 'Other']);

        Conversation::factory()->create([
            'whatsapp_line_id' => $this->testLine->id,
            'contact_id' => $mineContact->id,
            'contact_phone' => $mineContact->phone,
            'line_phone' => $this->testLine->phone,
            'contact_name' => 'Mine',
            'assigned_user_id' => $this->testUser->id,
            'last_message_at' => now(),
        ]);

        Conversation::factory()->create([
            'whatsapp_line_id' => $this->testLine->id,
            'contact_id' => $otherContact->id,
            'contact_phone' => $otherContact->phone,
            'line_phone' => $this->testLine->phone,
            'contact_name' => 'Other',
            'last_message_at' => now()->subMinute(),
        ]);

        $this->actingAsTenantUser();

        $results = $this->queryService->paginateThreads($this->testLine, scope: 'mine');

        $this->assertCount(1, $results['items']);
        $this->assertSame('Mine', $results['items'][0]['name']);
    }

    public function test_mark_all_read_clears_filtered_unread_threads(): void
    {
        $contactA = Contact::factory()->create();
        $contactB = Contact::factory()->create();

        $conversationA = Conversation::factory()->create([
            'whatsapp_line_id' => $this->testLine->id,
            'contact_id' => $contactA->id,
            'contact_phone' => $contactA->phone,
            'line_phone' => $this->testLine->phone,
            'last_message_at' => now(),
        ]);

        $conversationB = Conversation::factory()->create([
            'whatsapp_line_id' => $this->testLine->id,
            'contact_id' => $contactB->id,
            'contact_phone' => $contactB->phone,
            'line_phone' => $this->testLine->phone,
            'last_message_at' => now(),
        ]);

        $this->messageService->recordInbound($conversationA, 'Unread A');
        $this->messageService->recordInbound($conversationB, 'Unread B');

        $updated = $this->messageService->markAllReadForLine(
            line: $this->testLine,
            queryService: $this->queryService,
        );

        $this->assertSame(2, $updated);
        $this->assertSame(0, $this->queryService->totalUnreadCount($this->testLine));
    }

    public function test_assign_api_updates_conversation(): void
    {
        $contact = Contact::factory()->create();
        $conversation = Conversation::factory()->create([
            'whatsapp_line_id' => $this->testLine->id,
            'contact_id' => $contact->id,
            'contact_phone' => $contact->phone,
            'line_phone' => $this->testLine->phone,
            'last_message_at' => now(),
        ]);

        $this->actingAsTenantUser()
            ->postJson(route('inbox.api.assign', $conversation), [
                'assignee' => 'user:'.$this->testUser->uuid,
            ])
            ->assertOk()
            ->assertJsonPath('assignee', 'user:'.$this->testUser->uuid);

        $conversation->refresh();
        $this->assertSame($this->testUser->id, $conversation->assigned_user_id);
    }
}
