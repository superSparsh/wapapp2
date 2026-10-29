<?php

declare(strict_types=1);

namespace Tests\Feature\Inbox;

use App\Enums\MessageDirection;
use App\Enums\MessageStatus;
use App\Enums\MessageType;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\WalletAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class InboxFlowExportTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();

        WalletAccount::query()->create([
            'balance' => 500,
            'currency' => 'INR',
        ]);
    }

    protected function tearDown(): void
    {
        $this->tearDownTenant();
        parent::tearDown();
    }

    public function test_conversation_export_includes_flow_form_fields(): void
    {
        $contact = Contact::factory()->create([
            'name' => 'Riya Sharma',
            'phone' => '919876543210',
        ]);
        $conversation = Conversation::factory()->create([
            'whatsapp_line_id' => $this->testLine->id,
            'contact_id' => $contact->id,
            'contact_phone' => $contact->phone,
            'line_phone' => $this->testLine->phone,
            'contact_name' => $contact->name,
            'last_message_at' => now(),
        ]);

        Message::query()->create([
            'conversation_id' => $conversation->id,
            'body' => 'Form submitted: lead_form',
            'direction' => MessageDirection::Inbound,
            'message_type' => MessageType::Interactive,
            'status' => MessageStatus::Delivered,
            'metadata' => [
                'interactive_reply_type' => 'nfm_reply',
                'interactive' => [
                    'type' => 'nfm_reply',
                    'nfm_reply' => [
                        'name' => 'lead_form',
                        'body' => 'Sent',
                        'response_json' => json_encode([
                            'full_name' => 'Riya Sharma',
                            'email' => 'riya@example.com',
                            'city' => 'Mumbai',
                        ], JSON_THROW_ON_ERROR),
                    ],
                ],
            ],
            'created_at' => now(),
        ]);

        $response = $this->actingAsTenantUser()
            ->get(route('inbox.api.export', [
                'conversation' => $conversation,
                'content' => 'flow_responses',
            ]));

        $response->assertOk();
        $csv = $response->streamedContent();

        $this->assertStringContainsString('Flow Form Responses', $csv);
        $this->assertStringContainsString('Name,Phone,"Flow Name","Submitted At","Full Name",Email,City', $csv);
        $this->assertStringContainsString('Riya Sharma', $csv);
        $this->assertStringContainsString('919876543210', $csv);
        $this->assertStringContainsString('lead_form', $csv);
        $this->assertStringContainsString('riya@example.com', $csv);
        $this->assertStringContainsString('Mumbai', $csv);
    }
}
